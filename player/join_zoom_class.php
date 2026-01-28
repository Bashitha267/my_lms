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

// Check if class exists and is accessible
$check_query = "SELECT zc.*, ta.teacher_id 
                FROM zoom_classes zc
                INNER JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
                WHERE zc.id = ? AND zc.status IN ('scheduled', 'ongoing')";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $zoom_class_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Zoom class not found or not accessible']);
    exit;
}

$zoom_class = $check_result->fetch_assoc();
$check_stmt->close();

// If this is the teacher and class is scheduled, update to ongoing
if ($role === 'teacher' && $zoom_class['teacher_id'] === $user_id && $zoom_class['status'] === 'scheduled') {
    $update_query = "UPDATE zoom_classes SET status = 'ongoing', actual_start_time = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $zoom_class_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Check if already joined
$existing_query = "SELECT id FROM zoom_participants 
                  WHERE zoom_class_id = ? AND user_id = ? AND leave_time IS NULL";
$existing_stmt = $conn->prepare($existing_query);
$existing_stmt->bind_param("is", $zoom_class_id, $user_id);
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();

if ($existing_result->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Already joined', 'already_joined' => true]);
    exit;
}
$existing_stmt->close();

// Record participant join
$insert_query = "INSERT INTO zoom_participants (zoom_class_id, user_id, join_time) VALUES (?, ?, NOW())";
$insert_stmt = $conn->prepare($insert_query);
$insert_stmt->bind_param("is", $zoom_class_id, $user_id);

if ($insert_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Joined successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error joining class']);
}

$insert_stmt->close();
$conn->close();
?>
