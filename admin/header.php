<?php
// header.php - Admin header component with blue theme
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
// Prefix to use for links (empty if in same folder, otherwise something like '../admin/')
$admin_header_prefix = $admin_header_prefix ?? '';
?>

<!-- WhatsApp Debug Console -->
<?php if (isset($_SESSION['whatsapp_debug'])): ?>
<script>
    console.group('%c WhatsApp Debug Info ', 'background: #25d366; color: white; font-weight: bold; padding: 2px 4px; border-radius: 4px;');
    console.log('Target:', <?php echo json_encode($_SESSION['whatsapp_debug']['target'] ?? 'N/A'); ?>);
    console.log('Status:', <?php echo $_SESSION['whatsapp_debug']['success'] ? '"✅ SUCCESS"' : '"❌ FAILED"'; ?>);
    console.log('Response Message:', <?php echo json_encode($_SESSION['whatsapp_debug']['message'] ?? 'No message'); ?>);
    console.log('HTTP Code:', <?php echo json_encode($_SESSION['whatsapp_debug']['http_code'] ?? 'N/A'); ?>);
    console.log('Raw Response:', <?php echo json_encode($_SESSION['whatsapp_debug']['raw'] ?? 'N/A'); ?>);
    console.log('Time:', <?php echo json_encode($_SESSION['whatsapp_debug']['time'] ?? 'N/A'); ?>);
    console.groupEnd();
</script>
<?php unset($_SESSION['whatsapp_debug']); ?>
<?php endif; ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

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
                    Verify Payments
                </a>
                <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                <a href="<?php echo $admin_header_prefix; ?>teacher_payments.php" 
                   class="<?php echo ($current_page == 'teacher_payments.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Teacher Payouts
                </a>
                <?php endif; ?>
                <a href="<?php echo $admin_header_prefix; ?>manage_content.php" 
                   class="<?php echo ($current_page == 'manage_content.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Course Content
                </a>
                <a href="<?php echo $admin_header_prefix; ?>manage_publications.php" 
                   class="<?php echo ($current_page == 'manage_publications.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Publications & Orders
                </a>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="<?php echo $admin_header_prefix; ?>reports.php" 
                   class="<?php echo ($current_page == 'reports.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Reports
                </a>
                <?php endif; ?>
                <a href="<?php echo $admin_header_prefix; ?>../dashboard/request_al_details.php" 
                   class="<?php echo ($current_page == 'request_al_details.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    A/L Results
                </a>
                <a href="<?php echo $admin_header_prefix; ?>mass_messaging.php" 
                   class="<?php echo ($current_page == 'mass_messaging.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Messaging
                </a>
                <a href="<?php echo $admin_header_prefix; ?>settings.php" 
                   class="<?php echo ($current_page == 'settings.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?> px-2 py-1 rounded-md text-[10px] font-bold uppercase transition duration-150 ease-in-out">
                    Settings
                </a>
            </div>

            
            <!-- User Menu / Logout -->
            <div class="flex items-center space-x-2">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="relative" id="user-dropdown-container">
                        <button type="button" id="user-menu-button" class="flex items-center space-x-2 text-white hover:text-blue-100 transition-colors focus:outline-none p-1 rounded-lg hover:bg-blue-700">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center border-2 border-white/20 shadow-sm">
                                <i class="fas fa-user-shield text-xs"></i>
                            </div>
                            <i class="fas fa-chevron-down text-[8px] opacity-70"></i>
                        </button>
                        
                        <!-- Dropdown menu -->
                        <div id="user-dropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-2xl border border-gray-100 py-2 z-50 transform origin-top-right transition-all">
                            <div class="px-5 py-4 border-b border-gray-50 mb-1">
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Authenticated Account</p>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 font-bold">
                                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <p class="text-sm font-black text-gray-900 truncate"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                                        <p class="text-[10px] text-blue-600 font-black uppercase tracking-tight"><?php echo str_replace('_', ' ', $_SESSION['role']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="px-2">
                                <a href="<?php echo $admin_header_prefix; ?>dashboard.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 rounded-xl transition-colors">
                                    <i class="fas fa-chart-line text-gray-400 w-4"></i> Dashboard Overview
                                </a>
                                <a href="<?php echo $admin_header_prefix; ?>settings.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 rounded-xl transition-colors">
                                    <i class="fas fa-cog text-gray-400 w-4"></i> System Settings
                                </a>
                                
                                <div class="border-t border-gray-50 my-2 mx-2"></div>
                                
                                <a href="<?php echo $admin_header_prefix; ?>../auth.php?logout=1" class="flex items-center gap-3 px-4 py-3 text-sm text-red-600 hover:bg-red-50 rounded-xl transition-colors font-black">
                                    <i class="fas fa-sign-out-alt w-4"></i> Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
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
            <a href="<?php echo $admin_header_prefix; ?>verify_payments.php" class="<?php echo ($current_page == 'verify_payments.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Verify Payments</a>
            <a href="<?php echo $admin_header_prefix; ?>teacher_payments.php" class="<?php echo ($current_page == 'teacher_payments.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Teacher Payouts</a>
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
            <a href="<?php echo $admin_header_prefix; ?>reports.php" class="<?php echo ($current_page == 'reports.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Reports</a>
            <?php endif; ?>
            <a href="<?php echo $admin_header_prefix; ?>mass_messaging.php" class="<?php echo ($current_page == 'mass_messaging.php') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">Messaging</a>
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

    // User Menu Dropdown Toggle
    const userMenuButton = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-dropdown');

    if (userMenuButton && userDropdown) {
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', function(e) {
            if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });
    }
});
</script>
