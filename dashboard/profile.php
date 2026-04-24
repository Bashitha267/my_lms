<?php
// profile.php - User profile page for both students and teachers
require_once __DIR__ . '/../config.php';

// Start session safely if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if (empty($user_id)) {
    header("Location: ../login.php");
    exit();
}

if ($role === 'student') {
    require_once __DIR__ . '/../check_al_redirection.php';
}

// Get user info
$stmt = $conn->prepare("SELECT profile_picture, first_name, second_name, email, district, mobile_number FROM users WHERE user_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    error_log("Prepare failed in profile.php: " . $conn->error);
}

if (!$user_data) {
    echo "User data not found.";
    exit();
}

$full_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['second_name'] ?? ''));
if (empty($full_name)) $full_name = $user_id;

// Get ongoing live classes
$ongoing_classes = [];

if ($role === 'student') {
    // 1. YouTube Live for enrolled subjects
    $q1 = "SELECT r.id, r.title, r.status, 'youtube' as type, sub.name as subject_name, 
                  u.first_name as teacher_first, u.second_name as teacher_second, r.thumbnail_url
           FROM recordings r
           JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
           JOIN student_enrollment se ON ta.stream_subject_id = se.stream_subject_id AND ta.academic_year = se.academic_year
           JOIN subjects sub ON se.stream_subject_id = sub.id
           JOIN users u ON ta.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
           WHERE se.student_id = ? AND r.is_live = 1 AND r.status = 'ongoing' AND se.status = 'active'";
    $st1 = $conn->prepare($q1);
    $st1->bind_param("s", $user_id);
    $st1->execute();
    $res1 = $st1->get_result();
    while($row = $res1->fetch_assoc()) $ongoing_classes[] = $row;
    $st1->close();

    // 2. Zoom Classes for enrolled subjects
    $q2 = "SELECT zc.id, zc.title, zc.status, 'zoom' as type, sub.name as subject_name,
                  u.first_name as teacher_first, u.second_name as teacher_second
           FROM zoom_classes zc
           JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
           JOIN student_enrollment se ON ta.stream_subject_id = se.stream_subject_id AND ta.academic_year = se.academic_year
           JOIN subjects sub ON se.stream_subject_id = sub.id
           JOIN users u ON ta.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
           WHERE se.student_id = ? AND zc.status = 'ongoing' AND se.status = 'active'";
    $st2 = $conn->prepare($q2);
    $st2->bind_param("s", $user_id);
    $st2->execute();
    $res2 = $st2->get_result();
    while($row = $res2->fetch_assoc()) $ongoing_classes[] = $row;
    $st2->close();

    // 3. Instructor Personal Sessions (paid, with zoom link set in instructor_sessions)
    $q3 = "SELECT ir.id, CONCAT(s.name, ' — Private Session') as title, 'ongoing' as status,
                  'instructor_zoom' as type, s.name as subject_name, isess.zoom_link,
                  u.first_name as teacher_first, u.second_name as teacher_second
           FROM instructor_requests ir
           JOIN subjects s ON ir.subject_id = s.id
           JOIN users u ON ir.accepted_by = u.user_id
           JOIN instructor_sessions isess ON isess.request_id = ir.id
           WHERE ir.student_id = ? AND ir.status = 'paid'
             AND isess.zoom_link IS NOT NULL AND isess.zoom_link != ''
             AND isess.status != 'completed'";
    $st3 = $conn->prepare($q3);
    $st3->bind_param("s", $user_id);
    $st3->execute();
    $res3 = $st3->get_result();
    while($row = $res3->fetch_assoc()) $ongoing_classes[] = $row;
    $st3->close();

} elseif ($role === 'teacher') {
    // 1. YouTube Live created by this teacher
    $q1 = "SELECT r.id, r.title, r.status, 'youtube' as type, sub.name as subject_name,
                  u.first_name as teacher_first, u.second_name as teacher_second, r.thumbnail_url
           FROM recordings r
           JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
           JOIN subjects sub ON ta.stream_subject_id = sub.id
           JOIN users u ON ta.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
           WHERE ta.teacher_id = ? AND r.is_live = 1 AND r.status = 'ongoing'";
    $st1 = $conn->prepare($q1);
    $st1->bind_param("s", $user_id);
    $st1->execute();
    $res1 = $st1->get_result();
    while($row = $res1->fetch_assoc()) $ongoing_classes[] = $row;
    $st1->close();

    // 2. Zoom Classes created by this teacher
    $q2 = "SELECT zc.id, zc.title, zc.status, 'zoom' as type, sub.name as subject_name,
                  u.first_name as teacher_first, u.second_name as teacher_second
           FROM zoom_classes zc
           JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
           JOIN subjects sub ON ta.stream_subject_id = sub.id
           JOIN users u ON ta.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
           WHERE ta.teacher_id = ? AND zc.status = 'ongoing'";
    $st2 = $conn->prepare($q2);
    $st2->bind_param("s", $user_id);
    $st2->execute();
    $res2 = $st2->get_result();
    while($row = $res2->fetch_assoc()) $ongoing_classes[] = $row;
    $st2->close();

    // 3. Instructor Personal Sessions where this user is the accepted instructor (for teacher)
    $q3 = "SELECT ir.id, CONCAT(s.name, ' — Private Session') as title, 'ongoing' as status,
                  'instructor_zoom' as type, s.name as subject_name, isess.zoom_link,
                  u.first_name as teacher_first, u.second_name as teacher_second
           FROM instructor_requests ir
           JOIN subjects s ON ir.subject_id = s.id
           JOIN users u ON ir.accepted_by = u.user_id
           JOIN instructor_sessions isess ON isess.request_id = ir.id
           WHERE ir.accepted_by = ? AND ir.status = 'paid'
             AND isess.zoom_link IS NOT NULL AND isess.zoom_link != ''
             AND isess.status != 'completed'";
    $st3 = $conn->prepare($q3);
    $st3->bind_param("s", $user_id);
    $st3->execute();
    $res3 = $st3->get_result();
    while($row = $res3->fetch_assoc()) $ongoing_classes[] = $row;
    $st3->close();
}

// Get top 3 upcoming classes (Scheduled)
$upcoming_classes = [];
if ($role === 'student') {
    $q_up = "(SELECT r.id, r.title, r.scheduled_start_time, 'youtube' as type, sub.name as subject_name, 
                     u.first_name as teacher_first, u.second_name as teacher_second
              FROM recordings r
              JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              JOIN student_enrollment se ON ta.stream_subject_id = se.stream_subject_id AND ta.academic_year = se.academic_year
              JOIN subjects sub ON se.stream_subject_id = sub.id
              JOIN users u ON ta.teacher_id = u.user_id
              WHERE se.student_id = ? AND r.is_live = 1 AND r.status = 'scheduled' AND r.scheduled_start_time > NOW() AND se.status = 'active')
             UNION
             (SELECT z.id, z.title, z.scheduled_start_time, 'zoom' as type, sub.name as subject_name,
                     u.first_name as teacher_first, u.second_name as teacher_second
              FROM zoom_classes z
              JOIN teacher_assignments ta ON z.teacher_assignment_id = ta.id
              JOIN student_enrollment se ON ta.stream_subject_id = se.stream_subject_id AND ta.academic_year = se.academic_year
              JOIN subjects sub ON se.stream_subject_id = sub.id
              JOIN users u ON ta.teacher_id = u.user_id
              WHERE se.student_id = ? AND z.status = 'scheduled' AND z.scheduled_start_time > NOW() AND se.status = 'active')
             ORDER BY scheduled_start_time ASC LIMIT 3";
    $st_up = $conn->prepare($q_up);
    $st_up->bind_param("ss", $user_id, $user_id);
    $st_up->execute();
    $res_up = $st_up->get_result();
    while($row = $res_up->fetch_assoc()) $upcoming_classes[] = $row;
    $st_up->close();
} elseif ($role === 'teacher') {
    $q_up = "(SELECT r.id, r.title, r.scheduled_start_time, 'youtube' as type, sub.name as subject_name,
                     u.first_name as teacher_first, u.second_name as teacher_second
              FROM recordings r
              JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              JOIN subjects sub ON ta.stream_subject_id = sub.id
              JOIN users u ON ta.teacher_id = u.user_id
              WHERE ta.teacher_id = ? AND r.is_live = 1 AND r.status = 'scheduled' AND r.scheduled_start_time > NOW())
             UNION
             (SELECT z.id, z.title, z.scheduled_start_time, 'zoom' as type, sub.name as subject_name,
                     u.first_name as teacher_first, u.second_name as teacher_second
              FROM zoom_classes z
              JOIN teacher_assignments ta ON z.teacher_assignment_id = ta.id
              JOIN subjects sub ON ta.stream_subject_id = sub.id
              JOIN users u ON ta.teacher_id = u.user_id
              WHERE ta.teacher_id = ? AND z.status = 'scheduled' AND z.scheduled_start_time > NOW())
             ORDER BY scheduled_start_time ASC LIMIT 3";
    $st_up = $conn->prepare($q_up);
    $st_up->bind_param("ss", $user_id, $user_id);
    $st_up->execute();
    $res_up = $st_up->get_result();
    while($row = $res_up->fetch_assoc()) $upcoming_classes[] = $row;
    $st_up->close();
}

// Get published exams with upcoming deadlines
$upcoming_exams = [];
if ($role === 'student') {
    $q_ex = "SELECT e.id, e.title, e.deadline, sub.name as subject_name, 
                    u.first_name as teacher_first, u.second_name as teacher_second
             FROM exams e
             JOIN subjects sub ON e.subject_id = sub.id
             JOIN users u ON e.teacher_id = u.user_id
             JOIN student_enrollment se ON sub.id = se.stream_subject_id
             WHERE se.student_id = ? AND e.is_published = 1 AND e.status = 'active' 
               AND e.deadline > NOW() AND se.status = 'active'
             ORDER BY e.deadline ASC LIMIT 1";
    $st_ex = $conn->prepare($q_ex);
    $st_ex->bind_param("s", $user_id);
    $st_ex->execute();
    $res_ex = $st_ex->get_result();
    while($row = $res_ex->fetch_assoc()) $upcoming_exams[] = $row;
    $st_ex->close();
}

// Count recordings and check payment due dates (for the card stats)
$recordings_count = 0;
$payment_due_msg = "No pending payments";
$payment_due_msg_sinhala = "ගෙවීමට කිසිවක් නැත";
if ($role === 'student') {
    // Count available recordings
    $q_rec = "SELECT COUNT(*) as count FROM recordings r
              JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              JOIN student_enrollment se ON ta.stream_subject_id = se.stream_subject_id AND ta.academic_year = se.academic_year
              WHERE se.student_id = ? AND r.status = 'active' AND se.status = 'active'";
    $st_rec = $conn->prepare($q_rec);
    $st_rec->bind_param("s", $user_id);
    $st_rec->execute();
    $recordings_count = $st_rec->get_result()->fetch_assoc()['count'];
    $st_rec->close();

    // Just a placeholder for payment due date for now, ideally calc from monthly_payments
    $days_left = 30 - date('j');
    $payment_due_msg = "Due in " . $days_left . " days";
    $payment_due_msg_sinhala = "තව දින " . $days_left . " කින් ගෙවිය යුතුය";

    // Get latest 3 recordings (What's New) for ENROLLED subjects
    $latest_recordings = [];
    $q_lat = "SELECT r.id, r.title, sub.name as subject_name, r.thumbnail_url, 
                     u.first_name as teacher_first, u.second_name as teacher_second,
                     u.profile_picture as teacher_profile_picture
              FROM recordings r
              JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              JOIN student_enrollment se ON ta.stream_subject_id = se.stream_subject_id AND ta.academic_year = se.academic_year
              JOIN subjects sub ON ta.stream_subject_id = sub.id
              JOIN users u ON ta.teacher_id = u.user_id
              WHERE r.status = 'active' AND se.student_id = ? AND se.status = 'active'
              ORDER BY r.id DESC LIMIT 3";
    $st_lat = $conn->prepare($q_lat);
    $st_lat->bind_param("s", $user_id);
    $st_lat->execute();
    $res_lat = $st_lat->get_result();
    while($row = $res_lat->fetch_assoc()) $latest_recordings[] = $row;
    $st_lat->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/davidshimjs-qrcodejs@0.0.2/qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        
        .action-button {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .action-button:hover {
            transform: translateY(-8px) scale(1.02);
        }
        
        .live-pulse {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.025em;
        }
        
        .live-pulse::before {
            content: '';
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.3); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in {
            animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            opacity: 0;
        }

        .modern-card {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modern-card:hover {
            border-color: #e2e8f0;
            background: #fcfdfe;
            transform: translateY(-2px);
        }

        .live-session-card {
            background: #ffffff;
            border: 1px solid #fee2e2;
            border-radius: 2rem;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        .live-session-card:hover {
            border-color: #fca5a5;
            box-shadow: 0 25px 50px -12px rgba(239, 68, 68, 0.08);
        }

        /* Sinhala Tooltip Style */
        [data-sinhala] {
            position: relative;
            cursor: help;
        }
        [data-sinhala]:hover::after {
            content: attr(data-sinhala);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
            z-index: 50;
            pointer-events: none;
            opacity: 0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            animation: tooltipFadeIn 0.3s forwards;
        }
        @keyframes tooltipFadeIn {
            to { opacity: 1; transform: translate(-50%, -8px); }
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-900 min-h-screen flex flex-col">
    <?php include 'navbar.php'; ?>

    <main class="w-full px-4 sm:px-6 lg:px-12 py-8 flex-grow">
        

        <!-- Welcome Greeting -->
        <div class="mb-10 animate-fade-in" style="animation-delay: 0.05s">
            <h1 class="text-3xl md:text-5xl font-black text-[#1e293b] tracking-tight">ආයුබෝවන්, <span class="text-red-600"><?php echo htmlspecialchars($user_data['first_name'] ?? $user_id); ?>!</span></h1>
            <p class="text-slate-500 font-medium mt-2">Welcome back to your personalized learning workspace.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            <!-- Left Side: Main Content (8 columns) -->
            <div class="lg:col-span-8 space-y-16">
                
                <!-- Ongoing Live Classes (if any) -->
                <?php if (!empty($ongoing_classes)): ?>
                    <div class="animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-8 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-2xl font-black text-slate-900 tracking-tight" data-sinhala="දැනට පැවැත්වෙන පන්ති">Live Classroom</h2>
                                <p class="text-slate-400 text-xs font-medium mt-1">Happening right now. Join your session.</p>
                            </div>
                            <span class="live-pulse">ACTIVE NOW</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($ongoing_classes as $class): 
                                $teacher_name = trim(($class['teacher_first'] ?? '') . ' ' . ($class['teacher_second'] ?? ''));
                            ?>
                                <div class="live-session-card group flex flex-col shadow-sm">
                                    <div class="relative h-48">
                                        <?php if ($class['type'] === 'zoom'): ?>
                                            <div class="w-full h-full bg-[#1e293b] flex flex-col items-center justify-center text-white p-4">
                                                <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mb-3 shadow-xl shadow-blue-500/20">
                                                    <i class="fas fa-video text-2xl"></i>
                                                </div>
                                                <span class="text-[10px] font-black uppercase tracking-[0.2em] opacity-60">ZOOM SESSION</span>
                                            </div>
                                        <?php else: ?>
                                            <?php if (!empty($class['thumbnail_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($class['thumbnail_url']); ?>" class="w-full h-full object-cover" alt="Live Thumbnail">
                                            <?php else: ?>
                                                <div class="w-full h-full bg-[#1e293b] flex items-center justify-center text-white">
                                                    <i class="fab fa-youtube text-5xl text-red-600"></i>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php 
                                        $join_url = ($class['type'] === 'instructor_zoom') ? '../player/instructor_zoom.php?request_id=' . $class['id'] : 
                                                   (($class['type'] === 'zoom') ? '../player/zoom.php?id=' . $class['id'] : '../player/player.php?id=' . $class['id']);
                                        ?>
                                        <div class="absolute inset-0 bg-slate-900/60 opacity-0 group-hover:opacity-100 transition-all duration-500 flex flex-col items-center justify-center backdrop-blur-sm">
                                            <a href="<?php echo $join_url; ?>" class="bg-white text-slate-900 px-6 py-2.5 rounded-full text-sm font-bold shadow-2xl hover:scale-105 active:scale-95 transition-all" data-sinhala="පන්තියට එක්වන්න">Join Classroom</a>
                                        </div>
                                    </div>
                                    <div class="p-6 flex-1 flex flex-col bg-white">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-[10px] font-black text-red-600 uppercase tracking-widest flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 bg-red-600 rounded-full animate-pulse"></span>
                                                LIVE NOW
                                            </span>
                                            <span class="h-1 w-1 bg-slate-200 rounded-full"></span>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo htmlspecialchars($class['subject_name']); ?></span>
                                        </div>
                                        <h3 class="text-lg font-bold text-slate-900 mb-4 leading-tight line-clamp-2"><?php echo htmlspecialchars($class['title']); ?></h3>
                                        <div class="mt-auto flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-400">
                                                    <i class="fas fa-user-tie text-xs"></i>
                                                </div>
                                                <div>
                                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Instructor</p>
                                                    <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($teacher_name); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Newly Added Recordings -->
                <?php if ($role === 'student' && !empty($latest_recordings)): ?>
                <div class="animate-fade-in" style="animation-delay: 0.15s">
                    <div class="flex items-center justify-between mb-8 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-2xl font-black text-slate-900 tracking-tight" data-sinhala="අලුතින් එක් කළ පාඩම්">New Recordings</h2>
                            <p class="text-slate-400 text-xs font-medium mt-1">Recently added video lessons.</p>
                        </div>
                        <a href="recordings.php" class="text-red-600 font-bold text-xs hover:underline" data-sinhala="සියල්ල බලන්න">View All <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($latest_recordings as $rec): 
                            $teacher_name = trim(($rec['teacher_first'] ?? '') . ' ' . ($rec['teacher_second'] ?? ''));
                        ?>
                        <div class="modern-card overflow-hidden group shadow-sm">
                            <div class="relative h-40 overflow-hidden">
                                <?php if (!empty($rec['thumbnail_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($rec['thumbnail_url']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Recording Thumbnail">
                                <?php else: ?>
                                    <div class="w-full h-full bg-[#1e293b] flex items-center justify-center text-white group-hover:scale-110 transition-transform duration-500">
                                        <i class="fas fa-play-circle text-4xl opacity-20"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 to-transparent"></div>
                                <div class="absolute bottom-3 left-4">
                                    <span class="bg-red-600 text-white text-[9px] font-bold px-2 py-0.5 rounded">NEW</span>
                                </div>
                            </div>
                            <div class="p-5">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($rec['subject_name']); ?></p>
                                <h3 class="text-sm font-bold text-slate-900 mb-4 line-clamp-2 h-10"><?php echo htmlspecialchars($rec['title']); ?></h3>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($rec['teacher_profile_picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($rec['teacher_profile_picture']); ?>" class="w-6 h-6 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center text-[10px] text-slate-400">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <p class="text-[9px] font-bold text-slate-600"><?php echo htmlspecialchars($teacher_name); ?></p>
                                    </div>
                                    <a href="../player/player.php?id=<?php echo $rec['id']; ?>" class="w-7 h-7 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 hover:bg-red-600 hover:text-white transition-all">
                                        <i class="fas fa-play text-[9px]"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Right Side: Sidebar (4 columns) -->
            <div class="lg:col-span-4 space-y-12">
                
                <!-- Upcoming Classes Section -->
                <div class="animate-fade-in" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-8">
                        <h2 class="text-xl font-black text-[#1e293b] tracking-tight" data-sinhala="ඉදිරි පන්ති">Schedule</h2>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($upcoming_classes)): ?>
                            <div class="bg-white rounded-2xl p-8 border border-dashed border-gray-200 text-center">
                                <p class="text-gray-400 text-sm font-medium">No upcoming classes</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_classes as $item): 
                                $class_time = strtotime($item['scheduled_start_time']);
                                $day = date('d', $class_time);
                                $month = strtoupper(date('M', $class_time));
                                $time_str = date('h:i A', $class_time);
                                $teacher_name = trim(($item['teacher_first'] ?? '') . ' ' . ($item['teacher_second'] ?? ''));
                            ?>
                            <div class="bg-white rounded-2xl p-4 flex items-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
                                <!-- Date Badge -->
                                <div class="bg-[#f0f4ff] rounded-xl p-3 flex flex-col items-center justify-center min-w-[60px] mr-4">
                                    <span class="text-[9px] font-bold text-[#4c6ef5] uppercase tracking-widest"><?php echo $month; ?></span>
                                    <span class="text-xl font-black text-[#1e293b]"><?php echo $day; ?></span>
                                </div>

                                <!-- Class Info -->
                                <div class="flex-1 overflow-hidden">
                                    <h3 class="text-sm font-bold text-[#1e293b] mb-1 leading-tight truncate"><?php echo htmlspecialchars($item['title']); ?></h3>
                                    <div class="flex flex-col gap-0.5">
                                        <div class="flex items-center text-slate-500 text-[10px] font-medium">
                                            <i class="far fa-clock mr-1.5 text-[#4c6ef5]"></i>
                                            <?php echo $time_str; ?>
                                        </div>
                                        <div class="flex items-center text-slate-400 text-[10px] font-medium">
                                            <i class="far fa-user mr-1.5"></i>
                                            <?php echo htmlspecialchars($teacher_name); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>


            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-100 py-12 px-6">
        <div class="max-w-7xl mx-auto flex flex-col items-center">
            <div class="text-center">
                <h2 class="text-2xl font-black text-[#1e293b] mb-2 tracking-tight">Lernerr<span class="text-red-600">.LK</span></h2>
                <p class="text-slate-400 text-[10px] uppercase font-bold tracking-[0.4em] mb-4">&copy; <?php echo date('Y'); ?> Digital Learning Ecosystem</p>
                <div class="h-1.5 w-12 bg-red-600 rounded-full mx-auto"></div>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // High Quality QR Generation
            if(typeof QRCode !== 'undefined') {
                new QRCode(document.getElementById("qrCodeContainer"), {
                    text: "<?php echo $user_id; ?>",
                    width: 140,
                    height: 140,
                    colorDark : "#0f172a",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });
    </script>
</body>
</html>
