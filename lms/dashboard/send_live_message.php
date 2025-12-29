<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'student' && $role !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $live_class_id = intval($_POST['live_class_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($live_class_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid live class ID']);
        exit;
    }
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit;
    }
    
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message is too long (max 2000 characters)']);
        exit;
    }
    
    // Verify live class exists and user has access
    $query = "SELECT lc.id, lc.status, ta.stream_subject_id, ta.academic_year, ta.teacher_id
              FROM live_classes lc
              INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
              WHERE lc.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $live_class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Live class not found']);
        $stmt->close();
        exit;
    }
    
    $live_class = $result->fetch_assoc();
    $stream_subject_id = $live_class['stream_subject_id'];
    $academic_year = $live_class['academic_year'];
    $stmt->close();
    
    // Verify access
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
        $has_access = ($live_class['teacher_id'] === $user_id);
    }
    
    if (!$has_access) {
        echo json_encode(['success' => false, 'message' => 'You do not have access to this live class']);
        exit;
    }
    
    // Ensure live_class_chat table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'live_class_chat'");
    if ($table_check->num_rows === 0) {
        $create_table = "CREATE TABLE IF NOT EXISTS live_class_chat (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            live_class_id INT(11) NOT NULL,
            sender_id VARCHAR(20) NOT NULL,
            sender_role ENUM('student', 'teacher') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_live_class_id (live_class_id),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (live_class_id) REFERENCES live_classes(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($create_table);
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
    $insert_query = "INSERT INTO live_class_chat (live_class_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("isss", $live_class_id, $user_id, $sender_role_db, $message);
    
    if ($insert_stmt->execute()) {
        $message_id = $insert_stmt->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Message sent',
            'data' => [
                'id' => $message_id,
                'live_class_id' => $live_class_id,
                'sender_id' => $user_id,
                'sender_role' => $sender_role_db,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
                'sender_name' => trim(($user_info['first_name'] ?? '') . ' ' . ($user_info['second_name'] ?? '')),
                'sender_avatar' => $user_info['profile_picture'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $conn->error]);
    }
    
    $insert_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

