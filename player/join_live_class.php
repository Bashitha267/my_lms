<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Only students can join live classes']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recording_id = intval($_POST['recording_id'] ?? 0);
    
    if ($recording_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
        exit;
    }
    
    // Verify live class exists and is ongoing
    $query = "SELECT r.id, r.status, ta.stream_subject_id, ta.academic_year
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              WHERE r.id = ? AND r.is_live = 1 AND r.status = 'ongoing'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $recording_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Live class not found or not ongoing']);
        $stmt->close();
        exit;
    }
    
    $live_class = $result->fetch_assoc();
    $stmt->close();
    
    // Verify student is enrolled
    $enroll_query = "SELECT id FROM student_enrollment 
                   WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'
                   LIMIT 1";
    $enroll_stmt = $conn->prepare($enroll_query);
    $enroll_stmt->bind_param("sii", $user_id, $live_class['stream_subject_id'], $live_class['academic_year']);
    $enroll_stmt->execute();
    $enroll_result = $enroll_stmt->get_result();
    
    if ($enroll_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this subject']);
        $enroll_stmt->close();
        exit;
    }
    $enroll_stmt->close();
    
    // Check if already joined (and not left)
    $check_query = "SELECT id, left_at FROM live_class_participants 
                   WHERE recording_id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $recording_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $participant = $check_result->fetch_assoc();
        if (empty($participant['left_at'])) {
            // Already joined and still online
            echo json_encode(['success' => true, 'message' => 'Already joined', 'already_joined' => true]);
        } else {
            // Re-join - update left_at to NULL
            $update_query = "UPDATE live_class_participants SET joined_at = NOW(), left_at = NULL WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $participant['id']);
            $update_stmt->execute();
            $update_stmt->close();
            echo json_encode(['success' => true, 'message' => 'Rejoined live class']);
        }
    } else {
        // First time joining
        $insert_query = "INSERT INTO live_class_participants (recording_id, student_id, joined_at) VALUES (?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("is", $recording_id, $user_id);
        
        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Joined live class']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error joining live class']);
        }
        $insert_stmt->close();
    }
    
    $check_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

