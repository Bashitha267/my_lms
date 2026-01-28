<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$zoom_class_id = isset($_POST['zoom_class_id']) ? intval($_POST['zoom_class_id']) : 0;

if ($zoom_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom class ID']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$file_name = basename($file['name']);
$file_size = $file['size'];
$file_tmp = $file['tmp_name'];
$file_type = $file['type'];

// Max file size: 50MB
$max_size = 50 * 1024 * 1024;
if ($file_size > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 50MB limit']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/zoom/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
$unique_name = uniqid() . '_' . time() . '.' . $file_ext;
$file_path = $upload_dir . $unique_name;

// Move uploaded file
if (move_uploaded_file($file_tmp, $file_path)) {
    // Insert file record
    $insert_query = "INSERT INTO zoom_class_files (zoom_class_id, uploader_id, file_name, file_path, file_size, file_type) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("isssss", $zoom_class_id, $user_id, $file_name, $unique_name, $file_size, $file_type);
    
    if ($insert_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_id' => $insert_stmt->insert_id
        ]);
    } else {
        // Delete uploaded file if database insert fails
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'Error saving file information']);
    }
    $insert_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error moving uploaded file']);
}

$conn->close();
?>
