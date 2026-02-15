<?php
// Disable error reporting for production/API
error_reporting(0);
ini_set('display_errors', 0);

// Start buffering
ob_start();

require_once '../config.php';

header('Content-Type: application/json');

$stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;

if ($stream_id <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid stream ID']);
    exit();
}

// Get subjects for the selected stream
$query = "SELECT s.id, s.name, s.code, ss.id as stream_subject_id
          FROM subjects s
          INNER JOIN stream_subjects ss ON s.id = ss.subject_id
          WHERE ss.stream_id = ? AND ss.status = 1 AND s.status = 1
          ORDER BY s.name";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $stream_id);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'code' => $row['code'],
        'stream_subject_id' => $row['stream_subject_id']
    ];
}

$stmt->close();

ob_clean();
echo json_encode([
    'success' => true,
    'subjects' => $subjects
]);
?>
