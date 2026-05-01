<?php
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../config.php';

// Profile data for logged-in users
$profile_picture = $_SESSION['profile_picture'] ?? '';
$full_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['second_name'] ?? ''));
$user_id = $_SESSION['user_id'] ?? '';
$email = $_SESSION['email'] ?? '';
$district = $_SESSION['district'] ?? '';

// URLs
$base_url = "http://localhost/lms/dashboard/";
$root_url = "http://localhost/lms/";

// Whatsapp debug display
if (isset($_SESSION['whatsapp_debug'])): ?>
    <script>
        console.group('WhatsApp Notification Debug');
        console.log('Status:', <?php echo json_encode($_SESSION['whatsapp_debug']['status'] ?? 'N/A'); ?>);
        console.log('Message:', <?php echo json_encode($_SESSION['whatsapp_debug']['message'] ?? 'N/A'); ?>);
        console.log('API Response:', <?php echo json_encode($_SESSION['whatsapp_debug']['response'] ?? 'N/A'); ?>);
        console.log('Raw Response:', <?php echo json_encode($_SESSION['whatsapp_debug']['raw'] ?? 'N/A'); ?>);
        console.log('Time:', <?php echo json_encode($_SESSION['whatsapp_debug']['time'] ?? 'N/A'); ?>);
        console.groupEnd();
    </script>
    <?php unset($_SESSION['whatsapp_debug']); ?>
<?php endif; ?>

<style>
    /* Reset browser defaults to remove any gaps */
    html,
    body {
        margin: 0;
        padding: 0;
        padding-top: 35px;
        /* Offset for fixed navbar */
        width: 100%;
        overflow-x: hidden;
    }

    /* Smooth navbar transitions */
    .nav-link-item::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 2px;
        background: currentColor;
        transition: all 0.3s ease;
        transform: translateX(-50%);
        border-radius: 2px;
    }

    .nav-link-item:hover::after {
        width: 20px;
    }

    .nav-link-item.active::after {
        width: 15px;
        height: 3px;
    }

    #main-navbar.scrolled {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
</style>

<nav id="main-navbar" class="fixed top-0 w-full z-50 transition-all duration-500 bg-red-600 shadow-md">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-14 sm:h-16 transition-all duration-500" id="navbar-container">
            <!-- Logo/Brand - Left Side -->
            <div class="flex items-center flex-shrink-0">
                <a href="<?php echo $base_url; ?>dashboard.php" class="flex items-center gap-2 group">
                    <span id="nav-logo"
                        class="text-xl sm:text-2xl font-bold tracking-tighter transition-colors duration-500 text-white">
                        LERNERR<span id="logo-dot" class="text-white">.LK</span>
                    </span>
                </a>
            </div>

            <!-- Desktop Navigation - Center -->
            <div class="hidden lg:flex items-center justify-center flex-1 px-4 xl:px-6">
                <div class="flex space-x-1">
                    <?php
                    $nav_items = [
                        ['HOME', 'dashboard.php', 'මුල් පිටුව'],
                        ['PROFILE', 'profile.php', 'ගිණුම'],
                        ['RECORDINGS', 'recordings.php', 'පටිගත කිරීම්'],
                        ['LIVE CLASSES', 'live_classes.php', 'සජීවී පන්ති'],
                        ['INSTRUCTORS', 'instructors.php', 'ගුරුවරුන්'],
                        ['PAYMENTS', 'payments.php', 'ගෙවීම්'],
                        ['EXAM CENTER', 'exam_center.php', 'විභාග'],
                        ['ONLINE COURSES', 'online_courses.php', 'පාඨමාලා'],
                        ['PUBLICATIONS', 'publications.php', 'ප්‍රකාශන'],
                        ['A/L RESULTS', 'ALDetails.php', 'ප්‍රතිඵල'],
                        ['ABOUT US', 'about_us.php', 'අප ගැන']
                    ];
                    foreach ($nav_items as $item):
                        $is_active = ($current_page == $item[1]);
                        $active_class = $is_active ? 'active font-bold' : 'font-semibold';
                        $color_class = 'text-white hover:text-red-100';
                        ?>
                        <a href="<?php echo $base_url . $item[1]; ?>"
                            class="nav-link-item group relative px-2.5 py-2 text-[10px] xl:text-[11px] tracking-tight transition-all duration-300 <?php echo $color_class . ' ' . $active_class; ?>">
                            <span><?php echo $item[0]; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Side Actions -->
            <div class="flex items-center gap-2">
                <?php if (!isset($_SESSION['role'])): ?>
                    <a href="<?php echo $root_url; ?>register.php" id="nav-register-btn"
                        class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-full text-[9px] font-bold tracking-widest transition-all hover:scale-105 active:scale-95 bg-white text-red-600">
                        <span>REGISTER NOW</span>
                        <div class="rounded-full p-0.5 leading-none bg-red-600 text-white">
                            <i class="fas fa-arrow-up rotate-45 text-[7px]"></i>
                        </div>
                    </a>
                <?php else: ?>
                    <button onclick="openProfileModal()" id="nav-profile-btn"
                        class="flex items-center gap-2 group p-0.5 pr-2 rounded-full transition-all <?php echo ($current_page == 'dashboard.php') ? 'bg-white/10 hover:bg-white/20' : 'bg-slate-100 hover:bg-slate-200'; ?>">
                        <div
                            class="w-7 h-7 rounded-full bg-red-600 flex items-center justify-center text-white font-bold border border-white shadow-sm overflow-hidden">
                            <?php if (!empty($profile_picture)): ?>
                                <img src="<?php echo $root_url . $profile_picture; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span
                                    class="text-[10px]"><?php echo strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <span id="nav-profile-name" class="text-[10px] font-bold hidden md:block text-white">
                            <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?>
                        </span>
                    </button>
                <?php endif; ?>

                <!-- Mobile menu button -->
                <button type="button" onclick="toggleMobileMenu()" id="mobile-menu-btn"
                    class="lg:hidden p-1.5 rounded-lg transition-colors text-white hover:bg-white/10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile menu -->
<div id="mobile-menu" class="fixed inset-0 z-[60] lg:hidden hidden transition-all duration-500">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md" onclick="toggleMobileMenu()"></div>

    <!-- Menu Panel -->
    <div class="absolute right-0 top-0 bottom-0 w-full max-w-xs bg-white shadow-2xl overflow-y-auto transform transition-transform duration-500 translate-x-full"
        id="mobile-panel">
        <div class="p-6">
            <div class="flex items-center justify-between mb-8">
                <span class="text-2xl font-black tracking-tighter text-slate-900">LERNERR<span
                        class="text-red-600">.LK</span></span>
                <button onclick="toggleMobileMenu()"
                    class="p-2 bg-slate-100 rounded-full text-slate-500 hover:text-red-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-1">
                <?php foreach ($nav_items as $item):
                    $is_active = ($current_page == $item[1]);
                    ?>
                    <a href="<?php echo $base_url . $item[1]; ?>"
                        class="flex items-center justify-between px-4 py-4 rounded-2xl transition-all <?php echo $is_active ? 'bg-red-50 text-red-600 font-black' : 'text-slate-600 hover:bg-slate-50 font-bold'; ?>">
                        <div class="flex flex-col">
                            <span class="text-sm"><?php echo $item[0]; ?></span>
                            <span class="text-[10px] font-normal opacity-60"><?php echo $item[2]; ?></span>
                        </div>
                        <?php if ($is_active): ?>
                            <div class="w-1.5 h-1.5 bg-red-600 rounded-full"></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (!isset($_SESSION['role'])): ?>
                <div class="mt-8 pt-8 border-t border-slate-100">
                    <a href="<?php echo $root_url; ?>register.php"
                        class="flex items-center justify-between bg-red-600 text-white p-5 rounded-2xl shadow-xl shadow-red-600/20 group hover:scale-[1.02] transition-all">
                        <span class="font-black tracking-widest text-sm">REGISTER NOW</span>
                        <div class="bg-white/20 p-2 rounded-full group-hover:bg-white/40 transition-colors">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-8 pt-8 border-t border-slate-100">
                    <a href="<?php echo $root_url; ?>auth.php?logout=1"
                        class="flex items-center justify-between bg-slate-100 text-red-600 p-5 rounded-2xl font-black text-sm">
                        <span>LOGOUT</span>
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Profile Modal -->
<div id="userProfileModal"
    class="hidden fixed inset-0 bg-black bg-opacity-60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="relative h-32 bg-red-600">
            <button onclick="closeProfileModal()"
                class="absolute top-4 right-4 text-white hover:text-red-200 transition-colors z-10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
            <div class="absolute -bottom-12 left-1/2 transform -translate-x-1/2">
                <div
                    class="w-24 h-24 rounded-full border-4 border-white shadow-lg bg-red-600 flex items-center justify-center text-white text-3xl font-bold overflow-hidden">
                    <?php if (!empty($profile_picture)): ?>
                        <img src="<?php echo $root_url . $profile_picture; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="pt-16 pb-8 px-8 flex flex-col items-center">
            <h3 class="text-2xl font-black text-gray-900 mb-1"><?php echo htmlspecialchars($full_name); ?></h3>
            <p class="text-red-600 font-bold text-sm mb-4"><?php echo strtoupper($_SESSION['role'] ?? 'Student'); ?> ID:
                <?php echo htmlspecialchars($user_id); ?>
            </p>

            <div class="w-full space-y-3 mb-6">
                <div class="flex items-center p-3 bg-slate-50 rounded-xl">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3 text-red-600">
                        <i class="fas fa-envelope text-xs"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-[10px] text-gray-500 uppercase font-black tracking-wider">Email Address</p>
                        <p class="text-sm text-gray-900 font-bold"><?php echo htmlspecialchars($email); ?></p>
                    </div>
                </div>

                <div class="flex items-center p-3 bg-slate-50 rounded-xl">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3 text-red-600">
                        <i class="fas fa-map-marker-alt text-xs"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-[10px] text-gray-500 uppercase font-black tracking-wider">District</p>
                        <p class="text-sm text-gray-900 font-bold"><?php echo htmlspecialchars($district); ?></p>
                    </div>
                </div>
            </div>

            <!-- QR Code Section -->
            <div class="flex flex-col items-center">
                <div id="userQRCode" class="p-3 bg-white border-2 border-red-500 rounded-2xl shadow-inner mb-3"></div>
                <p class="text-[10px] text-gray-500 italic max-w-[200px] text-center font-medium">Scan for quick
                    admission identification.</p>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-8 py-4 flex justify-between items-center">
            <?php if (($_SESSION['role'] ?? '') !== 'admin'): ?>
                <a href="<?php echo $base_url; ?>profile.php"
                    class="text-slate-600 font-bold text-xs hover:text-red-600 transition-colors uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            <?php endif; ?>
            <a href="<?php echo $root_url; ?>auth.php?logout=1"
                class="text-red-600 font-black text-xs hover:text-red-700 transition-colors uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</div>

<!-- Guest Auth Modal -->
<div id="guestAuthModal"
    class="hidden fixed inset-0 bg-black bg-opacity-60 z-[100] flex items-center justify-center p-4 backdrop-blur-md">
    <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center animate-fade-in">
        <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-lock text-3xl"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-900 mb-2">Login Required</h3>
        <p class="text-slate-500 font-bold text-sm mb-8">Please login to access this section and start your learning
            journey.</p>
        <div class="space-y-3">
            <a href="<?php echo $root_url; ?>index.php"
                class="block w-full bg-red-600 text-white font-black py-4 rounded-2xl hover:bg-red-700 transition-all shadow-lg shadow-red-600/20">LOGIN
                NOW</a>
            <a href="<?php echo $root_url; ?>register.php"
                class="block w-full bg-slate-100 text-slate-700 font-black py-4 rounded-2xl hover:bg-slate-200 transition-all">CREATE
                ACCOUNT</a>
            <button onclick="closeGuestAuthModal()"
                class="text-slate-400 font-bold text-xs uppercase tracking-widest mt-4 hover:text-slate-600 transition-colors">Maybe
                Later</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    // Scroll logic
    window.addEventListener('scroll', function () {
        const nav = document.getElementById('main-navbar');
        const logo = document.getElementById('nav-logo');
        const logoDot = document.getElementById('logo-dot');
        const isHome = "<?php echo $current_page; ?>" === "dashboard.php";
        const navLinks = document.querySelectorAll('.nav-link-item');
        const regBtn = document.getElementById('nav-register-btn');
        const profileBtn = document.getElementById('nav-profile-btn');
        const mobileBtn = document.getElementById('mobile-menu-btn');

        // Background is now red by default
    });

    // Mobile menu toggle
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        const panel = document.getElementById('mobile-panel');
        if (menu.classList.contains('hidden')) {
            menu.classList.remove('hidden');
            setTimeout(() => panel.classList.remove('translate-x-full'), 10);
            document.body.style.overflow = 'hidden';
        } else {
            panel.classList.add('translate-x-full');
            setTimeout(() => {
                menu.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }, 500);
        }
    }

    // Profile Modal Logic
    let qrGenerated = false;
    function openProfileModal() {
        const modal = document.getElementById('userProfileModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (!qrGenerated && typeof QRCode !== 'undefined') {
                new QRCode(document.getElementById("userQRCode"), {
                    text: "<?php echo $user_id; ?>",
                    width: 100,
                    height: 100,
                    colorDark: "#dc2626",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
                qrGenerated = true;
            }
        }
    }

    function closeProfileModal() {
        document.getElementById('userProfileModal')?.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function showGuestAuthModal() {
        document.getElementById('guestAuthModal')?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeGuestAuthModal() {
        document.getElementById('guestAuthModal')?.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Intercept restricted links for guests
    document.addEventListener('DOMContentLoaded', function () {
        <?php if (!isset($_SESSION['role'])): ?>
            const links = document.querySelectorAll('nav a, #mobile-menu a');
            links.forEach(link => {
                link.addEventListener('click', function (e) {
                    const href = this.getAttribute('href');
                    if (!href || href.includes('#')) return;
                    const allowed = ['dashboard.php', 'live_classes.php', 'publications.php', 'about_us.php', 'index.php', 'ALDetails.php', 'register.php'];
                    const isAllowed = allowed.some(p => href.includes(p));
                    if (!isAllowed) {
                        e.preventDefault();
                        showGuestAuthModal();
                    }
                });
            });
        <?php endif; ?>
    });
</script>