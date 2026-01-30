<?php
// Disable error reporting for production/API to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name = trim($_POST['name'] ?? '');

if (empty($name)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Stream name is required']);
    exit;
}

// Check if stream already exists
$check_stmt = $conn->prepare("SELECT id FROM streams WHERE name = ?");
$check_stmt->bind_param("s", $name);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $existing = $check_result->fetch_assoc();
    $check_stmt->close();
    ob_clean();
    echo json_encode(['success' => true, 'stream_id' => $existing['id'], 'message' => 'Stream already exists']);
    exit;
}
$check_stmt->close();

// Create new stream
$insert_stmt = $conn->prepare("INSERT INTO streams (name, status) VALUES (?, 1)");
$insert_stmt->bind_param("s", $name);

if ($insert_stmt->execute()) {
    $stream_id = $conn->insert_id;
    ob_clean();
    echo json_encode(['success' => true, 'stream_id' => $stream_id, 'message' => 'Stream created successfully']);
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error creating stream: ' . $conn->error]);
}

$insert_stmt->close();















