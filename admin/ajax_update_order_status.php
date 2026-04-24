<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $valid_statuses = ['pending', 'preparing', 'hand_order_to_delivery', 'canceled', 'completed', 'return_requested'];
    
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
                    // WhatsApp
                    $status_messages = [
                        'pending' => "Your order is pending.",
                        'preparing' => "We are preparing your order.",
                        'hand_order_to_delivery' => "Your order is on the way!",
                        'canceled' => "Your order has been canceled.",
                        'completed' => "Your order has been completed.",
                        'return_requested' => "Your return request has been received."
                    ];
                    $msg = "Hello {$order['name']},\n\n🛒 *Order Update*\nOrder ID: $order_id\n\n" . ($status_messages[$new_status] ?? "Status: $new_status") . "\n\n- LearnerX";
                    sendWhatsAppMessage($order['contact_number'], $msg);
                    
                    echo json_encode(['success' => true, 'message' => "Order #$order_id updated to " . str_replace('_', ' ', $new_status)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Already in this status']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
    }
}
?>
