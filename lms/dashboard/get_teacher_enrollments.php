<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$teacher_id = isset($_GET['teacher_id']) ? trim($_GET['teacher_id']) : '';

if (empty($teacher_id)) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
    exit();
}

// Get all active teacher assignments for this teacher
$query = "SELECT ta.id as teacher_assignment_id, ta.stream_subject_id, ta.academic_year, ta.batch_name, ta.status,
                 s.name as stream_name, sub.name as subject_name, sub.code as subject_code
          FROM teacher_assignments ta
          INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
          INNER JOIN streams s ON ss.stream_id = s.id
          INNER JOIN subjects sub ON ss.subject_id = sub.id
          WHERE ta.teacher_id = ? AND ta.status = 'active'
          ORDER BY ta.academic_year DESC, s.name, sub.name";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

$enrollments = [];
while ($row = $result->fetch_assoc()) {
    // Check if student is already enrolled
    $check_query = "SELECT id FROM student_enrollment 
                    WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("sii", $user_id, $row['stream_subject_id'], $row['academic_year']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $is_enrolled = $check_result->num_rows > 0;
    $check_stmt->close();
    
    // Add enrollment flag to row
    $row['is_enrolled'] = $is_enrolled;
    $enrollments[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'enrollments' => $enrollments
]);
?>

