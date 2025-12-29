<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Only students can leave (teachers end the class, not leave)
if ($role !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$live_class_id = isset($_POST['live_class_id']) ? intval($_POST['live_class_id']) : 0;

if ($live_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid live class ID']);
    exit;
}

// Mark participant as left
$update_query = "UPDATE live_class_participants SET left_at = NOW() WHERE live_class_id = ? AND student_id = ? AND left_at IS NULL";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("is", $live_class_id, $user_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Left live class successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error leaving live class']);
}

$update_stmt->close();


