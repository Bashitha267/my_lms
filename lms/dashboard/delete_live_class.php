<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$live_class_id = isset($_POST['live_class_id']) ? intval($_POST['live_class_id']) : 0;

if ($live_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid live class ID']);
    exit;
}

// Verify teacher owns this live class (can only delete if not ongoing)
$verify_query = "SELECT id FROM live_classes lc
                 INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
                 WHERE lc.id = ? AND ta.teacher_id = ? AND lc.status != 'ongoing'";

$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("is", $live_class_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $verify_stmt->close();
    echo json_encode(['success' => false, 'message' => 'Live class not found, unauthorized, or cannot delete ongoing class']);
    exit;
}
$verify_stmt->close();

// Delete live class (cascade will delete participants)
$delete_query = "DELETE FROM live_classes WHERE id = ?";
$delete_stmt = $conn->prepare($delete_query);
$delete_stmt->bind_param("i", $live_class_id);

if ($delete_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Live class deleted successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting live class: ' . $conn->error]);
}

$delete_stmt->close();


