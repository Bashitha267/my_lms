<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Verify the order belongs to the user and is in 'hand_order_to_delivery' status
$stmt = $conn->prepare("SELECT status FROM publication_orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("is", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['status'] !== 'hand_order_to_delivery') {
        echo json_encode(['success' => false, 'message' => 'Order is not out for delivery']);
        exit;
    }
    
    // Update status to 'completed'
    $update_stmt = $conn->prepare("UPDATE publication_orders SET status = 'completed' WHERE id = ?");
    $update_stmt->bind_param("i", $order_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Delivery confirmed! Order completed.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to confirm delivery']);
    }
    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
}

$stmt->close();
$conn->close();
?>
