<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

header('Content-Type: application/json');

// Only allow guests (no user_id, but has guest_name)
$guest_name  = $_SESSION['guest_name']  ?? '';
$guest_phone = $_SESSION['guest_phone'] ?? '';

if (empty($guest_name)) {
    echo json_encode(['success' => false, 'message' => 'Not a valid guest session']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$recording_id = intval($_POST['recording_id'] ?? 0);

if ($recording_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
    exit;
}

// Verify it's a free live class
$query = "SELECT id, free_video, is_live FROM recordings WHERE id = ? AND is_live = 1 AND free_video = 1";
$stmt  = $conn->prepare($query);
$stmt->bind_param("i", $recording_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Not a free live class']);
    $stmt->close();
    exit;
}
$stmt->close();

// Check if columns exist in live_class_participants; if not, add them
$col_check = $conn->query("SHOW COLUMNS FROM live_class_participants LIKE 'guest_name'");
if ($col_check->num_rows === 0) {
    // Add guest columns
    $conn->query("ALTER TABLE live_class_participants 
                  ADD COLUMN guest_name VARCHAR(100) NULL DEFAULT NULL AFTER left_at,
                  ADD COLUMN guest_phone VARCHAR(30) NULL DEFAULT NULL AFTER guest_name");
    // Also make student_id nullable for guests
    $conn->query("ALTER TABLE live_class_participants MODIFY student_id VARCHAR(50) NULL DEFAULT NULL");
}

// Check if this guest already joined (same name + phone)
$check_query = "SELECT id, left_at FROM live_class_participants 
                WHERE recording_id = ? AND guest_name = ? AND student_id IS NULL";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("is", $recording_id, $guest_name);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $existing = $check_result->fetch_assoc();
    // Update join time (re-join)
    $upd = $conn->prepare("UPDATE live_class_participants SET joined_at = NOW(), left_at = NULL WHERE id = ?");
    $upd->bind_param("i", $existing['id']);
    $upd->execute();
    $upd->close();
    echo json_encode(['success' => true, 'message' => 'Re-joined as guest']);
} else {
    $insert = $conn->prepare("INSERT INTO live_class_participants (recording_id, student_id, guest_name, guest_phone, joined_at) VALUES (?, NULL, ?, ?, NOW())");
    $insert->bind_param("iss", $recording_id, $guest_name, $guest_phone);
    if ($insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Guest joined']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error recording join: ' . $conn->error]);
    }
    $insert->close();
}

$check_stmt->close();
?>
