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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $live_class_id = intval($_GET['live_class_id'] ?? 0);
    $last_message_id = intval($_GET['last_message_id'] ?? 0);
    
    if ($live_class_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid live class ID']);
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
    
    // Get messages from live_class_chat table (we'll create this)
    // For now, using a simple approach with a JSON field or separate table
    // Let's use a simple approach: store in a text field or create live_class_chat table
    // For simplicity, let's check if live_class_chat table exists, if not, we'll use a workaround
    
    // Check if live_class_chat table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'live_class_chat'");
    if ($table_check->num_rows === 0) {
        // Create table if it doesn't exist
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
    
    // Get messages
    if ($last_message_id > 0) {
        $messages_query = "SELECT lcc.id, lcc.live_class_id, lcc.sender_id, lcc.sender_role, lcc.message, lcc.created_at,
                                  u.first_name, u.second_name, u.profile_picture
                           FROM live_class_chat lcc
                           INNER JOIN users u ON lcc.sender_id = u.user_id
                           WHERE lcc.live_class_id = ? AND lcc.id > ?
                           ORDER BY lcc.created_at ASC
                           LIMIT 100";
        $messages_stmt = $conn->prepare($messages_query);
        $messages_stmt->bind_param("ii", $live_class_id, $last_message_id);
    } else {
        $messages_query = "SELECT lcc.id, lcc.live_class_id, lcc.sender_id, lcc.sender_role, lcc.message, lcc.created_at,
                                  u.first_name, u.second_name, u.profile_picture
                           FROM live_class_chat lcc
                           INNER JOIN users u ON lcc.sender_id = u.user_id
                           WHERE lcc.live_class_id = ?
                           ORDER BY lcc.created_at DESC
                           LIMIT 50";
        $messages_stmt = $conn->prepare($messages_query);
        $messages_stmt->bind_param("i", $live_class_id);
    }
    
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();
    
    $messages = [];
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'live_class_id' => $row['live_class_id'],
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
    
    // Reverse if we got messages in DESC order
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

