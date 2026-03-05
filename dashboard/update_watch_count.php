<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if (($role !== 'teacher' && $role !== 'admin') || empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$recording_id = intval($_POST['recording_id'] ?? 0);
$student_id = trim($_POST['student_id'] ?? '');
$action = trim($_POST['action'] ?? '');

if ($recording_id <= 0 || empty($student_id) || !in_array($action, ['increment', 'decrement'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Verify authorization - Teacher must own the recording or be admin
if ($role === 'teacher') {
    $verify_query = "SELECT r.id, r.watch_limit FROM recordings r
                    INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                    WHERE r.id = ? AND ta.teacher_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("is", $recording_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not own this recording.']);
        $verify_stmt->close();
        exit;
    }
    $recording_info = $verify_result->fetch_assoc();
    $watch_limit = $recording_info['watch_limit'];
    $verify_stmt->close();
} else {
    // Admin case - just get watch_limit
    $query = "SELECT watch_limit FROM recordings WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $recording_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $watch_limit = $row['watch_limit'];
    $stmt->close();
}

if ($action === 'increment') {
    // Check current count
    $count_query = "SELECT COUNT(*) as current_count FROM video_watch_log WHERE recording_id = ? AND student_id = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("is", $recording_id, $student_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $current_count = intval($count_row['current_count']);
    $count_stmt->close();

    if ($watch_limit > 0 && $current_count >= $watch_limit) {
        echo json_encode(['success' => false, 'message' => 'Watch limit reached. Cannot increment further.']);
        exit;
    }

    // Insert new watch log
    $insert_query = "INSERT INTO video_watch_log (recording_id, student_id, watched_at) VALUES (?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("is", $recording_id, $student_id);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Watch count increased']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error increasing watch count']);
    }
    $insert_stmt->close();

} else if ($action === 'decrement') {
    // Get the most recent log ID for this student and recording
    $get_recent_query = "SELECT id FROM video_watch_log WHERE recording_id = ? AND student_id = ? ORDER BY watched_at DESC LIMIT 1";
    $get_recent_stmt = $conn->prepare($get_recent_query);
    $get_recent_stmt->bind_param("is", $recording_id, $student_id);
    $get_recent_stmt->execute();
    $recent_result = $get_recent_stmt->get_result();
    
    if ($recent_result->num_rows > 0) {
        $recent_row = $recent_result->fetch_assoc();
        $log_id = $recent_row['id'];
        
        $delete_query = "DELETE FROM video_watch_log WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $log_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Watch count decreased']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error decreasing watch count']);
        }
        $delete_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'No watch history found for this student']);
    }
    $get_recent_stmt->close();
}
