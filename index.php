<?php
require_once __DIR__ . '/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$is_logged_in = !empty($user_id);

// Helpers for fallbacks
if (!function_exists('get_initials')) {
    function get_initials($title) {
        $words = preg_split('/[\s\-_,.]+/', trim($title));
        $initials = '';
        foreach ($words as $w) {
            if ($w !== '') {
                $initials .= mb_substr($w, 0, 1, 'UTF-8');
            }
        }
        return mb_strtoupper(mb_substr($initials, 0, 3, 'UTF-8'), 'UTF-8');
    }
}

if (!function_exists('get_fallback_gradient')) {
    function get_fallback_gradient($title) {
        $presets = [
            'from-blue-600 to-indigo-700',
            'from-emerald-500 to-teal-600',
            'from-violet-600 to-purple-700',
            'from-rose-500 to-pink-600',
            'from-amber-500 to-orange-600',
            'from-cyan-500 to-blue-600',
        ];
        $index = crc32($title) % count($presets);
        return $presets[abs($index)];
    }
}

// Get error/success messages from URL
$error_message = isset($_GET['error']) ? urldecode($_GET['error']) : '';
$success_message = isset($_GET['success']) ? urldecode($_GET['success']) : '';
$teacher_registered = isset($_GET['teacher_registered']) || !empty($_SESSION['pending_teacher_notice']);
if (isset($_SESSION['pending_teacher_notice'])) {
    unset($_SESSION['pending_teacher_notice']);
}

// Get all available courses
$courses_query = "SELECT c.id, c.teacher_id, c.title, c.description, c.price, c.cover_image, c.duration,
                  u.first_name, u.second_name, u.profile_picture as teacher_image
                  FROM courses c
                  LEFT JOIN users u ON c.teacher_id = u.user_id COLLATE utf8mb4_general_ci
                  WHERE c.status = 1
                  ORDER BY c.created_at DESC";

$courses_result = $conn->query($courses_query);
$courses = [];

if (!$courses_result) {
    error_log("Database Error in courses query: " . $conn->error);
} else {
    while ($row = $courses_result->fetch_assoc()) {
        $row['teacher_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
        $courses[] = $row;
    }
}

// Get all active classes (teacher assignments) grouped by stream
$assignments_query = "SELECT ta.*, s.name as stream_name, s.id as stream_id, sub.name as subject_name, sub.code as subject_code, sub.id as subject_id,
                             u.first_name, u.second_name, u.profile_picture as teacher_image,
                             (SELECT enrollment_fee FROM enrollment_fees WHERE teacher_assignment_id = ta.id LIMIT 1) as enrollment_fee,
                             (SELECT monthly_fee FROM enrollment_fees WHERE teacher_assignment_id = ta.id LIMIT 1) as monthly_fee
                      FROM teacher_assignments ta
                      INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                      INNER JOIN streams s ON ss.stream_id = s.id
                      INNER JOIN subjects sub ON ss.subject_id = sub.id
                      INNER JOIN users u ON ta.teacher_id = u.user_id
                      WHERE ta.status = 'active'
                      ORDER BY s.name, sub.name";
$assignments_result = $conn->query($assignments_query);
$assignments_by_stream = [];
$available_academic_years = [];
if ($assignments_result) {
    while ($row = $assignments_result->fetch_assoc()) {
        $stream_id = $row['stream_id'];
        if (!isset($assignments_by_stream[$stream_id])) {
            $assignments_by_stream[$stream_id] = [
                'stream_name' => $row['stream_name'],
                'classes' => []
            ];
        }
        $row['teacher_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
        if (!empty($row['academic_year']) && !in_array($row['academic_year'], $available_academic_years)) {
            $available_academic_years[] = (int)$row['academic_year'];
        }
        $assignments_by_stream[$stream_id]['classes'][] = $row;
    }
    rsort($available_academic_years);
}

// Check for existing enrollments if student
$user_enrollment_data = [];
if ($is_logged_in && $role === 'student') {
    $enr_query = "SELECT id, stream_subject_id FROM student_enrollment WHERE student_id = '$user_id' AND status = 'active'";
    $enr_res = $conn->query($enr_query);
    if ($enr_res) {
        $enrollment_ids = [];
        while ($row = $enr_res->fetch_assoc()) {
            $user_enrollment_data[$row['stream_subject_id']] = [
                'id' => $row['id'],
                'enrollment_paid' => false,
                'monthly_status' => 'not_paid'
            ];
            $enrollment_ids[] = $row['id'];
        }

        if (!empty($enrollment_ids)) {
            $ids_str = implode(',', $enrollment_ids);
            $ep_query = "SELECT student_enrollment_id, payment_status FROM enrollment_payments WHERE student_enrollment_id IN ($ids_str) ORDER BY id DESC";
            $ep_res = $conn->query($ep_query);
            while ($row = $ep_res->fetch_assoc()) {
                foreach ($user_enrollment_data as $ssid => $data) {
                    if ($data['id'] == $row['student_enrollment_id']) {
                        if (!isset($user_enrollment_data[$ssid]['enrollment_status_raw'])) {
                            $user_enrollment_data[$ssid]['enrollment_status_raw'] = $row['payment_status'];
                            if ($row['payment_status'] == 'paid' || $row['payment_status'] == 'approved') {
                                $user_enrollment_data[$ssid]['enrollment_paid'] = true;
                                $user_enrollment_data[$ssid]['enrollment_status'] = 'Paid';
                            } elseif ($row['payment_status'] == 'pending') {
                                $user_enrollment_data[$ssid]['enrollment_paid'] = false;
                                $user_enrollment_data[$ssid]['enrollment_status'] = 'Pending';
                            } else {
                                $user_enrollment_data[$ssid]['enrollment_paid'] = false;
                                $user_enrollment_data[$ssid]['enrollment_status'] = 'not_paid';
                            }
                        }
                    }
                }
            }

            $current_month = date('n');
            $current_year = date('Y');
            $mp_query = "SELECT student_enrollment_id, payment_status FROM monthly_payments WHERE student_enrollment_id IN ($ids_str) AND month = $current_month AND year = $current_year ORDER BY id DESC";
            $mp_res = $conn->query($mp_query);
            while ($row = $mp_res->fetch_assoc()) {
                foreach ($user_enrollment_data as $ssid => $data) {
                    if ($data['id'] == $row['student_enrollment_id']) {
                        if (!isset($user_enrollment_data[$ssid]['monthly_status_raw'])) {
                            $user_enrollment_data[$ssid]['monthly_status_raw'] = $row['payment_status'];
                            $st = $row['payment_status'];
                            if ($st == 'paid' || $st == 'approved')
                                $st = 'Paid';
                            elseif ($st == 'pending')
                                $st = 'Pending';
                            $user_enrollment_data[$ssid]['monthly_status'] = ucfirst($st);
                        }
                    }
                }
            }
        }
    }
}

if ($is_logged_in && $role === 'student') {
    require_once __DIR__ . '/check_al_redirection.php';

    $enrolled_count = count($user_enrollment_data);
    $pending_payments_count = 0;
    foreach ($user_enrollment_data as $data) {
        if (($data['monthly_status'] ?? '') === 'Pending' || ($data['monthly_status'] ?? '') === 'not_paid') {
            $pending_payments_count++;
        }
    }

    $exams_query = "SELECT COUNT(*) as count FROM exams WHERE status = 'active'";
    $exams_res = $conn->query($exams_query);
    $upcoming_exams_count = $exams_res ? $exams_res->fetch_assoc()['count'] : 0;

    $courses_enrolled_query = "SELECT COUNT(*) as count FROM course_enrollments WHERE student_id = '$user_id'";
    $courses_enrolled_res = $conn->query($courses_enrolled_query);
    $enrolled_courses_count = $courses_enrolled_res ? $courses_enrolled_res->fetch_assoc()['count'] : 0;
}

// Global Stats for Landing Sections
$total_students_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$db_student_count = $total_students_res ? (int)$total_students_res->fetch_assoc()['count'] : 0;

$total_teachers_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
$db_teacher_count = $total_teachers_res ? (int)$total_teachers_res->fetch_assoc()['count'] : 0;

$total_courses_res = $conn->query("SELECT COUNT(*) as count FROM teacher_assignments WHERE status = 'active'");
$db_course_count = $total_courses_res ? (int)$total_courses_res->fetch_assoc()['count'] : 0;

// Fetch section and card theme colors
$dashboard_colors = [];
$colors_res = $conn->query("SELECT * FROM dashboard_colors");
if ($colors_res) {
    while ($row = $colors_res->fetch_assoc()) {
        $dashboard_colors[$row['section_key']] = $row;
    }
}

// Fetch Homepage Videos
$desktop_video_url = "https://res.cloudinary.com/dnfbik3if/video/upload/v1785301466/Untitled_design_9_apin9z.mp4";
$mobile_video_url = "https://res.cloudinary.com/dnfbik3if/video/upload/v1785301593/Untitled_design_10_dxgmqw.mp4";

$v_res = $conn->query("SELECT video_type, video_path FROM homepage_videos");
if ($v_res) {
    while ($v_row = $v_res->fetch_assoc()) {
        if ($v_row['video_type'] === 'desktop' && !empty($v_row['video_path'])) {
            $desktop_video_url = $v_row['video_path'];
        }
        if ($v_row['video_type'] === 'mobile' && !empty($v_row['video_path'])) {
            $mobile_video_url = $v_row['video_path'];
        }
    }
}

if (!function_exists('format_html_color')) {
    function format_html_color($color) {
        $color = trim($color);
        if (empty($color)) return '#ffffff';
        if (preg_match('/^[a-fA-F0-9]{3,8}$/', $color)) {
            return '#' . $color;
        }
        return $color;
    }
}

// Get Result Poster settings
$result_poster_desktop = null;
$result_poster_mobile = null;
$poster_res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('result_poster_desktop', 'result_poster_mobile')");
if ($poster_res) {
    while ($p_row = $poster_res->fetch_assoc()) {
        if ($p_row['setting_key'] === 'result_poster_desktop' && !empty($p_row['setting_value'])) {
            $result_poster_desktop = $p_row['setting_value'];
        } elseif ($p_row['setting_key'] === 'result_poster_mobile' && !empty($p_row['setting_value'])) {
            $result_poster_mobile = $p_row['setting_value'];
        }
    }
}

// Get Student Showcase Images settings (Image 1 & Image 2 for Desktop & Mobile)
$showcase_img1_desktop = 'assests/smiling_student.png';
$showcase_img1_mobile  = 'assests/smiling_student.png';
$showcase_img2_desktop = 'assests/student.png';
$showcase_img2_mobile  = 'assests/student.png';

$showcase_res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('showcase_img1_desktop', 'showcase_img1_mobile', 'showcase_img2_desktop', 'showcase_img2_mobile')");
if ($showcase_res) {
    while ($sc_row = $showcase_res->fetch_assoc()) {
        if ($sc_row['setting_key'] === 'showcase_img1_desktop' && !empty($sc_row['setting_value'])) {
            $showcase_img1_desktop = $sc_row['setting_value'];
        } elseif ($sc_row['setting_key'] === 'showcase_img1_mobile' && !empty($sc_row['setting_value'])) {
            $showcase_img1_mobile = $sc_row['setting_value'];
        } elseif ($sc_row['setting_key'] === 'showcase_img2_desktop' && !empty($sc_row['setting_value'])) {
            $showcase_img2_desktop = $sc_row['setting_value'];
        } elseif ($sc_row['setting_key'] === 'showcase_img2_mobile' && !empty($sc_row['setting_value'])) {
            $showcase_img2_mobile = $sc_row['setting_value'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- Primary SEO Metadata -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_logged_in ? 'Dashboard' : 'Welcome'; ?> - Lernerr.LK</title>
    <meta name="description" content="Lernerr.LK is Sri Lanka's leading online Learning Management System (LMS), offering top-tier classes, experienced teachers, and online courses. Join us to elevate your academic success.">
    <meta name="keywords" content="Lernerr.LK, LMS Sri Lanka, online learning, AL classes, education portal Sri Lanka, study online, video lessons, Sinhala classes, Lernerr">
    <meta name="author" content="Lernerr.LK">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'); ?>">
    <meta property="og:title" content="Lernerr.LK - Best Online Learning Platform in Sri Lanka">
    <meta property="og:description" content="Access high-quality classes, video lessons, and interactive learning resources on Lernerr.LK, the leading LMS in Sri Lanka.">
    <meta property="og:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">
    <meta property="og:site_name" content="Lernerr.LK">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'); ?>">
    <meta property="twitter:title" content="Lernerr.LK - Best Online Learning Platform in Sri Lanka">
    <meta property="twitter:description" content="Access high-quality classes, video lessons, and interactive learning resources on Lernerr.LK, the leading LMS in Sri Lanka.">
    <meta property="twitter:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">

    <!-- Canonical Link -->
    <link rel="canonical" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'); ?>">

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assests/favicon-16x16.png">
    <link rel="manifest" href="assests/site.webmanifest">
    <link rel="shortcut icon" href="assests/favicon.ico">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EducationalOrganization",
      "name": "Lernerr.LK",
      "url": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/'; ?>",
      "logo": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>",
      "description": "Lernerr.LK is Sri Lanka's premier online learning management system, providing high-quality courses and learning resources for students.",
      "sameAs": [
        "https://web.facebook.com/lernerrlk",
        "https://www.youtube.com/@sameerapereraofficial",
        "https://www.tiktok.com/@sameerapereraofficial?_r=1&_t=ZS-9A1xZeJEmM0"
      ]
    }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Abhaya+Libre:wght@400;500;600;700;800&family=Gemunu+Libre:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Sans+Tamil:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Modern Design System Tokens */
        :root {
            --primary: #dc2626;
            --primary-light: #f87171;
            --primary-dark: #991b1b;
            --slate-900: #0f172a;
        }

        body {
            font-family: 'Inter', 'Abhaya Libre', 'Gemunu Libre', 'Noto Sans Tamil', sans-serif;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6, .font-black {
            font-family: 'Plus Jakarta Sans', 'Abhaya Libre', 'Gemunu Libre', 'Noto Sans Tamil', sans-serif;
        }

        .font-black {
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        /* Sri Lankan Flag Gradient (Orange, Green, Maroon/Red) */
        .sl-flag-gradient {
            background: linear-gradient(115deg, #ea580c 0%, #16a34a 40%, #881337 75%, #991b1b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
            display: inline-block;
            padding-top: 0.15em;
            padding-bottom: 0.35em;
            line-height: 1.35;
            overflow: visible;
        }

        /* Hero Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.75) 0%, rgba(15, 23, 42, 0.4) 60%, rgba(15, 23, 42, 0.1) 100%);
        }

        .stat-value {
            font-size: 3.5rem;
            font-weight: 900;
            line-height: 1;
            color: #dc2626;
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-count {
            animation: countUp 1s ease-out forwards;
        }

        .section-welcome {
            background-color: #ffffff;
        }

        .section-al {
            background-color: #f1f5f9;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include 'dashboard/navbar.php'; ?>

    <!-- Main Content -->
    <div class="w-full">
        <style>
            * {
                box-sizing: border-box;
            }
        </style>

        <?php if (!$is_logged_in): ?>
            <!-- Redesigned Hero Section with Background Video -->
            <section class="relative w-full h-screen flex flex-col justify-start items-center overflow-hidden pt-16 sm:pt-18 lg:pt-20 pb-4 px-4 text-center bg-white">
                <!-- Desktop Background Video -->
                <video autoplay loop muted playsinline class="hidden lg:block absolute top-0 left-0 w-full h-full object-cover z-0 pointer-events-none">
                    <source src="<?php echo htmlspecialchars($desktop_video_url); ?>" type="video/mp4">
                </video>
                <!-- Mobile Background Video -->
                <video autoplay loop muted playsinline class="block lg:hidden absolute top-0 left-0 w-full h-full object-cover z-0 pointer-events-none">
                    <source src="<?php echo htmlspecialchars($mobile_video_url); ?>" type="video/mp4">
                </video>
 
                <!-- Content Container (Top Aligned) -->
                <div class="max-w-4xl mx-auto relative z-20 w-full flex flex-col items-center animate-fade-in-up">
                    <!--<div class="mb-2 sm:mb-3">-->
                    <!--    <img src="assests/logo.jpeg" alt="LMS Logo" class="h-10 sm:h-12 w-auto object-contain mix-blend-multiply">-->
                    <!--</div>-->

                    <h1 id="hero-greeting" class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight mb-1.5 sm:mb-3 transition-all duration-300 transform inline-block sl-flag-gradient">
                        ආයුබෝවන්!
                    </h1>

                    <div class="text-[11px] sm:text-sm lg:text-[15px] text-slate-800 max-w-2xl mx-auto mb-2.5 sm:mb-4 leading-normal sm:leading-relaxed space-y-1 sm:space-y-1.5 font-semibold px-2">
                        <p>
                            ලංකාවේ සාර්ථකම Online ඇකඩමිය, Lernerr.LK වෙත ඔබව සාදරයෙන් පිළිගන්නවා.<br> 
                            ඔබ දැනටමත් අපගේ කුමන හෝ පාඨමාලාවක්/විෂයක් සඳහා ලියාපදිංචි වී ඇත්නම් ඔබගේ දුරකතන අංකය හා Password එක නිවැරදිව ලබා දී Login වෙන්න.
                        </p>
                        <p class="text-[10px] sm:text-xs text-slate-600 font-medium">
                            If you are a registered student, Please use your Mobile Number and Password.<br>
                            If you are New to here, Please Click 'Register Now'.
                        </p>
                    </div>
 
                    <!-- Sleek Form with Pill Inputs and Buttons -->
                    <form action="auth.php" method="POST" class="w-full max-w-2xl mx-auto px-4 flex flex-col items-center gap-2 sm:gap-3">
                        <!-- Teacher Registration Pending Notice -->
                        <?php if ($teacher_registered): ?>
                            <div class="w-full max-w-md bg-emerald-50 text-emerald-900 px-4 py-3 border border-emerald-200 rounded-2xl flex items-start gap-3 text-left shadow-sm">
                                <i class="fab fa-whatsapp text-emerald-600 text-lg mt-0.5 shrink-0"></i>
                                <div class="text-xs">
                                    <div class="font-extrabold text-emerald-950">Registration Pending Review</div>
                                    <div class="text-emerald-800 text-[11px] mt-0.5 leading-snug">
                                        Your registration is pending approval. Once our team accepts your registration, we will notify you via WhatsApp.
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Error/Success Messages -->
                        <?php if (!empty($error_message)): ?>
                            <div class="w-full max-w-md bg-red-50 text-red-700 px-4 py-2 border border-red-200 rounded-full flex items-center justify-center gap-2 text-xs font-bold shadow-sm">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                                <span><?php echo htmlspecialchars($error_message); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="w-full max-w-md bg-emerald-50 text-emerald-700 px-4 py-2 border border-emerald-200 rounded-full flex items-center justify-center gap-2 text-xs font-bold shadow-sm">
                                <i class="fas fa-check-circle text-emerald-500"></i>
                                <span><?php echo htmlspecialchars($success_message); ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Inputs Row -->
                        <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 w-full justify-center">
                            <!-- Phone Number Input Styled as a Pill -->
                            <div class="relative flex items-center bg-slate-50/90 hover:bg-white border-2 border-slate-300 hover:border-slate-400 rounded-full px-4 py-2 sm:px-4 sm:py-2.5 w-full sm:w-60 focus-within:border-red-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-red-100 transition-all shadow-sm">
                                <i class="fas fa-phone-alt text-slate-500 mr-2.5 text-xs sm:text-sm"></i>
                                <input type="text" name="identifier" required placeholder="Mobile Number" class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-slate-900 font-semibold text-xs sm:text-sm placeholder-slate-500">
                            </div>
                            
                            <!-- Password Input Styled as a Pill -->
                            <div class="relative flex items-center bg-slate-50/90 hover:bg-white border-2 border-slate-300 hover:border-slate-400 rounded-full px-4 py-2 sm:px-4 sm:py-2.5 w-full sm:w-60 focus-within:border-red-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-red-100 transition-all shadow-sm">
                                <i class="fas fa-lock text-slate-500 mr-2.5 text-xs sm:text-sm"></i>
                                <input type="password" name="password" required placeholder="Password" class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-slate-900 font-semibold text-xs sm:text-sm placeholder-slate-500">
                            </div>
                        </div>

                        <!-- Actions Row -->
                        <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 w-full justify-center mt-1">
                            <!-- Login Button (Primary Pill) -->
                            <button type="submit" name="login" class="bg-red-600 hover:bg-red-700 text-white font-bold text-xs sm:text-sm px-6 py-2 sm:px-7 sm:py-2.5 rounded-full transition-all flex items-center justify-center gap-2 w-full sm:w-auto shadow-md hover:shadow-lg hover:scale-[1.02] active:scale-[0.98]">
                                <span>Login Now</span>
                                <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                            
                            <!-- Register Button (Secondary Pill) -->
                            <a href="student_registration" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm px-6 py-2 sm:px-7 sm:py-2.5 rounded-full transition-all flex items-center justify-center gap-2 w-full sm:w-auto shadow-md hover:shadow-lg hover:scale-[1.02] active:scale-[0.98]">
                                <span>Register Now</span>
                            </a>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Stats Section -->
            <section class="py-10 sm:py-14 md:py-20 flex flex-col justify-center bg-gradient-to-r from-emerald-800 via-emerald-700 to-green-800 relative z-20">
                <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-3 gap-2 sm:gap-4 md:gap-8 text-center">
                        <div class="stat-item p-3 sm:p-4">
                            <h2 class="text-3xl sm:text-5xl md:text-8xl font-black text-white tracking-tighter mb-1.5 sm:mb-2"
                                id="student-count">0</h2>
                            <p class="text-[9px] sm:text-[10px] md:text-xs font-black text-emerald-100 uppercase tracking-[0.2em] sm:tracking-[0.3em]">Total
                                Students</p>
                        </div>
                        <div class="stat-item p-3 sm:p-4 border-x border-white/20">
                            <h2 class="text-3xl sm:text-5xl md:text-8xl font-black text-white tracking-tighter mb-1.5 sm:mb-2"
                                id="teacher-count">0</h2>
                            <p class="text-[9px] sm:text-[10px] md:text-xs font-black text-emerald-100 uppercase tracking-[0.2em] sm:tracking-[0.3em]">Expert
                                Teachers</p>
                        </div>
                        <div class="stat-item p-3 sm:p-4">
                            <h2 class="text-3xl sm:text-5xl md:text-8xl font-black text-white tracking-tighter mb-1.5 sm:mb-2"
                                id="course-count">0</h2>
                            <p class="text-[9px] sm:text-[10px] md:text-xs font-black text-emerald-100 uppercase tracking-[0.2em] sm:tracking-[0.3em]">Active
                                Courses</p>
                        </div>
                    </div>
                </div>
            </section>

            <script>
                // Multilingual Rotating Greeting (Ayubowan, Welcome, Wanakkam)
                document.addEventListener('DOMContentLoaded', () => {
                    const greetings = ["ආයුබෝවන්!", "Welcome!", "வணக்கம்!"];
                    let greetingIndex = 0;
                    const greetingEl = document.getElementById("hero-greeting");

                    if (greetingEl) {
                        setInterval(() => {
                            greetingEl.style.opacity = '0';
                            greetingEl.style.transform = 'translateY(-6px)';
                            
                            setTimeout(() => {
                                greetingIndex = (greetingIndex + 1) % greetings.length;
                                greetingEl.textContent = greetings[greetingIndex];
                                greetingEl.style.transform = 'translateY(6px)';
                                
                                setTimeout(() => {
                                    greetingEl.style.opacity = '1';
                                    greetingEl.style.transform = 'translateY(0)';
                                }, 50);
                            }, 250);
                        }, 2000);
                    }
                });

                function animateValue(id, start, end, duration) {
                    const obj = document.getElementById(id);
                    if (!obj) return;
                    if (end === 0) {
                        obj.innerHTML = '0';
                        return;
                    }
                    const startTimestamp = performance.now();
                    const animate = (timestamp) => {
                        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                        const current = Math.floor(progress * (end - start) + start);
                        obj.innerHTML = current;
                        if (progress < 1) {
                            window.requestAnimationFrame(animate);
                        } else {
                            obj.innerHTML = end;
                        }
                    };
                    window.requestAnimationFrame(animate);
                }

                document.addEventListener('DOMContentLoaded', () => {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                animateValue("student-count", 0, <?php echo $db_student_count; ?>, 600);
                                animateValue("teacher-count", 0, <?php echo $db_teacher_count; ?>, 600);
                                animateValue("course-count", 0, <?php echo $db_course_count; ?>, 600);
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.5 });

                    observer.observe(document.querySelector('.stat-item'));
                });
            </script>
        <?php else: ?>
            <!-- Clean White Welcome Banner for Logged In Users -->
            <div class="section-welcome pt-24 sm:pt-28 pb-4 sm:pb-6">
                <div class="max-w-[1400px] mx-auto px-3 sm:px-4 animate-fade-in-up">
                    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm sm:shadow-md border border-slate-200 relative overflow-hidden text-slate-900">
                        
                        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 sm:gap-6">
                            <!-- Left: User Avatar & Greeting -->
                            <div class="flex items-start sm:items-center gap-3 sm:gap-5 w-full md:w-auto">
                                <div class="relative shrink-0 mt-0.5 sm:mt-0">
                                    <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl bg-white border-2 border-red-600 flex items-center justify-center text-red-600 font-extrabold text-lg sm:text-2xl uppercase shadow-xs">
                                        <?php 
                                            $user_fullname = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['second_name'] ?? ''));
                                            echo get_initials($user_fullname ?: 'User'); 
                                        ?>
                                    </div>
                                    <span class="absolute bottom-0 right-0 w-3 h-3 sm:w-3.5 sm:h-3.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 sm:gap-2.5 mb-1 flex-wrap">
                                        <span class="bg-red-600 text-white text-[10px] sm:text-xs font-extrabold uppercase tracking-wide px-2 sm:px-3 py-0.5 sm:py-1 rounded-md sm:rounded-lg shadow-xs">
                                            <?php 
                                                if (in_array($role, ['admin', 'super_admin'])) {
                                                    echo 'Administrator';
                                                } elseif ($role === 'teacher') {
                                                    echo 'Teacher Account';
                                                } elseif ($role === 'instructor') {
                                                    echo 'Instructor Account';
                                                } else {
                                                    echo 'Student Account';
                                                }
                                            ?>
                                        </span>
                                        <span class="text-slate-700 text-[10px] sm:text-xs font-bold bg-slate-100 px-2 sm:px-2.5 py-0.5 rounded-md sm:rounded-lg border border-slate-200">ID: <?php echo htmlspecialchars($user_id); ?></span>
                                    </div>
                                    <h1 class="text-base sm:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 leading-snug break-words">
                                        සුබ දවසක්, <span class="text-red-600"><?php echo htmlspecialchars($user_fullname ?: 'User'); ?>!</span> 👋
                                    </h1>
                                    <p class="text-slate-600 text-xs sm:text-sm mt-0.5 sm:mt-1 font-medium">
                                        <?php if (in_array($role, ['admin', 'super_admin'])): ?>
                                            Lernerr.LK පරිපාලන පද්ධතිය වෙත සාදරයෙන් පිළිගනිමු.
                                        <?php else: ?>
                                            Lernerr.LK වෙත නැවත සාදරයෙන් පිළිගනිමු
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Right: Action Buttons (Red Border, 2-column on mobile, flex on desktop) -->
                            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3 w-full md:w-auto shrink-0 pt-2 sm:pt-0 border-t border-slate-100 md:border-t-0">
                                <?php if (in_array($role, ['admin', 'super_admin'])): ?>
                                    <a href="admin/dashboard.php"
                                       class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2 sm:py-2.5 bg-white hover:bg-red-50 text-red-600 font-extrabold text-[11px] sm:text-xs uppercase tracking-wider rounded-xl border-2 border-red-600 shadow-xs transition-all active:scale-95 text-center">
                                        <i class="fas fa-gauge-high text-xs sm:text-sm"></i>
                                        <span class="truncate">Admin Dashboard</span>
                                    </a>
                                    <a href="dashboard/profile"
                                       class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2 sm:py-2.5 bg-white hover:bg-red-50 text-red-600 font-extrabold text-[11px] sm:text-xs uppercase tracking-wider rounded-xl border-2 border-red-600 shadow-xs transition-all active:scale-95 text-center">
                                        <i class="fas fa-user-circle text-xs sm:text-sm"></i>
                                        <span class="truncate">My Profile</span>
                                    </a>
                                <?php elseif ($role === 'teacher'): ?>
                                    <a href="dashboard/profile"
                                       class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2 sm:py-2.5 bg-white hover:bg-red-50 text-red-600 font-extrabold text-[11px] sm:text-xs uppercase tracking-wider rounded-xl border-2 border-red-600 shadow-xs transition-all active:scale-95 text-center">
                                        <i class="fas fa-user-circle text-xs sm:text-sm"></i>
                                        <span class="truncate">Go to Profile</span>
                                    </a>
                                    <a href="dashboard/live_classes"
                                       class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2 sm:py-2.5 bg-white hover:bg-red-50 text-red-600 font-extrabold text-[11px] sm:text-xs uppercase tracking-wider rounded-xl border-2 border-red-600 shadow-xs transition-all active:scale-95 text-center">
                                        <i class="fas fa-chalkboard-teacher text-xs sm:text-sm"></i>
                                        <span class="truncate">Live Classes</span>
                                    </a>
                                <?php else: ?>
                                    <a href="dashboard/profile"
                                       class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2 sm:py-2.5 bg-white hover:bg-red-50 text-red-600 font-extrabold text-[11px] sm:text-xs uppercase tracking-wider rounded-xl border-2 border-red-600 shadow-xs transition-all active:scale-95 text-center">
                                        <i class="fas fa-user-circle text-xs sm:text-sm"></i>
                                        <span class="truncate">Go to Profile</span>
                                    </a>
                                    <a href="dashboard/recordings"
                                       class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2 sm:py-2.5 bg-white hover:bg-red-50 text-red-600 font-extrabold text-[11px] sm:text-xs uppercase tracking-wider rounded-xl border-2 border-red-600 shadow-xs transition-all active:scale-95 text-center">
                                        <i class="fas fa-book-reader text-xs sm:text-sm"></i>
                                        <span class="truncate">My Lessons</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Result Poster / Marketing Banner Section (16:9 Full Width Edge-to-Edge) -->
        <?php if (!empty($result_poster_desktop) || !empty($result_poster_mobile)): ?>
            <?php 
                $desktop_img = !empty($result_poster_desktop) ? $result_poster_desktop : $result_poster_mobile;
                $mobile_img  = !empty($result_poster_mobile)  ? $result_poster_mobile  : $result_poster_desktop;
            ?>
            <section class="relative w-full overflow-hidden bg-slate-950 z-20 p-0 m-0">
                <a href="dashboard/ALDetails" class="block w-full group relative overflow-hidden">
                    <!-- Desktop Poster Image (16:9 full width) -->
                    <img src="<?php echo htmlspecialchars($desktop_img); ?>" 
                         alt="Marketing Banner" 
                         class="hidden md:block w-full aspect-[16/9] object-cover mx-auto group-hover:scale-[1.005] transition-transform duration-300">
                    <!-- Mobile Poster Image (Facebook Portrait Full Width) -->
                    <img src="<?php echo htmlspecialchars($mobile_img); ?>" 
                         alt="Marketing Banner" 
                         class="block md:hidden w-full h-auto object-cover mx-auto">
                </a>
            </section>
        <?php endif; ?>

    <!-- Social Media Community & Student Showcase Section -->
    <section class="py-8 sm:py-14 md:py-20 bg-gradient-to-b from-slate-50 via-white to-slate-50 text-slate-900 relative overflow-hidden border-t border-slate-200">
        <!-- Desktop Background Image (Children Peeking) -->
        <div class="hidden md:block absolute inset-0 w-full h-full bg-no-repeat pointer-events-none z-0" 
             style="background-image: url('https://res.cloudinary.com/dnfbik3if/image/upload/v1791439520/Children_peeking_from_image_corners_20261008113509_xgmhtl.jpg'); background-size: 100% 100%; background-position: center top;">
        </div>

        <!-- Mobile Background Image (Children Peeking) -->
        <div class="block md:hidden absolute top-0 left-0 right-0 h-64 bg-no-repeat pointer-events-none z-0" 
             style="background-image: url('https://res.cloudinary.com/dnfbik3if/image/upload/v1791439559/Children_peeking_from_image_corners_20261008113552_shjrev.jpg'); background-size: 100% auto; background-position: top center;">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Section Header: Social Channels -->
            <div class="text-center max-w-3xl mx-auto mb-6 sm:mb-10 px-4 pt-32 sm:pt-20 md:pt-0">
                <h2 class="text-xl sm:text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight leading-snug px-4 sm:px-0">
                    අපගේ සමාජ මාධ්‍ය ජාලයන් හා එක්වන්න
                </h2>
                <p class="text-slate-600 text-xs sm:text-base font-medium mt-2 leading-relaxed max-w-xl mx-auto">
                    නවතම පන්ති තොරතුරු, නොමිලේ සම්මන්ත්‍රණ, කෙටි සටහන් සහ විභාග මගපෙන්වීම් ලබාගැනීමට අපගේ නිල පිටු සමඟ එක්වන්න.
                </p>
            </div>

            <!-- Social Media Cards Grid (4 Clean Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-0">
                <!-- YouTube Card -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-red-500 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between items-center text-center group">
                    <div class="flex flex-col items-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-red-50 border border-red-100 flex items-center justify-center text-red-600 mb-3 sm:mb-4 group-hover:scale-110 transition-transform shadow-inner">
                            <i class="fab fa-youtube text-2xl sm:text-3xl"></i>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-0.5">YouTube</h3>
                        <div class="text-xs sm:text-sm font-extrabold text-red-600 mb-1.5">10,000+ Subscribers</div>
                        <p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed mb-4 sm:mb-6 font-medium">
                            Sameera Perera Official<br>නොමිලේ සම්මන්ත්‍රණ සහ වීඩියෝ පාඩම්
                        </p>
                    </div>
                    <a href="https://www.youtube.com/@sameerapereraofficial" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center justify-center gap-2 w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 sm:py-2.5 px-4 rounded-xl text-xs uppercase tracking-wider transition-all shadow-md shadow-red-600/20">
                        <i class="fab fa-youtube text-sm"></i>
                        <span>Subscribe</span>
                    </a>
                </div>

                <!-- Facebook Card -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-blue-500 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between items-center text-center group">
                    <div class="flex flex-col items-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 mb-3 sm:mb-4 group-hover:scale-110 transition-transform shadow-inner">
                            <i class="fab fa-facebook-f text-2xl sm:text-3xl"></i>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-0.5">Facebook</h3>
                        <div class="text-xs sm:text-sm font-extrabold text-blue-600 mb-1.5">Lernerr.LK Official</div>
                        <p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed mb-4 sm:mb-6 font-medium">
                            නිල ෆේස්බුක් පිටුව<br>නවතම පන්ති නිවේදන සහ කාලසටහන්
                        </p>
                    </div>
                    <a href="https://web.facebook.com/lernerrlk" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center justify-center gap-2 w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 sm:py-2.5 px-4 rounded-xl text-xs uppercase tracking-wider transition-all shadow-md shadow-blue-600/20">
                        <i class="fab fa-facebook-f text-sm"></i>
                        <span>Follow Page</span>
                    </a>
                </div>

                <!-- TikTok Card -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-slate-800 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between items-center text-center group">
                    <div class="flex flex-col items-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-900 mb-3 sm:mb-4 group-hover:scale-110 transition-transform shadow-inner">
                            <i class="fab fa-tiktok text-2xl sm:text-3xl"></i>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-0.5">TikTok</h3>
                        <div class="text-xs sm:text-sm font-extrabold text-slate-800 mb-1.5">Shorts & Study Tips</div>
                        <p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed mb-4 sm:mb-6 font-medium">
                            @sameerapereraofficial<br>විභාග කෙටි ක්‍රම සහ Motivation
                        </p>
                    </div>
                    <a href="https://www.tiktok.com/@sameerapereraofficial?_r=1&_t=ZS-9A1xZeJEmM0" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center justify-center gap-2 w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2 sm:py-2.5 px-4 rounded-xl text-xs uppercase tracking-wider transition-all shadow-md shadow-slate-900/20">
                        <i class="fab fa-tiktok text-sm"></i>
                        <span>Follow TikTok</span>
                    </a>
                </div>

                <!-- WhatsApp Card -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-emerald-500 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between items-center text-center group">
                    <div class="flex flex-col items-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 mb-3 sm:mb-4 group-hover:scale-110 transition-transform shadow-inner">
                            <i class="fab fa-whatsapp text-2xl sm:text-3xl"></i>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-0.5">WhatsApp</h3>
                        <div class="text-xs sm:text-sm font-extrabold text-emerald-600 mb-1.5">+94 70 460 7707</div>
                        <p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed mb-4 sm:mb-6 font-medium">
                            ක්ෂණික සහය හා විමසීම්<br>පන්ති සම්බන්ධීකරණය
                        </p>
                    </div>
                    <a href="https://wa.me/94704607707" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center justify-center gap-2 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 sm:py-2.5 px-4 rounded-xl text-xs uppercase tracking-wider transition-all shadow-md shadow-emerald-600/20">
                        <i class="fab fa-whatsapp text-sm"></i>
                        <span>Chat on WhatsApp</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Student Images Showcase Section -->
    <section class="py-2 sm:py-6 md:py-10 bg-white relative overflow-hidden">
        <div class="w-full max-w-[1400px] mx-auto px-0 sm:px-4 md:px-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-6 w-full">
                <!-- Showcase Image 1 -->
                <div class="w-full overflow-hidden sm:rounded-2xl shadow-sm sm:shadow-md">
                    <!-- Desktop Version -->
                    <img src="<?php echo htmlspecialchars($showcase_img1_desktop); ?>" alt="Student Showcase" 
                         class="hidden md:block w-full h-auto object-cover hover:scale-[1.02] transition-transform duration-300">
                    <!-- Mobile Version -->
                    <img src="<?php echo htmlspecialchars($showcase_img1_mobile); ?>" alt="Student Showcase" 
                         class="block md:hidden w-full h-auto object-cover hover:scale-[1.02] transition-transform duration-300">
                </div>
                <!-- Showcase Image 2 -->
                <div class="w-full overflow-hidden sm:rounded-2xl shadow-sm sm:shadow-md">
                    <!-- Desktop Version -->
                    <img src="<?php echo htmlspecialchars($showcase_img2_desktop); ?>" alt="Student Showcase" 
                         class="hidden md:block w-full h-auto object-cover hover:scale-[1.02] transition-transform duration-300">
                    <!-- Mobile Version -->
                    <img src="<?php echo htmlspecialchars($showcase_img2_mobile); ?>" alt="Student Showcase" 
                         class="block md:hidden w-full h-auto object-cover hover:scale-[1.02] transition-transform duration-300">
                </div>
            </div>
        </div>
    </section>

    <?php if (false): // Sections hidden as requested ?>
    <!-- Available Classes Section -->
    <?php 
    $classes_bg_color = format_html_color($dashboard_colors['classes']['bg_color'] ?? 'bg-amber-200/80');
    $classes_bg_style = '';
    $classes_bg_class = 'bg-amber-200/80';
    if (strpos($classes_bg_color, '#') === 0) {
        $classes_bg_style = 'style="background-color: ' . $classes_bg_color . ';"';
        $classes_bg_class = '';
    } else {
        $classes_bg_class = $classes_bg_color;
    }
    ?>
    <div class="section-classes py-12 md:py-24 flex flex-col bg-gradient-to-br from-[#104e35] via-[#145c3f] to-[#0c3c28] relative overflow-hidden" id="classes-section">
        <!-- Abstract Decorative Elements to Match Results section -->
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-emerald-400/10 rounded-full blur-2xl -translate-x-12 translate-y-12 pointer-events-none"></div>
        <div class="absolute top-0 right-0 w-80 h-80 bg-teal-400/20 rounded-full blur-3xl -translate-y-24 translate-x-24 pointer-events-none"></div>
        
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div
                class="py-4 md:py-6 mb-4 md:mb-8 flex flex-col md:flex-row md:items-center justify-between border-b border-white/20 gap-4">
                <div>
                    <h2
                        class="text-xl md:text-3xl font-semibold text-white tracking-normal uppercase border-b-2 border-white pb-2 inline-block">
                        අපගේ ආයතනයෙන් ඔබට හැදෑරිය හැකි විෂයධාරාවන්</h2>
                    <p class="text-white/80 text-[10px] md:text-xs font-semibold mt-4">ලියාපදිංචි වීමට Enroll Now click කරන්න</p>
                </div>

                <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap w-full md:w-auto">
                    <!-- Exam Year (Academic Year) Filter -->
                    <div class="relative flex items-center bg-white rounded-full shadow-sm border border-slate-300 hover:border-slate-400 px-3.5 py-2 transition-all duration-300">
                        <i class="fas fa-calendar-alt text-slate-500 text-xs mr-2"></i>
                        <select id="examYearFilter" onchange="searchAndFilter()" class="bg-transparent border-none outline-none focus:outline-none focus:ring-0 text-xs font-extrabold text-slate-800 p-0 cursor-pointer">
                            <option value="all">All Exam Years</option>
                            <?php foreach ($available_academic_years as $yr): ?>
                                <option value="<?php echo $yr; ?>"><?php echo $yr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cohesive Search Input -->
                    <div class="relative flex items-center bg-white rounded-full shadow-sm border border-slate-300 hover:border-slate-400 px-3.5 py-2 w-full sm:w-64 transition-all duration-300">
                        <i class="fas fa-search text-slate-500 text-xs mr-2"></i>
                        <input type="text" id="classSearch" oninput="searchAndFilter()" placeholder="Search class, subject, teacher..." 
                            class="bg-transparent border-none outline-none focus:outline-none focus:ring-0 w-full text-xs font-semibold text-slate-800 placeholder-slate-500 p-0">
                    </div>
                </div>
            </div>

            <!-- Horizontally scrollable stream filter chips -->
            <style>
                .no-scrollbar::-webkit-scrollbar {
                    display: none;
                }
                .no-scrollbar {
                    -ms-overflow-style: none;
                    scrollbar-width: none;
                }
                @media (max-width: 767px) {
                    .class-card.mobile-hidden, .extra-course-card.mobile-hidden {
                        display: none !important;
                    }
                }
            </style>
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-6 w-full -mx-4 px-4 sm:mx-0 sm:px-0">
                <button onclick="filterStream('all', this)"
                    class="stream-chip whitespace-nowrap px-4 py-2 text-xs font-bold rounded-full transition-all duration-300 shadow-sm bg-white text-slate-900">
                    All Academic Streams
                </button>
                <?php foreach ($assignments_by_stream as $stream_id => $stream_data): ?>
                    <button onclick="filterStream('stream-<?php echo $stream_id; ?>', this)"
                        class="stream-chip whitespace-nowrap px-4 py-2 text-xs font-bold rounded-full transition-all duration-300 shadow-sm bg-white/20 text-white hover:bg-white/30 border border-white/20">
                        <?php echo htmlspecialchars($stream_data['stream_name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if (empty($assignments_by_stream)): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center w-full">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 mb-4">
                        <i class="fas fa-book-open text-slate-300 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-900">No classes available yet</h3>
                    <p class="text-slate-500 mt-2 text-xs">Check back later for new academic subjects.</p>
                </div>
            <?php else: ?>
                <?php
                $card_count = 0;
                $last_color = '';
                $tile_colors_str = $dashboard_colors['classes']['card_colors'] ?? 'bg-blue-100,bg-emerald-100,bg-violet-100,bg-amber-100,bg-rose-100,bg-cyan-100,bg-indigo-100,bg-orange-100,bg-red-100,bg-teal-100,bg-sky-100,bg-fuchsia-100,bg-pink-100,bg-lime-100,bg-yellow-100,bg-purple-100';
                $tile_colors = array_filter(array_map('trim', explode(',', $tile_colors_str)));
                if (empty($tile_colors)) {
                    $tile_colors = ['#ffffff'];
                }
                ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($assignments_by_stream as $stream_id => $stream_data):
                        ?>
                        <?php foreach ($stream_data['classes'] as $class):
                            $card_count++;
                            $isMobileHidden = ($card_count > 3 && $card_count <= 8);
                            $isHidden = $card_count > 8;
                            do {
                                $random_bg = $tile_colors[array_rand($tile_colors)];
                            } while ($random_bg === $last_color && count($tile_colors) > 1);
                            $last_color = $random_bg;
                            
                            $card_bg_color = format_html_color($random_bg);
                            $card_bg_style = '';
                            $card_bg_class = '';
                            if (strpos($card_bg_color, '#') === 0) {
                                $card_bg_style = 'background-color: ' . $card_bg_color . ';';
                            } else {
                                $card_bg_class = $card_bg_color;
                            }

                            $style_tags = [];
                            if ($isHidden) {
                                $style_tags[] = 'display: none;';
                            }
                            $style_attr = !empty($style_tags) ? 'style="' . implode(' ', $style_tags) . '"' : '';
                            ?>
                            <div <?php echo $style_attr; ?> 
                                data-subject="<?php echo htmlspecialchars($class['subject_name'], ENT_QUOTES); ?>" 
                                data-teacher="<?php echo htmlspecialchars($class['teacher_name'], ENT_QUOTES); ?>" 
                                data-batch="<?php echo htmlspecialchars($class['batch_name'] ?? '', ENT_QUOTES); ?>"
                                data-year="<?php echo htmlspecialchars($class['academic_year'] ?? '', ENT_QUOTES); ?>"
                                class="bg-white rounded-none shadow-md hover:shadow-2xl hover:z-20 transform hover:scale-[1.03] transition-all duration-300 overflow-hidden border border-slate-900/5 flex flex-col class-card stream-<?php echo $stream_id; ?> <?php echo $isHidden ? 'hidden-card' : ''; ?> <?php echo $isMobileHidden ? 'mobile-hidden' : ''; ?>">
                                <!-- Cover Image with Facebook Post Aspect Ratio (1.91:1) -->
                                <div class="relative aspect-[1.91/1] w-full overflow-hidden border-b border-slate-900/5 bg-white">
                                    <?php if ($class['cover_image']): ?>
                                        <img src="<?php echo htmlspecialchars($class['cover_image']); ?>"
                                            alt="<?php echo htmlspecialchars($class['batch_name'] ?: $class['subject_name']); ?>"
                                            class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br <?php echo get_fallback_gradient($class['subject_name']); ?> px-4 text-center">
                                            <span class="text-sm md:text-base font-black text-white leading-tight drop-shadow-md select-none"><?php echo htmlspecialchars($class['batch_name'] ?: $class['subject_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div
                                        class="absolute top-3 left-3 bg-slate-900/90 backdrop-blur-md px-2.5 py-1 text-[10px] font-extrabold text-white uppercase tracking-wider rounded-md shadow-sm">
                                        <?php echo htmlspecialchars($stream_data['stream_name']); ?>
                                    </div>
                                </div>

                                <div class="p-5 flex-1 flex flex-col justify-between">
                                    <div>
                                        <!-- Name of the Class / Enroll Name -->
                                        <h3 class="text-base md:text-lg font-black text-slate-900 mb-1.5 leading-snug truncate"
                                            title="<?php echo htmlspecialchars($class['batch_name'] ?: $class['subject_name']); ?>">
                                            <?php echo htmlspecialchars($class['batch_name'] ?: $class['subject_name']); ?>
                                        </h3>

                                        <!-- Subject Name - Year -->
                                        <div class="mb-3">
                                            <span class="text-xs font-extrabold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-100/80 inline-block truncate max-w-full">
                                                <i class="fas fa-book text-[10px] mr-1 text-blue-500"></i>
                                                <?php echo htmlspecialchars($class['subject_name']); ?><?php echo !empty($class['academic_year']) ? ' - ' . htmlspecialchars($class['academic_year']) : ''; ?>
                                            </span>
                                        </div>

                                        <!-- Instructor Row (Highlighted) -->
                                        <div class="flex items-center p-2.5 bg-slate-50/80 rounded-xl border border-slate-200/60 mb-4 shadow-xs">
                                            <?php if ($class['teacher_image']): ?>
                                                <img src="<?php echo htmlspecialchars($class['teacher_image']); ?>"
                                                    class="w-10 h-10 rounded-full ring-2 ring-blue-500/30 object-cover mr-3 shrink-0 shadow-sm">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-slate-800 to-slate-700 text-white flex items-center justify-center mr-3 shrink-0 shadow-sm">
                                                    <i class="fas fa-user-tie text-xs"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex flex-col justify-center min-w-0">
                                                <span class="text-[9px] font-extrabold text-blue-600 uppercase tracking-wider block leading-none mb-0.5">Teacher</span>
                                                <p class="text-sm font-black text-slate-900 leading-tight truncate">
                                                    <?php echo htmlspecialchars($class['teacher_name']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Fees or Payment Status -->
                                        <?php
                                        $enrolled_data = $user_enrollment_data[$class['stream_subject_id']] ?? null;
                                        if ($enrolled_data):
                                            ?>
                                            <div class="grid grid-cols-2 gap-2 mb-4">
                                                <!-- Enrollment Status -->
                                                <div
                                                    class="bg-white rounded-none p-2 text-center border border-slate-900/5 shadow-sm">
                                                    <p class="text-[8px] text-slate-400 uppercase tracking-widest font-bold mb-0.5">
                                                        Enrollment</p>
                                                    <p
                                                        class="text-xs font-bold <?php echo $enrolled_data['enrollment_paid'] ? 'text-green-600' : (isset($enrolled_data['enrollment_status']) && $enrolled_data['enrollment_status'] == 'Pending' ? 'text-yellow-600' : 'text-blue-500'); ?>">
                                                        <?php
                                                        if (isset($enrolled_data['enrollment_status'])) {
                                                            echo $enrolled_data['enrollment_status'] == 'not_paid' ? 'Unpaid' : $enrolled_data['enrollment_status'];
                                                        } else {
                                                            echo 'Unpaid';
                                                        }
                                                        ?>
                                                    </p>
                                                </div>

                                                <!-- Monthly Status -->
                                                <div
                                                    class="bg-white rounded-none p-2 text-center border border-slate-900/5 shadow-sm">
                                                    <p class="text-[8px] text-slate-400 uppercase tracking-widest font-bold mb-0.5">
                                                        <?php echo date('F'); ?>
                                                    </p>
                                                    <p
                                                        class="text-xs font-bold <?php echo $enrolled_data['monthly_status'] == 'Paid' ? 'text-green-600' : ($enrolled_data['monthly_status'] == 'Pending' ? 'text-yellow-600' : 'text-blue-500'); ?>">
                                                        <?php
                                                        if ($enrolled_data['monthly_status'] == 'not_paid')
                                                            echo 'Unpaid';
                                                        else
                                                            echo $enrolled_data['monthly_status'];
                                                        ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="grid grid-cols-2 gap-3 mb-4">
                                                <div class="bg-white rounded-none p-2.5 text-center border border-slate-900/5 shadow-sm">
                                                    <p class="text-[8px] text-slate-400 uppercase tracking-widest font-bold mb-0.5">
                                                        Enrollment</p>
                                                    <p class="text-base font-black text-slate-900">
                                                        <?php echo $class['enrollment_fee'] > 0 ? number_format($class['enrollment_fee']) : 'Free'; ?>
                                                    </p>
                                                </div>
                                                <div class="bg-white rounded-none p-2.5 text-center border border-slate-900/5 shadow-sm">
                                                    <p class="text-[8px] text-slate-400 uppercase tracking-widest font-bold mb-0.5">Monthly
                                                    </p>
                                                    <p class="text-base font-black text-slate-900">
                                                        <?php echo $class['monthly_fee'] > 0 ? number_format($class['monthly_fee']) : 'Free'; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($is_logged_in): ?>
                                            <?php if ($enrolled_data): ?>
                                                <a href="dashboard/recordings"
                                                    class="block w-full text-center bg-slate-900 text-white py-2.5 px-4 rounded-none hover:bg-slate-800 transition duration-200 text-[10px] font-bold uppercase tracking-wider active:scale-95 shadow-md">
                                                    View Details
                                                </a>
                                            <?php else: ?>
                                                <button
                                                    onclick="openEnrollModal(<?php echo $class['stream_subject_id']; ?>, '<?php echo htmlspecialchars($class['subject_name'], ENT_QUOTES); ?>')"
                                                    class="block w-full text-center bg-slate-900 text-white py-2.5 px-4 rounded-none hover:bg-slate-800 transition duration-200 text-[10px] font-bold uppercase tracking-wider active:scale-95 shadow-md">
                                                    Enroll Now
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="student_registration?stream_id=<?php echo $stream_id; ?>&subject_id=<?php echo $class['subject_id']; ?>"
                                                class="block w-full text-center bg-slate-900 text-white py-2.5 px-4 rounded-none hover:bg-slate-800 transition duration-200 text-[10px] font-bold uppercase tracking-wider active:scale-95 shadow-md">
                                                Enroll Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($card_count > 3): ?>
                <div class="mt-20 text-center <?php echo ($card_count > 8) ? 'block' : 'block md:hidden'; ?>" id="viewMoreContainer">
                    <button onclick="showAllClasses()"
                        class="inline-flex items-center gap-3 bg-slate-900 text-white px-8 py-4 font-medium text-xs uppercase tracking-widest hover:bg-slate-800 hover:scale-105 active:scale-95 transition-all shadow-lg shadow-slate-900/20 group rounded-none">
                        <span>පවතින සියලුම විෂයන් බලන්න</span>
                        <i class="fas fa-arrow-down text-[10px] group-hover:translate-y-1 transition-transform"></i>
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div> <!-- Inner container ends -->

    <script>
        function showAllClasses() {
            const hiddenCards = document.querySelectorAll('.class-card.hidden-card, .class-card.mobile-hidden');
            hiddenCards.forEach(card => {
                card.classList.remove('hidden-card');
                card.classList.remove('mobile-hidden');
                card.style.display = 'flex';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease-in-out';
                    card.style.opacity = '1';
                }, 10);
            });
            document.getElementById('viewMoreContainer').style.setProperty('display', 'none', 'important');
        }

        function showAllExtraCourses() {
            const hiddenCards = document.querySelectorAll('.extra-course-card.hidden-course, .extra-course-card.mobile-hidden');
            hiddenCards.forEach(card => {
                card.classList.remove('hidden-course');
                card.classList.remove('mobile-hidden');
                card.style.display = 'flex';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease-in-out';
                    card.style.opacity = '1';
                }, 10);
            });
            document.getElementById('viewMoreExtraContainer').style.setProperty('display', 'none', 'important');
        }

        function searchAndFilterExtra() {
            const searchQuery = document.getElementById('extraCourseSearch').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.extra-course-card');
            const viewMoreBtn = document.getElementById('viewMoreExtraContainer');
            const isMobile = window.innerWidth < 768;

            cards.forEach(card => {
                const title = (card.getAttribute('data-title') || '').toLowerCase();
                const teacher = (card.getAttribute('data-teacher') || '').toLowerCase();

                const matchesSearch = !searchQuery || title.includes(searchQuery) || teacher.includes(searchQuery);

                if (matchesSearch) {
                    if (searchQuery === '') {
                        if (card.classList.contains('hidden-course') || (isMobile && card.classList.contains('mobile-hidden'))) {
                            card.style.setProperty('display', 'none', 'important');
                        } else {
                            card.style.display = 'flex';
                        }
                    } else {
                        card.style.display = 'flex';
                    }
                    card.style.opacity = '1';
                } else {
                    card.style.setProperty('display', 'none', 'important');
                }
            });

            if (viewMoreBtn) {
                if (searchQuery === '') {
                    let hasHidden = false;
                    cards.forEach(card => {
                        if ((card.classList.contains('hidden-course') || (isMobile && card.classList.contains('mobile-hidden'))) && card.style.display === 'none') {
                            hasHidden = true;
                        }
                    });
                    viewMoreBtn.style.display = hasHidden ? 'block' : 'none';
                } else {
                    viewMoreBtn.style.display = 'none';
                }
            }
        }

        let activeStream = 'all';

        function filterStream(streamValue, buttonEl) {
            activeStream = streamValue;

            const chips = document.querySelectorAll('.stream-chip');
            chips.forEach(chip => {
                chip.className = "stream-chip whitespace-nowrap px-4 py-2 text-xs font-bold rounded-none transition-all duration-300 shadow-sm bg-white/20 text-white hover:bg-white/30 border border-white/20";
            });

            if (buttonEl) {
                buttonEl.className = "stream-chip whitespace-nowrap px-4 py-2 text-xs font-bold rounded-none transition-all duration-300 shadow-sm bg-white text-slate-900";
            }

            searchAndFilter();
        }

        function searchAndFilter() {
            const searchQuery = document.getElementById('classSearch').value.toLowerCase().trim();
            const streamClass = activeStream;
            const yearElem = document.getElementById('examYearFilter');
            const selectedYear = yearElem ? yearElem.value : 'all';
            const cards = document.querySelectorAll('.class-card');
            const viewMoreBtn = document.getElementById('viewMoreContainer');
            const isMobile = window.innerWidth < 768;

            cards.forEach(card => {
                const subject = (card.getAttribute('data-subject') || '').toLowerCase();
                const teacher = (card.getAttribute('data-teacher') || '').toLowerCase();
                const batch = (card.getAttribute('data-batch') || '').toLowerCase();
                const cardYear = card.getAttribute('data-year') || '';
                
                const matchesStream = (streamClass === 'all') || card.classList.contains(streamClass);
                const matchesYear = (selectedYear === 'all') || (cardYear === selectedYear);
                const matchesSearch = !searchQuery || subject.includes(searchQuery) || teacher.includes(searchQuery) || batch.includes(searchQuery);

                if (matchesStream && matchesSearch && matchesYear) {
                    if (searchQuery === '' && streamClass === 'all' && selectedYear === 'all') {
                        if (card.classList.contains('hidden-card') || (isMobile && card.classList.contains('mobile-hidden'))) {
                            card.style.setProperty('display', 'none', 'important');
                        } else {
                            card.style.display = 'flex';
                        }
                    } else {
                        card.style.display = 'flex';
                    }
                    card.style.opacity = '1';
                } else {
                    card.style.setProperty('display', 'none', 'important');
                }
            });

            if (viewMoreBtn) {
                if (searchQuery === '' && streamClass === 'all' && selectedYear === 'all') {
                    let hasHidden = false;
                    cards.forEach(card => {
                        if ((card.classList.contains('hidden-card') || (isMobile && card.classList.contains('mobile-hidden'))) && card.style.display === 'none') {
                            hasHidden = true;
                        }
                    });
                    viewMoreBtn.style.display = hasHidden ? 'block' : 'none';
                } else {
                    viewMoreBtn.style.display = 'none';
                }
            }
        }
    </script>
    </div> <!-- End of Classes Section -->

    <!-- Extra Courses Section -->
    <div class="section-extra py-12 md:py-24 flex flex-col bg-slate-50" id="extra-courses-section">
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="py-4 md:py-6 mb-4 md:mb-8 flex flex-col md:flex-row md:items-center justify-between border-b border-slate-200 gap-4">
                <div>
                    <h2
                        class="text-xl md:text-3xl font-semibold text-slate-900 tracking-normal uppercase border-b-2 border-slate-900 pb-2 inline-block">
                        අපගේ ආයතනයෙන් හැදෑරිය හැකි බාහිර පාඨමාලාවන්</h2>
                    <p class="text-slate-700 text-[10px] md:text-xs font-semibold mt-4">නවීන තාක්ෂණය හා බාහිර දැනුම ලබා ගැනීමට එක්වන්න</p>
                </div>
                
                <div class="flex items-center w-full md:w-auto">
                    <!-- Cohesive Search Input for Extra Courses -->
                    <div class="relative flex items-center bg-white rounded-none shadow-sm border border-slate-300 hover:border-slate-400 px-3.5 py-2 w-full sm:w-64 transition-all duration-300">
                        <i class="fas fa-search text-slate-500 text-xs mr-2"></i>
                        <input type="text" id="extraCourseSearch" oninput="searchAndFilterExtra()" placeholder="Search extra course..." 
                            class="bg-transparent border-none outline-none focus:outline-none focus:ring-0 w-full text-xs font-semibold text-slate-800 placeholder-slate-500 p-0">
                    </div>
                </div>
            </div>
            <?php if (empty($courses)): ?>
                <div class="bg-white rounded-none shadow p-8 text-center">
                    <p class="text-gray-500">No courses available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php
                    $last_course_color = '';
                    $course_count = 0;
                    $extra_tile_colors_str = $dashboard_colors['extra_courses']['card_colors'] ?? 'bg-blue-100,bg-emerald-100,bg-violet-100,bg-amber-100,bg-rose-100,bg-cyan-100,bg-indigo-100,bg-orange-100,bg-teal-100,bg-sky-100,bg-pink-100,bg-purple-100';
                    $extra_tile_colors = array_filter(array_map('trim', explode(',', $extra_tile_colors_str)));
                    if (empty($extra_tile_colors)) {
                        $extra_tile_colors = ['#ffffff'];
                    }

                    foreach ($courses as $course):
                        $course_count++;
                        $isCourseMobileHidden = ($course_count > 3 && $course_count <= 8);
                        $isCourseHidden = $course_count > 8;
                        do {
                            $random_course_bg = $extra_tile_colors[array_rand($extra_tile_colors)];
                        } while ($random_course_bg === $last_course_color && count($extra_tile_colors) > 1);
                        $last_course_color = $random_course_bg;
                        
                        $card_bg_color = format_html_color($random_course_bg);
                        $card_bg_style = '';
                        $card_bg_class = '';
                        if (strpos($card_bg_color, '#') === 0) {
                            $card_bg_style = 'background-color: ' . $card_bg_color . ';';
                        } else {
                            $card_bg_class = $card_bg_color;
                        }

                        $style_tags = [];
                        if ($isCourseHidden) {
                            $style_tags[] = 'display: none;';
                        }
                        $style_attr = !empty($style_tags) ? 'style="' . implode(' ', $style_tags) . '"' : '';
                        ?>
                        <div <?php echo $style_attr; ?> 
                            data-title="<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>"
                            data-teacher="<?php echo htmlspecialchars($course['teacher_name'], ENT_QUOTES); ?>"
                            class="bg-white rounded-none shadow-md hover:shadow-2xl hover:z-20 transform hover:scale-[1.03] transition-all duration-300 overflow-hidden border border-slate-200 flex flex-col extra-course-card <?php echo $isCourseHidden ? 'hidden-course' : ''; ?> <?php echo $isCourseMobileHidden ? 'mobile-hidden' : ''; ?>">
                            <!-- Course Cover Image with Facebook Post Aspect Ratio (1.91:1) -->
                            <div class="relative aspect-[1.91/1] w-full overflow-hidden border-b border-slate-900/5 bg-white">
                                <?php if ($course['cover_image']): ?>
                                    <img src="<?php echo htmlspecialchars($course['cover_image']); ?>"
                                        alt="<?php echo htmlspecialchars($course['title']); ?>"
                                        class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br <?php echo get_fallback_gradient($course['title']); ?> px-4 text-center">
                                        <span class="text-sm md:text-base font-black text-white leading-tight drop-shadow-md select-none"><?php echo htmlspecialchars($course['title']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md px-3 py-1 text-[9px] font-extrabold text-white uppercase tracking-wider">
                                    Extra Course
                                </div>
                            </div>

                            <!-- Course Content -->
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900 mb-3 leading-tight truncate"
                                        title="<?php echo htmlspecialchars($course['title']); ?>">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </h3>

                                    <div class="grid grid-cols-2 gap-3 mb-4">
                                        <div class="bg-white rounded-none p-2.5 text-center border border-slate-900/5 shadow-sm">
                                            <p class="text-[8px] text-slate-400 uppercase tracking-widest font-bold mb-0.5">Course Fee</p>
                                            <p class="text-base font-black text-slate-900">
                                                Rs. <?php echo number_format($course['price']); ?>
                                            </p>
                                        </div>
                                        <div class="bg-white rounded-none p-2.5 text-center border border-slate-900/5 shadow-sm">
                                            <p class="text-[8px] text-slate-400 uppercase tracking-widest font-bold mb-0.5">Duration</p>
                                            <p class="text-base font-black text-slate-900 truncate" title="<?php echo htmlspecialchars($course['duration'] ?: 'N/A'); ?>">
                                                <?php echo htmlspecialchars($course['duration'] ?: 'N/A'); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Instructor Row -->
                                    <div class="flex items-center mb-4">
                                        <div class="w-9 h-9 rounded-none bg-slate-900/5 flex items-center justify-center border border-slate-900/10 mr-3">
                                            <i class="fas fa-user text-[10px] text-slate-400"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <p class="text-xs font-bold text-slate-900 leading-none">
                                                <?php echo htmlspecialchars($course['teacher_name'] ?: 'Unknown'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="grid grid-cols-1 mb-4">
                                        <div class="bg-white rounded-xl p-2.5 text-center border border-slate-900/5 shadow-sm">
                                            <p class="text-[8px] text-slate-400 uppercase tracking-widest font-bold mb-0.5">Course Fee</p>
                                            <p class="text-base font-black text-slate-900">
                                                Rs. <?php echo number_format($course['price'], 2); ?>
                                            </p>
                                        </div>
                                    </div>

                                     <a href="student_registration?course_id=<?php echo $course['id']; ?>"
                                        class="block w-full text-center bg-slate-900 text-white py-2.5 px-4 rounded-full hover:bg-slate-800 transition duration-200 text-[10px] font-bold uppercase tracking-wider active:scale-95 shadow-md">
                                        <i class="fas fa-cart-plus mr-1"></i>Enroll Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($course_count > 3): ?>
                    <div class="mt-20 text-center <?php echo ($course_count > 8) ? 'block' : 'block md:hidden'; ?>" id="viewMoreExtraContainer">
                        <button onclick="showAllExtraCourses()"
                            class="inline-flex items-center gap-3 bg-slate-900 text-white px-8 py-4 font-medium text-xs uppercase tracking-widest hover:bg-slate-800 hover:scale-105 active:scale-95 transition-all shadow-lg shadow-slate-900/20 group rounded-none">
                            <span>සියලුම බාහිර පාඨමාලා බලන්න</span>
                            <i class="fas fa-arrow-down text-[10px] group-hover:translate-y-1 transition-transform"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; // End of hidden sections ?>

    <!-- Footer Section -->
    <footer class="bg-red-600 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="mb-6">
                <div class="inline-block bg-white rounded-xl px-6 py-3 shadow-sm mb-4">
                    <img src="assests/logo.jpeg" alt="LMS Logo" class="h-14 w-auto object-contain">
                </div>
                <div class="h-0.5 w-16 bg-white/30 mx-auto rounded-full"></div>
            </div>

            <div class="space-y-2 max-w-3xl mx-auto">
                <p class="text-base md:text-lg font-bold text-white leading-relaxed">
                    Lernerr.LK යනු ඔබට ගුණාත්මක Online අධ්‍යාපනයක් ලබාගත හැකි හොඳම ආයතනයයි.<br>
                    සෑම මොහොතකම ඉගෙනීමට යමක් සම්පාදනය කිරීමට අපි කැපවීමෙන් කටයුතු කරන්නෙමු. ❤️
                </p>
                <p class="text-xs md:text-sm font-semibold text-red-100 tracking-wide">
                    Lernerr.LK is the Finest Online Academy in Sri Lanka. Enjoy ❤️
                </p>
            </div>

            <div
                class="mt-8 pt-8 border-t border-red-500/30 flex flex-col md:flex-row justify-between items-center gap-6">
                <p class="text-xs font-bold text-red-100 uppercase tracking-widest order-2 md:order-1">
                    &copy; <?php echo date('Y'); ?> Lernerr.LK. All rights reserved.
                </p>
                <div class="flex flex-col sm:flex-row items-center gap-3.5 order-1 md:order-2">
                    <span class="text-xs font-extrabold uppercase tracking-widest text-red-100">Follow Us On:</span>
                    <div class="flex items-center space-x-3">
                        <a href="https://web.facebook.com/lernerrlk" target="_blank" rel="noopener noreferrer" 
                           class="w-10 h-10 rounded-full bg-white/15 hover:bg-white text-white hover:text-blue-600 flex items-center justify-center transition-all duration-300 shadow-sm hover:scale-110" title="Facebook">
                            <i class="fab fa-facebook-f text-base"></i>
                        </a>
                        <a href="https://www.youtube.com/@sameerapereraofficial" target="_blank" rel="noopener noreferrer" 
                           class="w-10 h-10 rounded-full bg-white/15 hover:bg-white text-white hover:text-red-600 flex items-center justify-center transition-all duration-300 shadow-sm hover:scale-110" title="YouTube">
                            <i class="fab fa-youtube text-base"></i>
                        </a>
                        <a href="https://www.tiktok.com/@sameerapereraofficial?_r=1&_t=ZS-9A1xZeJEmM0" target="_blank" rel="noopener noreferrer" 
                           class="w-10 h-10 rounded-full bg-white/15 hover:bg-white text-white hover:text-slate-900 flex items-center justify-center transition-all duration-300 shadow-sm hover:scale-110" title="TikTok">
                            <i class="fab fa-tiktok text-base"></i>
                        </a>
                        <a href="https://wa.me/94704607707" target="_blank" rel="noopener noreferrer" 
                           class="w-10 h-10 rounded-full bg-white/15 hover:bg-white text-white hover:text-emerald-600 flex items-center justify-center transition-all duration-300 shadow-sm hover:scale-110" title="WhatsApp">
                            <i class="fab fa-whatsapp text-base"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Login/Register Popup for Navigation Clicks -->
    <div id="authModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-75 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl p-10 max-w-md w-full mx-4 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-lock text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-black text-gray-900 mb-2">Please Login or Register First</h3>
            <p class="text-gray-600 mb-8 text-sm font-medium">කරුණාකර පළමුව ඇතුළු වන්න (Login) හෝ ලියාපදිංචි වන්න
                (Register)</p>

            <div class="space-y-4">
                <a href="#login-section" onclick="closeAuthModal(); scrollToLogin();"
                    class="block w-full bg-slate-900 text-white py-4 px-6 rounded-xl hover:bg-slate-800 font-bold transition-all transform active:scale-95 shadow-lg">
                    ඇතුළු වන්න (Login)
                </a>
                <a href="student_registration"
                    class="block w-full bg-gray-100 text-gray-700 py-4 px-6 rounded-xl hover:bg-gray-200 font-bold transition-all transform active:scale-95">
                    ලියාපදිංචි වන්න (Register)
                </a>
            </div>
            <button onclick="closeAuthModal()"
                class="mt-8 text-sm font-bold text-gray-400 hover:text-red-600 transition-colors uppercase tracking-widest">
                Cancel
            </button>
        </div>
    </div>

    <div id="enrollModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full mx-4 transform transition-all scale-100">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                    <i class="fas fa-question text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Confirm Enrollment</h3>
                <p class="text-gray-500 mb-8">Are you sure you want to enroll in user <span id="enrollSubjectName"
                        class="font-bold text-gray-800"></span>?</p>

                <div class="flex space-x-4">
                    <button onclick="closeEnrollModal()"
                        class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-semibold transition-colors">
                        Cancel
                    </button>
                    <button onclick="processEnrollment()"
                        class="flex-1 px-4 py-3 bg-slate-900 text-white rounded-xl hover:bg-slate-800 font-semibold shadow-lg transition-colors">
                        Yes, Enroll
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="enrollToast"
        class="hidden fixed bottom-5 right-5 z-50 transform transition-all duration-300 translate-y-20 opacity-0">
        <div class="bg-gray-800 text-white px-6 py-4 rounded-lg shadow-xl flex items-center">
            <div id="toastIcon" class="mr-3"></div>
            <div id="toastMessage"></div>
        </div>
    </div>

    <script>
        let selectedStreamSubjectId = null;

        function openEnrollModal(id, name) {
            selectedStreamSubjectId = id;
            document.getElementById('enrollSubjectName').textContent = name;
            document.getElementById('enrollModal').classList.remove('hidden');
        }

        function closeEnrollModal() {
            document.getElementById('enrollModal').classList.add('hidden');
            selectedStreamSubjectId = null;
        }

        function showToast(message, isSuccess = true) {
            const toast = document.getElementById('enrollToast');
            const icon = document.getElementById('toastIcon');
            const msg = document.getElementById('toastMessage');

            icon.innerHTML = isSuccess ? '<i class="fas fa-check-circle text-green-400 text-xl"></i>' : '<i class="fas fa-exclamation-circle text-red-400 text-xl"></i>';
            msg.textContent = message;

            toast.classList.remove('hidden', 'translate-y-20', 'opacity-0');

            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
                setTimeout(() => toast.classList.add('hidden'), 300);
            }, 3000);
        }

        function processEnrollment() {
            if (!selectedStreamSubjectId) return;

            const formData = new FormData();
            formData.append('enroll', '1');
            formData.append('stream_subject_id', selectedStreamSubjectId);
            formData.append('academic_year', new Date().getFullYear());

            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            fetch('dashboard/enroll.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    closeEnrollModal();
                    if (data.success) {
                        showToast('Enrollment successful!', true);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message || 'Enrollment failed', false);
                    }
                })
                .catch(error => {
                    closeEnrollModal();
                    showToast('An error occurred. Please try again.', false);
                    console.error('Error:', error);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }

        <?php if ($is_logged_in): ?>
        // Automatic live session checker for homepage
        const checkHomeSessionUrl = 'check_active_session.php';
        function verifyHomeLiveSession() {
            fetch(checkHomeSessionUrl, { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    if (data && data.logged_in === false && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    }
                })
                .catch(() => {});
        }
        setInterval(verifyHomeLiveSession, 4000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                verifyHomeLiveSession();
            }
        });
        <?php endif; ?>
    </script>
</body>

</html>
