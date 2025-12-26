<?php
// navbar.php - Navigation bar component for dashboard
// Include this file in your dashboard pages

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="bg-red-600 shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8">
        <div class="flex justify-between items-center h-14 sm:h-16">
            <!-- Logo/Brand -->
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
                <a href="about_us.php" 
                   class="<?php echo ($current_page == 'about_us.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-2 xl:px-3 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out">
                    ABOUT US
                </a>
            </div>
            
            <!-- Desktop User Menu / Logout -->
            <div class="hidden lg:flex lg:items-center lg:space-x-3">
                <?php if (isset($_SESSION['username'])): ?>
                    <span class="text-white text-xs xl:text-sm truncate max-w-[120px] xl:max-w-none">
                        Welcome, <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </span>
                    <a href="../auth.php?logout=1" 
                       class="bg-red-700 hover:bg-red-800 active:bg-red-900 text-white px-3 xl:px-4 py-2 rounded-md text-xs xl:text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-2 shadow-md hover:shadow-lg touch-manipulation">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="hidden xl:inline">LOGOUT</span>
                        <span class="xl:hidden">OUT</span>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile menu button -->
            <div class="lg:hidden flex items-center space-x-2">
                <?php if (isset($_SESSION['username'])): ?>
                    <span class="text-white text-xs sm:text-sm truncate max-w-[80px] sm:max-w-[120px]">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                <?php endif; ?>
                <button type="button" 
                        class="mobile-menu-button bg-red-700 inline-flex items-center justify-center p-2 sm:p-2.5 rounded-md text-white hover:bg-red-800 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white touch-manipulation"
                        aria-controls="mobile-menu" 
                        aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg class="hamburger-icon block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg class="close-icon hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu with smooth animation -->
    <div class="lg:hidden transition-all duration-300 ease-in-out overflow-hidden" id="mobile-menu" style="max-height: 0;">
        <div class="px-2 pt-2 pb-4 space-y-1 bg-red-700 border-t border-red-800">
            <a href="dashboard.php" 
               class="<?php echo ($current_page == 'dashboard.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation">
                HOME
            </a>
            <a href="online_courses.php" 
               class="<?php echo ($current_page == 'online_courses.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation">
                ONLINE COURSES
            </a>
            <a href="recordings.php" 
               class="<?php echo ($current_page == 'recordings.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation">
                RECORDINGS
            </a>
            <a href="live_classes.php" 
               class="<?php echo ($current_page == 'live_classes.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation">
                LIVE CLASSES
            </a>
            <a href="instructors.php" 
               class="<?php echo ($current_page == 'instructors.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation">
                INSTRUCTORS
            </a>
            <a href="payments.php" 
               class="<?php echo ($current_page == 'payments.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation">
                PAYMENTS
            </a>
            <a href="about_us.php" 
               class="<?php echo ($current_page == 'about_us.php') ? 'bg-red-800' : 'hover:bg-red-800 active:bg-red-900'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out touch-manipulation">
                ABOUT US
            </a>
            <?php if (isset($_SESSION['username'])): ?>
                <div class="border-t border-red-800 mt-2 pt-3">
                    <div class="px-4 py-2 mb-2">
                        <span class="text-white text-sm">
                            Welcome, <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </span>
                    </div>
                    <a href="../auth.php?logout=1" 
                       class="bg-red-800 hover:bg-red-900 active:bg-red-950 text-white block px-4 py-3 rounded-md text-sm font-medium uppercase flex items-center justify-center space-x-2 transition duration-150 ease-in-out touch-manipulation">
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
    transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
    opacity: 0;
}

#mobile-menu.menu-open {
    max-height: 600px;
    opacity: 1;
}

/* Touch-friendly tap targets */
.touch-manipulation {
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}

/* Prevent text selection on mobile buttons */
.mobile-menu-button {
    user-select: none;
    -webkit-user-select: none;
}
</style>

<script>
// Enhanced mobile menu toggle with smooth animation
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.querySelector('.mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = document.querySelector('.hamburger-icon');
    const closeIcon = document.querySelector('.close-icon');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            const isOpen = mobileMenu.classList.contains('menu-open');
            
            if (isOpen) {
                // Close menu
                mobileMenu.classList.remove('menu-open');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
                hamburgerIcon?.classList.remove('hidden');
                closeIcon?.classList.add('hidden');
            } else {
                // Open menu
                mobileMenu.classList.add('menu-open');
                mobileMenuButton.setAttribute('aria-expanded', 'true');
                hamburgerIcon?.classList.add('hidden');
                closeIcon?.classList.remove('hidden');
            }
        });
        
        // Close menu when clicking outside (optional)
        document.addEventListener('click', function(event) {
            if (!mobileMenuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                if (mobileMenu.classList.contains('menu-open')) {
                    mobileMenu.classList.remove('menu-open');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                    hamburgerIcon?.classList.remove('hidden');
                    closeIcon?.classList.add('hidden');
                }
            }
        });
        
        // Close menu on window resize to desktop size
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) { // lg breakpoint
                mobileMenu.classList.remove('menu-open');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
                hamburgerIcon?.classList.remove('hidden');
                closeIcon?.classList.add('hidden');
            }
        });
    }
});
</script>
