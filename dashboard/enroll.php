<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Only students can enroll']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['enroll'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$stream_subject_id = isset($_POST['stream_subject_id']) ? intval($_POST['stream_subject_id']) : 0;
$academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');

if ($stream_subject_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid stream subject ID']);
    exit();
}

// Check if already enrolled
$check_query = "SELECT id FROM student_enrollment 
                WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $check_stmt->close();
    echo json_encode(['success' => false, 'message' => 'You are already enrolled in this subject']);
    exit();
}
$check_stmt->close();

// Verify the stream_subject_id exists
$verify_query = "SELECT id FROM stream_subjects WHERE id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("i", $stream_subject_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $verify_stmt->close();
    echo json_encode(['success' => false, 'message' => 'Invalid subject']);
    exit();
}
$verify_stmt->close();

// Insert enrollment
$insert_query = "INSERT INTO student_enrollment (student_id, stream_subject_id, academic_year, status, payment_status, enrolled_date) 
                 VALUES (?, ?, ?, 'active', 'pending', CURDATE())";
$insert_stmt = $conn->prepare($insert_query);
$insert_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);

if ($insert_stmt->execute()) {
    $insert_stmt->close();
    echo json_encode(['success' => true, 'message' => 'Enrollment successful']);
} else {
    $error = $conn->error;
    $insert_stmt->close();
    echo json_encode(['success' => false, 'message' => 'Enrollment failed: ' . $error]);
}
?>





