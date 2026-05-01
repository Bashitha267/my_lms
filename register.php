<?php




require_once 'config.php';

$success_message = '';
$error_message = '';

// Initialize empty values for GET request
$first_name = '';
$second_name = '';
$second_name = '';
$mobile_number = '';
$whatsapp_number = '';
$verification_method = 'none';
$nic_number = '';
$nic_verified = 0;
$otp_verified = 0;
$dob = '';
$school_name = '';
$exam_year = '';
$district = '';
$address = '';
$gender = '';

// Get enrollment parameters from URL if not in POST
$url_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$url_stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$url_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Set initial values ensuring POST takes precedence
$enrollment_type = $_POST['enrollment_type'] ?? ($url_course_id > 0 ? 'course' : 'subject');
$course_id_selected = $_POST['course_id'] ?? $url_course_id;
$stream_id_selected = $_POST['stream_id'] ?? $url_stream_id;
$subject_id_selected = $_POST['subject_id'] ?? $url_subject_id;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Removed username input processing
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? ''; // Added confirm password

    $role = 'student'; // Only student registration allowed
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $verification_method = trim($_POST['verification_method'] ?? 'none');
    $nic_number = trim($_POST['nic_number'] ?? '');
    $nic_verified = isset($_POST['nic_verified']) ? intval($_POST['nic_verified']) : 0;
    $otp_verified = isset($_POST['otp_verified']) ? intval($_POST['otp_verified']) : 0;

    // Student-specific fields


    // Student-specific fields
    $dob = !empty($_POST['dob']) ? trim($_POST['dob']) : null;
    $school_name = !empty($_POST['school_name']) ? trim($_POST['school_name']) : null;
    $exam_year = !empty($_POST['exam_year']) ? intval($_POST['exam_year']) : null;
    $district = !empty($_POST['district']) ? trim($_POST['district']) : null;
    $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
    $gender = !empty($_POST['gender']) ? trim($_POST['gender']) : null;

    // Determine approval status based on verification (students only)
    $approved = 0;
    $verification_status = 'pending';

    // For students: verification determines approval
    if ($verification_method === 'nic' && $nic_verified === 1) {
        $approved = 1;
        $verification_status = 'verified_nic';
    } elseif ($verification_method === 'mobile' && $otp_verified === 1) {
        $approved = 1;
        $verification_status = 'verified_mobile';
    } else {
        // Verification failed or not completed - require admin approval
        $approved = 0;
        $verification_status = 'pending';
    }

    // Validation (students only)
    if (empty($password)) {
        $error_message = 'Password is required.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (empty($verification_method) || $verification_method === 'none') {
        $error_message = 'Please select a verification method and complete the verification.';
    } elseif ($verification_method === 'nic' && $nic_verified !== 1) {
        $error_message = 'Please verify your NIC number before submitting.';
    } elseif ($verification_method === 'mobile' && $otp_verified !== 1) {
        $error_message = 'Please verify your mobile number with OTP before submitting.';
    } else {
        // Additional validation for students
        $enrollment_type = $_POST['enrollment_type'] ?? 'subject';

        if ($enrollment_type === 'subject') {
            $stream_id_input = $_POST['stream_id'] ?? '';
            $subject_id_input = $_POST['subject_id'] ?? '';
            $selected_teacher_id = trim($_POST['selected_teacher_id'] ?? '');

            if (intval($stream_id_input) <= 0) {
                $error_message = 'Please select a stream.';
            } elseif (intval($subject_id_input) <= 0) {
                $error_message = 'Please select a subject.';
            } elseif (empty($selected_teacher_id)) {
                $error_message = 'Please select a teacher.';
            }
        } elseif ($enrollment_type === 'course') {
            $course_id_input = $_POST['course_id'] ?? '';
            if (intval($course_id_input) <= 0) {
                $error_message = 'Please select a course.';
            }
        }

        // If no validation errors, proceed with user creation
        if (empty($error_message)) {
            // Generate user_id based on role
            $role_prefix = [
                'student' => 'stu',
                'teacher' => 'tea',
                'instructor' => 'ins',
                'admin' => 'adm'
            ];
            $prefix = $role_prefix[$role] ?? 'usr';

            // Get next number for this role
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
            $pattern = $prefix . '_%';
            $stmt->bind_param("s", $pattern);
            $stmt->execute();
            $result = $stmt->get_result();

            $next_num = 1000; // Start from 1000
            if ($result->num_rows > 0) {
                $last_user = $result->fetch_assoc();
                $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
                $next_num = max($last_num + 1, 1000);
            }
            $stmt->close();

            $user_id = $prefix . '_' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            // $username = $user_id; // Removed username assignment

            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Handle profile picture upload (optional for students)
            $profile_picture_path = null;

            // Process upload if file is provided
            if (empty($error_message) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK && !empty($_FILES['profile_picture']['name'])) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file = $_FILES['profile_picture'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                // Validate file type
                if (!in_array($file_ext, $allowed_extensions)) {
                    $error_message = 'Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.';
                } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error_message = 'File size too large. Maximum size is 5MB.';
                } else {
                    // Generate unique filename
                    $new_filename = $user_id . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $profile_picture_path = 'uploads/profiles/' . $new_filename;
                    } else {
                        $error_message = 'Failed to upload profile picture.';
                    }
                }
            }

            // If no upload errors, proceed with user creation
            if (empty($error_message)) {
                $nic_no_value = ($verification_method === 'nic' && !empty($nic_number)) ? $nic_number : null;
                $verification_method_value = ($verification_method !== 'none') ? $verification_method : 'none';

                $stmt = $conn->prepare("INSERT INTO users (user_id, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status, nic_no, verification_method, dob, school_name, exam_year, district, address, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssissssisss", $user_id, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $profile_picture_path, $approved, $nic_no_value, $verification_method_value, $dob, $school_name, $exam_year, $district, $address, $gender);

                if ($stmt->execute()) {
                    // Handle student-specific data
                    // Handle student-specific data
                    if ($role === 'student') {
                        $enrollment_type = $_POST['enrollment_type'] ?? 'subject';
                        $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');

                        if ($enrollment_type === 'subject') {
                            $stream_id_input = $_POST['stream_id'] ?? '';
                            $subject_id_input = $_POST['subject_id'] ?? '';

                            $stream_id = intval($stream_id_input);
                            $subject_id = intval($subject_id_input);

                            // Create stream_subject if it doesn't exist
                            if (empty($error_message) && $stream_id > 0 && $subject_id > 0) {
                                $check_ss = $conn->prepare("SELECT id FROM stream_subjects WHERE stream_id = ? AND subject_id = ?");
                                $check_ss->bind_param("ii", $stream_id, $subject_id);
                                $check_ss->execute();
                                $ss_result = $check_ss->get_result();

                                $stream_subject_id = null;
                                if ($ss_result->num_rows > 0) {
                                    $ss_row = $ss_result->fetch_assoc();
                                    $stream_subject_id = $ss_row['id'];
                                } else {
                                    $create_ss = $conn->prepare("INSERT INTO stream_subjects (stream_id, subject_id, status) VALUES (?, ?, 1)");
                                    $create_ss->bind_param("ii", $stream_id, $subject_id);
                                    if ($create_ss->execute()) {
                                        $stream_subject_id = $conn->insert_id;
                                    } else {
                                        $error_message = 'Error creating stream-subject combination: ' . $conn->error;
                                    }
                                    $create_ss->close();
                                }
                                $check_ss->close();

                                // Insert student enrollment
                                if (empty($error_message) && $stream_subject_id) {
                                    $enroll_stmt = $conn->prepare("INSERT INTO student_enrollment (student_id, stream_subject_id, academic_year, enrolled_date, status, payment_status) VALUES (?, ?, ?, CURDATE(), 'active', 'pending')");
                                    $enroll_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);

                                    if (!$enroll_stmt->execute()) {
                                        $error_message = 'User created but failed to enroll student: ' . $enroll_stmt->error;
                                    } else {
                                        // Enrollment Success - Send WhatsApp
                                        if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                                            $sub_stmt = $conn->prepare("SELECT name FROM subjects WHERE id = ?");
                                            $sub_stmt->bind_param("i", $subject_id);
                                            $sub_stmt->execute();
                                            $sub_res = $sub_stmt->get_result();
                                            if ($sub_row = $sub_res->fetch_assoc()) {
                                                $subj_name = $sub_row['name'];
                                                $enroll_msg = "📚 * / ඇතුළත් වීම  සාර්ථකයි*\n\n" .
                                                    "Hello {$first_name},\n" .
                                                    "Enrollment Successful \n\n,You have successfully enrolled in the subject: *{$subj_name}*\n\n" .
                                                    "--------------------------\n\n" .
                                                    "ඔබ සාර්ථකව *{$subj_name}* විෂය සඳහා ලියාපදිංචි වී ඇත.";
                                                sendWhatsAppMessage($whatsapp_number, $enroll_msg);
                                            }
                                            $sub_stmt->close();
                                        }
                                    }
                                    $enroll_stmt->close();
                                }
                            }
                        } elseif ($enrollment_type === 'course') {
                            $course_id = intval($_POST['course_id'] ?? 0);

                            if (empty($error_message) && $course_id > 0) {
                                // Enroll in course
                                $enroll_stmt = $conn->prepare("INSERT INTO course_enrollments (course_id, student_id, enrolled_at, status, payment_status) VALUES (?, ?, NOW(), 'active', 'pending')");
                                $enroll_stmt->bind_param("is", $course_id, $user_id);

                                if (!$enroll_stmt->execute()) {
                                    if ($conn->errno != 1062) { // Ignore duplicate entry
                                        $error_message = 'User created but failed to enroll in course: ' . $enroll_stmt->error;
                                    }
                                } else {
                                    // Enrollment Success - Send WhatsApp
                                    if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                                        $crs_stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
                                        $crs_stmt->bind_param("i", $course_id);
                                        $crs_stmt->execute();
                                        $crs_res = $crs_stmt->get_result();
                                        if ($crs_row = $crs_res->fetch_assoc()) {
                                            $course_name = $crs_row['name'];
                                            $enroll_msg = "🎓 *Course Enrollment Successful*\n\n" .
                                                "Hello {$first_name},\n" .
                                                "You have successfully enrolled in the course: *{$course_name}*";
                                            sendWhatsAppMessage($whatsapp_number, $enroll_msg);
                                        }
                                        $crs_stmt->close();
                                    }
                                }
                                $enroll_stmt->close();
                            }
                        }
                    }


                    if (empty($error_message)) {
                        // Send welcome message via WhatsApp
                        if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                            try {
                                $welcome_msg = "🎓 *Welcome to LearnerX!* 🎓\n\n" .
                                    "Hello {$first_name}, your account has been successfully created.\n" .
                                    "🆔 *User ID:* {$user_id}\n\n" .
                                    "--------------------------\n\n" .
                                    "LearnerX වෙත ඔබව සාදරයෙන් පිළිගනිමු! 👋\n" .
                                    "ඔබේ ලියාපදිංචිය සාර්ථකයි.\n" .
                                    "🆔 *පරිශීලක හැඳුනුම්පත:* {$user_id}\n\n" .
                                    "දැන් ඔබට පන්ති සමඟ සම්බන්ධ විය හැක. ස්තුතියි!";

                                sendWhatsAppMessage($whatsapp_number, $welcome_msg);
                            } catch (Exception $e) {
                                error_log("WhatsApp welcome message failed: " . $e->getMessage());
                            }
                        }

                        $ui_welcome_msg = "Welcome to LearnerX! 🎓\n\nHello $first_name, your account has been successfully created.\nYour User ID is: $user_id.\n\nLearnerX වෙත ඔබව සාදරයෙන් පිළිගනිමු! 👋\nඔබේ ලියාපදිංචිය සාර්ථකයි.\nපරිශීලක හැඳුනුම්පත: $user_id";

                        if ($approved == 1) {
                            header("Location: login.php?success=" . urlencode($ui_welcome_msg));
                            exit;
                        } else {
                            $success_message = $ui_welcome_msg . "\n\nYour account is pending admin approval. You will be able to login once approved.";
                        }
                        // Clear form data
                        $_POST = array();
                    }
                } else {
                    if ($conn->errno == 1062) {
                        $error_message = 'User ID already exists.';
                    } else {
                        $error_message = 'Error creating user: ' . $conn->error;
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Get streams for dropdown
$streams_query = "SELECT id, name FROM streams WHERE status = 1 ORDER BY name";
$streams_result = $conn->query($streams_query);
$streams = ($streams_result && $streams_result->num_rows > 0) ? $streams_result->fetch_all(MYSQLI_ASSOC) : [];

// Get available courses for selection
$courses_query = "SELECT id, teacher_id, title, price, cover_image FROM courses WHERE status = 1 ORDER BY title";
$courses_result = $conn->query($courses_query);
$courses = [];
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        // Fetch teacher details safely
        $t_stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
        $t_stmt->bind_param("s", $row['teacher_id']);
        $t_stmt->execute();
        $t_res = $t_stmt->get_result();
        if ($t = $t_res->fetch_assoc()) {
            $row['teacher_name'] = $t['first_name'] . ' ' . $t['second_name'];
        } else {
            $row['teacher_name'] = 'Unknown Teacher';
        }
        $t_stmt->close();
        $courses[] = $row;
    }
}

// Sri Lanka Districts
$districts = [
    'Ampara',
    'Anuradhapura',
    'Badulla',
    'Batticaloa',
    'Colombo',
    'Galle',
    'Gampaha',
    'Hambantota',
    'Jaffna',
    'Kalutara',
    'Kandy',
    'Kegalle',
    'Kilinochchi',
    'Kurunegala',
    'Mannar',
    'Matale',
    'Matara',
    'Monaragala',
    'Mullaitivu',
    'Nuwara Eliya',
    'Polonnaruwa',
    'Puttalam',
    'Ratnapura',
    'Trincomalee',
    'Vavuniya'
];

// Pre-calculate next User ID for display (start from 1000)
$role_prefix_display = 'stu';
$stmt_display = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
$pattern_display = $role_prefix_display . '_%';
$stmt_display->bind_param("s", $pattern_display);
$stmt_display->execute();
$result_display = $stmt_display->get_result();

$next_num_display = 1000;
if ($result_display->num_rows > 0) {
    $last_user = $result_display->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], strlen($role_prefix_display) + 1));
    $next_num_display = max($last_num + 1, 1000);
}
$stmt_display->close();
$display_user_id = $role_prefix_display . '_' . str_pad($next_num_display, 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - LERNERR.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
        }

        .registration-container {
            width: 100%;
            max-width: 550px;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 1px 2px 0 rgba(60, 64, 67, .3), 0 1px 3px 1px rgba(60, 64, 67, .15);
            transition: all 0.3s ease;
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
            font-size: 16px;
            pointer-events: none;
            transition: all 0.2s;
        }

        .google-input:focus+.google-label,
        .google-input:not(:placeholder-shown)+.google-label {
            top: -10px;
            left: 10px;
            font-size: 12px;
            color: #1a73e8;
            font-weight: 500;
        }

        .google-input:not(:focus)+.google-label {
            color: #5f6368;
        }

        .btn-google {
            background-color: #1a73e8;
            color: white;
            padding: 10px 24px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-google:hover {
            background-color: #1765cc;
            box-shadow: 0 1px 3px 1px rgba(66, 133, 244, .15), 0 1px 2px 0 rgba(66, 133, 244, .3);
        }

        .btn-google-outline {
            background-color: transparent;
            color: #1a73e8;
            padding: 10px 24px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-google-outline:hover {
            background-color: #f6f9fe;
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
            background-color: #1a73e8;
        }

        .google-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }

        .google-logo svg {
            width: 75px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .section-header h1 {
            font-size: 20px;
            font-weight: 500;
            color: #ea4335;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

    <div class="registration-container">
        <!-- LERNERR.LK Logo -->
        <div class="google-logo">
            <h2 class="text-2xl font-black tracking-tighter text-[#ea4335]">
                LERNERR.LK
            </h2>
        </div>

        <div class="section-header">
            <h1 id="stepTitle" class="text-xl font-bold text-[#ea4335]">Create your Student Account</h1>
            <p id="stepSubtitle" class="text-base font-semibold text-gray-700 mt-1">ගිණුමක් සාදාගන්න</p>
        </div>

        <div class="progress-stepper" id="progressStepper">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
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

        <form method="POST" action="" id="addUserForm" enctype="multipart/form-data">
            <input type="hidden" id="role" name="role" value="student">

            <!-- STEP 1: Personal Info -->
            <div class="step-content active" id="step1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
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
                    <input type="date" id="dob" name="dob" class="google-input" placeholder=" " required
                        max="<?php echo date('Y-m-d', strtotime('-10 years')); ?>"
                        value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                    <label for="dob" class="google-label">Birthday (උපන් දිනය)</label>
                </div>

                <div class="google-input-group">
                    <select id="gender" name="gender" class="google-input" required>
                        <option value="" disabled selected hidden></option>
                        <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male
                            (පුරුෂ)</option>
                        <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>
                            Female (ස්ත්‍රී)</option>
                    </select>
                    <label for="gender" class="google-label">Gender (ස්ත්‍රී/පුරුෂ භාවය)</label>
                </div>

                <div class="flex justify-between items-center mt-10">
                    <a href="login.php" class="text-blue-600 font-medium text-sm hover:underline">Sign in instead</a>
                    <button type="button" onclick="nextStep(1)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 2: Security -->
            <div class="step-content" id="step2">
                <div class="google-input-group">
                    <input type="password" id="password" name="password" class="google-input" placeholder=" " required>
                    <label for="password" class="google-label">Password (මුරපදය)</label>
                </div>
                <div class="google-input-group">
                    <input type="password" id="confirm_password" name="confirm_password" class="google-input"
                        placeholder=" " required>
                    <label for="confirm_password" class="google-label">Confirm (තහවුරු කරන්න)</label>
                </div>
                <p class="text-xs text-gray-500 mb-6 px-1">Use 8 or more characters with a mix of letters, numbers &
                    symbols</p>

                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(2)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(2)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 3: Contact & Location -->
            <div class="step-content" id="step3">
                <div class="google-input-group">
                    <input type="text" id="whatsapp_number" name="whatsapp_number" class="google-input" placeholder=" "
                        required value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                    <label for="whatsapp_number" class="google-label">WhatsApp number (වට්ස්ඇප් අංකය)</label>
                </div>
                <div class="google-input-group">
                    <input type="text" id="mobile_number" name="mobile_number" class="google-input" placeholder=" "
                        required value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                    <label for="mobile_number" class="google-label">Mobile number (ජංගම දුරකථන අංකය)</label>
                </div>

                <div class="google-input-group relative">
                    <input type="text" id="district_search" class="google-input" placeholder=" " autocomplete="off"
                        value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>" oninput="filterDistricts()"
                        onfocus="showDistricts()" onblur="setTimeout(hideDistricts, 200)">
                    <label for="district_search" class="google-label">District (දිස්ත්‍රික්කය)</label>
                    <input type="hidden" id="district" name="district"
                        value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>">
                    <div id="district_dropdown"
                        class="absolute z-10 w-full bg-white border border-[#dadce0] rounded-md shadow-lg max-h-40 overflow-y-auto hidden">
                    </div>
                </div>

                <div class="google-input-group">
                    <textarea id="address" name="address" class="google-input" placeholder=" " rows="1"
                        required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    <label for="address" class="google-label">Address (ලිපිනය)</label>
                </div>

                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(3)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(3)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 4: Education & Enrollment -->
            <div class="step-content" id="step4">
                <div class="google-input-group">
                    <input type="text" id="school_name" name="school_name" class="google-input" placeholder=" "
                        value="<?php echo htmlspecialchars($_POST['school_name'] ?? ''); ?>">
                    <label for="school_name" class="google-label">School Name (පාසලේ නම)</label>
                </div>

                <div class="mb-6">
                    <label
                        class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 ml-1">Enrollment
                        Type</label>
                    <div class="flex space-x-3">
                        <label
                            class="flex items-center space-x-2 cursor-pointer p-3 border rounded-md hover:bg-gray-50 flex-1 transition-colors">
                            <input type="radio" name="enrollment_type" value="subject" checked
                                onchange="toggleEnrollmentType()">
                            <span class="text-sm font-medium">Class Enrollment</span>
                        </label>
                        <label
                            class="flex items-center space-x-2 cursor-pointer p-3 border rounded-md hover:bg-gray-50 flex-1 transition-colors">
                            <input type="radio" name="enrollment_type" value="course" onchange="toggleEnrollmentType()">
                            <span class="text-sm font-medium">Online Course</span>
                        </label>
                    </div>
                </div>

                <div id="classEnrollmentContainer" class="space-y-4">
                    <div class="google-input-group">
                        <select id="stream_id" name="stream_id" class="google-input" onchange="handleStreamChange()">
                            <option value="">-- Select Stream --</option>
                            <?php foreach ($streams as $stream): ?>
                                <option value="<?php echo $stream['id']; ?>" <?php echo ($stream_id_selected == $stream['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($stream['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="stream_id" class="google-label">Select Stream (Grade) (අංශය තෝරන්න)</label>
                    </div>

                    <div id="subjectContainer" class="google-input-group hidden">
                        <select id="subject_id" name="subject_id" class="google-input" onchange="handleSubjectChange()">
                            <option value="">-- Select Subject --</option>
                        </select>
                        <label for="subject_id" class="google-label">Select Subject (විෂය තෝරන්න)</label>
                    </div>

                    <div id="teachersContainer" class="hidden">
                        <label
                            class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 ml-1">Select
                            Teacher</label>
                        <div id="teachersGrid" class="space-y-2 max-h-48 overflow-y-auto p-1 border rounded-md"></div>
                        <input type="hidden" id="selected_teacher_id" name="selected_teacher_id" value="">
                    </div>
                </div>

                <div id="courseEnrollmentContainer" class="hidden">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 ml-1">Select
                        Online Course</label>
                    <input type="hidden" id="course_id" name="course_id"
                        value="<?php echo htmlspecialchars($course_id_selected); ?>">
                    <div class="grid grid-cols-1 gap-2 max-h-60 overflow-y-auto p-1 border rounded-md">
                        <?php foreach ($courses as $course): ?>
                            <div onclick="selectCourse(<?php echo $course['id']; ?>, this)"
                                class="course-item cursor-pointer border p-4 rounded-md hover:bg-blue-50 transition-colors text-sm flex justify-between items-center group">
                                <div>
                                    <div class="font-bold text-gray-800 group-hover:text-blue-700">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">By
                                        <?php echo htmlspecialchars($course['teacher_name']); ?>
                                    </div>
                                </div>
                                <div class="text-blue-600 font-bold">Rs. <?php echo number_format($course['price'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(4)" class="btn-google-outline">Back</button>
                    <button type="button" onclick="nextStep(4)" class="btn-google px-8">Next</button>
                </div>
            </div>

            <!-- STEP 5: Verification & Finalize -->
            <div class="step-content" id="step5">
                <div class="mb-8 p-4 bg-gray-50 rounded-lg border border-gray-100">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Profile Picture (Optional)</label>
                    <div class="flex items-center space-x-6">
                        <div id="photoPreview"
                            class="w-20 h-20 rounded-full bg-white flex items-center justify-center overflow-hidden border-2 border-gray-200 shadow-sm">
                            <img id="previewImg" src="" alt="Preview" class="w-full h-full object-cover hidden">
                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                                class="text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                onchange="previewProfilePicture(this)">
                            <p class="text-[10px] text-gray-400 mt-2">Max 5MB. JPG, PNG or WebP</p>
                        </div>
                    </div>
                </div>

                <div id="verificationSection">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 ml-1">Identity
                        Verification</p>
                    <div class="grid grid-cols-1 gap-3 mb-6">
                        <label
                            class="flex items-center space-x-3 p-4 border rounded-lg cursor-pointer hover:bg-blue-50 transition-colors border-gray-200">
                            <input type="radio" name="verification_method" value="nic"
                                onchange="handleVerificationMethodChange()" class="w-4 h-4 text-blue-600">
                            <div>
                                <span class="text-sm font-bold text-gray-800">Verify by NIC</span>
                                <p class="text-[11px] text-gray-500">Quickest way using your ID card</p>
                            </div>
                        </label>
                        <label
                            class="flex items-center space-x-3 p-4 border rounded-lg cursor-pointer hover:bg-blue-50 transition-colors border-gray-200">
                            <input type="radio" name="verification_method" value="mobile"
                                onchange="handleVerificationMethodChange()" class="w-4 h-4 text-blue-600">
                            <div>
                                <span class="text-sm font-bold text-gray-800">Verify by Mobile OTP</span>
                                <p class="text-[11px] text-gray-500">Receive a code via SMS/WhatsApp</p>
                            </div>
                        </label>
                    </div>

                    <div id="nicVerificationContainer" class="hidden animate-fadeIn">
                        <div class="flex space-x-2">
                            <input type="text" id="nic_number" name="nic_number" class="google-input !mb-0"
                                placeholder="NIC Number (e.g. 199912345678) (හැඳුනුම්පත් අංකය)">
                            <button type="button" onclick="verifyNIC()" class="btn-google">Verify</button>
                        </div>
                        <input type="hidden" id="nic_verified" name="nic_verified" value="0">
                    </div>

                    <div id="mobileVerificationContainer" class="hidden space-y-4 animate-fadeIn">
                        <div class="flex space-x-2">
                            <input type="text" id="verification_mobile" class="google-input !mb-0"
                                placeholder="Mobile Number (ජංගම දුරකථන අංකය)">
                            <button type="button" onclick="sendOTP()" id="sendOtpBtn" class="btn-google">Send
                                Code</button>
                        </div>
                        <div id="otpInputContainer" class="hidden flex space-x-2">
                            <input type="text" id="otp_code" class="google-input !mb-0"
                                placeholder="6-digit Code (රහස් අංකය)">
                            <button type="button" onclick="verifyOTP()" class="btn-google !bg-green-600">Verify
                                OTP</button>
                        </div>
                        <input type="hidden" id="otp_verified" name="otp_verified" value="0">
                    </div>
                    <div id="verificationStatus" class="mt-3 text-sm text-center font-medium"></div>
                </div>

                <div class="flex justify-between items-center mt-10">
                    <button type="button" onclick="prevStep(5)" class="btn-google-outline">Back</button>
                    <button type="submit" name="add_user" id="registerButton"
                        class="btn-google px-8 !bg-blue-700">Register</button>
                </div>
            </div>
        </form>
    </div>

    <div id="toastContainer" class="fixed bottom-4 left-4 z-50 space-y-2"></div>



    <script>
        let currentStep = 1;
        const totalSteps = 5;

        function showStep(n) {
            const steps = document.getElementsByClassName("step-content");
            const dots = document.getElementsByClassName("step-dot");
            const titles = [
                "Create your Student Account <span class='sinhala-subtitle'>(ගිණුමක් සාදාගන්න)</span>",
                "Secure your Account <span class='sinhala-subtitle'>(මුරපදයක් යොදන්න)</span>",
                "Contact & Location <span class='sinhala-subtitle'>(සන්නිවේදන තොරතුරු)</span>",
                "Education & Enrollment <span class='sinhala-subtitle'>(අධ්‍යාපනික තොරතුරු)</span>",
                "Verification & Finalize <span class='sinhala-subtitle'>(තහවුරු කිරීම)</span>"
            ];
            const subtitles = [
                "Be a part of LERNERR.LK Family",
                "Keep your account safe with a strong password <span class='sinhala-inline'>(මුරපදයක් මගින් ගිණුම ආරක්ෂා කරගන්න)</span>",
                "How can we reach you? <span class='sinhala-inline'>(සන්නිවේදන තොරතුරු ලබා දෙන්න)</span>",
                "Select your class or course <span class='sinhala-inline'>(පන්තිය හෝ පාඨමාලාව තෝරන්න)</span>",
                "Almost done! Verify your identity <span class='sinhala-inline'>(අවසාන පියවර! අනන්‍යතාවය තහවුරු කරන්න)</span>"
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
                if (!input.value.trim() && input.type !== 'radio' && input.type !== 'checkbox') {
                    valid = false;
                    input.style.borderColor = "#d93025";
                } else {
                    input.style.borderColor = "";
                }
            });

            if (n === 2) {
                const pass = document.getElementById("password").value;
                const confirm = document.getElementById("confirm_password").value;
                if (pass.length < 8) {
                    showToast("Password must be at least 8 characters", "error");
                    return false;
                }
                if (pass !== confirm) {
                    showToast("Passwords do not match!", "error");
                    return false;
                }
            }

            if (n === 4) {
                const enrollmentType = document.querySelector('input[name="enrollment_type"]:checked').value;
                if (enrollmentType === 'subject') {
                    const subjectId = document.getElementById('subject_id').value;
                    const teacherId = document.getElementById('selected_teacher_id').value;
                    if (!subjectId || !teacherId) {
                        showToast("Please select a subject and a teacher", "error");
                        return false;
                    }
                } else {
                    const courseId = document.getElementById('course_id').value;
                    if (!courseId) {
                        showToast("Please select an online course", "error");
                        return false;
                    }
                }
            }

            if (!valid) showToast("Please fill all required fields", "error");
            return valid;
        }

        // Toggle role-based fields (student only now)
        function toggleRoleBasedFields() {
            try {
                const studentFields = document.getElementById('studentFields');
                const teacherFields = document.getElementById('teacherFields');
                const verificationSection = document.getElementById('verificationSection');
                const submitButtonContainer = document.getElementById('submitButtonContainer');

                // Always show student fields and verification
                if (studentFields) studentFields.classList.remove('hidden');
                if (verificationSection) verificationSection.classList.remove('hidden');
                if (submitButtonContainer) submitButtonContainer.classList.remove('hidden');

                // Always hide teacher fields
                if (teacherFields) teacherFields.classList.add('hidden');
            } catch (error) {
                console.error('Error in toggleRoleBasedFields:', error);
            }
        }

        // Education field counter
        let educationCount = 0;

        // Add education field (for teachers)
        function addEducationField() {
            educationCount++;
            const container = document.getElementById('educationContainer');
            if (!container) return;

            const div = document.createElement('div');
            div.className = 'border border-gray-300 rounded-lg p-4 education-field';
            div.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qualification *</label>
                        <input type="text" name="education[${educationCount}][qualification]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., B.Sc. in Mathematics" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Institution</label>
                        <input type="text" name="education[${educationCount}][institution]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="University/College name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year Obtained</label>
                        <input type="number" name="education[${educationCount}][year_obtained]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., 2020" min="1950" max="2100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Field of Study</label>
                        <input type="text" name="education[${educationCount}][field_of_study]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., Mathematics, Physics">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Grade/Class</label>
                        <input type="text" name="education[${educationCount}][grade_or_class]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., First Class, Distinction">
                    </div>
                    <div class="flex items-end">
                        <button type="button" onclick="removeEducationField(this)" 
                                class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Remove
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }

        // Remove education field
        function removeEducationField(button) {
            const field = button.closest('.education-field');
            if (field) field.remove();
        }

        // Load subjects for selected streams (for teachers)
        async function loadTeacherSubjects() {
            const selectedStreams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);
            const subjectContainer = document.getElementById('teacherSubjectContainer');
            const subjectsGrid = document.getElementById('teacherSubjectsGrid');

            if (!subjectContainer || !subjectsGrid) return;

            if (selectedStreams.length === 0) {
                subjectContainer.classList.add('hidden');
                subjectsGrid.innerHTML = '';
                return;
            }

            subjectContainer.classList.remove('hidden');
            subjectsGrid.innerHTML = '<div class="col-span-full text-center py-4 text-gray-500">Loading subjects...</div>';

            try {
                // Fetch subjects for all selected streams
                const subjectPromises = selectedStreams.map(async streamId => {
                    const response = await fetch(`get_subjects.php?stream_id=${streamId}`);
                    const data = await response.json();
                    return { streamId, data };
                });

                const results = await Promise.all(subjectPromises);
                const allStreamSubjects = new Map();

                // Get stream names
                const streamNames = {};
                selectedStreams.forEach(streamId => {
                    const checkbox = document.querySelector(`input[value="${streamId}"].teacher-stream-checkbox`);
                    if (checkbox) {
                        const label = checkbox.closest('label');
                        if (label) {
                            streamNames[streamId] = label.textContent.trim();
                        }
                    }
                });

                // Process each stream's subjects
                for (const { streamId, data } of results) {
                    if (data.success && data.subjects) {
                        for (const subject of data.subjects) {
                            // Get stream_subject_id
                            const ssResponse = await fetch(`get_stream_subject_id.php?stream_id=${streamId}&subject_id=${subject.id}`);
                            const ssData = await ssResponse.json();

                            if (ssData.success && ssData.stream_subject_id) {
                                const key = `${streamId}_${subject.id}`;
                                if (!allStreamSubjects.has(key)) {
                                    allStreamSubjects.set(key, {
                                        stream_subject_id: ssData.stream_subject_id,
                                        stream_id: streamId,
                                        subject_id: subject.id,
                                        subject_name: subject.name,
                                        stream_name: streamNames[streamId] || `Stream ${streamId}`
                                    });
                                }
                            }
                        }
                    }
                }

                updateSubjectGrid(allStreamSubjects);
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading subjects.');
                subjectsGrid.innerHTML = '';
            }
        }

        // Update subject grid
        function updateSubjectGrid(streamSubjectsMap) {
            const subjectsGrid = document.getElementById('teacherSubjectsGrid');
            const subjectContainer = document.getElementById('teacherSubjectContainer');

            if (!subjectsGrid || !subjectContainer) return;

            subjectsGrid.innerHTML = '';

            if (streamSubjectsMap.size > 0) {
                streamSubjectsMap.forEach((item, key) => {
                    const label = document.createElement('label');
                    label.className = 'flex items-center space-x-2 p-3 border border-gray-300 rounded-md hover:bg-red-50 cursor-pointer';
                    label.innerHTML = `
                        <input type="checkbox" name="teacher_subjects[]" value="${item.stream_subject_id}" 
                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                        <div class="flex-1">
                            <span class="text-sm font-medium text-gray-900">${item.subject_name}</span>
                            <span class="text-xs text-gray-500 block">${item.stream_name}</span>
                        </div>
                    `;
                    subjectsGrid.appendChild(label);
                });
            } else {
                subjectsGrid.innerHTML = '<div class="col-span-full text-center py-4 text-gray-500">No subjects available for selected streams.</div>';
            }
        }

        // Handle stream change (for students - no "new" option)
        function handleStreamChange() {
            const streamId = document.getElementById('stream_id').value;
            const subjectContainer = document.getElementById('subjectContainer');
            const teachersContainer = document.getElementById('teachersContainer');

            if (streamId) {
                loadSubjects();
            } else {
                subjectContainer.classList.add('hidden');
                teachersContainer.classList.add('hidden');
            }
        }

        // Load subjects based on selected stream
        function loadSubjects() {
            const streamId = document.getElementById('stream_id').value;
            const subjectContainer = document.getElementById('subjectContainer');
            const subjectSelect = document.getElementById('subject_id');
            const teachersContainer = document.getElementById('teachersContainer');

            if (!streamId) {
                subjectContainer.classList.add('hidden');
                teachersContainer.classList.add('hidden');
                return;
            }

            // Show loading state
            subjectContainer.classList.remove('hidden');
            subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';

            // Fetch subjects via AJAX
            fetch(`get_subjects.php?stream_id=${streamId}`)
                .then(response => {
                    // Check if response is ok
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Server error:', text);
                            throw new Error(`HTTP error! status: ${response.status}`);
                        });
                    }
                    // Check content type
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Invalid JSON response:', text);
                            throw new Error('Invalid JSON response from server');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';

                    if (data && data.success && data.subjects && Array.isArray(data.subjects) && data.subjects.length > 0) {
                        // Subjects found - show subject dropdown
                        data.subjects.forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject.id;
                            option.textContent = subject.name;
                            subjectSelect.appendChild(option);
                        });
                        subjectContainer.classList.remove('hidden');
                    } else {
                        // No subjects found - hide subject dropdown
                        subjectContainer.classList.add('hidden');
                    }

                    teachersContainer.classList.add('hidden');
                    document.getElementById('selected_teacher_id').value = '';
                })
                .catch(error => {
                    console.error('Error loading subjects:', error);
                    // On error, hide subject dropdown
                    subjectContainer.classList.add('hidden');
                });
        }

        // Handle subject change (for students - no "new" option)
        function handleSubjectChange() {
            const subjectId = document.getElementById('subject_id').value;
            const teachersContainer = document.getElementById('teachersContainer');

            if (subjectId) {
                loadTeachers();
            } else {
                teachersContainer.classList.add('hidden');
            }
        }

        // Load teachers based on selected subject
        function loadTeachers() {
            const streamId = document.getElementById('stream_id').value;
            const subjectId = document.getElementById('subject_id').value;
            const teachersContainer = document.getElementById('teachersContainer');
            const teachersGrid = document.getElementById('teachersGrid');

            if (!streamId || !subjectId || streamId === 'new' || subjectId === 'new') {
                teachersContainer.classList.add('hidden');
                return;
            }

            // Fetch teachers via AJAX
            fetch(`get_teachers.php?stream_id=${streamId}&subject_id=${subjectId}`)
                .then(response => response.json())
                .then(data => {
                    teachersGrid.innerHTML = '';

                    if (data.success && data.teachers.length > 0) {
                        data.teachers.forEach(teacher => {
                            const card = createTeacherCard(teacher);
                            teachersGrid.appendChild(card);
                        });
                        teachersContainer.classList.remove('hidden');
                    } else {
                        teachersContainer.classList.add('hidden');
                        alert('No teachers available for this subject.');
                    }

                    document.getElementById('selected_teacher_id').value = '';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading teachers.');
                });
        }

        // Create teacher card
        function createTeacherCard(teacher) {
            const card = document.createElement('div');
            card.className = 'bg-white border-2 border-red-500 rounded-lg p-6 hover:border-red-600 hover:shadow-xl cursor-pointer transition-all duration-200 teacher-card flex flex-col h-full';
            card.dataset.teacherId = teacher.teacher_id;

            // Large centered profile picture
            const profilePic = teacher.profile_picture
                ? `<div class="flex justify-center mb-6">
                     <img src="${teacher.profile_picture}" alt="Profile" class="w-32 h-32 rounded-full object-cover border-4 border-red-200 shadow-lg">
                   </div>`
                : `<div class="flex justify-center mb-6">
                     <div class="w-32 h-32 rounded-full bg-red-100 flex items-center justify-center border-4 border-red-200 shadow-lg">
                       <svg class="w-16 h-16 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                       </svg>
                     </div>
                   </div>`;

            // Teacher name - left aligned
            const teacherName = (teacher.first_name || '') + ' ' + (teacher.second_name || '');
            const nameHTML = `<div class="text-left mb-4">
                <h4 class="font-bold text-xl text-gray-900 mb-1">${teacherName.trim() || 'Teacher'}</h4>
            </div>`;

            // WhatsApp number with icon - left aligned
            const whatsappHTML = teacher.whatsapp_number
                ? `<div class="text-left mb-4 flex items-center">
                     <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                       <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                     </svg>
                     <span class="text-gray-700 font-medium">${teacher.whatsapp_number}</span>
                   </div>`
                : '';

            // Build education details HTML - left aligned
            let educationHTML = '';
            if (teacher.education && teacher.education.length > 0) {
                educationHTML = '<div class="text-left mb-4 flex-1">';
                educationHTML += '<h5 class="text-sm font-semibold text-gray-800 mb-3 uppercase tracking-wide flex items-center">';
                educationHTML += '<svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                educationHTML += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
                educationHTML += '</svg>Education Details</h5>';
                educationHTML += '<ul class="space-y-2">';

                teacher.education.forEach(edu => {
                    const qualification = edu.qualification || '';
                    const institution = edu.institution || '';
                    const details = [
                        edu.field_of_study,
                        edu.year_obtained ? `(${edu.year_obtained}${edu.grade_or_class ? ' - ' + edu.grade_or_class : ''})` : edu.grade_or_class
                    ].filter(Boolean).join(' - ');

                    educationHTML += `<li class="text-sm text-gray-700 flex items-start bg-red-50 p-4 rounded-xl border border-red-100 mb-3 hover:shadow-md transition-shadow">
                        <div class="bg-red-600 rounded-full p-1.5 mr-4 mt-1 flex-shrink-0 shadow-sm">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="font-bold text-gray-900 text-base mb-0.5">${qualification}</div>
                            ${institution ? `<div class="text-red-700 font-medium">${institution}</div>` : ''}
                            ${details ? `<div class="text-gray-500 text-xs mt-1 uppercase tracking-tight">${details}</div>` : ''}
                        </div>
                    </li>`;
                });

                educationHTML += '</ul></div>';
            } else {
                educationHTML = '<div class="text-left mb-4 flex-1">';
                educationHTML += '<h5 class="text-sm font-semibold text-gray-800 mb-3 uppercase tracking-wide flex items-center">';
                educationHTML += '<svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                educationHTML += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
                educationHTML += '</svg>Education Details</h5>';
                educationHTML += '<p class="text-sm text-gray-500 italic">No education details available</p>';
                educationHTML += '</div>';
            }



            card.innerHTML = `
                ${profilePic}
                ${nameHTML}
                ${whatsappHTML}
                ${educationHTML}
                <div class="mt-4 pt-4 border-t border-gray-200 text-center">
                    <span class="inline-flex items-center px-4 py-2 text-sm font-semibold bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Select Teacher
                    </span>
                </div>
            `;

            // Add click event
            card.addEventListener('click', function () {
                // Remove selection from all cards
                document.querySelectorAll('.teacher-card').forEach(c => {
                    c.classList.remove('border-red-600', 'bg-red-50', 'shadow-xl');
                    c.classList.add('border-red-500');
                });

                // Select this card
                card.classList.remove('border-red-500');
                card.classList.add('border-red-600', 'bg-red-50', 'shadow-xl');

                // Set selected teacher ID
                document.getElementById('selected_teacher_id').value = teacher.teacher_id;
            });

            return card;
        }

        // Preview profile picture
        function previewProfilePicture(input) {
            const preview = document.getElementById('photoPreview');
            const previewImg = document.getElementById('previewImg');
            const photoError = document.getElementById('photoError');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB

                // Validate file size
                if (file.size > maxSize) {
                    photoError.textContent = 'File size exceeds 5MB limit.';
                    photoError.classList.remove('hidden');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    photoError.textContent = 'Invalid file type. Please select an image file.';
                    photoError.classList.remove('hidden');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }

                photoError.classList.add('hidden');

                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('hidden');
                photoError.classList.add('hidden');
            }
        }

        // Photo is optional for students
        function updatePhotoRequirement() {
            const photoInput = document.getElementById('profile_picture');
            const photoRequired = document.getElementById('photoRequired');

            photoInput.removeAttribute('required');
            if (photoRequired) photoRequired.classList.add('hidden');
        }

        // Create New Stream Modal (for teachers)
        function openCreateStreamModal() {
            const name = prompt('Enter new stream name:');
            if (!name || !name.trim()) {
                return;
            }

            const formData = new FormData();
            formData.append('name', name.trim());

            fetch('create_stream.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to refresh stream list
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to create stream'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error creating stream.');
                });
        }

        // Create New Subject Modal (for teachers)
        function openCreateSubjectModal() {
            const selectedStreams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);

            if (selectedStreams.length === 0) {
                alert('Please select at least one stream first.');
                return;
            }

            // Use first selected stream for creation
            const streamId = selectedStreams[0];

            const name = prompt('Enter new subject name:');
            if (!name || !name.trim()) {
                return;
            }

            const code = prompt('Enter subject code (optional):') || '';

            const formData = new FormData();
            formData.append('name', name.trim());
            formData.append('code', code.trim());
            formData.append('stream_id', streamId);

            fetch('create_subject.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload subjects for all selected streams
                        loadTeacherSubjects();
                        alert('Subject created successfully!');
                    } else {
                        alert('Error: ' + (data.message || 'Failed to create subject'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error creating subject.');
                });
        }



        // Toast Notification System
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();

            const bgColor = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600';
            const icon = type === 'success' ?
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>' :
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>';

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg mb-4 flex items-center space-x-3 transform transition-all duration-300 translate-x-full opacity-0 max-w-md`;
            toast.innerHTML = `
                <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    ${icon}
                </svg>
                <p class="flex-1 text-sm font-medium">${message}</p>
                <button onclick="closeToast('${toastId}')" class="text-white hover:text-gray-200">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            `;

            toastContainer.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 10);

            // Auto remove after 5 seconds
            setTimeout(() => {
                closeToast(toastId);
            }, 5000);
        }

        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }

        // Verification Method Change Handler
        function handleVerificationMethodChange() {
            const nicMethod = document.getElementById('verify_nic');
            const mobileMethod = document.getElementById('verify_mobile');
            const nicContainer = document.getElementById('nicVerificationContainer');
            const mobileContainer = document.getElementById('mobileVerificationContainer');
            const verificationStatus = document.getElementById('verificationStatus');

            // Reset verification status
            document.getElementById('nic_verified').value = '0';
            document.getElementById('otp_verified').value = '0';
            verificationStatus.classList.add('hidden');

            if (nicMethod.checked) {
                nicContainer.classList.remove('hidden');
                mobileContainer.classList.add('hidden');
                document.getElementById('otpInputContainer').classList.add('hidden');
                document.getElementById('otp_code').value = '';
                document.getElementById('otpVerificationResult').innerHTML = '';
            } else if (mobileMethod.checked) {
                nicContainer.classList.add('hidden');
                mobileContainer.classList.remove('hidden');
                document.getElementById('nic_number').value = '';
                document.getElementById('nicVerificationResult').innerHTML = '';

                // Auto-fill mobile number from form fields (use WhatsApp number first, then mobile number)
                const whatsappNumber = document.getElementById('whatsapp_number').value.trim();
                const mobileNumber = document.getElementById('mobile_number').value.trim();
                const verificationMobile = document.getElementById('verification_mobile');

                if (whatsappNumber) {
                    verificationMobile.value = whatsappNumber;
                } else if (mobileNumber) {
                    verificationMobile.value = mobileNumber;
                } else {
                    verificationMobile.value = '';
                }

                // Keep the field editable so users can change it before sending OTP
                verificationMobile.readOnly = false;
                verificationMobile.classList.remove('bg-gray-50');
                verificationMobile.placeholder = 'Enter mobile or WhatsApp number';

            } else {
                nicContainer.classList.add('hidden');
                mobileContainer.classList.add('hidden');
            }
        }

        // Verify NIC
        function verifyNIC() {
            const nicNumber = document.getElementById('nic_number').value.trim();
            const resultDiv = document.getElementById('nicVerificationResult');
            const dobField = document.getElementById('dob');
            const genderField = document.getElementById('gender');

            if (!nicNumber) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please enter NIC number</div>';
                showToast('Please enter NIC number', 'error');
                return;
            }

            // Check if DOB and Gender are filled first
            const dob = dobField ? dobField.value.trim() : '';
            const gender = genderField ? genderField.value.trim() : '';

            if (!dob) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please enter your Date of Birth first</div>';
                showToast('Please enter your Date of Birth before verifying NIC', 'error');
                dobField.focus();
                return;
            }

            if (!gender) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please select your Gender first</div>';
                showToast('Please select your Gender before verifying NIC', 'error');
                genderField.focus();
                return;
            }

            const formData = new FormData();
            formData.append('nic', nicNumber);

            // Send DOB and Gender for verification
            formData.append('dob', dob);
            formData.append('gender', gender);

            fetch('verify_nic.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.valid) {
                        resultDiv.innerHTML = `<div class="text-green-600 text-sm">NIC verified successfully against your DOB and Gender!</div>`;
                        document.getElementById('nic_verified').value = '1';
                        updateVerificationStatus();
                        showToast('NIC verified successfully against your information!', 'success');
                    } else {
                        // Show verification failed message
                        resultDiv.innerHTML = '<div class="text-red-600 text-sm">NIC verification failed. The NIC number does not match with your Date of Birth and Gender. Please check and try again.</div>';
                        document.getElementById('nic_verified').value = '0';
                        updateVerificationStatus();
                        showToast('NIC verification failed. Please check your information and try again.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultDiv.innerHTML = '<div class="text-red-600 text-sm">Error verifying NIC. Please try again.</div>';
                    document.getElementById('nic_verified').value = '0';
                    showToast('Error verifying NIC. Please try again.', 'error');
                });
        }

        // Send OTP
        function sendOTP() {
            const mobileNumber = document.getElementById('verification_mobile').value.trim();
            const sendOtpBtn = document.getElementById('sendOtpBtn');

            if (!mobileNumber) {
                showToast('Please enter mobile number', 'error');
                return;
            }

            sendOtpBtn.disabled = true;
            sendOtpBtn.textContent = 'Sending...';

            const formData = new FormData();
            formData.append('mobile_number', mobileNumber);

            fetch('send_otp.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('otpInputContainer').classList.remove('hidden');
                        // In production, remove the OTP display - it's only for testing
                        showToast('OTP sent successfully! Check your mobile. OTP: ' + data.otp, 'success');
                    } else {
                        showToast('Error: ' + (data.message || 'Failed to send OTP'), 'error');
                    }
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.textContent = 'Send OTP';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error sending OTP. Please try again.', 'error');
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.textContent = 'Send OTP';
                });
        }

        // Verify OTP
        function verifyOTP() {
            const otpCode = document.getElementById('otp_code').value.trim();
            const resultDiv = document.getElementById('otpVerificationResult');

            if (!otpCode || otpCode.length !== 6) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please enter 6-digit OTP code</div>';
                return;
            }

            const formData = new FormData();
            formData.append('otp_code', otpCode);

            fetch('verify_otp.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.verified) {
                        resultDiv.innerHTML = `<div class="text-green-600 text-sm">OTP verified successfully!</div>`;
                        document.getElementById('otp_verified').value = '1';
                        updateVerificationStatus();
                        showToast('Mobile number verified successfully!', 'success');
                    } else {
                        resultDiv.innerHTML = `<div class="text-red-600 text-sm">${data.message || 'Invalid OTP code'}</div>`;
                        document.getElementById('otp_verified').value = '0';
                        updateVerificationStatus();
                        showToast(data.message || 'Invalid OTP code', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultDiv.innerHTML = '<div class="text-red-600 text-sm">Error verifying OTP. Please try again.</div>';
                    document.getElementById('otp_verified').value = '0';
                    showToast('Error verifying OTP. Please try again.', 'error');
                });
        }

        // Update Verification Status
        function updateVerificationStatus() {
            const verificationStatus = document.getElementById('verificationStatus');
            const nicVerified = document.getElementById('nic_verified').value === '1';
            const otpVerified = document.getElementById('otp_verified').value === '1';

            if (nicVerified || otpVerified) {
                verificationStatus.innerHTML = `<div class="bg-green-50 border-l-4 border-green-400 text-green-700 p-4 rounded">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium">Verification successful! You can now submit the form.</p>
                    </div>
                </div>`;
                verificationStatus.classList.remove('hidden');
            } else {
                verificationStatus.classList.add('hidden');
            }
        }

        // Form Submission Validation (students only)
        document.getElementById('addUserForm')?.addEventListener('submit', function (e) {
            const verificationMethod = document.querySelector('input[name="verification_method"]:checked');
            const nicVerified = document.getElementById('nic_verified').value === '1';
            const otpVerified = document.getElementById('otp_verified').value === '1';

            // Students need verification
            if (!verificationMethod) {
                e.preventDefault();
                showToast('Please select a verification method and complete the verification.', 'error');
                return false;
            }

            if (verificationMethod.value === 'nic' && !nicVerified) {
                e.preventDefault();
                showToast('Please verify your NIC number before submitting.', 'error');
                return false;
            }

            if (verificationMethod.value === 'mobile' && !otpVerified) {
                e.preventDefault();
                showToast('Please verify your mobile number with OTP before submitting.', 'error');
                return false;
            }

        });



        // District Handling
        const districts = <?php echo json_encode($districts); ?>;
        const districtSearch = document.getElementById('district_search');
        const districtInput = document.getElementById('district');
        const districtDropdown = document.getElementById('district_dropdown');

        function populateDistricts(filter = '') {
            if (!districtDropdown) return;
            districtDropdown.innerHTML = '';

            const filtered = districts.filter(d => d.toLowerCase().includes(filter.toLowerCase()));

            if (filtered.length === 0) {
                const div = document.createElement('div');
                div.className = 'px-4 py-2 text-gray-500 text-sm';
                div.textContent = 'No results found';
                districtDropdown.appendChild(div);
                return;
            }

            filtered.forEach(d => {
                const div = document.createElement('div');
                div.className = 'px-4 py-2 hover:bg-red-50 cursor-pointer text-gray-700';
                div.textContent = d;
                div.onclick = function () {
                    selectDistrict(d);
                };
                districtDropdown.appendChild(div);
            });
        }

        function filterDistricts() {
            populateDistricts(districtSearch.value);
            districtDropdown.classList.remove('hidden');
        }

        function showDistricts() {
            populateDistricts(districtSearch.value);
            districtDropdown.classList.remove('hidden');
        }

        function hideDistricts() {
            // Small delay to allow click event to register
            if (districtDropdown) {
                districtDropdown.classList.add('hidden');
            }
        }

        function selectDistrict(value) {
            if (districtSearch) districtSearch.value = value;
            if (districtInput) districtInput.value = value;
            hideDistricts();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            showStep(currentStep);
            toggleRoleBasedFields();
            updatePhotoRequirement();

            // Handle URL parameters for pre-selection
            const urlParams = new URLSearchParams(window.location.search);
            const courseId = urlParams.get('course_id');
            const streamId = urlParams.get('stream_id');
            const subjectId = urlParams.get('subject_id');

            if (courseId && courseId > 0) {
                // Pre-select course enrollment
                const courseRadio = document.querySelector('input[name="enrollment_type"][value="course"]');
                if (courseRadio) {
                    courseRadio.checked = true;
                    toggleEnrollmentType();

                    // Wait for DOM update, then select the course
                    setTimeout(() => {
                        const courseCard = document.querySelector(`.course-card[onclick*="${courseId}"]`);
                        if (courseCard) {
                            courseCard.click();
                        } else {
                            // Set hidden input directly if card click doesn't work
                            const hiddenInput = document.getElementById('course_id');
                            if (hiddenInput) hiddenInput.value = courseId;
                        }
                    }, 100);
                }
            } else if (streamId && streamId > 0) {
                // Pre-select subject enrollment
                const subjectRadio = document.querySelector('input[name="enrollment_type"][value="subject"]');
                if (subjectRadio) {
                    subjectRadio.checked = true;
                    toggleEnrollmentType();

                    // Select stream
                    setTimeout(() => {
                        const streamSelect = document.getElementById('stream_id');
                        if (streamSelect) {
                            streamSelect.value = streamId;
                            handleStreamChange();

                            // If subject_id is also provided, select it after subjects load
                            if (subjectId && subjectId > 0) {
                                setTimeout(() => {
                                    const subjectSelect = document.getElementById('subject_id');
                                    if (subjectSelect) {
                                        subjectSelect.value = subjectId;
                                        handleSubjectChange();
                                    }
                                }, 500);
                            }
                        }
                    }, 100);
                }
            } else {
                // Default toggle
                toggleEnrollmentType();
            }
        });

        function toggleEnrollmentType() {
            const enrollmentType = document.querySelector('input[name="enrollment_type"]:checked').value;
            const classContainer = document.getElementById('classEnrollmentContainer');
            const courseContainer = document.getElementById('courseEnrollmentContainer');
            // Element IDs inside class container that we need to target specifically if they are moved
            const subjectContainer = document.getElementById('subjectContainer');
            const teachersContainer = document.getElementById('teachersContainer');

            if (enrollmentType === 'subject') {
                classContainer.classList.remove('hidden');
                courseContainer.classList.add('hidden');
            } else {
                classContainer.classList.add('hidden');
                courseContainer.classList.remove('hidden');
            }
        }

    </script>
</body>

</html>