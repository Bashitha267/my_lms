<?php
// navbar.php - Navigation bar component for dashboard
// Include this file in your dashboard pages

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user profile picture and full name
$profile_picture = null;
$full_name = '';
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config.php';
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT profile_picture, first_name, second_name, email, district, mobile_number FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $profile_picture = $user_data['profile_picture'];
        $first_name = $user_data['first_name'] ?? '';
        $second_name = $user_data['second_name'] ?? '';
        $email = $user_data['email'] ?? '';
        $district = $user_data['district'] ?? '';
        $mobile_number = $user_data['mobile_number'] ?? '';
        $full_name = trim($first_name . ' ' . $second_name);
        if (empty($full_name)) {
            $full_name = $_SESSION['username'] ?? '';
        }
    }
    $stmt->close();
}
?>
<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/davidshimjs-qrcodejs@0.0.2/qrcode.min.js"></script>


<nav class="bg-red-600 shadow-lg sticky top-0 z-50">
    <div class="  px-2 sm:px-4 lg:px-8">
        <div class="flex justify-between items-center h-14 sm:h-16">
            <!-- Logo/Brand - Left Side -->
            <div class="flex items-center flex-shrink-0">
                <a href="dashboard.php" class="text-white text-lg sm:text-xl font-bold hover:text-red-200 transition-colors">
                    LMS
                </a>
            </div>
            
            <!-- Desktop Navigation Links -->
            <div class="hidden lg:flex lg:items-center lg:space-x-1 xl:space-x-2 flex-1 justify-center">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                    <!-- Student Navigation with Sinhala Tooltips -->
                    <div class="nav-item-with-tooltip">
                        <a href="dashboard.php" 
                           class="<?php echo ($current_page == 'dashboard.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs  font-medium uppercase transition duration-150 ease-in-out">
                            HOME
                        </a>
                        <div class="sinhala-tooltip">මුල් පිටුව</div>
                    </div>
                    <div class="nav-item-with-tooltip">
                        <a href="online_courses.php" 
                           class="<?php echo ($current_page == 'online_courses.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs  font-medium uppercase transition duration-150 ease-in-out">
                            ONLINE COURSES
                        </a>
                        <div class="sinhala-tooltip">අන්තර්ජාල පාඨමාලා</div>
                    </div>
                    <div class="nav-item-with-tooltip">
                        <a href="recordings.php" 
                           class="<?php echo ($current_page == 'recordings.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs  font-medium uppercase transition duration-150 ease-in-out">
                            RECORDINGS
                        </a>
                        <div class="sinhala-tooltip">පටිගත කිරීම්</div>
                    </div>
                    <div class="nav-item-with-tooltip">
                        <a href="live_classes.php" 
                           class="<?php echo ($current_page == 'live_classes.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                            LIVE CLASSES
                        </a>
                        <div class="sinhala-tooltip">සජීවී පන්ති</div>
                    </div>
                    <div class="nav-item-with-tooltip">
                        <a href="instructors.php" 
                           class="<?php echo ($current_page == 'instructors.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                            INSTRUCTORS
                        </a>
                        <div class="sinhala-tooltip">උපදේශකයින්</div>
                    </div>
                    <div class="nav-item-with-tooltip">
                        <a href="payments.php" 
                           class="<?php echo ($current_page == 'payments.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                            PAYMENTS
                        </a>
                        <div class="sinhala-tooltip">ගෙවීම්</div>
                    </div>
                    <div class="nav-item-with-tooltip">
                        <a href="exam_center.php" 
                           class="<?php echo ($current_page == 'exam_center.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                            EXAM CENTER
                        </a>
                        <div class="sinhala-tooltip">විභාග මධ්‍යස්ථානය</div>
                    </div>
                    <div class="nav-item-with-tooltip">
                        <a href="about_us.php" 
                           class="<?php echo ($current_page == 'about_us.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                            ABOUT US
                        </a>
                        <div class="sinhala-tooltip">අප ගැන</div>
                    </div>
                <?php else: ?>
                    <!-- Teacher Navigation without tooltips -->
                    <a href="dashboard.php" 
                       class="<?php echo ($current_page == 'dashboard.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs  font-medium uppercase transition duration-150 ease-in-out">
                        HOME
                    </a>
                    <a href="online_courses.php" 
                       class="<?php echo ($current_page == 'online_courses.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs  font-medium uppercase transition duration-150 ease-in-out">
                        ONLINE COURSES
                    </a>
                    <a href="recordings.php" 
                       class="<?php echo ($current_page == 'recordings.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs  font-medium uppercase transition duration-150 ease-in-out">
                        RECORDINGS
                    </a>
                    <a href="live_classes.php" 
                       class="<?php echo ($current_page == 'live_classes.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                        LIVE CLASSES
                    </a>
                    <a href="instructors.php" 
                       class="<?php echo ($current_page == 'instructors.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                        INSTRUCTORS
                    </a>
                    <a href="payments.php" 
                       class="<?php echo ($current_page == 'payments.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                        PAYMENTS
                    </a>
                    <a href="exam_center.php" 
                       class="<?php echo ($current_page == 'exam_center.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                        EXAM CENTER
                    </a>
                    <a href="about_us.php" 
                       class="<?php echo ($current_page == 'about_us.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                        ABOUT US
                    </a>
                <?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                <a href="reports.php" 
                   class="<?php echo ($current_page == 'reports.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                    REPORTS
                </a>
                <?php endif; ?>
               
            </div>
            
            <!-- Right Side - User Info & Menu Button -->
            <div class="flex items-center space-x-2 sm:space-x-3 flex-shrink-0">
                <!-- Desktop User Menu / Logout -->
                <div class="hidden lg:flex lg:items-center lg:space-x-3">
                    <?php if (isset($_SESSION['username'])): ?>
                        <div class="flex items-center space-x-3">
                            <!-- Profile Picture -->
                            <div class="flex-shrink-0">
                                <?php if (!empty($profile_picture)): ?>
                                    <img src="../<?php echo htmlspecialchars($profile_picture); ?>" 
                                         alt="Profile" 
                                         class="w-8 h-8 xl:w-10 xl:h-10 rounded-full object-cover border-2 border-white shadow-md">
                                <?php else: ?>
                                    <div class="w-8 h-8 xl:w-10 xl:h-10 rounded-full bg-white flex items-center justify-center border-2 border-white shadow-md">
                                        <svg class="w-5 h-5 xl:w-6 xl:h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                             <!-- Full Name -->
                            <button onclick="openProfileModal()" class="flex items-center space-x-2 focus:outline-none group">
                                <span class="text-white text-xs xl:text-sm font-medium truncate max-w-[150px] xl:max-w-[200px] group-hover:text-red-200 transition-colors">
                                    <?php echo htmlspecialchars($full_name); ?>
                                </span>
                                <svg class="w-4 h-4 text-white group-hover:text-red-200 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <!-- Logout Icon -->
                            <a href="../auth.php?logout=1" 
                               class="bg-red-700 hover:bg-red-800 active:bg-red-900 text-white p-2 rounded-md transition duration-150 ease-in-out flex items-center justify-center shadow-md hover:shadow-lg touch-manipulation"
                               title="Logout">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Mobile User Info & Menu Button -->
                <div class="lg:hidden flex items-center space-x-1 sm:space-x-2">
                    <?php if (isset($_SESSION['username'])): ?>
                        <!-- Profile Picture (Mobile) -->
                        <div class="flex-shrink-0">
                            <?php if (!empty($profile_picture)): ?>
                                <img src="../<?php echo htmlspecialchars($profile_picture); ?>" 
                                     alt="Profile" 
                                     class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover border-2 border-white shadow-md"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white flex items-center justify-center border-2 border-white shadow-md" style="display: none;">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                            <?php else: ?>
                                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white flex items-center justify-center border-2 border-white shadow-md">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                         <!-- Full Name (Mobile) -->
                        <button onclick="openProfileModal()" class="flex items-center space-x-1 focus:outline-none">
                            <span class="text-white text-xs sm:text-sm font-medium truncate max-w-[80px] xs:max-w-[100px] sm:max-w-[120px]">
                                <?php echo htmlspecialchars($full_name); ?>
                            </span>
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    <?php endif; ?>
                    
                    <!-- Mobile Menu Toggle Button -->
                    <button type="button" 
                            id="mobile-menu-button"
                            class="mobile-menu-button bg-red-700 inline-flex items-center justify-center p-2 sm:p-2.5 rounded-md text-white hover:bg-red-800 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white touch-manipulation flex-shrink-0"
                            aria-controls="mobile-menu" 
                            aria-expanded="false"
                            aria-label="Toggle mobile menu">
                        <span class="sr-only">Open main menu</span>
                        <svg class="hamburger-icon block h-5 w-5 sm:h-6 sm:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="close-icon hidden h-5 w-5 sm:h-6 sm:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu with smooth animation -->
    <div class="lg:hidden transition-all duration-300 ease-in-out overflow-hidden" id="mobile-menu">
        <div class="px-2 pt-2 pb-4 space-y-1 bg-red-700 border-t border-red-800 max-h-[calc(100vh-64px)] overflow-y-auto">
            <a href="dashboard.php" 
               class="mobile-menu-link <?php echo ($current_page == 'dashboard.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                HOME
            </a>
            <a href="online_courses.php" 
               class="mobile-menu-link <?php echo ($current_page == 'online_courses.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                ONLINE COURSES
            </a>
            <a href="recordings.php" 
               class="mobile-menu-link <?php echo ($current_page == 'recordings.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                RECORDINGS
            </a>
            <a href="live_classes.php" 
               class="mobile-menu-link <?php echo ($current_page == 'live_classes.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                LIVE CLASSES
            </a>
            <a href="instructors.php" 
               class="mobile-menu-link <?php echo ($current_page == 'instructors.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                INSTRUCTORS
            </a>
            <a href="payments.php" 
               class="mobile-menu-link <?php echo ($current_page == 'payments.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                PAYMENTS
            </a>
            <a href="exam_center.php" 
               class="mobile-menu-link <?php echo ($current_page == 'exam_center.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                EXAM CENTER
            </a>
            <a href="about_us.php" 
               class="mobile-menu-link <?php echo ($current_page == 'about_us.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                ABOUT US
            </a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
            <a href="reports.php" 
               class="mobile-menu-link <?php echo ($current_page == 'reports.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation min-h-[44px] flex items-center">
                REPORTS
            </a>
            <?php endif; ?>
        
            <?php if (isset($_SESSION['username'])): ?>
                <div class="border-t border-red-800 mt-2 pt-3">
                    <a href="../auth.php?logout=1" 
                       class="mobile-menu-link bg-red-800 hover:bg-red-900 active:bg-red-950 text-white block px-4 py-3 rounded-md text-sm font-medium uppercase flex items-center justify-center gap-2 transition duration-150 ease-in-out touch-manipulation min-h-[44px]"
                       title="Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span>LOGOUT</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Profile Modal -->
<div id="userProfileModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="relative h-32 bg-red-600">
            <button onclick="closeProfileModal()" class="absolute top-4 right-4 text-white hover:text-red-200 transition-colors z-10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <div class="absolute -bottom-12 left-1/2 transform -translate-x-1/2">
                <?php if (!empty($profile_picture)): ?>
                    <img src="../<?php echo htmlspecialchars($profile_picture); ?>" 
                         class="w-24 h-24 rounded-full border-4 border-white shadow-lg object-cover bg-white">
                <?php else: ?>
                    <div class="w-24 h-24 rounded-full border-4 border-white shadow-lg bg-red-100 flex items-center justify-center">
                        <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="pt-16 pb-8 px-8 flex flex-col items-center">
            <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($full_name); ?></h3>
            <p class="text-red-600 font-semibold text-sm mb-4">Student ID: <?php echo htmlspecialchars($user_id); ?></p>
            
            <div class="w-full space-y-4 mb-8">
                <div class="flex items-center p-3 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Email Address</p>
                        <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($email); ?></p>
                    </div>
                </div>

                <div class="flex items-center p-3 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">District</p>
                        <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($district); ?></p>
                    </div>
                </div>
            </div>

            <!-- QR Code Section -->
            <div class="flex flex-col items-center">
                <div id="userQRCode" class="p-4 bg-white border-2 border-red-500 rounded-2xl shadow-inner mb-3"></div>
                <p class="text-xs text-gray-500 italic max-w-xs text-center">Scan this QR code during physical class admission for quick identification.</p>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 px-8 py-4 flex justify-center">
            <a href="../auth.php?logout=1" class="text-red-600 font-bold text-sm hover:text-red-700 transition-colors uppercase tracking-widest flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Logout Account
            </a>
        </div>
    </div>
</div>


<style>
/* Sinhala Tooltip Styles */
.nav-item-with-tooltip {
    position: relative;
    display: inline-block;
}

.sinhala-tooltip {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(8px);
    background: white;
    color: #dc2626;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: all 0.2s ease-in-out;
    z-index: 1000;
    border: 2px solid #fca5a5;
}

.sinhala-tooltip::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-bottom-color: white;
}

.nav-item-with-tooltip:hover .sinhala-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(4px);
}

/* Smooth mobile menu animation */
#mobile-menu {
    max-height: 0;
    opacity: 0;
    visibility: hidden;
    transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
    overflow: hidden;
    position: relative;
    z-index: 1000;
    display: block;
}

#mobile-menu.menu-open {
    max-height: 80vh;
    opacity: 1;
    visibility: visible;
    display: block;
}

/* Touch-friendly tap targets */
.touch-manipulation {
    -webkit-tap-highlight-color: rgba(255, 255, 255, 0.1);
    touch-action: manipulation;
    cursor: pointer;
}

/* Prevent text selection on mobile buttons */
.mobile-menu-button {
    user-select: none;
    -webkit-user-select: none;
    min-width: 44px;
    min-height: 44px;
}

/* Mobile menu links - ensure proper spacing and touch targets */
.mobile-menu-link {
    -webkit-tap-highlight-color: rgba(255, 255, 255, 0.1);
    touch-action: manipulation;
    cursor: pointer;
    word-wrap: break-word;
}

/* Prevent body scroll when menu is open */
body.menu-open {
    overflow: hidden;
    position: fixed;
    width: 100%;
}

/* Better spacing for very small screens */
@media (max-width: 375px) {
    #mobile-menu.menu-open {
        max-height: 75vh;
    }
}

/* Smooth scroll for mobile menu */
#mobile-menu > div {
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
}
</style>

<script>
// Enhanced mobile menu toggle with smooth animation
(function() {
    'use strict';
    
    let scrollPosition = 0;
    
    function openMenu() {
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const hamburgerIcon = mobileMenuButton ? mobileMenuButton.querySelector('.hamburger-icon') : null;
        const closeIcon = mobileMenuButton ? mobileMenuButton.querySelector('.close-icon') : null;
        const body = document.body;
        
        if (!mobileMenu || !mobileMenuButton) {
            return;
        }
        
        // Save scroll position
        scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        
        // Open menu - use both class and direct style manipulation
        mobileMenu.classList.add('menu-open');
        mobileMenu.style.maxHeight = '80vh';
        mobileMenu.style.opacity = '1';
        mobileMenu.style.visibility = 'visible';
        mobileMenu.style.display = 'block';
        
        mobileMenuButton.setAttribute('aria-expanded', 'true');
        if (hamburgerIcon) hamburgerIcon.classList.add('hidden');
        if (closeIcon) closeIcon.classList.remove('hidden');
        
        // Prevent body scroll
        body.classList.add('menu-open');
        body.style.top = `-${scrollPosition}px`;
    }
    
    function closeMenu() {
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const hamburgerIcon = mobileMenuButton ? mobileMenuButton.querySelector('.hamburger-icon') : null;
        const closeIcon = mobileMenuButton ? mobileMenuButton.querySelector('.close-icon') : null;
        const body = document.body;
        
        if (!mobileMenu || !mobileMenuButton) {
            return;
        }
        
        // Close menu - use both class and direct style manipulation
        mobileMenu.classList.remove('menu-open');
        mobileMenu.style.maxHeight = '0';
        mobileMenu.style.opacity = '0';
        mobileMenu.style.visibility = 'hidden';
        
        mobileMenuButton.setAttribute('aria-expanded', 'false');
        if (hamburgerIcon) hamburgerIcon.classList.remove('hidden');
        if (closeIcon) closeIcon.classList.add('hidden');
        
        // Restore body scroll
        body.classList.remove('menu-open');
        body.style.top = '';
        window.scrollTo(0, scrollPosition);
    }
    
    function isMenuOpen() {
        const mobileMenu = document.getElementById('mobile-menu');
        if (!mobileMenu) return false;
        
        // Check multiple ways to determine if menu is open
        const hasClass = mobileMenu.classList.contains('menu-open');
        const styleHeight = mobileMenu.style.maxHeight;
        const computedStyle = window.getComputedStyle(mobileMenu);
        const computedHeight = computedStyle.maxHeight;
        const computedVisibility = computedStyle.visibility;
        
        return hasClass && 
               (styleHeight === '80vh' || computedHeight !== '0px') && 
               computedVisibility === 'visible';
    }
    
    // Initialize when DOM is ready
    function initMobileMenu() {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuLinks = document.querySelectorAll('.mobile-menu-link');
        
        if (!mobileMenuButton || !mobileMenu) {
            console.warn('Mobile menu elements not found');
            return;
        }
        
        // Prevent double-firing on mobile (touchend + click)
        let lastToggleTime = 0;
        const TOGGLE_DEBOUNCE = 300; // milliseconds
        
        function handleMenuToggle(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            // Debounce to prevent double-firing
            const now = Date.now();
            if (now - lastToggleTime < TOGGLE_DEBOUNCE) {
                return;
            }
            lastToggleTime = now;
            
            // Check if menu is actually open
            const isOpen = isMenuOpen();
            
            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        }
        
        // Use click event (works for both mouse and touch after touchend)
        mobileMenuButton.addEventListener('click', handleMenuToggle);
        
        // Close menu when clicking on menu links
        mobileMenuLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                closeMenu();
            });
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (mobileMenu && mobileMenu.classList.contains('menu-open')) {
                if (!mobileMenuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                    closeMenu();
                }
            }
        });
        
        // Close menu on window resize to desktop size
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= 1024 && mobileMenu && mobileMenu.classList.contains('menu-open')) {
                    closeMenu();
                }
            }, 100);
        });
        
        // Close menu on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('menu-open')) {
                closeMenu();
            }
        });
        
        // Prevent menu from closing when clicking inside menu
        mobileMenu.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
})();

// Profile Modal Logic
let qrGenerated = false;
function openProfileModal() {
    const modal = document.getElementById('userProfileModal');
    if(modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Generate QR Code if not already generated
        if (!qrGenerated && typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById("userQRCode"), {
                text: "<?php echo $user_id; ?>",
                width: 128,
                height: 128,
                colorDark : "#dc2626",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
            qrGenerated = true;
        }
    }
}

function closeProfileModal() {
    const modal = document.getElementById('userProfileModal');
    if(modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Close on outside click
document.getElementById('userProfileModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeProfileModal();
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProfileModal();
    }
});

</script>
