<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$zoom_class_id = isset($_POST['zoom_class_id']) ? intval($_POST['zoom_class_id']) : 0;

if ($zoom_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom class ID']);
    exit;
}

// Check if user is the teacher owner
$check_query = "SELECT ta.teacher_id 
                FROM zoom_classes zc
                INNER JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
                WHERE zc.id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $zoom_class_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Zoom class not found']);
    exit;
}

$class_data = $check_result->fetch_assoc();
$check_stmt->close();

if ($role !== 'teacher' || $class_data['teacher_id'] !== $user_id) {
    echo json_encode(['success' => false, 'message' => 'Only the teacher can end the class']);
    exit;
}

// Update class status to ended
$update_query = "UPDATE zoom_classes SET status = 'ended', end_time = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("i", $zoom_class_id);

if ($update_stmt->execute()) {
    // Close all active participant sessions
    $close_participants_query = "UPDATE zoom_participants 
                                SET leave_time = NOW(), 
                                    duration_minutes = TIMESTAMPDIFF(MINUTE, join_time, NOW())
                                WHERE zoom_class_id = ? AND leave_time IS NULL";
    $close_stmt = $conn->prepare($close_participants_query);
    $close_stmt->bind_param("i", $zoom_class_id);
    $close_stmt->execute();
    $close_stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Zoom class ended successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error ending class']);
}

$update_stmt->close();
$conn->close();
?>
