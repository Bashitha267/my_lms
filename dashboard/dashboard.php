<?php
require_once '../config.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_logged_in ? 'Dashboard' : 'Welcome'; ?> - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar for all users -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class=" max-w-8xl mx-auto pt-4 pb-12 sm:px-6 lg:px-12">
        <?php if (!$is_logged_in): ?>
            <!-- Login Section for Guests -->
            <!-- Hero Section with Slideshow and Login - 50/50 Split -->
            <div class="md:mx-14 mx-4 grid grid-cols-1 lg:grid-cols-6 gap-6 mb-12 ">
                <!-- Left Side: Image Slideshow -->
                <div class="bg-gray-900 rounded-2xl shadow-xl overflow-hidden relative h-[300px] lg:h-[600px] lg:col-span-4 group ">
                    <div class="absolute inset-0">
                        <img src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80" 
                             alt="Education" 
                             class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-700">
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/40 to-transparent flex flex-col justify-end p-8 lg:p-10">
                        
                    </div>
                </div>

                <!-- Right Side: Login Form -->
                <div id="login-section" class="lg:h-[600px] lg:col-span-2">
                    <div class="bg-white rounded-2xl shadow-xl px-5 py-6 lg:px-6 lg:py-8 h-full flex flex-col justify-between border border-gray-100 mb-4 lg:mb-0">
                        <!-- Header Section -->
                        <div class="text-center">
                            <div class="mb-4">
                                <h2 class="text-2xl lg:text-3xl font-bold text-gray-800 mb-2">ආයුබෝවන්!!</h2>
                                <div class="h-1 w-16 bg-gradient-to-r from-red-600 to-red-700 mx-auto rounded-full"></div>
                            </div>
                            <p class="text-xs lg:text-[13px] text-gray-500 mt-1 leading-relaxed">
                                ලංකාවේ සාර්ථකම ඔන්ලිනෙ ඇකඩමියට ඔබව සාදරයෙන් පිළිගන්නවා. ඔබ දැනටමත් කුමන හෝ පාඨමාලාවක් සදහා ලියාපදිංචි වී ඇත්නම් ඔබගේ දුරකතන අංකය හා  Password  නිවැරදිව ලබා දී Login වෙන්න.
                            </p>
                            <p class="mt-2 pt-2 text-gray-500 font-semibold text-[10px] lg:text-[11px] border-t border-gray-100">
                                අලුතින්ම සම්බන්ද වීම සදහා පහතින් ඇති Register Button එක ක්ලික් කරන්න.
                        </div>
                        
                        <!-- Messages -->
                        <div class="flex-shrink-0">
                            <?php if ($error_message): ?>
                                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-2.5 rounded-lg mb-2 text-xs">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success_message): ?>
                                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-2.5 rounded-lg mb-2 text-xs">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <?php echo htmlspecialchars($success_message); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Login Form -->
                        <form action="../auth.php" method="POST" class="space-y-4">
                            <div>
                                <label for="identifier" class="block text-xs font-semibold text-gray-700 mb-1.5">Mobile Number</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-mobile-alt text-sm text-gray-400 group-focus-within:text-red-500 transition-colors"></i>
                                    </div>
                                    <input type="text" id="identifier" name="identifier" required
                                           class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all bg-gray-50 focus:bg-white"
                                           placeholder="0XX XXX XXXX">
                                </div>
                            </div>

                            <div>
                                <label for="password" class="block text-xs font-semibold text-gray-700 mb-1.5">Password</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-sm text-gray-400 group-focus-within:text-red-500 transition-colors"></i>
                                    </div>
                                    <input type="password" id="password" name="password" required
                                           class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all bg-gray-50 focus:bg-white"
                                           placeholder="Enter your password">
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between text-xs">
                                <label class="flex items-center text-gray-600 cursor-pointer hover:text-gray-800 transition-colors">
                                    <input type="checkbox" class="form-checkbox text-red-600 rounded border-gray-300 w-3.5 h-3.5 focus:ring-2 focus:ring-red-500">
                                    <span class="ml-2">Remember Me</span>
                                </label>
                                <a href="#" class="text-red-600 hover:text-red-700 font-medium hover:underline transition-all">Forgot Password?</a>
                            </div>

                            <button type="submit" name="login"
                                    class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-3 px-6 rounded-xl hover:from-red-700 hover:to-red-800 font-bold text-sm shadow-lg hover:shadow-xl hover:shadow-red-500/30 transition-all transform hover:-translate-y-0.5 active:scale-95">
                                <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                            </button>
                        </form>

                        <!-- Footer -->
                        <div class="text-center pt-4 border-t border-gray-200">
                            <p class="text-xs text-gray-600">
                                New to our platform? 
                                <a href="../register.php" class="text-red-600 hover:text-red-700 font-bold ml-1 hover:underline transition-colors inline-flex items-center">
                                    Register
                                    <i class="fas fa-arrow-right ml-1 text-[10px]"></i>
                                </a>
                            </p>
                           
                        </div>
                    </div>
                </div>
            </div>
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
        <section class="mt-4 mb-20 px-2 sm:px-4">
            <?php 
            $gallery_images = ['banner1.jpeg', 'banner2.jpeg', 'banner3.jpeg', 'banner4.jpeg', 'banner5.jpeg', 
                              'banner6.jpeg', 'banner7.jpeg', 'banner8.jpeg', 'banner9.jpeg', 'banner10.jpeg',
                              'banner11.jpeg', 'banner12.jpeg'];
            $chunks = array_chunk($gallery_images, 5); // Split into groups of 5 for the pattern (1 large + 4 small)
            ?>
            
            <div class="space-y-3">
                <?php foreach ($chunks as $chunkIndex => $chunk): ?>
                <!-- Bento Block -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2 mx-4">
                    <!-- Left: Large vertical image (spanning 6 columns) -->
                    <?php if (isset($chunk[0])): ?>
                    <div class="md:col-span-6 h-[350px] md:h-[700px] overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                         onclick="openImageModal('assests/<?php echo $chunk[0]; ?>')">
                        <img src="assests/<?php echo $chunk[0]; ?>" 
                             class="w-full h-full object-fill group-hover:scale-110 transition-transform duration-1000" 
                             alt="Gallery Image">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Right: Grid of smaller images (spanning 6 columns) -->
                    <div class="md:col-span-6 grid grid-rows-2 gap-4 h-[400px] md:h-[700px]">
                        <!-- Top row: 2 images side by side -->
                        <div class="grid grid-cols-2 gap-4">
                            <?php if (isset($chunk[1])): ?>
                            <div class="overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                                 onclick="openImageModal('assests/<?php echo $chunk[1]; ?>')">
                                <img src="assests/<?php echo $chunk[1]; ?>" 
                                     class="w-full h-full object-fill group-hover:scale-110 transition-transform duration-1000" 
                                     alt="Gallery Image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($chunk[2])): ?>
                            <div class="overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                                 onclick="openImageModal('assests/<?php echo $chunk[2]; ?>')">
                                <img src="assests/<?php echo $chunk[2]; ?>" 
                                     class="w-full h-full object-fill group-hover:scale-110 transition-transform duration-1000" 
                                     alt="Gallery Image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Bottom row: 2 images side by side -->
                        <div class="grid grid-cols-2 gap-4">
                            <?php if (isset($chunk[3])): ?>
                            <div class="overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                                 onclick="openImageModal('assests/<?php echo $chunk[3]; ?>')">
                                <img src="assests/<?php echo $chunk[3]; ?>" 
                                     class="w-full h-full object-fill group-hover:scale-110 transition-transform duration-1000" 
                                     alt="Gallery Image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($chunk[4])): ?>
                            <div class="overflow-hidden group cursor-pointer relative shadow-lg hover:shadow-2xl transition-all duration-700" 
                                 onclick="openImageModal('assests/<?php echo $chunk[4]; ?>')">
                                <img src="assests/<?php echo $chunk[4]; ?>" 
                                     class="w-full h-full object-fill group-hover:scale-110 transition-transform duration-1000" 
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
        <div class="px-4 mb-16" id="classes-section">
            <div class="text-center mb-10">
                <h2 class="text-4xl font-extrabold text-gray-900">පවතින පන්ති</h2>
                <p class="text-gray-600 mt-3 text-lg">විෂය ධාරාවන් අනුව පන්ති තෝරාගෙන අදම ලියාපදිංචි වන්න</p>
                <div class="h-1 w-24 bg-red-600 mx-auto mt-4 rounded-full"></div>
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <?php foreach ($assignments_by_stream as $stream_id => $stream_data): ?>
                        <?php foreach ($stream_data['classes'] as $class): ?>
                            <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden group border border-gray-200 class-card stream-<?php echo $stream_id; ?>">
                                <!-- Cover Image -->
                                <div class="relative h-40 overflow-hidden">
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

        <!-- Courses Section -->
        <div class="px-4 mb-12">
            <h2 class="text-3xl font-bold text-black p-4 rounded-xl text-center ">
                අපගේ පාඨමාලා
            </h2>
             <div class="h-1 w-24 bg-red-600 mx-auto rounded-full mb-12"></div>
            <?php if (empty($courses)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <p class="text-gray-500">No courses available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                        <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow overflow-hidden">
                            <!-- Course Cover Image -->
                            <div class="h-48 bg-gray-200 overflow-hidden">
                                <?php if ($course['cover_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($course['cover_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($course['title']); ?>"
                                         class="w-full h-full object-cover">
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

    <!-- Login/Register Popup for Navigation Clicks -->
    <div id="authModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-75 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl p-10 max-w-md w-full mx-4 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-lock text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-black text-gray-900 mb-2">Please Login or Register First</h3>
            <!-- <p class="text-gray-600 mb-8 text-lg">Please Login.</p> -->
            
            <div class="space-y-4">
                <a href="#login-section" onclick="closeAuthModal(); scrollToLogin();"
                   class="block w-full bg-red-600 text-white py-4 px-6 rounded-xl hover:bg-red-700 font-bold transition-all transform active:scale-95 shadow-lg shadow-red-200">
                    Login
                </a>
                <a href="../register.php"
                   class="block w-full bg-gray-100 text-gray-700 py-4 px-6 rounded-xl hover:bg-gray-200 font-bold transition-all transform active:scale-95">
                    Register
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

    <?php if (!$is_logged_in): ?>
    <script>
        // Show auth modal
        function showAuthModal() {
            document.getElementById('authModal').classList.remove('hidden');
        }

        // Close auth modal
        function closeAuthModal() {
            document.getElementById('authModal').classList.add('hidden');
        }

        // Scroll to login section
        function scrollToLogin() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Add click handlers to navbar links if user is not logged in
        document.addEventListener('DOMContentLoaded', function() {
            // Get all navbar links (both desktop and mobile)
            const allNavLinks = document.querySelectorAll('nav a[href*=".php"]:not([href*="logout"]):not([href*="auth.php"])');
            
            allNavLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    
                    // Only allow live_classes.php, dashboard.php, and about_us.php without login
                    if (href && !href.includes('live_classes.php') && !href.includes('dashboard.php') && !href.includes('about_us.php')) {
                        e.preventDefault();
                        e.stopPropagation();
                        showAuthModal();
                    }
                });
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
