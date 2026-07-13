<?php
require_once __DIR__ . '/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$is_logged_in = !empty($user_id);

// Super admin doesn't have a dashboard, redirect to payments
if ($is_logged_in) {
    if ($role === 'super_admin') {
        header('Location: admin/teacher_payments.php');
        exit;
    }
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    }
}

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
        $assignments_by_stream[$stream_id]['classes'][] = $row;
    }
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
$db_student_count = $total_students_res ? $total_students_res->fetch_assoc()['count'] : 0;

$total_teachers_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
$db_teacher_count = $total_teachers_res ? $total_teachers_res->fetch_assoc()['count'] : 0;

$total_courses_res = $conn->query("SELECT COUNT(*) as count FROM courses");
$db_course_count = $total_courses_res ? $total_courses_res->fetch_assoc()['count'] : 0;

// Fetch section and card theme colors
$dashboard_colors = [];
$colors_res = $conn->query("SELECT * FROM dashboard_colors");
if ($colors_res) {
    while ($row = $colors_res->fetch_assoc()) {
        $dashboard_colors[$row['section_key']] = $row;
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
    <title><?php echo $is_logged_in ? 'Dashboard' : 'Welcome'; ?> - Learner.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Sans+Sinhala:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            font-family: 'Inter', 'Noto Sans Sinhala', sans-serif;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6, .font-black {
            font-family: 'Plus Jakarta Sans', 'Noto Sans Sinhala', sans-serif;
        }

        .font-black {
            font-weight: 800;
            letter-spacing: -0.01em;
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
            <section class="relative w-full h-screen flex flex-col justify-start items-center overflow-hidden pt-16 sm:pt-20 lg:pt-28 pb-4 px-4 text-center bg-white">
                <!-- Desktop Background Video -->
                <video autoplay loop muted playsinline class="hidden lg:block absolute top-0 left-0 w-full h-full object-cover z-0 pointer-events-none">
                    <source src="https://res.cloudinary.com/dnfbik3if/video/upload/v1783682874/Animated_face_with_changing_expr__202607101654_c32ud3.mp4" type="video/mp4">
                </video>
                <!-- Mobile Background Video -->
                <video autoplay loop muted playsinline class="block lg:hidden absolute top-0 left-0 w-full h-full object-cover z-0 pointer-events-none">
                    <source src="https://res.cloudinary.com/dnfbik3if/video/upload/v1783682874/Animated_blinking_smiling_face_202607101656_iooai6.mp4" type="video/mp4">
                </video>
 
                <!-- Content Container (Top Aligned) -->
                <div class="max-w-4xl mx-auto relative z-20 w-full flex flex-col items-center animate-fade-in-up">
                    <div class="inline-block bg-red-600 text-white px-4 py-1.5 text-[10px] font-extrabold uppercase tracking-[0.25em] mb-2 sm:mb-4 shadow-sm rounded-full">
                        Learner.LK
                    </div>

                    <h1 class="text-2xl sm:text-4xl lg:text-6xl font-bold text-slate-900 tracking-tight leading-tight mb-2 sm:mb-4">
                        ආයුබෝවන්!! <br class="sm:hidden"> <span class="text-red-600">සාදරයෙන් පිළිගනිමු</span>
                    </h1>

                    <p class="text-[11px] sm:text-base lg:text-lg text-slate-700 max-w-3xl mx-auto mb-4 sm:mb-8 font-semibold leading-relaxed">
                        ලංකාවේ සාර්ථකම online ඇකඩමියට ඔබව සාදරයෙන් පිළිගන්නවා. ඔබ දැනටමත් කුමන හෝ පාඨමාලාවක් සඳහා ලියාපදිංචි වී ඇත්නම් ඔබගේ දුරකතන අංකය හා Password නිවැරදිව ලබා දී Login වෙන්න.
                    </p>
 
                    <!-- Sleek Form with Pill Inputs and Buttons -->
                    <form action="auth.php" method="POST" class="w-full max-w-2xl mx-auto px-4 flex flex-col items-center gap-2 sm:gap-4">
                        <!-- Error/Success Messages -->
                        <?php if (!empty($error_message)): ?>
                            <div class="w-full max-w-md bg-red-50 text-red-700 px-4 py-2.5 border border-red-200 rounded-full flex items-center justify-center gap-2 text-xs font-bold shadow-sm">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                                <span><?php echo htmlspecialchars($error_message); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="w-full max-w-md bg-emerald-50 text-emerald-700 px-4 py-2.5 border border-emerald-200 rounded-full flex items-center justify-center gap-2 text-xs font-bold shadow-sm">
                                <i class="fas fa-check-circle text-emerald-500"></i>
                                <span><?php echo htmlspecialchars($success_message); ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Inputs Row -->
                        <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 w-full justify-center">
                            <!-- Phone Number Input Styled as a Pill -->
                            <div class="relative flex items-center bg-white border border-slate-200 rounded-full px-4 py-2.5 sm:px-5 sm:py-3 w-full sm:w-64 hover:border-slate-300 focus-within:border-slate-400 focus-within:ring-2 focus-within:ring-slate-100 transition-all shadow-sm">
                                <i class="fas fa-phone-alt text-slate-400 mr-3 text-sm"></i>
                                <input type="text" name="identifier" required placeholder="Mobile Number" class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-slate-800 font-semibold text-xs sm:text-sm placeholder-slate-400">
                            </div>
                            
                            <!-- Password Input Styled as a Pill -->
                            <div class="relative flex items-center bg-white border border-slate-200 rounded-full px-4 py-2.5 sm:px-5 sm:py-3 w-full sm:w-64 hover:border-slate-300 focus-within:border-slate-400 focus-within:ring-2 focus-within:ring-slate-100 transition-all shadow-sm">
                                <i class="fas fa-lock text-slate-400 mr-3 text-sm"></i>
                                <input type="password" name="password" required placeholder="Password" class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-slate-800 font-semibold text-xs sm:text-sm placeholder-slate-400">
                            </div>
                        </div>

                        <!-- Actions Row -->
                        <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 w-full justify-center mt-1 sm:mt-2">
                            <!-- Login Button (Primary Pill) -->
                            <button type="submit" name="login" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm px-6 py-2.5 sm:px-8 sm:py-3.5 rounded-full transition-all flex items-center justify-center gap-2 w-full sm:w-auto shadow-md hover:shadow-lg hover:scale-[1.02] active:scale-[0.98]">
                                <span>Login Now</span>
                                <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                            
                            <!-- Register Button (Secondary Pill) -->
                            <a href="register.php" class="bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 font-bold text-xs sm:text-sm px-6 py-2.5 sm:px-8 sm:py-3.5 rounded-full transition-all flex items-center justify-center gap-2 w-full sm:w-auto shadow-sm hover:shadow-md hover:scale-[1.02] active:scale-[0.98]">
                                <span>Register Now</span>
                            </a>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Stats Section -->
            <section class="min-h-[40vh] flex flex-col justify-center bg-slate-100 relative z-20">
                <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-3 gap-4 md:gap-8 text-center">
                        <div class="stat-item p-4">
                            <h2 class="text-4xl md:text-8xl font-black text-slate-900 tracking-tighter mb-2"
                                id="student-count">1000+</h2>
                            <p class="text-[10px] md:text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Total
                                Students</p>
                        </div>
                        <div class="stat-item p-4 border-x border-slate-200">
                            <h2 class="text-4xl md:text-8xl font-black text-slate-900 tracking-tighter mb-2"
                                id="teacher-count">10</h2>
                            <p class="text-[10px] md:text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Expert
                                Teachers</p>
                        </div>
                        <div class="stat-item p-4">
                            <h2 class="text-4xl md:text-8xl font-black text-slate-900 tracking-tighter mb-2"
                                id="course-count">10</h2>
                            <p class="text-[10px] md:text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Active
                                Courses</p>
                        </div>
                    </div>
                </div>
            </section>

            <script>
                function animateValue(id, start, end, duration) {
                    const obj = document.getElementById(id);
                    if (!obj) return;
                    const startTimestamp = performance.now();
                    const animate = (timestamp) => {
                        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                        const current = Math.floor(progress * (end - start) + start);
                        obj.innerHTML = current + (id === 'student-count' ? '+' : '');
                        if (progress < 1) {
                            window.requestAnimationFrame(animate);
                        } else {
                            obj.innerHTML = end + (id === 'student-count' ? '+' : '');
                        }
                    };
                    window.requestAnimationFrame(animate);
                }

                document.addEventListener('DOMContentLoaded', () => {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                animateValue("student-count", 1000, 1000 + <?php echo $db_student_count; ?>, 600);
                                animateValue("teacher-count", 10, 20 + <?php echo $db_teacher_count; ?>, 600);
                                animateValue("course-count", 10, <?php echo $db_course_count; ?>, 600);
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.5 });

                    observer.observe(document.querySelector('.stat-item'));
                });
            </script>

            <!-- Gold Testimonial/Feedback Section -->
            <section class="relative bg-amber-500 overflow-hidden py-16 lg:py-24 z-20">
                <!-- Abstract Decorative Elements to Match reference design -->
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-orange-600/20 rounded-full blur-2xl -translate-x-12 translate-y-12"></div>
                <div class="absolute top-0 right-0 w-80 h-80 bg-yellow-400/30 rounded-full blur-3xl -translate-y-24 translate-x-24"></div>
                
                <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                        
                        <!-- Left Side: Feedback Text & Action -->
                        <div class="lg:col-span-7 text-left text-white animate-fade-in-up">
                            <span class="inline-block bg-slate-900 text-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] mb-4">
                                Student Feedback
                            </span>
                            
                            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-semibold text-slate-900 leading-tight mb-6 uppercase tracking-tight">
                                "මගේ A/L සිහිනය සැබෑ කරගන්න මේ පන්තිය සහ සර්ගේ නිවැරදි මඟපෙන්වීම මහත් රුකුලක් වුණා!"
                            </h2>
                            
                            <div class="h-1 w-20 bg-slate-900 mb-6 rounded-full"></div>
                            
                            <p class="text-slate-900/90 text-sm sm:text-base font-semibold leading-relaxed mb-8 max-w-2xl">
                                Learner.LK හරහා ක්‍රමානුකූලව සහ සරලව විෂය කරුණු ඉගෙනීමෙන්, පසුගිය උසස් පෙළ විභාගයෙන් දිස්ත්‍රික් මට්ටමේ මෙන්ම දිවයිනේ ඉහළම ප්‍රතිඵල ලබා ගැනීමට අපගේ සිසුන් විශාල පිරිසක් සමත් වී ඇත. ඔවුන්ගේ සාර්ථකත්වයේ හඬ ඔබත් අත්දකින්න.
                            </p>
                            
                            <!-- Navigate to ALDetails.php button -->
                            <a href="dashboard/ALDetails.php" class="inline-flex items-center gap-3 bg-slate-900 text-white px-8 py-4 font-medium text-xs uppercase tracking-widest hover:bg-slate-800 hover:scale-105 active:scale-95 transition-all shadow-lg shadow-slate-900/20 group">
                                <span>සියලුම ප්‍රතිඵල බලන්න / View More Results</span>
                                <i class="fas fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        </div>

                        <!-- Right Side: Student Photo -->
                        <div class="lg:col-span-5 flex justify-center lg:justify-end animate-fade-in-up" style="animation-delay: 0.15s;">
                            <div class="relative w-full max-w-sm sm:max-w-md">
                                <!-- Background Decorative Frame to mimic reference -->
                                <div class="absolute inset-0 bg-yellow-400 rounded-2xl transform rotate-3 shadow-lg"></div>
                                
                                <!-- Student Image -->
                                <div class="relative bg-amber-100 rounded-2xl overflow-hidden border-4 border-white shadow-2xl aspect-[4/5] sm:aspect-[3/4]">
                                    <img src="assests/smiling_student.png" alt="Smiling Student" class="w-full h-full object-cover">
                                </div>
                                
                                <!-- Bubble Message "Hello" (like reference image) -->
                                <div class="absolute -top-6 -right-6 bg-white text-slate-800 px-6 py-3 rounded-full shadow-2xl border border-amber-200 transform rotate-12 flex items-center gap-2">
                                    <span class="text-sm font-black text-red-600">A/L A3!</span>
                                    <i class="fas fa-graduation-cap text-slate-800 text-xs"></i>
                                </div>
                                
                                <!-- Success tag -->
                                <div class="absolute bottom-4 left-4 bg-slate-900/90 backdrop-blur text-white px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>District Rank 02</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

        <?php else: ?>
            <!-- Enhanced Welcome & Stats Section for Logged In Users -->
            <div class="section-welcome pt-28 pb-12">
                <div class="max-w-[1400px] mx-auto px-4 animate-fade-in-up">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        <!-- Welcome Card -->
                        <div
                            class="lg:col-span-4 bg-white rounded-none shadow-xl p-8 border border-slate-100 relative overflow-hidden h-full">
                            <div
                                class="absolute top-0 right-0 w-48 h-48 bg-red-50 rounded-none blur-3xl opacity-50 -mr-24 -mt-24">
                            </div>
                            <div class="relative z-10">
                                <div
                                    class="w-16 h-16 bg-red-600 rounded-none flex items-center justify-center text-white mb-6 shadow-lg shadow-red-200">
                                    <i class="fas fa-user-graduate text-2xl"></i>
                                </div>
                                <h1 class="text-2xl font-black text-slate-900 leading-tight">
                                    ආයුබෝවන් <br>
                                    <span
                                        class="text-red-600"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?>!</span>
                                </h1>
                                <p class="text-slate-500 font-bold mt-2 text-sm">ඔබගේ ඉගෙනුම් පුවරුවට නැවතත් සාදරයෙන්
                                    පිළිගනිමු.</p>

                                <div class="mt-8 pt-6 border-t border-slate-50 flex items-center justify-between">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Student
                                        ID: <?php echo $user_id; ?></span>
                                    <a href="dashboard/profile.php"
                                        class="text-red-600 text-[10px] font-black uppercase tracking-widest hover:translate-x-1 transition-transform">Edit
                                        Profile <i class="fas fa-chevron-right ml-1 text-[8px]"></i></a>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Enrolled Classes -->
                            <div
                                class="bg-white rounded-none p-6 border border-slate-100 shadow-sm hover:shadow-md transition-all group">
                                <div
                                    class="w-10 h-10 bg-blue-50 text-blue-600 rounded-none flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-book-reader"></i>
                                </div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Enrolled
                                    Classes</p>
                                <h3 class="text-2xl font-black text-slate-900"><?php echo $enrolled_count; ?></h3>
                                <a href="dashboard/recordings.php"
                                    class="inline-block mt-4 text-[9px] font-black text-blue-600 uppercase tracking-wider hover:underline">View
                                    Lessons</a>
                            </div>

                            <!-- Pending Payments -->
                            <div
                                class="bg-white rounded-none p-6 border border-slate-100 shadow-sm hover:shadow-md transition-all group">
                                <div
                                    class="w-10 h-10 bg-red-50 text-red-600 rounded-none flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Due Payments
                                </p>
                                <h3 class="text-2xl font-black text-slate-900"><?php echo $pending_payments_count; ?></h3>
                                <a href="dashboard/payments.php"
                                    class="inline-block mt-4 text-[9px] font-black text-red-600 uppercase tracking-wider hover:underline">Pay
                                    Now</a>
                            </div>

                            <!-- Upcoming Exams -->
                            <div
                                class="bg-white rounded-none p-6 border border-slate-100 shadow-sm hover:shadow-md transition-all group">
                                <div
                                    class="w-10 h-10 bg-amber-50 text-amber-600 rounded-none flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-file-signature"></i>
                                </div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Available
                                    Exams</p>
                                <h3 class="text-2xl font-black text-slate-900"><?php echo $upcoming_exams_count; ?></h3>
                                <a href="dashboard/exam_center.php"
                                    class="inline-block mt-4 text-[9px] font-black text-amber-600 uppercase tracking-wider hover:underline">Go
                                    to Center</a>
                            </div>

                            <!-- Extra Courses -->
                            <div
                                class="bg-white rounded-none p-6 border border-slate-100 shadow-sm hover:shadow-md transition-all group">
                                <div
                                    class="w-10 h-10 bg-purple-50 text-purple-600 rounded-none flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">My Courses
                                </p>
                                <h3 class="text-2xl font-black text-slate-900"><?php echo $enrolled_courses_count; ?></h3>
                                <a href="dashboard/online_courses.php"
                                    class="inline-block mt-4 text-[9px] font-black text-purple-600 uppercase tracking-wider hover:underline">Explore
                                    More</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>



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

                <div class="flex items-center w-full md:w-auto">
                    <!-- Cohesive Search Input -->
                    <div class="relative flex items-center bg-white rounded-full shadow-sm border border-slate-300 hover:border-slate-400 px-3.5 py-2 w-full sm:w-64 transition-all duration-300">
                        <i class="fas fa-search text-slate-500 text-xs mr-2"></i>
                        <input type="text" id="classSearch" oninput="searchAndFilter()" placeholder="Search subject or teacher..." 
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
                                class="bg-white rounded-none shadow-md hover:shadow-2xl hover:z-20 transform hover:scale-[1.03] transition-all duration-300 overflow-hidden border border-slate-900/5 flex flex-col class-card stream-<?php echo $stream_id; ?> <?php echo $isHidden ? 'hidden-card' : ''; ?> <?php echo $isMobileHidden ? 'mobile-hidden' : ''; ?>">
                                <!-- Cover Image with Facebook Post Aspect Ratio (1.91:1) -->
                                <div class="relative aspect-[1.91/1] w-full overflow-hidden border-b border-slate-900/5 bg-white">
                                    <?php if ($class['cover_image']): ?>
                                        <img src="<?php echo htmlspecialchars($class['cover_image']); ?>"
                                            alt="<?php echo htmlspecialchars($class['subject_name']); ?>"
                                            class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br <?php echo get_fallback_gradient($class['subject_name']); ?> px-4 text-center">
                                            <span class="text-sm md:text-base font-black text-white leading-tight drop-shadow-md select-none"><?php echo htmlspecialchars($class['subject_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div
                                        class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md px-3 py-1 text-[9px] font-extrabold text-white uppercase tracking-wider">
                                        <?php echo htmlspecialchars($stream_data['stream_name']); ?>
                                    </div>
                                </div>

                                <div class="p-5 flex-1 flex flex-col justify-between">
                                    <div>
                                        <!-- Subject Name -->
                                        <h3 class="text-lg font-semibold text-slate-900 mb-3 leading-tight truncate"
                                            title="<?php echo htmlspecialchars($class['subject_name']); ?>">
                                            <?php echo htmlspecialchars($class['subject_name']); ?>
                                        </h3>

                                        <!-- Instructor Row -->
                                        <div class="flex items-center mb-4">
                                            <?php if ($class['teacher_image']): ?>
                                                <img src="<?php echo htmlspecialchars($class['teacher_image']); ?>"
                                                    class="w-9 h-9 rounded-none border border-slate-900/10 object-cover mr-3">
                                            <?php else: ?>
                                                <div
                                                    class="w-9 h-9 rounded-none bg-slate-900/5 flex items-center justify-center border border-slate-900/10 mr-3">
                                                    <i class="fas fa-user text-[10px] text-slate-400"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex flex-col">
                                                <p class="text-xs font-bold text-slate-900 leading-none">
                                                    <?php echo htmlspecialchars($class['teacher_name']); ?>
                                                </p>
                                                <p class="text-[8px] text-slate-400 font-extrabold mt-1 uppercase tracking-widest">Lead Instructor</p>
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
                                                <a href="dashboard/recordings.php"
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
                                            <a href="register.php?stream_id=<?php echo $stream_id; ?>&subject_id=<?php echo $class['subject_id']; ?>"
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
            const cards = document.querySelectorAll('.class-card');
            const viewMoreBtn = document.getElementById('viewMoreContainer');
            const isMobile = window.innerWidth < 768;

            cards.forEach(card => {
                const subject = (card.getAttribute('data-subject') || '').toLowerCase();
                const teacher = (card.getAttribute('data-teacher') || '').toLowerCase();
                
                const matchesStream = (streamClass === 'all') || card.classList.contains(streamClass);
                const matchesSearch = !searchQuery || subject.includes(searchQuery) || teacher.includes(searchQuery);

                if (matchesStream && matchesSearch) {
                    if (searchQuery === '' && streamClass === 'all') {
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
                if (searchQuery === '' && streamClass === 'all') {
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

                                    <a href="register.php?course_id=<?php echo $course['id']; ?>"
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

    <!-- Footer Section -->
    <footer class="bg-red-600 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="mb-6">
                <img src="assests/logo.jpeg" alt="LMS Logo" class="h-16 w-auto object-contain mx-auto rounded-lg shadow-md mb-3">
                <div class="h-0.5 w-16 bg-white/30 mx-auto rounded-full"></div>
            </div>

            <div class="space-y-2">
                <p class="text-base md:text-lg font-bold text-white">Learner.LK යනු ශ්‍රී ලංකාවේ හොඳම අන්තර්ජාල අධ්‍යාපන
                    ආයතනයයි.</p>
                <p class="text-red-100 font-semibold text-xs md:text-sm tracking-wide">Learner.LK is the best online
                    academy in Sri Lanka.</p>
            </div>

            <div
                class="mt-10 pt-8 border-t border-red-500/30 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs font-bold text-red-100 uppercase tracking-widest">&copy; <?php echo date('Y'); ?>
                    Learner.LK. All rights reserved.</p>
                <div class="flex space-x-6">
                    <a href="#" class="text-white hover:text-red-200 transition-colors"><i
                            class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white hover:text-red-200 transition-colors"><i
                            class="fab fa-youtube"></i></a>
                    <a href="#" class="text-white hover:text-red-200 transition-colors"><i
                            class="fab fa-whatsapp"></i></a>
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
                <a href="register.php"
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
    </script>
</body>

</html>
