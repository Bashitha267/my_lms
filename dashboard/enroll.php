<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Only students can enroll']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['enroll'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$stream_subject_id = isset($_POST['stream_subject_id']) ? intval($_POST['stream_subject_id']) : 0;
$academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');

if ($stream_subject_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid stream subject ID']);
    exit();
}

// Check if already enrolled
$check_query = "SELECT id FROM student_enrollment 
                WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $check_stmt->close();
    echo json_encode(['success' => false, 'message' => 'You are already enrolled in this subject']);
    exit();
}
$check_stmt->close();

// Verify the stream_subject_id exists
$verify_query = "SELECT id FROM stream_subjects WHERE id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("i", $stream_subject_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $verify_stmt->close();
    echo json_encode(['success' => false, 'message' => 'Invalid subject']);
    exit();
}
$verify_stmt->close();

// Insert enrollment
$insert_query = "INSERT INTO student_enrollment (student_id, stream_subject_id, academic_year, status, payment_status, enrolled_date) 
                 VALUES (?, ?, ?, 'active', 'pending', CURDATE())";
$insert_stmt = $conn->prepare($insert_query);
$insert_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);

if ($insert_stmt->execute()) {
    $insert_stmt->close();

    // Send WhatsApp notification
    if (file_exists('../whatsapp_config.php')) {
        require_once '../whatsapp_config.php';
        
        if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
            // Fetch student whatsapp number, subject name, and teacher name
            $info_query = "SELECT u.whatsapp_number, u.first_name, s.name as subject_name,
                                 tu.first_name as t_fname, tu.second_name as t_sname
                          FROM users u
                          JOIN stream_subjects ss ON ss.id = ?
                          JOIN subjects s ON ss.subject_id = s.id
                          LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id 
                               AND ta.academic_year = ? AND ta.status = 'active'
                          LEFT JOIN users tu ON ta.teacher_id = tu.user_id
                          WHERE u.user_id = ?";
            $info_stmt = $conn->prepare($info_query);
            $info_stmt->bind_param("iis", $stream_subject_id, $academic_year, $user_id);
            $info_stmt->execute();
            $info_result = $info_stmt->get_result();
            
            if ($info_row = $info_result->fetch_assoc()) {
                $whatsapp_number = $info_row['whatsapp_number'];
                $first_name = $info_row['first_name'];
                $subject_name = $info_row['subject_name'];
                $teacher_name = trim(($info_row['t_fname'] ?? '') . ' ' . ($info_row['t_sname'] ?? ''));
                $teacher_display = !empty($teacher_name) ? "\n👨‍🏫 *ගුරුතුමා / Teacher:* {$teacher_name}" : "";
                
                if (!empty($whatsapp_number)) {
                    $enroll_msg = "📚 *ඇතුළත් වීම  සාර්ථකයි / Enrollment Successful*\n\n" .
                                "ඔබ සාර්ථකව *{$subject_name}* විෂය සඳහා ලියාපදිංචි වී ඇත.{$teacher_display}\n" .
                                "--------------------------\n\n" .
                                "Hello {$first_name},\n" .
                                "You have successfully enrolled in the subject: *{$subject_name}*.\n\n" .
                                "Thank you for choosing LearnerX!";

                    sendWhatsAppMessage($whatsapp_number, $enroll_msg);
                }
            }
            $info_stmt->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Enrollment successful']);
} else {

    $error = $conn->error;
    $insert_stmt->close();
    echo json_encode(['success' => false, 'message' => 'Enrollment failed: ' . $error]);
}
?>





