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

if ($zoom_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom class ID']);
    exit;
}

// Find active participant record
$query = "SELECT id, join_time FROM zoom_participants 
          WHERE zoom_class_id = ? AND user_id = ? AND leave_time IS NULL
          ORDER BY join_time DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $zoom_class_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $participant = $result->fetch_assoc();
    $participant_id = $participant['id'];
    $join_time = $participant['join_time'];
    
    // Calculate duration in minutes
    $duration_query = "UPDATE zoom_participants 
                      SET leave_time = NOW(), 
                          duration_minutes = TIMESTAMPDIFF(MINUTE, join_time, NOW())
                      WHERE id = ?";
    $duration_stmt = $conn->prepare($duration_query);
    $duration_stmt->bind_param("i", $participant_id);
    $duration_stmt->execute();
    $duration_stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Left successfully']);
} else {
    echo json_encode(['success' => true, 'message' => 'No active session found']);
}

$stmt->close();
$conn->close();
?>
