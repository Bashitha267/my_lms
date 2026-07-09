<?php
require_once '../check_session.php';

// Verify user is admin
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$stmt = $conn->prepare("SELECT user_id, email, role, first_name, second_name, mobile_number, whatsapp_number, status, approved, registering_date, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$education = [];
$assignments = [];
$students_count = 0;
$classes_count = 0;

if ($user['role'] === 'teacher') {
    // Fetch stats
    $s_res = $conn->query("SELECT COUNT(DISTINCT se.student_id) FROM student_enrollment se JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id AND se.academic_year = ta.academic_year WHERE ta.teacher_id = '$user_id' AND se.status = 'active'");
    $students_count = ($s_res) ? intval($s_res->fetch_row()[0]) : 0;
    
    $c_res = $conn->query("SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id = '$user_id' AND status = 'active'");
    $classes_count = ($c_res) ? intval($c_res->fetch_row()[0]) : 0;

    // Fetch education details
    $stmt = $conn->prepare("SELECT qualification, institution, year_obtained, grade_or_class FROM teacher_education WHERE teacher_id = ? ORDER BY year_obtained DESC");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $education[] = $row;
    }
    $stmt->close();

    // Fetch assignments
    $stmt = $conn->prepare("
        SELECT ta.id, ta.academic_year, ta.commission_rate, s.name as stream_name, sub.name as subject_name 
        FROM teacher_assignments ta
        JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
        JOIN streams s ON ss.stream_id = s.id
        JOIN subjects sub ON ss.subject_id = sub.id
        WHERE ta.teacher_id = ?
        ORDER BY ta.academic_year DESC, s.name ASC, sub.name ASC
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'user' => $user,
    'students_count' => $students_count,
    'classes_count' => $classes_count,
    'education' => $education,
    'assignments' => $assignments
]);
?>
