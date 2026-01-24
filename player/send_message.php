<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Only students and teachers can send messages
if ($role !== 'student' && $role !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recording_id = intval($_POST['recording_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($recording_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
        exit;
    }
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit;
    }
    
    // Limit message length
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message is too long (max 2000 characters)']);
        exit;
    }
    
    // Verify recording exists and user has access (handle both regular recordings and live classes)
    $query = "SELECT r.id, r.status, r.is_live, ta.stream_subject_id, ta.academic_year
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              WHERE r.id = ? AND (r.status = 'active' OR (r.is_live = 1 AND r.status IN ('scheduled', 'ongoing', 'ended', 'cancelled')))";
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
    $stream_subject_id = $recording['stream_subject_id'];
    $academic_year = $recording['academic_year'];
    $stmt->close();
    
    // Verify access: For students, check enrollment; for teachers, check assignment
    $has_access = false;
    if ($role === 'student') {
        $enroll_query = "SELECT id FROM student_enrollment 
                       WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'
                       LIMIT 1";
        $enroll_stmt = $conn->prepare($enroll_query);
        $enroll_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $enroll_stmt->execute();
        $enroll_result = $enroll_stmt->get_result();
        $has_access = $enroll_result->num_rows > 0;
        $enroll_stmt->close();
    } else if ($role === 'teacher') {
        $teacher_query = "SELECT id FROM teacher_assignments 
                        WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'
                        LIMIT 1";
        $teacher_stmt = $conn->prepare($teacher_query);
        $teacher_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $teacher_stmt->execute();
        $teacher_result = $teacher_stmt->get_result();
        $has_access = $teacher_result->num_rows > 0;
        $teacher_stmt->close();
    }
    
    if (!$has_access) {
        echo json_encode(['success' => false, 'message' => 'You do not have access to this recording']);
        exit;
    }
    
    // Get sender info
    $user_query = "SELECT first_name, second_name, profile_picture FROM users WHERE user_id = ? LIMIT 1";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("s", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_info = $user_result->fetch_assoc();
    $user_stmt->close();
    
    // Insert message
    $sender_role_db = $role === 'student' ? 'student' : 'teacher';
    $insert_query = "INSERT INTO chat_messages (recording_id, sender_id, sender_role, message, status) VALUES (?, ?, ?, ?, 'sent')";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("isss", $recording_id, $user_id, $sender_role_db, $message);
    
    if ($insert_stmt->execute()) {
        $message_id = $insert_stmt->insert_id;
        
        // Get the inserted message with full details
        $get_query = "SELECT cm.id, cm.recording_id, cm.sender_id, cm.sender_role, cm.message, cm.status, cm.created_at,
                             u.first_name, u.second_name, u.profile_picture
                      FROM chat_messages cm
                      INNER JOIN users u ON cm.sender_id = u.user_id
                      WHERE cm.id = ? LIMIT 1";
        $get_stmt = $conn->prepare($get_query);
        $get_stmt->bind_param("i", $message_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        $message_data = $get_result->fetch_assoc();
        $get_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Message sent',
            'data' => [
                'id' => $message_data['id'],
                'recording_id' => $message_data['recording_id'],
                'sender_id' => $message_data['sender_id'],
                'sender_role' => $message_data['sender_role'],
                'message' => $message_data['message'],
                'created_at' => $message_data['created_at'],
                'sender_name' => trim(($message_data['first_name'] ?? '') . ' ' . ($message_data['second_name'] ?? '')),
                'sender_avatar' => $message_data['profile_picture'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error sending message']);
    }
    
    $insert_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

