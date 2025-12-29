<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

try {
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

    // Verify teacher owns this live class and it's scheduled
    $verify_query = "SELECT lc.id, lc.status FROM live_classes lc
                     INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
                     WHERE lc.id = ? AND ta.teacher_id = ? AND lc.status = 'scheduled'";

    $verify_stmt = $conn->prepare($verify_query);
    if (!$verify_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $verify_stmt->bind_param("is", $live_class_id, $user_id);
    
    if (!$verify_stmt->execute()) {
        $verify_stmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        $verify_stmt->close();
        echo json_encode(['success' => false, 'message' => 'Live class not found or unauthorized']);
        exit;
    }
    $verify_stmt->close();

    // Update live class status to 'ongoing'
    $update_query = "UPDATE live_classes SET status = 'ongoing', actual_start_time = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $update_stmt->bind_param("i", $live_class_id);

    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Live class started successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error starting live class: ' . $conn->error]);
    }

    $update_stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}


