<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Only students can track views
if ($role !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recording_id = intval($_POST['recording_id'] ?? 0);
    
    if ($recording_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
        exit;
    }
    
    // Verify recording exists and get watch limit
    $query = "SELECT r.id, r.watch_limit, ta.stream_subject_id, ta.academic_year
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              WHERE r.id = ? AND r.status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $recording_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Recording not found']);
        $stmt->close();
        exit;
    }
    
    $recording = $result->fetch_assoc();
    $watch_limit = intval($recording['watch_limit'] ?? 3);
    $stream_subject_id = $recording['stream_subject_id'];
    $academic_year = $recording['academic_year'];
    $stmt->close();
    
    // Check if student is enrolled
    $enroll_query = "SELECT id FROM student_enrollment 
                     WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'
                     LIMIT 1";
    $enroll_stmt = $conn->prepare($enroll_query);
    $enroll_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
    $enroll_stmt->execute();
    $enroll_result = $enroll_stmt->get_result();
    
    if ($enroll_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled']);
        $enroll_stmt->close();
        exit;
    }
    $enroll_stmt->close();
    
    // Check current watch count
    if ($watch_limit > 0) {
        $count_query = "SELECT COUNT(*) as watch_count FROM video_watch_log 
                       WHERE recording_id = ? AND student_id = ?";
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param("is", $recording_id, $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $current_count = intval($count_row['watch_count']);
        $count_stmt->close();
        
        // If limit exceeded, don't allow tracking
        if ($current_count >= $watch_limit) {
            echo json_encode(['success' => false, 'message' => 'Watch limit exceeded', 'watch_count' => $current_count, 'watch_limit' => $watch_limit]);
            exit;
        }
    }
    
    // Insert watch log
    $insert_query = "INSERT INTO video_watch_log (recording_id, student_id) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("is", $recording_id, $user_id);
    
    if ($insert_stmt->execute()) {
        // Get updated count
        $new_count_query = "SELECT COUNT(*) as watch_count FROM video_watch_log 
                           WHERE recording_id = ? AND student_id = ?";
        $new_count_stmt = $conn->prepare($new_count_query);
        $new_count_stmt->bind_param("is", $recording_id, $user_id);
        $new_count_stmt->execute();
        $new_count_result = $new_count_stmt->get_result();
        $new_count_row = $new_count_result->fetch_assoc();
        $new_count = intval($new_count_row['watch_count']);
        $new_count_stmt->close();
        
        $remaining = $watch_limit > 0 ? max(0, $watch_limit - $new_count) : -1;
        
        echo json_encode([
            'success' => true, 
            'message' => 'View tracked',
            'watch_count' => $new_count,
            'watch_limit' => $watch_limit,
            'remaining' => $remaining
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error tracking view']);
    }
    
    $insert_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

