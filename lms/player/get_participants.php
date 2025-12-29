<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$live_class_id = isset($_GET['live_class_id']) ? intval($_GET['live_class_id']) : 0;

if ($live_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid live class ID']);
    exit;
}

// Get participants for this live class (currently in class - left_at is NULL)
$query = "SELECT lcp.student_id, u.first_name, u.second_name, u.profile_picture, u.role,
                 CONCAT(u.first_name, ' ', u.second_name) as name
          FROM live_class_participants lcp
          INNER JOIN users u ON lcp.student_id = u.user_id
          WHERE lcp.live_class_id = ? AND lcp.left_at IS NULL
          ORDER BY lcp.joined_at ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $live_class_id);
$stmt->execute();
$result = $stmt->get_result();

$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = [
        'student_id' => $row['student_id'],
        'name' => trim($row['name']),
        'profile_picture' => $row['profile_picture'],
        'role' => $row['role']
    ];
}

$stmt->close();

// Also include the teacher
$teacher_query = "SELECT u.user_id, u.first_name, u.second_name, u.profile_picture, u.role,
                         CONCAT(u.first_name, ' ', u.second_name) as name
                  FROM live_classes lc
                  INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
                  INNER JOIN users u ON ta.teacher_id = u.user_id
                  WHERE lc.id = ?";

$teacher_stmt = $conn->prepare($teacher_query);
$teacher_stmt->bind_param("i", $live_class_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();

if ($teacher_row = $teacher_result->fetch_assoc()) {
    $participants[] = [
        'student_id' => $teacher_row['user_id'],
        'name' => trim($teacher_row['name']),
        'profile_picture' => $teacher_row['profile_picture'],
        'role' => $teacher_row['role']
    ];
}

$teacher_stmt->close();

echo json_encode(['success' => true, 'participants' => $participants]);


