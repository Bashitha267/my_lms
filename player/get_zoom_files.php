<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$zoom_class_id = isset($_GET['zoom_class_id']) ? intval($_GET['zoom_class_id']) : 0;

if ($zoom_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom class ID']);
    exit;
}

// Get files separated by uploader role
$query = "SELECT zcf.*, u.first_name, u.second_name, u.role
          FROM zoom_class_files zcf
          INNER JOIN users u ON zcf.uploader_id = u.user_id
          WHERE zcf.zoom_class_id = ?
          ORDER BY zcf.uploaded_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $zoom_class_id);
$stmt->execute();
$result = $stmt->get_result();

$downloads = []; // Teacher files
$uploads = [];   // Student files

while ($row = $result->fetch_assoc()) {
    $file_data = [
        'id' => $row['id'],
        'file_name' => $row['file_name'],
        'file_path' => $row['file_path'],
        'file_size' => formatFileSize($row['file_size']),
        'file_type' => $row['file_type'],
        'uploader_name' => trim($row['first_name'] . ' ' . $row['second_name']),
        'uploaded_at' => $row['uploaded_at']
    ];
    
    if ($row['role'] === 'teacher') {
        $downloads[] = $file_data;
    } else {
        $uploads[] = $file_data;
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'downloads' => $downloads,
    'uploads' => $uploads
]);

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>
