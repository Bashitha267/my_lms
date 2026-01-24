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
    $stmt = $conn->prepare("SELECT profile_picture, first_name, second_name FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $profile_picture = $user_data['profile_picture'];
        $first_name = $user_data['first_name'] ?? '';
        $second_name = $user_data['second_name'] ?? '';
        $full_name = trim($first_name . ' ' . $second_name);
        if (empty($full_name)) {
            $full_name = $_SESSION['username'] ?? '';
        }
    }
    $stmt->close();
}
?>

<nav class="bg-red-600 shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8">
        <div class="flex justify-between items-center h-14 sm:h-16">
            <!-- Logo/Brand - Left Side -->
            <div class="flex items-center flex-shrink-0">
                <a href="dashboard.php" class="text-white text-lg sm:text-xl font-bold hover:text-red-200 transition-colors">
                    LMS
                </a>
            </div>
            
            <!-- Desktop Navigation Links -->
            <div class="hidden lg:flex lg:items-center lg:space-x-1 xl:space-x-2 flex-1 justify-center">
                <a href="dashboard.php" 
                   class="<?php echo ($current_page == 'dashboard.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                    HOME
                </a>
                <a href="online_courses.php" 
                   class="<?php echo ($current_page == 'online_courses.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                    ONLINE COURSES
                </a>
                <a href="recordings.php" 
                   class="<?php echo ($current_page == 'recordings.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
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
                            <span class="text-white text-xs xl:text-sm font-medium truncate max-w-[150px] xl:max-w-[200px]">
                                <?php echo htmlspecialchars($full_name); ?>
                            </span>
                            
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
                        <span class="text-white text-xs sm:text-sm font-medium truncate max-w-[80px] xs:max-w-[100px] sm:max-w-[120px]">
                            <?php echo htmlspecialchars($full_name); ?>
                        </span>
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

<style>
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
</script>
