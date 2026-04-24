<?php
require_once 'config.php';
require_once 'whatsapp_config.php';

$page_title = "Instructor Registration";
$success_message = '';
$error_message = '';
$subjects = [];

// Fetch available subjects
$subjects_res = $conn->query("SELECT id, name, code FROM subjects WHERE status = 1 ORDER BY name");
if ($subjects_res) {
    while($row = $subjects_res->fetch_assoc()) $subjects[] = $row;
}

// Pre-calculate next User ID for display
$prefix_display = 'ins';
$stmt_display = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
$pattern_display = $prefix_display . '_%';
$stmt_display->bind_param("s", $pattern_display);
$stmt_display->execute();
$result_display = $stmt_display->get_result();

$next_num_display = 1000;
if ($result_display->num_rows > 0) {
    $last_user = $result_display->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], strlen($prefix_display) + 1));
    $next_num_display = max($last_num + 1, 1000);
}
$stmt_display->close();
$display_user_id = $prefix_display . '_' . str_pad($next_num_display, 4, '0', STR_PAD_LEFT);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_instructor'])) {
    
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nic_number = trim($_POST['nic_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
    $selected_subjects = $_POST['subjects'] ?? [];
    $other_subject = trim($_POST['other_subject'] ?? '');
    
    // Validation
    if (empty($first_name) || empty($second_name) || empty($mobile_number) || empty($password)) {
        $error_message = 'All required fields must be filled.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (empty($selected_subjects) && empty($other_subject)) {
        $error_message = 'Please select at least one subject you can teach.';
    } else {
        // Check for existing user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE mobile_number = ?");
        $stmt->bind_param("s", $mobile_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = 'Mobile Number already registered.';
        }
        $stmt->close();
        
        if (empty($error_message)) {
            // Generate User ID (ins_XXXX)
            $prefix = 'ins';
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
            $pattern = $prefix . '_%';
            $stmt->bind_param("s", $pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $next_num = 1000;
            if ($result->num_rows > 0) {
                $last_user = $result->fetch_assoc();
                $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
                $next_num = max($last_num + 1, 1000);
            }
            $stmt->close();
            
            $user_id = $prefix . '_' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'instructor';
            $approved = 0; // Requires Admin Approval
            
            // Insert User
            $stmt = $conn->prepare("INSERT INTO users (user_id, password, role, first_name, second_name, mobile_number, whatsapp_number, nic_no, address, gender, approved, registering_date, status, hourly_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, ?)");
            $stmt->bind_param("ssssssssssid", $user_id, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $nic_number, $address, $gender, $approved, $hourly_rate);
            
            if ($stmt->execute()) {
                // Handle "Other" subject
                if (!empty($other_subject)) {
                    $check_sub = $conn->prepare("SELECT id FROM subjects WHERE name = ?");
                    $check_sub->bind_param("s", $other_subject);
                    $check_sub->execute();
                    $sub_res = $check_sub->get_result();
                    if ($sub_row = $sub_res->fetch_assoc()) {
                        if (!in_array($sub_row['id'], $selected_subjects)) {
                            $selected_subjects[] = $sub_row['id'];
                        }
                    } else {
                        $ins_sub = $conn->prepare("INSERT INTO subjects (name, status) VALUES (?, 1)");
                        $ins_sub->bind_param("s", $other_subject);
                        $ins_sub->execute();
                        $selected_subjects[] = $ins_sub->insert_id;
                        $ins_sub->close();
                    }
                    $check_sub->close();
                }

                // Insert selected subjects
                $subject_stmt = $conn->prepare("INSERT INTO instructor_subjects (instructor_id, subject_id) VALUES (?, ?)");
                foreach ($selected_subjects as $sid) {
                    $sid_int = intval($sid);
                    $subject_stmt->bind_param("si", $user_id, $sid_int);
                    $subject_stmt->execute();
                }
                $subject_stmt->close();

                $success_message = "Registration successful! Your Instructor ID is $user_id. Please wait for Admin approval.";
                
                // Clear form
                $_POST = array();
                
                 // Send WhatsApp Notification (Welcome)
                 if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                    $msg = "👋 *Welcome to Lernerr - Instructor Portal*\n\n" .
                           "Hello $first_name,\n" .
                           "Your registration as an Instructor is successful.\n" .
                           "🆔 *ID:* $user_id\n" .
                           "⚠️ *Status:* Pending Admin Approval\n\n" .
                           "You will be notified once your account is approved.";
                    sendWhatsAppMessage($whatsapp_number, $msg);
                 }

            } else {
                $error_message = "Registration failed: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Registration - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .form-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #dadce0;
            margin-bottom: 12px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .form-card:first-of-type {
            border-top: 10px solid #6d28d9; /* Purple 700 */
        }
        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #202124;
            margin-bottom: 8px;
        }
        input[type="text"], input[type="tel"], input[type="number"], input[type="password"], select, textarea {
            width: 100%;
            padding: 12px 0;
            border: none;
            border-bottom: 1px solid #dadce0;
            background: transparent;
            font-size: 1rem;
            transition: border-bottom 0.2s ease-in-out;
        }
        input:focus, select:focus, textarea:focus {
            border-bottom: 2px solid #6d28d9;
            outline: none;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: #202124;
            margin-bottom: 8px;
        }
        .section-subtitle {
            font-size: 0.875rem;
            color: #5f6368;
            margin-bottom: 24px;
        }
        .btn-primary {
            background-color: #6d28d9;
            color: white;
            padding: 12px 32px;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.2s;
            cursor: pointer;
            border: none;
        }
        .btn-primary:hover {
            background-color: #5b21b6;
        }
        .subject-chip {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            margin: 4px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            font-size: 0.875rem;
        }
        .subject-chip input:checked + span {
            color: white;
        }
        .subject-chip:has(input:checked) {
            background: #6d28d9;
            border-color: #6d28d9;
            color: white;
        }
        .subject-chip input {
            display: none;
        }
    </style>
</head>
<body>
    
    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header Image Card -->
        <div class="form-card p-0 overflow-hidden !border-0 shadow-lg mb-8">
            <div class="h-4 w-full bg-purple-700"></div>
            <div class="p-8">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-purple-100 text-purple-700 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-xl"></i>
                    </div>
                    <h1 class="text-3xl font-normal text-[#202124]">Instructor Registration</h1>
                </div>
                <div class="text-[#202124] text-sm">
                    Join the Lernerr.LK team as an Instructor. Share your knowledge and mentor students.
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <span class="text-red-600 text-sm font-medium">* Required</span>
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="form-card !border-t-green-500 shadow-md">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-2xl">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Registration Successful!</h2>
                </div>
                <p class="text-gray-600 mb-8 whitespace-pre-line"><?php echo htmlspecialchars($success_message); ?></p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="login.php" class="btn-primary text-center">Login to Dashboard</a>
                    <a href="index.php" class="px-8 py-3 text-gray-600 hover:bg-gray-50 rounded font-medium text-center border border-gray-200 transition">Return Home</a>
                </div>
            </div>
        <?php else: ?>

            <?php if ($error_message): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 shadow-sm" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                
                <!-- SECTION 1: Profile Information -->
                <div class="form-card">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="section-title">Profile Information</h3>
                            <p class="section-subtitle">Basic details for your instructor profile</p>
                        </div>
                        <div class="bg-purple-50 text-purple-700 px-4 py-2 rounded-full text-sm font-bold border border-purple-100">
                            ID: <?php echo htmlspecialchars($display_user_id); ?>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label>First Name *</label>
                            <input type="text" name="first_name" required 
                                   placeholder="Your first name"
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Last Name *</label>
                            <input type="text" name="second_name" required 
                                   placeholder="Your last name"
                                   value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Mobile Number *</label>
                            <input type="tel" name="mobile_number" required 
                                   placeholder="07xxxxxxxx"
                                   value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>WhatsApp Number *</label>
                            <input type="tel" name="whatsapp_number" required 
                                   placeholder="07xxxxxxxx"
                                   value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>NIC Number</label>
                            <input type="text" name="nic_number" 
                                   placeholder="e.g. 199012345678"
                                   value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Gender *</label>
                            <select name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo(($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo(($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label>Postal Address</label>
                            <textarea name="address" rows="1" placeholder="City, District"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: Expertise & Rates -->
                <div class="form-card">
                    <h3 class="section-title">Expertise & Rates</h3>
                    <p class="section-subtitle">Select the subjects you teach and your hourly rate</p>
                    
                    <div class="space-y-8">
                        <div>
                            <label>Expected Hourly Rate (LKR) *</label>
                            <div class="relative">
                                <span class="absolute left-0 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                                <input type="number" name="hourly_rate" required 
                                       style="padding-left: 35px !important;"
                                       placeholder="1500" 
                                       value="<?php echo htmlspecialchars($_POST['hourly_rate'] ?? '1500'); ?>">
                            </div>
                        </div>

                        <div>
                            <label>Subjects You Teach *</label>
                            <div class="flex flex-wrap -m-1 mb-4">
                                <?php foreach ($subjects as $sub): ?>
                                    <label class="subject-chip">
                                        <input type="checkbox" name="subjects[]" value="<?php echo $sub['id']; ?>"
                                               <?php echo (isset($_POST['subjects']) && in_array($sub['id'], $_POST['subjects'])) ? 'checked' : ''; ?>>
                                        <span>
                                            <?php echo htmlspecialchars($sub['name']); ?>
                                            <?php if($sub['code']): ?>
                                                <small class="opacity-60 ml-1">(<?php echo htmlspecialchars($sub['code']); ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-6 border-t border-gray-100 pt-6">
                                <label class="text-sm text-gray-500 mb-2">Subject not in list? Add it below:</label>
                                <input type="text" name="other_subject" 
                                       placeholder="Enter new subject name"
                                       value="<?php echo htmlspecialchars($_POST['other_subject'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Security -->
                <div class="form-card">
                    <h3 class="section-title">Security</h3>
                    <p class="section-subtitle">Set a strong password for your account</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label>Password *</label>
                            <input type="password" name="password" required placeholder="Choose a password">
                        </div>
                        <div>
                            <label>Confirm Password *</label>
                            <input type="password" name="confirm_password" required placeholder="Repeat your password">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between py-6">
                    <a href="login.php" class="text-sm font-bold text-purple-700 hover:underline">Already have an account? Login here</a>
                    <button type="submit" name="register_instructor" class="btn-primary shadow-lg shadow-purple-200 transform active:scale-95 transition">
                        Submit Registration
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>
