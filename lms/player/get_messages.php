<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Only students and teachers can get messages
if ($role !== 'student' && $role !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recording_id = intval($_GET['recording_id'] ?? 0);
    $last_message_id = intval($_GET['last_message_id'] ?? 0); // For polling - get messages after this ID
    
    if ($recording_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
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
    
    // Get messages - if last_message_id provided, get only new messages (for polling)
    // Otherwise get last 50 messages
    if ($last_message_id > 0) {
        $messages_query = "SELECT cm.id, cm.recording_id, cm.sender_id, cm.sender_role, cm.message, cm.status, cm.created_at,
                                  u.first_name, u.second_name, u.profile_picture
                           FROM chat_messages cm
                           INNER JOIN users u ON cm.sender_id = u.user_id
                           WHERE cm.recording_id = ? AND cm.id > ?
                           ORDER BY cm.created_at ASC
                           LIMIT 100";
        $messages_stmt = $conn->prepare($messages_query);
        $messages_stmt->bind_param("ii", $recording_id, $last_message_id);
    } else {
        $messages_query = "SELECT cm.id, cm.recording_id, cm.sender_id, cm.sender_role, cm.message, cm.status, cm.created_at,
                                  u.first_name, u.second_name, u.profile_picture
                           FROM chat_messages cm
                           INNER JOIN users u ON cm.sender_id = u.user_id
                           WHERE cm.recording_id = ?
                           ORDER BY cm.created_at DESC
                           LIMIT 50";
        $messages_stmt = $conn->prepare($messages_query);
        $messages_stmt->bind_param("i", $recording_id);
    }
    
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();
    
    $messages = [];
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'recording_id' => $row['recording_id'],
            'sender_id' => $row['sender_id'],
            'sender_role' => $row['sender_role'],
            'message' => $row['message'],
            'created_at' => $row['created_at'],
            'sender_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? '')),
            'sender_avatar' => $row['profile_picture'] ?? '',
            'is_own_message' => ($row['sender_id'] === $user_id)
        ];
    }
    
    $messages_stmt->close();
    
    // Reverse if we got messages in DESC order (for initial load)
    if ($last_message_id == 0) {
        $messages = array_reverse($messages);
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

