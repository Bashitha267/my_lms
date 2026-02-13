<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$zoom_class_id = isset($_POST['zoom_class_id']) ? intval($_POST['zoom_class_id']) : 0;

if ($zoom_class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom class ID']);
    exit;
}

// Check if class exists and is accessible
$check_query = "SELECT zc.*, ta.teacher_id 
                FROM zoom_classes zc
                INNER JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
                WHERE zc.id = ? AND zc.status IN ('scheduled', 'ongoing')";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $zoom_class_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Zoom class not found or not accessible']);
    exit;
}

$zoom_class = $check_result->fetch_assoc();
$check_stmt->close();

// If this is the teacher and class is scheduled, update to ongoing
if ($role === 'teacher' && $zoom_class['teacher_id'] === $user_id && $zoom_class['status'] === 'scheduled') {
    $update_query = "UPDATE zoom_classes SET status = 'ongoing', actual_start_time = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $zoom_class_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Check if already joined
$existing_query = "SELECT id FROM zoom_participants 
                  WHERE zoom_class_id = ? AND user_id = ? AND leave_time IS NULL";
$existing_stmt = $conn->prepare($existing_query);
$existing_stmt->bind_param("is", $zoom_class_id, $user_id);
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();

if ($existing_result->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Already joined', 'already_joined' => true]);
    exit;
}
$existing_stmt->close();

// Record participant join
$insert_query = "INSERT INTO zoom_participants (zoom_class_id, user_id, join_time) VALUES (?, ?, NOW())";
$insert_stmt = $conn->prepare($insert_query);
$insert_stmt->bind_param("is", $zoom_class_id, $user_id);

if ($insert_stmt->execute()) {
    // Zoom Notification for Students
    if ($role === 'student' && !empty($user_id)) {
        sendZoomJoinNotifications($conn, $zoom_class_id, $user_id);
    }
    echo json_encode(['success' => true, 'message' => 'Joined successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error joining class']);
}

$insert_stmt->close();
$conn->close();

/**
 * Helper function to send WhatsApp notifications for joining Zoom class
 */
function sendZoomJoinNotifications($conn, $zoom_class_id, $student_id) {
    if (!file_exists('../whatsapp_config.php')) return;
    require_once '../whatsapp_config.php';
    if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED) return;

    // Fetch Details
    $query = "SELECT zc.title, s.name as subject_name, 
                     stu.first_name as student_name, stu.whatsapp_number as student_wa, stu.mobile_number as student_mob,
                     tchr.first_name as teacher_first, tchr.second_name as teacher_second, tchr.whatsapp_number as teacher_wa, tchr.mobile_number as teacher_mob
              FROM zoom_classes zc
              JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
              JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              JOIN subjects s ON ss.subject_id = s.id
              JOIN users tchr ON ta.teacher_id = tchr.user_id
              JOIN users stu ON stu.user_id = ?
              WHERE zc.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $student_id, $zoom_class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $subj = $row['subject_name'];
        $title = $row['title'];
        $s_name = $row['student_name'];
        $s_wa = !empty($row['student_wa']) ? $row['student_wa'] : $row['student_mob'];
        $t_name = trim($row['teacher_first'] . ' ' . $row['teacher_second']);
        $t_wa = !empty($row['teacher_wa']) ? $row['teacher_wa'] : $row['teacher_mob'];

        $now = date('h:i A');

        // 1. Notify Teacher
        if (!empty($t_wa)) {
            $t_msg = "💻 *Student Joined Zoom Class*\n\n" .
                   "Student: *{$s_name}* ({$student_id})\n" .
                   "Subject: *{$subj}*\n" .
                   "Topic: *{$title}*\n" .
                   "Time: *{$now}*";
            sendWhatsAppMessage($t_wa, $t_msg);
        }

        // 2. Notify Student
        if (!empty($s_wa)) {
            $s_msg = "💻 *You Joined a Zoom Class*\n\n" .
                   "Hello {$s_name},\n" .
                   "You have successfully joined the Zoom class of *{$subj}* by *{$t_name}*.\n\n" .
                   "--------------------------\n\n" .
                   "ඔබ මේ වන විට *{$t_name}* විසින් පවත්වනු ලබන *{$subj}* Zoom සජීවී පන්තිය සමඟ සාර්ථකව සම්බන්ධ වී ඇත.\n\n" .
                   "Best of luck! - LearnerX Team";
            sendWhatsAppMessage($s_wa, $s_msg);
        }
    }
    $stmt->close();
}

?>
