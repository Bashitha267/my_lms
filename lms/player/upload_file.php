<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if (!$user_id || ($role !== 'student' && $role !== 'teacher')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$recording_id = isset($_POST['recording_id']) ? intval($_POST['recording_id']) : 0;

if ($recording_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
    exit;
}

// Verify recording exists and user has access (handle both regular recordings and live classes)
$check_query = "SELECT r.id FROM recordings r 
                INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                WHERE r.id = ? AND (r.status = 'active' OR (r.is_live = 1 AND r.status IN ('scheduled', 'ongoing', 'ended', 'cancelled')))";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $recording_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Recording not found']);
    exit;
}
$check_stmt->close();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$max_size = 50 * 1024 * 1024; // 50MB

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 50MB limit']);
    exit;
}

// Create upload directory
$upload_dir = '../uploads/recordings/' . $recording_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_name = $file['name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$file_type = $file['type'];
$file_size = $file['size'];
$unique_filename = $user_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
$upload_path = $upload_dir . $unique_filename;

if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

// Save file info to database
$file_path = 'uploads/recordings/' . $recording_id . '/' . $unique_filename;
$insert_query = "INSERT INTO recording_files (recording_id, uploaded_by, file_name, file_path, file_size, file_type, file_extension) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_query);
$insert_stmt->bind_param("isssiss", $recording_id, $user_id, $file_name, $file_path, $file_size, $file_type, $file_ext);

if ($insert_stmt->execute()) {
    $file_id = $conn->insert_id;
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'file' => [
            'id' => $file_id,
            'file_name' => $file_name,
            'file_path' => $file_path,
            'file_size' => $file_size,
            'file_type' => $file_type,
            'file_extension' => $file_ext
        ]
    ]);
} else {
    // Delete uploaded file if database insert fails
    unlink($upload_path);
    echo json_encode(['success' => false, 'message' => 'Failed to save file information']);
}

$insert_stmt->close();
$conn->close();
?>




