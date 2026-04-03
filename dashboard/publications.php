<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

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

// Handle filters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Fetch Categories
$categories = $conn->query("SELECT * FROM publication_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch Publications with Filtering
$pub_sql = "SELECT p.*, c.name as category_name FROM publications p LEFT JOIN publication_categories c ON p.category_id = c.id WHERE 1=1";
$params = [];
$types = '';

if (!empty($search_query)) {
    $pub_sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}
if ($category_filter > 0) {
    $pub_sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}
$pub_sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($pub_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$publications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publications - LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
        }

        .glass-nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .hero-gradient {
            background: radial-gradient(circle at top right, #fff1f2 0%, transparent 40%),
                        radial-gradient(circle at bottom left, #f0f9ff 0%, transparent 40%);
        }

        .pub-card {
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            isolation: isolate;
        }

        .pub-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.1);
            border-color: #fecaca;
        }

        .pub-cover {
            position: relative;
            aspect-ratio: 3/4;
            border-radius: 20px;
            margin: 12px;
            overflow: hidden;
            background: #f1f5f9;
        }

        .pub-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .pub-card:hover .pub-cover img {
            transform: scale(1.1);
        }

        .cat-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(4px);
            color: #ef4444;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 100px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .discount-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 100px;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }

        .search-container {
            background: white;
            border-radius: 20px;
            padding: 8px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }

        .filter-pill {
            padding: 10px 20px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            background: transparent;
        }

        .filter-pill:hover {
            background: #f1f5f9;
        }

        /* Tooltip Styles */
        [data-sinhala] {
            position: relative;
        }
        [data-sinhala]:hover::after {
            content: attr(data-sinhala);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
            z-index: 100;
            pointer-events: none;
            opacity: 0;
            animation: tooltipIn 0.3s forwards;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        @keyframes tooltipIn {
            to { opacity: 1; transform: translate(-50%, -10px); }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <main class="relative hero-gradient pt-16 pb-20">
        
        <!-- Abstract Decorations -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-red-100 rounded-full blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-100 rounded-full blur-3xl opacity-30 translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <!-- Header Section -->
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-10 mb-16">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-full shadow-sm mb-6 animate-float">
                        <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                        <span class="text-xs font-bold text-slate-600 uppercase tracking-wider" data-sinhala="අධ්‍යාපනික ද්‍රව්‍ය">Premium Learning Resources</span>
                    </div>
                    <h1 class="text-5xl lg:text-7xl font-black text-slate-900 tracking-tight leading-[1.1] mb-6">
                        Unlock Expert <br/> 
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-red-400" data-sinhala="ප්‍රකාශන">Publications</span>
                    </h1>
                    <p class="text-lg text-slate-500 font-medium leading-relaxed" data-sinhala="අපගේ ඉහළම උපදේශකයින් විසින් සකස් කරන ලද පෙළපොත්, මාර්ගෝපදේශ සහ අධ්‍යයන ද්‍රව්‍ය.">
                        Elevate your learning experience with textbooks, curated guides, and specialized study materials crafted by our elite instructors.
                    </p>
                </div>

                <!-- Search & Filter Controls -->
                <div class="w-full lg:max-w-md">
                    <form action="publications.php" method="GET" class="search-container flex flex-col sm:flex-row gap-2">
                        <div class="flex-1 relative">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                                   placeholder="Search resources..." data-sinhala="නිබන්ධන සොයන්න..."
                                   class="w-full pl-11 pr-4 py-3 bg-transparent outline-none text-slate-700 font-medium placeholder:text-slate-400">
                        </div>
                        <div class="flex gap-2">
                            <select name="category" onchange="this.form.submit()" class="bg-slate-50 px-4 py-3 rounded-xl text-sm font-bold text-slate-600 outline-none border-none cursor-pointer hover:bg-slate-100 transition-colors">
                                <option value="0" data-sinhala="සියලුම කාණ්ඩ">All Topics</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="bg-slate-900 text-white p-3.5 rounded-xl hover:bg-black transition-all">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                    <?php if (!empty($search_query) || $category_filter > 0): ?>
                        <div class="mt-4 flex justify-end">
                            <a href="publications.php" class="text-xs font-bold text-red-600 hover:text-red-700 underline underline-offset-4 flex items-center gap-1">
                                <i class="fas fa-times-circle"></i> Clear Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats & Breadcrumbs -->
            <div class="flex items-center gap-6 mb-10 overflow-x-auto pb-2 border-b border-slate-200">
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-slate-400 font-bold">FOUND</span>
                    <span class="bg-slate-900 text-white px-2.5 py-0.5 rounded-md text-xs font-black"><?php echo count($publications); ?></span>
                    <span class="text-slate-600 font-bold uppercase tracking-widest text-[10px]">RESOURCES</span>
                </div>
                <?php if($category_filter > 0): ?>
                    <div class="h-4 w-px bg-slate-300"></div>
                    <div class="flex items-center gap-2">
                        <i class="fas fa-tag text-red-500 text-xs"></i>
                        <span class="text-xs font-bold text-slate-500 uppercase"><?php 
                            foreach($categories as $c) if($c['id'] == $category_filter) echo htmlspecialchars($c['name']); 
                        ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Publications Grid -->
            <?php if(empty($publications)): ?>
                <div class="bg-white rounded-[40px] p-20 text-center border-2 border-dashed border-slate-200">
                    <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-8">
                        <i class="fas fa-search text-3xl text-slate-300"></i>
                    </div>
                    <h2 class="text-3xl font-black text-slate-800 mb-4">No Matches Found</h2>
                    <p class="text-slate-500 max-w-sm mx-auto font-medium mb-8">Try adjusting your filters or search keywords to find what you're looking for.</p>
                    <a href="publications.php" class="inline-flex items-center gap-3 bg-red-600 text-white px-8 py-4 rounded-2xl font-bold hover:bg-red-700 transition-all shadow-xl shadow-red-200">
                        Explore All Publications
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-8">
                    <?php foreach($publications as $pub):
                        $final_price = $pub['price'] - $pub['discount'];
                        $discount_pct = $pub['price'] > 0 ? round(($pub['discount'] / $pub['price']) * 100) : 0;
                    ?>
                        <div class="pub-card flex flex-col group">
                            <!-- Image Section -->
                            <div class="pub-cover group">
                                <?php if(!empty($pub['image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($pub['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($pub['title']); ?>">
                                <?php else: ?>
                                    <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-slate-50 to-slate-200">
                                        <i class="fas fa-book-open text-slate-300 text-6xl"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <span class="cat-badge" data-sinhala="කාණ්ඩය"><?php echo htmlspecialchars($pub['category_name']); ?></span>
                                
                                <?php if($pub['discount'] > 0): ?>
                                    <span class="discount-badge" data-sinhala="වට්ටම්">-<?php echo $discount_pct; ?>%</span>
                                <?php endif; ?>

                                <!-- Quick Overlay on Hover -->
                                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-sm flex items-center justify-center">
                                    <button onclick="openOrderModal(<?php echo htmlspecialchars(json_encode([
                                        'id'    => $pub['id'],
                                        'title' => $pub['title'],
                                        'price' => $final_price
                                    ])); ?>)" class="bg-white text-slate-900 w-14 h-14 rounded-full shadow-2xl flex items-center justify-center hover:scale-110 transition-transform">
                                        <i class="fas fa-shopping-basket text-xl"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Content Section -->
                            <div class="px-6 pb-6 pt-2 flex-1 flex flex-col">
                                <h3 class="text-lg font-black text-slate-800 line-clamp-2 leading-tight mb-3 min-h-[3rem]">
                                    <?php echo htmlspecialchars($pub['title']); ?>
                                </h3>
                                <p class="text-sm text-slate-500 font-medium line-clamp-2 mb-6 flex-1">
                                    <?php echo htmlspecialchars($pub['description']); ?>
                                </p>

                                <!-- Price & Action -->
                                <div class="flex items-end justify-between mt-auto pt-6 border-t border-slate-100">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">BEST PRICE</p>
                                        <div class="flex items-center gap-2">
                                            <span class="text-2xl font-black text-slate-900 tracking-tighter">Rs.<?php echo number_format($final_price, 0); ?></span>
                                            <?php if($pub['discount'] > 0): ?>
                                                <span class="text-sm text-slate-300 line-through font-bold">Rs.<?php echo number_format($pub['price'], 0); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button onclick="openOrderModal(<?php echo htmlspecialchars(json_encode([
                                        'id'    => $pub['id'],
                                        'title' => $pub['title'],
                                        'price' => $final_price
                                    ])); ?>)" data-sinhala="දැන් ලබාගන්න"
                                            class="bg-red-600 text-white w-12 h-12 rounded-2xl flex items-center justify-center hover:bg-slate-900 transition-all shadow-lg shadow-red-100 group-hover:-translate-y-1">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
            <div class="modal-inner relative bg-white w-full max-w-xl rounded-[40px] shadow-[0_40px_100px_-15px_rgba(0,0,0,0.3)] overflow-hidden border border-white/20">
                
                <!-- Modal Decoration -->
                <div class="absolute top-0 right-0 w-48 h-48 bg-red-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 -z-10"></div>

                <!-- Header -->
                <div class="px-10 pt-10 pb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-3xl font-black text-slate-900 tracking-tight" data-sinhala="ඇණවුම සම්පූර්ණ කරන්න">Checkout</h2>
                        <p class="text-slate-400 font-bold text-xs uppercase tracking-widest mt-1">Shipping & Payment Details</p>
                    </div>
                    <button onclick="closeOrderModal()" class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="px-10 pb-10">
                    <!-- Order Snapshot -->
                    <div class="bg-slate-900 rounded-3xl p-6 mb-8 flex flex-col sm:flex-row items-center justify-between gap-6 shadow-2xl">
                        <div class="flex items-center gap-4">
                           <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-white text-2xl">
                               <i class="fas fa-box-open"></i>
                           </div>
                           <div>
                               <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">SELECTED RESOURCE</p>
                               <h4 id="modalPubTitle" class="text-white font-bold leading-tight line-clamp-1"></h4>
                           </div>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">TOTAL COST</p>
                            <span id="modalPubPrice" class="text-2xl font-black text-white tracking-tighter"></span>
                        </div>
                    </div>

                    <!-- Form -->
                    <form id="orderForm" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" id="pubId" name="publication_id">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Full Name</label>
                                <input type="text" name="name" required value="<?php echo htmlspecialchars($user_name); ?>"
                                       class="w-full bg-slate-50 border border-slate-200 px-5 py-4 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1" data-sinhala="වොට්සැප් අංකය">WhatsApp Number</label>
                                <input type="text" name="contact_number" required value="<?php echo htmlspecialchars($user_contact); ?>"
                                       class="w-full bg-slate-50 border border-slate-200 px-5 py-4 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">District</label>
                                <input type="text" name="district" required value="<?php echo htmlspecialchars($user_district); ?>"
                                       class="w-full bg-slate-50 border border-slate-200 px-5 py-4 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Quantity</label>
                                <select name="quantity" class="w-full bg-slate-50 border border-slate-200 px-5 py-4 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                                    <?php for($i=1; $i<=10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Piece<?php echo $i>1?'s':'' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Delivery Address</label>
                            <textarea name="address" required rows="2" placeholder="Street, Building, City..."
                                      class="w-full bg-slate-50 border border-slate-200 px-5 py-4 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all resize-none"></textarea>
                        </div>

                        <!-- Payment Method -->
                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Payment Method</label>
                            <input type="hidden" name="payment_method" id="paymentMethodInput">
                            <div class="grid grid-cols-2 gap-4">
                                <div id="tabCard" onclick="selectPayment('card')" class="cursor-pointer border-2 border-slate-100 p-5 rounded-3xl flex flex-col items-center gap-2 hover:border-red-100 hover:bg-red-50/30 transition-all group">
                                    <i class="fas fa-credit-card text-2xl text-slate-300 group-hover:text-red-400"></i>
                                    <span class="text-xs font-black text-slate-400 group-hover:text-red-900 uppercase tracking-tighter">Online Payment</span>
                                </div>
                                <div id="tabBank" onclick="selectPayment('bank_transfer')" class="cursor-pointer border-2 border-slate-100 p-5 rounded-3xl flex flex-col items-center gap-2 hover:border-red-100 hover:bg-red-50/30 transition-all group">
                                    <i class="fas fa-university text-2xl text-slate-300 group-hover:text-red-400"></i>
                                    <span class="text-xs font-black text-slate-400 group-hover:text-red-900 uppercase tracking-tighter">Bank Transfer</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details (Hidden by default) -->
                        <div id="bankDetailsSection" class="hidden animate-fade-in bg-slate-50 rounded-[32px] p-8 border border-slate-200">
                             <div class="flex items-center gap-4 mb-6">
                                 <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-slate-900 shadow-sm border border-slate-100">
                                     <i class="fas fa-building-columns"></i>
                                 </div>
                                 <h5 class="font-black text-slate-900 tracking-tight">Institute Bank Details</h5>
                             </div>
                             <div class="grid gap-3 text-sm font-bold text-slate-600 mb-8">
                                 <div class="flex justify-between border-b border-slate-200 pb-2">
                                     <span class="text-slate-400">BANK</span>
                                     <span>COMMERCIAL BANK</span>
                                 </div>
                                 <div class="flex justify-between border-b border-slate-200 pb-2">
                                     <span class="text-slate-400">ACCOUNT</span>
                                     <span>LEARNERX HUB (PVT) LTD</span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-slate-400">NUMBER</span>
                                     <span class="font-mono text-red-600">80100 23456 7890</span>
                                 </div>
                             </div>
                             <div class="space-y-3">
                                 <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Upload Receipt</label>
                                 <input type="file" name="receipt" id="receiptFile" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-black file:bg-red-600 file:text-white hover:file:bg-slate-900 cursor-pointer">
                             </div>
                        </div>

                        <div id="formFeedback" class="text-xs font-bold"></div>

                        <button type="submit" id="submitOrderBtn" class="w-full bg-slate-900 text-white font-black py-5 rounded-3xl hover:bg-black transition-all shadow-2xl flex items-center justify-center gap-3 active:scale-[0.98]">
                            <span>SEND ORDER REQUEST</span>
                            <i class="fas fa-paper-plane text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SUCCESS NOTIFICATION -->
    <div id="successModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900 flex items-center justify-center">
            <div class="text-center">
                <div class="w-32 h-32 bg-red-600 rounded-[40px] flex items-center justify-center mx-auto mb-10 shadow-[0_30px_60px_-10px_rgba(239,68,68,0.5)] rotate-12">
                    <i class="fas fa-check text-5xl text-white"></i>
                </div>
                <h3 class="text-5xl font-black text-white tracking-tight mb-4">Request Sent!</h3>
                <p class="text-slate-400 text-lg max-w-sm mx-auto font-medium mb-12">We've received your order. One of our team members will contact you on WhatsApp shortly.</p>
                <button onclick="window.location.reload()" class="bg-white text-slate-900 px-10 py-5 rounded-full font-black hover:scale-110 transition-transform">
                    AWESOME! 🎊
                </button>
            </div>
        </div>
    </div>

    <script>
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
            document.getElementById('modalPubPrice').textContent = 'Rs.' + pub.price.toLocaleString();
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
                    document.getElementById('successModal').classList.remove('hidden');
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
    </script>
</body>
</html>
