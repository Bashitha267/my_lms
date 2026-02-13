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
    
    // Verify live class exists and is ongoing or scheduled
    $query = "SELECT r.id, r.status, ta.stream_subject_id, ta.academic_year
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              WHERE r.id = ? AND r.is_live = 1 AND r.status IN ('ongoing', 'scheduled')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $recording_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Live class not found or not accessible']);
        $stmt->close();
        exit;
    }
    
    $live_class = $result->fetch_assoc();
    $stmt->close();
    
    // ... (rest of the enrollment and join check logic) ...
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
            
            // Trigger Notification even on re-join
            sendJoinNotifications($conn, $recording_id, $user_id);
            
            echo json_encode(['success' => true, 'message' => 'Rejoined live class']);
        }
    } else {
        // First time joining
        $insert_query = "INSERT INTO live_class_participants (recording_id, student_id, joined_at) VALUES (?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("is", $recording_id, $user_id);
        
        if ($insert_stmt->execute()) {
            // Trigger Notification
            sendJoinNotifications($conn, $recording_id, $user_id);
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

/**
 * Helper function to send WhatsApp notifications for joining live class
 */
function sendJoinNotifications($conn, $recording_id, $student_id) {
    if (!file_exists('../whatsapp_config.php')) return;
    require_once '../whatsapp_config.php';
    if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED) return;

    // Fetch Details
    $query = "SELECT r.title, s.name as subject_name, 
                     stu.first_name as student_name, stu.whatsapp_number as student_wa, stu.mobile_number as student_mob,
                     tchr.first_name as teacher_first, tchr.second_name as teacher_second, tchr.whatsapp_number as teacher_wa, tchr.mobile_number as teacher_mob
              FROM recordings r
              JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              JOIN subjects s ON ss.subject_id = s.id
              JOIN users tchr ON ta.teacher_id = tchr.user_id
              JOIN users stu ON stu.user_id = ?
              WHERE r.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $student_id, $recording_id);
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
            $t_msg = "👤 *Student Joined Live Class*\n\n" .
                   "Student: *{$s_name}* ({$student_id})\n" .
                   "Subject: *{$subj}*\n" .
                   "Topic: *{$title}*\n" .
                   "Time: *{$now}*";
            sendWhatsAppMessage($t_wa, $t_msg);
        }

        // 2. Notify Student
        if (!empty($s_wa)) {
            $s_msg = "🎥 *You Joined a Live Class*\n\n" .
                   "Hello {$s_name},\n" .
                   "You have successfully joined the live class of *{$subj}* by *{$t_name}*.\n\n" .
                   "--------------------------\n\n" .
                   "ඔබ මේ වන විට *{$t_name}* විසින් පවත්වනු ලබන *{$subj}* සජීවී පන්තිය සමඟ සාර්ථකව සම්බන්ධ වී ඇත.\n\n" .
                   "Best of luck! - LearnerX Team";
            sendWhatsAppMessage($s_wa, $s_msg);
        }
    }
    $stmt->close();
}

?>

