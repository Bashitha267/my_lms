<?php
// header.php - Admin header component with red and white theme
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<header class="bg-red-600 shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo/Brand -->
            <div class="flex items-center flex-shrink-0">
                <a href="dashboard.php" class="text-white text-xl font-bold hover:text-red-200 transition-colors flex items-center space-x-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <span>LMS Admin</span>
                </a>
            </div>
            
            <!-- Admin Navigation Links -->
            <div class="hidden md:flex md:items-center md:space-x-2 flex-1 justify-center">
                <a href="dashboard.php" 
                   class="<?php echo ($current_page == 'dashboard.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span>DASHBOARD</span>
                </a>
                <a href="users.php" 
                   class="<?php echo ($current_page == 'users.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <span>MANAGE USERS</span>
                </a>
                <a href="add_user.php" 
                   class="<?php echo ($current_page == 'add_user.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    <span>ADD USER</span>
                </a>
                <a href="verify_payments.php" 
                   class="<?php echo ($current_page == 'verify_payments.php') ? 'bg-red-700' : 'hover:bg-red-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>VERIFY PAYMENTS</span>
                </a>
            </div>
            
            <!-- User Menu / Logout -->
            <div class="flex items-center space-x-3">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="hidden sm:flex items-center space-x-2 text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                    <a href="../auth.php?logout=1" 
                       class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-2 shadow-md hover:shadow-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span>LOGOUT</span>
                    </a>
                <?php endif; ?>
                
                <!-- Mobile menu button -->
                <button type="button" 
                        class="md:hidden mobile-menu-button bg-red-700 inline-flex items-center justify-center p-2 rounded-md text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
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
    
    <!-- Mobile menu -->
    <div class="md:hidden transition-all duration-300 ease-in-out overflow-hidden" id="mobile-menu" style="max-height: 0;">
        <div class="px-2 pt-2 pb-4 space-y-1 bg-red-700 border-t border-red-800">
            <a href="dashboard.php" 
               class="<?php echo ($current_page == 'dashboard.php') ? 'bg-red-800' : 'hover:bg-red-800'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>DASHBOARD</span>
            </a>
            <a href="users.php" 
               class="<?php echo ($current_page == 'users.php') ? 'bg-red-800' : 'hover:bg-red-800'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>MANAGE USERS</span>
            </a>
            <a href="add_user.php" 
               class="<?php echo ($current_page == 'add_user.php') ? 'bg-red-800' : 'hover:bg-red-800'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
                <span>ADD USER</span>
            </a>
            <a href="verify_payments.php" 
               class="<?php echo ($current_page == 'verify_payments.php') ? 'bg-red-800' : 'hover:bg-red-800'; ?> text-white block px-4 py-3 rounded-md text-sm font-medium uppercase transition duration-150 ease-in-out flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>VERIFY PAYMENTS</span>
            </a>
            <?php if (isset($_SESSION['username'])): ?>
                <div class="border-t border-red-800 mt-2 pt-3">
                    <div class="px-4 py-2 mb-2 text-white text-sm">
                        Welcome, <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                    <a href="../auth.php?logout=1" 
                       class="bg-red-800 hover:bg-red-900 text-white block px-4 py-3 rounded-md text-sm font-medium uppercase flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span>LOGOUT</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<style>
#mobile-menu {
    transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
    opacity: 0;
}

#mobile-menu.menu-open {
    max-height: 600px;
    opacity: 1;
}

.mobile-menu-button {
    user-select: none;
    -webkit-user-select: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.querySelector('.mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = document.querySelector('.hamburger-icon');
    const closeIcon = document.querySelector('.close-icon');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            const isOpen = mobileMenu.classList.contains('menu-open');
            
            if (isOpen) {
                mobileMenu.classList.remove('menu-open');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
                hamburgerIcon?.classList.remove('hidden');
                closeIcon?.classList.add('hidden');
            } else {
                mobileMenu.classList.add('menu-open');
                mobileMenuButton.setAttribute('aria-expanded', 'true');
                hamburgerIcon?.classList.add('hidden');
                closeIcon?.classList.remove('hidden');
            }
        });
    }
});
</script>

