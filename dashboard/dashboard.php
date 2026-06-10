<?php
require_once __DIR__ . '/../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$is_logged_in = !empty($user_id);

// Super admin doesn't have a dashboard, redirect to payments
if ($role === 'super_admin') {
    header('Location: ../admin/teacher_payments.php');
    exit;
}

// Get error/success messages from URL
$error_message = isset($_GET['error']) ? urldecode($_GET['error']) : '';
$success_message = isset($_GET['success']) ? urldecode($_GET['success']) : '';

// Get all available courses
$courses_query = "SELECT c.id, c.teacher_id, c.title, c.description, c.price, c.cover_image,
                  u.first_name, u.second_name
                  FROM courses c
                  LEFT JOIN users u ON c.teacher_id = u.user_id COLLATE utf8mb4_general_ci
                  WHERE c.status = 1
                  ORDER BY c.created_at DESC";

$courses_result = $conn->query($courses_query);
$courses = [];

if (!$courses_result) {
    // Debug info if query fails
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
    // 1. Get Enrollments
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

            // 2. Check Enrollment Payments
            $ep_query = "SELECT student_enrollment_id, payment_status FROM enrollment_payments WHERE student_enrollment_id IN ($ids_str) ORDER BY id DESC";
            $ep_res = $conn->query($ep_query);
            while ($row = $ep_res->fetch_assoc()) {
                foreach ($user_enrollment_data as $ssid => $data) {
                    if ($data['id'] == $row['student_enrollment_id']) {
                        // Only set if not already set by a newer record
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

            // 3. Check Monthly Payments for Current Month
            $current_month = date('n');
            $current_year = date('Y');
            $mp_query = "SELECT student_enrollment_id, payment_status FROM monthly_payments WHERE student_enrollment_id IN ($ids_str) AND month = $current_month AND year = $current_year ORDER BY id DESC";
            $mp_res = $conn->query($mp_query);
            while ($row = $mp_res->fetch_assoc()) {
                foreach ($user_enrollment_data as $ssid => $data) {
                    if ($data['id'] == $row['student_enrollment_id']) {
                        // Only set if not already set by a newer record
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
    require_once __DIR__ . '/../check_al_redirection.php';

    // Fetch Student Stats
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

// Helper function to format color values (with or without #)
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

        /* Gallery Scroll Animation */
        .scrolling-gallery {
            display: flex;
            gap: 1.5rem;
            width: max-content;
            animation: scrollGallery 60s linear infinite;
        }

        .scrolling-gallery:hover {
            animation-play-state: paused;
        }

        @keyframes scrollGallery {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
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

        /* Premium Design Utilities */
        .blob {
            position: absolute;
            width: 800px;
            height: 800px;
            border-radius: 50%;
            filter: blur(120px);
            z-index: -1;
            opacity: 0.3;
        }

        .blob-1 {
            background: rgba(239, 68, 68, 0.2);
            top: -300px;
            left: -200px;
        }

        .blob-2 {
            background: rgba(248, 113, 113, 0.2);
            top: -200px;
            right: -200px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .card-hover:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .search-container {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.06);
        }

        .hero-bg-container {
            position: absolute;
            inset: 0;
            background-image: url('../education_hero_bg_1778466693992.png');
            background-size: cover;
            background-position: center;
            filter: blur(2px) brightness(55%);
            /* premium */
            transform: scale(1.1);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.4));
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

        .section-stats {
            background-color: #ffffff;
        }

        .section-al {
            background-color: #f1f5f9;
        }

        /* Slate 100 */
        .section-gallery {
            background-color: #ffffff;
        }

        .section-classes {
            background-color: #e0f2fe;
        }

        /* Sky 100 */
        .section-extra {
            background-color: #fee2e2;
        }

        /* Red 100 */

        .hero-title {
            font-size: 5rem;
            line-height: 1;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -2px;
            color: white;
        }

        .hero-p {
            font-size: 1.125rem;
            color: rgba(255, 255, 255, 0.95);
            max-width: 600px;
            margin: 2rem 0;
            line-height: 1.6;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Include Navbar for all users -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="w-full">
        <style>
            * {
                box-sizing: border-box;
            }
        </style>

        <?php if (!$is_logged_in): ?>
            <!-- Blurred Hero Section with Simplified Content -->
            <!-- Blurred Hero Section with Simplified Content -->
            <!-- Blurred Hero Section with Simplified Content -->
            <section class="relative min-h-screen flex items-center overflow-hidden">
                <div class="hero-bg-container"></div>
                <div class="hero-overlay"></div>

                <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
                    <div class="max-w-3xl animate-fade-in-up">
                        <div
                            class="inline-block bg-red-600 text-white px-3 py-1 text-[10px] font-bold uppercase tracking-[0.3em] mb-6">
                            Learner.LK
                        </div>
                        <h1
                            class="text-3xl md:text-5xl font-black text-white leading-snug mb-4 uppercase tracking-normal">
                            ආයුබෝවන්!! <br>
                            <span class="text-red-600">සාදරයෙන් පිළිගනිමු</span>
                        </h1>

                        <p
                            class="text-sm md:text-base text-white/90 font-medium leading-relaxed mb-6 max-w-2xl tracking-wide">
                            ලංකාවේ සාර්ථකම online ඇකඩමියට ඔබව සාදරයෙන් පිළිගන්නවා. ඔබ දැනටමත් කුමන හෝ පාඨමාලාවක් සඳහා
                            ලියාපදිංචි වී ඇත්නම් ඔබගේ දුරකතන අංකය හා Password නිවැරදිව ලබා දී Login වෙන්න.
                        </p>
                        <p class="text-[10px] text-white/80 font-semibold uppercase tracking-[0.15em] mb-8">
                            අලුතින්ම සම්බන්ධ වීම සඳහා ඉහත ඇති <span class="text-red-500">REGISTER BUTTON</span> එක <span
                                class="text-white">CLICK කරන්න.</span>
                        </p>

                        <!-- Compact Glassmorphic Login Bar -->
                        <div class="max-w-2xl">
                            <?php if (!empty($error_message)): ?>
                                <div class="bg-red-600/90 text-white px-4 py-3 mb-4 font-bold border-l-4 border-white backdrop-blur-md flex items-center shadow-lg">
                                    <i class="fas fa-exclamation-circle mr-3"></i>
                                    <span><?php echo htmlspecialchars($error_message); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($success_message)): ?>
                                <div class="bg-emerald-600/90 text-white px-4 py-3 mb-4 font-bold border-l-4 border-white backdrop-blur-md flex items-center shadow-lg">
                                    <i class="fas fa-check-circle mr-3"></i>
                                    <span><?php echo htmlspecialchars($success_message); ?></span>
                                </div>
                            <?php endif; ?>
                            <form action="../auth.php" method="POST"
                                class="bg-white/10 backdrop-blur-xl p-1 rounded-none flex flex-col md:flex-row gap-1 border border-white/20 shadow-2xl group transition-all hover:bg-white/15">
                                <div
                                    class="flex-1 flex items-center px-4 py-2 border-b md:border-b-0 md:border-r border-white/10">
                                    <i class="fas fa-mobile-alt text-red-500 mr-3 text-base"></i>
                                    <input type="text" name="identifier" required placeholder="Mobile Number"
                                        class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-white font-bold text-sm placeholder-white/30">
                                </div>
                                <div class="flex-1 flex items-center px-4 py-2">
                                    <i class="fas fa-lock text-red-500 mr-3 text-base"></i>
                                    <input type="password" name="password" required placeholder="Password"
                                        class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-white font-semibold text-sm placeholder-white/30">
                                </div>
                                <button type="submit" name="login"
                                    class="bg-red-600 text-white px-8 py-3 rounded-none font-black text-sm hover:bg-red-700 transition-all shadow-lg shadow-red-600/20 active:scale-95 whitespace-nowrap">
                                    Login Now
                                </button>
                            </form>
                            <p class="text-[10px] text-white/80 font-semibold uppercase tracking-[0.15em] mt-4 ml-4">
                                අලුතින්ම සම්බන්ධ වීම සඳහා <a href="../register.php"
                                    class="text-red-500 hover:underline">REGISTER HERE</a>
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Stats Section -->
            <section class="min-h-[40vh] flex flex-col justify-center bg-slate-100 relative z-20">
                <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-3 gap-4 md:gap-8 text-center">
                        <div class="stat-item p-4">
                            <h2 class="text-4xl md:text-8xl font-black text-slate-900 tracking-tighter mb-2"
                                id="student-count">0</h2>
                            <p class="text-[10px] md:text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Total
                                Students</p>
                        </div>
                        <div class="stat-item p-4 border-x border-slate-200">
                            <h2 class="text-4xl md:text-8xl font-black text-slate-900 tracking-tighter mb-2"
                                id="teacher-count">0</h2>
                            <p class="text-[10px] md:text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Expert
                                Teachers</p>
                        </div>
                        <div class="stat-item p-4">
                            <h2 class="text-4xl md:text-8xl font-black text-slate-900 tracking-tighter mb-2"
                                id="course-count">0</h2>
                            <p class="text-[10px] md:text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Active
                                Courses</p>
                        </div>
                    </div>
                </div>
            </section>

            <script>
                function animateValue(id, start, end, duration) {
                    if (start === end) return;
                    const range = end - start;
                    let current = start;
                    const increment = end > start ? 1 : -1;
                    const stepTime = Math.abs(Math.floor(duration / range));
                    const obj = document.getElementById(id);
                    const timer = setInterval(function () {
                        current += increment;
                        obj.innerHTML = current + (id === 'student-count' ? '+' : '');
                        if (current == end) {
                            clearInterval(timer);
                        }
                    }, stepTime);
                }

                document.addEventListener('DOMContentLoaded', () => {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                animateValue("student-count", 0, 1000 + <?php echo $db_student_count; ?>, 2000);
                                animateValue("teacher-count", 0, 20 + <?php echo $db_teacher_count; ?>, 2000);
                                animateValue("course-count", 0, <?php echo $db_course_count; ?>, 2000);
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.5 });

                    observer.observe(document.querySelector('.stat-item'));
                });
            </script>
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
                                    <a href="profile.php"
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
                                <a href="recordings.php"
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
                                <a href="payments.php"
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
                                <a href="exam_center.php"
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
                                <a href="online_courses.php"
                                    class="inline-block mt-4 text-[9px] font-black text-purple-600 uppercase tracking-wider hover:underline">Explore
                                    More</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Universal AL Results Section -->
        <?php 
        $al_bg_color = format_html_color($dashboard_colors['al_results']['bg_color'] ?? 'bg-sky-200');
        $al_bg_style = '';
        $al_bg_class = 'bg-sky-200';
        if (strpos($al_bg_color, '#') === 0) {
            $al_bg_style = 'style="background-color: ' . $al_bg_color . ';"';
            $al_bg_class = '';
        } else {
            $al_bg_class = $al_bg_color;
        }
        ?>
        <div class="section-al py-12 md:py-24 flex flex-col <?php echo $al_bg_class; ?>" <?php echo $al_bg_style; ?>>
            <div class="w-full mx-auto px-4 sm:px-6 lg:px-8 animate-fade-in-up">
                <div
                    class="px-6 py-4 md:py-6 mb-4 md:mb-8 flex flex-col md:flex-row md:items-center justify-between border-b border-sky-200 gap-4">
                    <h2
                        class="text-xl md:text-3xl font-black text-slate-900 tracking-normal uppercase border-b-4 border-slate-900 pb-2 inline-block">
                        අපගේ පසුගිය විශිෂ්ට ප්‍රතිඵල</h2>
                    <a href="ALDetails.php"
                        class="text-red-600 text-[10px] font-bold uppercase tracking-widest hover:text-red-500 transition-colors">
                        View Results <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <?php
                // Curated Demo Data for A/L Achievers (Directly using array as requested)
                $al_results = [
                    ['name' => 'Sugath Perera', 'stream' => 'Combined Mathematics', 'district' => 'Gampaha', 'drank' => '02', 'irank' => '14', 'results' => ['Combined Mathematics' => 'A', 'Physics' => 'A', 'Chemistry' => 'A']],
                    ['name' => 'Nipuna Diyalagoda', 'stream' => 'Physical Science', 'district' => 'Gampaha', 'drank' => '24', 'irank' => '325', 'results' => ['Combined Mathematics' => 'A', 'Physics' => 'A', 'Chemistry' => 'A']],
                    ['name' => 'Kasun Perera', 'stream' => 'Biological Science', 'district' => 'Colombo', 'drank' => '12', 'irank' => '105', 'results' => ['Biology' => 'A', 'Physics' => 'A', 'Chemistry' => 'A']],
                    ['name' => 'Sanduni Silva', 'stream' => 'Commerce', 'district' => 'Kandy', 'drank' => '05', 'irank' => '42', 'results' => ['Accounting' => 'A', 'Economics' => 'A', 'Business Studies' => 'A']],
                    ['name' => 'Malith Ranasinghe', 'stream' => 'Physical Science', 'district' => 'Matara', 'drank' => '18', 'irank' => '210', 'results' => ['Combined Mathematics' => 'A', 'Physics' => 'A', 'Chemistry' => 'A']],
                    ['name' => 'Dilshani Silva', 'stream' => 'Biological Science', 'district' => 'Kalutara', 'drank' => '08', 'irank' => '88', 'results' => ['Biology' => 'A', 'Physics' => 'A', 'Chemistry' => 'A']],
                    ['name' => 'Pathum Perera', 'stream' => 'Physical Science', 'district' => 'Galle', 'drank' => '15', 'irank' => '156', 'results' => ['Combined Mathematics' => 'A', 'Physics' => 'A', 'Chemistry' => 'A']],
                    ['name' => 'Kavindi Perera', 'stream' => 'Commerce', 'district' => 'Ratnapura', 'drank' => '03', 'irank' => '22', 'results' => ['Accounting' => 'A', 'Economics' => 'A', 'Business Studies' => 'A']],
                    ['name' => 'Tharindu Silva', 'stream' => 'Physical Science', 'district' => 'Kurunegala', 'drank' => '21', 'irank' => '288', 'results' => ['Combined Mathematics' => 'A', 'Physics' => 'A', 'Chemistry' => 'A']],
                    ['name' => 'Ruwan Perera', 'stream' => 'Biological Science', 'district' => 'Badulla', 'drank' => '10', 'irank' => '95', 'results' => ['Biology' => 'A', 'Physics' => 'A', 'Chemistry' => 'A']]
                ];
                ?>

                <style>
                    /* Each card takes 100% on mobile (1 visible), 25% on desktop (4 visible) */
                    .al-card-wrap {
                        flex: 0 0 100%;
                        max-width: 100%;
                    }
                    @media (min-width: 1024px) {
                        .al-card-wrap {
                            flex: 0 0 25%;
                            max-width: 25%;
                        }
                    }
                    #achieverSlider {
                        display: flex;
                        width: 100%;
                    }
                    /* Dots */
                    .al-dot {
                        width: 8px; height: 8px;
                        border-radius: 50%;
                        background: rgba(15,23,42,0.25);
                        transition: background 0.3s, transform 0.3s;
                        cursor: pointer;
                    }
                    .al-dot.active {
                        background: #dc2626;
                        transform: scale(1.3);
                    }
                </style>
                <div class="relative overflow-hidden group/slider" id="achieverSliderWrapper">
                    <div id="achieverSlider" class="flex transition-transform duration-700 ease-in-out">
                        <?php
                        $last_tile_color = '';
                        $tile_colors_str = $dashboard_colors['al_results']['card_colors'] ?? 'bg-blue-100,bg-emerald-100,bg-violet-100,bg-amber-100,bg-rose-100,bg-cyan-100,bg-indigo-100,bg-orange-100,bg-teal-100,bg-sky-100,bg-pink-100,bg-purple-100';
                        $tile_colors = array_filter(array_map('trim', explode(',', $tile_colors_str)));
                        if (empty($tile_colors)) {
                            $tile_colors = ['#ffffff'];
                        }
                        foreach ($al_results as $res):
                            do {
                                $random_tile_bg = $tile_colors[array_rand($tile_colors)];
                            } while ($random_tile_bg === $last_tile_color && count($tile_colors) > 1);
                            $last_tile_color = $random_tile_bg;
                            
                            $card_bg_color = format_html_color($random_tile_bg);
                            $card_bg_style = '';
                            $card_bg_class = '';
                            if (strpos($card_bg_color, '#') === 0) {
                                $card_bg_style = 'style="background-color: ' . $card_bg_color . ';"';
                            } else {
                                $card_bg_class = $card_bg_color;
                            }
                            ?>
                            <div class="al-card-wrap p-0 z-10 hover:z-20 transition-all duration-500">
                                <div <?php echo $card_bg_style; ?>
                                    class="<?php echo $card_bg_class; ?> rounded-none transform hover:scale-105 hover:brightness-95 transition-all duration-500 relative group overflow-hidden h-[600px] flex flex-col shadow-none hover:shadow-2xl">
                                    <!-- Card Header -->
                                    <div class="p-4">
                                        <div class="relative h-60 overflow-hidden rounded-none border border-slate-900/10">
                                            <?php if (isset($res['photo']) && !empty($res['photo'])): ?>
                                                <img src="../<?php echo $res['photo']; ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <img src="../assests/student_avatar.png" class="w-full h-full object-cover">
                                            <?php endif; ?>
                                            <!-- Stream Label -->
                                            <div
                                                class="absolute bottom-2 left-2 bg-black/60 backdrop-blur-md text-white text-[8px] font-black px-2 py-1 rounded-none uppercase tracking-widest whitespace-nowrap">
                                                <?php echo $res['stream']; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Info -->
                                    <div class="px-8 pb-8 pt-2 flex-1 flex flex-col">
                                        <div class="text-left mb-4">
                                            <h3 class="font-black text-slate-900 text-xl leading-tight mb-1">
                                                <?php echo $res['name']; ?>
                                            </h3>
                                            <p class="text-[8px] text-slate-500 font-black uppercase tracking-[0.2em]">
                                                <?php echo $res['district']; ?> District Specialist
                                            </p>
                                        </div>

                                        <!-- Grades -->
                                        <div class="space-y-1 mb-6">
                                            <?php
                                            $results_to_show = isset($res['results']) ? $res['results'] : [
                                                'Combined Mathematics' => 'A',
                                                'Physics' => 'A',
                                                'Chemistry' => 'A'
                                            ];
                                            foreach ($results_to_show as $subject => $grade): ?>
                                                <div
                                                    class="flex justify-between items-center px-0 py-1 border-b border-slate-900/10">
                                                    <span
                                                        class="text-[10px] font-black text-slate-700 uppercase tracking-wider truncate mr-2"><?php echo $subject; ?></span>
                                                    <span class="text-xl font-black text-slate-900"><?php echo $grade; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4 pt-4 mt-auto border-t border-slate-900/10">
                                            <div class="text-left">
                                                <p
                                                    class="text-[8px] text-slate-400 font-black uppercase tracking-wider mb-1">
                                                    Dist. Rank</p>
                                                <p class="text-xl font-black text-slate-900"><?php echo $res['drank']; ?>
                                                </p>
                                            </div>
                                            <div class="text-left">
                                                <p
                                                    class="text-[8px] text-slate-400 font-black uppercase tracking-wider mb-1">
                                                    Island Rank</p>
                                                <p class="text-xl font-black text-slate-900"><?php echo $res['irank']; ?>
                                                </p>
                                            </div>
                                        </div> <!-- closes grid -->
                                    </div> <!-- closes p-4 content wrapper -->
                                </div> <!-- closes card-hover -->
                            </div> <!-- closes px-4 column -->
                        <?php endforeach; ?>
                    </div>

                    <!-- Slider Controls -->
                    <button id="alPrevBtn"
                        class="absolute left-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 backdrop-blur shadow-lg rounded-none flex items-center justify-center text-slate-800 opacity-0 group-hover/slider:opacity-100 transition-opacity z-20 hover:bg-red-600 hover:text-white">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button id="alNextBtn"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 backdrop-blur shadow-lg rounded-none flex items-center justify-center text-slate-800 opacity-0 group-hover/slider:opacity-100 transition-opacity z-20 hover:bg-red-600 hover:text-white">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <!-- Dots -->
                <div class="flex justify-center gap-2 mt-6" id="alDots"></div>

                <script>
                    (function () {
                        const slider = document.getElementById('achieverSlider');
                        const wrapper = document.getElementById('achieverSliderWrapper');
                        const dotsContainer = document.getElementById('alDots');
                        const totalCards = <?php echo count($al_results); ?>;

                        let currentSlide = 0;
                        let autoSlideTimer = null;

                        function getSlidesToShow() {
                            return window.innerWidth >= 1024 ? 4 : 1;
                        }

                        function getMaxSlide() {
                            return totalCards - getSlidesToShow();
                        }

                        function goToSlide(index) {
                            const max = getMaxSlide();
                            currentSlide = Math.max(0, Math.min(index, max));
                            // Each card is (100 / totalCards)% wide of the slider track,
                            // but since the track is 100% of the container we shift by
                            // (cardWidth in container %) = (1/visibleCount)*100
                            const cardWidthPct = 100 / getSlidesToShow();
                            // Actual shift: currentSlide cards * cardWidthPct / totalCards * totalCards... simplified:
                            // Since each card CSS = (100/slidesToShow)% of wrapper,
                            // one card shift = (100/slidesToShow)% of wrapper
                            // But the slider element itself is 100% of wrapper,
                            // so translateX percentage is relative to the slider (= wrapper width).
                            const offset = currentSlide * (100 / getSlidesToShow());
                            slider.style.transform = `translateX(-${offset}%)`;
                            updateDots();
                        }

                        function moveSlider(direction) {
                            const max = getMaxSlide();
                            if (direction === 'next') {
                                goToSlide(currentSlide >= max ? 0 : currentSlide + 1);
                            } else {
                                goToSlide(currentSlide <= 0 ? max : currentSlide - 1);
                            }
                        }

                        // Build dots
                        function buildDots() {
                            dotsContainer.innerHTML = '';
                            const dotCount = getMaxSlide() + 1;
                            for (let i = 0; i < dotCount; i++) {
                                const dot = document.createElement('button');
                                dot.className = 'al-dot' + (i === 0 ? ' active' : '');
                                dot.addEventListener('click', () => { goToSlide(i); restartAuto(); });
                                dotsContainer.appendChild(dot);
                            }
                        }

                        function updateDots() {
                            dotsContainer.querySelectorAll('.al-dot').forEach((d, i) => {
                                d.classList.toggle('active', i === currentSlide);
                            });
                        }

                        function startAuto() {
                            autoSlideTimer = setInterval(() => moveSlider('next'), 3000);
                        }

                        function stopAuto() {
                            clearInterval(autoSlideTimer);
                        }

                        function restartAuto() {
                            stopAuto();
                            startAuto();
                        }

                        document.getElementById('alPrevBtn').addEventListener('click', () => { moveSlider('prev'); restartAuto(); });
                        document.getElementById('alNextBtn').addEventListener('click', () => { moveSlider('next'); restartAuto(); });

                        // Pause on hover
                        wrapper.addEventListener('mouseenter', stopAuto);
                        wrapper.addEventListener('mouseleave', startAuto);

                        // Touch / swipe support
                        let touchStartX = 0;
                        wrapper.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
                        wrapper.addEventListener('touchend', e => {
                            const diff = touchStartX - e.changedTouches[0].clientX;
                            if (Math.abs(diff) > 40) { moveSlider(diff > 0 ? 'next' : 'prev'); restartAuto(); }
                        }, { passive: true });

                        // Resize: recalculate
                        window.addEventListener('resize', () => {
                            currentSlide = 0;
                            slider.style.transform = 'translateX(0)';
                            buildDots();
                            updateDots();
                        });

                        // Init
                        buildDots();
                        startAuto();
                    })();
                </script>
            </div>
        </div>
    </div>
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
    <div class="section-classes py-12 md:py-24 flex flex-col <?php echo $classes_bg_class; ?>" id="classes-section" <?php echo $classes_bg_style; ?>>
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="px-6 py-4 md:py-6 mb-4 md:mb-8 flex flex-col md:flex-row md:items-center justify-between border-b border-amber-200 gap-4">
                <div>
                    <h2
                        class="text-xl md:text-3xl font-black text-slate-900 tracking-normal uppercase border-b-4 border-slate-900 pb-2 inline-block">
                        අප ආයතනයෙන් උගන්වන විෂයධාරාවන්</h2>
                    <p class="text-slate-500 text-[10px] md:text-xs font-semibold mt-4">ලියාපදිංචි වීමට Enroll Now click කරන්න</p>
                </div>

                <div class="flex flex-col items-end">
                    <label for="streamFilter"
                        class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Filter by
                        Stream</label>
                    <select id="streamFilter" onchange="filterStream(this.value)"
                        class="block w-full md:w-56 pl-3 pr-10 py-2 text-xs border-amber-200 focus:outline-none focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm border bg-white/80 font-bold text-slate-700">
                        <option value="all">All Academic Streams</option>
                        <?php foreach ($assignments_by_stream as $stream_id => $stream_data): ?>
                            <option value="stream-<?php echo $stream_id; ?>">
                                <?php echo htmlspecialchars($stream_data['stream_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-0">
                    <?php foreach ($assignments_by_stream as $stream_id => $stream_data):
                        ?>
                        <?php foreach ($stream_data['classes'] as $class):
                            $card_count++;
                            $isHidden = $card_count > 6;
                            // Ensure same color doesn't appear twice in a row
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
                            if (!empty($card_bg_style)) {
                                $style_tags[] = $card_bg_style;
                            }
                            if ($isHidden) {
                                $style_tags[] = 'display: none;';
                            }
                            $style_attr = !empty($style_tags) ? 'style="' . implode(' ', $style_tags) . '"' : '';
                            ?>
                            <div <?php echo $style_attr; ?> class="<?php echo $card_bg_class; ?> rounded-none shadow-none hover:shadow-2xl hover:z-20 transform hover:scale-105 hover:brightness-95 transition-all duration-500 overflow-hidden group border-none class-card stream-<?php echo $stream_id; ?> <?php echo $isHidden ? 'hidden-card' : ''; ?>">
                                <!-- Cover Image -->
                                <div class="p-4">
                                    <div class="relative h-60 overflow-hidden rounded-none border border-slate-900/10">
                                        <?php if ($class['cover_image']): ?>
                                            <img src="../<?php echo htmlspecialchars($class['cover_image']); ?>"
                                                alt="<?php echo htmlspecialchars($class['subject_name']); ?>"
                                                class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                                        <?php else: ?>
                                            <div class="w-full h-full bg-slate-900/5 flex items-center justify-center">
                                                <i class="fas fa-book text-slate-400/20 text-4xl"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div
                                            class="absolute top-2 right-2 bg-black/60 backdrop-blur-md px-2 py-1 rounded-none text-[9px] font-bold text-white shadow-sm border border-slate-900/10 uppercase tracking-wide">
                                            <?php echo htmlspecialchars($stream_data['stream_name']); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-8">
                                    <!-- Subject & Teacher -->
                                    <h3 class="text-xl font-black text-slate-900 mb-2 leading-tight truncate"
                                        title="<?php echo htmlspecialchars($class['subject_name']); ?>">
                                        <?php echo htmlspecialchars($class['subject_name']); ?>
                                    </h3>

                                    <div class="flex items-center mb-8">
                                        <?php if ($class['teacher_image']): ?>
                                            <img src="../<?php echo htmlspecialchars($class['teacher_image']); ?>"
                                                class="w-12 h-12 rounded-none border border-slate-900/10 object-cover mr-3">
                                        <?php else: ?>
                                            <div
                                                class="w-12 h-12 rounded-none bg-slate-900/5 flex items-center justify-center border border-slate-900/10 mr-3">
                                                <i class="fas fa-user text-xs text-slate-400"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex flex-col">
                                            <p class="text-sm font-black text-slate-900 leading-none">
                                                <?php echo htmlspecialchars($class['teacher_name']); ?>
                                            </p>
                                            <p class="text-[9px] text-slate-500 font-black mt-1 uppercase tracking-widest">Lead
                                                Instructor</p>
                                        </div>
                                    </div>

                                    <!-- Fees or Payment Status -->
                                    <?php
                                    $enrolled_data = $user_enrollment_data[$class['stream_subject_id']] ?? null;
                                    if ($enrolled_data):
                                        ?>
                                        <div class="grid grid-cols-2 gap-3 mb-4">
                                            <!-- Enrollment Status -->
                                            <div
                                                class="<?php echo $enrolled_data['enrollment_paid'] ? 'bg-green-50 border-green-100' : (isset($enrolled_data['enrollment_status']) && $enrolled_data['enrollment_status'] == 'Pending' ? 'bg-yellow-50 border-yellow-100' : 'bg-blue-50 border-blue-100'); ?> rounded-lg p-2 text-center border">
                                                <p
                                                    class="text-[10px] <?php echo $enrolled_data['enrollment_paid'] ? 'text-green-500' : (isset($enrolled_data['enrollment_status']) && $enrolled_data['enrollment_status'] == 'Pending' ? 'text-yellow-600' : 'text-blue-500'); ?> uppercase tracking-wider font-semibold mb-1">
                                                    Enrollment</p>
                                                <p
                                                    class="text-xs font-bold <?php echo $enrolled_data['enrollment_paid'] ? 'text-green-700' : (isset($enrolled_data['enrollment_status']) && $enrolled_data['enrollment_status'] == 'Pending' ? 'text-yellow-700' : 'text-blue-700'); ?>">
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
                                                class="<?php echo $enrolled_data['monthly_status'] == 'Paid' ? 'bg-green-50 border-green-100' : ($enrolled_data['monthly_status'] == 'Pending' ? 'bg-yellow-50 border-yellow-100' : 'bg-blue-50 border-blue-100'); ?> rounded-lg p-2 text-center border">
                                                <p
                                                    class="text-[10px] <?php echo $enrolled_data['monthly_status'] == 'Paid' ? 'text-green-500' : ($enrolled_data['monthly_status'] == 'Pending' ? 'text-yellow-600' : 'text-blue-500'); ?> uppercase tracking-wider font-semibold mb-1">
                                                    <?php echo date('F'); ?>
                                                </p>
                                                <p
                                                    class="text-xs font-bold <?php echo $enrolled_data['monthly_status'] == 'Paid' ? 'text-green-700' : ($enrolled_data['monthly_status'] == 'Pending' ? 'text-yellow-700' : 'text-blue-700'); ?>">
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
                                        <div class="grid grid-cols-2 gap-4 mb-8">
                                            <div class="bg-white/50 rounded-none p-4 text-center border border-slate-900/10">
                                                <p class="text-[9px] text-slate-500 uppercase tracking-widest font-black mb-1">
                                                    Enrollment</p>
                                                <p class="text-xl font-black text-slate-900">
                                                    <?php echo $class['enrollment_fee'] > 0 ? number_format($class['enrollment_fee']) : 'Free'; ?>
                                                </p>
                                            </div>
                                            <div class="bg-white/50 rounded-none p-4 text-center border border-slate-900/10">
                                                <p class="text-[9px] text-slate-500 uppercase tracking-widest font-black mb-1">Monthly
                                                </p>
                                                <p class="text-xl font-black text-slate-900">
                                                    <?php echo $class['monthly_fee'] > 0 ? number_format($class['monthly_fee']) : 'Free'; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($is_logged_in): ?>
                                        <?php if ($enrolled_data): ?>
                                            <a href="recordings.php"
                                                class="block w-full text-center bg-gray-100 text-gray-700 py-1.5 px-3 rounded-lg hover:bg-gray-200 transition-colors duration-200 text-[11px] font-medium">
                                                View Details
                                            </a>
                                        <?php else: ?>
                                            <button
                                                onclick="openEnrollModal(<?php echo $class['stream_subject_id']; ?>, '<?php echo htmlspecialchars($class['subject_name'], ENT_QUOTES); ?>')"
                                                class="block w-full text-center bg-slate-900 text-white py-4 px-6 rounded-none hover:bg-slate-800 transition-colors duration-200 text-xs font-black uppercase tracking-widest shadow-lg">
                                                Enroll Now
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="../register.php?stream_id=<?php echo $stream_id; ?>&subject_id=<?php echo $class['subject_id']; ?>"
                                            class="block w-full text-center bg-gray-900 text-white py-1.5 px-3 rounded-lg hover:bg-red-600 transition-colors duration-200 text-[11px] font-medium">
                                            Enroll Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($card_count > 6): ?>
                <div class="mt-20 text-center" id="viewMoreContainer">
                    <button onclick="showAllClasses()"
                        class="inline-flex items-center gap-4 bg-slate-900 text-white py-5 px-16 rounded-full hover:bg-slate-800 hover:scale-105 transition-all duration-500 font-black text-xs uppercase tracking-[0.3em] shadow-[0_30px_60px_rgba(0,0,0,0.4)] group">
                        <span>පවතින සියලුම විෂයන් බලන්න</span>
                        <i class="fas fa-arrow-down text-[10px] group-hover:translate-y-1 transition-transform"></i>
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div> <!-- Inner container ends -->

    <script>
        function showAllClasses() {
            const hiddenCards = document.querySelectorAll('.hidden-card');
            hiddenCards.forEach(card => {
                card.style.display = 'block';
                // Fade in effect
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease-in-out';
                    card.style.opacity = '1';
                }, 10);
            });
            document.getElementById('viewMoreContainer').style.display = 'none';
        }

        function showAllExtraCourses() {
            const hiddenCards = document.querySelectorAll('.hidden-course');
            hiddenCards.forEach(card => {
                card.style.display = 'block';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease-in-out';
                    card.style.opacity = '1';
                }, 10);
            });
            document.getElementById('viewMoreExtraContainer').style.display = 'none';
        }

        function filterStream(streamClass) {
            // Filter cards
            const cards = document.querySelectorAll('.class-card');
            const viewMoreBtn = document.getElementById('viewMoreContainer');

            cards.forEach(card => {
                if (streamClass === 'all') {
                    // Reset to "View More" state
                    if (card.classList.contains('hidden-card')) {
                        card.style.display = 'none';
                    } else {
                        card.style.display = 'block';
                    }
                    if (viewMoreBtn) viewMoreBtn.style.display = 'block';
                } else {
                    // Show all matching cards, hide others
                    if (card.classList.contains(streamClass)) {
                        card.style.display = 'block';
                        card.style.opacity = '1';
                    } else {
                        card.style.display = 'none';
                    }
                    if (viewMoreBtn) viewMoreBtn.style.display = 'none';
                }
            });
        }
    </script>
    </div> <!-- End of Classes Section -->

    <!-- Extra Courses Section -->
    <?php 
    $extra_bg_color = format_html_color($dashboard_colors['extra_courses']['bg_color'] ?? 'bg-emerald-200/80');
    $extra_bg_style = '';
    $extra_bg_class = 'bg-emerald-200/80';
    if (strpos($extra_bg_color, '#') === 0) {
        $extra_bg_style = 'style="background-color: ' . $extra_bg_color . ';"';
        $extra_bg_class = '';
    } else {
        $extra_bg_class = $extra_bg_color;
    }
    ?>
    <div class="section-extra py-12 md:py-24 flex flex-col <?php echo $extra_bg_class; ?>" <?php echo $extra_bg_style; ?>>
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="px-6 py-4 md:py-6 mb-4 md:mb-8 flex flex-col md:flex-row md:items-center justify-between border-b border-emerald-200 gap-4">
                <div>
                    <h2
                        class="text-xl md:text-3xl font-black text-slate-900 tracking-normal uppercase border-b-4 border-slate-900 pb-2 inline-block">
                        අපගේ බාහිර පාඨමාලා</h2>
                    <p class="text-slate-500 text-[10px] md:text-xs font-semibold mt-4">නවීන තාක්ෂණය හා බාහිර දැනුම ලබා ගැනීමට එක්වන්න</p>
                </div>
                <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest hidden md:block">Extra
                    Learning</span>
            </div>
            <?php if (empty($courses)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <p class="text-gray-500">No courses available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-0">
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
                        $isCourseHidden = $course_count > 6;
                        // Ensure same color doesn't appear twice in a row
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
                        if (!empty($card_bg_style)) {
                            $style_tags[] = $card_bg_style;
                        }
                        if ($isCourseHidden) {
                            $style_tags[] = 'display: none;';
                        }
                        $style_attr = !empty($style_tags) ? 'style="' . implode(' ', $style_tags) . '"' : '';
                        ?>
                        <div <?php echo $style_attr; ?> class="<?php echo $card_bg_class; ?> rounded-none shadow-none hover:shadow-2xl hover:z-20 transform hover:scale-105 hover:brightness-95 transition-all duration-500 overflow-hidden border-none extra-course-card <?php echo $isCourseHidden ? 'hidden-course' : ''; ?>">
                            <!-- Course Cover Image -->
                            <div class="p-4">
                                <div class="h-60 overflow-hidden relative border border-slate-900/10">
                                    <?php if ($course['cover_image']): ?>
                                        <img src="../<?php echo htmlspecialchars($course['cover_image']); ?>"
                                            alt="<?php echo htmlspecialchars($course['title']); ?>"
                                            class="w-full h-full object-cover transform hover:scale-110 transition-transform duration-700">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-slate-900/5">
                                            <i class="fas fa-book text-slate-400/20 text-6xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Course Content -->
                            <div class="p-8">
                                <h3 class="font-black text-xl text-slate-900 mb-2 truncate">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </h3>

                                <p class="text-[10px] text-slate-500 mb-8 font-black uppercase tracking-widest">
                                    <i class="fas fa-user-tie text-slate-400 mr-2"></i>
                                    By <?php echo htmlspecialchars($course['teacher_name'] ?: 'Unknown'); ?>
                                </p>

                                <div class="flex items-center justify-between mb-8">
                                    <span class="text-slate-900 font-black text-2xl">
                                        Rs. <?php echo number_format($course['price'], 2); ?>
                                    </span>
                                </div>

                                <a href="../register.php?course_id=<?php echo $course['id']; ?>"
                                    class="block w-full text-center bg-slate-900 text-white py-4 px-6 rounded-none hover:bg-slate-800 transition text-xs font-black uppercase tracking-widest mt-4 shadow-md">
                                    <i class="fas fa-cart-plus mr-1"></i>Enroll Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($course_count > 6): ?>
                    <div class="mt-20 text-center" id="viewMoreExtraContainer">
                        <button onclick="showAllExtraCourses()"
                            class="inline-flex items-center gap-4 bg-slate-900 text-white py-5 px-16 rounded-full hover:bg-slate-800 hover:scale-105 transition-all duration-500 font-black text-xs uppercase tracking-[0.3em] shadow-[0_30px_60px_rgba(0,0,0,0.4)] group">
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
                <h2 class="text-lg md:text-xl font-black text-white mb-2">Learner.LK</h2>
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
                <a href="../register.php"
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

            // Disable button
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            fetch('enroll.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    closeEnrollModal();
                    if (data.success) {
                        showToast('Enrollment successful!', true);
                        // Reload after short delay
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