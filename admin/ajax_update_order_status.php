<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

header('Content-Type: application/json');

if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Self-heal: ensure status column can store all statuses
$conn->query("ALTER TABLE publication_orders MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    $valid_statuses = ['pending', 'preparing', 'hand_order_to_delivery', 'completed', 'canceled', 'return_requested'];
    
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit();
    }

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
                    // Fetch order items for rich WhatsApp message
                    $items_summary = 'Publication Item(s)';
                    $items_q = $conn->query("SELECT p.title, poi.quantity FROM publication_order_items poi JOIN publications p ON poi.publication_id = p.id WHERE poi.order_id = $order_id");
                    if ($items_q && $items_q->num_rows > 0) {
                        $items_list = [];
                        while ($it = $items_q->fetch_assoc()) {
                            $items_list[] = $it['title'] . ($it['quantity'] > 1 ? " (x{$it['quantity']})" : "");
                        }
                        $items_summary = implode(', ', $items_list);
                    }

                    // Status Titles & Explanations
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

                    // Determine phone number
                    $target_phone = !empty($order['contact_number']) ? $order['contact_number'] : ($order['user_wa'] ?? '');
                    $wa_sent = false;

                    if (!empty($target_phone)) {
                        $clean_phone = preg_replace('/\D/', '', $target_phone);
                        if (!empty($clean_phone)) {
                            $wa_res = sendWhatsAppMessage($clean_phone, $msg);
                            if (is_array($wa_res) && !empty($wa_res['success'])) {
                                $wa_sent = true;
                            }
                            error_log("WhatsApp notification for Order #$order_id to $clean_phone: " . json_encode($wa_res));
                        }
                    }

                    $friendly_name = ucwords(str_replace('_', ' ', $new_status));
                    $msg_text = "Order #$order_id updated to $friendly_name";
                    if ($wa_sent) {
                        $msg_text .= " &bull; WhatsApp sent";
                    } elseif (!empty($target_phone)) {
                        $msg_text .= " (WhatsApp notified)";
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => $msg_text,
                        'whatsapp_sent' => $wa_sent
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Order is already in this status']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid status selected']);
    }
}
?>
