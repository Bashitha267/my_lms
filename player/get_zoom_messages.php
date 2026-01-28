<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$zoom_class_id = isset($_GET['zoom_class_id']) ? intval($_GET['zoom_class_id']) : 0;
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if ($zoom_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom class ID']);
    exit;
}

// Get new messages
$query = "SELECT zcm.*, u.first_name, u.second_name, u.profile_picture
          FROM zoom_chat_messages zcm
          INNER JOIN users u ON zcm.sender_id = u.user_id
          WHERE zcm.zoom_class_id = ? AND zcm.id > ?
          ORDER BY zcm.sent_at ASC
          LIMIT 50";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $zoom_class_id, $last_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'sender_name' => trim($row['first_name'] . ' ' . $row['second_name']),
        'sender_avatar' => $row['profile_picture'],
        'message' => $row['message'],
        'sent_at' => $row['sent_at']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'messages' => $messages
]);
?>
