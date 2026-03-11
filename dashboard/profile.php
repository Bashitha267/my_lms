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
           JOIN users u ON ta.teacher_id = u.user_id
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
           JOIN users u ON ta.teacher_id = u.user_id
           WHERE se.student_id = ? AND zc.status = 'ongoing' AND se.status = 'active'";
    $st2 = $conn->prepare($q2);
    $st2->bind_param("s", $user_id);
    $st2->execute();
    $res2 = $st2->get_result();
    while($row = $res2->fetch_assoc()) $ongoing_classes[] = $row;
    $st2->close();

} elseif ($role === 'teacher') {
    // 1. YouTube Live created by this teacher
    $q1 = "SELECT r.id, r.title, r.status, 'youtube' as type, sub.name as subject_name,
                  u.first_name as teacher_first, u.second_name as teacher_second, r.thumbnail_url
           FROM recordings r
           JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
           JOIN subjects sub ON ta.stream_subject_id = sub.id
           JOIN users u ON ta.teacher_id = u.user_id
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
           JOIN users u ON ta.teacher_id = u.user_id
           WHERE ta.teacher_id = ? AND zc.status = 'ongoing'";
    $st2 = $conn->prepare($q2);
    $st2->bind_param("s", $user_id);
    $st2->execute();
    $res2 = $st2->get_result();
    while($row = $res2->fetch_assoc()) $ongoing_classes[] = $row;
    $st2->close();
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

        .animate-fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-900">
    <?php include 'navbar.php'; ?>

    <main class="w-full px-4 sm:px-6 lg:px-8 py-6">
        
        <!-- Header Greeting -->
        <div class="mb-8 animate-fade-in">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                ආයුබෝවන්, <span class="text-red-600"><?php echo htmlspecialchars($full_name); ?></span>
            </h1>
            <div class="h-1 w-12 bg-red-600 rounded-full mt-2"></div>
        </div>
        
        <!-- Ongoing Live Classes Banner -->
        <?php if (!empty($ongoing_classes)): ?>
            <div class="mb-6 animate-fade-in" style="animation-delay: 0.1s">
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-slate-200">
                    <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center space-x-3">
                            <span class="live-pulse">LIVE NOW</span>
                            <h2 class="text-slate-900 font-bold text-sm">Ongoing Live Classes</h2>
                        </div>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <?php foreach ($ongoing_classes as $class): 
                            $teacher_name = trim(($class['teacher_first'] ?? '') . ' ' . ($class['teacher_second'] ?? ''));
                        ?>
                            <a href="<?php echo ($class['type'] === 'zoom') ? 'live_classes.php' : '../player/player.php?id='.$class['id']; ?>" 
                               class="bg-white hover:shadow-lg transition-all rounded-2xl overflow-hidden group border border-slate-100 flex flex-col">
                                
                                <!-- Thumbnail Section -->
                                <div class="relative h-28 overflow-hidden">
                                    <?php if ($class['type'] === 'zoom'): ?>
                                        <div class="w-full h-full bg-blue-600 flex flex-col items-center justify-center text-white p-4 text-center">
                                            <i class="fas fa-video text-3xl mb-2 opacity-80 group-hover:scale-110 transition-transform duration-500"></i>
                                            <span class="text-sm font-black uppercase tracking-tighter">ZOOM CLASS</span>
                                        </div>
                                    <?php else: ?>
                                        <?php if (!empty($class['thumbnail_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($class['thumbnail_url']); ?>" 
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                                 alt="Live Thumbnail">
                                        <?php else: ?>
                                            <div class="w-full h-full bg-slate-900 flex items-center justify-center text-white">
                                                <i class="fab fa-youtube text-3xl text-red-600"></i>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div class="absolute top-2 left-2">
                                        <span class="bg-red-600 text-white text-[8px] font-black px-2 py-0.5 rounded-full flex items-center gap-1 shadow-lg">
                                            LIVE
                                        </span>
                                    </div>
                                </div>

                                <div class="p-4 flex-1 flex flex-col">
                                    <h3 class="text-slate-900 font-bold text-xs line-clamp-2 group-hover:text-red-500 transition-colors mb-2 leading-tight"><?php echo htmlspecialchars($class['title']); ?></h3>
                                    
                                    <div class="mt-auto space-y-1">
                                        <div class="flex items-center text-slate-500 text-[9px] font-bold uppercase tracking-wide">
                                            <i class="fas fa-book-open w-4 text-red-500"></i>
                                            <?php echo htmlspecialchars($class['subject_name']); ?>
                                        </div>
                                        <div class="flex items-center text-slate-400 text-[8px] font-bold uppercase tracking-widest">
                                            <i class="fas fa-user-tie w-4"></i>
                                            By <?php echo htmlspecialchars($teacher_name); ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="w-full space-y-6 animate-fade-in" style="animation-delay: 0.3s">
            
            <section>
                <div class="flex items-center gap-3 mb-6">
                    <div class="h-6 w-1.5 bg-red-600 rounded-full"></div>
                    <h2 class="text-lg font-black text-slate-900 tracking-tight uppercase">Quick Access</h2>
                </div>
                
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Live Classes -->
                    <a href="live_classes.php" class="action-button bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col items-center group">
                        <div class="w-14 h-14 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-red-500 group-hover:text-white shadow-sm transition-all duration-500">
                            <i class="fas fa-broadcast-tower text-xl"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-xs tracking-wide">සජීවී පන්ති</h3>
                        <p class="text-[8px] text-slate-400 font-bold mt-1 uppercase tracking-widest">Live Classes</p>
                    </a>

                    <!-- Recordings -->
                    <a href="recordings.php" class="action-button bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col items-center group">
                        <div class="w-14 h-14 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-indigo-500 group-hover:text-white shadow-sm transition-all duration-500">
                            <i class="fas fa-film text-xl"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-xs tracking-wide">රෙකෝඩින්</h3>
                        <p class="text-[8px] text-slate-400 font-bold mt-1 uppercase tracking-widest">Recordings</p>
                    </a>

                    <!-- Payments -->
                    <a href="payments.php" class="action-button bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col items-center group">
                        <div class="w-14 h-14 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-emerald-500 group-hover:text-white shadow-sm transition-all duration-500">
                            <i class="fas fa-wallet text-xl"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-xs tracking-wide">ගෙවීම්</h3>
                        <p class="text-[8px] text-slate-400 font-bold mt-1 uppercase tracking-widest">Payments</p>
                    </a>

                    <!-- Exams -->
                    <a href="exam_center.php" class="action-button bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col items-center group">
                        <div class="w-14 h-14 bg-violet-50 text-violet-500 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-violet-500 group-hover:text-white shadow-sm transition-all duration-500">
                            <i class="fas fa-medal text-xl"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-xs tracking-wide">විභාග</h3>
                        <p class="text-[8px] text-slate-400 font-bold mt-1 uppercase tracking-widest">Exams</p>
                    </a>

                    <!-- Publications -->
                    <a href="publications.php" class="action-button bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col items-center group">
                        <div class="w-14 h-14 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-rose-500 group-hover:text-white shadow-sm transition-all duration-500">
                            <i class="fas fa-bookmark text-xl"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-xs tracking-wide">නිබන්ධන</h3>
                        <p class="text-[8px] text-slate-400 font-bold mt-1 uppercase tracking-widest">Resources</p>
                    </a>
                </div>
            </section>
        </div>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 border-t border-slate-800 mt-10 py-10 px-4">
        <div class="max-w-7xl mx-auto flex flex-col items-center">
            <div class="text-center">
                <h2 class="text-xl font-black text-white mb-1 tracking-tighter">Lernerr<span class="text-red-600">.LK</span></h2>
                <p class="text-slate-500 text-[8px] uppercase font-black tracking-[0.3em]">&copy; <?php echo date('Y'); ?> Digital Learning Ecosystem</p>
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
