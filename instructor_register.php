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
    
    // Validation
    if (empty($first_name) || empty($second_name) || empty($mobile_number) || empty($password)) {
        $error_message = 'All required fields must be filled.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (empty($selected_subjects)) {
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
    <title>Instructor Registration | Lernerr</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }
        .custom-input {
            width: 100%;
            padding: 12px 16px;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s ease;
            outline: none;
        }
        .custom-input:focus {
            border-color: #6d28d9;
            box-shadow: 0 0 0 4px rgba(109, 40, 217, 0.1);
        }
        .custom-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            margin-left: 2px;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex items-center justify-center py-12 px-4 md:px-10">

    <div class="bg-white rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.1)] w-full max-w-[1400px] flex flex-col xl:flex-row overflow-hidden border border-white/20">
        
        <!-- Section 1: Sidebar (Purple) -->
        <div class="xl:w-1/4 bg-[#5B21B6] p-10 xl:p-14 text-white flex flex-col justify-between relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -mr-32 -mt-32 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-purple-400/10 rounded-full -ml-24 -mb-24 blur-2xl"></div>
            
            <div class="relative z-10">
                <h1 class="text-4xl xl:text-5xl font-black mb-4 leading-tight">Join as<br>Instructor</h1>
                <p class="text-purple-100 text-lg opacity-80 leading-relaxed max-w-[200px]">Share your knowledge and mentor students.</p>
            </div>
            
            <div class="relative z-10 space-y-10 my-12">
                <div class="flex items-start gap-4 group">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-xl shrink-0 group-hover:bg-purple-400 transition-colors">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-lg">Mentorship</h4>
                        <p class="text-sm text-purple-200/70">Guide students personally and build your brand.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 group">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-xl shrink-0 group-hover:bg-purple-400 transition-colors">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-lg">Flexible Schedule</h4>
                        <p class="text-sm text-purple-200/70">Accept requests and manage sessions on your own terms.</p>
                    </div>
                </div>
            </div>
            
            <div class="relative z-10 flex items-center justify-between text-xs font-medium text-purple-300">
                <span>&copy; <?php echo date('Y'); ?> Lernerr</span>
                <div class="flex gap-4">
                    <a href="#" class="hover:text-white transition">Terms</a>
                    <a href="#" class="hover:text-white transition">Privacy</a>
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="flex-1 p-20 text-center flex flex-col items-center justify-center">
                <div class="w-24 h-24 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-4xl mb-6 shadow-sm">
                    <i class="fas fa-check"></i>
                </div>
                <h2 class="text-3xl font-black text-slate-800 mb-4">Registration Successful!</h2>
                <p class="text-slate-500 mb-10 max-w-md"><?php echo $success_message; ?></p>
                <a href="login.php" class="bg-[#5B21B6] text-white px-10 py-4 rounded-2xl font-bold hover:bg-purple-700 transition shadow-xl shadow-purple-200">Go to Dashboard</a>
            </div>
        <?php else: ?>

        <form method="POST" action="" class="flex-1 flex flex-col xl:flex-row">
            
            <!-- Section 2: Basic Info -->
            <div class="xl:w-1/3 p-10 xl:p-14 space-y-8 border-r border-slate-50">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-xl">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-slate-800">Instructor Registration</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-0.5">Step 01: Profile Information</p>
                    </div>
                </div>

                <?php if ($error_message): ?>
                    <div class="bg-red-50 border border-red-100 text-red-600 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="custom-label">First Name</label>
                        <input type="text" name="first_name" required class="custom-input" placeholder="John" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="custom-label">Last Name</label>
                        <input type="text" name="second_name" required class="custom-input" placeholder="Doe" value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="custom-label">Mobile</label>
                        <input type="tel" name="mobile_number" required class="custom-input" placeholder="0712345678" value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="custom-label">WhatsApp</label>
                        <input type="tel" name="whatsapp_number" required class="custom-input" placeholder="0712345678" value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="custom-label">NIC Number</label>
                        <input type="text" name="nic_number" class="custom-input" placeholder="123456789V" value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="custom-label">Gender</label>
                        <select name="gender" class="custom-input cursor-pointer">
                            <option value="male" <?php echo(($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo(($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="custom-label">Postal Address</label>
                    <input type="text" name="address" class="custom-input" placeholder="City, District" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                </div>

                <div>
                    <label class="custom-label">Expected Hourly Rate (LKR)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">Rs.</span>
                        <input type="number" name="hourly_rate" required class="custom-input pl-12" placeholder="1500" value="<?php echo htmlspecialchars($_POST['hourly_rate'] ?? '1500'); ?>">
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2 ml-1">* Approximate fee per hour for mentoring sessions.</p>
                </div>
            </div>

            <!-- Section 3: Subject Selection (Lavender) -->
            <div class="xl:w-1/3 bg-[#F5F3FF] p-10 xl:p-14 space-y-8 flex flex-col">
                <div class="flex items-center gap-4 mb-2">
                    <div class="w-12 h-12 bg-white text-purple-600 rounded-2xl flex items-center justify-center text-xl shadow-sm">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-slate-800">Select Subjects</h2>
                        <p class="text-xs font-bold text-purple-400 uppercase tracking-widest mt-0.5">Step 02: Your Expertise</p>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto pr-2 space-y-10">
                    <div class="space-y-4">
                        <label class="block text-sm font-bold text-slate-700 ml-1">Subjects You Teach *</label>
                        <div class="grid grid-cols-1 gap-3">
                            <?php if (!empty($subjects)): ?>
                                <?php foreach ($subjects as $sub): ?>
                                    <label class="group flex items-center gap-4 bg-white px-5 py-4 rounded-2xl border-2 border-transparent cursor-pointer hover:border-purple-200 transition-all shadow-sm select-none has-[:checked]:border-purple-600 has-[:checked]:bg-purple-600 has-[:checked]:text-white">
                                        <div class="relative flex items-center">
                                            <input type="checkbox" name="subjects[]" value="<?php echo $sub['id']; ?>" class="peer opacity-0 absolute inset-0 cursor-pointer" <?php echo (isset($_POST['subjects']) && in_array($sub['id'], $_POST['subjects'])) ? 'checked' : ''; ?>>
                                            <div class="w-6 h-6 rounded-lg border-2 border-slate-200 peer-checked:border-white peer-checked:bg-white flex items-center justify-center transition-all">
                                                <i class="fas fa-check text-[10px] text-purple-600 opacity-0 peer-checked:opacity-100"></i>
                                            </div>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold leading-tight"><?php echo htmlspecialchars($sub['name']); ?></span>
                                            <?php if ($sub['code']): ?>
                                                <span class="text-[10px] font-black opacity-60 uppercase tracking-widest group-has-[:checked]:opacity-80"><?php echo htmlspecialchars($sub['code']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="bg-white/50 p-6 rounded-2xl border-2 border-dashed border-slate-200 text-center">
                                    <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">No subjects available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Security & Submit -->
            <div class="xl:w-1/4 p-10 xl:p-14 flex flex-col justify-between">
                <div class="space-y-8">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-slate-50 text-slate-600 rounded-2xl flex items-center justify-center text-xl">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-slate-800">Security</h2>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-0.5">Final Step</p>
                        </div>
                    </div>

                    <div>
                        <label class="custom-label">Password</label>
                        <input type="password" name="password" required class="custom-input" placeholder="••••••••">
                    </div>

                    <div>
                        <label class="custom-label">Confirm Password</label>
                        <input type="password" name="confirm_password" required class="custom-input" placeholder="••••••••">
                    </div>
                </div>

                <div class="space-y-6">
                    <button type="submit" name="register_instructor" class="w-full bg-[#5B21B6] hover:bg-purple-700 text-white font-black py-5 rounded-2xl shadow-[0_15px_30px_rgba(91,33,182,0.25)] transition transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-3">
                        REGISTER NOW
                        <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    
                    <div class="text-center">
                        <p class="text-sm text-slate-400 font-medium">
                            Already have an account? <br>
                            <a href="login.php" class="text-purple-600 font-black hover:underline mt-1 inline-block uppercase tracking-widest text-[11px]">Login here</a>
                        </p>
                    </div>
                </div>
            </div>

        </form>
        <?php endif; ?>
    </div>

</body>
</html>
