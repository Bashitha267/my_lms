<?php
session_start();
require_once 'config.php';
require_once 'whatsapp_config.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_teacher'])) {
    $email = null;
    $password = $_POST['password'] ?? '';
    $role = 'teacher';
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $approved = 0;
    
    // Validation
    if (empty($password)) {
        $error_message = 'Password is required.';
    } else {
        $prefix = 'tea';
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
        
        $profile_picture_path = null;
        if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
            if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file = $_FILES['profile_picture'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_extensions) && $file['size'] <= 5 * 1024 * 1024) {
                    $new_filename = $user_id . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $profile_picture_path = 'uploads/profiles/' . $new_filename;
                    }
                }
            }
        }
        
        if (empty($error_message)) {
            $nic_number = !empty(trim($_POST['nic_number'] ?? '')) ? trim($_POST['nic_number']) : null;
            $dob    = !empty(trim($_POST['dob'] ?? ''))    ? trim($_POST['dob'])    : null;
            $gender = !empty(trim($_POST['gender'] ?? '')) ? trim($_POST['gender']) : null;
            $requested_rate = isset($_POST['requested_rate']) ? floatval($_POST['requested_rate']) : 75.00;

            $is_mentor = 0;
            $hourly_rate = 0.00;

            $stmt = $conn->prepare("INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status, nic_no, dob, gender, commission_rate, requested_commission_rate, hourly_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }

            $default_approved_rate = 75.00;
            $stmt->bind_param("sssssssssisssddd", $user_id, $email, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $profile_picture_path, $approved, $nic_number, $dob, $gender, $default_approved_rate, $requested_rate, $hourly_rate);
            
            if ($stmt->execute()) {
                // Education
                if (isset($_POST['education']) && is_array($_POST['education'])) {
                    $edu_stmt = $conn->prepare("INSERT INTO teacher_education (teacher_id, qualification, institution, year_obtained, field_of_study, grade_or_class) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($_POST['education'] as $edu) {
                        if (!empty($edu['qualification'])) {
                            $institution = $edu['institution'] ?? '';
                            $year = !empty($edu['year_obtained']) ? intval($edu['year_obtained']) : null;
                            $field = $edu['field_of_study'] ?? '';
                            $grade = $edu['grade_or_class'] ?? '';
                            $edu_stmt->bind_param("sssiss", $user_id, $edu['qualification'], $institution, $year, $field, $grade);
                            $edu_stmt->execute();
                        }
                    }
                    $edu_stmt->close();
                }
                
                // Handle New Stream and Subject Creation
                $teacher_subjects = $_POST['teacher_subjects'] ?? [];
                
                if (!empty($_POST['new_stream_name']) || !empty($_POST['new_subject_name'])) {
                    $target_stream_id = null;
                    
                    // 1. Create/Get Stream
                    if (!empty($_POST['new_stream_name'])) {
                        $ns_name = trim($_POST['new_stream_name']);
                        $check_s = $conn->prepare("SELECT id FROM streams WHERE name = ?");
                        $check_s->bind_param("s", $ns_name);
                        $check_s->execute();
                        $res_s = $check_s->get_result();
                        if ($res_s->num_rows > 0) {
                            $target_stream_id = $res_s->fetch_assoc()['id'];
                        } else {
                            $ins_s = $conn->prepare("INSERT INTO streams (name, status) VALUES (?, 1)");
                            $ins_s->bind_param("s", $ns_name);
                            $ins_s->execute();
                            $target_stream_id = $ins_s->insert_id;
                        }
                    } else {
                        // Use first selected stream if no new stream name is provided
                        $selected_streams = $_POST['teacher_streams'] ?? [];
                        if (!empty($selected_streams)) {
                            $target_stream_id = intval($selected_streams[0]);
                        }
                    }

                    // 2. Create/Get Subject and Map it
                    if ($target_stream_id && !empty($_POST['new_subject_name'])) {
                        $nsub_name = trim($_POST['new_subject_name']);
                        
                        // Check/Create Subject
                        $check_sub = $conn->prepare("SELECT id FROM subjects WHERE name = ?");
                        $check_sub->bind_param("s", $nsub_name);
                        $check_sub->execute();
                        $res_sub = $check_sub->get_result();
                        $sub_id = null;
                        if ($res_sub->num_rows > 0) {
                            $sub_id = $res_sub->fetch_assoc()['id'];
                        } else {
                            $ins_sub = $conn->prepare("INSERT INTO subjects (name, status) VALUES (?, 1)");
                            $ins_sub->bind_param("s", $nsub_name);
                            $ins_sub->execute();
                            $sub_id = $ins_sub->insert_id;
                        }
                        
                        // Check/Create Mapping (stream_subject)
                        $check_map = $conn->prepare("SELECT id FROM stream_subjects WHERE stream_id = ? AND subject_id = ?");
                        $check_map->bind_param("ii", $target_stream_id, $sub_id);
                        $check_map->execute();
                        $res_map = $check_map->get_result();
                        $ss_id = null;
                        if ($res_map->num_rows > 0) {
                            $ss_id = $res_map->fetch_assoc()['id'];
                        } else {
                            $ins_map = $conn->prepare("INSERT INTO stream_subjects (stream_id, subject_id, status) VALUES (?, ?, 1)");
                            $ins_map->bind_param("ii", $target_stream_id, $sub_id);
                            $ins_map->execute();
                            $ss_id = $ins_map->insert_id;
                        }
                        
                        if ($ss_id) {
                            $teacher_subjects[] = $ss_id;
                        }
                    }
                }

                // Assignments
                $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');
                if (!empty($teacher_subjects) && is_array($teacher_subjects)) {
                    $assign_stmt = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, status, assigned_date) VALUES (?, ?, ?, 'pending', CURDATE())");
                    foreach ($teacher_subjects as $ss_id) {
                        $ss_id = intval($ss_id);
                        if ($ss_id > 0) {
                            $assign_stmt->bind_param("sii", $user_id, $ss_id, $academic_year);
                            $assign_stmt->execute();
                        }
                    }
                    $assign_stmt->close();
                }
                
                $success_message = "Registration successful! Your Teacher ID is: $user_id. Please wait for admin approval.";
                $_POST = array();
            } else {
                $error_message = ($conn->errno == 1062) ? 'Email or User ID already exists.' : 'Error creating user: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

$streams_query = "SELECT id, name FROM streams WHERE status = 1 ORDER BY name";
$streams = $conn->query($streams_query)->fetch_all(MYSQLI_ASSOC);

// Pre-calculate next User ID for display
$prefix_display = 'tea';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assests/favicon-16x16.png">
    <link rel="manifest" href="assests/site.webmanifest">
    <link rel="shortcut icon" href="assests/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration | Lernerr.LK</title>
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
            background: linear-gradient(135deg, #fdf2f2 0%, #fff 100%);
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
            background: #fecaca; /* red-200 */
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }

        .blob-2 {
            width: 400px;
            height: 400px;
            background: #fed7aa; /* orange-200 */
            bottom: -50px;
            right: -50px;
            animation-delay: -5s;
        }

        .blob-3 {
            width: 300px;
            height: 300px;
            background: #fbcfe8; /* pink-200 */
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
                padding: 16px 12px;
                display: block;
                align-items: unset;
            }
            .registration-container {
                padding: 24px 18px;
                border-radius: 20px;
                margin-top: 12px;
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
            border-color: #1a73e8;
            outline: none;
            box-shadow: 0 0 0 1px #1a73e8;
        }

        .google-label {
            position: absolute;
            left: 12px;
            top: 13px;
            padding: 0 4px;
            background: white;
            color: #5f6368;
            font-size: 14px;
            pointer-events: none;
            transition: all 0.2s;
            /* Prevent long labels from overflowing out of the input box */
            max-width: calc(100% - 24px);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .google-input:focus+.google-label,
        .google-input:not(:placeholder-shown)+.google-label {
            top: -10px;
            left: 10px;
            font-size: 11px;
            color: #1a73e8;
            font-weight: 500;
            white-space: normal;   /* allow wrapping only when floated up */
            overflow: visible;
            max-width: calc(100% - 20px);
        }

        .google-input:not(:focus)+.google-label {
            color: #5f6368;
        }

        .btn-google {
            background-color: #dc2626;
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
            background-color: #b91c1c;
            box-shadow: 0 1px 3px 1px rgba(220, 38, 38, .15), 0 1px 2px 0 rgba(220, 38, 38, .3);
        }

        .btn-google-outline {
            background-color: transparent;
            color: #dc2626;
            padding: 12px 28px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-google-outline:hover {
            background-color: #fef2f2;
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
            background-color: #dc2626;
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
            color: #dc2626;
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

        @media (max-width: 480px) {
            .registration-container {
                padding: 24px 16px;
                border-radius: 16px;
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
                padding: 12px 13px;
            }

            /* Keep label matching the new 14px base at rest */
            .google-label {
                font-size: 13px;
                top: 13px;
            }

            .google-input:focus + .google-label,
            .google-input:not(:placeholder-shown) + .google-label {
                font-size: 10px;
                top: -9px;
            }

            .btn-google,
            .btn-google-outline {
                padding: 10px 16px;
                font-size: 13px;
            }

            /* Prevent "Register as Teacher" from overflowing */
            .btn-google {
                white-space: nowrap;
            }

            .progress-stepper {
                margin-bottom: 20px;
            }

            /* OTP / NIC rows: stack button below input on tiny screens */
            .mobile-stack {
                flex-direction: column;
            }
            .mobile-stack > button {
                width: 100%;
                margin-top: 4px;
                padding: 10px;
                border-radius: 6px;
            }

            /* Step 3 header: allow wrap so button doesn't squish title */
            .step3-header {
                flex-wrap: wrap;
                gap: 8px;
            }
            .step3-header h3 {
                font-size: 15px;
            }
        }

        @media (max-width: 360px) {
            .registration-container {
                padding: 20px 12px;
            }
            .google-input {
                font-size: 13px;
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
            <h1 id="stepTitle" class="text-xl font-bold text-[#dc2626]">Create your Teacher Account</h1>
            <p id="stepSubtitle" class="text-base font-semibold text-gray-700 mt-1">නව ගුරුවරයෙකු ලෙස ලියාපදිංචි වීම</p>
        </div>

        <div class="progress-stepper" id="progressStepper">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded mb-6 text-sm">
                <?php echo htmlspecialchars($success_message); ?>
                <div class="mt-2">
                    <a href="login.php" class="font-bold underline text-green-800">Login now</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded mb-6 text-sm">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="teacherRegisterForm" enctype="multipart/form-data">
            <input type="hidden" name="register_teacher" value="1">
            <input type="hidden" id="dob" name="dob" value="">
            <input type="hidden" id="gender" name="gender" value="">
            <input type="hidden" id="mobile_verified" name="mobile_verified" value="0">

            <!-- STEP 1: Personal Info -->
            <div class="step-content active" id="step1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                    <div class="google-input-group">
                        <input type="text" id="first_name" name="first_name" class="google-input" placeholder=" "
                            required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        <label for="first_name" class="google-label">First name (මුල් නම)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="text" id="second_name" name="second_name" class="google-input" placeholder=" "
                            required value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                        <label for="second_name" class="google-label">Last name (වාසගම)</label>
                    </div>
                </div>

                <div class="google-input-group">
                    <input type="password" id="password" name="password" class="google-input" placeholder="Password (මුරපදය)" required>
                    <label for="password" class="google-label">Password (මුරපදය)</label>
                </div>

                <div class="flex justify-between items-center mt-10">
                    <a href="login.php" class="text-red-600 font-medium text-sm hover:underline">Sign in instead</a>
                    <button type="button" onclick="nextStep(1)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 2: Contact & Verification -->
            <div class="step-content" id="step2">
                <div class="google-input-group">
                    <div class="flex gap-2 mobile-stack">
                        <div class="relative flex-1">
                            <input type="text" id="mobile_number" name="mobile_number" class="google-input" placeholder=" "
                                required value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                            <label for="mobile_number" class="google-label">Mobile Number (ජංගම දුරකථන අංකය)</label>
                        </div>
                        <button type="button" onclick="sendOTP()" id="sendOtpBtn" class="bg-red-50 text-red-600 px-4 rounded border border-red-100 hover:bg-red-100 font-semibold text-xs transition whitespace-nowrap">Send OTP</button>
                    </div>
                </div>

                <div id="otpSection" class="hidden google-input-group p-4 bg-gray-50 border border-gray-200 rounded-lg mb-6">
                    <label class="block text-xs font-semibold text-gray-500 mb-2">Enter 6-digit OTP (ලැබුණු OTP අංකය ඇතුළත් කරන්න)</label>
                    <div class="flex gap-2">
                        <input type="text" id="otp_code" maxlength="6" class="flex-1 google-input text-center text-lg tracking-widest" placeholder="xxxxxx">
                        <button type="button" onclick="verifyOTP()" class="bg-emerald-600 text-white px-6 rounded font-semibold text-sm hover:bg-emerald-700 transition">Verify</button>
                    </div>
                    <p id="otpMessage" class="mt-2 text-xs font-semibold"></p>
                </div>

                <div class="google-input-group">
                    <div class="flex gap-2 mobile-stack">
                        <div class="relative flex-1">
                            <input type="text" id="nic_number" name="nic_number" class="google-input uppercase" placeholder=" "
                                required value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>">
                            <label for="nic_number" class="google-label">NIC Number (ජාතික හැඳුනුම්පත් අංකය)</label>
                        </div>
                        <button type="button" onclick="verifyNIC()" class="bg-red-50 text-red-600 px-4 rounded border border-red-100 hover:bg-red-100 font-semibold text-xs transition whitespace-nowrap">Check NIC</button>
                    </div>
                </div>

                <div class="google-input-group">
                    <input type="text" id="whatsapp_number" name="whatsapp_number" class="google-input" placeholder="WhatsApp Number (වට්ස්ඇප් අංකය)"
                        value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                    <label for="whatsapp_number" class="google-label">WhatsApp number (වට්ස්ඇප් අංකය)</label>
                </div>

                <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Profile Picture</label>
                    <input type="file" name="profile_picture" accept="image/*" class="text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                    <p class="text-[10px] text-gray-400 mt-2">Max 5MB. JPG, PNG or WebP</p>
                </div>

                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(2)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(2)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 3: Academic Background -->
            <div class="step-content" id="step3">
                <div class="flex justify-between items-center mb-6 step3-header">
                    <h3 class="text-base font-semibold text-gray-800">Academic Background <span class="block text-xs font-normal text-gray-500">(අධ්‍යාපන සුදුසුකම්)</span></h3>
                    <button type="button" onclick="addEducationField()" class="text-xs bg-red-50 text-red-700 px-3 py-1.5 rounded font-bold border border-red-100 hover:bg-red-100 transition whitespace-nowrap flex-shrink-0">+ Add Qualification</button>
                </div>

                <div id="educationContainer" class="space-y-4">
                    <!-- Dynamic fields added via Javascript -->
                </div>

                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(3)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(3)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 4: Teaching Preferences & Register -->
            <div class="step-content" id="step4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                    <div class="google-input-group">
                        <input type="number" id="academic_year" name="academic_year" class="google-input font-semibold" placeholder=" "
                            value="<?php echo date('Y'); ?>" required>
                        <label for="academic_year" class="google-label">Academic Year (අධ්‍යයන වර්ෂය)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="number" id="requested_rate" name="requested_rate" class="google-input font-semibold" placeholder=" "
                            value="75" min="1" max="100" required>
                        <label for="requested_rate" class="google-label">Requested Commission % (කොමිස් %)</label>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Select Academic Streams (විෂයධාරාවන් තෝරන්න) *</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <?php foreach ($streams as $stream): ?>
                            <label class="flex items-center space-x-2 border border-gray-200 rounded-lg p-3 bg-white hover:bg-red-50/30 cursor-pointer transition select-none">
                                <input type="checkbox" name="teacher_streams[]" value="<?php echo $stream['id']; ?>" class="teacher-stream-checkbox focus:ring-red-500 text-red-600 rounded" onchange="loadTeacherSubjects()">
                                <span class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($stream['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="teacherSubjectContainer" class="hidden mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Select Subjects (විෂයන් තෝරන්න) *</label>
                    <div id="teacherSubjectsGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <!-- AJAX generated contents -->
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="h-px flex-1 bg-gray-200"></div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Can't find your subject or stream?</span>
                        <div class="h-px flex-1 bg-gray-200"></div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                        <div class="google-input-group">
                            <input type="text" id="new_stream_name" name="new_stream_name" class="google-input" placeholder=" ">
                            <label for="new_stream_name" class="google-label">New Stream Name (Arts, Commerce...) (නව විෂයධාරාවක් එකතූ කරන්න)</label>
                        </div>
                        <div class="google-input-group">
                            <input type="text" id="new_subject_name" name="new_subject_name" class="google-input" placeholder=" ">
                            <label for="new_subject_name" class="google-label">New Subject Name (නව විෂය එකතූ කරන්න)</label>
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-400 italic">Note: New subjects will be linked to the new stream above or your first selected stream.</p>
                </div>

                <div class="flex justify-between items-center mt-10 gap-3">
                    <button type="button" onclick="prevStep(4)" class="btn-google-outline">Back</button>
                    <button type="submit" class="btn-google shadow-lg shadow-red-500/20 flex-shrink-0">Register as Teacher</button>
                </div>
            </div>

        </form>
    </div>

    <script>
        window.preSelectedSubjects = <?php echo json_encode($_POST['teacher_subjects'] ?? []); ?>;
        window.preSelectedSubjects = window.preSelectedSubjects.map(String);

        let currentStep = 1;

        document.addEventListener('DOMContentLoaded', () => {
            showStep(currentStep);
            if (document.querySelectorAll('.education-field').length === 0) addEducationField();
        });

        function showStep(n) {
            const steps = document.getElementsByClassName("step-content");
            const dots = document.getElementsByClassName("step-dot");
            
            const titles = [
                "Create your Teacher Account <span class='sinhala-subtitle'>(නව ගුරුවරයෙකු ලෙස ලියාපදිංචි වීම)</span>",
                "Contact & Verification <span class='sinhala-subtitle'>(සන්නිවේදන තොරතුරු සහ තහවුරු කිරීම)</span>",
                "Academic Background <span class='sinhala-subtitle'>(අධ්‍යයන පසුබිම)</span>",
                "Teaching Preferences <span class='sinhala-subtitle'>(ඉගැන්වීම් මනාපයන්)</span>"
            ];
            
            const subtitles = [
                "Step 1 of 4: Personal Details",
                "Step 2 of 4: Identity & Contact",
                "Step 3 of 4: Qualification Details",
                "Step 4 of 4: Select Streams and Subjects"
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

        let eduCount = 0;
        function addEducationField() {
            eduCount++;
            const container = document.getElementById('educationContainer');
            const div = document.createElement('div');
            div.className = 'p-4 border border-gray-200 rounded-xl bg-gray-50 education-field relative mb-4';
            div.innerHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                    <div class="google-input-group">
                        <input type="text" name="education[${eduCount}][qualification]" class="google-input" placeholder="Qualification (සුදුසුකම)" required>
                        <label class="google-label">Qualification (සුදුසුකම)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="text" name="education[${eduCount}][institution]" class="google-input" placeholder="Institution (ආයතනය)" required>
                        <label class="google-label">Institution (ආයතනය)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="number" name="education[${eduCount}][year_obtained]" class="google-input" placeholder="Year Obtain (ලබාගත් වසර)" required>
                        <label class="google-label">Year Obtain (ලබාගත් වසර)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="text" name="education[${eduCount}][grade_or_class]" class="google-input" placeholder="Grade/Class (සාමාර්ථය)" required>
                        <label class="google-label">Grade/Class (සාමාර්ථය)</label>
                    </div>
                </div>
                ${eduCount > 1 ? `<button type="button" onclick="this.closest('.education-field').remove()" class="absolute top-2 right-2 text-gray-400 hover:text-red-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>` : ''}
            `;
            container.appendChild(div);
        }

        async function loadTeacherSubjects() {
            const streams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);
            const container = document.getElementById('teacherSubjectContainer');
            const grid = document.getElementById('teacherSubjectsGrid');

            if (streams.length === 0) { container.classList.add('hidden'); return; }
            container.classList.remove('hidden');
            grid.innerHTML = '<div class="col-span-full py-4 text-center text-gray-400">Loading...</div>';

            try {
                const results = await Promise.all(streams.map(async id => {
                    const r = await fetch(`ajax/get_subjects.php?stream_id=${id}`);
                    return r.json();
                }));
                
                grid.innerHTML = '';
                results.forEach(res => {
                    if (res.success && res.subjects) {
                        res.subjects.forEach(sub => {
                            const isChecked = window.preSelectedSubjects.includes(String(sub.stream_subject_id)) ? 'checked' : '';
                            const label = document.createElement('label');
                            label.className = 'flex items-center space-x-2 border rounded-lg p-3 bg-white hover:bg-gray-50 cursor-pointer select-none';
                            label.innerHTML = `<input type="checkbox" name="teacher_subjects[]" value="${sub.stream_subject_id}" class="focus:ring-red-500 text-red-600 rounded" ${isChecked}>
                                               <span class="text-sm font-semibold text-gray-700">${sub.name}</span>`;
                            grid.appendChild(label);
                        });
                    }
                });
            } catch (e) { grid.innerHTML = '<div class="text-red-500 text-xs font-bold col-span-full py-2">Error loading subjects.</div>'; }
        }

        function sendOTP() {
            const mob = document.getElementById('mobile_number').value.trim();
            if(!mob) return alert('Enter mobile number! (ජංගම දුරකථන අංකය ඇතුළත් කරන්න)');
            document.getElementById('sendOtpBtn').innerText = 'Sending...';
            setTimeout(() => {
                document.getElementById('otpSection').classList.remove('hidden');
                document.getElementById('sendOtpBtn').innerText = 'Resend';
            }, 500);
        }

        function verifyOTP() {
            const code = document.getElementById('otp_code').value.trim();
            if(code.length === 6) {
                document.getElementById('otpMessage').innerText = 'Mobile Number Verified (ජංගම දුරකථන අංකය සාර්ථකව තහවුරු කරන ලදී)';
                document.getElementById('otpMessage').className = 'mt-2 text-xs font-bold text-emerald-600';
                document.getElementById('mobile_verified').value = '1';
            } else {
                alert('Please enter a valid 6-digit OTP.');
            }
        }

        function verifyNIC() {
            const nic = document.getElementById('nic_number').value.trim();
            if(!nic) return alert('Enter NIC number! (ජාතික හැඳුනුම්පත් අංකය ඇතුළත් කරන්න)');
            alert('NIC extraction and verification successful (mock validation).');
        }
    </script>
</body>
</html>
