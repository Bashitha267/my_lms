<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

// Verify user is admin
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: /lms/login.php");
    exit();
}

$active_tab = $_GET['tab'] ?? 'orders';
$success_message = '';
$error_message = '';

// --- TAB 1: ORDER MANAGEMENT LOGIC ---
if ($active_tab === 'orders' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $valid_statuses = ['pending', 'preparing', 'hand_order_to_delivery', 'canceled'];
    
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("SELECT name, contact_number, status FROM publication_orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($order = $res->fetch_assoc()) {
            if ($order['status'] !== $new_status) {
                $update_stmt = $conn->prepare("UPDATE publication_orders SET status = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_status, $order_id);
                if ($update_stmt->execute()) {
                    $success_message = "Order #{$order_id} status updated.";
                    // WhatsApp
                    $status_messages = [
                        'pending' => "Your order is pending.",
                        'preparing' => "We are preparing your order.",
                        'hand_order_to_delivery' => "Your order is on the way!",
                        'canceled' => "Your order has been canceled."
                    ];
                    $msg = "Hello {$order['name']},\n\n🛒 *Order Update*\nOrder ID: $order_id\n\n" . ($status_messages[$new_status] ?? "Status: $new_status") . "\n\n- LearnerX";
                    sendWhatsAppMessage($order['contact_number'], $msg);
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
        $price = floatval($_POST['price']);
        $discount = floatval($_POST['discount']);
        
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
        
        $stmt = $conn->prepare("INSERT INTO publications (category_id, title, description, price, discount, image_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdds", $category_id, $title, $description, $price, $discount, $image_path);
        if ($stmt->execute()) $success_message = "Publication added.";
        else $error_message = "Error: " . $conn->error;
        $stmt->close();
    }
    // Delete Publication
    elseif (isset($_POST['delete_publication'])) {
        $pid = intval($_POST['publication_id']);
        $res = $conn->query("SELECT image_path FROM publications WHERE id = $pid")->fetch_assoc();
        if ($res && !empty($res['image_path']) && file_exists('../' . $res['image_path'])) unlink('../' . $res['image_path']);
        $conn->query("DELETE FROM publications WHERE id = $pid");
        $success_message = "Publication deleted.";
    }
}

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

// Fetch Publications (Tab 2)
$categories = $conn->query("SELECT * FROM publication_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publications & Orders | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 min-h-screen">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Publications & Orders</h1>
            <p class="text-slate-500 font-medium">Manage book orders and store inventory.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded-xl flex items-center justify-between">
                <div class="flex items-center"><i class="fas fa-check-circle mr-3"></i><span class="font-bold"><?php echo $success_message; ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex space-x-2 mb-8 bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200 inline-flex">
            <a href="?tab=orders" class="px-6 py-2.5 rounded-xl font-bold transition-all <?php echo $active_tab === 'orders' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50'; ?>">
                Manage Orders
            </a>
            <a href="?tab=publications" class="px-6 py-2.5 rounded-xl font-bold transition-all <?php echo $active_tab === 'publications' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50'; ?>">
                Inventory & Add New
            </a>
        </div>

        <?php if ($active_tab === 'orders'): ?>
            <!-- TAB: ORDERS -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Order Details</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Items & Total</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Payment</th>
                                <th class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Status Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-black text-slate-900 leading-tight">#<?php echo $order['id']; ?> - <?php echo htmlspecialchars($order['name']); ?></div>
                                    <div class="text-xs text-slate-400 font-bold mt-1 tracking-tighter"><?php echo $order['contact_number']; ?> | <?php echo $order['district']; ?></div>
                                    <div class="text-[10px] text-slate-300 mt-1"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-slate-600 font-medium mb-1 w-48 truncate" title="<?php echo htmlspecialchars($order['items_summary']); ?>"><?php echo htmlspecialchars($order['items_summary']); ?></div>
                                    <div class="text-sm font-black text-blue-600">LKR <?php echo number_format($order['total_amount'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-xs font-bold text-slate-700 uppercase"><?php echo str_replace('_', ' ', $order['payment_method']); ?></span>
                                    <?php if ($order['bank_receipt_path']): ?>
                                        <a href="../<?php echo $order['bank_receipt_path']; ?>" target="_blank" class="block text-[10px] text-blue-500 font-bold hover:underline mt-1">View Receipt</a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col items-center gap-2">
                                        <?php 
                                            $s = $order['status'];
                                            $clr = 'bg-slate-100 text-slate-600';
                                            if ($s == 'pending') $clr = 'bg-amber-100 text-amber-600';
                                            if ($s == 'preparing') $clr = 'bg-blue-100 text-blue-600';
                                            if ($s == 'hand_order_to_delivery') $clr = 'bg-indigo-100 text-indigo-600';
                                            if ($s == 'canceled') $clr = 'bg-red-100 text-red-600';
                                            if ($s == 'completed') $clr = 'bg-emerald-100 text-emerald-600';
                                            if ($s == 'return_requested') $clr = 'bg-purple-100 text-purple-600';
                                        ?>
                                        <select onchange="updateOrderStatus(this, <?php echo $order['id']; ?>)" class="status-select text-[10px] font-black rounded-xl border-2 border-slate-100 p-2 <?php echo $clr; ?> focus:border-blue-500 focus:ring-0 transition-colors">
                                            <option value="pending" <?php echo $s == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="preparing" <?php echo $s == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                            <option value="hand_order_to_delivery" <?php echo $s == 'hand_order_to_delivery' ? 'selected' : ''; ?>>On Delivery</option>
                                            <option value="canceled" <?php echo $s == 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                                            <option value="completed" <?php echo $s == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="return_requested" <?php echo $s == 'return_requested' ? 'selected' : ''; ?>>Return Requested</option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- TAB: PUBLICATIONS -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Add Publication -->
                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
                        <h2 class="text-xl font-black text-slate-900 mb-6 flex items-center">
                            <i class="fas fa-plus-circle mr-3 text-blue-600"></i>Add New Publication
                        </h2>
                        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Title</label>
                                <input type="text" name="title" required class="w-full px-4 py-3 rounded-2xl border-2 border-slate-50 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 font-bold transition-all" placeholder="Enter book title">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Category</label>
                                <select name="category_id" required class="w-full px-4 py-3 rounded-2xl border-2 border-slate-50 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 font-bold transition-all">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Cover Image</label>
                                <input type="file" name="publication_image" class="text-xs font-bold text-slate-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Description</label>
                                <textarea name="description" rows="3" class="w-full px-4 py-3 rounded-2xl border-2 border-slate-50 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 font-bold transition-all"></textarea>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Price (LKR)</label>
                                <input type="number" step="0.01" name="price" required class="w-full px-4 py-3 rounded-2xl border-2 border-slate-50 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 font-bold transition-all">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Discount (LKR)</label>
                                <input type="number" step="0.01" value="0" name="discount" class="w-full px-4 py-3 rounded-2xl border-2 border-slate-50 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 font-bold transition-all">
                            </div>
                            <button type="submit" name="add_publication" class="md:col-span-2 bg-blue-600 text-white py-4 rounded-2xl font-black text-lg hover:bg-blue-700 transition shadow-xl mt-4">Publish Item</button>
                        </form>
                    </div>

                    <!-- Inventory Table -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-50"><h3 class="font-black text-slate-900 uppercase tracking-widest text-xs">Current Inventory</h3></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] uppercase font-black text-slate-400">
                                        <th class="px-6 py-4 text-left">Item</th>
                                        <th class="px-6 py-4 text-left">Price</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($publications as $pub): ?>
                                    <tr>
                                        <td class="px-6 py-4 flex items-center gap-4">
                                            <img src="../<?php echo $pub['image_path'] ?: 'assets/img/book-placeholder.png'; ?>" class="w-10 h-14 object-cover rounded-lg shadow">
                                            <div>
                                                <div class="font-black text-slate-900"><?php echo htmlspecialchars($pub['title']); ?></div>
                                                <div class="text-[10px] text-blue-500 font-bold italic"><?php echo htmlspecialchars($pub['category_name']); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-900">LKR <?php echo number_format($pub['price'] - $pub['discount'], 2); ?></div>
                                            <?php if ($pub['discount'] > 0): ?><div class="text-[10px] text-slate-400 line-through">LKR <?php echo number_format($pub['price'], 2); ?></div><?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" onsubmit="return confirm('Delete?');">
                                                <input type="hidden" name="publication_id" value="<?php echo $pub['id']; ?>">
                                                <button name="delete_publication" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div class="space-y-8 h-fit sticky top-24">
                    <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
                        <h2 class="text-lg font-black text-slate-900 mb-6">Manage Categories</h2>
                        <form method="POST" class="mb-8">
                            <div class="flex gap-2">
                                <input type="text" name="category_name" placeholder="Category Name" class="flex-1 px-4 py-2 bg-slate-50 rounded-xl font-bold text-sm focus:outline-none focus:ring-2 focus:ring-blue-100">
                                <button type="submit" name="add_category" class="bg-blue-600 text-white px-4 rounded-xl hover:bg-blue-700 transition shadow-lg"><i class="fas fa-plus"></i></button>
                            </div>
                        </form>
                        <div class="space-y-3">
                            <?php foreach ($categories as $cat): ?>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl group transition-all hover:bg-white hover:shadow-md border border-transparent hover:border-slate-100">
                                <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($cat['name']); ?></span>
                                <form method="POST" onsubmit="return confirm('Delete?');">
                                    <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                    <button name="delete_category" class="text-slate-300 group-hover:text-red-500 transition-colors"><i class="fas fa-trash-alt text-xs"></i></button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div id="statusToast" class="fixed top-24 right-8 z-[100] transform translate-x-full transition-transform duration-300 bg-white shadow-2xl rounded-2xl border border-slate-100 p-4 flex items-center gap-4 min-w-[300px]">
        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center" id="toastIcon">
            <i class="fas fa-check"></i>
        </div>
        <div>
            <div class="text-xs font-black text-slate-400 uppercase tracking-widest">Update Success</div>
            <div class="text-sm font-bold text-slate-700" id="toastMessage">Order status updated</div>
        </div>
    </div>

    <script>
        function updateOrderStatus(selectElement, orderId) {
            const status = selectElement.value;
            const originalClass = selectElement.className;
            
            // Temporary loading state
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
                    
                    // Update dropdown color based on new status
                    const colors = {
                        'pending': 'bg-amber-100 text-amber-600',
                        'preparing': 'bg-blue-100 text-blue-600',
                        'hand_order_to_delivery': 'bg-indigo-100 text-indigo-600',
                        'canceled': 'bg-red-100 text-red-600',
                        'completed': 'bg-emerald-100 text-emerald-600',
                        'return_requested': 'bg-purple-100 text-purple-600'
                    };
                    
                    // Remove old color classes
                    const colorClasses = Object.values(colors).join(' ');
                    selectElement.className = selectElement.className.split(' ').filter(c => !colorClasses.includes(c)).join(' ');
                    selectElement.classList.add(...(colors[status] || 'bg-slate-100 text-slate-600').split(' '));
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

        function showToast(message, type = 'success') {
            const toast = document.getElementById('statusToast');
            const toastMsg = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            
            toastMsg.textContent = message;
            
            if (type === 'success') {
                toastIcon.className = 'w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center';
                toastIcon.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                toastIcon.className = 'w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center';
                toastIcon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            }

            toast.classList.remove('translate-x-full');
            setTimeout(() => {
                toast.classList.add('translate-x-full');
            }, 3000);
        }
    </script>
</body>
</html>
