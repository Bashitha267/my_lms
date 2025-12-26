<?php
require_once '../check_session.php';

// Verify user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

require_once '../config.php';

header('Content-Type: application/json');

$stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($stream_id <= 0 || $subject_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid stream or subject ID']);
    exit();
}

// Get stream_subject_id for the given stream-subject combination
$query = "SELECT id as stream_subject_id FROM stream_subjects WHERE stream_id = ? AND subject_id = ? AND status = 1 LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $stream_id, $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'stream_subject_id' => $row['stream_subject_id']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Stream-subject combination not found'
    ]);
}

$stmt->close();
?>

