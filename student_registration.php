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
        $enrollment_type = $_POST['enrollment_type'] ?? '';

        if (empty($enrollment_type)) {
            $error_message = 'Please select an enrollment path (Class Enrollment or Online Course) / කරුණාකර ඇතුළත් වීමේ ක්‍රමයක් තෝරන්න.';
        } elseif ($enrollment_type === 'subject') {
            $stream_id_input = $_POST['stream_id'] ?? '';
            $subject_id_input = $_POST['subject_id'] ?? '';
            $selected_teacher_id = trim($_POST['selected_teacher_id'] ?? '');

            if (intval($stream_id_input) <= 0) {
                $error_message = 'Please select a stream / කරුණාකර අංශයක් තෝරාගන්න.';
            } elseif (intval($subject_id_input) <= 0) {
                $error_message = 'Please select a subject / කරුණාකර විෂයක් තෝරාගන්න.';
            } elseif (empty($selected_teacher_id)) {
                $error_message = 'Please select a teacher / කරුණාකර ගුරුවරයෙකු තෝරාගන්න.';
            }
        } elseif ($enrollment_type === 'course') {
            $course_id_input = $_POST['course_id'] ?? '';
            if (intval($course_id_input) <= 0) {
                $error_message = 'Please select a course / කරුණාකර බාහිර පාඨමාලාවක් තෝරාගන්න.';
            }
        }

        if (empty($error_message)) {
            try {
                // Generate user_id based on role
                $role_prefix = [
                    'student' => 'stu',
                    'teacher' => 'tea',
                    'instructor' => 'ins',
                    'admin' => 'adm'
                ];
                $prefix = $role_prefix[$role] ?? 'usr';

                // Get next number for this role using numeric casting for correct sorting
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY CAST(SUBSTRING(user_id, 5) AS UNSIGNED) DESC LIMIT 1");
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
                        if ($role === 'student') {
                            $enrollment_type = $_POST['enrollment_type'] ?? '';
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
                                            $stream_subject_id = $create_ss->insert_id;
                                        } else {
                                            $error_message = 'Error creating stream-subject combination: ' . $conn->error;
                                        }
                                        $create_ss->close();
                                    }
                                    $check_ss->close();
                                }

                                if (empty($error_message) && $stream_subject_id) {
                                    // Insert student enrollment
                                    $enroll_stmt = $conn->prepare("INSERT INTO student_enrollment (student_id, stream_subject_id, academic_year, enrolled_date, status, payment_status) VALUES (?, ?, ?, CURDATE(), 'active', 'pending')");
                                    $enroll_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
                                    if ($enroll_stmt->execute()) {
                                        // Enrollment Success - Send WhatsApp
                                        // Teacher-student link is already derivable via student_enrollment + teacher_assignments

                                        if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                                            try {
                                                // Get subject name
                                                $subj_stmt = $conn->prepare("SELECT name FROM subjects WHERE id = ?");
                                                $subj_stmt->bind_param("i", $subject_id);
                                                $subj_stmt->execute();
                                                $subj_res = $subj_stmt->get_result();
                                                $subj_name = ($subj_res->num_rows > 0) ? $subj_res->fetch_assoc()['name'] : 'Selected Subject';
                                                $subj_stmt->close();

                                                $enroll_msg = "🎓 *Enrollment Successful* \n\n" .
                                                    "You have successfully enrolled in the subject: *{$subj_name}*\n\n" .
                                                    "--------------------------\n\n" .
                                                    "විෂය ලියාපදිංචිය සාර්ථකයි! 👋\n" .
                                                    "ඔබ සාර්ථකව *{$subj_name}* විෂය සඳහා ලියාපදිංචි වී ඇත.";

                                                sendWhatsAppMessage($whatsapp_number, $enroll_msg);
                                            } catch (Exception $e) {
                                                error_log("WhatsApp enrollment message failed: " . $e->getMessage());
                                            }
                                        }
                                    } else {
                                        $error_message = 'User created but failed to enroll student: ' . $enroll_stmt->error;
                                    }
                                    $enroll_stmt->close();
                                }
                            } elseif ($enrollment_type === 'course') {
                                $course_id_input = $_POST['course_id'] ?? '';
                                $course_id = intval($course_id_input);

                                if (empty($error_message) && $course_id > 0) {
                                    $enroll_stmt = $conn->prepare("INSERT INTO course_enrollments (course_id, student_id, enrolled_at, status, payment_status) VALUES (?, ?, NOW(), 'active', 'pending')");
                                    $enroll_stmt->bind_param("is", $course_id, $user_id);
                                    if ($enroll_stmt->execute()) {
                                        // Enrollment Success - Send WhatsApp
                                        if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                                            try {
                                                // Get course name
                                                $crs_stmt = $conn->prepare("SELECT title FROM courses WHERE id = ?");
                                                $crs_stmt->bind_param("i", $course_id);
                                                $crs_stmt->execute();
                                                $crs_res = $crs_stmt->get_result();
                                                $crs_title = ($crs_res->num_rows > 0) ? $crs_res->fetch_assoc()['title'] : 'Selected Course';
                                                $crs_stmt->close();

                                                $enroll_msg = "🎓 *Course Enrollment Successful*\n\n" .
                                                    "You have successfully enrolled in the course: *{$crs_title}*\n\n" .
                                                    "--------------------------\n\n" .
                                                    "පාඨමාලා ලියාපදිංචිය සාර්ථකයි! 👋\n" .
                                                    "ඔබ සාර්ථකව *{$crs_title}* පාඨමාලාව සඳහා ලියාපදිංචි වී ඇත.";

                                                sendWhatsAppMessage($whatsapp_number, $enroll_msg);
                                            } catch (Exception $e) {
                                                error_log("WhatsApp course enrollment message failed: " . $e->getMessage());
                                            }
                                        }
                                    } else {
                                        $error_message = 'User created but failed to enroll in course: ' . $enroll_stmt->error;
                                    }
                                    $enroll_stmt->close();
                                }
                            }
                        }

                        if (empty($error_message)) {
                            // Send welcome message via WhatsApp
                            if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                                try {
                                    $welcome_msg = "🎓 *Welcome to Lernerr.LK!* 🎓\n\n" .
                                        "Hello {$first_name}, your account has been successfully created.\n" .
                                        "🆔 *User ID:* {$user_id}\n\n" .
                                        "--------------------------\n\n" .
                                        "Lernerr.LK වෙත ඔබව සාදරයෙන් පිළිගනිමු! 👋\n" .
                                        "ඔබේ ලියාපදිංචිය සාර්ථකයි.\n" .
                                        "🆔 *පරිශීලක හැඳුනුම්පත:* {$user_id}\n\n" .
                                        "දැන් ඔබට පන්ති සමඟ සම්බන්ධ විය හැක. ස්තුතියි!";

                                    sendWhatsAppMessage($whatsapp_number, $welcome_msg);
                                } catch (Exception $e) {
                                    error_log("WhatsApp welcome message failed: " . $e->getMessage());
                                }
                            }

                            $ui_welcome_msg = "Welcome to Lernerr.LK! 🎓\n\nHello $first_name, your account has been successfully created.\nYour User ID is: $user_id.\n\nLernerr.LK වෙත ඔබව සාදරයෙන් පිළිගනිමු! 👋\nඔබේ ලියාපදිංචිය සාර්ථකයි.\nපරිශීලක හැඳුනුම්පත: $user_id";

                            if ($approved == 1) {
                                // Show welcome screen then redirect to index
                                $registration_success = true;
                                $welcome_first_name = $first_name;
                                $welcome_user_id = $user_id;
                                $welcome_redirect = 'index.php';
                            } else {
                                $registration_success = true;
                                $welcome_first_name = $first_name;
                                $welcome_user_id = $user_id;
                                $welcome_redirect = 'login.php';
                                $welcome_pending = true;
                            }
                            // Clear form data
                            $_POST = array();

                        }
                    } else {
                        if ($conn->errno == 1062) {
                            $error_message = 'User ID or verification info already exists / මෙම පරිශීලක හැඳුනුම්පත හෝ තොරතුරු දැනටමත් පවතී.';
                        } else {
                            $error_message = 'Error creating user: ' . $conn->error;
                        }
                    }
                    $stmt->close();
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062 || $conn->errno == 1062) {
                    $error_message = 'User ID or verification info already exists / මෙම පරිශීලක හැඳුනුම්පත හෝ තොරතුරු දැනටමත් පවතී.';
                } else {
                    $error_message = 'Database error: ' . $e->getMessage();
                }
            } catch (Exception $e) {
                $error_message = 'Error: ' . $e->getMessage();
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
    <title>Registration - Lernerr.LK</title>
    <meta name="description" content="Register as a student on Lernerr.LK, the best online Learning Management System in Sri Lanka. Build your academic future with our expert classes.">
    <meta name="keywords" content="Lernerr.LK student registration, online LMS registration, register Lernerr.LK, student registration Sri Lanka">
    <meta name="author" content="Lernerr.LK">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Student Registration - Lernerr.LK">
    <meta property="og:description" content="Register as a student on Lernerr.LK, the best online Learning Management System in Sri Lanka.">
    <meta property="og:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">
    <meta property="og:site_name" content="Lernerr.LK">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="Student Registration - Lernerr.LK">
    <meta property="twitter:description" content="Register as a student on Lernerr.LK, the best online Learning Management System in Sri Lanka.">
    <meta property="twitter:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assests/favicon-16x16.png">
    <link rel="manifest" href="assests/site.webmanifest">
    <link rel="shortcut icon" href="assests/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { box-sizing: border-box; }

        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
                top: 22.8%;
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
                transform: translateY(-50%);
                width: 88%;
                height: 62%;
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

        /* ── Step panels ── */
        .step-content {
            display: none;
            height: auto;
            overflow: visible;
            padding-right: 4px;
        }
        .step-content.active {
            display: flex;
            flex-direction: column;
            animation: fadeIn 0.35s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Google-style inputs */
        .google-input-group { position: relative; margin-bottom: 20px; }
        .google-input {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color .2s, box-shadow .2s;
            background: white;
            font-family: inherit;
        }
        .google-input:focus {
            border-color: #1a73e8;
            outline: none;
            box-shadow: 0 0 0 1px #1a73e8;
        }
        .google-label {
            position: absolute;
            left: 12px; top: 13px;
            padding: 0 4px;
            background: white;
            color: #5f6368;
            font-size: 16px;
            pointer-events: none;
            transition: all .2s;
        }
        .google-input:focus + .google-label,
        .google-input:not(:placeholder-shown) + .google-label {
            top: -10px; left: 10px;
            font-size: 12px; color: #1a73e8; font-weight: 500;
        }
        .google-input:not(:focus) + .google-label { color: #5f6368; }

        select.google-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        /* Buttons */
        .btn-google {
            background-color: #1a73e8;
            color: white;
            padding: 10px 24px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
            transition: background-color .2s, box-shadow .2s;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-google:hover { background-color: #1765cc; box-shadow: 0 1px 3px rgba(66,133,244,.3); }
        .btn-google-outline {
            background-color: transparent;
            color: #1a73e8;
            padding: 10px 24px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
            transition: background-color .2s;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-google-outline:hover { background-color: #f6f9fe; }

        /* Progress dots */
        .progress-stepper { display: flex; gap: 6px; flex-wrap: wrap; }
        .step-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background-color: #e8eaed;
            transition: background-color .3s, width .3s;
        }
        .step-dot.active { background-color: #1a73e8; width: 20px; border-radius: 4px; }

        /* Search bar */
        .search-bar {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 14px;
            background: white url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%235f6368' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3ccircle cx='11' cy='11' r='8'/%3e%3cline x1='21' y1='21' x2='16.65' y2='16.65'/%3e%3c/svg%3e") no-repeat 12px center;
            background-size: 16px;
            outline: none;
            font-family: inherit;
            transition: border-color .2s;
        }
        .search-bar:focus { border-color: #1a73e8; box-shadow: 0 0 0 1px #1a73e8; }

        /* Teacher cards - 2 column grid */
        .teacher-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-auto-rows: min-content;
            align-content: start;
            gap: 10px;
        }
        @media (max-width: 480px) { .teacher-grid { grid-template-columns: 1fr; } }

        .teacher-card {
            background: white;
            border: 2px solid #e8eaed;
            border-radius: 14px;
            padding: 14px;
            cursor: pointer;
            transition: all .25s;
            position: relative;
            overflow: hidden;
        }
        .teacher-card:hover { border-color: #bfdbfe; box-shadow: 0 4px 12px rgba(26,115,232,.1); }
        .teacher-card.selected { border-color: #1a73e8; background: #f0f6ff; box-shadow: 0 4px 16px rgba(26,115,232,.15); }
        .teacher-card .tc-pic {
            width: 52px; height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f1f1f1;
            flex-shrink: 0;
        }
        .teacher-card .tc-name { font-weight: 700; font-size: 13px; color: #1e293b; line-height: 1.3; }
        .teacher-card .tc-fee-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: #f0f6ff;
            color: #1a73e8;
            font-size: 10px;
            font-weight: 700;
            border-radius: 20px;
            padding: 2px 8px;
            white-space: nowrap;
        }
        .teacher-card .tc-degree { font-size: 10px; color: #64748b; margin-top: 3px; line-height: 1.4; }
        .teacher-card .tc-uni { font-size: 10px; color: #94a3b8; margin-top: 1px; }
        .teacher-card .tc-selected-badge {
            display: none;
            position: absolute;
            top: 8px; right: 8px;
            background: #1a73e8;
            color: white;
            border-radius: 50%;
            width: 20px; height: 20px;
            align-items: center; justify-content: center;
            font-size: 10px;
        }
        .teacher-card.selected .tc-selected-badge { display: flex; }
 
        /* Enrollment type cards */
        .enroll-card {
            border: 2px solid #e8eaed;
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: all .25s;
            background: white;
            text-align: center;
        }
        .enroll-card:hover { border-color: #bfdbfe; }
        .enroll-card.selected { border-color: #1a73e8; background: #f0f6ff; }
 
        /* Course item */
        .course-item {
            cursor: pointer;
            background: white;
            border: 2px solid #e8eaed;
            border-radius: 14px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all .25s;
        }
        .course-item:hover { border-color: #bfdbfe; }
        .course-item.selected { border-color: #1a73e8; background: #f0f6ff; }

        /* Section label */
        .section-label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        /* Sinhala helpers */
        .sinhala-subtitle { display: block; font-size: 13px; color: #202124; font-weight: 400; margin-top: 2px; }
        .sinhala-inline   { font-size: 11px; color: #5f6368; font-weight: 400; margin-left: 2px; }

        /* Step nav row */
        .step-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 16px; flex-shrink: 0; }

        /* Verification cards */
        .verify-card {
            border: 2px solid #e8eaed;
            border-radius: 14px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all .25s;
            background: white;
            margin-bottom: 10px;
        }
        .verify-card.selected { border-color: #1a73e8; background: #f0f6ff; }

        /* Welcome screen */
        .welcome-screen {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 20px;
        }

        @media (max-width: 640px) {
            .step-content.active {
                animation: fadeInMobile 0.25s ease-out;
            }
            @keyframes fadeInMobile {
                from { opacity: 0; }
                to   { opacity: 1; }
            }
            .step-content { height: auto; min-height: 320px; padding-bottom: 60px !important; }
            .teacher-grid { grid-template-columns: 1fr; }
            .step-nav {
                position: absolute;
                bottom: 12px;
                left: 16px;
                right: 16px;
                background: #ffffff;
                padding-top: 8px;
                z-index: 30;
                margin-top: 0;
            }
        }

        /* Peeking kids overlay (disabled, part of background) */
        .peeking-kids-overlay { display: none !important; }
        .blob { display: none !important; }
    </style>
</head>

<body>

<?php if (!empty($registration_success)): ?>
<!-- ============ WELCOME OVERLAY ============ -->
<div id="welcomeOverlay" style="
    position: fixed; inset: 0; z-index: 9999;
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Inter', -apple-system, sans-serif;
    overflow: hidden;
">
    <!-- Animated background orbs -->
    <div style="position:absolute;inset:0;overflow:hidden;pointer-events:none;">
        <div style="position:absolute;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(239,68,68,0.15) 0%,transparent 70%);top:-100px;left:-100px;animation:orbFloat 8s ease-in-out infinite;"></div>
        <div style="position:absolute;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(99,102,241,0.15) 0%,transparent 70%);bottom:-100px;right:-100px;animation:orbFloat 10s ease-in-out infinite reverse;"></div>
        <div style="position:absolute;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(251,191,36,0.1) 0%,transparent 70%);top:50%;left:60%;animation:orbFloat 6s ease-in-out infinite;"></div>
    </div>

    <!-- Stars / particles -->
    <div id="particles" style="position:absolute;inset:0;pointer-events:none;"></div>

    <!-- Content Card -->
    <div style="
        position:relative; text-align:center; padding: 56px 48px;
        background: rgba(255,255,255,0.04);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 28px;
        max-width: 480px; width: 90%;
        box-shadow: 0 40px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05);
        animation: cardPop .6s cubic-bezier(.34,1.56,.64,1) both;
    ">
        <!-- Confetti emoji burst -->
        <div style="font-size:60px;margin-bottom:8px;animation:bounce 1s ease infinite alternate;">🎉</div>

        <!-- Title -->
        <h1 style="font-size:28px;font-weight:800;color:#fff;margin-bottom:6px;letter-spacing:-0.5px;">
            Welcome, <?php echo htmlspecialchars($welcome_first_name); ?>! 👋
        </h1>
        <p style="font-size:15px;color:rgba(255,255,255,0.6);margin-bottom:32px;line-height:1.6;">
            ඔබේ ලියාපදිංචිය සාර්ථකයි! Your account has been created.<br>
            <?php if (!empty($welcome_pending)): ?>
                <span style="color:#fbbf24;">⏳ Pending admin approval before you can login.</span>
            <?php else: ?>
                <span style="color:#4ade80;">✅ Your account is active and ready to use.</span>
            <?php endif; ?>
        </p>

        <!-- User ID Badge -->
        <div style="
            display:inline-flex; align-items:center; gap:10px;
            background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3);
            border-radius: 50px; padding: 10px 24px; margin-bottom: 36px;
        ">
            <span style="font-size:13px;color:rgba(255,255,255,0.5);font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Your Student ID</span>
            <span style="font-size:18px;font-weight:800;color:#f87171;letter-spacing:.05em;"><?php echo htmlspecialchars($welcome_user_id); ?></span>
        </div>

        <!-- Countdown bar -->
        <div style="margin-bottom:20px;">
            <p style="font-size:12px;color:rgba(255,255,255,0.35);margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;">
                Redirecting in <span id="countdownNum">5</span>s…
            </p>
            <div style="height:3px;background:rgba(255,255,255,0.08);border-radius:100px;overflow:hidden;">
                <div id="countdownBar" style="height:100%;background:linear-gradient(90deg,#ef4444,#f97316);border-radius:100px;width:100%;transition:width 1s linear;"></div>
            </div>
        </div>

        <!-- Button -->
        <a href="<?php echo htmlspecialchars($welcome_redirect); ?>" style="
            display:inline-block;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white; text-decoration: none;
            padding: 14px 40px; border-radius: 50px;
            font-size: 15px; font-weight: 700;
            box-shadow: 0 8px 24px rgba(239,68,68,0.4);
            transition: transform .2s, box-shadow .2s;
            letter-spacing: .02em;
        " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 12px 32px rgba(239,68,68,0.5)'"
           onmouseout="this.style.transform='';this.style.boxShadow='0 8px 24px rgba(239,68,68,0.4)'">
            <?php echo empty($welcome_pending) ? '🏠 Go to Home' : '🔐 Go to Login'; ?>
        </a>
    </div>
</div>

<style>
@keyframes cardPop { from { opacity:0; transform: scale(.8) translateY(30px); } to { opacity:1; transform: scale(1) translateY(0); } }
@keyframes orbFloat { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(40px,40px) scale(1.1); } }
@keyframes bounce { from { transform: scale(1) translateY(0); } to { transform: scale(1.1) translateY(-8px); } }
@keyframes starFade { 0% { opacity:0; transform: scale(0); } 20% { opacity:1; transform: scale(1); } 80% { opacity:1; } 100% { opacity:0; transform: translateY(-60px); } }
</style>

<script>
// Countdown & redirect
(function(){
    var secs = 5;
    var target = '<?php echo htmlspecialchars($welcome_redirect); ?>';
    var bar = document.getElementById('countdownBar');
    var num = document.getElementById('countdownNum');
    var iv = setInterval(function(){
        secs--;
        num.textContent = secs;
        bar.style.width = (secs / 5 * 100) + '%';
        if (secs <= 0) { clearInterval(iv); window.location.href = target; }
    }, 1000);
})();

// Floating stars/particles
(function(){
    var container = document.getElementById('particles');
    for (var i = 0; i < 30; i++) {
        (function(i){
            setTimeout(function(){
                var star = document.createElement('div');
                star.innerHTML = ['⭐','✨','🌟','💫'][Math.floor(Math.random()*4)];
                star.style.cssText = 'position:absolute;font-size:'+(10+Math.random()*18)+'px;left:'+(Math.random()*100)+'%;top:'+(40+Math.random()*50)+'%;animation:starFade '+(2+Math.random()*3)+'s ease forwards;pointer-events:none;';
                container.appendChild(star);
                setTimeout(function(){ star.remove(); }, 5000);
            }, i * 180);
        })(i);
    }
})();
</script>
<!-- ============ END WELCOME OVERLAY ============ -->
<?php endif; ?>


    <div class="bg-design">
        <picture>
            <source media="(max-width: 640px)" srcset="https://res.cloudinary.com/dnfbik3if/image/upload/v1784129659/Untitled_design_18_woorfv.jpg">
            <img src="https://res.cloudinary.com/dnfbik3if/image/upload/v1784122268/Untitled_design_12_owzcby.jpg" class="bg-img" alt="Background">
        </picture>

        <div class="registration-container grid grid-cols-1 md:grid-cols-12 gap-2 md:gap-12 md:items-center">

            <!-- Left Side: Logo + Step Info -->
            <div class="md:col-span-5 flex flex-col items-center justify-center text-center md:min-h-[380px] space-y-4 pr-0 md:pr-8 relative z-10">
            <div class="flex flex-col items-center w-full">
                <!-- Logo -->
                <div class="mb-1 md:mb-2">
                    <img src="assests/logo.jpeg" alt="Lernerr.LK Logo" class="h-10 md:h-16 w-auto object-contain rounded-lg shadow-sm">
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="mt-4">
                        <h1 class="text-xl font-bold text-emerald-600">Registration Successful!</h1>
                        <p class="text-sm font-semibold text-gray-600 mt-1">ලියාපදිංචිය සාර්ථකයි</p>
                    </div>
                <?php else: ?>
                    <div class="mt-2 hidden md:flex flex-col items-center">
                        <h1 id="stepTitle" class="text-xl font-bold text-[#ea4335] leading-snug">Create your Account</h1>
                        <span class="sinhala-subtitle font-semibold text-[#ea4335] text-sm mt-0.5" id="stepTitleSinhala">(ගිණුමක් සාදාගන්න)</span>
                        <p id="stepSubtitle" class="text-xs text-slate-600 mt-4 max-w-[280px] leading-relaxed">Be a part of the Lernerr.LK family</p>
                        <span class="sinhala-inline text-slate-500 mt-0.5 text-[11px] max-w-[280px] leading-relaxed" id="stepSubtitleSinhala"></span>

                        <!-- Step counter -->
                        <div class="mt-6 flex items-center gap-2">
                            <span class="text-xs text-slate-400 font-medium" id="stepCounter">Step 1 of 9</span>
                        </div>
                        <!-- Progress dots -->
                        <div class="progress-stepper mt-3" id="progressStepper">
                            <div class="step-dot active"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                            <div class="step-dot"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="md:col-span-7 w-full flex flex-col md:justify-center md:relative z-10 md:pt-20">

            <?php if (!empty($error_message)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        showToast(<?php echo json_encode($error_message); ?>, 'error');
                    });
                </script>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <!-- Full success message view -->
                <div class="bg-green-50 border border-green-200 text-green-700 p-6 rounded-2xl text-sm text-center flex flex-col items-center">
                    <svg class="w-14 h-14 text-emerald-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="font-bold text-lg block mb-2"><?php echo nl2br(htmlspecialchars($success_message)); ?></span>
                    <p class="text-gray-500 mb-6 text-sm">Your student account has been created. You can now login to access your courses.</p>
                    <a href="login.php" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98]">
                        Continue to Login
                    </a>
                </div>
            <?php endif; ?>

            <?php if (empty($success_message)): ?>
            <form method="POST" action="" id="addUserForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" id="role" name="role" value="student">

                <!-- ═══════════════════════════════════════════
                     STEP 1: Name, DOB, Gender
                ═══════════════════════════════════════════ -->
                <div class="step-content active" id="step1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                        <div class="google-input-group">
                            <input type="text" id="first_name" name="first_name" class="google-input" placeholder=" " required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            <label for="first_name" class="google-label">First name (මුල් නම)</label>
                        </div>
                        <div class="google-input-group">
                            <input type="text" id="second_name" name="second_name" class="google-input" placeholder=" " required value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
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
                            <option value="male"   <?php echo (($_POST['gender'] ?? '') === 'male')   ? 'selected' : ''; ?>>Male (පුරුෂ)</option>
                            <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female (ස්ත්‍රී)</option>
                        </select>
                        <label for="gender" class="google-label">Gender (ස්ත්‍රී/පුරුෂ භාවය)</label>
                    </div>
                    <div class="step-nav">
                        <a href="login.php" class="text-blue-600 font-medium text-sm hover:underline">Sign in instead</a>
                        <button type="button" onclick="nextStep(1)" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 2: Password
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step2">
                    <div class="google-input-group">
                        <input type="password" id="password" name="password" class="google-input" placeholder=" " required>
                        <label for="password" class="google-label">Password (මුරපදය)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="password" id="confirm_password" name="confirm_password" class="google-input" placeholder=" " required>
                        <label for="confirm_password" class="google-label">Confirm Password (තහවුරු කරන්න)</label>
                    </div>
                    <p class="text-xs text-gray-500 px-1 -mt-2">Use 8 or more characters with a mix of letters, numbers &amp; symbols</p>
                    <div class="step-nav">
                        <button type="button" onclick="prevStep(2)" class="btn-google-outline">Back</button>
                        <button type="button" onclick="nextStep(2)" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 3: WhatsApp + Mobile Number ONLY
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step3">
                    <div class="mb-4">
                        <div class="hidden md:flex items-center gap-3 p-3 bg-green-50 border border-green-100 rounded-xl mb-4">
                            <svg class="w-8 h-8 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 2.004C6.476 2.004 2.004 6.476 2.004 12c0 1.776.464 3.443 1.274 4.893L2 22l5.234-1.273A9.943 9.943 0 0012 21.996c5.523 0 9.996-4.472 9.996-9.996 0-5.523-4.473-9.996-9.997-9.996z"/></svg>
                            <div>
                                <p class="text-sm font-bold text-green-800">Contact Numbers</p>
                                <p class="text-xs text-green-600">We'll use these to keep in touch with you / අපි ඔබව සම්බන්ධ කර ගැනීමට මෙය භාවිතා කරමු</p>
                            </div>
                        </div>
                    </div>
                    <div class="google-input-group">
                        <input type="tel" id="whatsapp_number" name="whatsapp_number" class="google-input" placeholder=" " required
                            pattern="[0-9+\-\s]{7,15}"
                            value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                        <label for="whatsapp_number" class="google-label">WhatsApp number (වට්ස්ඇප් අංකය)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="tel" id="mobile_number" name="mobile_number" class="google-input" placeholder=" " required
                            pattern="[0-9+\-\s]{7,15}"
                            value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                        <label for="mobile_number" class="google-label">Mobile number (ජංගම දුරකථන අංකය)</label>
                    </div>
                    <div class="step-nav">
                        <button type="button" onclick="prevStep(3)" class="btn-google-outline">Back</button>
                        <button type="button" onclick="nextStep(3)" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 4: Other Info (District, Address, School)
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step4">
                    <div class="google-input-group relative">
                        <input type="text" id="district_search" class="google-input" placeholder=" " autocomplete="off"
                            value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>"
                            oninput="filterDistricts()" onfocus="showDistricts()" onblur="setTimeout(hideDistricts, 200)">
                        <label for="district_search" class="google-label">District (දිස්ත්‍රික්කය)</label>
                        <input type="hidden" id="district" name="district" value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>">
                        <div id="district_dropdown" class="absolute z-20 w-full bg-white border border-[#dadce0] rounded-md shadow-lg max-h-40 overflow-y-auto hidden"></div>
                    </div>
                    <div class="google-input-group">
                        <textarea id="address" name="address" class="google-input" placeholder=" " rows="2" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        <label for="address" class="google-label">Address (ලිපිනය)</label>
                    </div>
                    <div class="google-input-group">
                        <input type="text" id="school_name" name="school_name" class="google-input" placeholder=" " required
                            value="<?php echo htmlspecialchars($_POST['school_name'] ?? ''); ?>">
                        <label for="school_name" class="google-label">School Name (පාසලේ නම)</label>
                    </div>
                    <div class="step-nav">
                        <button type="button" onclick="prevStep(4)" class="btn-google-outline">Back</button>
                        <button type="button" onclick="nextStep(4)" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 5: Enrollment Type ONLY
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step5">
                    <p class="section-label mb-4">Choose Your Path <span class="normal-case text-slate-400 font-normal">(ඔබේ ඉගෙනුම් මාර්ගය තෝරන්න)</span></p>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <!-- Class Enrollment -->
                        <div class="enroll-card" id="enrollCard_subject" onclick="selectEnrollType('subject')">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                            </div>
                            <h3 class="font-bold text-sm text-slate-800">Class Enrollment</h3>
                            <p class="text-xs font-semibold text-blue-400 mt-0.5">පන්ති ලියාපදිංචිය</p>
                            <p class="text-[10px] text-slate-400 mt-2">Join weekly sessions with a teacher</p>
                        </div>
                        <!-- Online Course -->
                        <div class="enroll-card" id="enrollCard_course" onclick="selectEnrollType('course')">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>
                            </div>
                            <h3 class="font-bold text-sm text-slate-800">Online Course</h3>
                            <p class="text-xs font-semibold text-blue-400 mt-0.5">පාඨමාලා ලියාපදිංචිය</p>
                            <p class="text-[10px] text-slate-400 mt-2">Self-paced digital learning</p>
                        </div>
                    </div>
                    <input type="hidden" id="enrollment_type" name="enrollment_type" value="">
                    <p class="text-center text-xs text-slate-400 mt-2">Select one to continue <span class="text-slate-300">→</span></p>
                    <div class="step-nav">
                        <button type="button" onclick="prevStep(5)" class="btn-google-outline">Back</button>
                        <button type="button" onclick="nextStep(5)" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 6a: CLASS — Stream + Subject Selection
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step6_class" style="display:none;">
                    <!-- Stream search -->
                    <p class="section-label">Select Stream (Grade) <span class="normal-case text-slate-400 font-normal">(අංශය තෝරන්න)</span></p>
                    <div class="mb-3">
                        <input type="text" id="streamSearchInput" class="search-bar" placeholder="Search stream..." oninput="filterStreams()">
                    </div>
                    <div id="streamList" class="grid grid-cols-2 gap-2 mb-4 max-h-[160px] overflow-y-auto">
                        <?php foreach ($streams as $stream): ?>
                            <div class="stream-item cursor-pointer border-2 border-slate-100 bg-white rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:border-blue-300 hover:bg-blue-50 transition-all"
                                 data-name="<?php echo htmlspecialchars(strtolower($stream['name'])); ?>"
                                 data-id="<?php echo $stream['id']; ?>"
                                 onclick="selectStream(<?php echo $stream['id']; ?>, this)">
                                <?php echo htmlspecialchars($stream['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="stream_id" name="stream_id" value="">

                    <!-- Subject section (hidden until stream selected) -->
                    <div id="subjectSection" class="hidden">
                        <p class="section-label">Select Subject <span class="normal-case text-slate-400 font-normal">(විෂය තෝරන්න)</span></p>
                        <div class="mb-3">
                            <input type="text" id="subjectSearchInput" class="search-bar" placeholder="Search subject..." oninput="filterSubjects()">
                        </div>
                        <div id="subjectList" class="grid grid-cols-2 gap-2 max-h-[130px] overflow-y-auto"></div>
                        <input type="hidden" id="subject_id" name="subject_id" value="">
                    </div>

                    <div class="step-nav">
                        <button type="button" onclick="goToStep5FromClass()" class="btn-google-outline">Back</button>
                        <button type="button" onclick="nextFromClass()" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 6b: COURSE — Course Selection
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step6_course" style="display:none;">
                    <p class="section-label">Select Online Course <span class="normal-case text-slate-400 font-normal">(පාඨමාලාව තෝරන්න)</span></p>
                    <div class="mb-3">
                        <input type="text" id="courseSearchInput" class="search-bar" placeholder="Search course..." oninput="filterCourses()">
                    </div>
                    <div class="space-y-2 max-h-[340px] overflow-y-auto pr-1" id="courseList">
                        <?php foreach ($courses as $course): ?>
                            <div class="course-item" id="courseItem_<?php echo $course['id']; ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($course['title'])); ?>"
                                 onclick="selectCourse(<?php echo $course['id']; ?>, this)">
                                <div class="w-12 h-12 rounded-xl bg-slate-100 overflow-hidden flex-shrink-0">
                                    <img src="<?php echo $course['cover_image'] ? htmlspecialchars($course['cover_image']) : 'assests/course_placeholder.png'; ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-sm text-slate-800 truncate"><?php echo htmlspecialchars($course['title']); ?></div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">By <?php echo htmlspecialchars($course['teacher_name']); ?></div>
                                </div>
                                <div class="bg-red-50 text-red-600 px-3 py-1 rounded-full font-black text-[11px] whitespace-nowrap flex-shrink-0">
                                    Rs. <?php echo number_format($course['price'], 0); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="course_id" name="course_id" value="">

                    <div class="step-nav">
                        <button type="button" onclick="goToStep5FromCourse()" class="btn-google-outline">Back</button>
                        <button type="button" onclick="nextFromCourse()" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 7: Teacher Selection (Class path only)
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step7_teacher" style="display:none;">
                    <p class="section-label">Select Your Teacher <span class="normal-case text-slate-400 font-normal">(ගුරුවරයා තෝරන්න)</span></p>
                    <div class="mb-3">
                        <input type="text" id="teacherSearchInput" class="search-bar" placeholder="Search teacher by name..." oninput="filterTeachers()">
                    </div>
                    <div id="teachersGrid" class="teacher-grid h-[280px] min-h-[280px] overflow-y-auto pr-1">
                        <!-- Populated by JS -->
                    </div>
                    <div id="teachersEmpty" class="hidden text-center py-8 text-slate-400 text-sm">
                        <svg class="w-12 h-12 mx-auto mb-2 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        No teachers found for this subject
                    </div>
                    <div id="teachersLoading" class="hidden text-center py-8 text-slate-400 text-sm">
                        <svg class="w-6 h-6 animate-spin mx-auto mb-2 text-red-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Loading teachers...
                    </div>
                    <input type="hidden" id="selected_teacher_id" name="selected_teacher_id" value="">

                    <div class="step-nav">
                        <button type="button" onclick="goBackFromTeacher()" class="btn-google-outline">Back</button>
                        <button type="button" onclick="nextFromTeacher()" class="btn-google px-8">Next</button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 8: Profile Picture (optional)
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step8_photo" style="display:none;">
                    <div class="flex flex-col items-center justify-center flex-1 py-4">
                        <div class="mb-2">
                            <div id="photoPreview" class="w-28 h-28 rounded-full bg-slate-50 border-4 border-white shadow-lg flex items-center justify-center overflow-hidden mx-auto cursor-pointer" onclick="document.getElementById('profile_picture').click()">
                                <img id="previewImg" src="" alt="Preview" class="w-full h-full object-cover hidden">
                                <svg id="photoIcon" class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1">Add a Profile Picture</h3>
                        <p class="text-xs text-slate-500 mb-1">ඔබේ profile picture එකක් add කරන්න</p>
                        <p class="text-[11px] text-slate-400 mb-5">(Optional — you can skip this step)</p>

                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                            class="hidden" onchange="previewProfilePicture(this)">

                        <button type="button" onclick="document.getElementById('profile_picture').click()"
                            class="px-6 py-2.5 rounded-full border-2 border-slate-200 text-sm font-semibold text-slate-600 hover:border-red-300 hover:text-red-600 transition-all mb-3">
                            📷 Choose Photo
                        </button>

                        <p class="text-[10px] text-gray-400">Max 5MB · JPG, PNG or WebP</p>
                        <div id="photoError" class="hidden text-xs text-red-500 mt-2"></div>
                    </div>

                    <div class="step-nav">
                        <button type="button" onclick="goBackFromPhoto()" class="btn-google-outline">Back</button>
                        <div class="flex gap-2">
                            <button type="button" onclick="skipPhoto()" class="btn-google-outline text-slate-500 text-xs px-4">Skip</button>
                            <button type="button" onclick="nextFromPhoto()" class="btn-google px-8">Next</button>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════
                     STEP 9: Verification Method + Verify
                ═══════════════════════════════════════════ -->
                <div class="step-content" id="step9_verify" style="display:none;">
                    <p class="section-label mb-3">Identity Verification <span class="normal-case text-slate-400 font-normal">(අනන්‍යතාවය තහවුරු කිරීම)</span></p>

                    <!-- NIC option -->
                    <div class="verify-card" id="verifyCard_nic" onclick="selectVerifyMethod('nic')">
                        <div class="flex items-center gap-3 mb-0">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2" stroke-width="2"/><line x1="2" y1="10" x2="22" y2="10" stroke-width="2"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-bold text-slate-800">Verify by NIC</span>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">National ID Card</p>
                            </div>
                            <input type="radio" id="verify_nic" name="verification_method" value="nic" class="ml-auto w-4 h-4 text-blue-600" onchange="handleVerificationMethodChange()">
                        </div>
                        <div id="nicVerificationContainer" class="hidden mt-3 pt-3 border-t border-slate-100">
                            <div class="flex gap-2">
                                <input type="text" id="nic_number" name="nic_number" class="google-input !py-2.5 !text-sm !mb-0 flex-1" placeholder="Enter NIC Number">
                                <button type="button" onclick="verifyNIC()" class="btn-google !py-2.5 !px-4 text-[10px] uppercase font-black tracking-widest flex-shrink-0">Verify</button>
                            </div>
                            <div id="nicVerificationResult" class="mt-2 text-[11px] font-bold uppercase"></div>
                            <input type="hidden" id="nic_verified" name="nic_verified" value="0">
                        </div>
                    </div>

                    <!-- Mobile OTP option -->
                    <div class="verify-card" id="verifyCard_mobile" onclick="selectVerifyMethod('mobile')">
                        <div class="flex items-center gap-3 mb-0">
                            <div class="w-9 h-9 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-bold text-slate-800">Verify by Mobile OTP</span>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">SMS / WhatsApp code</p>
                            </div>
                            <input type="radio" id="verify_mobile" name="verification_method" value="mobile" class="ml-auto w-4 h-4 text-blue-600" onchange="handleVerificationMethodChange()">
                        </div>
                        <div id="mobileVerificationContainer" class="hidden mt-3 pt-3 border-t border-slate-100 space-y-3">
                            <div class="flex gap-2">
                                <input type="text" id="verification_mobile" class="google-input !py-2.5 !text-sm !mb-0 flex-1" placeholder="Mobile / WhatsApp number">
                                <button type="button" onclick="sendOTP()" id="sendOtpBtn" class="btn-google !py-2.5 !px-4 text-[10px] uppercase font-black tracking-widest flex-shrink-0">Send Code</button>
                            </div>
                            <div id="otpInputContainer" class="hidden flex gap-2">
                                <input type="text" id="otp_code" class="google-input !py-2.5 !text-sm !mb-0 flex-1" placeholder="6-digit Code">
                                <button type="button" onclick="verifyOTP()" class="btn-google !bg-green-600 !py-2.5 !px-4 text-[10px] uppercase font-black tracking-widest flex-shrink-0">Verify</button>
                            </div>
                            <div id="otpVerificationResult" class="text-[11px] font-bold uppercase"></div>
                            <input type="hidden" id="otp_verified" name="otp_verified" value="0">
                        </div>
                    </div>

                    <div id="verificationStatus" class="mt-2 text-sm text-center font-medium"></div>

                    <div class="step-nav">
                        <button type="button" onclick="goBackFromVerify()" class="btn-google-outline">Back</button>
                        <button type="submit" name="add_user" id="registerButton" class="btn-google px-8 !bg-green-600 hover:!bg-green-700">Register</button>
                    </div>
                </div>

            </form>
            <?php endif; ?>

        </div><!-- end right col -->
    </div><!-- end registration-container -->
    </div><!-- end bg-design -->

    <!-- Toast container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-[9999] space-y-2"></div>

    <script>
    // ═══════════════════════════════════════════════════
    //  State
    // ═══════════════════════════════════════════════════
    let currentStepId = 'step1';
    const TOTAL_VISIBLE_STEPS = 9; // for the dot counter

    // Map step IDs to dot indices (1-based)
    const stepDotMap = {
        'step1':        1,
        'step2':        2,
        'step3':        3,
        'step4':        4,
        'step5':        5,
        'step6_class':  6,
        'step6_course': 6,
        'step7_teacher':7,
        'step8_photo':  8,
        'step9_verify': 9,
    };

    const stepTitles = {
        'step1':         { en:'Create your Account',          si:'(ගිණුමක් සාදාගන්න)',               sub:'Be a part of the Lernerr.LK family',                    subSi:'' },
        'step2':         { en:'Secure your Account',          si:'(මුරපදයක් යොදන්න)',                sub:'Keep your account safe with a strong password',          subSi:'(ශක්තිමත් මුරපදයක් භාවිතා කරන්න)' },
        'step3':         { en:'Contact Numbers',              si:'(ජංගම අංක)',                        sub:'How can we reach you?',                                  subSi:'(ඔබව සම්බන්ධ කර ගැනීමට)' },
        'step4':         { en:'Location & School',            si:'(ස්ථානය සහ පාසල)',                  sub:'Where are you studying?',                                subSi:'(ඔබ ඉගෙන ගන්නේ කොහේද?)' },
        'step5':         { en:'Choose Your Path',             si:'(ඉගෙනුම් මාර්ගය)',                 sub:'Select how you want to learn',                           subSi:'(ඉගෙනීමේ ක්‍රමය තෝරන්න)' },
        'step6_class':   { en:'Stream & Subject',             si:'(අංශය සහ විෂය)',                   sub:'Select your grade stream and subject',                   subSi:'(ශ්‍රේණිය සහ විෂය තෝරන්න)' },
        'step6_course':  { en:'Select a Course',              si:'(පාඨමාලාව)',                        sub:'Pick an online course to enroll in',                     subSi:'(ඇතුළත් වීමට පාඨමාලාවක් තෝරන්න)' },
        'step7_teacher': { en:'Pick Your Teacher',            si:'(ගුරුවරයා)',                        sub:'Select a teacher for your subject',                      subSi:'(ඔබේ විෂය සඳහා ගුරුවරයෙකු තෝරන්න)' },
        'step8_photo':   { en:'Profile Picture',              si:'(Profile Picture)',                 sub:'Add a photo (optional)',                                  subSi:'(අවශ්‍ය නොවේ — skip කළ හැක)' },
        'step9_verify':  { en:'Verify Identity',              si:'(අනන්‍යතාවය)',                      sub:'Almost done! Verify your identity to complete registration', subSi:'(ලියාපදිංචිය සම්පූර්ණ කිරීමට)' },
    };

    // ═══════════════════════════════════════════════════
    //  Auto-save & Restore Logic
    // ═══════════════════════════════════════════════════
    const STORAGE_KEY = 'learner_registration_data';

    function saveFormData() {
        const formData = {};
        const inputs = document.querySelectorAll('#addUserForm input, #addUserForm select, #addUserForm textarea');
        inputs.forEach(input => {
            if (input.type === 'password' || input.type === 'file') return;
            if (input.type === 'radio' || input.type === 'checkbox') {
                if (input.checked) formData[input.name] = input.value;
            } else {
                formData[input.id || input.name] = input.value;
            }
        });
        formData['currentStepId'] = currentStepId;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
    }

    function restoreFormData() {
        const savedData = localStorage.getItem(STORAGE_KEY);
        if (!savedData) {
            showStep('step1');
            return;
        }
        
        const formData = JSON.parse(savedData);
        Object.keys(formData).forEach(key => {
            if (key === 'currentStepId') return;
            
            const input = document.getElementById(key) || document.querySelector(`[name="${key}"]`);
            if (!input) return;

            if (input.type === 'radio' || input.type === 'checkbox') {
                if (input.value === formData[key]) {
                    input.checked = true;
                }
            } else {
                input.value = formData[key];
                // Dispatch input event to raise Google-style labels
                input.dispatchEvent(new Event('input'));
            }
        });

        // Restore custom UI selections
        if (formData['enrollment_type']) {
            selectEnrollType(formData['enrollment_type']);
        }
        
        if (formData['stream_id']) {
            const streamEl = document.querySelector(`.stream-item[data-id="${formData['stream_id']}"]`);
            if (streamEl) {
                selectStream(parseInt(formData['stream_id']), streamEl);
                if (formData['subject_id']) {
                    setTimeout(() => {
                        const subEl = document.querySelector(`.subject-item[data-id="${formData['subject_id']}"]`);
                        if (subEl) {
                            selectSubject(parseInt(formData['subject_id']), subEl);
                            
                            loadTeachersForStep7(() => {
                                if (formData['selected_teacher_id']) {
                                    const teacherCard = document.querySelector(`.teacher-card[data-teacher-id="${formData['selected_teacher_id']}"]`);
                                    if (teacherCard) teacherCard.click();
                                }
                            });
                        }
                    }, 800);
                }
            }
        }

        if (formData['course_id']) {
            const courseEl = document.getElementById('courseItem_' + formData['course_id']);
            if (courseEl) {
                selectCourse(parseInt(formData['course_id']), courseEl);
            }
        }

        const checkedVerify = document.querySelector('input[name="verification_method"]:checked');
        if (checkedVerify) {
            selectVerifyMethod(checkedVerify.value);
        }

        if (formData['currentStepId']) {
            showStep(formData['currentStepId']);
        } else {
            showStep('step1');
        }
    }

    // ═══════════════════════════════════════════════════
    //  Show Step
    // ═══════════════════════════════════════════════════
    function showStep(stepId) {
        // Hide all steps
        document.querySelectorAll('.step-content').forEach(el => {
            el.classList.remove('active');
            el.style.display = '';
        });

        // Show target
        const el = document.getElementById(stepId);
        if (!el) return;
        el.style.display = 'flex';
        el.classList.add('active');

        currentStepId = stepId;

        // Update dots
        const dots = document.getElementsByClassName('step-dot');
        const dotIdx = (stepDotMap[stepId] || 1) - 1;
        Array.from(dots).forEach((d, i) => {
            d.classList.toggle('active', i === dotIdx);
        });

        // Update titles
        const t = stepTitles[stepId] || stepTitles['step1'];
        const titleEl = document.getElementById('stepTitle');
        const titleSiEl = document.getElementById('stepTitleSinhala');
        const subEl = document.getElementById('stepSubtitle');
        const subSiEl = document.getElementById('stepSubtitleSinhala');
        const counterEl = document.getElementById('stepCounter');

        if (titleEl) titleEl.textContent = t.en;
        if (titleSiEl) titleSiEl.textContent = t.si;
        if (subEl) subEl.textContent = t.sub;
        if (subSiEl) subSiEl.textContent = t.subSi;
        if (counterEl) counterEl.textContent = `Step ${stepDotMap[stepId] || 1} of ${TOTAL_VISIBLE_STEPS}`;

        saveFormData();
        window.scrollTo(0, 0);
    }

    // ═══════════════════════════════════════════════════
    //  Validation helpers
    // ═══════════════════════════════════════════════════
    function validateStep(stepId) {
        if (stepId === 'step1') {
            const fn = document.getElementById('first_name').value.trim();
            const sn = document.getElementById('second_name').value.trim();
            const dob = document.getElementById('dob').value.trim();
            const gender = document.getElementById('gender').value.trim();
            if (!fn) { showToast('Please enter your first name', 'error'); return false; }
            if (!sn) { showToast('Please enter your last name', 'error'); return false; }
            if (!dob) { showToast('Please enter your date of birth', 'error'); return false; }
            if (!gender) { showToast('Please select your gender', 'error'); return false; }
        }
        if (stepId === 'step2') {
            const pass = document.getElementById('password').value;
            const conf = document.getElementById('confirm_password').value;
            if (pass.length < 8) { showToast('Password must be at least 8 characters', 'error'); return false; }
            if (pass !== conf) { showToast('Passwords do not match!', 'error'); return false; }
        }
        if (stepId === 'step3') {
            const wa = document.getElementById('whatsapp_number').value.trim();
            const mob = document.getElementById('mobile_number').value.trim();
            if (!wa) { showToast('Please enter your WhatsApp number', 'error'); return false; }
            if (!mob) { showToast('Please enter your mobile number', 'error'); return false; }
        }
        if (stepId === 'step4') {
            const addr = document.getElementById('address').value.trim();
            const school = document.getElementById('school_name').value.trim();
            if (!addr) { showToast('Please enter your address', 'error'); return false; }
            if (!school) { showToast('Please enter your school name', 'error'); return false; }
        }
        if (stepId === 'step5') {
            const et = document.getElementById('enrollment_type').value;
            if (!et) { showToast('Please select an enrollment path', 'error'); return false; }
        }
        return true;
    }

    // ═══════════════════════════════════════════════════
    //  Step navigation
    // ═══════════════════════════════════════════════════
    function nextStep(fromStepNum) {
        const stepIdMap = { 1:'step1', 2:'step2', 3:'step3', 4:'step4', 5:'step5' };
        const stepId = stepIdMap[fromStepNum];
        if (!validateStep(stepId)) return;

        if (fromStepNum === 5) {
            const et = document.getElementById('enrollment_type').value;
            if (et === 'subject') {
                showStep('step6_class');
            } else {
                showStep('step6_course');
            }
            return;
        }

        const nextMap = { 1:'step2', 2:'step3', 3:'step4', 4:'step5' };
        if (nextMap[fromStepNum]) showStep(nextMap[fromStepNum]);
    }

    function prevStep(fromStepNum) {
        const prevMap = { 2:'step1', 3:'step2', 4:'step3', 5:'step4' };
        if (prevMap[fromStepNum]) showStep(prevMap[fromStepNum]);
    }

    // Class flow
    function goToStep5FromClass() { showStep('step5'); }
    function nextFromClass() {
        const streamId = document.getElementById('stream_id').value;
        const subjectId = document.getElementById('subject_id').value;
        if (!streamId) { showToast('Please select a stream', 'error'); return; }
        if (!subjectId) { showToast('Please select a subject', 'error'); return; }
        // Load teachers and proceed
        loadTeachersForStep7(() => showStep('step7_teacher'));
    }

    // Course flow
    function goToStep5FromCourse() { showStep('step5'); }
    function nextFromCourse() {
        const courseId = document.getElementById('course_id').value;
        if (!courseId) { showToast('Please select a course', 'error'); return; }
        showStep('step8_photo');
    }

    // Teacher flow
    function goBackFromTeacher() { showStep('step6_class'); }
    function nextFromTeacher() {
        const teacherId = document.getElementById('selected_teacher_id').value;
        if (!teacherId) { showToast('Please select a teacher', 'error'); return; }
        showStep('step8_photo');
    }

    // Photo flow
    function goBackFromPhoto() {
        const et = document.getElementById('enrollment_type').value;
        if (et === 'subject') {
            showStep('step7_teacher');
        } else {
            showStep('step6_course');
        }
    }
    function skipPhoto() { showStep('step9_verify'); }
    function nextFromPhoto() { showStep('step9_verify'); }

    // Verify flow
    function goBackFromVerify() { showStep('step8_photo'); }

    // ═══════════════════════════════════════════════════
    //  Enrollment type selection (Step 5)
    // ═══════════════════════════════════════════════════
    function selectEnrollType(type) {
        document.getElementById('enrollment_type').value = type;

        document.getElementById('enrollCard_subject').classList.remove('selected');
        document.getElementById('enrollCard_course').classList.remove('selected');

        if (type === 'subject') {
            document.getElementById('enrollCard_subject').classList.add('selected');
        } else {
            document.getElementById('enrollCard_course').classList.add('selected');
        }
        saveFormData();
    }

    // ═══════════════════════════════════════════════════
    //  Stream selection + search (Step 6a)
    // ═══════════════════════════════════════════════════
    function filterStreams() {
        const q = document.getElementById('streamSearchInput').value.toLowerCase();
        document.querySelectorAll('.stream-item').forEach(item => {
            item.style.display = item.dataset.name.includes(q) ? '' : 'none';
        });
    }

    function selectStream(streamId, el) {
        // Deselect all
        document.querySelectorAll('.stream-item').forEach(i => {
            i.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-700');
            i.classList.add('border-slate-100');
        });
        el.classList.remove('border-slate-100');
        el.classList.add('border-blue-500', 'bg-blue-50', 'text-blue-700');

        document.getElementById('stream_id').value = streamId;
        saveFormData();

        // Reset subject
        document.getElementById('subject_id').value = '';
        document.getElementById('subjectSection').classList.remove('hidden');
        document.getElementById('subjectList').innerHTML = '<div class="col-span-2 text-center text-slate-400 text-xs py-3">Loading subjects...</div>';
        document.getElementById('selected_teacher_id').value = '';

        // Load subjects
        fetch(`get_subjects.php?stream_id=${streamId}`)
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('subjectList');
                list.innerHTML = '';
                if (data.success && data.subjects && data.subjects.length > 0) {
                    data.subjects.forEach(s => {
                        const div = document.createElement('div');
                        div.className = 'subject-item cursor-pointer border-2 border-slate-100 bg-white rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:border-blue-300 hover:bg-blue-50 transition-all';
                        div.dataset.name = s.name.toLowerCase();
                        div.dataset.id = s.id;
                        div.textContent = s.name;
                        div.onclick = function() { selectSubject(s.id, div); };
                        list.appendChild(div);
                    });
                } else {
                    list.innerHTML = '<div class="col-span-2 text-center text-slate-400 text-xs py-3">No subjects found for this stream</div>';
                }
            })
            .catch(() => {
                document.getElementById('subjectList').innerHTML = '<div class="col-span-2 text-center text-red-400 text-xs py-3">Error loading subjects</div>';
            });
    }

    function filterSubjects() {
        const q = document.getElementById('subjectSearchInput').value.toLowerCase();
        document.querySelectorAll('.subject-item').forEach(item => {
            item.style.display = item.dataset.name.includes(q) ? '' : 'none';
        });
    }

    function selectSubject(subjectId, el) {
        document.querySelectorAll('.subject-item').forEach(i => {
            i.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-700');
            i.classList.add('border-slate-100');
        });
        el.classList.remove('border-slate-100');
        el.classList.add('border-blue-500', 'bg-blue-50', 'text-blue-700');
        document.getElementById('subject_id').value = subjectId;
        document.getElementById('selected_teacher_id').value = '';
        saveFormData();
    }

    // ═══════════════════════════════════════════════════
    //  Course search + selection (Step 6b)
    // ═══════════════════════════════════════════════════
    function filterCourses() {
        const q = document.getElementById('courseSearchInput').value.toLowerCase();
        document.querySelectorAll('.course-item').forEach(item => {
            item.style.display = (item.dataset.name || '').includes(q) ? '' : 'none';
        });
    }

    function selectCourse(courseId, el) {
        document.querySelectorAll('.course-item').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('course_id').value = courseId;
        saveFormData();
    }

    // ═══════════════════════════════════════════════════
    //  Load & display teachers (Step 7)
    // ═══════════════════════════════════════════════════
    function loadTeachersForStep7(callback) {
        const streamId = document.getElementById('stream_id').value;
        const subjectId = document.getElementById('subject_id').value;
        const grid = document.getElementById('teachersGrid');
        const emptyMsg = document.getElementById('teachersEmpty');
        const loading = document.getElementById('teachersLoading');

        grid.innerHTML = '';
        grid.classList.add('hidden');
        emptyMsg.classList.add('hidden');
        loading.classList.remove('hidden');

        fetch(`get_teachers.php?stream_id=${streamId}&subject_id=${subjectId}`)
            .then(r => r.json())
            .then(data => {
                loading.classList.add('hidden');
                if (data.success && data.teachers.length > 0) {
                    data.teachers.forEach(t => {
                        grid.appendChild(createTeacherCard(t));
                    });
                    grid.classList.remove('hidden');
                    emptyMsg.classList.add('hidden');
                } else {
                    grid.classList.add('hidden');
                    emptyMsg.classList.remove('hidden');
                }
                document.getElementById('selected_teacher_id').value = '';
                if (callback) callback();
            })
            .catch(() => {
                loading.classList.add('hidden');
                grid.classList.add('hidden');
                emptyMsg.classList.remove('hidden');
                if (callback) callback();
            });
    }

    function createTeacherCard(teacher) {
        const card = document.createElement('div');
        card.className = 'teacher-card';
        card.dataset.teacherId = teacher.teacher_id;
        card.dataset.name = ((teacher.first_name || '') + ' ' + (teacher.second_name || '')).toLowerCase();

        const name = ((teacher.first_name || '') + ' ' + (teacher.second_name || '')).trim() || 'Teacher';
        const pic  = teacher.profile_picture ? teacher.profile_picture : 'assests/student_avatar.png';
        const enFee = teacher.enrollment_fee > 0 ? 'Rs. ' + parseFloat(teacher.enrollment_fee).toLocaleString() : 'Free';
        const moFee = teacher.monthly_fee > 0 ? 'Rs. ' + parseFloat(teacher.monthly_fee).toLocaleString() + '/mo' : 'Free';
        const degree = teacher.degree || '';
        const uni = teacher.university || '';

        card.innerHTML = `
            <div class="tc-selected-badge">✓</div>
            <div class="flex items-center gap-2 mb-2">
                <img src="${pic}" class="tc-pic" onerror="this.src='assests/student_avatar.png'" alt="${name}">
                <div class="flex-1 min-w-0">
                    <div class="tc-name truncate">${name}</div>
                    ${degree ? `<div class="tc-degree truncate">${degree}</div>` : ''}
                    ${uni ? `<div class="tc-uni truncate">${uni}</div>` : ''}
                </div>
            </div>
            <div class="flex flex-col gap-1 mt-1 text-[11px] font-semibold text-slate-700">
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400 font-medium">Enroll Fee:</span>
                    <span class="text-red-600 font-bold">${enFee}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400 font-medium">Monthly Fee:</span>
                    <span class="text-green-600 font-bold">${moFee}</span>
                </div>
            </div>
        `;

        card.onclick = function() {
            document.querySelectorAll('.teacher-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            document.getElementById('selected_teacher_id').value = teacher.teacher_id;
            saveFormData();
        };

        return card;
    }

    // Teacher search filter
    function filterTeachers() {
        const q = document.getElementById('teacherSearchInput').value.toLowerCase();
        document.querySelectorAll('.teacher-card').forEach(card => {
            card.style.display = (card.dataset.name || '').includes(q) ? '' : 'none';
        });
    }

    // ═══════════════════════════════════════════════════
    //  Profile picture preview (Step 8)
    // ═══════════════════════════════════════════════════
    function previewProfilePicture(input) {
        const previewImg = document.getElementById('previewImg');
        const photoIcon  = document.getElementById('photoIcon');
        const photoError = document.getElementById('photoError');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                photoError.textContent = 'File size exceeds 5MB limit.';
                photoError.classList.remove('hidden');
                input.value = '';
                return;
            }
            const allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
            if (!allowed.includes(file.type)) {
                photoError.textContent = 'Invalid file type. Please select an image.';
                photoError.classList.remove('hidden');
                input.value = '';
                return;
            }
            photoError.classList.add('hidden');
            const reader = new FileReader();
            reader.onload = e => {
                previewImg.src = e.target.result;
                previewImg.classList.remove('hidden');
                photoIcon.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            previewImg.classList.add('hidden');
            photoIcon.classList.remove('hidden');
        }
    }

    // ═══════════════════════════════════════════════════
    //  Verification (Step 9)
    // ═══════════════════════════════════════════════════
    function selectVerifyMethod(method) {
        // Click the matching radio
        document.getElementById('verify_' + method).checked = true;
        handleVerificationMethodChange();
        // Highlight card
        document.getElementById('verifyCard_nic').classList.remove('selected');
        document.getElementById('verifyCard_mobile').classList.remove('selected');
        document.getElementById('verifyCard_' + method).classList.add('selected');
        saveFormData();
    }

    function handleVerificationMethodChange() {
        const nicMethod    = document.getElementById('verify_nic');
        const mobileMethod = document.getElementById('verify_mobile');
        const nicContainer    = document.getElementById('nicVerificationContainer');
        const mobileContainer = document.getElementById('mobileVerificationContainer');

        document.getElementById('nic_verified').value = '0';
        document.getElementById('otp_verified').value = '0';

        if (nicMethod.checked) {
            nicContainer.classList.remove('hidden');
            mobileContainer.classList.add('hidden');
        } else if (mobileMethod.checked) {
            nicContainer.classList.add('hidden');
            mobileContainer.classList.remove('hidden');
            // Auto-fill mobile
            const wa  = document.getElementById('whatsapp_number').value.trim();
            const mob = document.getElementById('mobile_number').value.trim();
            document.getElementById('verification_mobile').value = wa || mob || '';
        } else {
            nicContainer.classList.add('hidden');
            mobileContainer.classList.add('hidden');
        }
    }

    function verifyNIC() {
        const nicNumber = document.getElementById('nic_number').value.trim();
        const resultDiv = document.getElementById('nicVerificationResult');
        const dob    = document.getElementById('dob').value.trim();
        const gender = document.getElementById('gender').value.trim();

        if (!nicNumber) { resultDiv.innerHTML = '<span class="text-red-600">Please enter NIC number</span>'; return; }
        if (!dob)    { showToast('Please enter your Date of Birth first', 'error'); return; }
        if (!gender) { showToast('Please select your Gender first', 'error'); return; }

        resultDiv.innerHTML = '<span class="text-slate-400">Verifying...</span>';

        const fd = new FormData();
        fd.append('nic', nicNumber);
        fd.append('dob', dob);
        fd.append('gender', gender);

        fetch('verify_nic.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.valid) {
                    resultDiv.innerHTML = '<span class="text-green-600">✓ NIC verified successfully!</span>';
                    document.getElementById('nic_verified').value = '1';
                    updateVerificationStatus();
                    showToast('NIC verified!', 'success');
                } else {
                    resultDiv.innerHTML = '<span class="text-red-600">✗ NIC verification failed. Check your information.</span>';
                    document.getElementById('nic_verified').value = '0';
                    showToast('NIC verification failed', 'error');
                }
            })
            .catch(() => {
                resultDiv.innerHTML = '<span class="text-red-600">Error verifying NIC. Try again.</span>';
                document.getElementById('nic_verified').value = '0';
            });
    }

    function sendOTP() {
        const mobile = document.getElementById('verification_mobile').value.trim();
        const btn    = document.getElementById('sendOtpBtn');
        if (!mobile) { showToast('Please enter mobile number', 'error'); return; }
        btn.disabled = true;
        btn.textContent = 'Sending...';
        const fd = new FormData();
        fd.append('mobile_number', mobile);
        fetch('send_otp.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('otpInputContainer').classList.remove('hidden');
                    showToast('OTP sent! Code: ' + data.otp, 'success');
                } else {
                    showToast('Error: ' + (data.message || 'Failed to send OTP'), 'error');
                }
                btn.disabled = false;
                btn.textContent = 'Send Code';
            })
            .catch(() => {
                showToast('Error sending OTP. Try again.', 'error');
                btn.disabled = false;
                btn.textContent = 'Send Code';
            });
    }

    function verifyOTP() {
        const code = document.getElementById('otp_code').value.trim();
        const resultDiv = document.getElementById('otpVerificationResult');
        if (!code || code.length !== 6) {
            resultDiv.innerHTML = '<span class="text-red-600">Please enter 6-digit code</span>';
            return;
        }
        const fd = new FormData();
        fd.append('otp_code', code);
        fetch('verify_otp.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.verified) {
                    resultDiv.innerHTML = '<span class="text-green-600">✓ Mobile verified!</span>';
                    document.getElementById('otp_verified').value = '1';
                    updateVerificationStatus();
                    showToast('Mobile verified!', 'success');
                } else {
                    resultDiv.innerHTML = '<span class="text-red-600">✗ ' + (data.message || 'Invalid code') + '</span>';
                    document.getElementById('otp_verified').value = '0';
                    showToast(data.message || 'Invalid OTP', 'error');
                }
            })
            .catch(() => {
                resultDiv.innerHTML = '<span class="text-red-600">Error verifying OTP</span>';
            });
    }

    function updateVerificationStatus() {
        const nicOk = document.getElementById('nic_verified').value === '1';
        const otpOk = document.getElementById('otp_verified').value === '1';
        const statusDiv = document.getElementById('verificationStatus');
        if (nicOk || otpOk) {
            statusDiv.innerHTML = `<div class="bg-green-50 border border-green-200 text-green-700 p-3 rounded-lg text-xs font-medium flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Verification successful! Click Register to complete.</div>`;
        } else {
            statusDiv.innerHTML = '';
        }
    }

    // ═══════════════════════════════════════════════════
    //  District handling (Step 4)
    // ═══════════════════════════════════════════════════
    const districts = <?php echo json_encode($districts); ?>;

    function populateDistricts(filter = '') {
        const dd = document.getElementById('district_dropdown');
        if (!dd) return;
        dd.innerHTML = '';
        const filtered = districts.filter(d => d.toLowerCase().includes(filter.toLowerCase()));
        if (filtered.length === 0) {
            dd.innerHTML = '<div class="px-4 py-2 text-gray-500 text-sm">No results</div>';
            return;
        }
        filtered.forEach(d => {
            const div = document.createElement('div');
            div.className = 'px-4 py-2 hover:bg-red-50 cursor-pointer text-gray-700 text-sm';
            div.textContent = d;
            div.onclick = () => selectDistrict(d);
            dd.appendChild(div);
        });
    }
    function filterDistricts() {
        populateDistricts(document.getElementById('district_search').value);
        document.getElementById('district_dropdown').classList.remove('hidden');
    }
    function showDistricts() {
        populateDistricts(document.getElementById('district_search').value);
        document.getElementById('district_dropdown').classList.remove('hidden');
    }
    function hideDistricts() {
        const dd = document.getElementById('district_dropdown');
        if (dd) dd.classList.add('hidden');
    }
    function selectDistrict(value) {
        document.getElementById('district_search').value = value;
        document.getElementById('district').value = value;
        hideDistricts();
    }

    // ═══════════════════════════════════════════════════
    //  Toast
    // ═══════════════════════════════════════════════════
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const id = 'toast-' + Date.now();
        const bg = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600';
        const toast = document.createElement('div');
        toast.id = id;
        toast.className = `${bg} text-white px-5 py-3 rounded-xl shadow-xl flex items-center gap-3 transform transition-all duration-300 translate-x-full opacity-0 max-w-sm text-sm font-medium`;
        toast.innerHTML = `<span class="flex-1">${message}</span><button onclick="closeToast('${id}')" class="text-white/80 hover:text-white ml-2 text-lg leading-none">&times;</button>`;
        container.appendChild(toast);
        setTimeout(() => { toast.classList.remove('translate-x-full', 'opacity-0'); }, 10);
        const duration = type === 'error' ? 2000 : 5000;
        setTimeout(() => closeToast(id), duration);
    }
    function closeToast(id) {
        const t = document.getElementById(id);
        if (!t) return;
        t.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => t.remove(), 300);
    }

    // ═══════════════════════════════════════════════════
    //  Form Submit Validation
    // ═══════════════════════════════════════════════════
    document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
        const verMethod = document.querySelector('input[name="verification_method"]:checked');
        const nicOk = document.getElementById('nic_verified').value === '1';
        const otpOk = document.getElementById('otp_verified').value === '1';

        if (!verMethod) {
            e.preventDefault();
            showToast('Please select a verification method', 'error');
            return false;
        }
        if (verMethod.value === 'nic' && !nicOk) {
            e.preventDefault();
            showToast('Please verify your NIC before submitting', 'error');
            return false;
        }
        if (verMethod.value === 'mobile' && !otpOk) {
            e.preventDefault();
            showToast('Please verify your mobile with OTP before submitting', 'error');
            return false;
        }
    });

    // ═══════════════════════════════════════════════════
    //  Handle URL params for pre-selection
    // ═══════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', function() {
        // First restore auto-saved inputs
        restoreFormData();

        // Handle URL params for pre-selection (takes precedence)
        const urlParams = new URLSearchParams(window.location.search);
        const courseId  = urlParams.get('course_id');
        const streamId  = urlParams.get('stream_id');
        const subjectId = urlParams.get('subject_id');

        if (courseId && parseInt(courseId) > 0) {
            document.getElementById('enrollment_type').value = 'course';
            selectEnrollType('course');
            setTimeout(() => {
                const courseEl = document.getElementById('courseItem_' + courseId);
                if (courseEl) selectCourse(parseInt(courseId), courseEl);
                else document.getElementById('course_id').value = courseId;
            }, 100);
        } else if (streamId && parseInt(streamId) > 0) {
            document.getElementById('enrollment_type').value = 'subject';
            selectEnrollType('subject');
            const streamEl = document.querySelector(`.stream-item[data-id="${streamId}"]`);
            if (streamEl) {
                setTimeout(() => {
                    selectStream(parseInt(streamId), streamEl);
                    if (subjectId && parseInt(subjectId) > 0) {
                        setTimeout(() => {
                            const subEl = document.querySelector(`.subject-item[data-id="${subjectId}"]`);
                            if (subEl) selectSubject(parseInt(subjectId), subEl);
                        }, 600);
                    }
                }, 100);
            }
        }

        // Set up input listeners to trigger auto-save
        const form = document.getElementById('addUserForm');
        if (form) {
            form.addEventListener('input', saveFormData);
            form.addEventListener('change', saveFormData);
            form.addEventListener('submit', () => {
                // Clear storage on successful submission
                localStorage.removeItem(STORAGE_KEY);
            });
        }
    });
    </script>
</body>
</html>