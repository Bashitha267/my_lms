<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

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
    <title>Publications - Lernerr.LK</title>
    <meta name="description" content="Browse and order educational books, papers, and publications from Lernerr.LK. High-quality study materials designed for your academic success.">
    <meta name="keywords" content="Lernerr.LK publications, study materials Sri Lanka, educational books, exam papers A/L">
    <meta name="author" content="Lernerr.LK">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Publications - Lernerr.LK">
    <meta property="og:description" content="Browse and order educational books, papers, and publications from Lernerr.LK.">
    <meta property="og:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">
    <meta property="og:site_name" content="Lernerr.LK">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="Publications - Lernerr.LK">
    <meta property="twitter:description" content="Browse and order educational books, papers, and publications from Lernerr.LK.">
    <meta property="twitter:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            transform: translateY(-8px);
            border-color: rgba(220, 38, 38, 0.3);
            box-shadow: 0 20px 40px -15px rgba(220, 38, 38, 0.1);
        }
        .gradient-text {
            background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #ef4444;
            border-radius: 10px;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen custom-scrollbar">
    <!-- Include Navbar -->
    <?php include '../dashboard/navbar.php'; ?>

    <!-- Hero Header -->
    <section class="relative pt-16 pb-20 overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full -z-10">
            <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] bg-red-50 rounded-full blur-[120px] opacity-60"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-slate-100 rounded-full blur-[100px] opacity-60"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-12">
                <div>
                    <span class="inline-block px-4 py-1.5 mb-4 text-sm font-bold tracking-widest text-red-600 uppercase bg-red-50 rounded-full">Educational Materials</span>
                    <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-4 tracking-tight">
                        Premium <span class="gradient-text">Publications</span>
                    </h1>
                    <p class="text-slate-600 max-w-xl text-lg">
                        Unlock your potential with our curated collection of textbooks, guides, and study materials designed by top educators.
                    </p>
                </div>

                <!-- Filters -->
                <div class="w-full md:w-auto">
                    <form action="publications.php" method="GET" class="flex flex-wrap items-center gap-3">
                        <div class="relative flex-1 md:flex-none min-w-[160px]">
                            <select name="category" onchange="this.form.submit()" 
                                    class="w-full appearance-none bg-white border border-slate-200 px-4 py-3 pr-10 rounded-2xl text-sm font-medium focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all outline-none">
                                <option value="0">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                <i class="fas fa-chevron-down text-xs"></i>
                            </div>
                        </div>

                        <div class="relative flex-1 md:w-64">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                                   placeholder="Search publications..."
                                   class="w-full bg-white border border-slate-200 pl-11 pr-4 py-3 rounded-2xl text-sm font-medium focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all outline-none">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <i class="fas fa-search text-sm"></i>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Publications Grid -->
            <?php if(empty($publications)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-book-open text-slate-300 text-4xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-2">No publications found</h3>
                    <p class="text-slate-500 max-w-xs">We couldn't find any materials matching your search criteria. Try a different category or search term.</p>
                    <a href="publications.php" class="mt-6 text-red-600 font-bold hover:underline">Clear all filters</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <?php foreach($publications as $pub): 
                        $final_price = $pub['price'] - $pub['discount'];
                    ?>
                        <div class="glass-card flex flex-col h-full rounded-3xl overflow-hidden">
                            <!-- Image Container -->
                            <div class="relative aspect-[4/5] overflow-hidden group">
                                <?php if(!empty($pub['image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($pub['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($pub['title']); ?>"
                                         class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                <?php else: ?>
                                    <div class="w-full h-full bg-slate-100 flex items-center justify-center">
                                        <i class="fas fa-book text-slate-300 text-6xl"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="absolute top-4 left-4">
                                    <span class="px-3 py-1 bg-white/90 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-widest text-red-600 shadow-sm border border-red-50/50">
                                        <?php echo htmlspecialchars($pub['category_name']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-6 flex flex-col flex-grow">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 line-clamp-2 min-h-[3.5rem] leading-snug">
                                    <?php echo htmlspecialchars($pub['title']); ?>
                                </h3>
                                <p class="text-sm text-slate-500 mb-6 line-clamp-3 leading-relaxed">
                                    <?php echo htmlspecialchars($pub['description']); ?>
                                </p>

                                <div class="mt-auto">
                                    <div class="flex items-center gap-3 mb-6">
                                        <div class="text-2xl font-black text-slate-900">
                                            Rs. <?php echo number_format($final_price, 0); ?>
                                        </div>
                                        <?php if($pub['discount'] > 0): ?>
                                            <div class="text-sm text-slate-400 line-through">
                                                Rs. <?php echo number_format($pub['price'], 0); ?>
                                            </div>
                                            <div class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                                SAVE Rs. <?php echo number_format($pub['discount'], 0); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <button onclick="openOrderModal(<?php echo htmlspecialchars(json_encode([
                                        'id' => $pub['id'],
                                        'title' => $pub['title'],
                                        'price' => $final_price
                                    ])); ?>)" 
                                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-red-200 flex items-center justify-center gap-2 group transform active:scale-95">
                                        <span>Buy Now</span>
                                        <i class="fas fa-shopping-cart text-sm transition-transform group-hover:translate-x-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Order Drawer/Modal (Styled Premium) -->
    <div id="orderModal" class="fixed inset-0 z-[100] hidden overflow-hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closeOrderModal()"></div>

            <div class="inline-block align-bottom bg-white rounded-t-[2.5rem] sm:rounded-[2.5rem] text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-slate-100">
                <div class="bg-white px-8 pt-8 pb-8">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl font-black text-slate-900" id="modal-title">Complete Your Order</h3>
                        </div>
                        <button onclick="closeOrderModal()" class="text-slate-400 hover:text-slate-600 p-2">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <div class="bg-red-50 rounded-2xl p-5 mb-8 flex items-center justify-between border border-red-100">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-red-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-red-200">
                                <i class="fas fa-book"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-red-600 uppercase tracking-widest mb-0.5">Selected Item</p>
                                <p class="font-bold text-slate-900 truncate max-w-[200px]" id="modalPubTitle"></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total Price</p>
                            <p class="text-xl font-black text-slate-900" id="modalPubPrice"></p>
                        </div>
                    </div>

                    <form id="orderForm" enctype="multipart/form-data" class="space-y-5">
                        <input type="hidden" id="pubId" name="publication_id">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-widest mb-2">Your Name</label>
                                <input type="text" name="name" required value="<?php echo htmlspecialchars($user_name); ?>"
                                       class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all placeholder-slate-400 text-sm font-medium">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-widest mb-2">WhatsApp Number</label>
                                <input type="text" name="contact_number" required value="<?php echo htmlspecialchars($user_contact); ?>"
                                       class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all placeholder-slate-400 text-sm font-medium">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-widest mb-2">District</label>
                            <input type="text" name="district" required value="<?php echo htmlspecialchars($user_district); ?>"
                                   class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all placeholder-slate-400 text-sm font-medium">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-widest mb-2">Delivery Address</label>
                            <textarea name="address" rows="2" required placeholder="Full address for courier delivery..."
                                      class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all placeholder-slate-400 text-sm font-medium"></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-widest mb-2">Payment Method</label>
                            <select name="payment_method" id="paymentMethod" required onchange="togglePaymentDetails()"
                                    class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all text-sm font-medium">
                                <option value="">Choose payment option...</option>
                                <option value="card">Online Payment (Card/Wallet)</option>
                                <option value="bank_transfer">Manual Bank Transfer</option>
                            </select>
                        </div>

                        <!-- Bank Details (Conditional) -->
                        <div id="bankDetailsSection" class="hidden bg-slate-50 p-6 rounded-2xl border border-dashed border-slate-300">
                            <div class="flex items-center gap-2 mb-4 text-slate-900 font-bold">
                                <i class="fas fa-university"></i>
                                <span>Bank Account Info</span>
                            </div>
                            <div class="space-y-1 mb-4 text-sm font-medium text-slate-600">
                                <p>Bank: <span class="text-slate-900">Commercial Bank</span></p>
                                <p>Account Name: <span class="text-slate-900">Lernerr.LK Institute</span></p>
                                <p>Account No: <span class="text-slate-900 font-bold">1234 5678 9012</span></p>
                            </div>
                            
                            <label class="block text-[10px] font-black text-red-600 uppercase tracking-widest mb-2">Upload Transfer Receipt</label>
                            <div class="relative">
                                <input type="file" name="receipt" id="receiptFile" accept="image/*,.pdf"
                                       class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-red-50 file:text-red-700 hover:file:bg-red-100 cursor-pointer">
                            </div>
                            <p id="receiptError" class="hidden text-[10px] text-red-500 font-bold mt-2 italic">Receipt is mandatory for bank transfers.</p>
                        </div>

                        <div id="formFeedback"></div>

                        <button type="submit" id="submitOrderBtn"
                                class="w-full bg-slate-900 hover:bg-black text-white font-bold py-4 rounded-2xl transition-all shadow-xl shadow-slate-200 flex items-center justify-center gap-2 mt-4 transform active:scale-95">
                            <span>Place Order Now</span>
                            <i class="fas fa-arrow-right text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md"></div>
        <div class="relative bg-white rounded-[2.5rem] p-10 max-w-sm w-full text-center shadow-2xl border border-slate-100">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-green-600 text-3xl"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-900 mb-2">Order Success!</h3>
            <p class="text-slate-500 mb-8">Your order has been placed. We'll contact you shortly via WhatsApp.</p>
            <button onclick="window.location.reload()" class="w-full bg-slate-900 text-white font-bold py-4 rounded-2xl">
                Awesome!
            </button>
        </div>
    </div>

    <script>
        function openOrderModal(pub) {
            document.getElementById('pubId').value = pub.id;
            document.getElementById('modalPubTitle').textContent = pub.title;
            document.getElementById('modalPubPrice').textContent = 'Rs. ' + pub.price;
            document.getElementById('orderModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Animation for the modal content
            const modalContent = document.querySelector('#orderModal > div > div:nth-child(2)');
            modalContent.classList.add('translate-y-0');
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            document.getElementById('orderForm').reset();
            togglePaymentDetails();
        }

        function togglePaymentDetails() {
            const method = document.getElementById('paymentMethod').value;
            const bankSection = document.getElementById('bankDetailsSection');
            if (method === 'bank_transfer') {
                bankSection.classList.remove('hidden');
            } else {
                bankSection.classList.add('hidden');
            }
        }

        document.getElementById('orderForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const method = document.getElementById('paymentMethod').value;
            const receipt = document.getElementById('receiptFile').files[0];
            const receiptError = document.getElementById('receiptError');
            
            if (method === 'bank_transfer' && !receipt) {
                receiptError.classList.remove('hidden');
                return;
            } else {
                receiptError.classList.add('hidden');
            }

            const btn = document.getElementById('submitOrderBtn');
            const feedback = document.getElementById('formFeedback');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            feedback.innerHTML = '';

            const formData = new FormData(this);
            
            fetch('submit_publication_order.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('successModal').classList.remove('hidden');
                    document.getElementById('orderModal').classList.add('hidden');
                } else {
                    feedback.innerHTML = `<div class="p-4 bg-red-50 text-red-600 rounded-xl text-xs font-bold mb-4 border border-red-100 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>${data.message}</span>
                    </div>`;
                    btn.disabled = false;
                    btn.innerHTML = 'Place Order Now <i class="fas fa-arrow-right text-xs"></i>';
                }
            })
            .catch(err => {
                feedback.innerHTML = `<div class="p-4 bg-red-50 text-red-600 rounded-xl text-xs font-bold mb-4 border border-red-100">Connection error. Please try again.</div>`;
                btn.disabled = false;
                btn.innerHTML = 'Place Order Now <i class="fas fa-arrow-right text-xs"></i>';
            });
        });
    </script>
</body>
</html>
