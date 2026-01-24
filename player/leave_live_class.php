<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Only students can leave live classes']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recording_id = intval($_POST['recording_id'] ?? 0);
    
    if ($recording_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
        exit;
    }
    
    // Update left_at timestamp
    $update_query = "UPDATE live_class_participants SET left_at = NOW() 
                    WHERE recording_id = ? AND student_id = ? AND left_at IS NULL";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("is", $recording_id, $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Left live class']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error leaving live class']);
    }
    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
