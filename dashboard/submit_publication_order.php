<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';
// Include WhatsApp functionality
require_once '../whatsapp_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $publication_id = isset($_POST['publication_id']) ? intval($_POST['publication_id']) : 0;
    
    $name = trim($_POST['name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    
    if (empty($publication_id) || empty($name) || empty($contact_number) || empty($address) || empty($district) || empty($payment_method)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    
    // Fetch publication details to get the price
    $stmt = $conn->prepare("SELECT title, price, discount FROM publications WHERE id = ?");
    $stmt->bind_param("i", $publication_id);
    $stmt->execute();
    $pub_result = $stmt->get_result();
    
    if ($pub_result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Invalid publication selected.']);
        exit;
    }
    
    $pub_row = $pub_result->fetch_assoc();
    $stmt->close();
    
    $pub_title = $pub_row['title'];
    $final_price = floatval($pub_row['price']) - floatval($pub_row['discount']);
    
    // Handle File Upload for Bank Receipt
    $bank_receipt_path = null;
    if ($payment_method === 'bank_transfer') {
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== 0) {
            echo json_encode(['success' => false, 'message' => 'Bank receipt is required for bank transfers.']);
            exit;
        }
        
        $upload_dir = '../uploads/receipts/publications/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'webp', 'pdf');
        
        if (in_array($file_extension, $allowed_extensions)) {
            $unique_filename = uniqid('receipt_pub_') . '.' . $file_extension;
            $target_file = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target_file)) {
                $bank_receipt_path = 'uploads/receipts/publications/' . $unique_filename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload receipt file.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid receipt file format.']);
            exit;
        }
    }
    
    $conn->begin_transaction();
    
    try {
        // Insert Order
        $stmt = $conn->prepare("INSERT INTO publication_orders (user_id, name, contact_number, address, district, payment_method, bank_receipt_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssssss", $user_id, $name, $contact_number, $address, $district, $payment_method, $bank_receipt_path);
        $stmt->execute();
        $order_id = $conn->insert_id;
        $stmt->close();
        
        // Insert Order Items (Just 1 for now)
        $quantity = 1;
        $stmt = $conn->prepare("INSERT INTO publication_order_items (order_id, publication_id, quantity, price_at_order) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $order_id, $publication_id, $quantity, $final_price);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // WhatsApp Notifications
        $order_total_fmt = number_format($final_price, 2);
        
        // To Customer
        $customer_msg = "Hello $name,\n\nYour order for '$pub_title' has been received.\nOrder ID: $order_id\nTotal: LKR $order_total_fmt.\nPayment Method: $payment_method\n\nThank you for shopping with us!\n- LearnerX";
        sendWhatsAppMessage($contact_number, $customer_msg);
        
        // To Admin
        $admin_number = ADMIN_WHATSAPP;
        $admin_msg = "🔔 New Publication Order received!\n\nOrder ID: $order_id\nItem: $pub_title\nCustomer: $name\nContact: $contact_number\nLocation: $address, $district\nPayment Method: $payment_method\nTotal: LKR $order_total_fmt\n\nPlease check the admin panel.";
        sendWhatsAppMessage($admin_number, $admin_msg);
        
        echo json_encode(['success' => true, 'message' => 'Order placed successfully!']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'An error occurred saving your order.']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
