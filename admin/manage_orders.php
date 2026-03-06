<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

// Verify user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: /lms/login.php");
    exit();
}

$page_title = "Manage Publication Orders";
$success_message = '';
$error_message = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    
    // Valid statuses
    $valid_statuses = ['pending', 'preparing', 'hand_order_to_delivery', 'canceled'];
    if (in_array($new_status, $valid_statuses)) {
        
        // Fetch order details for WhatsApp notification
        $stmt = $conn->prepare("SELECT name, contact_number, status FROM publication_orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($order = $result->fetch_assoc()) {
            if ($order['status'] !== $new_status) {
                // Update status in DB
                $update_stmt = $conn->prepare("UPDATE publication_orders SET status = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_status, $order_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Order #{$order_id} status updated to " . str_replace('_', ' ', $new_status) . ".";
                    
                    // Send WhatsApp Notification
                    $customer_name = $order['name'];
                    $contact_number = $order['contact_number'];
                    
                    $status_messages = [
                        'pending' => "Your order is currently pending.",
                        'preparing' => "Good news! We are currently preparing your order.",
                        'hand_order_to_delivery' => "Your order has been handed over to our delivery partner. It's on its way to you!",
                        'canceled' => "We're sorry to inform you that your order has been canceled."
                    ];
                    
                    $status_text = $status_messages[$new_status] ?? "Your order status has been updated to {$new_status}.";
                    
                    $msg = "Hello $customer_name,\n\n🛒 *Order Update Details*\nOrder ID: $order_id\n\n$status_text\n\nThank you!\n- LearnerX";
                    
                    sendWhatsAppMessage($contact_number, $msg);
                    
                } else {
                    $error_message = "Error updating order: " . $conn->error;
                }
                $update_stmt->close();
            } else {
                $error_message = "Order is already in this status.";
            }
        } else {
            $error_message = "Order not found.";
        }
        $stmt->close();
    } else {
        $error_message = "Invalid status selected.";
    }
}

// Fetch all orders with their total amount
$query = "
    SELECT o.*, 
           (SELECT SUM(price_at_order * quantity) FROM publication_order_items WHERE order_id = o.id) as total_amount,
           (SELECT GROUP_CONCAT(CONCAT(p.title, ' (x', poi.quantity, ')') SEPARATOR ', ') 
            FROM publication_order_items poi 
            JOIN publications p ON poi.publication_id = p.id 
            WHERE poi.order_id = o.id) as items_summary
    FROM publication_orders o
    ORDER BY o.created_at DESC
";
$orders = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Publication Orders - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
             <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Manage Publication Orders</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items & Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status & Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?php echo htmlspecialchars($order['id']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($order['name']); ?></div>
                                    <div class="text-xs text-gray-500"><i class="fas fa-phone mr-1"></i> <?php echo htmlspecialchars($order['contact_number']); ?></div>
                                    <div class="text-xs text-gray-500 mt-1" title="<?php echo htmlspecialchars($order['address']); ?>">
                                        <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($order['district']); ?>
                                    </div>
                                    <?php if (!empty($order['user_id'])): ?>
                                        <div class="text-xs text-blue-600 mt-1">Logged User ID: <?php echo htmlspecialchars($order['user_id']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 truncate w-48" title="<?php echo htmlspecialchars($order['items_summary']); ?>">
                                        <?php echo htmlspecialchars($order['items_summary']); ?>
                                    </div>
                                    <div class="text-sm font-bold text-green-600 mt-1">LKR <?php echo number_format($order['total_amount'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method']))); ?>
                                    </div>
                                    <?php if ($order['payment_method'] === 'bank_transfer' && !empty($order['bank_receipt_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($order['bank_receipt_path']); ?>" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 underline mt-1 inline-block">
                                            <i class="fas fa-file-invoice mr-1"></i>View Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <form method="POST" action="" class="flex flex-col items-center">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        
                                        <?php 
                                            // Badges styling based on status
                                            $status = $order['status'];
                                            $bg_color = 'bg-gray-100 text-gray-800';
                                            if ($status == 'pending') $bg_color = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                            if ($status == 'preparing') $bg_color = 'bg-blue-100 text-blue-800 border-blue-200';
                                            if ($status == 'hand_order_to_delivery') $bg_color = 'bg-indigo-100 text-indigo-800 border-indigo-200';
                                            if ($status == 'canceled') $bg_color = 'bg-red-100 text-red-800 border-red-200';
                                        ?>
                                        
                                        <select name="status" class="w-full text-sm font-semibold rounded-md border shadow-sm p-1.5 focus:ring-blue-500 focus:border-blue-500 <?php echo $bg_color; ?>">
                                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="preparing" <?php echo $status == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                            <option value="hand_order_to_delivery" <?php echo $status == 'hand_order_to_delivery' ? 'selected' : ''; ?>>On Delivery</option>
                                            <option value="canceled" <?php echo $status == 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                                        </select>
                                        
                                        <button type="submit" name="update_status" class="mt-2 w-full text-xs bg-gray-800 text-white px-2 py-1.5 rounded hover:bg-gray-700 transition shadow-sm">
                                            Update Status
                                        </button>
                                    </form>
                                    <div class="text-[10px] text-gray-400 mt-1 italic"><i class="fab fa-whatsapp mr-1 text-green-500"></i>Notifies user</div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                    <i class="fas fa-box-open fa-3x mb-3 text-gray-300"></i>
                                    <p class="text-lg">No publication orders found.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
