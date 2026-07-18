<?php
// instructor/navbar.php - Navigation bar for instructor panel
$current_page = basename($_SERVER['PHP_SELF']);

// Get user info
$full_name = 'Instructor';
$profile_picture = null;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config.php';
    $stmt = $conn->prepare("SELECT first_name, second_name, profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $_SESSION['user_id']);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    if ($user_data) {
        $full_name = trim($user_data['first_name'] . ' ' . $user_data['second_name']);
        $profile_picture = $user_data['profile_picture'];
    }
    $stmt->close();
}

$root_url = '../';
?>

<nav class="bg-purple-700 shadow-lg sticky top-0 z-50">
    <div class="px-2 sm:px-4 lg:px-8">
        <div class="flex justify-between items-center h-12">
            <!-- Logo -->
            <div class="flex items-center flex-shrink-0">
                <a href="<?php echo $root_url; ?>" class="flex items-center hover:opacity-80 transition-opacity">
                    <img src="<?php echo $root_url; ?>assests/logo.jpeg" alt="LMS Logo" class="h-9 w-auto object-contain rounded">
                </a>
            </div>

            <!-- Main Nav Items -->
            <div class="hidden lg:flex lg:items-center lg:space-x-1 flex-1 justify-center">
                <a href="dashboard" 
                   class="<?php echo ($current_page == 'dashboard.php') ? 'bg-purple-800' : 'hover:bg-purple-800'; ?> text-white px-3 py-1.5 rounded text-[11px] font-bold uppercase transition">
                    DASHBOARD
                </a>
                <a href="payments" 
                   class="<?php echo ($current_page == 'payments.php') ? 'bg-purple-800' : 'hover:bg-purple-800'; ?> text-white px-3 py-1.5 rounded text-[11px] font-bold uppercase transition">
                    PAYMENTS
                </a>
                <a href="history" 
                   class="<?php echo ($current_page == 'history.php') ? 'bg-purple-800' : 'hover:bg-purple-800'; ?> text-white px-3 py-1.5 rounded text-[11px] font-bold uppercase transition">
                    HISTORY
                </a>
            </div>

            <!-- Right side -->
            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2">
                    <?php if ($profile_picture): ?>
                        <img src="<?php echo $root_url . htmlspecialchars($profile_picture); ?>" class="w-7 h-7 rounded-full border border-white shadow-sm object-cover">
                    <?php else: ?>
                        <div class="w-7 h-7 rounded-full bg-purple-500 flex items-center justify-center border border-white text-white text-[10px]">
                            <?php echo substr($full_name, 0, 1); ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-white text-[10px] font-medium hidden sm:block truncate max-w-[100px]"><?php echo htmlspecialchars($full_name); ?></span>
                </div>
                <a href="<?php echo $root_url; ?>auth.php?logout=1" class="text-white hover:text-purple-300 transition p-1" title="Logout">
                    <i class="fas fa-sign-out-alt text-sm"></i>
                </a>
                
                <!-- Mobile menu button -->
                <button type="button" onclick="document.getElementById('mobile-instructor-menu').classList.toggle('hidden')" class="lg:hidden text-white p-1">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Menu -->
    <div id="mobile-instructor-menu" class="hidden lg:hidden bg-purple-800 px-2 pt-2 pb-3 space-y-1 border-t border-purple-900">
        <a href="dashboard" class="block text-white px-3 py-2 rounded text-xs font-bold uppercase <?php echo ($current_page == 'dashboard.php') ? 'bg-purple-900' : ''; ?>">DASHBOARD</a>
        <a href="payments" class="block text-white px-3 py-2 rounded text-xs font-bold uppercase <?php echo ($current_page == 'payments.php') ? 'bg-purple-900' : ''; ?>">PAYMENTS</a>
        <a href="history" class="block text-white px-3 py-2 rounded text-xs font-bold uppercase <?php echo ($current_page == 'history.php') ? 'bg-purple-900' : ''; ?>">HISTORY</a>
    </div>
</nav>
