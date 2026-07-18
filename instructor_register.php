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
                    $msg = "👋 *Welcome to Lernerr.LK - Instructor Portal*\n\n" .
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
    <title>Instructor Registration | Lernerr.LK</title>
    <meta name="description" content="Register as an instructor on Lernerr.LK, the best online learning platform in Sri Lanka. Manage sessions, stream classes, and support student success.">
    <meta name="keywords" content="Lernerr.LK instructor registration, become an instructor Lernerr.LK, online coaching Sri Lanka">
    <meta name="author" content="Lernerr.LK">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Instructor Registration | Lernerr.LK">
    <meta property="og:description" content="Register as an instructor on Lernerr.LK, the best online learning platform in Sri Lanka.">
    <meta property="og:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">
    <meta property="og:site_name" content="Lernerr.LK">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="Instructor Registration | Lernerr.LK">
    <meta property="twitter:description" content="Register as an instructor on Lernerr.LK, the best online learning platform in Sri Lanka.">
    <meta property="twitter:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assests/favicon-16x16.png">
    <link rel="manifest" href="assests/site.webmanifest">
    <link rel="shortcut icon" href="assests/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Sinhala:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #fff;
            min-height: 100vh;
            font-family: 'Inter', 'Noto Sans Sinhala', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
            overflow-x: hidden;
            position: relative;
        }

        /* Premium Colorful Background Design */
        .bg-design {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #f3e8ff 0%, #fff 100%); /* Purple tinted gradient */
            overflow: hidden;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            z-index: -1;
            animation: move 20s infinite alternate ease-in-out;
        }

        .blob-1 {
            width: 500px;
            height: 500px;
            background: #f3e8ff; /* purple-100 */
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }

        .blob-2 {
            width: 400px;
            height: 400px;
            background: #e9d5ff; /* purple-200 */
            bottom: -50px;
            right: -50px;
            animation-delay: -5s;
        }

        .blob-3 {
            width: 300px;
            height: 300px;
            background: #f5f3ff; /* violet-50 */
            top: 40%;
            right: 10%;
            animation-delay: -10s;
        }

        @keyframes move {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(100px, 100px) scale(1.1); }
        }

        .registration-container {
            width: 100%;
            max-width: 600px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            z-index: 10;
            margin: 0 auto;
        }

        @media (max-width: 640px) {
            body {
                padding: 20px 16px;
                display: block;
            }
            .registration-container {
                padding: 24px 20px;
                border-radius: 20px;
                margin-top: 20px;
                margin-bottom: 20px;
            }
        }

        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .google-input-group {
            position: relative;
            margin-bottom: 24px;
        }

        .google-input {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: white;
        }

        .google-input:focus {
            border-color: #6d28d9; /* Purple accent color */
            outline: none;
            box-shadow: 0 0 0 1px #6d28d9;
        }

        .google-label {
            position: absolute;
            left: 12px;
            top: 13px;
            padding: 0 4px;
            background: white;
            color: #5f6368;
            font-size: 16px;
            pointer-events: none;
            transition: all 0.2s;
        }

        .google-input:focus+.google-label,
        .google-input:not(:placeholder-shown)+.google-label {
            top: -10px;
            left: 10px;
            font-size: 12px;
            color: #6d28d9;
            font-weight: 500;
        }

        .google-input:not(:focus)+.google-label {
            color: #5f6368;
        }

        .btn-google {
            background-color: #6d28d9;
            color: white;
            padding: 12px 28px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-google:hover {
            background-color: #5b21b6;
            box-shadow: 0 1px 3px 1px rgba(109, 40, 217, .15), 0 1px 2px 0 rgba(109, 40, 217, .3);
        }

        .btn-google-outline {
            background-color: transparent;
            color: #6d28d9;
            padding: 12px 28px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-google-outline:hover {
            background-color: #faf5ff;
        }

        .progress-stepper {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 8px;
        }

        .step-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #e8eaed;
            transition: background-color 0.3s;
        }

        .step-dot.active {
            background-color: #6d28d9;
        }

        .google-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .section-header h1 {
            font-size: 20px;
            font-weight: 500;
            color: #6d28d9;
            margin-bottom: 6px;
        }

        .section-header p {
            font-size: 14px;
            color: #5f6368;
            margin-bottom: 24px;
        }

        select.google-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        .sinhala-subtitle {
            display: block;
            font-size: 13px;
            color: #202124;
            font-weight: 400;
            margin-top: 2px;
        }

        .sinhala-inline {
            font-size: 11px;
            color: #5f6368;
            font-weight: 400;
            margin-left: 2px;
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
            user-select: none;
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

        @media (max-width: 480px) {
            .registration-container {
                padding: 24px 16px;
                border-radius: 0;
                border: none;
                box-shadow: none;
            }

            .google-logo h2 {
                font-size: 20px !important;
            }

            .section-header h1 {
                font-size: 18px;
            }

            .section-header p {
                font-size: 13px;
                margin-bottom: 20px;
            }

            .sinhala-subtitle {
                font-size: 12px;
            }

            .sinhala-inline {
                font-size: 10px;
            }

            .google-input {
                font-size: 14px;
                padding: 11px 13px;
            }

            .google-label {
                font-size: 14px;
                top: 11px;
            }

            .google-input:focus+.google-label,
            .google-input:not(:placeholder-shown)+.google-label {
                font-size: 11px;
                top: -9px;
            }

            .btn-google,
            .btn-google-outline {
                padding: 9px 20px;
                font-size: 13px;
            }

            .progress-stepper {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Background Design Elements -->
    <div class="bg-design">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <div class="registration-container">
        <!-- Logo -->
        <div class="google-logo">
            <img src="assests/logo.jpeg" alt="LMS Logo" class="h-16 w-auto object-contain rounded-lg shadow-sm">
        </div>

        <div class="section-header">
            <h1 id="stepTitle" class="text-xl font-bold text-[#6d28d9]">Create your Instructor Account</h1>
            <p id="stepSubtitle" class="text-base font-semibold text-gray-700 mt-1">නව උපදේශකවරයෙකු ලෙස ලියාපදිංචි වීම</p>
        </div>

        <div class="progress-stepper" id="progressStepper">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 p-8 rounded-2xl mb-6 text-sm text-center">
                <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Registration Successful!</h3>
                <p class="text-gray-600 mb-8"><?php echo htmlspecialchars($success_message); ?></p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="login.php" class="btn-google text-center flex-1">Login to Dashboard</a>
                    <a href="index.php" class="btn-google-outline text-center flex-1">Return Home</a>
                </div>
            </div>
        <?php else: ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded mb-6 text-sm">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="instructorRegisterForm">
                <input type="hidden" name="register_instructor" value="1">

                <!-- STEP 1: Personal Info -->
                <div class="step-content active" id="step1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                        <div class="google-input-group">
                            <input type="text" id="first_name" name="first_name" class="google-input" placeholder="First Name (මුල් නම)"
                                required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            <label for="first_name" class="google-label">First name (මුල් නම)</label>
                        </div>
                        <div class="google-input-group">
                            <input type="text" id="second_name" name="second_name" class="google-input" placeholder="Last Name (වාසගම)"
                                required value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                            <label for="second_name" class="google-label">Last name (වාසගම)</label>
                        </div>
                    </div>

                    <div class="google-input-group">
                        <select id="gender" name="gender" class="google-input" required>
                            <option value="" disabled selected hidden></option>
                            <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male (පුරුෂ)</option>
                            <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female (ස්ත්‍රී)</option>
                        </select>
                        <label for="gender" class="google-label">Gender (ස්ත්‍රී/පුරුෂ භාවය)</label>
                    </div>

                    <div class="google-input-group">
                        <input type="password" id="password" name="password" class="google-input" placeholder="Password (මුරපදය)" required>
                        <label for="password" class="google-label">Password (මුරපදය)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="password" id="confirm_password" name="confirm_password" class="google-input" placeholder="Confirm Password (මුරපදය තහවුරු කරන්න)" required>
                        <label for="confirm_password" class="google-label">Confirm Password (මුරපදය තහවුරු කරන්න)</label>
                    </div>

                    <div class="flex justify-between items-center mt-10">
                        <a href="login.php" class="text-[#6d28d9] font-medium text-sm hover:underline">Sign in instead</a>
                        <button type="button" onclick="nextStep(1)" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- STEP 2: Contact & Location -->
                <div class="step-content" id="step2">
                    <div class="google-input-group">
                        <input type="text" id="mobile_number" name="mobile_number" class="google-input" placeholder="Mobile Number (ජංගම දුරකථන අංකය)"
                            required value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                        <label for="mobile_number" class="google-label">Mobile Number (ජංගම දුරකථන අංකය)</label>
                    </div>

                    <div class="google-input-group">
                        <input type="text" id="whatsapp_number" name="whatsapp_number" class="google-input" placeholder="WhatsApp Number (වට්ස්ඇප් අංකය)"
                            required value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                        <label for="whatsapp_number" class="google-label">WhatsApp number (වට්ස්ඇප් අංකය)</label>
                    </div>

                    <div class="google-input-group">
                        <input type="text" id="nic_number" name="nic_number" class="google-input uppercase" placeholder="NIC Number (ජාතික හැඳුනුම්පත් අංකය)"
                            value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>">
                        <label for="nic_number" class="google-label">NIC Number (ජාතික හැඳුනුම්පත් අංකය)</label>
                    </div>

                    <div class="google-input-group">
                        <textarea id="address" name="address" class="google-input" placeholder="Postal Address (ලිපිනය)" rows="1"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        <label for="address" class="google-label">Postal Address (ලිපිනය)</label>
                    </div>

                    <div class="flex justify-between items-center mt-10">
                        <button type="button" onclick="prevStep(2)" class="btn-google-outline">Back</button>
                        <button type="button" onclick="nextStep(2)" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- STEP 3: Expertise & Rates -->
                <div class="step-content" id="step3">
                    <div class="google-input-group">
                        <input type="number" id="hourly_rate" name="hourly_rate" class="google-input font-semibold" placeholder="Expected Hourly Rate LKR (පැයකට අය කරන මුදල)"
                            value="<?php echo htmlspecialchars($_POST['hourly_rate'] ?? '1500'); ?>" required>
                        <label for="hourly_rate" class="google-label">Expected Hourly Rate LKR (පැයකට අය කරන මුදල)</label>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Subjects You Can Teach (ඉගැන්විය හැකි විෂයන් තෝරන්න) *</label>
                        <div class="flex flex-wrap -m-1 max-h-48 overflow-y-auto p-2 bg-gray-50 border border-gray-100 rounded-xl">
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
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <div class="google-input-group">
                            <input type="text" id="other_subject" name="other_subject" class="google-input" placeholder="Add Other Subject (වෙනත් විෂයක් ඇතුළත් කරන්න)">
                            <label for="other_subject" class="google-label">Subject not in list? Add it below:</label>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mt-10">
                        <button type="button" onclick="prevStep(3)" class="btn-google-outline">Back</button>
                        <button type="submit" class="btn-google px-8 shadow-lg shadow-purple-500/20">Submit Registration</button>
                    </div>
                </div>

            </form>
        <?php endif; ?>
    </div>

    <script>
        let currentStep = 1;

        document.addEventListener('DOMContentLoaded', () => {
            showStep(currentStep);
        });

        function showStep(n) {
            const steps = document.getElementsByClassName("step-content");
            const dots = document.getElementsByClassName("step-dot");
            
            const titles = [
                "Create your Instructor Account <span class='sinhala-subtitle'>(නව උපදේශකවරයෙකු ලෙස ලියාපදිංචි වීම)</span>",
                "Contact & Location <span class='sinhala-subtitle'>(සන්නිවේදන තොරතුරු සහ ලිපිනය)</span>",
                "Expertise & Rates <span class='sinhala-subtitle'>(විෂයන් සහ අය කිරීම්)</span>"
            ];
            
            const subtitles = [
                "Step 1 of 3: Personal Details",
                "Step 2 of 3: Identity & Location",
                "Step 3 of 3: Subject & Pricing Details"
            ];

            for (let i = 0; i < steps.length; i++) {
                steps[i].classList.remove("active");
                if (dots[i]) dots[i].classList.remove("active");
            }
            if (steps[n - 1]) steps[n - 1].classList.add("active");
            if (dots[n - 1]) dots[n - 1].classList.add("active");

            const titleElem = document.getElementById("stepTitle");
            const subtitleElem = document.getElementById("stepSubtitle");
            if (titleElem) titleElem.innerHTML = titles[n - 1];
            if (subtitleElem) subtitleElem.innerHTML = subtitles[n - 1];

            window.scrollTo(0, 0);
        }

        function nextStep(n) {
            if (!validateStep(n)) return;
            currentStep = n + 1;
            showStep(currentStep);
        }

        function prevStep(n) {
            currentStep = n - 1;
            showStep(currentStep);
        }

        function validateStep(n) {
            const currentStepDiv = document.getElementById("step" + n);
            if (!currentStepDiv) return true;

            const inputs = currentStepDiv.querySelectorAll("input[required], select[required], textarea[required]");
            let valid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('border-red-500');
                    valid = false;
                } else {
                    input.classList.remove('border-red-500');
                }
            });

            if (!valid) {
                alert("Please fill in all required fields (කරුණාකර සියලුම අනිවාර්ය ක්ෂේත්‍ර පුරවන්න).");
            }
            return valid;
        }
    </script>
</body>
</html>
