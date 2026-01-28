<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$zoom_class_id = isset($_GET['zoom_class_id']) ? intval($_GET['zoom_class_id']) : 0;

if ($zoom_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom class ID']);
    exit;
}

// Get participants
$query = "SELECT zp.*, u.first_name, u.second_name, u.profile_picture,
                 CASE 
                     WHEN zp.leave_time IS NULL THEN CONCAT(TIMESTAMPDIFF(MINUTE, zp.join_time, NOW()), ' min')
                     ELSE CONCAT(zp.duration_minutes, ' min')
                 END as duration_display
          FROM zoom_participants zp
          INNER JOIN users u ON zp.user_id = u.user_id
          WHERE zp.zoom_class_id = ?
          ORDER BY zp.join_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $zoom_class_id);
$stmt->execute();
$result = $stmt->get_result();

$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'name' => trim($row['first_name'] . ' ' . $row['second_name']),
        'profile_picture' => $row['profile_picture'],
        'join_time' => $row['join_time'],
        'leave_time' => $row['leave_time'],
        'duration' => $row['duration_display'],
        'is_active' => $row['leave_time'] === null
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'participants' => $participants
]);
?>
