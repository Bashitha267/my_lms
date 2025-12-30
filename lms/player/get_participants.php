<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

$recording_id = intval($_GET['recording_id'] ?? 0);

if ($recording_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
    exit;
}

// Verify recording exists and is a live class
$query = "SELECT r.id, r.is_live, r.status, ta.stream_subject_id, ta.academic_year, ta.teacher_id
          FROM recordings r
          INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
          WHERE r.id = ? AND r.is_live = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $recording_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Live class not found']);
    $stmt->close();
    exit;
}

$live_class = $result->fetch_assoc();
$stmt->close();

// Verify access
$has_access = false;
if ($role === 'teacher' && $live_class['teacher_id'] === $user_id) {
    $has_access = true;
} elseif ($role === 'student') {
    $enroll_query = "SELECT id FROM student_enrollment 
                   WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'
                   LIMIT 1";
    $enroll_stmt = $conn->prepare($enroll_query);
    $enroll_stmt->bind_param("sii", $user_id, $live_class['stream_subject_id'], $live_class['academic_year']);
    $enroll_stmt->execute();
    $enroll_result = $enroll_stmt->get_result();
    $has_access = $enroll_result->num_rows > 0;
    $enroll_stmt->close();
}

if (!$has_access) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get participants
$participants_query = "SELECT lcp.id, lcp.student_id, lcp.joined_at, lcp.left_at,
                              u.first_name, u.second_name, u.profile_picture
                       FROM live_class_participants lcp
                       INNER JOIN users u ON lcp.student_id = u.user_id
                       WHERE lcp.recording_id = ?
                       ORDER BY lcp.joined_at DESC";
$participants_stmt = $conn->prepare($participants_query);
$participants_stmt->bind_param("i", $recording_id);
$participants_stmt->execute();
$participants_result = $participants_stmt->get_result();

$participants = [];
while ($row = $participants_result->fetch_assoc()) {
    $participants[] = [
        'id' => $row['id'],
        'student_id' => $row['student_id'],
        'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? '')),
        'profile_picture' => $row['profile_picture'] ?? '',
        'joined_at' => $row['joined_at'],
        'left_at' => $row['left_at'],
        'is_online' => empty($row['left_at'])
    ];
}

$participants_stmt->close();

echo json_encode([
    'success' => true,
    'participants' => $participants,
    'count' => count($participants),
    'online_count' => count(array_filter($participants, function($p) { return $p['is_online']; }))
]);
?>
