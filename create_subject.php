<?php
// Disable error reporting for production/API to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$code = trim($_POST['code'] ?? '');
$stream_id = isset($_POST['stream_id']) ? intval($_POST['stream_id']) : 0;

if (empty($name)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Subject name is required']);
    exit;
}

if ($stream_id <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Stream ID is required']);
    exit;
}

// Check if subject already exists
$check_stmt = $conn->prepare("SELECT id FROM subjects WHERE name = ?");
$check_stmt->bind_param("s", $name);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

$subject_id = null;
if ($check_result->num_rows > 0) {
    $existing = $check_result->fetch_assoc();
    $subject_id = $existing['id'];
} else {
    // Create new subject
    $insert_stmt = $conn->prepare("INSERT INTO subjects (name, code, status) VALUES (?, ?, 1)");
    $insert_stmt->bind_param("ss", $name, $code);
    
    if ($insert_stmt->execute()) {
        $subject_id = $conn->insert_id;
    } else {
        $insert_stmt->close();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error creating subject: ' . $conn->error]);
        exit;
    }
    $insert_stmt->close();
}
$check_stmt->close();

// Check if stream_subject relationship exists
$check_ss_stmt = $conn->prepare("SELECT id FROM stream_subjects WHERE stream_id = ? AND subject_id = ?");
$check_ss_stmt->bind_param("ii", $stream_id, $subject_id);
$check_ss_stmt->execute();
$check_ss_result = $check_ss_stmt->get_result();

$stream_subject_id = null;
if ($check_ss_result->num_rows > 0) {
    $existing_ss = $check_ss_result->fetch_assoc();
    $stream_subject_id = $existing_ss['id'];
} else {
    // Create stream_subject relationship
    $insert_ss_stmt = $conn->prepare("INSERT INTO stream_subjects (stream_id, subject_id, status) VALUES (?, ?, 1)");
    $insert_ss_stmt->bind_param("ii", $stream_id, $subject_id);
    
    if ($insert_ss_stmt->execute()) {
        $stream_subject_id = $conn->insert_id;
    } else {
        $insert_ss_stmt->close();
        $check_ss_stmt->close();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error creating stream-subject relationship: ' . $conn->error]);
        exit;
    }
    $insert_ss_stmt->close();
}
$check_ss_stmt->close();

ob_clean();
echo json_encode([
    'success' => true,
    'subject_id' => $subject_id,
    'stream_subject_id' => $stream_subject_id,
    'message' => 'Subject created and linked successfully'
]);














