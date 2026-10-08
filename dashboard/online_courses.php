<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

function get_img_url($path) {
    if (empty($path)) return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return '../' . ltrim($path, '/');
}

function get_fallback_gradient($name) {
    $gradients = [
        'from-blue-600 to-indigo-700',
        'from-purple-600 to-pink-600',
        'from-emerald-600 to-teal-700',
        'from-red-600 to-rose-700',
        'from-amber-600 to-orange-700',
        'from-cyan-600 to-blue-700'
    ];
    $idx = abs(crc32($name ?? '')) % count($gradients);
    return $gradients[$idx];
}

$user_logged_in = isset($_SESSION['user_id']);
$user_id = $user_logged_in ? $_SESSION['user_id'] : '';
$role = $_SESSION['role'] ?? '';
$success_msg = '';
$error_msg = '';

// Handle Subject Class Enrollment (Logged-in Student)
if ($user_logged_in && $role === 'student' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_subject_class'])) {
    $stream_subject_id = intval($_POST['stream_subject_id'] ?? 0);
    $academic_year = intval($_POST['academic_year'] ?? date('Y'));
    $teacher_id = trim($_POST['teacher_id'] ?? '');

    if ($stream_subject_id > 0) {
        $chk_stmt = $conn->prepare("SELECT id FROM student_enrollment WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'");
        $chk_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $chk_stmt->execute();
        $chk_res = $chk_stmt->get_result();
        if ($chk_res->num_rows > 0) {
            $error_msg = "You are already enrolled in this class.";
        } else {
            if (!empty($teacher_id)) {
                $ins_stmt = $conn->prepare("INSERT INTO student_enrollment (student_id, teacher_id, stream_subject_id, academic_year, status, payment_status, enrolled_date) VALUES (?, ?, ?, ?, 'active', 'pending', CURDATE())");
                $ins_stmt->bind_param("ssii", $user_id, $teacher_id, $stream_subject_id, $academic_year);
            } else {
                $ins_stmt = $conn->prepare("INSERT INTO student_enrollment (student_id, stream_subject_id, academic_year, status, payment_status, enrolled_date) VALUES (?, ?, ?, 'active', 'pending', CURDATE())");
                $ins_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
            }
            if ($ins_stmt->execute()) {
                $success_msg = "Successfully enrolled in the class! You can now access lesson recordings and live sessions.";
            } else {
                $error_msg = "Error enrolling in class: " . $conn->error;
            }
            $ins_stmt->close();
        }
        $chk_stmt->close();
    }
}

// Handle Course Enrollment (Logged-in Student)
if ($user_logged_in && $role === 'student' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course'])) {
    $course_id = intval($_POST['course_id'] ?? 0);
    if ($course_id > 0) {
        $c_res = $conn->query("SELECT price FROM courses WHERE id = $course_id");
        if ($c_res && $c_res->num_rows > 0) {
            $c_row = $c_res->fetch_assoc();
            $payment_status = ($c_row['price'] > 0) ? 'pending' : 'free';
            
            $stmt = $conn->prepare("INSERT IGNORE INTO course_enrollments (course_id, student_id, status, payment_status) VALUES (?, ?, 'active', ?)");
            $stmt->bind_param("iss", $course_id, $user_id, $payment_status);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $enrollment_id = $stmt->insert_id;
                $success_msg = "Enrolled in course successfully!";
                if ($payment_status === 'pending') {
                    header("Location: course_payment_form.php?enrollment_id=" . $enrollment_id);
                    exit;
                }
            } else {
                $success_msg = "You are already enrolled in this course.";
            }
            $stmt->close();
        }
    }
}

// Handle Course Creation (Teacher Only)
if ($user_logged_in && $role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $duration = trim($_POST['duration'] ?? '');
    
    $cover_image = '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/courses/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed)) {
            $filename = uniqid('course_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $filename)) {
                $cover_image = 'uploads/courses/' . $filename;
            }
        }
    }
    
    if (empty($title)) {
        $error_msg = "Course title is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (teacher_id, title, description, price, cover_image, duration, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssdss", $user_id, $title, $description, $price, $cover_image, $duration);
        if ($stmt->execute()) {
            $success_msg = "Course created successfully!";
        } else {
            $error_msg = "Error creating course: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch Data for Student / Guest / Teacher
$enrolled_classes = [];
$enrolled_courses = [];
$enrolled_course_ids = [];
$enrolled_stream_subject_keys = [];
$my_teacher_courses = [];

if ($user_logged_in && $role === 'student') {
    // 1. Enrolled Subject Classes
    $enr_q = "SELECT se.id as enrollment_id, se.stream_subject_id, se.academic_year, se.payment_status, se.status as enrollment_status,
                     s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                     ta.id as teacher_assignment_id, ta.batch_name, ta.cover_image,
                     u.first_name, u.second_name, u.profile_picture as teacher_image
              FROM student_enrollment se
              INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              LEFT JOIN teacher_assignments ta ON (ta.stream_subject_id = ss.id AND ta.academic_year = se.academic_year AND ta.status = 'active')
              LEFT JOIN users u ON (ta.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci OR se.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci)
              WHERE se.student_id = ? AND se.status = 'active'
              ORDER BY se.enrolled_date DESC";
    $enr_stmt = $conn->prepare($enr_q);
    $enr_stmt->bind_param("s", $user_id);
    $enr_stmt->execute();
    $enr_res = $enr_stmt->get_result();
    while ($row = $enr_res->fetch_assoc()) {
        $row['teacher_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
        $enrolled_classes[] = $row;
        $enrolled_stream_subject_keys[] = $row['stream_subject_id'] . '_' . $row['academic_year'];
    }
    $enr_stmt->close();

    // 2. Enrolled Special Courses
    $c_enr_q = "SELECT c.*, ce.payment_status, ce.enrolled_at,
                       u.first_name, u.second_name, u.profile_picture as teacher_image
                FROM course_enrollments ce
                INNER JOIN courses c ON ce.course_id = c.id
                LEFT JOIN users u ON c.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                WHERE ce.student_id = ? AND ce.status = 'active'
                ORDER BY ce.enrolled_at DESC";
    $c_enr_stmt = $conn->prepare($c_enr_q);
    $c_enr_stmt->bind_param("s", $user_id);
    $c_enr_stmt->execute();
    $c_enr_res = $c_enr_stmt->get_result();
    while ($row = $c_enr_res->fetch_assoc()) {
        $row['teacher_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
        $enrolled_courses[] = $row;
        $enrolled_course_ids[] = $row['id'];
    }
    $c_enr_stmt->close();
} elseif ($user_logged_in && $role === 'teacher') {
    $t_stmt = $conn->prepare("SELECT * FROM courses WHERE teacher_id = ? ORDER BY created_at DESC");
    $t_stmt->bind_param("s", $user_id);
    $t_stmt->execute();
    $my_teacher_courses = $t_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $t_stmt->close();
}

// 3. Available Subject Classes (for everyone: guests and students)
$assign_q = "SELECT ta.*, s.name as stream_name, s.id as stream_id, sub.name as subject_name, sub.code as subject_code,
                    u.first_name, u.second_name, u.profile_picture as teacher_image,
                    (SELECT enrollment_fee FROM enrollment_fees WHERE teacher_assignment_id = ta.id LIMIT 1) as enrollment_fee,
                    (SELECT monthly_fee FROM enrollment_fees WHERE teacher_assignment_id = ta.id LIMIT 1) as monthly_fee
             FROM teacher_assignments ta
             INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
             INNER JOIN streams s ON ss.stream_id = s.id
             INNER JOIN subjects sub ON ss.subject_id = sub.id
             INNER JOIN users u ON ta.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
             WHERE ta.status = 'active'
             ORDER BY s.name, sub.name";
$assign_res = $conn->query($assign_q);
$available_classes = [];
$streams_list = [];
$years_list = [];
if ($assign_res) {
    while ($row = $assign_res->fetch_assoc()) {
        $row['teacher_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
        $available_classes[] = $row;
        if (!empty($row['stream_name']) && !in_array($row['stream_name'], $streams_list)) {
            $streams_list[] = $row['stream_name'];
        }
        if (!empty($row['academic_year']) && !in_array($row['academic_year'], $years_list)) {
            $years_list[] = $row['academic_year'];
        }
    }
    rsort($years_list);
}

// 4. Available Special Courses (for everyone)
$courses_q = "SELECT c.*, u.first_name, u.second_name, u.profile_picture as teacher_image
              FROM courses c
              LEFT JOIN users u ON c.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
              WHERE c.status = 1
              ORDER BY c.created_at DESC";
$courses_res = $conn->query($courses_q);
$available_extra_courses = [];
if ($courses_res) {
    while ($row = $courses_res->fetch_assoc()) {
        $row['teacher_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
        $available_extra_courses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Classes and Courses - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .bg-design {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-image: url('https://res.cloudinary.com/dnfbik3if/image/upload/v1791440549/Add_mixed_characters_peering_cor__20261008115221_sdaspr.jpg');
            background-size: 100% 100%;
            background-position: center top;
            background-repeat: no-repeat;
            z-index: 0;
            pointer-events-none;
        }
        @media (max-width: 768px) {
            .bg-design {
                background-size: 100% auto;
                background-position: top center;
            }
        }

        .content-overlay {
            min-height: 100vh;
            position: relative;
            z-index: 10;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.9);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
            border-color: #fca5a5;
        }
    </style>
</head>
<body class="min-h-screen bg-white relative">
    <div class="bg-design"></div>
    <?php include __DIR__ . '/navbar.php'; ?>

    <!-- Main Content -->
    <main class="content-overlay pt-20 sm:pt-24 pb-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Success Message -->
            <?php if (!empty($success_msg)): ?>
                <div class="max-w-4xl mx-auto mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl shadow-sm flex items-center justify-between" role="alert">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-green-500 text-lg"></i>
                        <p class="text-xs sm:text-sm font-bold"><?php echo htmlspecialchars($success_msg); ?></p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-green-700 font-bold hover:opacity-75">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($error_msg)): ?>
                <div class="max-w-4xl mx-auto mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl shadow-sm flex items-center justify-between" role="alert">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                        <p class="text-xs sm:text-sm font-bold"><?php echo htmlspecialchars($error_msg); ?></p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-700 font-bold hover:opacity-75">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Red Title Bar -->
            <div class="max-w-4xl mx-auto mb-3 sm:mb-4">
                <div class="relative rounded-2xl bg-red-600 p-4 sm:p-5 text-white shadow-lg shadow-red-600/20 flex items-center justify-between overflow-hidden">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-11 sm:h-11 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center flex-shrink-0 shadow-inner">
                            <i class="fas fa-graduation-cap text-base sm:text-lg text-white"></i>
                        </div>
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black tracking-tight leading-tight">
                            Online Classes and Courses
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Separate Subtext Section (Gray Color) -->
            <div class="max-w-4xl mx-auto mb-3 sm:mb-4">
                <div class="bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-sm">
                    <p class="text-[11.5px] sm:text-[14.5px] text-slate-800 font-medium leading-relaxed mb-1.5">
                        මෙහිදී ඔබ ලියාපදිංචි වී ඇති පන්ති නැරඹීමට මෙන්ම, නව විෂය පන්ති සහ බාහිර පාඨමාලා සඳහා ලියාපදිංචි වීමටද හැකියාව ඇත. ඔබට නොමිලේ ඇතුළත් විය හැකි බාහිර පාඨමාලා හෝ විෂය ධාරා නැරඹීමටද පළමුව අප ආයතනය හා <a href="../student_registration.php" class="text-red-600 font-bold underline hover:text-red-700 transition-colors">register</a> විය යුතුය.
                    </p>
                    <p class="text-[10.5px] sm:text-xs text-slate-600 font-medium leading-normal">
                        Here you can view your active enrolled classes, as well as discover and enroll in new subject classes and external courses. To access and view free courses or subject streams, you must first <a href="../student_registration.php" class="text-red-600 font-bold underline hover:text-red-700 transition-colors">register</a> with our institute.
                    </p>
                </div>
            </div>

            <!-- TEACHER VIEW: My Created Courses -->
            <?php if ($user_logged_in && $role === 'teacher'): ?>
                <div class="mb-12">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
                            <i class="fas fa-chalkboard-teacher text-red-600"></i>
                            <span>My Created Online Courses</span>
                        </h3>
                        <button onclick="openCreateModal()" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold uppercase tracking-wider rounded-xl shadow-md transition-all flex items-center gap-2">
                            <i class="fas fa-plus"></i> Create New Course
                        </button>
                    </div>

                    <?php if (empty($my_teacher_courses)): ?>
                        <div class="bg-white/80 backdrop-blur-md rounded-3xl p-10 text-center border border-slate-200 shadow-sm max-w-lg mx-auto">
                            <i class="fas fa-book-open text-slate-300 text-5xl mb-4"></i>
                            <h4 class="text-lg font-bold text-slate-800 mb-1">No Courses Created Yet</h4>
                            <p class="text-xs text-slate-500 mb-6">Start sharing your expertise by creating your first specialized online course.</p>
                            <button onclick="openCreateModal()" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold uppercase tracking-wider rounded-xl shadow-md transition-all">
                                Create Course Now
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($my_teacher_courses as $c): ?>
                                <div onclick="window.location.href='course_content.php?id=<?php echo $c['id']; ?>'" 
                                     class="glass-card rounded-2xl overflow-hidden cursor-pointer flex flex-col justify-between">
                                    <div>
                                        <div class="h-44 bg-slate-100 relative overflow-hidden">
                                            <?php if (!empty($c['cover_image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($c['cover_image']); ?>" alt="Cover" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center bg-slate-200 text-slate-400"><i class="fas fa-image text-4xl"></i></div>
                                            <?php endif; ?>
                                            <span class="absolute top-3 right-3 px-3 py-1 bg-white/90 backdrop-blur text-xs font-black text-slate-800 rounded-full shadow-sm">
                                                <?php echo $c['price'] > 0 ? 'Rs. ' . number_format($c['price'], 0) : 'FREE'; ?>
                                            </span>
                                        </div>
                                        <div class="p-5">
                                            <h4 class="text-base font-bold text-slate-900 mb-1.5 truncate"><?php echo htmlspecialchars($c['title']); ?></h4>
                                            <p class="text-xs text-slate-500 line-clamp-2 mb-3"><?php echo htmlspecialchars($c['description']); ?></p>
                                        </div>
                                    </div>
                                    <div class="p-5 pt-0 flex items-center justify-between border-t border-slate-100 mt-2 text-xs font-bold text-red-600">
                                        <span>Manage Lessons <i class="fas fa-arrow-right ml-1"></i></span>
                                        <?php if (!empty($c['duration'])): ?>
                                            <span class="text-slate-400 font-normal"><i class="far fa-clock mr-1"></i><?php echo htmlspecialchars($c['duration']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- SECTION 1: MY ENROLLED CLASSES & COURSES (Only for Logged-in Students) -->
            <?php if ($user_logged_in && $role === 'student'): ?>
                <div class="mb-14">
                    <div class="bg-white/85 backdrop-blur-md border border-slate-200/90 rounded-2xl p-4 sm:p-5 mb-6 shadow-xs flex items-center justify-between">
                        <div>
                            <h3 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2.5">
                                <span class="w-2.5 h-6 bg-red-600 rounded-full"></span>
                                <span>My Enrolled Classes & Courses</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-1 font-medium">Your current active subject enrollments and online programs</p>
                        </div>
                        <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-black">
                            <?php echo count($enrolled_classes) + count($enrolled_courses); ?> Active
                        </span>
                    </div>

                    <?php if (empty($enrolled_classes) && empty($enrolled_courses)): ?>
                        <div class="bg-white/80 backdrop-blur-md rounded-3xl p-8 sm:p-10 text-center border border-slate-200 shadow-sm max-w-md mx-auto mb-8">
                            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl shadow-inner">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <h4 class="text-base font-bold text-slate-800 mb-1">No Active Enrollments Found</h4>
                            <p class="text-xs text-slate-500 mb-4">Browse our available subjects and courses below to start learning today!</p>
                            <a href="#available-classes-section" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 text-white font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-red-700 transition-all shadow-md">
                                <i class="fas fa-compass"></i> Explore Available Classes
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            <!-- Enrolled Subject Classes -->
                            <?php foreach ($enrolled_classes as $enr): ?>
                                <div class="glass-card rounded-2xl overflow-hidden flex flex-col justify-between border-t-4 border-t-red-600 shadow-sm border border-slate-200/80">
                                    <div>
                                        <!-- Cover Image First -->
                                        <div class="relative aspect-[1.91/1] w-full overflow-hidden bg-slate-100">
                                            <?php if (!empty($enr['cover_image'])): ?>
                                                <img src="<?php echo htmlspecialchars(get_img_url($enr['cover_image'])); ?>" alt="<?php echo htmlspecialchars($enr['subject_name']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br <?php echo get_fallback_gradient($enr['subject_name']); ?> text-white p-4 text-center">
                                                    <i class="fas fa-book-open text-3xl mb-1.5 opacity-90"></i>
                                                    <span class="font-black text-sm drop-shadow-sm truncate max-w-full"><?php echo htmlspecialchars($enr['subject_name']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Floating Badges -->
                                            <div class="absolute top-3 left-3 flex flex-wrap items-center gap-1.5 z-10">
                                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-slate-900/85 backdrop-blur-md text-white border border-white/20 shadow-xs">
                                                    <?php echo htmlspecialchars($enr['stream_name']); ?> • <?php echo htmlspecialchars($enr['academic_year']); ?>
                                                </span>
                                            </div>
                                            <div class="absolute top-3 right-3 z-10">
                                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-600/90 backdrop-blur-md text-white shadow-xs border border-white/20 flex items-center gap-1">
                                                    <i class="fas fa-check-circle text-[9px]"></i> Active
                                                </span>
                                            </div>
                                        </div>

                                        <div class="p-5">
                                            <h4 class="text-lg font-black text-slate-900 leading-snug mb-3">
                                                <?php echo htmlspecialchars($enr['subject_name']); ?>
                                            </h4>

                                            <!-- Teacher Info (Clearly Displayed) -->
                                            <div class="flex items-center gap-3 p-3 bg-slate-50/90 rounded-xl mb-3 border border-slate-200/70 shadow-xs">
                                                <div class="w-11 h-11 rounded-full overflow-hidden ring-2 ring-red-500/30 flex-shrink-0 flex items-center justify-center bg-slate-200 shadow-sm">
                                                    <?php if (!empty($enr['teacher_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars(get_img_url($enr['teacher_image'])); ?>" alt="Teacher" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <i class="fas fa-user-tie text-base text-slate-500"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <span class="text-[9px] font-black text-red-600 uppercase tracking-widest block leading-none mb-1">Teacher</span>
                                                    <h5 class="text-sm font-black text-slate-900 truncate leading-tight"><?php echo htmlspecialchars($enr['teacher_name'] ?: 'Teacher'); ?></h5>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 p-5 pt-0 border-t border-slate-100">
                                        <a href="recordings.php" class="inline-flex items-center justify-center gap-1.5 py-2.5 px-3 bg-red-600 hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition-colors text-center">
                                            <i class="fas fa-video text-[11px]"></i> Recordings
                                        </a>
                                        <a href="live_classes.php" class="inline-flex items-center justify-center gap-1.5 py-2.5 px-3 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition-colors text-center">
                                            <i class="fas fa-broadcast-tower text-[11px]"></i> Live Class
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Enrolled Extra Courses -->
                            <?php foreach ($enrolled_courses as $c): ?>
                                <div class="glass-card rounded-2xl overflow-hidden flex flex-col justify-between border-t-4 border-t-emerald-600 shadow-sm border border-slate-200/80">
                                    <div>
                                        <!-- Cover Image First -->
                                        <div class="relative aspect-[1.91/1] w-full overflow-hidden bg-slate-100">
                                            <?php if (!empty($c['cover_image'])): ?>
                                                <img src="<?php echo htmlspecialchars(get_img_url($c['cover_image'])); ?>" alt="Cover" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br <?php echo get_fallback_gradient($c['title']); ?> text-white p-4 text-center">
                                                    <i class="fas fa-laptop-code text-3xl mb-1.5 opacity-90"></i>
                                                    <span class="font-black text-sm drop-shadow-sm truncate max-w-full"><?php echo htmlspecialchars($c['title']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="absolute top-3 left-3 z-10">
                                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-700/90 backdrop-blur-md text-white border border-white/20 shadow-xs">
                                                    Special Course
                                                </span>
                                            </div>
                                            <div class="absolute top-3 right-3 z-10">
                                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-600/90 backdrop-blur-md text-white shadow-xs border border-white/20 flex items-center gap-1">
                                                    <i class="fas fa-check-circle text-[9px]"></i> Enrolled
                                                </span>
                                            </div>
                                        </div>

                                        <div class="p-5">
                                            <h4 class="text-lg font-black text-slate-900 leading-snug mb-2 truncate">
                                                <?php echo htmlspecialchars($c['title']); ?>
                                            </h4>

                                            <!-- Teacher Info -->
                                            <div class="flex items-center gap-3 p-3 bg-slate-50/90 rounded-xl mb-3 border border-slate-200/70 shadow-xs">
                                                <div class="w-11 h-11 rounded-full overflow-hidden ring-2 ring-emerald-500/30 flex-shrink-0 flex items-center justify-center bg-slate-200 shadow-sm">
                                                    <?php if (!empty($c['teacher_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars(get_img_url($c['teacher_image'])); ?>" alt="Teacher" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <i class="fas fa-user-tie text-base text-slate-500"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <span class="text-[9px] font-black text-emerald-600 uppercase tracking-widest block leading-none mb-1">Teacher</span>
                                                    <h5 class="text-sm font-black text-slate-900 truncate leading-tight"><?php echo htmlspecialchars($c['teacher_name'] ?: 'Teacher'); ?></h5>
                                                </div>
                                            </div>

                                            <p class="text-xs text-slate-500 line-clamp-2"><?php echo htmlspecialchars($c['description']); ?></p>
                                        </div>
                                    </div>

                                    <div class="p-5 pt-0 border-t border-slate-100">
                                        <a href="course_content.php?id=<?php echo $c['id']; ?>" class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition-colors text-center">
                                            <i class="fas fa-play text-xs"></i> Continue Course Lessons
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- SECTION 2: AVAILABLE SUBJECT CLASSES TO ENROLL -->
            <div id="available-classes-section" class="mt-2 sm:mt-3 mb-12">
                <div class="bg-white/85 backdrop-blur-md border border-slate-200/90 rounded-2xl p-4 sm:p-5 mb-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2.5">
                            <span class="w-2.5 h-6 bg-red-600 rounded-full"></span>
                            <span>Available Subject Classes</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-1 font-medium">අපගේ ආයතනයෙන් ඔබට හැදෑරිය හැකි විෂය ධාරාවන්</p>
                    </div>

                    <!-- Search, Stream & Exam Year Filter -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="relative">
                            <input type="text" id="classSearchInput" oninput="filterClasses()" placeholder="Search subjects or teachers..." 
                                   class="pl-9 pr-4 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none w-52 sm:w-60 shadow-xs">
                            <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        </div>

                        <select id="streamFilterSelect" onchange="filterClasses()" class="py-2 px-3 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none cursor-pointer shadow-xs">
                            <option value="all">All Streams</option>
                            <?php foreach ($streams_list as $stream_name): ?>
                                <option value="<?php echo htmlspecialchars($stream_name); ?>"><?php echo htmlspecialchars($stream_name); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="yearFilterSelect" onchange="filterClasses()" class="py-2 px-3 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none cursor-pointer shadow-xs">
                            <option value="all">All Exam Years</option>
                            <?php foreach ($years_list as $yr): ?>
                                <option value="<?php echo htmlspecialchars($yr); ?>"><?php echo htmlspecialchars($yr); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if (empty($available_classes)): ?>
                    <div class="bg-white/80 backdrop-blur-md rounded-3xl p-12 text-center border border-slate-200 shadow-sm max-w-md mx-auto">
                        <i class="fas fa-chalkboard text-slate-300 text-5xl mb-3"></i>
                        <p class="text-sm font-bold text-slate-700">No active classes found at the moment.</p>
                    </div>
                <?php else: ?>
                    <div id="classesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($available_classes as $cls): 
                            $is_enrolled = in_array($cls['stream_subject_id'] . '_' . $cls['academic_year'], $enrolled_stream_subject_keys);
                            $monthly_fee_val = !empty($cls['monthly_fee']) ? floatval($cls['monthly_fee']) : 0;
                            $enr_fee_val = !empty($cls['enrollment_fee']) ? floatval($cls['enrollment_fee']) : 0;
                        ?>
                            <div class="class-card glass-card rounded-2xl overflow-hidden flex flex-col justify-between shadow-sm border border-slate-200/80"
                                 data-stream="<?php echo htmlspecialchars($cls['stream_name']); ?>"
                                 data-year="<?php echo htmlspecialchars($cls['academic_year']); ?>"
                                 data-search="<?php echo htmlspecialchars(strtolower($cls['subject_name'] . ' ' . $cls['teacher_name'] . ' ' . $cls['stream_name'] . ' ' . $cls['academic_year'])); ?>">
                                <div>
                                    <!-- 1. Cover Image (Displayed First) -->
                                    <div class="relative aspect-[1.91/1] w-full overflow-hidden bg-slate-100 border-b border-slate-100">
                                        <?php if (!empty($cls['cover_image'])): ?>
                                            <img src="<?php echo htmlspecialchars(get_img_url($cls['cover_image'])); ?>" alt="<?php echo htmlspecialchars($cls['subject_name']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br <?php echo get_fallback_gradient($cls['subject_name']); ?> text-white p-4 text-center">
                                                <i class="fas fa-book-open text-3xl mb-1.5 opacity-90"></i>
                                                <span class="font-black text-sm drop-shadow-sm truncate max-w-full"><?php echo htmlspecialchars($cls['subject_name']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Floating Stream & Year Badge -->
                                        <div class="absolute top-3 left-3 flex flex-wrap items-center gap-1.5 z-10">
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-slate-900/85 backdrop-blur-md text-white border border-white/20 shadow-xs">
                                                <?php echo htmlspecialchars($cls['stream_name']); ?>
                                            </span>
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white/90 backdrop-blur-md text-slate-800 shadow-xs">
                                                <?php echo htmlspecialchars($cls['academic_year']); ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($cls['batch_name'])): ?>
                                            <div class="absolute top-3 right-3 z-10">
                                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-amber-500/90 backdrop-blur-md text-white shadow-xs border border-white/20 truncate max-w-[130px]">
                                                    <?php echo htmlspecialchars($cls['batch_name']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- 2. Card Content -->
                                    <div class="p-5">
                                        <!-- Subject Name -->
                                        <h4 class="text-lg sm:text-xl font-black text-slate-900 leading-snug mb-3">
                                            <?php echo htmlspecialchars($cls['subject_name']); ?>
                                        </h4>

                                        <!-- 3. Teacher Profile & Name (Clearly Displayed) -->
                                        <div class="flex items-center gap-3 p-3 bg-slate-50/90 rounded-xl mb-4 border border-slate-200/80 shadow-xs">
                                            <div class="w-12 h-12 rounded-full overflow-hidden ring-2 ring-red-500/30 flex-shrink-0 flex items-center justify-center bg-slate-200 shadow-sm">
                                                <?php if (!empty($cls['teacher_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars(get_img_url($cls['teacher_image'])); ?>" alt="Teacher" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <i class="fas fa-user-tie text-lg text-slate-500"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-[9px] font-black text-red-600 uppercase tracking-widest block leading-none mb-1">Teacher</span>
                                                <h5 class="text-sm font-black text-slate-900 truncate leading-tight">
                                                    <?php echo htmlspecialchars($cls['teacher_name'] ?: 'Teacher'); ?>
                                                </h5>
                                            </div>
                                        </div>

                                        <!-- 4. Fees Breakdown -->
                                        <div class="flex items-center justify-between py-2.5 px-3.5 bg-slate-50/70 rounded-xl mb-2 text-xs border border-slate-200/60">
                                            <div>
                                                <span class="text-[10px] text-slate-500 block font-bold uppercase tracking-wider">Monthly Fee</span>
                                                <span class="font-black text-red-600 text-sm sm:text-base">
                                                    <?php echo $monthly_fee_val > 0 ? 'Rs. ' . number_format($monthly_fee_val, 0) : 'FREE'; ?>
                                                </span>
                                            </div>
                                            <?php if ($enr_fee_val > 0): ?>
                                                <div class="text-right">
                                                    <span class="text-[10px] text-slate-500 block font-bold uppercase tracking-wider">Admission Fee</span>
                                                    <span class="font-bold text-slate-800 text-xs sm:text-sm">Rs. <?php echo number_format($enr_fee_val, 0); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. Action Button -->
                                <div class="p-5 pt-0">
                                    <?php if ($is_enrolled): ?>
                                        <a href="recordings.php" class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 bg-emerald-50 text-emerald-700 font-black text-xs uppercase tracking-wider rounded-xl border border-emerald-200 transition-colors">
                                            <i class="fas fa-check-circle text-emerald-600"></i> Already Enrolled
                                        </a>
                                    <?php elseif ($user_logged_in && $role === 'student'): ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="enroll_subject_class" value="1">
                                            <input type="hidden" name="stream_subject_id" value="<?php echo $cls['stream_subject_id']; ?>">
                                            <input type="hidden" name="academic_year" value="<?php echo $cls['academic_year']; ?>">
                                            <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($cls['teacher_id']); ?>">
                                            <button type="submit" class="w-full py-3 px-4 bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95 flex items-center justify-center gap-2">
                                                <i class="fas fa-user-plus"></i> Enroll in Class
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Guest User Redirects to Registration -->
                                        <a href="../student_registration.php" class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95">
                                            <i class="fas fa-user-plus"></i> Register to Enroll
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="classesPagination" class="flex items-center justify-center gap-2 mt-8"></div>
                <?php endif; ?>
            </div>

            <!-- SECTION 3: SPECIAL ONLINE COURSES -->
            <div class="mb-14">
                <div class="bg-white/85 backdrop-blur-md border border-slate-200/90 rounded-2xl p-4 sm:p-5 mb-6 shadow-xs flex items-center justify-between">
                    <div>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2.5">
                            <span class="w-2.5 h-6 bg-red-600 rounded-full"></span>
                            <span>Special Online Courses</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-1 font-medium">අපගේ ආයතනයෙන් ඔබට හැදෑරිය හැකි බාහිර පාඨමාලා.</p>
                    </div>
                </div>

                <?php if (empty($available_extra_courses)): ?>
                    <div class="bg-white/80 backdrop-blur-md rounded-3xl p-12 text-center border border-slate-200 shadow-sm max-w-md mx-auto">
                        <i class="fas fa-laptop-code text-slate-300 text-5xl mb-3"></i>
                        <p class="text-sm font-bold text-slate-700">No special courses listed at the moment.</p>
                    </div>
                <?php else: ?>
                    <div id="coursesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($available_extra_courses as $c): 
                            $is_course_enrolled = in_array($c['id'], $enrolled_course_ids);
                            $price_num = floatval($c['price']);
                        ?>
                            <div class="course-card glass-card rounded-2xl overflow-hidden flex flex-col justify-between shadow-sm border border-slate-200/80">
                                <div>
                                    <!-- 1. Cover Image (Displayed First) -->
                                    <div class="relative aspect-[1.91/1] w-full overflow-hidden bg-slate-100 border-b border-slate-100">
                                        <?php if (!empty($c['cover_image'])): ?>
                                            <img src="<?php echo htmlspecialchars(get_img_url($c['cover_image'])); ?>" alt="Cover" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br <?php echo get_fallback_gradient($c['title']); ?> text-white p-4 text-center">
                                                <i class="fas fa-laptop-code text-3xl mb-1.5 opacity-90"></i>
                                                <span class="font-black text-sm drop-shadow-sm truncate max-w-full"><?php echo htmlspecialchars($c['title']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <span class="absolute top-3 right-3 px-3 py-1 bg-slate-900/85 backdrop-blur-md text-xs font-black text-white rounded-lg shadow-sm border border-white/20">
                                            <?php echo $price_num > 0 ? 'Rs. ' . number_format($price_num, 0) : 'FREE'; ?>
                                        </span>
                                    </div>

                                    <!-- 2. Course Details -->
                                    <div class="p-5">
                                        <h4 class="text-lg font-black text-slate-900 leading-snug mb-3 truncate">
                                            <?php echo htmlspecialchars($c['title']); ?>
                                        </h4>

                                        <!-- Teacher Info (Clearly Displayed) -->
                                        <div class="flex items-center gap-3 p-3 bg-slate-50/90 rounded-xl mb-4 border border-slate-200/80 shadow-xs">
                                            <div class="w-12 h-12 rounded-full overflow-hidden ring-2 ring-red-500/30 flex-shrink-0 flex items-center justify-center bg-slate-200 shadow-sm">
                                                <?php if (!empty($c['teacher_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars(get_img_url($c['teacher_image'])); ?>" alt="Teacher" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <i class="fas fa-user-tie text-lg text-slate-500"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-[9px] font-black text-red-600 uppercase tracking-widest block leading-none mb-1">Teacher</span>
                                                <h5 class="text-sm font-black text-slate-900 truncate leading-tight"><?php echo htmlspecialchars($c['teacher_name'] ?: 'Teacher'); ?></h5>
                                            </div>
                                        </div>

                                        <?php if (!empty($c['description'])): ?>
                                            <p class="text-xs text-slate-500 line-clamp-2 mb-3"><?php echo htmlspecialchars($c['description']); ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($c['duration'])): ?>
                                            <div class="flex items-center text-xs text-slate-500">
                                                <i class="far fa-clock mr-1.5 text-red-500"></i>
                                                <span>Duration: <strong class="text-slate-700"><?php echo htmlspecialchars($c['duration']); ?></strong></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="p-5 pt-0">
                                    <?php if ($is_course_enrolled): ?>
                                        <a href="course_content.php?id=<?php echo $c['id']; ?>" class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 bg-emerald-50 text-emerald-700 font-black text-xs uppercase tracking-wider rounded-xl border border-emerald-200 transition-colors">
                                            <i class="fas fa-play text-emerald-600"></i> Enrolled (Go to Lessons)
                                        </a>
                                    <?php elseif ($user_logged_in && $role === 'student'): ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" name="enroll_course" class="w-full py-3 px-4 bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95 flex items-center justify-center gap-2">
                                                <i class="fas fa-shopping-cart"></i> <?php echo $price_num > 0 ? 'Buy & Enroll' : 'Enroll Free'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Guest User Redirects to Registration -->
                                        <a href="../student_registration.php" class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95">
                                            <i class="fas fa-user-plus"></i> Register to Enroll
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="coursesPagination" class="flex items-center justify-center gap-2 mt-8"></div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Teacher Create Course Modal -->
    <?php if ($user_logged_in && $role === 'teacher'): ?>
        <div id="createCourseModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
                <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                    <h3 class="text-xl font-black text-slate-900 flex items-center gap-2">
                        <i class="fas fa-plus-circle text-red-600"></i> Create New Online Course
                    </h3>
                    <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Course Title *</label>
                        <input type="text" name="title" required placeholder="e.g. Complete A/L Chemistry Revision" 
                               class="w-full px-3.5 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Course Description</label>
                        <textarea name="description" rows="3" placeholder="Overview of what students will learn..."
                                  class="w-full px-3.5 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Price (Rs.) (0 for free)</label>
                            <input type="number" name="price" step="0.01" min="0" value="0" 
                                   class="w-full px-3.5 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Duration</label>
                            <input type="text" name="duration" placeholder="e.g. 8 Weeks / 24 Hours" 
                                   class="w-full px-3.5 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Course Cover Image</label>
                        <input type="file" name="cover_image" accept="image/*" 
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-red-600 file:text-white hover:file:bg-red-700 cursor-pointer">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" onclick="closeCreateModal()" class="px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit" name="create_course" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-md transition-all">
                            Publish Course
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function openCreateModal() {
            const modal = document.getElementById('createCourseModal');
            if (modal) modal.classList.remove('hidden');
        }
        function closeCreateModal() {
            const modal = document.getElementById('createCourseModal');
            if (modal) modal.classList.add('hidden');
        }

        const ITEMS_PER_PAGE = 6;
        let currentClassPage = 1;
        let currentCoursePage = 1;

        function renderPagination(containerId, totalPages, currentPage, onPageChange) {
            const container = document.getElementById(containerId);
            if (!container) return;
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';
            
            // Prev button
            const prevDisabled = currentPage === 1;
            html += `<button type="button" onclick="${onPageChange}(${currentPage - 1})" ${prevDisabled ? 'disabled' : ''} class="px-3.5 py-2 rounded-xl text-xs font-bold border transition-all ${prevDisabled ? 'border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50' : 'border-slate-300 text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-300 bg-white shadow-xs cursor-pointer'}">
                <i class="fas fa-chevron-left mr-1"></i> Prev
            </button>`;

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    const isActive = i === currentPage;
                    html += `<button type="button" onclick="${onPageChange}(${i})" class="w-9 h-9 rounded-xl text-xs font-black transition-all cursor-pointer ${isActive ? 'bg-red-600 text-white shadow-md shadow-red-600/30' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 shadow-xs'}">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    html += `<span class="px-1 text-slate-400 font-bold">...</span>`;
                }
            }

            // Next button
            const nextDisabled = currentPage === totalPages;
            html += `<button type="button" onclick="${onPageChange}(${currentPage + 1})" ${nextDisabled ? 'disabled' : ''} class="px-3.5 py-2 rounded-xl text-xs font-bold border transition-all ${nextDisabled ? 'border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50' : 'border-slate-300 text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-300 bg-white shadow-xs cursor-pointer'}">
                Next <i class="fas fa-chevron-right ml-1"></i>
            </button>`;

            container.innerHTML = html;
        }

        function goToClassPage(page) {
            currentClassPage = page;
            filterClasses(false);
            const el = document.getElementById('available-classes-section');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function goToCoursePage(page) {
            currentCoursePage = page;
            paginateSpecialCourses();
            const el = document.getElementById('coursesGrid');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Live Filter & Pagination for Available Subject Classes
        function filterClasses(resetPage = true) {
            if (resetPage) currentClassPage = 1;
            const searchInput = document.getElementById('classSearchInput');
            const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const streamSelect = document.getElementById('streamFilterSelect');
            const streamVal = streamSelect ? streamSelect.value : 'all';
            const yearSelect = document.getElementById('yearFilterSelect');
            const yearVal = yearSelect ? yearSelect.value : 'all';
            const cards = Array.from(document.querySelectorAll('.class-card'));

            const matchingCards = cards.filter(card => {
                const cardStream = card.getAttribute('data-stream');
                const cardYear = card.getAttribute('data-year');
                const cardSearch = card.getAttribute('data-search');

                const matchStream = (streamVal === 'all' || cardStream === streamVal);
                const matchYear = (yearVal === 'all' || cardYear === yearVal);
                const matchSearch = (!searchVal || (cardSearch && cardSearch.includes(searchVal)));

                return matchStream && matchYear && matchSearch;
            });

            // Hide all
            cards.forEach(c => c.style.display = 'none');

            // Calculate pagination
            const totalMatching = matchingCards.length;
            const totalPages = Math.ceil(totalMatching / ITEMS_PER_PAGE) || 1;
            if (currentClassPage > totalPages) currentClassPage = totalPages;

            const start = (currentClassPage - 1) * ITEMS_PER_PAGE;
            const end = start + ITEMS_PER_PAGE;
            const pageItems = matchingCards.slice(start, end);

            pageItems.forEach(c => c.style.display = '');

            renderPagination('classesPagination', totalPages, currentClassPage, 'goToClassPage');
        }

        // Pagination for Special Online Courses
        function paginateSpecialCourses() {
            const cards = Array.from(document.querySelectorAll('.course-card'));
            if (!cards.length) return;

            const totalItems = cards.length;
            const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE) || 1;
            if (currentCoursePage > totalPages) currentCoursePage = totalPages;

            cards.forEach(c => c.style.display = 'none');

            const start = (currentCoursePage - 1) * ITEMS_PER_PAGE;
            const end = start + ITEMS_PER_PAGE;
            const pageItems = cards.slice(start, end);

            pageItems.forEach(c => c.style.display = '');

            renderPagination('coursesPagination', totalPages, currentCoursePage, 'goToCoursePage');
        }

        document.addEventListener('DOMContentLoaded', () => {
            filterClasses(true);
            paginateSpecialCourses();
        });
    </script>
</body>
</html>
