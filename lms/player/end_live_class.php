<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$live_class_id = isset($_POST['live_class_id']) ? intval($_POST['live_class_id']) : 0;
$save_as_recording = isset($_POST['save_as_recording']) ? intval($_POST['save_as_recording']) : 0;

if ($live_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid live class ID']);
    exit;
}

// Verify teacher owns this live class
$verify_query = "SELECT lc.id, lc.title, lc.description, lc.youtube_video_id, lc.youtube_url, 
                        lc.teacher_assignment_id, ta.stream_subject_id, ta.academic_year
                 FROM live_classes lc
                 INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
                 WHERE lc.id = ? AND ta.teacher_id = ? AND lc.status = 'ongoing'";

$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("is", $live_class_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $verify_stmt->close();
    echo json_encode(['success' => false, 'message' => 'Live class not found or unauthorized']);
    exit;
}

$live_class_data = $verify_result->fetch_assoc();
$verify_stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Update live class status to 'ended'
    $update_query = "UPDATE live_classes SET status = 'ended', end_time = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $live_class_id);
    $update_stmt->execute();
    $update_stmt->close();

    $recording_id = null;
    $recording_url = null;

    // If save as recording, create recording entry
    if ($save_as_recording && !empty($live_class_data['youtube_video_id'])) {
        // Extract thumbnail URL
        $thumbnail_url = 'https://img.youtube.com/vi/' . $live_class_data['youtube_video_id'] . '/hqdefault.jpg';
        
        // Create recording
        $recording_query = "INSERT INTO recordings (teacher_assignment_id, title, description, youtube_video_id, youtube_url, thumbnail_url, status, free_video, watch_limit, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, 'active', 0, 0, NOW())";
        $recording_stmt = $conn->prepare($recording_query);
        $recording_stmt->bind_param("isssss", 
            $live_class_data['teacher_assignment_id'],
            $live_class_data['title'],
            $live_class_data['description'],
            $live_class_data['youtube_video_id'],
            $live_class_data['youtube_url'],
            $thumbnail_url
        );
        
        if ($recording_stmt->execute()) {
            $recording_id = $conn->insert_id;
            
            // Update live class with recording ID
            $update_recording_query = "UPDATE live_classes SET recording_id = ? WHERE id = ?";
            $update_recording_stmt = $conn->prepare($update_recording_query);
            $update_recording_stmt->bind_param("ii", $recording_id, $live_class_id);
            $update_recording_stmt->execute();
            $update_recording_stmt->close();
            
            $recording_url = '../player/player.php?id=' . $recording_id . 
                           '&stream_subject_id=' . $live_class_data['stream_subject_id'] . 
                           '&academic_year=' . $live_class_data['academic_year'];
        }
        $recording_stmt->close();
    }

    // Mark all participants as left
    $mark_left_query = "UPDATE live_class_participants SET left_at = NOW() WHERE live_class_id = ? AND left_at IS NULL";
    $mark_left_stmt = $conn->prepare($mark_left_query);
    $mark_left_stmt->bind_param("i", $live_class_id);
    $mark_left_stmt->execute();
    $mark_left_stmt->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Live class ended successfully',
        'recording_id' => $recording_id,
        'recording_url' => $recording_url
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}


