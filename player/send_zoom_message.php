<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$zoom_class_id = isset($_POST['zoom_class_id']) ? intval($_POST['zoom_class_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($zoom_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom class ID']);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

// Insert message
$insert_query = "INSERT INTO zoom_chat_messages (zoom_class_id, sender_id, message) VALUES (?, ?, ?)";
$insert_stmt = $conn->prepare($insert_query);
$insert_stmt->bind_param("iss", $zoom_class_id, $user_id, $message);

if ($insert_stmt->execute()) {
    $message_id = $insert_stmt->insert_id;
    $insert_stmt->close();
    
    // Get the complete message with sender info
    $select_query = "SELECT zcm.*, u.first_name, u.second_name, u.profile_picture
                    FROM zoom_chat_messages zcm
                    INNER JOIN users u ON zcm.sender_id = u.user_id
                    WHERE zcm.id = ?";
    $select_stmt = $conn->prepare($select_query);
    $select_stmt->bind_param("i", $message_id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $msg = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'message' => [
                'id' => $msg['id'],
                'sender_id' => $msg['sender_id'],
                'sender_name' => trim($msg['first_name'] . ' ' . $msg['second_name']),
                'sender_avatar' => $msg['profile_picture'],
                'message' => $msg['message'],
                'sent_at' => $msg['sent_at']
            ]
        ]);
    } else {
        echo json_encode(['success' => true]);
    }
    $select_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error sending message']);
}

$conn->close();
?>
