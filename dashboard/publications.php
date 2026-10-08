<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

function get_img_url($path) {
    if (empty($path)) return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return '../' . ltrim($path, '/');
}

function get_fallback_gradient($name) {
    $gradients = [
        'from-blue-600 to-indigo-700',
        'from-purple-600 to-pink-600',
        'from-emerald-600 to-teal-700',
        'from-red-600 to-rose-700',
        'from-amber-600 to-orange-700',
        'from-cyan-600 to-blue-700'
    ];
    $idx = abs(crc32($name ?? '')) % count($gradients);
    return $gradients[$idx];
}

// Ensure is_free and pdf_path columns exist
$chk_col1 = $conn->query("SHOW COLUMNS FROM publications LIKE 'pdf_path'");
if ($chk_col1 && $chk_col1->num_rows == 0) {
    $conn->query("ALTER TABLE publications ADD COLUMN pdf_path VARCHAR(255) DEFAULT NULL AFTER image_path");
}
$chk_col2 = $conn->query("SHOW COLUMNS FROM publications LIKE 'is_free'");
if ($chk_col2 && $chk_col2->num_rows == 0) {
    $conn->query("ALTER TABLE publications ADD COLUMN is_free TINYINT(1) NOT NULL DEFAULT 0 AFTER discount");
}

// Prepare user info if logged in
$user_logged_in = isset($_SESSION['user_id']);
$user_id = $user_logged_in ? $_SESSION['user_id'] : '';
$user_name = '';
$user_contact = '';
$user_district = '';

if ($user_logged_in) {
    $stmt = $conn->prepare("SELECT first_name, second_name, mobile_number, district FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_name = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
        $user_contact = $row['mobile_number'] ?? '';
        $user_district = $row['district'] ?? '';
    }
    $stmt->close();
}

// Fetch Categories
$categories = $conn->query("SELECT * FROM publication_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch Publications
$pub_sql = "SELECT p.*, c.name as category_name FROM publications p LEFT JOIN publication_categories c ON p.category_id = c.id ORDER BY p.created_at DESC";
$publications = $conn->query($pub_sql)->fetch_all(MYSQLI_ASSOC);

// Fetch User Orders if logged in
$view = $_GET['view'] ?? 'browse';
$user_orders = [];
$user_orders_count = 0;

if ($user_logged_in) {
    $cnt_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM publication_orders WHERE user_id = ?");
    $cnt_stmt->bind_param("s", $user_id);
    $cnt_stmt->execute();
    $user_orders_count = $cnt_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $cnt_stmt->close();

    if ($view == 'orders') {
        $orders_sql = "SELECT o.*, 
                        GROUP_CONCAT(p.title SEPARATOR ', ') as titles,
                        SUM(oi.quantity * oi.price_at_order) as total_amount
                       FROM publication_orders o
                       JOIN publication_order_items oi ON o.id = oi.order_id
                       JOIN publications p ON oi.publication_id = p.id
                       WHERE o.user_id = ?
                       GROUP BY o.id
                       ORDER BY o.id DESC";
        $stmt = $conn->prepare($orders_sql);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $user_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $view = 'browse';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publications & Study Materials - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .bg-design {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-image: url('https://res.cloudinary.com/dnfbik3if/image/upload/v1791440549/Add_mixed_characters_peering_cor__20261008115221_sdaspr.jpg');
            background-size: 100% 100%;
            background-position: center top;
            background-repeat: no-repeat;
            z-index: 0;
            pointer-events-none;
        }
        @media (max-width: 768px) {
            .bg-design {
                background-size: 100% auto;
                background-position: top center;
            }
        }

        .content-overlay {
            min-height: 100vh;
            position: relative;
            z-index: 10;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.9);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
            border-color: #fca5a5;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        #toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #10b981;
            color: #fff;
            text-align: center;
            border-radius: 12px;
            padding: 16px;
            position: fixed;
            z-index: 1000;
            right: 30px;
            top: 30px;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        #toast.show {
            visibility: visible;
            -webkit-animation: fadeinToast 0.5s, fadeoutToast 0.5s 1.5s;
            animation: fadeinToast 0.5s, fadeoutToast 0.5s 1.5s;
        }

        @-webkit-keyframes fadeinToast { from {top: 0; opacity: 0;} to {top: 30px; opacity: 1;} }
        @keyframes fadeinToast { from {top: 0; opacity: 0;} to {top: 30px; opacity: 1;} }
        @-webkit-keyframes fadeoutToast { from {top: 30px; opacity: 1;} to {top: 0; opacity: 0;} }
        @keyframes fadeoutToast { from {top: 30px; opacity: 1;} to {top: 0; opacity: 0;} }
    </style>
</head>
<body class="min-h-screen bg-white relative">
    <div class="bg-design"></div>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div id="toast">Order Request Sent! 🚀</div>

    <!-- Main Content -->
    <main class="content-overlay pt-20 sm:pt-24 pb-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Red Title Bar -->
            <div class="max-w-4xl mx-auto mb-3 sm:mb-4">
                <div class="relative rounded-2xl bg-red-600 p-4 sm:p-5 text-white shadow-lg shadow-red-600/20 flex items-center justify-between overflow-hidden">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-11 sm:h-11 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center flex-shrink-0 shadow-inner">
                            <i class="fas fa-book-open text-base sm:text-lg text-white"></i>
                        </div>
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black tracking-tight leading-tight">
                            Publications & Study Materials
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Separate Subtext Section (Gray Color) -->
            <div class="max-w-4xl mx-auto mb-6 sm:mb-8">
                <div class="bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-sm">
                    <p class="text-[11.5px] sm:text-[14.5px] text-slate-800 font-medium leading-relaxed mb-1.5">
                        මෙහිදී Lernerr.LK වෙතින් නොමිලේ හෝ මුදල් ගෙවා ලබා ගැනීමට නිකුත් කරනු ලබන සියලුම මුද්‍රිත නිබන්ධන, PDF, පොත්, සඟරා යනාදිය ඔබට ලබාගත හැකියි.
                    </p>
                    <p class="text-[10.5px] sm:text-xs text-slate-600 font-medium leading-normal">
                        Here you can access all printed tutorials, PDFs, books, magazines, etc., released for free or for purchase from Lernerr.LK.
                    </p>
                </div>
            </div>

            <?php if ($view === 'browse'): ?>
                <!-- SECTION: AVAILABLE PUBLICATIONS -->
                <div class="mb-14">
                    <!-- Section Header Card -->
                    <div class="bg-white/85 backdrop-blur-md border border-slate-200/90 rounded-2xl p-4 sm:p-5 mb-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2.5">
                                <span class="w-2.5 h-6 bg-red-600 rounded-full"></span>
                                <span>Available Publications</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-1 font-medium">අපගේ ආයතනයෙන් නිකුත් කරනු ලබන සියලුම පොත්, සඟරා සහ නිබන්ධන</p>
                        </div>

                        <!-- Search & Filters -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <?php if ($user_logged_in): ?>
                                <a href="publications.php?view=orders" class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-800 text-xs font-bold rounded-xl shadow-xs transition-all">
                                    <i class="fas fa-shopping-bag text-red-600"></i>
                                    <span>My Orders (<?php echo $user_orders_count; ?>)</span>
                                </a>
                            <?php endif; ?>

                            <div class="relative">
                                <input type="text" id="pubSearchInput" oninput="filterPublications()" placeholder="Search publications..." 
                                       class="pl-9 pr-4 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none w-48 sm:w-56 shadow-xs">
                                <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            </div>

                            <select id="categoryFilterSelect" onchange="filterPublications()" class="py-2 px-3 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:outline-none cursor-pointer shadow-xs">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if (empty($publications)): ?>
                        <div class="bg-white/80 backdrop-blur-md rounded-3xl p-12 text-center border border-slate-200 shadow-sm max-w-md mx-auto">
                            <i class="fas fa-book text-slate-300 text-5xl mb-3"></i>
                            <p class="text-sm font-bold text-slate-700">No publications found at the moment.</p>
                        </div>
                    <?php else: ?>
                        <!-- Publications Grid -->
                        <div id="pubGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($publications as $pub):
                                $is_free_pub = !empty($pub['is_free']) || ($pub['price'] <= 0 && !empty($pub['pdf_path']));
                                $final_price = max(0, $pub['price'] - $pub['discount']);
                                $discount_pct = $pub['price'] > 0 ? round(($pub['discount'] / $pub['price']) * 100) : 0;
                            ?>
                                <div class="pub-card glass-card rounded-2xl overflow-hidden flex flex-col justify-between shadow-sm border border-slate-200/80"
                                     data-category="<?php echo $pub['category_id'] ?? 0; ?>"
                                     data-search="<?php echo htmlspecialchars(strtolower(($pub['title'] ?? '') . ' ' . ($pub['description'] ?? '') . ' ' . ($pub['category_name'] ?? ''))); ?>">
                                    <div>
                                        <!-- 1. Cover Image (Displayed First) -->
                                        <div class="relative aspect-[1.91/1] w-full overflow-hidden bg-slate-100 border-b border-slate-100">
                                            <?php if (!empty($pub['image_path'])): ?>
                                                <img src="<?php echo htmlspecialchars(get_img_url($pub['image_path'])); ?>" alt="<?php echo htmlspecialchars($pub['title']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br <?php echo get_fallback_gradient($pub['title']); ?> text-white p-4 text-center">
                                                    <i class="fas fa-book-open text-3xl mb-1.5 opacity-90"></i>
                                                    <span class="font-black text-sm drop-shadow-sm truncate max-w-full"><?php echo htmlspecialchars($pub['title']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Floating Badges -->
                                            <div class="absolute top-3 left-3 flex flex-wrap items-center gap-1.5 z-10">
                                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-slate-900/85 backdrop-blur-md text-white border border-white/20 shadow-xs">
                                                    <?php echo htmlspecialchars($pub['category_name'] ?? 'Publication'); ?>
                                                </span>
                                            </div>

                                            <div class="absolute top-3 right-3 z-10 flex items-center gap-1.5">
                                                <?php if ($is_free_pub): ?>
                                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-600/95 backdrop-blur-md text-white shadow-xs border border-white/20 flex items-center gap-1">
                                                        <i class="fas fa-gift text-[9px]"></i> FREE PDF
                                                    </span>
                                                <?php elseif ($pub['discount'] > 0): ?>
                                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-red-600/95 backdrop-blur-md text-white shadow-xs border border-white/20">
                                                        -<?php echo $discount_pct; ?>% OFF
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- 2. Card Content -->
                                        <div class="p-4 sm:p-5">
                                            <h4 class="text-sm sm:text-base font-bold text-slate-900 leading-snug line-clamp-2 mb-1.5">
                                                <?php echo htmlspecialchars($pub['title']); ?>
                                            </h4>

                                            <?php if (!empty($pub['description'])): ?>
                                                <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed mb-3">
                                                    <?php echo htmlspecialchars($pub['description']); ?>
                                                </p>
                                            <?php endif; ?>

                                            <!-- Pricing & Format Box -->
                                            <div class="bg-slate-50/80 border border-slate-100 rounded-xl p-2.5 sm:p-3 mb-1">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex flex-col">
                                                        <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Price</span>
                                                        <?php if ($is_free_pub): ?>
                                                            <span class="text-sm sm:text-base font-black text-emerald-600">FREE</span>
                                                        <?php else: ?>
                                                            <div class="flex items-baseline gap-1.5">
                                                                <span class="text-sm sm:text-base font-black text-red-600">Rs. <?php echo number_format($final_price, 0); ?></span>
                                                                <?php if ($pub['discount'] > 0): ?>
                                                                    <span class="text-[10px] text-slate-400 line-through font-semibold">Rs. <?php echo number_format($pub['price'], 0); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Format</span>
                                                        <span class="block text-xs font-bold text-slate-700">
                                                            <?php echo !empty($pub['pdf_path']) ? 'Digital PDF' : 'Printed Book / Notes'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 3. Action Button -->
                                    <div class="p-4 sm:p-5 pt-0">
                                        <?php if ($is_free_pub): ?>
                                            <?php if (!empty($pub['pdf_path'])): ?>
                                                <a href="<?php echo (isset($root_url) ? $root_url : '../'); ?>download_publication.php?id=<?php echo $pub['id']; ?>" 
                                                   class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95 group">
                                                    <i class="fas fa-download group-hover:translate-y-0.5 transition-transform"></i>
                                                    <span>Download Free PDF</span>
                                                </a>
                                            <?php else: ?>
                                                <button disabled class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 bg-slate-100 text-slate-400 font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed">
                                                    <i class="fas fa-clock"></i> PDF Coming Soon
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button onclick="openOrderModal(<?php echo htmlspecialchars(json_encode([
                                                'id'    => $pub['id'],
                                                'title' => $pub['title'],
                                                'price' => $final_price
                                            ])); ?>)" class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 bg-red-600 hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95">
                                                <i class="fas fa-shopping-cart"></i>
                                                <span>Buy / Order Now</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination Controls -->
                        <div id="pubPagination" class="mt-8 flex justify-center items-center gap-2"></div>
                    <?php endif; ?>
                </div>

            <?php elseif ($view === 'orders' && $user_logged_in): ?>
                <!-- SECTION: MY ORDERS -->
                <div class="mb-14">
                    <div class="bg-white/85 backdrop-blur-md border border-slate-200/90 rounded-2xl p-4 sm:p-5 mb-6 shadow-xs flex items-center justify-between">
                        <div>
                            <h3 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2.5">
                                <span class="w-2.5 h-6 bg-red-600 rounded-full"></span>
                                <span>My Publication Orders</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-1 font-medium">Track the status of your study material orders and shipments</p>
                        </div>
                        <a href="publications.php" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl transition-all">
                            <i class="fas fa-arrow-left"></i> Back to Publications
                        </a>
                    </div>

                    <?php if (empty($user_orders)): ?>
                        <div class="bg-white/80 backdrop-blur-md rounded-3xl p-12 text-center border border-slate-200 shadow-sm max-w-md mx-auto">
                            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl shadow-inner">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <h4 class="text-base font-bold text-slate-800 mb-1">No Orders Found</h4>
                            <p class="text-xs text-slate-500 mb-4">You haven't placed any publication orders yet.</p>
                            <a href="publications.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 text-white font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-red-700 transition-all shadow-md">
                                <i class="fas fa-book-open"></i> Browse Publications
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="bg-white/90 backdrop-blur-md rounded-2xl overflow-hidden border border-slate-200 shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200">
                                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Order Details</th>
                                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Payment</th>
                                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Total</th>
                                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($user_orders as $order): ?>
                                            <tr class="hover:bg-slate-50/70 transition-colors">
                                                <td class="px-6 py-4">
                                                    <p class="text-[10px] font-black text-slate-400 mb-0.5">Order #<?php echo $order['id']; ?></p>
                                                    <h4 class="font-bold text-slate-900 text-xs sm:text-sm"><?php echo htmlspecialchars($order['titles']); ?></h4>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="inline-block px-2.5 py-1 bg-slate-100 rounded-lg text-[10px] font-black text-slate-600 uppercase">
                                                        <?php echo htmlspecialchars(str_replace('_', ' ', $order['payment_method'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <?php
                                                        $status_colors = [
                                                            'pending'            => 'bg-amber-100 text-amber-700',
                                                            'preparing'          => 'bg-blue-100 text-blue-700',
                                                            'hand_order_to_delivery' => 'bg-indigo-100 text-indigo-700',
                                                            'canceled'           => 'bg-rose-100 text-rose-700',
                                                            'completed'          => 'bg-emerald-100 text-emerald-700',
                                                            'return_requested'   => 'bg-purple-100 text-purple-700'
                                                        ];
                                                        $color = $status_colors[$order['status']] ?? 'bg-slate-100 text-slate-600';
                                                    ?>
                                                    <span class="inline-block px-3 py-1 <?php echo $color; ?> rounded-full text-[11px] font-bold uppercase tracking-tight">
                                                        <?php echo htmlspecialchars(str_replace('_', ' ', $order['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-right font-black text-slate-900 text-xs sm:text-sm">
                                                    Rs. <?php echo number_format($order['total_amount'], 0); ?>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <?php if ($order['status'] === 'completed'): ?>
                                                        <button onclick="returnOrder(<?php echo $order['id']; ?>)" 
                                                                class="bg-red-50 text-red-600 px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider hover:bg-red-600 hover:text-white transition-all shadow-xs border border-red-100">
                                                            <i class="fas fa-undo mr-1"></i> Return
                                                        </button>
                                                    <?php elseif ($order['status'] === 'pending'): ?>
                                                        <button onclick="cancelOrder(<?php echo $order['id']; ?>)" 
                                                                class="bg-rose-50 text-rose-600 px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider hover:bg-rose-600 hover:text-white transition-all shadow-xs border border-rose-100">
                                                            <i class="fas fa-times-circle mr-1"></i> Cancel
                                                        </button>
                                                    <?php elseif ($order['status'] === 'hand_order_to_delivery'): ?>
                                                        <button onclick="confirmDelivery(<?php echo $order['id']; ?>)" 
                                                                class="bg-emerald-50 text-emerald-600 px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider hover:bg-emerald-600 hover:text-white transition-all shadow-xs border border-emerald-100">
                                                            <i class="fas fa-check-circle mr-1"></i> Confirm Received
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ===================== PREMIUM ORDER MODAL ===================== -->
    <div id="orderModal" class="fixed inset-0 z-[100] hidden overflow-y-auto" role="dialog">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity" onclick="closeOrderModal()"></div>

        <!-- Scrollable Container -->
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="modal-inner relative bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden border border-slate-100">
                
                <!-- Header -->
                <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">Order Publication</h2>
                        <p class="text-slate-500 font-medium text-xs mt-0.5">Shipping & Payment Details</p>
                    </div>
                    <button onclick="closeOrderModal()" class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-5 sm:p-6">
                    <!-- Order Snapshot -->
                    <div class="bg-slate-900 rounded-2xl p-4 mb-5 flex items-center justify-between gap-4 shadow-lg text-white">
                        <div class="flex items-center gap-3">
                           <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-white text-lg shrink-0">
                               <i class="fas fa-box-open"></i>
                           </div>
                           <div>
                               <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">SELECTED RESOURCE</p>
                               <h4 id="modalPubTitle" class="text-xs sm:text-sm font-semibold text-white leading-tight line-clamp-1"></h4>
                           </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">TOTAL COST</p>
                            <span id="modalPubPrice" class="text-base sm:text-lg font-extrabold text-red-400 tracking-tight"></span>
                        </div>
                    </div>

                    <!-- Form -->
                    <form id="orderForm" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" id="pubId" name="publication_id">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block ml-0.5">Full Name</label>
                                <input type="text" name="name" required value="<?php echo htmlspecialchars($user_name); ?>"
                                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl font-medium text-xs text-slate-800 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block ml-0.5">WhatsApp Number</label>
                                <input type="text" name="contact_number" required value="<?php echo htmlspecialchars($user_contact); ?>"
                                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl font-medium text-xs text-slate-800 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block ml-0.5">District</label>
                                <input type="text" name="district" required value="<?php echo htmlspecialchars($user_district); ?>"
                                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl font-medium text-xs text-slate-800 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-slate-600 block ml-0.5">Quantity</label>
                                <select name="quantity" class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl font-medium text-xs text-slate-800 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                                    <?php for($i=1; $i<=10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Piece<?php echo $i>1?'s':'' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-600 block ml-0.5">Delivery Address</label>
                            <textarea name="address" required rows="2" placeholder="Street, Building, City..."
                                      class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl font-medium text-xs text-slate-800 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all resize-none"></textarea>
                        </div>

                        <!-- Payment Method -->
                        <div class="space-y-2">
                            <label class="text-xs font-semibold text-slate-600 block ml-0.5">Payment Method</label>
                            <input type="hidden" name="payment_method" id="paymentMethodInput">
                            <div class="grid grid-cols-2 gap-3">
                                <div id="tabCard" onclick="selectPayment('card')" class="cursor-pointer border border-slate-200 p-3 rounded-2xl flex flex-col items-center gap-1.5 hover:border-red-300 hover:bg-red-50/30 transition-all group">
                                    <i class="fas fa-credit-card text-lg text-slate-400 group-hover:text-red-500"></i>
                                    <span class="text-xs font-semibold text-slate-600 group-hover:text-red-700">Online Payment</span>
                                </div>
                                <div id="tabBank" onclick="selectPayment('bank_transfer')" class="cursor-pointer border border-slate-200 p-3 rounded-2xl flex flex-col items-center gap-1.5 hover:border-red-300 hover:bg-red-50/30 transition-all group">
                                    <i class="fas fa-university text-lg text-slate-400 group-hover:text-red-500"></i>
                                    <span class="text-xs font-semibold text-slate-600 group-hover:text-red-700">Bank Transfer</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details (Hidden by default) -->
                        <div id="bankDetailsSection" class="hidden animate-fade-in bg-slate-50 rounded-2xl p-4 border border-slate-200 space-y-3">
                             <div class="flex items-center gap-2.5">
                                 <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center text-slate-800 shadow-xs border border-slate-100">
                                     <i class="fas fa-building-columns text-xs"></i>
                                 </div>
                                 <div>
                                     <h5 class="font-bold text-xs text-slate-900">Bank Transfer Accounts</h5>
                                     <p class="text-[10.5px] text-slate-500 font-medium">Please deposit or transfer the amount to one of our accounts:</p>
                                 </div>
                             </div>

                             <div class="space-y-2.5">
                                 <!-- Peoples' Bank Card -->
                                 <div class="bg-white p-3 rounded-xl border border-slate-200/90 shadow-2xs space-y-1">
                                     <div class="flex items-center justify-between">
                                         <span class="text-[10px] font-extrabold text-amber-700 bg-amber-50 border border-amber-200/60 px-2 py-0.5 rounded-md uppercase tracking-wider">PEOPLES' BANK</span>
                                         <span class="text-[10px] font-semibold text-slate-500">GAMPOLA BRANCH</span>
                                     </div>
                                     <div class="flex items-baseline justify-between pt-0.5">
                                         <span class="text-[11px] text-slate-500 font-medium">Account No:</span>
                                         <span class="font-mono font-black text-xs sm:text-sm text-red-600 tracking-wider select-all">018200210032205</span>
                                     </div>
                                     <div class="flex items-baseline justify-between border-t border-slate-100 pt-0.5 text-[10.5px]">
                                         <span class="text-slate-400 font-medium">Name:</span>
                                         <span class="font-bold text-slate-700">KVK SAMEERA PERERA</span>
                                     </div>
                                 </div>

                                 <!-- BOC (Bank of Ceylon) Card -->
                                 <div class="bg-white p-3 rounded-xl border border-slate-200/90 shadow-2xs space-y-1">
                                     <div class="flex items-center justify-between">
                                         <span class="text-[10px] font-extrabold text-blue-700 bg-blue-50 border border-blue-200/60 px-2 py-0.5 rounded-md uppercase tracking-wider">BOC (BANK OF CEYLON)</span>
                                         <span class="text-[10px] font-semibold text-slate-500">GAMPOLA BRANCH</span>
                                     </div>
                                     <div class="flex items-baseline justify-between pt-0.5">
                                         <span class="text-[11px] text-slate-500 font-medium">Account No:</span>
                                         <span class="font-mono font-black text-xs sm:text-sm text-red-600 tracking-wider select-all">0082153803</span>
                                     </div>
                                     <div class="flex items-baseline justify-between border-t border-slate-100 pt-0.5 text-[10.5px]">
                                         <span class="text-slate-400 font-medium">Name:</span>
                                         <span class="font-bold text-slate-700">KVK SAMEERA PERERA</span>
                                     </div>
                                 </div>
                             </div>

                             <div class="space-y-1 pt-1">
                                 <label class="text-xs font-semibold text-slate-600 block">Upload Payment Slip / Receipt</label>
                                 <input type="file" name="receipt" id="receiptFile" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-red-600 file:text-white hover:file:bg-slate-900 cursor-pointer">
                             </div>
                        </div>

                        <div id="formFeedback" class="text-xs font-medium"></div>

                        <button type="submit" id="submitOrderBtn" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 active:scale-95 text-xs uppercase tracking-wider mt-2">
                            <span>SEND ORDER REQUEST</span>
                            <i class="fas fa-paper-plane text-[10px]"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ITEMS_PER_PAGE = 6;
        let currentPubPage = 1;
        let selectedPaymentMethod = '';

        function selectPayment(method) {
            selectedPaymentMethod = method;
            document.getElementById('paymentMethodInput').value = method;

            const card = document.getElementById('tabCard');
            const bank = document.getElementById('tabBank');
            const bankSection = document.getElementById('bankDetailsSection');

            card.classList.remove('bg-red-50', 'border-red-500', 'ring-2', 'ring-red-500/20');
            bank.classList.remove('bg-red-50', 'border-red-500', 'ring-2', 'ring-red-500/20');

            if(method === 'card') {
                card.classList.add('bg-red-50', 'border-red-500', 'ring-2', 'ring-red-500/20');
                bankSection.classList.add('hidden');
            } else {
                bank.classList.add('bg-red-50', 'border-red-500', 'ring-2', 'ring-red-500/20');
                bankSection.classList.remove('hidden');
            }
        }

        function openOrderModal(pub) {
            document.getElementById('pubId').value = pub.id;
            document.getElementById('modalPubTitle').textContent = pub.title;
            document.getElementById('modalPubPrice').textContent = 'Rs. ' + pub.price.toLocaleString();
            document.getElementById('orderModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Reset state
            selectedPaymentMethod = '';
            document.getElementById('paymentMethodInput').value = '';
            document.getElementById('tabCard').className = document.getElementById('tabCard').className.replace(/bg-red-50|border-red-500|ring-2|ring-red-500\/20/g, '');
            document.getElementById('tabBank').className = document.getElementById('tabBank').className.replace(/bg-red-50|border-red-500|ring-2|ring-red-500\/20/g, '');
            document.getElementById('bankDetailsSection').classList.add('hidden');
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            document.getElementById('orderForm').reset();
        }

        document.getElementById('orderForm').addEventListener('submit', function(e) {
            e.preventDefault();

            if (!selectedPaymentMethod) {
                document.getElementById('formFeedback').innerHTML = '<p class="text-red-500">Pick a payment method!</p>';
                return;
            }

            const btn = document.getElementById('submitOrderBtn');
            const feedback = document.getElementById('formFeedback');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';

            const formData = new FormData(this);

            fetch('submit_publication_order.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const toast = document.getElementById('toast');
                    toast.className = 'show';
                    setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 2000);
                    closeOrderModal();
                } else {
                    feedback.innerHTML = `<span class="text-red-500">${data.message}</span>`;
                    btn.disabled = false;
                    btn.innerHTML = '<span>SEND ORDER REQUEST</span><i class="fas fa-paper-plane text-xs"></i>';
                }
            })
            .catch(() => {
                feedback.innerHTML = '<span class="text-red-500">Network Error. Try again!</span>';
                btn.disabled = false;
                btn.innerHTML = '<span>SEND ORDER REQUEST</span><i class="fas fa-paper-plane text-xs"></i>';
            });
        });

        function returnOrder(orderId) {
            if (!confirm('Are you sure you want to request a return for this order?')) return;

            const formData = new FormData();
            formData.append('order_id', orderId);

            fetch('return_publication_order.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const toast = document.getElementById('toast');
                toast.textContent = data.message;
                toast.className = 'show';
                if (data.success) {
                    toast.style.backgroundColor = '#10b981';
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    toast.style.backgroundColor = '#ef4444';
                    setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 3000);
                }
            })
            .catch(() => {
                alert('An error occurred. Please try again.');
            });
        }

        function cancelOrder(orderId) {
            if (!confirm('Are you sure you want to cancel this order?')) return;

            const formData = new FormData();
            formData.append('order_id', orderId);

            fetch('cancel_publication_order.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const toast = document.getElementById('toast');
                toast.textContent = data.message;
                toast.className = 'show';
                if (data.success) {
                    toast.style.backgroundColor = '#10b981';
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    toast.style.backgroundColor = '#ef4444';
                    setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 3000);
                }
            })
            .catch(() => {
                alert('An error occurred. Please try again.');
            });
        }

        function confirmDelivery(orderId) {
            if (!confirm('Have you received this publication? This will mark the order as completed.')) return;

            const formData = new FormData();
            formData.append('order_id', orderId);

            fetch('confirm_publication_delivery.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const toast = document.getElementById('toast');
                toast.textContent = data.message;
                toast.className = 'show';
                if (data.success) {
                    toast.style.backgroundColor = '#10b981';
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    toast.style.backgroundColor = '#ef4444';
                    setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 3000);
                }
            })
            .catch(() => {
                alert('An error occurred. Please try again.');
            });
        }

        // Pagination renderer
        function renderPagination(containerId, totalPages, currentPage, onPageClickFnName) {
            const container = document.getElementById(containerId);
            if (!container) return;
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';
            // Prev button
            html += `<button onclick="${onPageClickFnName}(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} 
                             class="w-9 h-9 rounded-xl border border-slate-200 bg-white/90 backdrop-blur text-slate-600 hover:bg-red-50 hover:text-red-600 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center text-xs font-bold transition-all shadow-2xs">
                         <i class="fas fa-chevron-left"></i>
                     </button>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    html += `<button class="w-9 h-9 rounded-xl bg-red-600 text-white font-black text-xs shadow-md">${i}</button>`;
                } else {
                    html += `<button onclick="${onPageClickFnName}(${i})" 
                                     class="w-9 h-9 rounded-xl border border-slate-200 bg-white/90 backdrop-blur text-slate-700 hover:bg-slate-100 font-bold text-xs transition-all shadow-2xs">${i}</button>`;
                }
            }

            // Next button
            html += `<button onclick="${onPageClickFnName}(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} 
                             class="w-9 h-9 rounded-xl border border-slate-200 bg-white/90 backdrop-blur text-slate-600 hover:bg-red-50 hover:text-red-600 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center text-xs font-bold transition-all shadow-2xs">
                         <i class="fas fa-chevron-right"></i>
                     </button>`;

            container.innerHTML = html;
        }

        function goToPubPage(page) {
            currentPubPage = page;
            filterPublications(false);
            const el = document.getElementById('pubGrid');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function filterPublications(resetPage = true) {
            if (resetPage) currentPubPage = 1;
            const searchInput = document.getElementById('pubSearchInput');
            const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const catSelect = document.getElementById('categoryFilterSelect');
            const catVal = catSelect ? catSelect.value : 'all';
            const cards = Array.from(document.querySelectorAll('.pub-card'));

            const matchingCards = cards.filter(card => {
                const cardCat = card.getAttribute('data-category');
                const cardSearch = card.getAttribute('data-search');

                const matchCat = (catVal === 'all' || cardCat === catVal);
                const matchSearch = (!searchVal || (cardSearch && cardSearch.includes(searchVal)));

                return matchCat && matchSearch;
            });

            // Hide all
            cards.forEach(c => c.style.display = 'none');

            // Calculate pagination
            const totalMatching = matchingCards.length;
            const totalPages = Math.ceil(totalMatching / ITEMS_PER_PAGE) || 1;
            if (currentPubPage > totalPages) currentPubPage = totalPages;

            const start = (currentPubPage - 1) * ITEMS_PER_PAGE;
            const end = start + ITEMS_PER_PAGE;
            const pageItems = matchingCards.slice(start, end);

            pageItems.forEach(c => c.style.display = '');

            renderPagination('pubPagination', totalPages, currentPubPage, 'goToPubPage');
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            filterPublications(true);
        });
    </script>
</body>
</html>
