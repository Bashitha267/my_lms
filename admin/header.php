<?php
// header.php - Admin header component with blue theme
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
// Prefix to use for links (empty if in same folder, otherwise something like '../admin/')
$admin_header_prefix = $admin_header_prefix ?? '';
?>

<header class="bg-blue-600 shadow-lg sticky top-0 z-50">
    <div class=" mx-auto px-2 sm:px-4 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo/Brand -->
            <div class="flex items-center flex-shrink-0">
                <a href="<?php echo $admin_header_prefix; ?>dashboard.php" class="text-white text-2xl font-bold hover:text-blue-100 transition-colors tracking-tight">
                    LMS ADMIN
                </a>
            </div>
            
            <!-- Admin Navigation Links -->
            <div class="hidden md:flex md:items-center md:space-x-1 flex-1 justify-center">
                <a href="<?php echo $admin_header_prefix; ?>dashboard.php" 
                   class="<?php echo ($current_page == 'dashboard.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Dashboard
                </a>
                <a href="<?php echo $admin_header_prefix; ?>users.php" 
                   class="<?php echo ($current_page == 'users.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Users
                </a>
                <a href="<?php echo $admin_header_prefix; ?>update_students.php" 
                   class="<?php echo ($current_page == 'update_students.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Students
                </a>
                <a href="<?php echo $admin_header_prefix; ?>manage_instructors.php" 
                   class="<?php echo ($current_page == 'manage_instructors.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Instructors
                </a>
                <a href="<?php echo $admin_header_prefix; ?>manage_teachers.php" 
                   class="<?php echo ($current_page == 'manage_teachers.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Teachers
                </a>
               
                <a href="<?php echo $admin_header_prefix; ?>verify_payments.php" 
                   class="<?php echo ($current_page == 'verify_payments.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Payments
                </a>
                <a href="<?php echo $admin_header_prefix; ?>manage_content.php" 
                   class="<?php echo ($current_page == 'manage_content.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Course Content
                </a>
                <a href="<?php echo $admin_header_prefix; ?>manage_publications.php" 
                   class="<?php echo ($current_page == 'manage_publications.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Publications & Orders
                </a>
                <a href="<?php echo $admin_header_prefix; ?>reports.php" 
                   class="<?php echo ($current_page == 'reports.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Reports
                </a>
                <a href="<?php echo $admin_header_prefix; ?>../dashboard/request_al_details.php" 
                   class="<?php echo ($current_page == 'request_al_details.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    A/L Results
                </a>
                <a href="<?php echo $admin_header_prefix; ?>settings.php" 
                   class="<?php echo ($current_page == 'settings.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Settings
                </a>
            </div>

            
            <!-- User Menu / Logout -->
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="hidden sm:block text-blue-100 text-sm font-medium">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </div>
                    <a href="<?php echo $admin_header_prefix; ?>../auth.php?logout=1" 
                       class="bg-white text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-md text-sm font-bold uppercase transition duration-150 ease-in-out shadow-sm border border-transparent">
                        Logout
                    </a>
                <?php endif; ?>
                
                <!-- Mobile menu button -->
                <button type="button" 
                        class="md:hidden mobile-menu-button bg-blue-700 inline-flex items-center justify-center p-2 rounded-md text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
                        aria-controls="mobile-menu" 
                        aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <!-- Hamburger Icon -->
                    <svg class="hamburger-icon block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <!-- Close Icon -->
                    <svg class="close-icon hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu -->
    <div class="md:hidden transition-all duration-300 ease-in-out overflow-hidden" id="mobile-menu">
        <div class="px-2 pt-2 pb-4 space-y-1 bg-blue-700 border-t border-blue-800 shadow-inner text-center">
            <a href="<?php echo $admin_header_prefix; ?>dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
            <a href="<?php echo $admin_header_prefix; ?>users.php" class="<?php echo ($current_page == 'users.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Manage Users</a>
            <a href="<?php echo $admin_header_prefix; ?>manage_teachers.php" class="<?php echo ($current_page == 'manage_teachers.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Manage Teachers</a>
            <a href="<?php echo $admin_header_prefix; ?>verify_payments.php" class="<?php echo ($current_page == 'verify_payments.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Payments</a>
            <a href="<?php echo $admin_header_prefix; ?>manage_publications.php" class="<?php echo ($current_page == 'manage_publications.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Publications & Orders</a>
            <a href="<?php echo $admin_header_prefix; ?>reports.php" class="<?php echo ($current_page == 'reports.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Reports</a>
            <a href="<?php echo $admin_header_prefix; ?>settings.php" class="<?php echo ($current_page == 'settings.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Settings</a>
            <?php if (isset($_SESSION['username'])): ?>
                <div class="border-t border-blue-800 mt-4 pt-4 pb-2">
                    <a href="<?php echo $admin_header_prefix; ?>../auth.php?logout=1" class="block px-3 py-2 rounded-md text-base font-medium text-blue-100 hover:text-white hover:bg-blue-800">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<style>
#mobile-menu {
    transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
    max-height: 0;
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
