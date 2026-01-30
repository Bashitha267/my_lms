<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$recording_id = isset($_GET['recording_id']) ? intval($_GET['recording_id']) : 0;

if ($recording_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
    exit;
}

// Build query based on user role
if ($role === 'teacher') {
    // Teachers can see all files
    $query = "SELECT rf.id, rf.file_name, rf.file_path, rf.file_size, rf.file_type, rf.file_extension, 
                     rf.upload_date, rf.uploaded_by,
                     u.first_name, u.second_name, u.role
              FROM recording_files rf
              INNER JOIN users u ON rf.uploaded_by = u.user_id
              WHERE rf.recording_id = ? AND rf.status = 1
              ORDER BY rf.upload_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $recording_id);
} else {
    // Students can see only their own files and teacher files
    $query = "SELECT rf.id, rf.file_name, rf.file_path, rf.file_size, rf.file_type, rf.file_extension, 
                     rf.upload_date, rf.uploaded_by,
                     u.first_name, u.second_name, u.role
              FROM recording_files rf
              INNER JOIN users u ON rf.uploaded_by = u.user_id
              WHERE rf.recording_id = ? AND rf.status = 1 
                AND (rf.uploaded_by = ? OR u.role = 'teacher')
              ORDER BY rf.upload_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $recording_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$files = [];
while ($row = $result->fetch_assoc()) {
    $uploader_name = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
    $files[] = [
        'id' => $row['id'],
        'file_name' => $row['file_name'],
        'file_path' => $row['file_path'],
        'file_size' => $row['file_size'],
        'file_type' => $row['file_type'],
        'file_extension' => $row['file_extension'],
        'upload_date' => $row['upload_date'],
        'uploaded_by' => $row['uploaded_by'],
        'uploader_name' => $uploader_name,
        'uploader_role' => $row['role'],
        'is_own_file' => $row['uploaded_by'] === $user_id
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'files' => $files]);
?>















