<?php
require_once __DIR__ . '/../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$is_logged_in = !empty($user_id);

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
        while($row = $enr_res->fetch_assoc()) {
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
            while($row = $ep_res->fetch_assoc()) {
                foreach($user_enrollment_data as $ssid => $data) {
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
             while($row = $mp_res->fetch_assoc()) {
                  foreach($user_enrollment_data as $ssid => $data) {
                         if ($data['id'] == $row['student_enrollment_id']) {
                             // Only set if not already set by a newer record
                             if (!isset($user_enrollment_data[$ssid]['monthly_status_raw'])) {
                                 $user_enrollment_data[$ssid]['monthly_status_raw'] = $row['payment_status'];
                                 $st = $row['payment_status'];
                                 if ($st == 'paid' || $st == 'approved') $st = 'Paid';
                                 elseif ($st == 'pending') $st = 'Pending';
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_logged_in ? 'Dashboard' : 'Welcome'; ?> - Learner.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* Custom Scrollbar for modern look */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #fca5a5;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar for all users -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="w-full pb-12">
<style>
* { box-sizing: border-box; }
</style>

        <?php if (!$is_logged_in): ?>
            <!-- Full Screen Hero Section -->
            <section class="relative min-h-screen flex items-center overflow-hidden mb-8 bg-cover bg-center bg-no-repeat" style="background-image: url('../assests/rdbg.jpg');">
            <!-- Dark Overlay for better contrast -->
            <div class="absolute inset-0 bg-slate-900/20 z-0"></div>
            <!-- Subtle background decorative elements to fill space -->
            <div class="absolute top-20 right-20 w-64 h-64 bg-red-600/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 left-20 w-96 h-96 bg-red-600/5 rounded-full blur-3xl"></div>

            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-12 w-full">
                <div class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                    
                    <!-- Right Side: Student Image (Top on mobile) -->
                    <div class="lg:col-span-5 order-first lg:order-last relative animate-fade-in-right">
                        <div class="relative z-10 w-full max-w-[280px] md:max-w-md mx-auto mt-16 lg:mt-0">
                            <img src="../assests/student.png" alt="Student" class="w-full h-auto drop-shadow-2xl scale-110 lg:scale-125">
                            
                            <!-- Floating UI Decorations -->
                            <div class="absolute -top-4 -right-4 bg-white/90 backdrop-blur-md p-2 rounded-xl shadow-lg animate-bounce-slow border border-white/50">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-star text-[10px]"></i>
                                    </div>
                                    <div>
                                        <p class="text-[7px] text-slate-400 font-black uppercase leading-none">Top Rated</p>
                                        <p class="text-[9px] text-slate-800 font-black">#1 Academy</p>
                                    </div>
                                </div>
                            </div>

                            <div class="absolute bottom-10 -left-6 bg-white/90 backdrop-blur-md p-2 rounded-xl shadow-lg animate-bounce-slow delay-700 border border-white/50">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user-tie text-[10px]"></i>
                                    </div>
                                    <div>
                                        <p class="text-[7px] text-slate-400 font-black uppercase leading-none">Instructors</p>
                                        <p class="text-[9px] text-slate-800 font-black">10+ Teachers</p>
                                    </div>
                                </div>
                            </div>

                            <div class="absolute top-1/2 -right-8 bg-white/90 backdrop-blur-md p-2 rounded-xl shadow-lg animate-bounce-slow delay-500 border border-white/50">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-users text-[10px]"></i>
                                    </div>
                                    <div>
                                        <p class="text-[7px] text-slate-400 font-black uppercase leading-none">Community</p>
                                        <p class="text-[9px] text-slate-800 font-black">1000+ Students</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Backdrop decoration -->
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[300px] lg:w-[550px] h-[300px] lg:h-[550px] bg-red-600/5 rounded-full blur-3xl -z-0"></div>
                    </div>

                    <!-- Left Side: Text and Login -->
                    <div class="lg:col-span-7 text-left animate-fade-in-left relative z-10">
                        <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold text-slate-800 leading-tight mb-6 tracking-tight">
                            Empower Your Future. <br>
                            <span class="text-red-600">Start Learning Today.</span>
                        </h1>
                        <p class="max-w-2xl text-lg md:text-xl text-slate-600 font-medium mb-4 leading-relaxed">
                            Access high-quality live classes, recorded sessions, and professional courses tailored for your success.
                        </p>
                        <p class="max-w-2xl text-sm md:text-base text-slate-500 font-normal mb-10 leading-relaxed">
                            ලංකාවේ සාර්ථකම online ඇකඩමියට ඔබව සාදරයෙන් පිළිගන්නවා. ඔබ දැනටමත් කුමන හෝ පාඨමාලාවක් සඳහා ලියාපදිංචි වී ඇත්නම් ඔබගේ දුරකතන අංකය හා Password නිවැරදිව ලබා දී Login වෙන්න. 
                            අලුතින්ම සම්බන්ධ වීම සඳහා ඉහත ඇති Register Button එක click කරන්න.
                        </p>
                        <!-- Login Bar -->
                        <div class="max-w-2xl pb-16 lg:pb-0">
                            <form action="../auth.php" method="POST" class="bg-white p-2 rounded-2xl md:rounded-full border border-slate-200 shadow-xl flex flex-col md:flex-row items-center gap-2 transition-all hover:shadow-2xl">
                                <div class="flex-1 flex items-center px-6 py-2 w-full">
                                    <i class="fas fa-mobile-alt text-slate-500 mr-3 text-sm"></i>
                                    <input type="text" name="identifier" required placeholder="Mobile Number" class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-slate-800 placeholder-slate-500 font-bold text-sm">
                                </div>
                                <div class="flex-1 flex items-center px-6 py-2 w-full border-t md:border-t-0 md:border-l border-slate-100">
                                    <i class="fas fa-lock text-slate-500 mr-3 text-sm"></i>
                                    <input type="password" name="password" required placeholder="Password" class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-slate-800 placeholder-slate-500 font-bold text-sm">
                                </div>
                                <button type="submit" name="login" class="w-full md:w-auto bg-red-600 text-white px-12 py-4 rounded-xl md:rounded-full font-black text-sm hover:bg-red-700 transition-all shadow-lg shadow-red-600/20 active:scale-95 whitespace-nowrap">
                                    Login Now
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            </section>

            <!-- Results Section: Automatic Moving Slider -->
            <section class="py-12 bg-gray-50 overflow-hidden">
                <div class="max-w-7xl mx-auto px-4 mb-10 text-center">
                    <h2 class="text-3xl font-black text-slate-800 tracking-tight uppercase">Our Top Results</h2>
                    <div class="w-20 h-1 bg-red-600 mx-auto mt-4 rounded-full"></div>
                </div>

                <!-- Slider Container -->
                <div class="relative marquee-container py-8">
                    <div class="flex overflow-hidden group">
                        <!-- First Marquee Group -->
                        <div class="flex animate-marquee whitespace-nowrap gap-6 group-hover:pause">
                            <?php 
                            $results = [
                                ['name' => 'Nipuna Diyalagoda', 'id' => '123456', 'stream' => 'Physical Science', 'year' => '2025', 'maths' => 'A', 'physics' => 'A', 'chem' => 'A', 'district' => 'Gampaha', 'drank' => '24', 'irank' => '325'],
                                ['name' => 'Kasun Perera', 'id' => '654321', 'stream' => 'Biological Science', 'year' => '2025', 'bio' => 'A', 'physics' => 'A', 'chem' => 'A', 'district' => 'Colombo', 'drank' => '12', 'irank' => '105'],
                                ['name' => 'Sanduni Silva', 'id' => '789012', 'stream' => 'Commerce', 'year' => '2025', 'acc' => 'A', 'econ' => 'A', 'bs' => 'A', 'district' => 'Kandy', 'drank' => '05', 'irank' => '42'],
                                ['name' => 'Malith Ranasinghe', 'id' => '345678', 'stream' => 'Physical Science', 'year' => '2025', 'maths' => 'A', 'physics' => 'A', 'chem' => 'A', 'district' => 'Matara', 'drank' => '18', 'irank' => '210'],
                                ['name' => 'Dulani Mendis', 'id' => '901234', 'stream' => 'Biological Science', 'year' => '2025', 'bio' => 'A', 'physics' => 'A', 'chem' => 'A', 'district' => 'Galle', 'drank' => '08', 'irank' => '88']
                            ];
                            
                            foreach ($results as $res): ?>
                            <div class="flex-shrink-0 w-80 bg-white rounded-2xl shadow-[0_10px_40px_rgba(0,0,0,0.06)] p-6 border border-slate-100 mx-3">
                                <div class="flex items-center gap-4 mb-6">
                                    <div class="w-16 h-16 rounded-full overflow-hidden bg-slate-100 border-2 border-red-50">
                                        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo urlencode($res['name']); ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div class="whitespace-normal">
                                        <h3 class="font-black text-slate-800 text-base leading-tight"><?php echo $res['name']; ?></h3>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Adm No: <?php echo $res['id']; ?></p>
                                        <p class="text-[10px] text-red-600 font-black uppercase"><?php echo $res['stream']; ?> - <?php echo $res['year']; ?></p>
                                    </div>
                                </div>
                                
                                <div class="space-y-3 mb-6">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-bold text-slate-600"><?php echo isset($res['maths']) ? 'Combined Mathematics' : (isset($res['bio']) ? 'Biology' : 'Accounting'); ?></span>
                                        <span class="text-xl font-black text-red-600">A</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-bold text-slate-600"><?php echo isset($res['physics']) ? 'Physics' : 'Economics'; ?></span>
                                        <span class="text-xl font-black text-red-600">A</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-bold text-slate-600"><?php echo isset($res['chem']) ? 'Chemistry' : 'Business Studies'; ?></span>
                                        <span class="text-xl font-black text-red-600">A</span>
                                    </div>
                                </div>
                                
                                <div class="pt-4 border-t border-slate-50 grid grid-cols-3 gap-2 text-center">
                                    <div><p class="text-[8px] text-slate-400 font-bold uppercase">District</p><p class="text-xs font-black text-slate-700"><?php echo $res['district']; ?></p></div>
                                    <div><p class="text-[8px] text-slate-400 font-bold uppercase">Dist. Rank</p><p class="text-xs font-black text-slate-700"><?php echo $res['drank']; ?></p></div>
                                    <div><p class="text-[8px] text-slate-400 font-bold uppercase">Island Rank</p><p class="text-xs font-black text-slate-700"><?php echo $res['irank']; ?></p></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Second Marquee Group -->
                        <div class="flex animate-marquee whitespace-nowrap gap-6 group-hover:pause" aria-hidden="true">
                            <?php foreach ($results as $res): ?>
                            <div class="flex-shrink-0 w-80 bg-white rounded-2xl shadow-[0_10px_40px_rgba(0,0,0,0.06)] p-6 border border-slate-100 mx-3">
                                <div class="flex items-center gap-4 mb-6">
                                    <div class="w-16 h-16 rounded-full overflow-hidden bg-slate-100 border-2 border-red-50">
                                        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo urlencode($res['name']); ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div class="whitespace-normal">
                                        <h3 class="font-black text-slate-800 text-base leading-tight"><?php echo $res['name']; ?></h3>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Adm No: <?php echo $res['id']; ?></p>
                                        <p class="text-[10px] text-red-600 font-black uppercase"><?php echo $res['stream']; ?> - <?php echo $res['year']; ?></p>
                                    </div>
                                </div>
                                <div class="space-y-3 mb-6">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-bold text-slate-600"><?php echo isset($res['maths']) ? 'Combined Mathematics' : (isset($res['bio']) ? 'Biology' : 'Accounting'); ?></span>
                                        <span class="text-xl font-black text-red-600">A</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-bold text-slate-600"><?php echo isset($res['physics']) ? 'Physics' : 'Economics'; ?></span>
                                        <span class="text-xl font-black text-red-600">A</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-bold text-slate-600"><?php echo isset($res['chem']) ? 'Chemistry' : 'Business Studies'; ?></span>
                                        <span class="text-xl font-black text-red-600">A</span>
                                    </div>
                                </div>
                                <div class="pt-4 border-t border-slate-50 grid grid-cols-3 gap-2 text-center">
                                    <div><p class="text-[8px] text-slate-400 font-bold uppercase">District</p><p class="text-xs font-black text-slate-700"><?php echo $res['district']; ?></p></div>
                                    <div><p class="text-[8px] text-slate-400 font-bold uppercase">Dist. Rank</p><p class="text-xs font-black text-slate-700"><?php echo $res['drank']; ?></p></div>
                                    <div><p class="text-[8px] text-slate-400 font-bold uppercase">Island Rank</p><p class="text-xs font-black text-slate-700"><?php echo $res['irank']; ?></p></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- See All Button -->
                <div class="mt-8 text-center">
                    <a href="ALDetails.php" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-8 py-3 rounded-full font-bold transition-all shadow-lg shadow-red-600/30 hover:shadow-red-600/50 transform hover:-translate-y-0.5 active:scale-95 uppercase text-sm">
                        <span>See All Results</span>
                        <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                </div>

                <style>
                @keyframes marquee {
                    0% { transform: translateX(0); }
                    100% { transform: translateX(-100%); }
                }
                .animate-marquee {
                    animation: marquee 40s linear infinite;
                }
                .group:hover .animate-marquee {
                    animation-play-state: paused;
                }
                </style>
            </section>

        <?php else: ?>
            <!-- Welcome Section for Logged In Users -->
            <div class="px-4 mb-8">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h1 class="text-3xl font-bold text-gray-900">
                       ආයුබෝවන් <span class="ml-2 text-red-600"><?php echo htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['second_name'] ?? '')) ?: 'User'); ?></span>
                    </h1>
                </div>
            </div>
        <?php endif; ?>
        <!-- Bento Grid Gallery Section -->
        <section class="mb-4">
            <?php 
            $gallery_images = [];
            $posts_result = $conn->query("SELECT image_path FROM home_posts ORDER BY created_at DESC");
            if ($posts_result && $posts_result->num_rows > 0) {
                while ($row = $posts_result->fetch_assoc()) {
                    // Prepend ../ because dashboard.php is in the /dashboard/ folder
                    $gallery_images[] = '../' . $row['image_path'];
                }
            }
            
            shuffle($gallery_images);
            $chunks = array_chunk($gallery_images, 5); // Split into groups of 5 for the pattern (1 large + 4 small)
            ?>
            
            <div class="space-y-[2px]">
                <?php foreach ($chunks as $chunkIndex => $chunk): ?>
                <!-- Bento Block -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-[2px]">
                    <!-- Left: Large vertical image (spanning 6 columns) -->
                    <?php if (isset($chunk[0])): ?>
                    <div class="md:col-span-6 h-[350px] md:h-[700px] overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                         onclick="openImageModal('<?php echo $chunk[0]; ?>')">
                        <img src="<?php echo $chunk[0]; ?>" 
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" 
                             alt="Gallery Image">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Right: Grid of smaller images (spanning 6 columns) -->
                    <div class="md:col-span-6 grid grid-rows-2 gap-[2px] h-[400px] md:h-[700px]">
                        <!-- Top row: 2 images side by side -->
                        <div class="grid grid-cols-2 gap-[2px]">
                            <?php if (isset($chunk[1])): ?>
                            <div class="overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                                 onclick="openImageModal('<?php echo $chunk[1]; ?>')">
                                <img src="<?php echo $chunk[1]; ?>" 
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" 
                                     alt="Gallery Image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($chunk[2])): ?>
                            <div class="overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                                 onclick="openImageModal('<?php echo $chunk[2]; ?>')">
                                <img src="<?php echo $chunk[2]; ?>" 
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" 
                                     alt="Gallery Image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Bottom row: 2 images side by side -->
                        <div class="grid grid-cols-2 gap-[2px]">
                            <?php if (isset($chunk[3])): ?>
                            <div class="overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                                 onclick="openImageModal('<?php echo $chunk[3]; ?>')">
                                <img src="<?php echo $chunk[3]; ?>" 
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" 
                                     alt="Gallery Image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($chunk[4])): ?>
                            <div class="overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                                 onclick="openImageModal('<?php echo $chunk[4]; ?>')">
                                <img src="<?php echo $chunk[4]; ?>" 
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" 
                                     alt="Gallery Image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <!-- Available Classes Section -->
        <div class="px-4  mt-8 mb-16" id="classes-section">
            <div class="text-center mb-10">
                <span class="bg-red-600 text-white px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest mb-4 inline-block shadow-sm">Popular Classes</span>
                <h2 class="text-4xl font-black text-gray-900 mb-4">පවතින පන්ති</h2>
                <p class="text-gray-600 max-w-2xl mx-auto text-base sm:text-lg font-medium">විෂය ධාරාවන් අනුව පන්ති තෝරාගෙන අදම ලියාපදිංචි වන්න</p>
                <div class="h-1.5 w-32 bg-red-600 mx-auto mt-6 rounded-full"></div>
            </div>
            
            <?php if (empty($assignments_by_stream)): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-book-open text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-medium text-gray-900">No classes available yet</h3>
                    <p class="text-gray-500 mt-2">Check back later for new class openings.</p>
                </div>
            <?php else: ?>
                <!-- Stream Dropdown -->
                <div class="mb-8">
                    <label for="streamFilter" class="block text-sm font-medium text-gray-700 mb-2">Select Stream</label>
                    <select id="streamFilter" onchange="filterStream(this.value)" class="block w-full md:w-64 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md shadow-sm border">
                        <option value="all">All Streams</option>
                        <?php foreach ($assignments_by_stream as $stream_id => $stream_data): ?>
                            <option value="stream-<?php echo $stream_id; ?>">
                                <?php echo htmlspecialchars($stream_data['stream_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Classes Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($assignments_by_stream as $stream_id => $stream_data): ?>
                        <?php foreach ($stream_data['classes'] as $class): ?>
                            <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden group border border-gray-200 class-card stream-<?php echo $stream_id; ?>">
                                <!-- Cover Image -->
                                <div class="relative h-72 overflow-hidden">
                                    <?php if ($class['cover_image']): ?>
                                        <img src="../<?php echo htmlspecialchars($class['cover_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($class['subject_name']); ?>"
                                             class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-slate-100 flex items-center justify-center">
                                            <i class="fas fa-book text-slate-300 text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm px-2 py-1 rounded-md text-[10px] font-bold text-gray-700 shadow-sm border border-gray-100 uppercase tracking-wide">
                                        <?php echo htmlspecialchars($stream_data['stream_name']); ?>
                                    </div>
                                </div>

                                <div class="p-5">
                                    <!-- Subject & Teacher -->
                                    <h3 class="text-base font-bold text-gray-800 mb-1 leading-snug" title="<?php echo htmlspecialchars($class['subject_name']); ?>">
                                        <?php echo htmlspecialchars($class['subject_name']); ?>
                                    </h3>
                                    
                                    <div class="flex items-center mb-4">
                                        <?php if ($class['teacher_image']): ?>
                                            <img src="../<?php echo htmlspecialchars($class['teacher_image']); ?>" class="w-12 h-12 rounded-full border border-gray-200 object-cover mr-2">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center border border-gray-200 mr-2">
                                                <i class="fas fa-user text-xs text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                        <p class="text-xs text-gray-500 mr-1">by</p>
                                        <p class="text-xs font-semibold text-gray-700"><?php echo htmlspecialchars($class['teacher_name']); ?></p>
                                    </div>

                                    <!-- Fees or Payment Status -->
                                    <?php 
                                    $enrolled_data = $user_enrollment_data[$class['stream_subject_id']] ?? null;
                                    if ($enrolled_data): 
                                    ?>
                                        <div class="grid grid-cols-2 gap-3 mb-4">
                                            <!-- Enrollment Status -->
                                            <div class="<?php echo $enrolled_data['enrollment_paid'] ? 'bg-green-50 border-green-100' : (isset($enrolled_data['enrollment_status']) && $enrolled_data['enrollment_status'] == 'Pending' ? 'bg-yellow-50 border-yellow-100' : 'bg-red-50 border-red-100'); ?> rounded-lg p-2 text-center border">
                                                <p class="text-[10px] <?php echo $enrolled_data['enrollment_paid'] ? 'text-green-500' : (isset($enrolled_data['enrollment_status']) && $enrolled_data['enrollment_status'] == 'Pending' ? 'text-yellow-600' : 'text-red-500'); ?> uppercase tracking-wider font-semibold mb-1">Enrollment</p>
                                                <p class="text-xs font-bold <?php echo $enrolled_data['enrollment_paid'] ? 'text-green-700' : (isset($enrolled_data['enrollment_status']) && $enrolled_data['enrollment_status'] == 'Pending' ? 'text-yellow-700' : 'text-red-700'); ?>">
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
                                            <div class="<?php echo $enrolled_data['monthly_status'] == 'Paid' ? 'bg-green-50 border-green-100' : ($enrolled_data['monthly_status'] == 'Pending' ? 'bg-yellow-50 border-yellow-100' : 'bg-red-50 border-red-100'); ?> rounded-lg p-2 text-center border">
                                                <p class="text-[10px] <?php echo $enrolled_data['monthly_status'] == 'Paid' ? 'text-green-500' : ($enrolled_data['monthly_status'] == 'Pending' ? 'text-yellow-600' : 'text-red-500'); ?> uppercase tracking-wider font-semibold mb-1"><?php echo date('F'); ?></p>
                                                <p class="text-xs font-bold <?php echo $enrolled_data['monthly_status'] == 'Paid' ? 'text-green-700' : ($enrolled_data['monthly_status'] == 'Pending' ? 'text-yellow-700' : 'text-red-700'); ?>">
                                                    <?php 
                                                        if ($enrolled_data['monthly_status'] == 'not_paid') echo 'Unpaid';
                                                        else echo $enrolled_data['monthly_status'];
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="grid grid-cols-2 gap-3 mb-4">
                                            <div class="bg-gray-50 rounded-lg p-2 text-center border border-gray-100">
                                                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Enrollment</p>
                                                <p class="text-sm font-bold text-gray-900"><?php echo $class['enrollment_fee'] > 0 ? number_format($class['enrollment_fee']) : 'Free'; ?></p>
                                            </div>
                                            <div class="bg-gray-50 rounded-lg p-2 text-center border border-gray-100">
                                                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Monthly</p>
                                                <p class="text-sm font-bold text-gray-900"><?php echo $class['monthly_fee'] > 0 ? number_format($class['monthly_fee']) : 'Free'; ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($is_logged_in): ?>
                                        <?php if ($enrolled_data): ?>
                                            <a href="recordings.php" 
                                               class="block w-full text-center bg-gray-100 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-200 transition-colors duration-200 text-sm font-medium">
                                                View Details
                                            </a>
                                        <?php else: ?>
                                            <button onclick="openEnrollModal(<?php echo $class['stream_subject_id']; ?>, '<?php echo htmlspecialchars($class['subject_name'], ENT_QUOTES); ?>')" 
                                                    class="block w-full text-center bg-gray-900 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition-colors duration-200 text-sm font-medium">
                                                Enroll Now
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="../register.php?stream_id=<?php echo $stream_id; ?>&subject_id=<?php echo $class['subject_id']; ?>"
                                           class="block w-full text-center bg-gray-900 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition-colors duration-200 text-sm font-medium">
                                            Enroll Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
            function filterStream(streamClass) {
                // Filter cards
                const cards = document.querySelectorAll('.class-card');
                cards.forEach(card => {
                    if (streamClass === 'all' || card.classList.contains(streamClass)) {
                        card.style.display = 'block';
                        // Add fade in animation
                        card.style.opacity = '0';
                        setTimeout(() => card.style.opacity = '1', 50);
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
        </script>

        <div class="px-4 mb-20">
            <div class="text-center mb-10">
                <span class="bg-red-600 text-white px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest mb-4 inline-block shadow-sm">Popular Courses</span>
                <h2 class="text-4xl font-black text-gray-900 mb-4">අපගේ බාහිර පාඨමාලා</h2>
                <p class="text-gray-600 max-w-2xl mx-auto text-base sm:text-lg font-medium">ඔබේ අනාගතය සාර්ථක කර ගැනීමට අපගේ අන්තර්ජාල පාඨමාලා හා එක්වන්න</p>
                <div class="h-1.5 w-32 bg-red-600 mx-auto mt-6 rounded-full"></div>
            </div>
            <?php if (empty($courses)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <p class="text-gray-500">No courses available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                        <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow overflow-hidden">
                            <!-- Course Cover Image -->
                            <div class="h-72 bg-gray-50 overflow-hidden">
                                <?php if ($course['cover_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($course['cover_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($course['title']); ?>"
                                         class="w-full h-full object-contain">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-400 to-red-600">
                                        <i class="fas fa-book text-white text-6xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Course Content -->
                            <div class="p-6">
                                <h3 class="font-bold text-xl text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </h3>
                                
                                <p class="text-sm text-gray-600 mb-3">
                                    <i class="fas fa-user text-red-600 mr-1"></i>
                                    By <?php echo htmlspecialchars($course['teacher_name'] ?: 'Unknown'); ?>
                                </p>

                                <?php if ($course['description']): ?>
                                    <p class="text-gray-700 text-sm mb-4 line-clamp-3">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 150)); ?>...
                                    </p>
                                <?php endif; ?>

                                <div class="flex items-center justify-between mt-4">
                                    <span class="text-red-600 font-bold text-2xl">
                                        Rs. <?php echo number_format($course['price'], 2); ?>
                                    </span>
                                </div>

                                <a href="../register.php?course_id=<?php echo $course['id']; ?>"
                                   class="block w-full text-center bg-red-600 text-white py-3 px-4 rounded-lg hover:bg-red-700 transition font-semibold mt-4">
                                    <i class="fas fa-cart-plus mr-2"></i>Enroll Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="bg-red-600 py-12 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="mb-6">
                <h2 class="text-2xl md:text-3xl font-black text-white mb-2">Learner.LK</h2>
                <div class="h-1 w-20 bg-white/30 mx-auto rounded-full"></div>
            </div>
            
            <div class="space-y-3">
                <p class="text-lg md:text-xl font-bold text-white">Learner.LK යනු ශ්‍රී ලංකාවේ හොඳම අන්තර්ජාල අධ්‍යාපන ආයතනයයි.</p>
                <p class="text-red-100 font-semibold text-sm md:text-base tracking-wide">Learner.LK is the best online academy in Sri Lanka.</p>
            </div>

            <div class="mt-10 pt-8 border-t border-red-500/30 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs font-bold text-red-100 uppercase tracking-widest">&copy; <?php echo date('Y'); ?> Learner.LK. All rights reserved.</p>
                <div class="flex space-x-6">
                    <a href="#" class="text-white hover:text-red-200 transition-colors"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white hover:text-red-200 transition-colors"><i class="fab fa-youtube"></i></a>
                    <a href="#" class="text-white hover:text-red-200 transition-colors"><i class="fab fa-whatsapp"></i></a>
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
            <p class="text-gray-600 mb-8 text-sm font-medium">කරුණාකර පළමුව ඇතුළු වන්න (Login) හෝ ලියාපදිංචි වන්න (Register)</p>
            
            <div class="space-y-4">
                <a href="#login-section" onclick="closeAuthModal(); scrollToLogin();"
                   class="block w-full bg-red-600 text-white py-4 px-6 rounded-xl hover:bg-red-700 font-bold transition-all transform active:scale-95 shadow-lg shadow-red-200">
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
                <p class="text-gray-500 mb-8">Are you sure you want to enroll in user <span id="enrollSubjectName" class="font-bold text-gray-800"></span>?</p>
                
                <div class="flex space-x-4">
                    <button onclick="closeEnrollModal()" 
                            class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-semibold transition-colors">
                        Cancel
                    </button>
                    <button onclick="processEnrollment()" 
                            class="flex-1 px-4 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 font-semibold shadow-lg hover:shadow-red-500/30 transition-colors">
                        Yes, Enroll
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="enrollToast" class="hidden fixed bottom-5 right-5 z-50 transform transition-all duration-300 translate-y-20 opacity-0">
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

    <!-- Full Image Popup Modal -->
    <div id="imageModal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-[100] flex items-center justify-center p-4">
        <button onclick="closeImageModal()" class="absolute top-6 right-6 text-white text-4xl hover:text-red-500 transition-colors">
            <i class="fas fa-times"></i>
        </button>
        <div class="max-w-5xl w-full h-full flex items-center justify-center">
            <img id="modalImg" src="" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl transition-all duration-300">
        </div>
    </div>

    <script>
        function openImageModal(src) {
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('modalImg');
            img.src = src;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        }

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Close on clicking outside the image
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });
    </script>

</body>
</html>
