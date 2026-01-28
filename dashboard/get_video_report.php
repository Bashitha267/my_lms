<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'teacher' || empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$recording_id = intval($_GET['recording_id'] ?? 0);

if ($recording_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
    exit;
}

// Verify teacher owns this recording
$verify_query = "SELECT r.id, r.title FROM recordings r
                INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                WHERE r.id = ? AND ta.teacher_id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("is", $recording_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not own this recording.']);
    $verify_stmt->close();
    exit;
}

$recording_info = $verify_result->fetch_assoc();
$verify_stmt->close();

// Get detailed watch history
$report_query = "SELECT u.user_id, u.first_name, u.second_name, u.profile_picture,
                      COUNT(l.id) as watch_count,
                      MAX(l.watched_at) as last_watched
               FROM video_watch_log l
               INNER JOIN users u ON l.student_id = u.user_id
               WHERE l.recording_id = ?
               GROUP BY u.user_id
               ORDER BY last_watched DESC";

$report_stmt = $conn->prepare($report_query);
$report_stmt->bind_param("i", $recording_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();

$participants = [];
$total_views = 0;

while ($row = $report_result->fetch_assoc()) {
    $total_views += intval($row['watch_count']);
    $participants[] = [
        'student_id' => $row['user_id'],
        'name' => trim($row['first_name'] . ' ' . $row['second_name']),
        'profile_picture' => $row['profile_picture'],
        'watch_count' => intval($row['watch_count']),
        'last_watched' => date('M d, Y h:i A', strtotime($row['last_watched']))
    ];
}

$report_stmt->close();

echo json_encode([
    'success' => true,
    'recording_title' => $recording_info['title'],
    'stats' => [
        'total_watchers' => count($participants),
        'total_views' => $total_views
    ],
    'participants' => $participants
]);
