<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

// Verify user is admin
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: " . BASE_PATH . "login.php");
    exit();
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

$active_tab = $_GET['tab'] ?? 'orders';
$success_message = '';
$error_message = '';

// --- TAB 1: ORDER MANAGEMENT LOGIC ---
if ($active_tab === 'orders' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    $valid_statuses = ['pending', 'preparing', 'hand_order_to_delivery', 'completed', 'canceled', 'return_requested'];
    
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("SELECT o.*, u.whatsapp_number as user_wa FROM publication_orders o LEFT JOIN users u ON o.user_id = u.user_id WHERE o.id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($order = $res->fetch_assoc()) {
            if ($order['status'] !== $new_status) {
                $update_stmt = $conn->prepare("UPDATE publication_orders SET status = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_status, $order_id);
                if ($update_stmt->execute()) {
                    // Fetch items for message
                    $items_summary = 'Publication Item(s)';
                    $items_q = $conn->query("SELECT p.title, poi.quantity FROM publication_order_items poi JOIN publications p ON poi.publication_id = p.id WHERE poi.order_id = $order_id");
                    if ($items_q && $items_q->num_rows > 0) {
                        $items_list = [];
                        while ($it = $items_q->fetch_assoc()) {
                            $items_list[] = $it['title'] . ($it['quantity'] > 1 ? " (x{$it['quantity']})" : "");
                        }
                        $items_summary = implode(', ', $items_list);
                    }

                    $status_titles = [
                        'pending'                => "⏳ Order Pending / ඇණවුම පරීක්ෂා කරමින් පවතී",
                        'preparing'              => "📦 Preparing Order / ඇසුරුම් කරමින් පවතී",
                        'hand_order_to_delivery' => "🚚 Handed to Delivery / බෙදාහැරීමට භාර දී ඇත",
                        'completed'              => "✅ Order Delivered / ඇණවුම සාර්ථකව භාර දී ඇත",
                        'canceled'               => "❌ Order Canceled / ඇණවුම අවලංගු කරන ලදී",
                        'return_requested'       => "🔄 Return Requested / ආපසු භාරදීම සටහන් විය"
                    ];

                    $status_notes = [
                        'pending'                => "We have received your order and are currently verifying payment details.",
                        'preparing'              => "Your publication parcel is being packaged and prepared for shipping.",
                        'hand_order_to_delivery' => "Your package has been handed to the courier and is on its way to your address!",
                        'completed'              => "Your parcel has been delivered. Thank you for learning with Lernerr.LK!",
                        'canceled'               => "Your publication order has been canceled. Please contact support if you need assistance.",
                        'return_requested'       => "Your return request has been received. Our team will contact you shortly."
                    ];

                    $status_title = $status_titles[$new_status] ?? ucwords(str_replace('_', ' ', $new_status));
                    $status_note = $status_notes[$new_status] ?? "";

                    $msg = "🛒 *Order Status Update*\n\n" .
                           "Hello *{$order['name']}*,\n\n" .
                           "*Status:* {$status_title}\n\n" .
                           "📄 *Order #:* {$order_id}\n" .
                           "📚 *Items:* {$items_summary}\n" .
                           (!empty($order['district']) ? "📍 *Delivery:* {$order['district']} - {$order['address']}\n\n" : "\n") .
                           "ℹ️ {$status_note}\n\n" .
                           "Thank you for choosing Lernerr.LK!";

                    $target_phone = !empty($order['contact_number']) ? $order['contact_number'] : ($order['user_wa'] ?? '');
                    if (!empty($target_phone)) {
                        $clean_phone = preg_replace('/\D/', '', $target_phone);
                        if (!empty($clean_phone)) {
                            sendWhatsAppMessage($clean_phone, $msg);
                        }
                    }

                    $success_message = "Order #{$order_id} status updated to " . ucwords(str_replace('_', ' ', $new_status)) . ". WhatsApp message sent.";
                } else {
                    $error_message = "Failed to update order status.";
                }
            } else {
                $error_message = "Already in this status.";
            }
        }
    }
}

// --- TAB 2: PUBLICATION MANAGEMENT LOGIC ---
if ($active_tab === 'publications' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
            $stmt = $conn->prepare("INSERT INTO publication_categories (name) VALUES (?)");
            $stmt->bind_param("s", $category_name);
            if ($stmt->execute()) $success_message = "Category added.";
            else $error_message = "Error: " . $conn->error;
            $stmt->close();
        }
    }
    // Delete Category
    elseif (isset($_POST['delete_category'])) {
        $category_id = intval($_POST['category_id']);
        $check = $conn->query("SELECT COUNT(*) FROM publications WHERE category_id = $category_id")->fetch_row()[0];
        if ($check > 0) $error_message = "Category not empty.";
        else {
            $conn->query("DELETE FROM publication_categories WHERE id = $category_id");
            $success_message = "Category deleted.";
        }
    }
    // Add Publication
    elseif (isset($_POST['add_publication'])) {
        $category_id = intval($_POST['category_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        $price = $is_free ? 0.00 : floatval($_POST['price']);
        $discount = $is_free ? 0.00 : floatval($_POST['discount']);
        
        $image_path = '';
        if (isset($_FILES['publication_image']) && $_FILES['publication_image']['error'] == 0) {
            $upload_dir = '../uploads/publications/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['publication_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = uniqid('pub_') . '.' . $ext;
                if (move_uploaded_file($_FILES['publication_image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = 'uploads/publications/' . $filename;
                }
            }
        }

        $pdf_path = '';
        if (isset($_FILES['publication_pdf']) && $_FILES['publication_pdf']['error'] == 0) {
            $pdf_upload_dir = '../uploads/publications/pdfs/';
            if (!is_dir($pdf_upload_dir)) mkdir($pdf_upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['publication_pdf']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $pdf_filename = uniqid('pub_pdf_') . '.pdf';
                if (move_uploaded_file($_FILES['publication_pdf']['tmp_name'], $pdf_upload_dir . $pdf_filename)) {
                    $pdf_path = 'uploads/publications/pdfs/' . $pdf_filename;
                }
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO publications (category_id, title, description, price, discount, is_free, image_path, pdf_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddiss", $category_id, $title, $description, $price, $discount, $is_free, $image_path, $pdf_path);
        if ($stmt->execute()) $success_message = "Publication added successfully.";
        else $error_message = "Error: " . $conn->error;
        $stmt->close();
    }
    // Edit Publication
    elseif (isset($_POST['edit_publication'])) {
        $pid = intval($_POST['publication_id']);
        $category_id = intval($_POST['category_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        $price = $is_free ? 0.00 : floatval($_POST['price']);
        $discount = $is_free ? 0.00 : floatval($_POST['discount']);

        $res = $conn->query("SELECT image_path, pdf_path FROM publications WHERE id = $pid")->fetch_assoc();
        $image_path = $res['image_path'] ?? '';
        $pdf_path = $res['pdf_path'] ?? '';

        // Handle Image Replacement
        if (isset($_FILES['publication_image']) && $_FILES['publication_image']['error'] == 0) {
            $upload_dir = '../uploads/publications/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['publication_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = uniqid('pub_') . '.' . $ext;
                if (move_uploaded_file($_FILES['publication_image']['tmp_name'], $upload_dir . $filename)) {
                    if (!empty($image_path) && file_exists('../' . $image_path)) unlink('../' . $image_path);
                    $image_path = 'uploads/publications/' . $filename;
                }
            }
        }

        // Handle PDF Replacement
        if (isset($_FILES['publication_pdf']) && $_FILES['publication_pdf']['error'] == 0) {
            $pdf_upload_dir = '../uploads/publications/pdfs/';
            if (!is_dir($pdf_upload_dir)) mkdir($pdf_upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['publication_pdf']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $pdf_filename = uniqid('pub_pdf_') . '.pdf';
                if (move_uploaded_file($_FILES['publication_pdf']['tmp_name'], $pdf_upload_dir . $pdf_filename)) {
                    if (!empty($pdf_path) && file_exists('../' . $pdf_path)) unlink('../' . $pdf_path);
                    $pdf_path = 'uploads/publications/pdfs/' . $pdf_filename;
                }
            }
        }

        // Handle PDF Removal if requested
        if (isset($_POST['remove_pdf']) && $_POST['remove_pdf'] == '1') {
            if (!empty($pdf_path) && file_exists('../' . $pdf_path)) unlink('../' . $pdf_path);
            $pdf_path = null;
        }

        $stmt = $conn->prepare("UPDATE publications SET category_id = ?, title = ?, description = ?, price = ?, discount = ?, is_free = ?, image_path = ?, pdf_path = ? WHERE id = ?");
        $stmt->bind_param("issddissi", $category_id, $title, $description, $price, $discount, $is_free, $image_path, $pdf_path, $pid);
        if ($stmt->execute()) $success_message = "Publication updated successfully.";
        else $error_message = "Error: " . $conn->error;
        $stmt->close();
    }
    // Delete Publication
    elseif (isset($_POST['delete_publication'])) {
        $pid = intval($_POST['publication_id']);
        $res = $conn->query("SELECT image_path, pdf_path FROM publications WHERE id = $pid")->fetch_assoc();
        if ($res) {
            if (!empty($res['image_path']) && file_exists('../' . $res['image_path'])) unlink('../' . $res['image_path']);
            if (!empty($res['pdf_path']) && file_exists('../' . $res['pdf_path'])) unlink('../' . $res['pdf_path']);
        }
        $conn->query("DELETE FROM publications WHERE id = $pid");
        $success_message = "Publication deleted.";
    }
}

// Fetch Summary Stats
$total_pubs_count = $conn->query("SELECT COUNT(*) FROM publications")->fetch_row()[0] ?? 0;
$free_pubs_count = $conn->query("SELECT COUNT(*) FROM publications WHERE is_free = 1 OR (price <= 0 AND pdf_path IS NOT NULL AND pdf_path != '')")->fetch_row()[0] ?? 0;
$paid_pubs_count = max(0, $total_pubs_count - $free_pubs_count);
$total_orders_count = $conn->query("SELECT COUNT(*) FROM publication_orders")->fetch_row()[0] ?? 0;
$pending_orders_count = $conn->query("SELECT COUNT(*) FROM publication_orders WHERE status = 'pending'")->fetch_row()[0] ?? 0;

// Fetch Orders (Tab 1)
$orders = [];
if ($active_tab === 'orders') {
    $q = "SELECT o.*, 
          (SELECT SUM(price_at_order * quantity) FROM publication_order_items WHERE order_id = o.id) as total_amount,
          (SELECT GROUP_CONCAT(CONCAT(p.title, ' (x', poi.quantity, ')') SEPARATOR ', ') 
           FROM publication_order_items poi 
           JOIN publications p ON poi.publication_id = p.id 
           WHERE poi.order_id = o.id) as items_summary
          FROM publication_orders o ORDER BY o.created_at DESC";
    $orders = $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

// Fetch Categories & Publications (Tab 2)
$categories = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM publications WHERE category_id = c.id) as pub_count FROM publication_categories c ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);
$publications = [];
if ($active_tab === 'publications') {
    $q = "SELECT p.*, c.name as category_name FROM publications p LEFT JOIN publication_categories c ON p.category_id = c.id ORDER BY p.created_at DESC";
    $publications = $conn->query($q)->fetch_all(MYSQLI_ASSOC);
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
    <title>Publications & Orders | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Header & Stats Summary -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <i class="fas fa-book-open text-blue-600 text-lg"></i> Publications & Orders
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">Manage free PDF downloads, bookstore inventory catalog, and order fulfillments.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="../client/publications.php" target="_blank" class="px-3 py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg shadow-xs flex items-center gap-1.5 transition-colors">
                    <i class="fas fa-external-link-alt text-[10px] text-gray-400"></i> View Storefront
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2.5 rounded-lg flex items-center justify-between text-xs font-medium animate-fade">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 p-1"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-5 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-2.5 rounded-lg flex items-center justify-between text-xs font-medium animate-fade">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-rose-500 text-sm"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 p-1"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- 4-Card Compact Metric Stats Bar -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-6">
            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-book text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-gray-900 leading-tight"><?php echo number_format($total_pubs_count); ?></div>
                    <div class="text-[11px] text-gray-500 font-medium">Total Publications</div>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-file-pdf text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-emerald-600 leading-tight"><?php echo number_format($free_pubs_count); ?></div>
                    <div class="text-[11px] text-gray-500 font-medium">Free PDF Downloads</div>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-tag text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-indigo-600 leading-tight"><?php echo number_format($paid_pubs_count); ?></div>
                    <div class="text-[11px] text-gray-500 font-medium">Paid Books</div>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-truck text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-gray-900 leading-tight">
                        <span class="<?php echo $pending_orders_count > 0 ? 'text-amber-600' : 'text-gray-900'; ?>"><?php echo number_format($pending_orders_count); ?></span>
                        <span class="text-xs font-normal text-gray-400">/ <?php echo number_format($total_orders_count); ?></span>
                    </div>
                    <div class="text-[11px] text-gray-500 font-medium">Pending Orders</div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 mb-6 border-b border-gray-200 pb-3">
            <a href="?tab=orders" class="px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all <?php echo $active_tab === 'orders' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100/70 border border-gray-200'; ?>">
                <i class="fas fa-shipping-fast text-xs"></i> Orders Fulfillment
                <?php if ($pending_orders_count > 0): ?>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold <?php echo $active_tab === 'orders' ? 'bg-white text-blue-700' : 'bg-amber-100 text-amber-800'; ?>">
                        <?php echo $pending_orders_count; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="?tab=publications" class="px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all <?php echo $active_tab === 'publications' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100/70 border border-gray-200'; ?>">
                <i class="fas fa-layer-group text-xs"></i> Publications & Inventory
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold <?php echo $active_tab === 'publications' ? 'bg-white text-blue-700' : 'bg-gray-100 text-gray-700'; ?>">
                    <?php echo $total_pubs_count; ?>
                </span>
            </a>
        </div>

        <?php if ($active_tab === 'orders'): ?>
            <!-- ===================== TAB 1: ORDERS FULFILLMENT ===================== -->
            <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden">
                <!-- Orders Table Controls -->
                <div class="p-3.5 border-b border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gray-50/50">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div class="relative flex-1 sm:w-64">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input type="text" id="orderSearchInput" onkeyup="filterOrdersTable()" placeholder="Search order ID, student, phone..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <select id="orderStatusFilter" onchange="filterOrdersTable()" class="px-2.5 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-600">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="preparing">Preparing</option>
                            <option value="hand_order_to_delivery">On Delivery</option>
                            <option value="completed">Completed</option>
                            <option value="canceled">Canceled</option>
                            <option value="return_requested">Return Requested</option>
                        </select>
                    </div>
                    <div class="text-[11px] text-gray-500 font-medium">
                        Showing <span id="ordersVisibleCount" class="font-bold text-gray-800"><?php echo count($orders); ?></span> orders
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left" id="ordersTable">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="px-4 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Order ID & Date</th>
                                <th class="px-4 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Customer & Delivery</th>
                                <th class="px-4 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Ordered Items & Total</th>
                                <th class="px-4 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Payment Info</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                                    <i class="fas fa-inbox text-2xl text-gray-300 mb-2 block"></i>
                                    <span class="font-medium">No publication orders received yet.</span>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-slate-50/75 transition-colors order-row" data-status="<?php echo $order['status']; ?>">
                                <td class="px-4 py-3 align-top whitespace-nowrap">
                                    <div class="font-bold text-gray-900">#<?php echo $order['id']; ?></div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($order['name']); ?></div>
                                    <div class="text-[11px] text-gray-500 flex items-center gap-1.5 mt-0.5">
                                        <i class="fas fa-phone text-[10px] text-gray-400"></i>
                                        <a href="tel:<?php echo htmlspecialchars($order['contact_number']); ?>" class="hover:text-blue-600 font-medium">
                                            <?php echo htmlspecialchars($order['contact_number']); ?>
                                        </a>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $order['contact_number']); ?>" target="_blank" class="text-emerald-500 hover:text-emerald-600 ml-1" title="Message on WhatsApp">
                                            <i class="fab fa-whatsapp text-xs"></i>
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-gray-500 mt-1 flex items-start gap-1">
                                        <i class="fas fa-map-marker-alt text-[10px] text-gray-400 mt-0.5 shrink-0"></i>
                                        <span class="truncate max-w-xs" title="<?php echo htmlspecialchars($order['address'] . ', ' . $order['district']); ?>">
                                            <?php echo htmlspecialchars($order['district']); ?> &bull; <?php echo htmlspecialchars($order['address']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="text-gray-700 font-medium max-w-xs truncate mb-1" title="<?php echo htmlspecialchars($order['items_summary']); ?>">
                                        <?php echo htmlspecialchars($order['items_summary']); ?>
                                    </div>
                                    <div class="text-xs font-bold text-blue-600">
                                        LKR <?php echo number_format($order['total_amount'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium <?php echo $order['payment_method'] === 'bank_transfer' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-slate-100 text-slate-700'; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?>
                                    </span>
                                    <?php if (!empty($order['bank_receipt_path'])): ?>
                                        <div class="mt-1.5">
                                            <a href="javascript:void(0)" onclick="viewReceiptModal('../<?php echo htmlspecialchars($order['bank_receipt_path']); ?>')" class="inline-flex items-center gap-1 text-[11px] text-blue-600 hover:text-blue-800 font-medium">
                                                <i class="fas fa-receipt text-xs"></i> View Slip
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 align-top text-center whitespace-nowrap">
                                    <?php 
                                        $s = $order['status'];
                                        $clr = 'bg-slate-50 text-slate-700 border-slate-200';
                                        if ($s == 'pending') $clr = 'bg-amber-50 text-amber-700 border-amber-200';
                                        if ($s == 'preparing') $clr = 'bg-blue-50 text-blue-700 border-blue-200';
                                        if ($s == 'hand_order_to_delivery') $clr = 'bg-indigo-50 text-indigo-700 border-indigo-200';
                                        if ($s == 'canceled') $clr = 'bg-rose-50 text-rose-700 border-rose-200';
                                        if ($s == 'completed') $clr = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                        if ($s == 'return_requested') $clr = 'bg-purple-50 text-purple-700 border-purple-200';
                                    ?>
                                    <select onchange="updateOrderStatus(this, <?php echo $order['id']; ?>)" class="status-select text-xs font-semibold rounded-md border px-2 py-1 <?php echo $clr; ?> focus:ring-1 focus:ring-blue-500 focus:outline-none transition-colors cursor-pointer">
                                        <option value="pending" <?php echo $s == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="preparing" <?php echo $s == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                        <option value="hand_order_to_delivery" <?php echo $s == 'hand_order_to_delivery' ? 'selected' : ''; ?>>On Delivery</option>
                                        <option value="completed" <?php echo $s == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="canceled" <?php echo $s == 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                                        <option value="return_requested" <?php echo $s == 'return_requested' ? 'selected' : ''; ?>>Return Requested</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- ===================== TAB 2: PUBLICATIONS & CATALOG ===================== -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- Left Column: Publications Catalog Table (8 cols) -->
                <div class="lg:col-span-8 space-y-4">
                    <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden">
                        
                        <!-- Header & Live Search Bar -->
                        <div class="p-3.5 border-b border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gray-50/50">
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <div class="relative flex-1 sm:w-56">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                    <input type="text" id="pubSearchInput" onkeyup="filterPubsTable()" placeholder="Search title, category..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>
                                <select id="pubTypeFilter" onchange="filterPubsTable()" class="px-2.5 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-600">
                                    <option value="">All Formats</option>
                                    <option value="free">Free PDF Only</option>
                                    <option value="paid">Paid Books Only</option>
                                </select>
                            </div>
                            <div class="text-[11px] text-gray-500 font-medium">
                                Total: <span id="pubVisibleCount" class="font-bold text-gray-800"><?php echo count($publications); ?></span> items
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-left" id="publicationsTable">
                                <thead class="bg-gray-50/80">
                                    <tr>
                                        <th class="px-4 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Item & Attached PDF</th>
                                        <th class="px-4 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-4 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Pricing / Type</th>
                                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-xs">
                                    <?php if (empty($publications)): ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">
                                            <i class="fas fa-book-open text-2xl text-gray-300 mb-2 block"></i>
                                            <span class="font-medium">No publications added yet. Use the form on the right to upload your first free PDF or paid publication.</span>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php foreach ($publications as $pub): ?>
                                    <?php 
                                        $isFree = (!empty($pub['is_free']) || ($pub['price'] <= 0 && !empty($pub['pdf_path'])));
                                    ?>
                                    <tr class="hover:bg-slate-50/75 transition-colors pub-row" data-type="<?php echo $isFree ? 'free' : 'paid'; ?>">
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex items-start gap-3">
                                                <img src="../<?php echo !empty($pub['image_path']) ? htmlspecialchars($pub['image_path']) : 'assests/logo.jpeg'; ?>" 
                                                     alt="Cover" 
                                                     class="w-10 h-14 object-cover rounded-md border border-gray-200 shrink-0 bg-slate-100 shadow-2xs">
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-gray-900 text-xs leading-tight flex items-center gap-1.5 flex-wrap">
                                                        <span><?php echo htmlspecialchars($pub['title']); ?></span>
                                                        <?php if ($isFree): ?>
                                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">FREE PDF</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- PDF Status / Download preview link -->
                                                    <div class="mt-1.5 flex items-center gap-2">
                                                        <?php if (!empty($pub['pdf_path'])): ?>
                                                            <a href="../download_publication.php?id=<?php echo $pub['id']; ?>" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-medium text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 rounded border border-rose-200/60 transition-colors" title="Download attached PDF">
                                                                <i class="fas fa-file-pdf text-[11px]"></i> Download PDF
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center gap-1 text-[11px] text-gray-400 italic">
                                                                <i class="fas fa-minus-circle text-[10px]"></i> No PDF file
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 align-top whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                <?php echo htmlspecialchars($pub['category_name'] ?? 'Uncategorized'); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top whitespace-nowrap">
                                            <?php if ($isFree): ?>
                                                <div class="font-bold text-emerald-600 text-xs flex items-center gap-1">
                                                    <i class="fas fa-gift text-[10px]"></i> Free Download
                                                </div>
                                                <div class="text-[10px] text-gray-400">Digital PDF Item</div>
                                            <?php else: ?>
                                                <div class="font-bold text-gray-900 text-xs">
                                                    LKR <?php echo number_format($pub['price'] - $pub['discount'], 2); ?>
                                                </div>
                                                <?php if ($pub['discount'] > 0): ?>
                                                    <div class="text-[10px] text-gray-400 line-through">
                                                        LKR <?php echo number_format($pub['price'], 2); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="text-[10px] text-gray-400">Physical / Store</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5">
                                                <button type="button" onclick="openEditPubModal(<?php echo htmlspecialchars(json_encode($pub)); ?>)" class="w-7 h-7 rounded-md bg-gray-100 text-gray-600 hover:bg-blue-50 hover:text-blue-600 flex items-center justify-center transition-colors" title="Edit Publication">
                                                    <i class="fas fa-pen text-[11px]"></i>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this publication?');" class="inline m-0">
                                                    <input type="hidden" name="publication_id" value="<?php echo $pub['id']; ?>">
                                                    <button type="submit" name="delete_publication" class="w-7 h-7 rounded-md bg-gray-100 text-gray-400 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center transition-colors" title="Delete Publication">
                                                        <i class="fas fa-trash-alt text-[11px]"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Add Publication & Categories (4 cols) -->
                <div class="lg:col-span-4 space-y-5">
                    
                    <!-- Card 1: Add New Publication (Streamlined for Free PDF & Store Items) -->
                    <div class="bg-white p-4 sm:p-5 rounded-xl border border-gray-200 shadow-xs">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                            <div>
                                <h2 class="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                                    <i class="fas fa-plus-circle text-blue-600"></i> Add Publication
                                </h2>
                                <p class="text-[11px] text-gray-500 mt-0.5">Upload free PDFs or add bookstore items</p>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="space-y-3.5">
                            
                            <!-- Publication Type Segmented Selector -->
                            <div>
                                <label class="text-[11px] font-semibold text-gray-600 block mb-1.5 uppercase tracking-wider">Publication Type</label>
                                <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-lg">
                                    <button type="button" id="btnTypeFree" onclick="selectPubType('free')" class="py-1.5 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all bg-white text-emerald-700 shadow-xs">
                                        <i class="fas fa-gift text-emerald-600 text-xs"></i> Free PDF
                                    </button>
                                    <button type="button" id="btnTypePaid" onclick="selectPubType('paid')" class="py-1.5 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-tag text-blue-600 text-xs"></i> Paid Item
                                    </button>
                                </div>
                                <input type="hidden" name="is_free" id="add_is_free" value="1">
                            </div>

                            <!-- Title -->
                            <div>
                                <label class="text-xs font-medium text-gray-700 block mb-1">Publication Title <span class="text-rose-500">*</span></label>
                                <input type="text" name="title" required placeholder="e.g. 2026 Physics Past Papers Revision" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            </div>

                            <!-- Category -->
                            <div>
                                <label class="text-xs font-medium text-gray-700 block mb-1">Category <span class="text-rose-500">*</span></label>
                                <select name="category_id" required class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white">
                                    <option value="" disabled selected>-- Choose Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- PDF Upload Dropzone / Field -->
                            <div class="p-3 rounded-lg border border-dashed border-blue-300 bg-blue-50/40 space-y-1.5 transition-all" id="pdfUploadBox">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-semibold text-blue-900 flex items-center gap-1.5">
                                        <i class="fas fa-file-pdf text-rose-500 text-sm"></i> PDF Document (.pdf)
                                        <span id="pdfRequiredBadge" class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.2 rounded">Required for Free</span>
                                    </label>
                                </div>
                                <input type="file" name="publication_pdf" id="add_pdf_file" accept="application/pdf,.pdf" onchange="previewSelectedPdf(this, 'addPdfFileName')" class="block w-full text-xs text-gray-500 file:mr-2.5 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                                <div id="addPdfFileName" class="text-[11px] text-gray-500 italic hidden font-medium"></div>
                                <p class="text-[10px] text-gray-400">Maximum recommended size: 50MB. Students can download directly.</p>
                            </div>

                            <!-- Cover Image -->
                            <div>
                                <label class="text-xs font-medium text-gray-700 block mb-1">Cover Thumbnail (Optional)</label>
                                <input type="file" name="publication_image" accept="image/*" class="block w-full text-xs text-gray-500 file:mr-2.5 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-[11px] file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer">
                                <p class="text-[10px] text-gray-400 mt-0.5">JPG, PNG or WEBP book cover thumbnail.</p>
                            </div>

                            <!-- Price & Discount Container (Shown only for Paid Items) -->
                            <div id="addPriceContainer" class="hidden space-y-3 pt-2 border-t border-gray-100">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-gray-700 block mb-1">Price (LKR) <span class="text-rose-500">*</span></label>
                                        <input type="number" step="0.01" name="price" id="add_price" value="0.00" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-700 block mb-1">Discount (LKR)</label>
                                        <input type="number" step="0.01" name="discount" id="add_discount" value="0.00" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500">
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="text-xs font-medium text-gray-700 block mb-1">Description (Optional)</label>
                                <textarea name="description" rows="2" placeholder="Short description of the publication content..." class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>

                            <button type="submit" name="add_publication" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors flex items-center justify-center gap-1.5">
                                <i class="fas fa-cloud-arrow-up"></i> Publish Publication
                            </button>
                        </form>
                    </div>

                    <!-- Card 2: Manage Categories -->
                    <div class="bg-white p-4 sm:p-5 rounded-xl border border-gray-200 shadow-xs">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                            <h3 class="text-xs font-bold text-gray-900 flex items-center gap-1.5">
                                <i class="fas fa-folder text-amber-500"></i> Manage Categories
                            </h3>
                            <span class="text-[11px] text-gray-400 font-medium"><?php echo count($categories); ?> total</span>
                        </div>
                        
                        <!-- Add Category Form -->
                        <form method="POST" class="mb-3.5 flex gap-1.5">
                            <input type="text" name="category_name" required placeholder="New category name" class="flex-1 px-3 py-1.5 text-xs bg-white border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:outline-none">
                            <button type="submit" name="add_category" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg shadow-xs transition-colors shrink-0">
                                <i class="fas fa-plus"></i>
                            </button>
                        </form>

                        <!-- Existing Categories List -->
                        <div class="space-y-1.5 max-h-56 overflow-y-auto custom-scrollbar pr-1">
                            <?php if (empty($categories)): ?>
                                <p class="text-[11px] text-gray-400 italic text-center py-2">No categories yet.</p>
                            <?php endif; ?>
                            <?php foreach ($categories as $cat): ?>
                            <div class="flex items-center justify-between px-2.5 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <i class="fas fa-tag text-[10px] text-gray-400"></i>
                                    <span class="font-medium text-gray-800 truncate"><?php echo htmlspecialchars($cat['name']); ?></span>
                                    <span class="text-[10px] text-gray-400 bg-white px-1.5 py-0.2 rounded border border-gray-200 shrink-0">
                                        <?php echo intval($cat['pub_count'] ?? 0); ?>
                                    </span>
                                </div>
                                <form method="POST" onsubmit="return confirm('Delete category \'<?php echo addslashes($cat['name']); ?>\'?');" class="m-0 shrink-0">
                                    <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" name="delete_category" class="text-gray-400 hover:text-rose-500 p-1 transition-colors" title="Delete category">
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- ===================== EDIT PUBLICATION MODAL ===================== -->
    <div id="editPubModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" onclick="closeEditPubModal()"></div>

        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative bg-white w-full max-w-lg rounded-xl shadow-xl border border-gray-200 p-5 sm:p-6 my-8">
                
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                            <i class="fas fa-pen-to-square text-blue-600"></i> Edit Publication
                        </h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Update PDF file, format, category, or pricing</p>
                    </div>
                    <button type="button" onclick="closeEditPubModal()" class="w-7 h-7 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 flex items-center justify-center transition-colors">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-3.5">
                    <input type="hidden" name="publication_id" id="edit_pub_id">
                    <input type="hidden" name="is_free" id="edit_is_free" value="1">

                    <!-- Format Selector -->
                    <div>
                        <label class="text-[11px] font-semibold text-gray-600 block mb-1.5 uppercase tracking-wider">Format</label>
                        <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-lg">
                            <button type="button" id="editBtnTypeFree" onclick="selectEditPubType('free')" class="py-1 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all bg-white text-emerald-700 shadow-xs">
                                <i class="fas fa-gift text-emerald-600 text-xs"></i> Free PDF
                            </button>
                            <button type="button" id="editBtnTypePaid" onclick="selectEditPubType('paid')" class="py-1 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all text-gray-600 hover:text-gray-900">
                                <i class="fas fa-tag text-blue-600 text-xs"></i> Paid Item
                            </button>
                        </div>
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Title <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" id="edit_title" required class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Category & Cover Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-gray-700 block mb-1">Category <span class="text-rose-500">*</span></label>
                            <select name="category_id" id="edit_category_id" required class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 bg-white">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-700 block mb-1">Replace Cover Image</label>
                            <div class="flex items-center gap-2">
                                <img id="editCurrentImg" src="" class="w-8 h-10 object-cover rounded border border-gray-200 shrink-0 hidden bg-slate-100">
                                <input type="file" name="publication_image" accept="image/*" class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[10px] file:bg-gray-100 hover:file:bg-gray-200 cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <!-- PDF Document Field in Modal -->
                    <div class="p-3 rounded-lg border border-dashed border-blue-300 bg-blue-50/40 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-semibold text-blue-900 flex items-center gap-1.5">
                                <i class="fas fa-file-pdf text-rose-500 text-sm"></i> PDF Document (.pdf)
                            </label>
                        </div>
                        <div id="editCurrentPdfNotice" class="text-xs"></div>
                        <input type="file" name="publication_pdf" accept="application/pdf,.pdf" class="block w-full text-xs text-gray-500 file:mr-2.5 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                        <div id="removePdfContainer" class="hidden pt-1">
                            <label class="inline-flex items-center gap-1.5 text-xs font-medium text-rose-600 cursor-pointer">
                                <input type="checkbox" name="remove_pdf" id="edit_remove_pdf" value="1" class="rounded border-gray-300 text-rose-600 focus:ring-rose-500">
                                <span>Remove current PDF document</span>
                            </label>
                        </div>
                    </div>

                    <!-- Price & Discount in Modal -->
                    <div id="editPriceContainer" class="hidden grid grid-cols-2 gap-3 pt-1 border-t border-gray-100">
                        <div>
                            <label class="text-xs font-medium text-gray-700 block mb-1">Price (LKR) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" name="price" id="edit_price" value="0.00" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-700 block mb-1">Discount (LKR)</label>
                            <input type="number" step="0.01" name="discount" id="edit_discount" value="0.00" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Description</label>
                        <textarea name="description" id="edit_description" rows="2" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-2 border-t border-gray-100">
                        <button type="button" onclick="closeEditPubModal()" class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" name="edit_publication" class="px-4 py-1.5 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-xs transition-colors">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================== RECEIPT PREVIEW MODAL ===================== -->
    <div id="receiptModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" onclick="closeReceiptModal()"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative bg-white w-full max-w-lg rounded-xl shadow-2xl border border-gray-200 p-4 sm:p-5">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                    <h3 class="text-xs font-bold text-gray-900 flex items-center gap-1.5">
                        <i class="fas fa-receipt text-blue-600"></i> Bank Transfer Slip
                    </h3>
                    <button type="button" onclick="closeReceiptModal()" class="w-6 h-6 rounded-md text-gray-400 hover:text-gray-600 flex items-center justify-center">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <div class="max-h-[70vh] overflow-auto flex items-center justify-center bg-slate-50 rounded-lg p-2 border border-gray-100">
                    <img id="receiptModalImg" src="" alt="Payment Receipt" class="max-h-[65vh] object-contain rounded">
                </div>
                <div class="mt-3 flex justify-end">
                    <a id="receiptDownloadLink" href="#" target="_blank" class="px-3 py-1.5 text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-1.5">
                        <i class="fas fa-external-link-alt text-[10px]"></i> Open Full Size
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="statusToast" class="fixed bottom-6 right-6 z-50 transform translate-y-16 opacity-0 transition-all duration-200 bg-gray-900 text-white shadow-lg rounded-lg px-4 py-2.5 flex items-center gap-2.5 text-xs font-medium">
        <i id="toastIcon" class="fas fa-check text-emerald-400 text-sm"></i>
        <span id="toastMessage">Order updated successfully</span>
    </div>

    <script>
        // Publication Type Switcher (Add Form)
        function selectPubType(type) {
            const btnFree = document.getElementById('btnTypeFree');
            const btnPaid = document.getElementById('btnTypePaid');
            const isFreeInput = document.getElementById('add_is_free');
            const priceContainer = document.getElementById('addPriceContainer');
            const priceInput = document.getElementById('add_price');
            const discountInput = document.getElementById('add_discount');
            const pdfBox = document.getElementById('pdfUploadBox');
            const pdfBadge = document.getElementById('pdfRequiredBadge');

            if (type === 'free') {
                btnFree.className = 'py-1.5 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all bg-white text-emerald-700 shadow-xs';
                btnPaid.className = 'py-1.5 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all text-gray-600 hover:text-gray-900';
                isFreeInput.value = '1';
                priceContainer.classList.add('hidden');
                priceInput.value = '0.00';
                discountInput.value = '0.00';
                pdfBox.classList.remove('border-gray-200', 'bg-gray-50/40');
                pdfBox.classList.add('border-emerald-300', 'bg-emerald-50/30');
                pdfBadge.classList.remove('hidden');
            } else {
                btnPaid.className = 'py-1.5 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all bg-white text-blue-700 shadow-xs';
                btnFree.className = 'py-1.5 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all text-gray-600 hover:text-gray-900';
                isFreeInput.value = '0';
                priceContainer.classList.remove('hidden');
                if (parseFloat(priceInput.value) <= 0) priceInput.value = '';
                pdfBox.classList.remove('border-emerald-300', 'bg-emerald-50/30');
                pdfBox.classList.add('border-blue-300', 'bg-blue-50/40');
                pdfBadge.classList.add('hidden');
            }
        }

        // Publication Type Switcher (Edit Form)
        function selectEditPubType(type) {
            const btnFree = document.getElementById('editBtnTypeFree');
            const btnPaid = document.getElementById('editBtnTypePaid');
            const isFreeInput = document.getElementById('edit_is_free');
            const priceContainer = document.getElementById('editPriceContainer');
            const priceInput = document.getElementById('edit_price');
            const discountInput = document.getElementById('edit_discount');

            if (type === 'free') {
                btnFree.className = 'py-1 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all bg-white text-emerald-700 shadow-xs';
                btnPaid.className = 'py-1 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all text-gray-600 hover:text-gray-900';
                isFreeInput.value = '1';
                priceContainer.classList.add('hidden');
                priceInput.value = '0.00';
                discountInput.value = '0.00';
            } else {
                btnPaid.className = 'py-1 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all bg-white text-blue-700 shadow-xs';
                btnFree.className = 'py-1 px-2 rounded-md text-xs font-semibold flex items-center justify-center gap-1.5 transition-all text-gray-600 hover:text-gray-900';
                isFreeInput.value = '0';
                priceContainer.classList.remove('hidden');
            }
        }

        // PDF file selection preview
        function previewSelectedPdf(input, noticeId) {
            const notice = document.getElementById(noticeId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                notice.textContent = `Selected: ${file.name} (${sizeMb} MB)`;
                notice.classList.remove('hidden');
            } else {
                notice.classList.add('hidden');
            }
        }

        // Live Filter for Publications Table
        function filterPubsTable() {
            const query = (document.getElementById('pubSearchInput')?.value || '').toLowerCase();
            const typeFilter = document.getElementById('pubTypeFilter')?.value || '';
            const rows = document.querySelectorAll('#publicationsTable tbody tr.pub-row');
            let visible = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const type = row.getAttribute('data-type');
                const matchesText = text.includes(query);
                const matchesType = !typeFilter || type === typeFilter;

                if (matchesText && matchesType) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            const countSpan = document.getElementById('pubVisibleCount');
            if (countSpan) countSpan.textContent = visible;
        }

        // Live Filter for Orders Table
        function filterOrdersTable() {
            const query = (document.getElementById('orderSearchInput')?.value || '').toLowerCase();
            const statusFilter = document.getElementById('orderStatusFilter')?.value || '';
            const rows = document.querySelectorAll('#ordersTable tbody tr.order-row');
            let visible = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                const matchesText = text.includes(query);
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesText && matchesStatus) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            const countSpan = document.getElementById('ordersVisibleCount');
            if (countSpan) countSpan.textContent = visible;
        }

        // AJAX Update Order Status
        function updateOrderStatus(selectElement, orderId) {
            const status = selectElement.value;
            selectElement.disabled = true;
            selectElement.style.opacity = '0.5';

            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('status', status);

            fetch('ajax_update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    const colors = {
                        'pending': 'bg-amber-50 text-amber-700 border-amber-200',
                        'preparing': 'bg-blue-50 text-blue-700 border-blue-200',
                        'hand_order_to_delivery': 'bg-indigo-50 text-indigo-700 border-indigo-200',
                        'completed': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'canceled': 'bg-rose-50 text-rose-700 border-rose-200',
                        'return_requested': 'bg-purple-50 text-purple-700 border-purple-200'
                    };
                    
                    const row = selectElement.closest('tr');
                    if (row) row.setAttribute('data-status', status);

                    // Reset styling
                    selectElement.className = 'status-select text-xs font-semibold rounded-md border px-2 py-1 focus:ring-1 focus:ring-blue-500 focus:outline-none transition-colors cursor-pointer ' + (colors[status] || 'bg-slate-50 text-slate-700 border-slate-200');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(() => {
                showToast('Network error occurred', 'error');
            })
            .finally(() => {
                selectElement.disabled = false;
                selectElement.style.opacity = '1';
            });
        }

        // Toast Feedback
        function showToast(message, type = 'success') {
            const toast = document.getElementById('statusToast');
            const toastMsg = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            
            toastMsg.textContent = message;
            if (type === 'success') {
                toastIcon.className = 'fas fa-check text-emerald-400 text-sm';
            } else {
                toastIcon.className = 'fas fa-triangle-exclamation text-rose-400 text-sm';
            }

            toast.classList.remove('translate-y-16', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-16', 'opacity-0');
            }, 3000);
        }

        // Edit Modal Controls
        function openEditPubModal(pub) {
            document.getElementById('edit_pub_id').value = pub.id;
            document.getElementById('edit_title').value = pub.title;
            document.getElementById('edit_category_id').value = pub.category_id;
            document.getElementById('edit_description').value = pub.description || '';
            
            const isFree = (pub.is_free == 1 || (parseFloat(pub.price) <= 0 && pub.pdf_path));
            selectEditPubType(isFree ? 'free' : 'paid');

            document.getElementById('edit_price').value = parseFloat(pub.price || 0).toFixed(2);
            document.getElementById('edit_discount').value = parseFloat(pub.discount || 0).toFixed(2);

            // Cover preview
            const imgPreview = document.getElementById('editCurrentImg');
            if (pub.image_path) {
                imgPreview.src = '../' + pub.image_path;
                imgPreview.classList.remove('hidden');
            } else {
                imgPreview.classList.add('hidden');
            }

            // PDF preview
            const pdfNotice = document.getElementById('editCurrentPdfNotice');
            const removePdfContainer = document.getElementById('removePdfContainer');
            if (pub.pdf_path) {
                pdfNotice.innerHTML = `<span class="text-emerald-700 font-medium flex items-center gap-1.5"><i class="fas fa-check-circle text-emerald-500"></i> Current PDF: <a href="../download_publication.php?id=${pub.id}" target="_blank" class="underline text-blue-600 hover:text-blue-800 font-semibold">Download / View</a></span>`;
                removePdfContainer.classList.remove('hidden');
            } else {
                pdfNotice.innerHTML = '<span class="text-gray-400 italic">No PDF document attached</span>';
                removePdfContainer.classList.add('hidden');
            }
            document.getElementById('edit_remove_pdf').checked = false;

            document.getElementById('editPubModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeEditPubModal() {
            document.getElementById('editPubModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Receipt Preview Modal
        function viewReceiptModal(imagePath) {
            const modal = document.getElementById('receiptModal');
            const img = document.getElementById('receiptModalImg');
            const link = document.getElementById('receiptDownloadLink');
            img.src = imagePath;
            link.href = imagePath;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeReceiptModal() {
            document.getElementById('receiptModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    </script>
</body>
</html>
