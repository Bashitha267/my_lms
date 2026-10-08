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
    $verification_method = $_POST['verification_method'] ?? '';
    $mobile_verified = intval($_POST['mobile_verified'] ?? 0);
    $nic_verified = intval($_POST['nic_verified'] ?? 0);

    // Validation
    if (empty($password)) {
        $error_message = 'Password is required.';
    } elseif (empty($first_name) || empty($second_name)) {
        $error_message = 'First name and Last name are required.';
    } elseif (empty($mobile_number)) {
        $error_message = 'Mobile number is required.';
    } elseif (empty($verification_method)) {
        $error_message = 'Please select and complete a verification method / කරුණාකර තහවුරු කිරීමේ ක්‍රමයක් සම්පූර්ණ කරන්න.';
    } elseif ($verification_method === 'otp' && $mobile_verified !== 1) {
        $error_message = 'Please verify your WhatsApp number with OTP before submitting / කරුණාකර WhatsApp OTP මගින් ඔබගේ අංකය තහවුරු කරන්න.';
    } elseif ($verification_method === 'nic' && $nic_verified !== 1) {
        $error_message = 'Please verify your NIC before submitting / කරුණාකර ඔබගේ ජාතික හැඳුනුම්පත් අංකය තහවුරු කරන්න.';
    } else {
        $prefix = 'T';
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY CAST(SUBSTRING(user_id, 3) AS UNSIGNED) DESC LIMIT 1");
        $pattern = $prefix . '_%';
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $next_num = 1;
        if ($result->num_rows > 0) {
            $last_user = $result->fetch_assoc();
            $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
            $next_num = max($last_num + 1, 1);
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

            $approved = 0;
            $is_mentor = 0;
            $hourly_rate = 0.00;

            // Pre-check for duplicate mobile or NIC
            $dup_check = $conn->prepare("SELECT user_id, mobile_number, whatsapp_number, nic_no FROM users WHERE (mobile_number = ? AND mobile_number != '') OR (whatsapp_number = ? AND whatsapp_number != '') OR (nic_no = ? AND nic_no IS NOT NULL AND nic_no != '') LIMIT 1");
            if ($dup_check) {
                $dup_check->bind_param("sss", $mobile_number, $whatsapp_number, $nic_number);
                $dup_check->execute();
                $dup_res = $dup_check->get_result();
                if ($dup_res->num_rows > 0) {
                    $existing = $dup_res->fetch_assoc();
                    if (!empty($nic_number) && $existing['nic_no'] === $nic_number) {
                        $error_message = 'This NIC number is already registered. Please use a different NIC / මෙම ජාතික හැඳුනුම්පත් අංකය දැනටමත් ලියාපදිංචි වී ඇත.';
                    } else {
                        $error_message = 'This mobile number or NIC is already registered. Please use a different number / මෙම දුරකථන අංකය හෝ ජාතික හැඳුනුම්පත් අංකය දැනටමත් ලියාපදිංචි වී ඇත.';
                    }
                }
                $dup_check->close();
            }

            if (empty($error_message)) {
                $user_created = false;
                try {
                    $stmt = $conn->prepare("INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status, nic_no, dob, gender, commission_rate, requested_commission_rate, hourly_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt === false) {
                        die("Prepare failed: " . $conn->error);
                    }

                    $default_approved_rate = 75.00;
                    $stmt->bind_param("sssssssssisssddd", $user_id, $email, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $profile_picture_path, $approved, $nic_number, $dob, $gender, $default_approved_rate, $requested_rate, $hourly_rate);
                    
                    if ($stmt->execute()) {
                        $user_created = true;
                    } else {
                        if ($conn->errno == 1062) {
                            $error_message = 'This mobile number or NIC is already registered. Please use a different number / මෙම දුරකථන අංකය හෝ ජාතික හැඳුනුම්පත් අංකය දැනටමත් ලියාපදිංචි වී ඇත.';
                        } else {
                            $error_message = 'Error creating user: ' . $conn->error;
                        }
                    }
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() == 1062 || $conn->errno == 1062) {
                        $error_message = 'This mobile number or NIC is already registered. Please use a different number / මෙම දුරකථන අංකය හෝ ජාතික හැඳුනුම්පත් අංකය දැනටමත් ලියාපදිංචි වී ඇත.';
                    } else {
                        $error_message = 'Database error: ' . $e->getMessage();
                    }
                } catch (Exception $e) {
                    $error_message = 'Error creating user: ' . $e->getMessage();
                }

                // If user creation succeeded, process secondary records safely
                if ($user_created) {
                    try {
                        // Education
                        if (isset($_POST['education']) && is_array($_POST['education'])) {
                            $edu_stmt = $conn->prepare("INSERT INTO teacher_education (teacher_id, qualification, institution, year_obtained) VALUES (?, ?, ?, ?)");
                            if ($edu_stmt) {
                                foreach ($_POST['education'] as $edu) {
                                    if (!empty($edu['qualification'])) {
                                        $institution = $edu['institution'] ?? '';
                                        $year = !empty($edu['year_obtained']) ? intval($edu['year_obtained']) : null;
                                        $edu_stmt->bind_param("sssi", $user_id, $edu['qualification'], $institution, $year);
                                        try { $edu_stmt->execute(); } catch (Throwable $ex) {}
                                    }
                                }
                                $edu_stmt->close();
                            }
                        }
                        
                        // Selected Subject Mappings (stream_subjects)
                        $teacher_subjects = $_POST['teacher_subjects'] ?? [];

                        // Class Enrollments / Teacher Assignments
                        if (isset($_POST['class_enrollments']) && is_array($_POST['class_enrollments'])) {
                            $assign_stmt = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, batch_name, status, assigned_date, cover_image, notes) VALUES (?, ?, ?, ?, 'pending', CURDATE(), ?, ?)");
                            
                            if ($assign_stmt) {
                                foreach ($_POST['class_enrollments'] as $idx => $enr) {
                                    $raw_ss_id = $enr['stream_subject_id'] ?? '';
                                    $ss_id = intval($raw_ss_id);
                                    
                                    if (($ss_id <= 0 || $raw_ss_id === 'new') && !empty($teacher_subjects)) {
                                        $ss_id = intval(end($teacher_subjects));
                                    }

                                    if ($ss_id <= 0) continue;

                                    $ac_year = intval($enr['academic_year'] ?? date('Y'));
                                    $batch = trim($enr['batch_name'] ?? '');
                                    $notes = trim($enr['notes'] ?? '');

                                    if (empty($batch)) continue;
                                    
                                    $cover_path = null;
                                    $file_key = 'class_cover_image_' . $idx;
                                    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                                        $u_dir = 'uploads/subject_covers/';
                                        if (!file_exists($u_dir)) mkdir($u_dir, 0777, true);
                                        $f_ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
                                        if (in_array($f_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                            $f_name = 'cover_' . $user_id . '_' . $idx . '_' . time() . '.' . $f_ext;
                                            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $u_dir . $f_name)) {
                                                $cover_path = 'uploads/subject_covers/' . $f_name;
                                            }
                                        }
                                    }
                                    
                                    try {
                                        $assign_stmt->bind_param("siisss", $user_id, $ss_id, $ac_year, $batch, $cover_path, $notes);
                                        if ($assign_stmt->execute()) {
                                            $new_assignment_id = $assign_stmt->insert_id;
                                            
                                            $enrollment_fee = isset($enr['enrollment_fee']) ? floatval($enr['enrollment_fee']) : 0.00;
                                            $monthly_fee = isset($enr['monthly_fee']) ? floatval($enr['monthly_fee']) : 0.00;

                                            if ($enrollment_fee > 0 || $monthly_fee > 0) {
                                                $fee_stmt = $conn->prepare("INSERT INTO enrollment_fees (teacher_assignment_id, enrollment_fee, monthly_fee) VALUES (?, ?, ?)");
                                                if ($fee_stmt) {
                                                    $fee_stmt->bind_param("idd", $new_assignment_id, $enrollment_fee, $monthly_fee);
                                                    try { $fee_stmt->execute(); } catch (Throwable $ex) {}
                                                    $fee_stmt->close();
                                                }
                                            }
                                        }
                                    } catch (Throwable $ex) {}
                                }
                                $assign_stmt->close();
                            }
                        } elseif (!empty($teacher_subjects) && is_array($teacher_subjects)) {
                            $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');
                            $assign_stmt = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, status, assigned_date) VALUES (?, ?, ?, 'pending', CURDATE())");
                            if ($assign_stmt) {
                                foreach ($teacher_subjects as $ss_id) {
                                    $ss_id = intval($ss_id);
                                    if ($ss_id > 0) {
                                        try {
                                            $assign_stmt->bind_param("sii", $user_id, $ss_id, $academic_year);
                                            $assign_stmt->execute();
                                        } catch (Throwable $ex) {}
                                    }
                                }
                                $assign_stmt->close();
                            }
                        }
                    } catch (Throwable $ex) {}

                    // Send WhatsApp registration acknowledgement to teacher
                    if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                        try {
                            $reg_msg = "Teacher Registration Received\n\n" .
                                "Hello *{$first_name} {$second_name}*,\n" .
                                "Thank you for applying to register as a teacher with our institution.\n" .
                                "*Your Teacher ID:* {$user_id}\n\n" .
                                "You will be notified once your application is reviewed and approved by our Institution. Please wait until then.\n\n" .
                                "--------------------------\n\n" .
                                "අප ආයතනය හා ගුරුවරයෙකු ලෙස ලියාපදිංචි වීමට අයදුම් කල ඔබට ස්තුතිය.අප ආයතනය ඔබගේ අයදුම්පත තහවුරු  කළ පසු ඔබට දැන්ම් දෙනු ලැබේ.එතෙක් රැදී සිටින්න.\n" ;
                                
                            sendWhatsAppMessage($whatsapp_number, $reg_msg);
                        } catch (Exception $e) {
                            error_log("WhatsApp teacher registration message failed: " . $e->getMessage());
                        }
                    }

                    $_SESSION['pending_teacher_notice'] = true;
                    header("Location: index.php?teacher_registered=1");
                    exit();
                }
            }
        }
    }
}

$streams_query = "SELECT id, name FROM streams WHERE status = 1 ORDER BY name";
$streams = $conn->query($streams_query)->fetch_all(MYSQLI_ASSOC);

// Pre-calculate next User ID for display
$prefix_display = 'T';
$stmt_display = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY CAST(SUBSTRING(user_id, 3) AS UNSIGNED) DESC LIMIT 1");
$pattern_display = $prefix_display . '_%';
$stmt_display->bind_param("s", $pattern_display);
$stmt_display->execute();
$result_display = $stmt_display->get_result();
$next_num_display = 1;
if ($result_display->num_rows > 0) {
    $last_user = $result_display->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], strlen($prefix_display) + 1));
    $next_num_display = max($last_num + 1, 1);
}
$stmt_display->close();
$display_user_id = $prefix_display . '_' . str_pad($next_num_display, 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration | Lernerr.LK</title>
    <meta name="description" content="Register as a teacher on Lernerr.LK, Sri Lanka's leading online Learning Management System. Share your expertise and educate students nationwide.">
    <meta name="keywords" content="Lernerr.LK teacher registration, become a teacher Lernerr.LK, online teaching Sri Lanka">
    <meta name="author" content="Lernerr.LK">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Teacher Registration | Lernerr.LK">
    <meta property="og:description" content="Register as a teacher on Lernerr.LK, Sri Lanka's leading online Learning Management System.">
    <meta property="og:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">
    <meta property="og:site_name" content="Lernerr.LK">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="Teacher Registration | Lernerr.LK">
    <meta property="twitter:description" content="Register as a teacher on Lernerr.LK, Sri Lanka's leading online Learning Management System.">
    <meta property="twitter:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assests/favicon-16x16.png">
    <link rel="manifest" href="assests/site.webmanifest">
    <link rel="shortcut icon" href="assests/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Abhaya+Libre:wght@400;500;600;700;800&family=Gemunu+Libre:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Inter', 'Abhaya Libre', 'Gemunu Libre', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
            overflow-x: hidden;
            position: relative;
        }

        /* Background */
        .bg-design {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
            pointer-events: none;
            background: #ffffff;
            /* Mobile dimensions (9:16 ratio) by default */
            width: min(100vw, 100vh * 1080 / 1920);
            height: min(100vh, 100vw * 1920 / 1080);
            max-width: 1080px;
            max-height: 1920px;
        }

        @media (min-width: 641px) {
            .bg-design {
                /* Desktop dimensions (16:9 ratio) */
                width: min(100vw, 100vh * 1920 / 1080);
                height: min(100vh, 100vw * 1080 / 1920);
                max-width: 1920px;
                max-height: 1080px;
            }
        }

        .bg-design .bg-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center top;
            position: absolute;
            top: 0;
            left: 0;
            z-index: -1;
        }

        /* Registration container */
        .registration-container {
            width: 100%;
            max-width: 1000px;
            background: transparent;
            border-radius: 28px;
            padding: 48px;
            position: relative;
            z-index: 10;
            margin: 0 auto;
            pointer-events: auto;
        }

        @media (min-width: 641px) {
            .registration-container {
                position: absolute;
                left: 10.2%;
                top: 28%;
                width: 61.8%;
                height: 55%;
                padding: 0;
                margin: 0;
                z-index: 20;
            }
        }

        @media (max-width: 640px) {
            body { padding: 10px 8px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            .registration-container {
                position: absolute;
                left: 6%;
                top: 50%;
                transform: translateY(calc(-50% + 3px));
                width: 88%;
                height: auto;
                max-height: 95vh;
                padding: 12px 16px 20px 16px;
                margin: 0;
                z-index: 20;
                background: #ffffff;
                border: none;
                box-shadow: 0 4px 12px rgba(0,0,0,.08);
                border-radius: 20px;
                overflow-y: auto;
                grid-auto-rows: min-content;
                align-content: start;
            }
            .registration-container::-webkit-scrollbar { width: 4px; }
            .registration-container::-webkit-scrollbar-track { background: transparent; }
            .registration-container::-webkit-scrollbar-thumb { background: #dadce0; border-radius: 4px; }
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
            background-color: #1a73e8;
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
            background-color: #1765cc;
            box-shadow: 0 1px 3px 1px rgba(26, 115, 232, .15), 0 1px 2px 0 rgba(26, 115, 232, .3);
        }

        .btn-google-outline {
            background-color: transparent;
            color: #1a73e8;
            padding: 12px 28px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-google-outline:hover {
            background-color: #eff6ff;
        }

        .progress-stepper {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .step-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #e8eaed;
            transition: background-color 0.3s;
        }

        .step-dot.active {
            background-color: #1a73e8;
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
            color: #1a73e8;
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
                font-size: 13px;
                padding: 11px 12px;
            }

            /* Smaller label at rest to prevent overflow on narrow inputs */
            .google-label {
                font-size: 11px;
                top: 13px;
                max-width: calc(100% - 16px);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .google-input:focus + .google-label,
            .google-input:not(:placeholder-shown) + .google-label {
                font-size: 9px;
                top: -9px;
                white-space: normal;
                overflow: visible;
                max-width: calc(100% - 16px);
            }

            /* Navigation buttons: shrink font & allow wrapping so they don't overflow */
            .btn-google,
            .btn-google-outline {
                padding: 9px 12px;
                font-size: 11px;
                white-space: normal;
                text-align: center;
                line-height: 1.3;
            }

            /* Flex nav rows: wrap so both buttons stay visible */
            .flex.justify-between.items-center {
                flex-wrap: wrap;
                gap: 6px;
            }
            .flex.justify-between.items-center .btn-google,
            .flex.justify-between.items-center .btn-google-outline {
                flex: 1 1 auto;
                min-width: 0;
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

    <!-- Background -->
    <div class="bg-design">
        <picture>
            <source media="(max-width: 640px)" srcset="https://res.cloudinary.com/dnfbik3if/image/upload/v1785388824/Untitled_design_27_ahyugb.jpg">
            <img src="https://res.cloudinary.com/dnfbik3if/image/upload/v1784881096/Untitled_design_25_qqbbyb.jpg" class="bg-img" alt="Background">
        </picture>

        <div class="registration-container grid grid-cols-1 md:grid-cols-12 gap-2 md:gap-12 md:items-center">

            <!-- Left Side: Logo + Step Info -->
            <div class="md:col-span-5 flex flex-col items-center justify-center text-center md:min-h-[380px] space-y-4 pr-0 md:pr-8 relative z-10">
                <div class="flex flex-col items-center w-full">
                    <!-- Logo -->
                    <div class="mb-1 md:mb-2">
                        <img src="assests/logo.jpeg" alt="Lernerr.LK Logo" class="h-10 md:h-16 w-auto object-contain rounded-lg shadow-sm">
                    </div>

                    <div class="mt-2 hidden md:flex flex-col items-center">
                        <h1 id="stepTitle" class="text-xl font-bold text-[#1a73e8] leading-snug">Create your Teacher Account</h1>
                        <p id="stepSubtitle" class="text-xs text-slate-600 mt-4 max-w-[280px] leading-relaxed">Be a part of the Lernerr.LK team</p>

                        <div class="mt-6 flex items-center gap-2">
                            <span class="text-xs text-slate-400 font-medium" id="stepCounter">Step 1 of 6</span>
                        </div>
                        <div class="progress-stepper mt-3" id="progressStepper">
                            <div class="step-dot active"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Form -->
            <div class="md:col-span-7 relative z-10">


        <?php if (!empty($success_message)): ?>
            <!-- Success overlay -->
            <div id="successOverlay" style="position:fixed;inset:0;z-index:9999;background:rgba(255,255,255,0.97);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;text-align:center;padding:24px;">
                <!-- Animated checkmark -->
                <div style="width:80px;height:80px;background:#22c55e;border-radius:50%;display:flex;align-items:center;justify-content:center;animation:popIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h2 style="font-size:22px;font-weight:800;color:#15803d;margin:0;">Registration Successful!</h2>
                <p style="font-size:14px;color:#4b5563;margin:0;max-width:300px;"><?php echo htmlspecialchars($success_message); ?></p>
                <p style="font-size:13px;color:#6b7280;margin:0;">Redirecting to login in <span id="countdown" style="font-weight:700;color:#1a73e8;">2</span>s…</p>
                <a href="<?php echo BASE_PATH; ?>" style="font-size:13px;color:#1a73e8;font-weight:600;text-decoration:underline;">Go now →</a>
            </div>
            <style>
                @keyframes popIn { from{opacity:0;transform:scale(0.5);} to{opacity:1;transform:scale(1);} }
            </style>
            <script>
                (function() {
                    let secs = 2;
                    const el = document.getElementById('countdown');
                    const timer = setInterval(function() {
                        secs--;
                        if (el) el.textContent = secs;
                        if (secs <= 0) {
                            clearInterval(timer);
                            window.location.href = '<?php echo BASE_PATH; ?>';
                        }
                    }, 1000);
                })();
            </script>
        <?php endif; ?>


        <?php if (!empty($error_message)): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-700 p-4 rounded mb-6 text-sm">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="teacherRegisterForm" enctype="multipart/form-data" novalidate>
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

                <div class="google-input-group" style="position:relative;">
                    <input type="password" id="password" name="password" class="google-input" placeholder=" " required style="padding-right:42px;">
                    <label for="password" class="google-label">Password (මුරපදය)</label>
                    <button type="button" onclick="togglePassword('password','eyeIcon_password')"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#5f6368;padding:4px;"
                        tabindex="-1" aria-label="Show/hide password">
                        <svg id="eyeIcon_password" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>

                <div class="flex justify-between items-center mt-10">
                    <a href="login.php" class="text-blue-600 font-medium text-sm hover:underline">Sign in instead</a>
                    <button type="button" onclick="nextStep(1)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 2: Contact Numbers -->
            <div class="step-content" id="step2">
                <div class="google-input-group">
                    <input type="text" id="mobile_number" name="mobile_number" class="google-input" placeholder=" "
                        required value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                    <label for="mobile_number" class="google-label">Mobile Number (ජංගම දුරකථන අංකය)</label>
                </div>
                <div class="google-input-group">
                    <input type="text" id="whatsapp_number" name="whatsapp_number" class="google-input" placeholder=" "
                        value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                    <label for="whatsapp_number" class="google-label">WhatsApp Number (වට්ස්ඇප් අංකය)</label>
                </div>
                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(2)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(2)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 3: Choose Verification Method -->
            <div class="step-content" id="step3">
                <input type="hidden" id="verification_method" name="verification_method" value="">
                <input type="hidden" id="nic_verified_flag" name="nic_verified" value="0">

                <p class="text-xs font-semibold text-gray-500 mb-4">Choose One Verification Method (තහවුරු කිරීමේ ක්‍රමය තෝරන්න) *</p>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <!-- NIC Card -->
                    <div id="verifyCard_nic"
                         onclick="selectVerifyMethod('nic')"
                         class="verify-method-card cursor-pointer border-2 border-slate-200 rounded-xl p-5 flex flex-col items-center gap-2 text-center transition-all hover:border-blue-300 hover:bg-blue-50">
                        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-slate-700">NIC Number</span>
                        <span class="text-[11px] text-slate-400">ජාතික හැඳුනුම්පත</span>
                    </div>
                    <!-- WhatsApp OTP Card -->
                    <div id="verifyCard_otp"
                         onclick="selectVerifyMethod('otp')"
                         class="verify-method-card cursor-pointer border-2 border-slate-200 rounded-xl p-5 flex flex-col items-center gap-2 text-center transition-all hover:border-green-300 hover:bg-green-50">
                        <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3-3-3z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-slate-700">WhatsApp OTP</span>
                        <span class="text-[11px] text-slate-400">OTP හරහා තහවුරු කරන්න</span>
                    </div>
                </div>
                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(3)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(3)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 4: Verification Process -->
            <div class="step-content" id="step4">
                <!-- NIC Panel -->
                <div id="nicPanel" class="hidden google-input-group">
                    <p class="text-xs font-semibold text-gray-500 mb-3">Enter your NIC Number to verify (ජාතික හැඳුනුම්පත් අංකය ඇතුළත් කරන්න)</p>
                    <div class="flex gap-2 mobile-stack">
                        <div class="relative flex-1">
                            <input type="text" id="nic_number" name="nic_number" class="google-input uppercase" placeholder=" "
                                oninput="resetNicVerification()"
                                value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>">
                            <label for="nic_number" class="google-label">NIC Number (ජාතික හැඳුනුම්පත් අංකය)</label>
                        </div>
                        <button type="button" onclick="verifyNIC()" id="verifyNicBtn"
                            class="bg-blue-50 text-blue-600 px-4 rounded border border-blue-100 hover:bg-blue-100 font-semibold text-xs transition whitespace-nowrap">Check NIC</button>
                    </div>
                    <p id="nicMessage" class="mt-2 text-xs font-semibold"></p>
                </div>

                <!-- WhatsApp OTP Panel -->
                <div id="otpPanel" class="hidden">
                    <p class="text-xs font-semibold text-gray-500 mb-3">Verify via WhatsApp OTP (WhatsApp OTP හරහා තහවුරු කරන්න)</p>
                    <div class="google-input-group">
                        <div class="flex gap-2 mobile-stack">
                            <div class="relative flex-1">
                                <input type="text" id="otp_mobile" class="google-input" placeholder=" " oninput="resetMobileVerification()">
                                <label for="otp_mobile" class="google-label">WhatsApp / Mobile Number (ඔබේ අංකය)</label>
                            </div>
                            <button type="button" onclick="sendOTP()" id="sendOtpBtn"
                                class="bg-blue-50 text-blue-600 px-4 rounded border border-blue-100 hover:bg-blue-100 font-semibold text-xs transition whitespace-nowrap">Send OTP</button>
                        </div>
                    </div>
                    <div id="otpSection" class="hidden google-input-group p-4 bg-gray-50 border border-gray-200 rounded-lg mb-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-2">Enter 6-digit OTP (OTP අංකය ඇතුළත් කරන්න)</label>
                        <div class="flex gap-2">
                            <input type="text" id="otp_code" maxlength="6"
                                class="flex-1 google-input text-center text-lg tracking-widest" placeholder="xxxxxx">
                            <button type="button" onclick="verifyOTP()"
                                class="bg-emerald-600 text-white px-6 rounded font-semibold text-sm hover:bg-emerald-700 transition">Verify</button>
                        </div>
                        <p id="otpMessage" class="mt-2 text-xs font-semibold"></p>
                    </div>
                </div>

                <!-- Profile Picture -->
                <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg mb-4 mt-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Profile Picture</label>
                    <input type="file" name="profile_picture" accept="image/*"
                        class="text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-[10px] text-gray-400 mt-2">Max 5MB. JPG, PNG or WebP</p>
                </div>

                <div class="flex justify-between items-center mt-6">
                    <button type="button" onclick="prevStep(4)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(4)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 5: Academic Background -->
            <div class="step-content" id="step5">
                <div class="flex justify-between items-center mb-6 step3-header">
                    <h3 class="text-base font-semibold text-gray-800">Academic Background <span class="block text-xs font-normal text-gray-500">(අධ්‍යාපන සුදුසුකම්)</span></h3>
                    <button type="button" onclick="addEducationField()" class="text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded font-bold border border-blue-100 hover:bg-blue-100 transition whitespace-nowrap flex-shrink-0">+ Add Qualification</button>
                </div>

                <div id="educationContainer" class="space-y-4 max-h-[340px] overflow-y-auto pr-1"
                     style="scrollbar-width: thin; scrollbar-color: #c7d2fe transparent;">
                    <!-- Dynamic fields added via Javascript -->
                </div>

                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(5)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(5)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 6: Streams → Subjects → Register -->
            <div class="step-content" id="step6">

                <!-- Hidden Year -->
                <input type="hidden" id="academic_year" name="academic_year" value="<?php echo date('Y'); ?>">

                <!-- PANEL A: Stream Selection -->
                <div id="streamPanel">

                    <!-- Commission Rate & Pie Chart Breakdown -->
                    <div class="mb-4 p-4 bg-gradient-to-r from-emerald-50 via-teal-50 to-blue-50 border border-emerald-200/80 rounded-xl shadow-xs">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="flex-1 w-full">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="requested_rate_input" class="text-xs font-black text-gray-800 flex items-center gap-1.5">
                                        <i class="fas fa-percentage text-emerald-600"></i>
                                        Requested Commission Rate % (කොමිස් ප්‍රතිශතය) *
                                    </label>
                                    <span class="text-[11px] font-black text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded-full border border-emerald-300 shadow-2xs" id="rateBadgeText">
                                        75% Teacher Share
                                    </span>
                                </div>
                                
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="relative flex-1">
                                        <input type="number" 
                                               id="requested_rate_input" 
                                               name="requested_rate" 
                                               min="1" 
                                               max="100" 
                                               value="75" 
                                               oninput="updateCommissionPieChart(this.value)"
                                               class="w-full text-xs font-extrabold text-gray-800 bg-white border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none shadow-xs"
                                               required>
                                        <span class="absolute right-3 top-2 text-xs font-bold text-gray-400">%</span>
                                    </div>

                                    <!-- Quick Preset Buttons -->
                                    <div class="flex gap-1 shrink-0">
                                        <button type="button" onclick="setCommissionPreset(70)" class="px-2.5 py-1.5 text-xs font-extrabold bg-white border border-gray-200 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition text-gray-700 cursor-pointer shadow-2xs">70%</button>
                                        <button type="button" onclick="setCommissionPreset(75)" class="px-2.5 py-1.5 text-xs font-extrabold bg-white border border-gray-200 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition text-gray-700 cursor-pointer shadow-2xs">75%</button>
                                        <button type="button" onclick="setCommissionPreset(80)" class="px-2.5 py-1.5 text-xs font-extrabold bg-white border border-gray-200 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition text-gray-700 cursor-pointer shadow-2xs">80%</button>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 text-xs font-bold">
                                    <div class="flex items-center gap-1.5 text-emerald-700">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block shadow-2xs"></span>
                                        <span id="pieTeacherLegend">Teacher Share: 75%</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-blue-700">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block shadow-2xs"></span>
                                        <span id="piePlatformLegend">Platform Fee: 25%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic Donut / Pie Chart -->
                            <div class="shrink-0 flex flex-col items-center bg-white p-3 rounded-xl border border-emerald-100 shadow-xs">
                                <div class="relative w-16 h-16 rounded-full flex items-center justify-center shadow-md transition-all duration-300 ring-2 ring-emerald-100"
                                     id="commissionPieChart"
                                     style="background: conic-gradient(#10b981 0% 75%, #3b82f6 75% 100%);">
                                    <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-inner">
                                        <span id="pieTeacherVal" class="text-xs font-black text-emerald-600">75%</span>
                                    </div>
                                </div>
                                <span class="text-[10px] font-extrabold text-gray-400 mt-1 uppercase tracking-wider">Revenue Split</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs font-semibold text-gray-500 mb-2">Select Academic Stream(s) (විෂයධාරාවන් තෝරන්න) *</p>

                    <!-- Streams scrollable (Enhanced Height) -->
                    <div class="max-h-[280px] overflow-y-auto pr-1 mb-3 rounded-lg border border-gray-200 p-3 bg-gray-50/50"
                         style="scrollbar-width: thin; scrollbar-color: #bfdbfe transparent;">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                            <?php foreach ($streams as $stream): ?>
                                <label class="flex items-center space-x-2.5 border border-gray-200 rounded-xl p-3 bg-white hover:bg-blue-50/50 hover:border-blue-300 cursor-pointer transition shadow-xs select-none">
                                    <input type="checkbox" name="teacher_streams[]" value="<?php echo $stream['id']; ?>"
                                        class="teacher-stream-checkbox focus:ring-blue-500 text-blue-600 rounded w-4 h-4"
                                        onchange="loadTeacherSubjects()">
                                    <span class="text-xs font-extrabold text-gray-800 leading-tight"><?php echo htmlspecialchars($stream['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mt-4">
                        <button type="button" onclick="prevStep(6)" class="btn-google-outline">Back</button>
                        <button type="button" onclick="goToSubjectPanel()" class="btn-google px-6">Continue to Subjects &rarr;</button>
                    </div>
                </div>

                <!-- PANEL B: Subject Selection + Register -->
                <div id="subjectPanel" class="hidden">
                    <p class="text-xs font-semibold text-gray-500 mb-2">Select Subjects (විෂයන් තෝරන්න) *</p>

                    <!-- Subjects scrollable -->
                    <div class="mb-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <div id="teacherSubjectsGrid"
                             class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[150px] overflow-y-auto pr-1"
                             style="scrollbar-width: thin; scrollbar-color: #bfdbfe transparent;">
                            <div class="col-span-full py-3 text-center text-gray-400 text-xs">Select a stream first to see subjects.</div>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mt-3 gap-3">
                        <button type="button" onclick="backToStreamPanel()" class="btn-google-outline">&larr; Back to Streams</button>
                        <button type="button" onclick="goToEnrollmentPanel()" class="btn-google px-6 flex-shrink-0">Continue to Class Enrollments &rarr;</button>
                    </div>
                </div>

                <!-- PANEL C: Class Enrollments Configuration -->
                <div id="enrollmentPanel" class="hidden">
                    <div id="enrollmentsContainer" class="mb-2">
                        <!-- Dynamic step cards added via Javascript -->
                    </div>
                </div>

            </div>

        </form>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon  = document.getElementById(iconId);
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            icon.innerHTML = show
                ? '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
                : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
        window.preSelectedSubjects = <?php echo json_encode($_POST['teacher_subjects'] ?? []); ?>;
        window.preSelectedSubjects = window.preSelectedSubjects.map(String);

        let currentStep = 1;

        document.addEventListener('DOMContentLoaded', () => {
            showStep(currentStep);
            if (document.querySelectorAll('.education-field').length === 0) addEducationField();
        });

        function updateCommissionPieChart(val) {
            let rate = parseFloat(val);
            if (isNaN(rate)) rate = 75;
            rate = Math.min(100, Math.max(0, rate));
            
            const platform = (100 - rate).toFixed(0);
            const teacher = rate.toFixed(0);

            const pie = document.getElementById('commissionPieChart');
            const valText = document.getElementById('pieTeacherVal');
            const teacherLegend = document.getElementById('pieTeacherLegend');
            const platformLegend = document.getElementById('piePlatformLegend');
            const badgeText = document.getElementById('rateBadgeText');

            if (pie) pie.style.background = `conic-gradient(#10b981 0% ${teacher}%, #3b82f6 ${teacher}% 100%)`;
            if (valText) valText.textContent = `${teacher}%`;
            if (teacherLegend) teacherLegend.textContent = `Teacher Share: ${teacher}%`;
            if (platformLegend) platformLegend.textContent = `Platform Fee: ${platform}%`;
            if (badgeText) badgeText.textContent = `${teacher}% Teacher Share`;
        }

        function setCommissionPreset(val) {
            const input = document.getElementById('requested_rate_input');
            if (input) {
                input.value = val;
                updateCommissionPieChart(val);
            }
        }

        function showStep(n) {
            const steps = document.getElementsByClassName("step-content");
            const dots = document.getElementsByClassName("step-dot");
            
            const titles = [
                "Create your Teacher Account <span class='sinhala-subtitle'>(නව ගුරුවරයෙකු ලෙස ලියාපදිංචි වීම)</span>",
                "Contact Numbers <span class='sinhala-subtitle'>(දුරකථන අංක)</span>",
                "Verification Method <span class='sinhala-subtitle'>(තහවුරු කිරීමේ ක්‍රමය)</span>",
                "Verification Process <span class='sinhala-subtitle'>(තහවුරු කිරීම)</span>",
                "Academic Background <span class='sinhala-subtitle'>(අධ්‍යාපන පසුබිම)</span>",
                "Selecting the Stream <span class='sinhala-subtitle'>(විෂයධාරාවන් තෝරාගැනීම)</span>"
            ];
            
            const subtitles = [
                "Step 1 of 6: Personal Details",
                "Step 2 of 6: Contact Numbers",
                "Step 3 of 6: Choose One Verification Method",
                "Step 4 of 6: Complete Verification & Profile",
                "Step 5 of 6: Qualification Details",
                "Step 6 of 6: Selecting the Stream"
            ];

            for (let i = 0; i < steps.length; i++) {
                steps[i].classList.remove("active");
                if (dots[i]) dots[i].classList.remove("active");
            }
            if (steps[n - 1]) steps[n - 1].classList.add("active");
            if (dots[n - 1]) dots[n - 1].classList.add("active");

            const titleElem = document.getElementById("stepTitle");
            const subtitleElem = document.getElementById("stepSubtitle");
            const counterElem = document.getElementById("stepCounter");
            if (titleElem) titleElem.innerHTML = titles[n - 1];
            if (subtitleElem) subtitleElem.innerHTML = subtitles[n - 1];
            if (counterElem) counterElem.textContent = `Step ${n} of 6`;

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
                    input.classList.add('border-blue-500');
                    valid = false;
                } else {
                    input.classList.remove('border-blue-500');
                }
            });

            if (!valid) {
                alert("Please fill in all required fields (කරුණාකර සියලුම අනිවාර්ය ක්ෂේත්‍ර පුරවන්න).");
                return false;
            }

            // Step 3 Validation: Must choose a verification method
            if (n === 3) {
                const method = document.getElementById('verification_method').value;
                if (!method) {
                    alert("Please choose a verification method (කරුණාකර තහවුරු කිරීමේ ක්‍රමයක් තෝරන්න).");
                    return false;
                }
            }

            // Step 4 Validation: Must verify WhatsApp OTP or NIC
            if (n === 4) {
                const method = document.getElementById('verification_method').value;
                if (method === 'otp') {
                    const isVerified = document.getElementById('mobile_verified').value === '1';
                    if (!isVerified) {
                        alert("Please verify your WhatsApp number with the OTP code before proceeding to the next step (කරුණාකර ඊළඟ පියවරට යාමට පෙර WhatsApp OTP මගින් ඔබගේ අංකය තහවුරු කරන්න).");
                        const otpInput = document.getElementById('otp_code');
                        if (otpInput && !document.getElementById('otpSection').classList.contains('hidden')) {
                            otpInput.focus();
                        } else {
                            document.getElementById('sendOtpBtn')?.focus();
                        }
                        return false;
                    }
                } else if (method === 'nic') {
                    const isNicVerified = document.getElementById('nic_verified_flag').value === '1';
                    if (!isNicVerified) {
                        alert("Please verify your NIC before proceeding to the next step (කරුණාකර ඊළඟ පියවරට යාමට පෙර ඔබගේ ජාතික හැඳුනුම්පත් අංකය තහවුරු කරන්න).");
                        document.getElementById('nic_number')?.focus();
                        return false;
                    }
                } else {
                    alert("Please select and complete a verification method (කරුණාකර තහවුරු කිරීමේ ක්‍රමයක් තෝරා සම්පූර්ණ කරන්න).");
                    return false;
                }
            }

            return true;
        }

        let eduCount = 0;
        function addEducationField() {
            eduCount++;
            const container = document.getElementById('educationContainer');
            const div = document.createElement('div');
            div.className = 'p-4 border border-gray-200 rounded-xl bg-white shadow-xs education-field relative mb-4';
            div.innerHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                    <div class="google-input-group">
                        <input type="text" name="education[${eduCount}][qualification]" class="google-input" placeholder=" " required>
                        <label class="google-label">Qualification (සුදුසුකම)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="text" name="education[${eduCount}][institution]" class="google-input" placeholder=" " required>
                        <label class="google-label">Institution (ආයතනය)</label>
                    </div>
                    <div class="google-input-group sm:col-span-2">
                        <input type="number" name="education[${eduCount}][year_obtained]" class="google-input" placeholder=" " required>
                        <label class="google-label">Year Obtain (ලබාගත් වසර)</label>
                    </div>
                </div>
            `;
            container.appendChild(div);
            reindexEducationFields();
        }

        function reindexEducationFields() {
            const fields = document.querySelectorAll('.education-field');
            const badgeColors = [
                { bg: 'bg-blue-100', text: 'text-blue-800', border: 'border-blue-300' },
                { bg: 'bg-emerald-100', text: 'text-emerald-800', border: 'border-emerald-300' },
                { bg: 'bg-purple-100', text: 'text-purple-800', border: 'border-purple-300' },
                { bg: 'bg-amber-100', text: 'text-amber-900', border: 'border-amber-300' },
                { bg: 'bg-rose-100', text: 'text-rose-800', border: 'border-rose-300' },
                { bg: 'bg-indigo-100', text: 'text-indigo-800', border: 'border-indigo-300' },
                { bg: 'bg-teal-100', text: 'text-teal-800', border: 'border-teal-300' }
            ];

            fields.forEach((div, index) => {
                const num = index + 1;
                const color = badgeColors[index % badgeColors.length];
                
                let headerBadge = div.querySelector('.edu-badge');
                if (!headerBadge) {
                    headerBadge = document.createElement('div');
                    headerBadge.className = 'edu-badge flex justify-between items-center mb-3 pb-2 border-b border-gray-100';
                    div.prepend(headerBadge);
                }

                headerBadge.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold ${color.bg} ${color.text} border ${color.border}">
                        <span class="w-2 h-2 rounded-full bg-current"></span>
                        Academic Qualification #${num}
                    </span>
                    ${fields.length > 1 ? `
                        <button type="button" onclick="removeEducationField(this)" class="text-xs text-red-500 hover:text-red-700 font-bold flex items-center gap-1 bg-red-50 px-2.5 py-1 rounded border border-red-100 transition cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg> Remove
                        </button>
                    ` : ''}
                `;
            });
        }

        function removeEducationField(btn) {
            btn.closest('.education-field').remove();
            reindexEducationFields();
        }

        async function loadTeacherSubjects() {
            const streams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);
            const grid = document.getElementById('teacherSubjectsGrid');
            if (!grid) return;

            if (streams.length === 0) {
                grid.innerHTML = '<div class="col-span-full py-3 text-center text-gray-400 text-xs">Select a stream first to see subjects.</div>';
                return;
            }
            grid.innerHTML = '<div class="col-span-full py-4 text-center text-gray-400 text-xs">Loading...</div>';

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
                            label.className = 'flex items-center space-x-2 border rounded-lg p-3 bg-white hover:bg-blue-50/30 cursor-pointer select-none';
                            label.innerHTML = `<input type="checkbox" name="teacher_subjects[]" value="${sub.stream_subject_id}" class="focus:ring-blue-500 text-blue-600 rounded" ${isChecked}>
                                               <span class="text-sm font-semibold text-gray-700">${sub.name}</span>`;
                            grid.appendChild(label);
                        });
                    }
                });
                if (grid.innerHTML === '') {
                    grid.innerHTML = '<div class="col-span-full py-3 text-center text-gray-400 text-xs">No subjects found for selected stream(s).</div>';
                }
            } catch (e) {
                grid.innerHTML = '<div class="text-blue-500 text-xs font-bold col-span-full py-2">Error loading subjects.</div>';
            }
        }

        function goToSubjectPanel() {
            const streams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked'));
            if (streams.length === 0) {
                alert('Please select at least one stream (කරුණාකර විෂයධාරාවක් තෝරන්න).');
                return;
            }
            loadTeacherSubjects();
            document.getElementById('streamPanel').classList.add('hidden');
            document.getElementById('subjectPanel').classList.remove('hidden');
        }

        function backToStreamPanel() {
            document.getElementById('subjectPanel').classList.add('hidden');
            document.getElementById('streamPanel').classList.remove('hidden');
        }

        let enrollmentCount = 0;
        let activeEnrollmentIndex = 0;
        let selectedSubjectOptions = [];

        function goToEnrollmentPanel() {
            const checkedSubjects = Array.from(document.querySelectorAll('input[name="teacher_subjects[]"]:checked'));

            if (checkedSubjects.length === 0) {
                alert('Please select at least one subject (කරුණාකර අවම වශයෙන් එක් විෂයක් තෝරන්න).');
                return;
            }

            selectedSubjectOptions = [];
            checkedSubjects.forEach(cb => {
                const labelText = cb.closest('label').querySelector('span').innerText;
                selectedSubjectOptions.push({ id: cb.value, name: labelText });
            });

            document.getElementById('subjectPanel').classList.add('hidden');
            document.getElementById('enrollmentPanel').classList.remove('hidden');

            const container = document.getElementById('enrollmentsContainer');
            if (container.children.length === 0) {
                addEnrollmentStep();
            } else {
                showEnrollmentStep(0);
            }
        }

        function backToSubjectPanel() {
            document.getElementById('enrollmentPanel').classList.add('hidden');
            document.getElementById('subjectPanel').classList.remove('hidden');
            showStep(6);
        }

        function buildOptionHtml() {
            let html = '<option value="">-- Select Subject --</option>';
            selectedSubjectOptions.forEach(opt => {
                html += `<option value="${opt.id}">${opt.name}</option>`;
            });
            return html;
        }

        function buildYearOptionsHtml() {
            const currYear = new Date().getFullYear();
            let html = '';
            for (let y = 0; y <= 4; y++) {
                html += `<option value="${currYear + y}">${currYear + y}</option>`;
            }
            return html;
        }

        function createEnrollmentCard(idx, data = {}) {
            const div = document.createElement('div');
            div.id = `enrollmentCard_${idx}`;
            div.className = 'enrollment-step-card p-4 border border-slate-200 rounded-xl bg-slate-50 relative';
            div.style.display = 'none';

            div.innerHTML = `
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <div>
                        <span class="text-xs font-extrabold text-blue-600 uppercase tracking-wider">Create Initial Class</span>
                    </div>
                </div>

                <div class="mb-3 p-3 bg-blue-50/80 border border-blue-200 rounded-lg text-xs text-blue-800 flex items-start gap-2">
                    <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <span class="font-bold">Initial Class Setup:</span> Configure 1 class during registration. When you joined to Lernerr.LK, you can create more classes/enrolls.
                        <div class="text-[11px] text-slate-600 mt-0.5 font-medium">ඔබ Lernerr.LK හා එක්වූ පසු ඔබට නව පන්ති නිර්මාණය (Create) කළ හැක.</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-2">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Subject (විෂය) *</label>
                        <select name="class_enrollments[${idx}][stream_subject_id]" data-field="stream_subject_id" class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            ${buildOptionHtml()}
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Exam Year (Academic Year) *</label>
                        <select name="class_enrollments[${idx}][academic_year]" data-field="academic_year" class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            ${buildYearOptionsHtml()}
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Name of the Class (පන්ති නාමය) *</label>
                        <input type="text" name="class_enrollments[${idx}][batch_name]" data-field="batch_name" placeholder="e.g. 2027 Chemistry Paper Class" class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Class Cover Image (කවර් පින්තූරය)</label>
                        <input type="file" name="class_cover_image_${idx}" accept="image/*" class="w-full text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Enrollment Fee / Rs. (ලියාපදිංචි ගාස්තුව)</label>
                        <input type="number" step="0.01" min="0" name="class_enrollments[${idx}][enrollment_fee]" data-field="enrollment_fee" placeholder="e.g. 1000" class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Monthly Fee / Rs. (මාසික ගාස්තුව)</label>
                        <input type="number" step="0.01" min="0" name="class_enrollments[${idx}][monthly_fee]" data-field="monthly_fee" placeholder="e.g. 2500" class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                </div>

                <div class="flex flex-wrap justify-between items-center mt-6 pt-3 border-t border-slate-200 gap-2">
                    <button type="button" onclick="backToSubjectPanel()" class="btn-google-outline text-xs px-4 py-2">&larr; Back to Subjects</button>
                    <button type="submit" onclick="return validateBeforeSubmit()" class="btn-google text-xs px-6 py-2.5 shadow-md shadow-blue-500/20">
                        Register as Teacher
                    </button>
                </div>
            `;

            if (data.stream_subject_id) div.querySelector('select[data-field="stream_subject_id"]').value = data.stream_subject_id;
            if (data.academic_year) div.querySelector('select[data-field="academic_year"]').value = data.academic_year;
            if (data.batch_name) div.querySelector('input[data-field="batch_name"]').value = data.batch_name;
            if (data.enrollment_fee) div.querySelector('input[data-field="enrollment_fee"]').value = data.enrollment_fee;
            if (data.monthly_fee) div.querySelector('input[data-field="monthly_fee"]').value = data.monthly_fee;

            return div;
        }

        function addEnrollmentStep() {
            const container = document.getElementById('enrollmentsContainer');
            if (container.children.length === 0) {
                container.appendChild(createEnrollmentCard(0, {}));
            }
            showEnrollmentStep(0);
        }

        function showEnrollmentStep(targetIdx) {
            const cards = document.querySelectorAll('.enrollment-step-card');
            cards.forEach((card, i) => card.style.display = i === targetIdx ? 'block' : 'none');
            activeEnrollmentIndex = targetIdx;

            const titleElem = document.getElementById("stepTitle");
            const subtitleElem = document.getElementById("stepSubtitle");
            if (titleElem) titleElem.innerHTML = `Class Enrollment <span class='sinhala-subtitle'>(පන්තිය නිර්මාණය කිරීම)</span>`;
            if (subtitleElem) subtitleElem.innerHTML = `Step 6 of 6: Create your initial class.<br><span class='text-[11px] text-blue-600 font-bold mt-1 inline-block'>When you joined to Lernerr.LK, you can create more classes/enrolls.<br><span class='font-normal text-slate-500'>(ඔබ Lernerr.LK හා එක්වූ පසු ඔබට නව පන්ති නිර්මාණය Create කළ හැක.)</span></span>`;
        }

        function validateEnrollmentStep(cardIdx) {
            const cards = document.querySelectorAll('.enrollment-step-card');
            const card = cards[cardIdx];
            if (!card) return true;

            const subjectSelect = card.querySelector('select[data-field="stream_subject_id"]');
            if (subjectSelect && !subjectSelect.value) {
                alert('Please select a subject for this class enrollment (කරුණාකර විෂයක් තෝරන්න).');
                subjectSelect.focus();
                return false;
            }
            const classNameInput = card.querySelector('input[data-field="batch_name"]');
            if (classNameInput && !classNameInput.value.trim()) {
                alert('Please enter the Name of the Class (කරුණාකර පන්ති නාමය ඇතුළත් කරන්න).');
                classNameInput.focus();
                return false;
            }
            return true;
        }

        function deleteEnrollmentStep(btnIdx) {
            const container = document.getElementById('enrollmentsContainer');
            const cards = Array.from(container.querySelectorAll('.enrollment-step-card'));

            // Save data from all current cards
            const savedData = cards.map(card => ({
                stream_subject_id: (card.querySelector('select[data-field="stream_subject_id"]') || {}).value || '',
                academic_year:     (card.querySelector('select[data-field="academic_year"]') || {}).value || '',
                batch_name:        (card.querySelector('input[data-field="batch_name"]') || {}).value || '',
                enrollment_fee:    (card.querySelector('input[data-field="enrollment_fee"]') || {}).value || '',
                monthly_fee:       (card.querySelector('input[data-field="monthly_fee"]') || {}).value || ''
            }));

            // Remove the card at btnIdx position
            savedData.splice(btnIdx, 1);

            if (savedData.length === 0) {
                backToSubjectPanel();
                return;
            }

            // Rebuild all cards with fresh sequential 0-based indices
            container.innerHTML = '';
            enrollmentCount = savedData.length;
            savedData.forEach((data, idx) => container.appendChild(createEnrollmentCard(idx, data)));

            showEnrollmentStep(Math.max(0, btnIdx - 1));
        }

        function sendOTP() {
            const mob = document.getElementById('otp_mobile').value.trim()
                     || document.getElementById('mobile_number').value.trim();
            if (!mob) return alert('Enter mobile number! (ජංගම දුරකථන අංකය ඇතුළත් කරන්න)');
            const btn = document.getElementById('sendOtpBtn');
            btn.innerText = 'Sending...';
            btn.disabled = true;
            const fd = new FormData();
            fd.append('mobile_number', mob);
            fetch('send_otp.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('otpSection').classList.remove('hidden');
                        const msg = document.getElementById('otpMessage');
                        msg.innerText = '✓ OTP sent to your WhatsApp! Enter the 6-digit code below. (OTP WhatsApp වෙත යවා ඇත)';
                        msg.className = 'mt-2 text-xs font-semibold text-blue-600';
                        btn.innerText = 'Resend';
                    } else {
                        alert('Failed to send OTP: ' + (data.message || 'Unknown error'));
                        btn.innerText = 'Send OTP';
                    }
                    btn.disabled = false;
                })
                .catch(() => {
                    alert('Error sending OTP. Please try again.');
                    btn.innerText = 'Send OTP';
                    btn.disabled = false;
                });
        }

        function verifyOTP() {
            const code = document.getElementById('otp_code').value.trim();
            const msg = document.getElementById('otpMessage');
            if (!code || code.length !== 6) {
                alert('Please enter a valid 6-digit OTP.');
                return;
            }
            const fd = new FormData();
            fd.append('otp_code', code);
            fetch('verify_otp.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.verified) {
                        msg.innerText = '✓ Mobile Number Verified! (ජංගම දුරකථන අංකය සාර්ථකව තහවුරු කරන ලදී)';
                        msg.className = 'mt-2 text-xs font-bold text-emerald-600';
                        document.getElementById('mobile_verified').value = '1';
                    } else {
                        msg.innerText = '✗ ' + (data.message || 'Invalid OTP. Please try again.');
                        msg.className = 'mt-2 text-xs font-bold text-red-600';
                    }
                })
                .catch(() => {
                    msg.innerText = '✗ Error verifying OTP. Please try again.';
                    msg.className = 'mt-2 text-xs font-bold text-red-600';
                });
        }

        function resetMobileVerification() {
            document.getElementById('mobile_verified').value = '0';
            const msg = document.getElementById('otpMessage');
            if (msg) msg.innerText = '';
        }

        function resetNicVerification() {
            document.getElementById('nic_verified_flag').value = '0';
            const msg = document.getElementById('nicMessage');
            if (msg) msg.innerText = '';
        }

        function validateBeforeSubmit() {
            const method = document.getElementById('verification_method').value;
            if (method === 'otp' && document.getElementById('mobile_verified').value !== '1') {
                alert('Please verify your WhatsApp number with OTP before submitting (කරුණාකර ලියාපදිංචි වීමට පෙර WhatsApp OTP තහවුරු කරන්න).');
                showStep(4);
                return false;
            }
            if (method === 'nic' && document.getElementById('nic_verified_flag').value !== '1') {
                alert('Please verify your NIC before submitting (කරුණාකර ලියාපදිංචි වීමට පෙර ජාතික හැඳුනුම්පත තහවුරු කරන්න).');
                showStep(4);
                return false;
            }
            if (!method) {
                alert('Please complete the verification step before submitting (කරුණාකර තහවුරු කිරීමේ පියවර සම්පූර්ණ කරන්න).');
                showStep(3);
                return false;
            }

            const cards = document.querySelectorAll('.enrollment-step-card');
            for (let i = 0; i < cards.length; i++) {
                if (!validateEnrollmentStep(i)) {
                    showEnrollmentStep(i);
                    return false;
                }
            }
            return true;
        }

        function selectVerifyMethod(method) {
            document.getElementById('verification_method').value = method;
            document.querySelectorAll('.verify-method-card').forEach(c => {
                c.classList.remove('border-blue-500', 'bg-blue-50', 'border-green-500', 'bg-green-50');
                c.classList.add('border-slate-200');
            });
            const nicPanel = document.getElementById('nicPanel');
            const otpPanel = document.getElementById('otpPanel');
            if (method === 'nic') {
                document.getElementById('verifyCard_nic').classList.remove('border-slate-200');
                document.getElementById('verifyCard_nic').classList.add('border-blue-500', 'bg-blue-50');
                nicPanel.classList.remove('hidden');
                otpPanel.classList.add('hidden');
            } else {
                document.getElementById('verifyCard_otp').classList.remove('border-slate-200');
                document.getElementById('verifyCard_otp').classList.add('border-green-500', 'bg-green-50');
                otpPanel.classList.remove('hidden');
                nicPanel.classList.add('hidden');
                const wa = document.getElementById('whatsapp_number').value.trim();
                const mob = document.getElementById('mobile_number').value.trim();
                if (wa || mob) document.getElementById('otp_mobile').value = wa || mob;
            }
        }

        // sendOTP and verifyOTP are defined above (real AJAX implementations)

        function verifyNIC() {
            const nic = document.getElementById('nic_number').value.trim();
            const msg = document.getElementById('nicMessage');
            if (!nic) return alert('Enter NIC number! (ජාතික හැඳුනුම්පත් අංකය ඇතුළත් කරන්න)');

            msg.innerText = 'Verifying NIC...';
            msg.className = 'mt-2 text-xs font-semibold text-slate-500';

            const fd = new FormData();
            fd.append('nic', nic);

            fetch('verify_nic.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.valid) {
                        msg.innerText = '✓ NIC verified successfully! (ජාතික හැඳුනුම්පත සාර්ථකව තහවුරු කරන ලදී)';
                        msg.className = 'mt-2 text-xs font-bold text-emerald-600';
                        document.getElementById('nic_verified_flag').value = '1';
                        if (data.dob) document.getElementById('dob').value = data.dob;
                        if (data.gender) document.getElementById('gender').value = data.gender;
                    } else {
                        msg.innerText = '✗ ' + (data.message || 'Invalid NIC number. Please check again.');
                        msg.className = 'mt-2 text-xs font-bold text-red-600';
                        document.getElementById('nic_verified_flag').value = '0';
                    }
                })
                .catch(() => {
                    msg.innerText = '✗ Error verifying NIC. Please try again.';
                    msg.className = 'mt-2 text-xs font-bold text-red-600';
                    document.getElementById('nic_verified_flag').value = '0';
                });
        }
    </script>
</body>
</html>
