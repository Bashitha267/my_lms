<?php
require_once '../check_session.php';
require_once '../config.php';

// Only admins can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$admin_id = $_SESSION['user_id'] ?? '';
$success_message = '';
$error_message = '';
$active_tab = $_GET['tab'] ?? 'verify';

// Handle payment verification (from Verify Tab)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $payment_type = $_POST['payment_type'] ?? ''; 
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? ''; 
    
    if ($payment_id > 0 && in_array($action, ['approve', 'reject']) && in_array($payment_type, ['enrollment', 'monthly', 'course'])) {
        if ($payment_type === 'course') {
            $status = $action === 'approve' ? 'paid' : 'failed';
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE course_payments SET payment_status = ?, verified_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $status, $payment_id);
                $stmt->execute();
                
                if ($action === 'approve') {
                     // Update Enrollment
                     $conn->query("UPDATE course_enrollments SET payment_status = 'paid' WHERE id = (SELECT course_enrollment_id FROM course_payments WHERE id = $payment_id)");
                }
                
                // WhatsApp Notification
                if (file_exists('../whatsapp_config.php')) {
                    require_once '../whatsapp_config.php';
                    if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
                        $info_q = "SELECT u.first_name, u.whatsapp_number, c.title as course_title, cp.amount
                                  FROM course_payments cp
                                  JOIN course_enrollments ce ON cp.course_enrollment_id = ce.id
                                  JOIN users u ON ce.student_id = u.user_id
                                  JOIN courses c ON ce.course_id = c.id
                                  WHERE cp.id = ?";
                        $i_stmt = $conn->prepare($info_q);
                        $i_stmt->bind_param("i", $payment_id);
                        $i_stmt->execute();
                        $i_res = $i_stmt->get_result();
                        if ($i_row = $i_res->fetch_assoc()) {
                            $s_name = $i_row['first_name'];
                            $s_wa = $i_row['whatsapp_number'];
                            $c_title = $i_row['course_title'];
                            $amt = number_format($i_row['amount'], 2);
                            
                            if ($action === 'approve') {
                                $s_msg = "✅ *Payment Approved / ගෙවීම් තහවුරු කරන ලදී*\n\n" .
                                       "Hello {$s_name},\n" .
                                       "Your payment of *Rs. {$amt}* for the course *{$c_title}* has been *Approved*.\n" .
                                       "You can now access the course content.\n\n" .
                                       "--------------------------\n\n" .
                                       "ඔබේ *{$c_title}* පාඨමාලාව සඳහා වූ රු. {$amt} ක ගෙවීම සාර්ථකව තහවුරු කරන ලදී. ඔබට දැන් පාඩම් නැරඹිය හැකිය.\n\n" .
                                       "Thank you, LearnerX Team";
                            } else {
                                $s_msg = "❌ *Payment Rejected / ගෙවීම් ප්‍රතික්ෂේප කරන ලදී*\n\n" .
                                       "Hello {$s_name},\n" .
                                       "Your payment for the course *{$c_title}* has been *Rejected*.\n" .
                                       "Please contact support for more information.\n\n" .
                                       "--------------------------\n\n" .
                                       "ඔබේ *{$c_title}* පාඨමාලාව සඳහා වූ ගෙවීම ප්‍රතික්ෂේප කර ඇත. වැඩි විස්තර සඳහා අපව අමතන්න.\n\n" .
                                       "Thank you, LearnerX Team";
                            }
                            sendWhatsAppMessage($s_wa, $s_msg);
                        }
                        $i_stmt->close();
                    }
                }
                
                $conn->commit();
                $success_message = "Payment {$action}d successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error: " . $e->getMessage();
            }
        } else {
            $table = $payment_type === 'enrollment' ? 'enrollment_payments' : 'monthly_payments';
            $status = $action === 'approve' ? 'paid' : 'failed';
            
            // Fetch info BEFORE update for notification
            $student_info = null;
            $info_query = "SELECT u.first_name, u.whatsapp_number, s.name as subject_name, p.amount,
                                  st.name as stream_name, t.first_name as teacher_name
                          FROM {$table} p
                          JOIN student_enrollment se ON p.student_enrollment_id = se.id
                          JOIN users u ON se.student_id = u.user_id
                          JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                          JOIN subjects s ON ss.subject_id = s.id
                          JOIN streams st ON ss.stream_id = st.id
                          LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id AND ta.academic_year = se.academic_year AND ta.status = 'active'
                          LEFT JOIN users t ON ta.teacher_id = t.user_id
                          WHERE p.id = ?";
            $info_stmt = $conn->prepare($info_query);
            $info_stmt->bind_param("i", $payment_id);
            $info_stmt->execute();
            $student_info = $info_stmt->get_result()->fetch_assoc();
            $info_stmt->close();

            // Perform Update
            $query = "UPDATE {$table} SET payment_status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $status, $payment_id);
            
            if ($stmt->execute()) {
                $success_message = "Payment {$action}d successfully!";
                
                // WhatsApp Notification
                if ($student_info && file_exists('../whatsapp_config.php')) {
                    require_once '../whatsapp_config.php';
                    if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
                        $s_name = $student_info['first_name'];
                        $s_wa = $student_info['whatsapp_number'];
                        $subj = $student_info['subject_name'];
                        $stream = $student_info['stream_name'];
                        $teacher = $student_info['teacher_name'] ?? 'Teacher';
                        $amt = number_format($student_info['amount'], 2);
                        $p_label = $payment_type === 'enrollment' ? 'Enrollment' : 'Monthly';
                        // $p_label_si = $payment_type === 'enrollment' ? 'බඳවා ගැනීමේ' : 'මාසික'; // Unused in new format if we simplify
                        
                        if ($action === 'approve') {
                            $s_msg = "✅ *Payment Approved / ගෙවීම් තහවුරු කරන ලදී*\n\n" .
                                   "Hello {$s_name},\n" .
                                   "Your {$p_label} payment has been approved.\n\n" .
                                   "Teacher: *{$teacher}*\n" .
                                   "Stream: *{$stream}*\n" .
                                   "Subject: *{$subj}*\n" .
                                   "Amount: *Rs. {$amt}*\n\n" .
                                   "You can now access the content.\n" .
                                   "ඔබට දැන් පන්ති සදහා සහභාගී විය හැක.\n\n" .
                                   "--------------------------\n\n" .
                                   "";
                        } else {
                            $s_msg = "❌ *Payment Rejected / ගෙවීම් ප්‍රතික්ෂේප කරන ලදී*\n\n" .
                                   "Hello {$s_name},\n" .
                                   "Your {$p_label} payment for *{$subj}* has been *Rejected*.\n\n" .
                                   "Teacher: *{$teacher}*\n" .
                                   "Stream: *{$stream}*\n\n" .
                                   "Please contact support or re-upload your receipt.\n" .
                                   "ඔබේ ගෙවීම ප්‍රතික්ෂේප කර ඇත. කරුණාකර අපව අමතන්න.\n\n" .
                                   "--------------------------\n\n" .
                                   "";
                        }
                        sendWhatsAppMessage($s_wa, $s_msg);
                    }
                }
            } else {
                $error_message = "Error updating payment: " . $conn->error;
            }
            $stmt->close();
        }
    }
}


// --- DATA FETCHING ---

// 1. Pending Payments & History (for Verify Tab)
$pending_payments = [];
$history_payments = [];

if ($active_tab === 'verify') {
    // Pending Enrollment
    $enroll_query = "SELECT ep.*, se.student_id, se.academic_year, u.first_name, u.second_name, 
                     s.name as stream_name, sub.name as subject_name
                     FROM enrollment_payments ep
                     JOIN student_enrollment se ON ep.student_enrollment_id = se.id
                     JOIN users u ON se.student_id = u.user_id
                     JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                     JOIN streams s ON ss.stream_id = s.id
                     JOIN subjects sub ON ss.subject_id = sub.id
                     WHERE ep.payment_status = 'pending' ORDER BY ep.created_at DESC";
    $res = $conn->query($enroll_query);
    while ($row = $res->fetch_assoc()) { $row['payment_type'] = 'enrollment'; $pending_payments[] = $row; }
    
    // Pending Monthly
    $monthly_query = "SELECT mp.*, se.student_id, se.academic_year, u.first_name, u.second_name,
                      s.name as stream_name, sub.name as subject_name
                      FROM monthly_payments mp
                      JOIN student_enrollment se ON mp.student_enrollment_id = se.id
                      JOIN users u ON se.student_id = u.user_id
                      JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                      JOIN streams s ON ss.stream_id = s.id
                      JOIN subjects sub ON ss.subject_id = sub.id
                      WHERE mp.payment_status = 'pending' ORDER BY mp.created_at DESC";
    $res = $conn->query($monthly_query);
    while ($row = $res->fetch_assoc()) { $row['payment_type'] = 'monthly'; $pending_payments[] = $row; }

    // Pending Course
    $course_query = "SELECT cp.*, ce.student_id, 'N/A' as academic_year,
                      c.title as stream_name, 'Online Course' as subject_name
                      FROM course_payments cp
                      JOIN course_enrollments ce ON cp.course_enrollment_id = ce.id
                      JOIN courses c ON ce.course_id = c.id
                      WHERE cp.payment_status = 'pending' ORDER BY cp.created_at DESC";
    $c_res = $conn->query($course_query);
    if ($c_res) {
        while ($row = $c_res->fetch_assoc()) { 
            $row['payment_type'] = 'course';
            // Fetch user separately
            $u_stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
            $u_stmt->bind_param("s", $row['student_id']);
            $u_stmt->execute();
            $u_res = $u_stmt->get_result();
            if ($u_row = $u_res->fetch_assoc()) {
                $row['first_name'] = $u_row['first_name'];
                $row['second_name'] = $u_row['second_name'];
            } else {
                $row['first_name'] = 'Unknown';
                $row['second_name'] = '';
            }
            $pending_payments[] = $row; 
        }
    }

    // History (Last 10 Approved)
    // 1. Fetch from Stream Payments (Enrollment + Monthly)
    $hist_sql = "
        (SELECT ep.id, ep.amount, ep.payment_status, ep.created_at, ep.receipt_path, 'enrollment' as type, 
                u.first_name, u.second_name,
                s.name as stream_name, sub.name as subject_name
         FROM enrollment_payments ep
         JOIN student_enrollment se ON ep.student_enrollment_id = se.id
         JOIN users u ON se.student_id = u.user_id
         JOIN stream_subjects ss ON se.stream_subject_id = ss.id
         JOIN streams s ON ss.stream_id = s.id
         JOIN subjects sub ON ss.subject_id = sub.id
         WHERE ep.payment_status = 'paid')
        UNION ALL
        (SELECT mp.id, mp.amount, mp.payment_status, mp.created_at, mp.receipt_path, 'monthly' as type,
                u.first_name, u.second_name,
                s.name as stream_name, sub.name as subject_name
         FROM monthly_payments mp
         JOIN student_enrollment se ON mp.student_enrollment_id = se.id
         JOIN users u ON se.student_id = u.user_id
         JOIN stream_subjects ss ON se.stream_subject_id = ss.id
         JOIN streams s ON ss.stream_id = s.id
         JOIN subjects sub ON ss.subject_id = sub.id
         WHERE mp.payment_status = 'paid')
        ORDER BY created_at DESC LIMIT 10
    ";
    
    $res = $conn->query($hist_sql);
    if($res) {
        while($row = $res->fetch_assoc()) { $history_payments[] = $row; }
    }

    // 2. Fetch from Course Payments
    $c_hist_sql = "
         SELECT cp.id, cp.amount, cp.payment_status, cp.created_at, cp.receipt_path, 'course' as type,
                ce.student_id,
                c.title as stream_name, 'Online Course' as subject_name
         FROM course_payments cp
         JOIN course_enrollments ce ON cp.course_enrollment_id = ce.id
         JOIN courses c ON ce.course_id = c.id
         WHERE cp.payment_status = 'paid'
         ORDER BY cp.created_at DESC LIMIT 10";
    
    $c_res = $conn->query($c_hist_sql);
    if ($c_res) {
        while ($row = $c_res->fetch_assoc()) {
            // Fetch User
            $u_stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
            $u_stmt->bind_param("s", $row['student_id']);
            $u_stmt->execute();
            $u_res = $u_stmt->get_result();
            if ($u_row = $u_res->fetch_assoc()) {
                $row['first_name'] = $u_row['first_name'];
                $row['second_name'] = $u_row['second_name'];
            } else {
                 $row['first_name'] = 'Unknown';
                 $row['second_name'] = '';
            }
            unset($row['student_id']); // Remove internal field
            $history_payments[] = $row;
        }
    }

    // 3. Sort and Limit
    usort($history_payments, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $history_payments = array_slice($history_payments, 0, 10);
}

// 2. Class Payments (for Classes Tab)
$teachers = [];
$teacher_classes = [];
$teacher_courses = [];
$class_students = [];
$course_students = [];
$selected_teacher = null;
$selected_assignment = null;
$selected_course = null;

if ($active_tab === 'classes') {
    // Fetch Teachers
    if (!isset($_GET['teacher_id'])) {
        $res = $conn->query("SELECT user_id, first_name, second_name, profile_picture FROM users WHERE role='teacher' ORDER BY first_name");
        while($row = $res->fetch_assoc()) $teachers[] = $row;
    } else {
        // Fetch Teacher Details
        $tid = $_GET['teacher_id'];
        $stmt = $conn->prepare("SELECT user_id, first_name, second_name FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $tid);
        $stmt->execute();
        $selected_teacher = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!isset($_GET['assignment_id']) && !isset($_GET['course_id'])) {
            // Fetch Classes for Teacher (Filtered by Year)
            $filter_year = $_GET['year'] ?? date('Y');
            $stmt = $conn->prepare("
                SELECT ta.id, ta.academic_year, s.name as stream_name, sub.name as subject_name, sub.code as subject_code
                FROM teacher_assignments ta
                JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                JOIN streams s ON ss.stream_id = s.id
                JOIN subjects sub ON ss.subject_id = sub.id
                WHERE ta.teacher_id = ? AND ta.academic_year = ? AND ta.status = 'active'
                ORDER BY s.name, sub.name
            ");
            $stmt->bind_param("si", $tid, $filter_year);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) $teacher_classes[] = $row;
            $stmt->close();
            
            // Fetch Online Courses for Teacher
            $query = "SELECT id, title, price, cover_image FROM courses WHERE teacher_id = ? ORDER BY title";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("s", $tid);
                $stmt->execute();
                $res = $stmt->get_result();
                while($row = $res->fetch_assoc()) $teacher_courses[] = $row;
                $stmt->close();
            }
            
        } elseif (isset($_GET['assignment_id'])) {
            // Fetch Assignment Details
            $aid = intval($_GET['assignment_id']);
            $stmt = $conn->prepare("
                SELECT ta.*, s.name as stream_name, sub.name as subject_name
                FROM teacher_assignments ta
                JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                JOIN streams s ON ss.stream_id = s.id
                JOIN subjects sub ON ss.subject_id = sub.id
                WHERE ta.id = ?
            ");
            $stmt->bind_param("i", $aid);
            $stmt->execute();
            $selected_assignment = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Fetch Students & Payment Status
            $filter_month = $_GET['month'] ?? date('n');
            $filter_year = $_GET['year'] ?? date('Y');
            $filter_status = $_GET['status'] ?? 'all';

            $query = "
                SELECT 
                    se.id as enrollment_id, u.user_id, u.first_name, u.second_name, u.profile_picture,
                    mp.payment_status as monthly_status, mp.amount, mp.created_at as paid_at
                FROM student_enrollment se
                JOIN users u ON se.student_id = u.user_id
                LEFT JOIN monthly_payments mp ON mp.student_enrollment_id = se.id 
                    AND mp.month = ? AND mp.year = ? AND mp.payment_status = 'paid'
                WHERE se.stream_subject_id = ? AND se.academic_year = ? AND se.status = 'active'
            ";
            
            if ($filter_status === 'paid') {
                $query .= " AND mp.payment_status = 'paid'";
            } elseif ($filter_status === 'not_paid') {
                $query .= " AND mp.id IS NULL"; 
            }
            
            $query .= " ORDER BY u.first_name";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiis", $filter_month, $filter_year, $selected_assignment['stream_subject_id'], $selected_assignment['academic_year']);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) $class_students[] = $row;
            $stmt->close();
            
        } elseif (isset($_GET['course_id'])) {
            // Fetch Course Details
            $cid = intval($_GET['course_id']);
            $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->bind_param("i", $cid);
            $stmt->execute();
            $selected_course = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Fetch Students & Payment Status
            $filter_status = $_GET['status'] ?? 'all';
            
            $query = "
                SELECT ce.id, ce.student_id,
                       ce.payment_status, ce.enrolled_at,
                       (SELECT amount FROM course_payments cp WHERE cp.course_enrollment_id = ce.id AND cp.payment_status = 'paid' ORDER BY id DESC LIMIT 1) as paid_amount,
                       (SELECT created_at FROM course_payments cp WHERE cp.course_enrollment_id = ce.id AND cp.payment_status = 'paid' ORDER BY id DESC LIMIT 1) as paid_at
                FROM course_enrollments ce
                WHERE ce.course_id = ?
            ";
             
            if ($filter_status === 'paid') $query .= " AND ce.payment_status = 'paid'";
            elseif ($filter_status === 'not_paid') $query .= " AND ce.payment_status = 'pending'";
             
            // Removed SQL ORDER BY due to separated query
             
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $cid);
                $stmt->execute();
                $res = $stmt->get_result();
                
                // Prepare user fetch statement
                $u_stmt = $conn->prepare("SELECT user_id, first_name, second_name, profile_picture FROM users WHERE user_id = ?");
                
                while($row = $res->fetch_assoc()) {
                    // Fetch user details separately to avoid collation mismatch
                    $u_stmt->bind_param("s", $row['student_id']);
                    $u_stmt->execute();
                    $u_res = $u_stmt->get_result();
                    if ($user = $u_res->fetch_assoc()) {
                        $row['user_id'] = $user['user_id'];
                        $row['first_name'] = $user['first_name'];
                        $row['second_name'] = $user['second_name'];
                        $row['profile_picture'] = $user['profile_picture'];
                    } else {
                        $row['user_id'] = $row['student_id'];
                        $row['first_name'] = 'Unknown';
                        $row['second_name'] = 'User';
                        $row['profile_picture'] = null;
                    }
                    $course_students[] = $row;
                }
                $u_stmt->close();
                $stmt->close();
                
                // Sort by name in PHP
                usort($course_students, function($a, $b) {
                    return strcasecmp($a['first_name'] . ' ' . $a['second_name'], $b['first_name'] . ' ' . $b['second_name']);
                });
            } else {
                $error_message = "Database error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payments | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-active { color: #dc2626; border-bottom: 2px solid #dc2626; margin-bottom: -1px; }
        .tab-inactive { color: #6b7280; }
        .tab-inactive:hover { color: #374151; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Tab Navigation -->
        <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
            <div class="border-b border-gray-200 overflow-x-auto no-scrollbar">
                <nav class="flex -mb-px" aria-label="Tabs">
                    <a href="?tab=verify" class="whitespace-nowrap py-4 px-6 text-center font-medium text-sm sm:text-base flex-1 shrink-0 <?php echo $active_tab === 'verify' ? 'tab-active' : 'tab-inactive hover:bg-gray-50'; ?>">
                        Verify Pending Requests
                    </a>
                    <a href="?tab=classes" class="whitespace-nowrap py-4 px-6 text-center font-medium text-sm sm:text-base flex-1 shrink-0 <?php echo $active_tab === 'classes' ? 'tab-active' : 'tab-inactive hover:bg-gray-50'; ?>">
                        Class Payments Overview
                    </a>
                </nav>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- TAB 1: VERIFY PENDING -->
        <?php if ($active_tab === 'verify'): ?>
            <div class="space-y-8">
                <!-- Pending Section -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">Pending Payments</h2>
                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded"><?php echo count($pending_payments); ?> Pending</span>
                    </div>
                    
                    <?php if (empty($pending_payments)): ?>
                        <div class="p-12 text-center text-gray-500">No pending payments found.</div>
                    <?php else: ?>
                        <!-- Mobile Card View -->
                        <div class="md:hidden space-y-4 p-4 bg-gray-50">
                            <?php foreach ($pending_payments as $p): ?>
                                <div class="bg-white border rounded-lg p-4 shadow-sm">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($p['first_name'].' '.$p['second_name']); ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($p['student_id']); ?></p>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                            <?php echo ucfirst($p['payment_type']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-4 text-sm border-t border-b border-gray-100 py-3">
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wide">Class</p>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($p['subject_name']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($p['stream_name']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wide">Amount</p>
                                            <p class="font-bold text-green-600">Rs. <?php echo number_format($p['amount'], 2); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center pt-2">
                                        <?php if ($p['receipt_path']): ?>
                                            <a href="../<?php echo $p['receipt_path']; ?>" target="_blank" class="text-blue-600 hover:underline text-sm flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                Receipt
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">No Receipt</span>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="flex space-x-2">
                                            <input type="hidden" name="verify_payment" value="1">
                                            <input type="hidden" name="payment_type" value="<?php echo $p['payment_type']; ?>">
                                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                            
                                            <button type="submit" name="action" value="reject" class="text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 text-xs font-semibold px-3 py-1.5 rounded transition block">Reject</button>
                                            <button type="submit" name="action" value="approve" class="bg-green-600 text-white hover:bg-green-700 text-xs font-semibold px-3 py-1.5 rounded shadow-sm transition block">Approve</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table View -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Proof</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pending_payments as $p): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($p['first_name'].' '.$p['second_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($p['student_id']); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($p['subject_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($p['stream_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                Rs. <?php echo number_format($p['amount'], 2); ?><br>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800"><?php echo ucfirst($p['payment_type']); ?></span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($p['receipt_path']): ?>
                                                    <a href="../<?php echo $p['receipt_path']; ?>" target="_blank" class="text-red-600 hover:underline text-sm">View Receipt</a>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-sm">No Receipt</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-center space-x-2">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="verify_payment" value="1">
                                                    <input type="hidden" name="payment_type" value="<?php echo $p['payment_type']; ?>">
                                                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                                    
                                                    <button type="submit" name="action" value="approve" class="text-green-600 hover:text-green-900 font-medium text-sm">Approve</button>
                                                    <span class="text-gray-300">|</span>
                                                    <button type="submit" name="action" value="reject" class="text-red-600 hover:text-red-900 font-medium text-sm">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- History Section -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-900">Recent Approved History</h2>
                        <p class="text-sm text-gray-500">Last 10 approved payments</p>
                    </div>
                    <?php if (empty($history_payments)): ?>
                        <div class="p-8 text-center text-gray-500">No payment history found.</div>
                    <?php else: ?>
                        <!-- Mobile Card View -->
                        <div class="md:hidden space-y-4 p-4 bg-gray-50">
                            <?php foreach ($history_payments as $h): ?>
                                <div class="bg-white border rounded-lg p-4 shadow-sm">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($h['first_name'].' '.$h['second_name']); ?></h3>
                                            <p class="text-xs text-gray-400"><?php echo date('M d, Y H:i', strtotime($h['created_at'])); ?></p>
                                        </div>
                                        <span class="text-gray-500 font-medium text-sm">Rs. <?php echo number_format($h['amount'], 2); ?></span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between text-sm py-2 border-t border-gray-100 mt-2">
                                        <div class="flex-1">
                                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($h['subject_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($h['stream_name']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($h['receipt_path']): ?>
                                                <a href="../<?php echo $h['receipt_path']; ?>" target="_blank" class="text-blue-600 hover:underline text-xs flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg> 
                                                    Receipt
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">No Receipt</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table View -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Receipt</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($history_payments as $h): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($h['first_name'].' '.$h['second_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($h['subject_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($h['stream_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm font-medium text-green-600">
                                                Rs. <?php echo number_format($h['amount'], 2); ?>
                                                <span class="block text-xs text-gray-500 font-normal"><?php echo ucfirst($h['type']); ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?php echo date('M d, H:i', strtotime($h['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <?php if ($h['receipt_path']): ?>
                                                    <a href="../<?php echo $h['receipt_path']; ?>" target="_blank" class="text-gray-400 hover:text-red-600 transition-colors">
                                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-300">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- TAB 2: CLASSES OVERVIEW -->
        <?php if ($active_tab === 'classes'): ?>
            
            <!-- Breadcrumb Navigation -->
            <nav class="flex mb-4 text-sm text-gray-500">
                <a href="?tab=classes" class="hover:text-red-600">Teachers</a>
                <?php if($selected_teacher): ?>
                    <span class="mx-2">/</span>
                    <a href="?tab=classes&teacher_id=<?php echo $selected_teacher['user_id']; ?>" class="hover:text-red-600"><?php echo htmlspecialchars($selected_teacher['first_name']); ?></a>
                <?php endif; ?>
                <?php if($selected_assignment): ?>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($selected_assignment['subject_name']); ?></span>
                <?php endif; ?>
                <?php if($selected_course): ?>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($selected_course['title']); ?></span>
                <?php endif; ?>
            </nav>

            <!-- Step 1: Select Teacher -->
            <?php if (!$selected_teacher): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Select a Teacher</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <?php foreach($teachers as $t): ?>
                            <a href="?tab=classes&teacher_id=<?php echo $t['user_id']; ?>" class="flex items-center p-3 border rounded-lg hover:border-red-500 hover:shadow-md transition-all group">
                                <div class="w-10 h-10 rounded-full bg-gray-200 overflow-hidden mr-3">
                                    <?php if($t['profile_picture']): ?>
                                        <img src="../<?php echo $t['profile_picture']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="flex items-center justify-center w-full h-full text-gray-500 text-xs"><?php echo substr($t['first_name'],0,1); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="font-medium text-gray-700 group-hover:text-red-600"><?php echo htmlspecialchars($t['first_name'].' '.$t['second_name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Step 2: Select Class/Course -->
            <?php if ($selected_teacher && !$selected_assignment && !$selected_course): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold text-gray-900">Classes for <?php echo htmlspecialchars($selected_teacher['first_name']); ?></h3>
                        <form method="GET" class="flex items-center space-x-2">
                            <input type="hidden" name="tab" value="classes">
                            <input type="hidden" name="teacher_id" value="<?php echo $selected_teacher['user_id']; ?>">
                            <select name="year" onchange="this.form.submit()" class="border-gray-300 rounded text-sm focus:ring-red-500 focus:border-red-500">
                                <?php 
                                $cur = date('Y');
                                for($y=$cur-1; $y<=$cur+1; $y++) {
                                    $sel = ($y == ($_GET['year']??$cur)) ? 'selected' : '';
                                    echo "<option value='$y' $sel>$y</option>";
                                }
                                ?>
                            </select>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php if(empty($teacher_classes)): ?>
                            <p class="text-gray-500 col-span-3 text-center">No classes found for this year.</p>
                        <?php else: ?>
                            <?php foreach($teacher_classes as $c): ?>
                                <a href="?tab=classes&teacher_id=<?php echo $selected_teacher['user_id']; ?>&assignment_id=<?php echo $c['id']; ?>&year=<?php echo $_GET['year']??date('Y'); ?>" class="block bg-white border border-gray-200 rounded-lg p-5 hover:border-red-500 hover:shadow-lg transition-all relative overflow-hidden">
                                     <div class="absolute top-0 left-0 w-1 h-full bg-red-500"></div>
                                     <h4 class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($c['subject_name']); ?></h4>
                                     <p class="text-gray-600"><?php echo htmlspecialchars($c['stream_name']); ?></p>
                                     <p class="text-xs text-gray-400 mt-2"><?php echo $c['subject_code']; ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Online Courses Section -->
                    <div class="mt-8">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Online Courses for <?php echo htmlspecialchars($selected_teacher['first_name']); ?></h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php if(empty($teacher_courses)): ?>
                                <p class="text-gray-500 col-span-full">No online courses found.</p>
                            <?php else: ?>
                                <?php foreach($teacher_courses as $c): ?>
                                    <a href="?tab=classes&teacher_id=<?php echo $selected_teacher['user_id']; ?>&course_id=<?php echo $c['id']; ?>" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-red-500 hover:shadow-lg transition-all group">
                                         <?php if(!empty($c['cover_image'])): ?>
                                            <div class="h-32 w-full bg-gray-200">
                                                <img src="../<?php echo $c['cover_image']; ?>" class="w-full h-full object-cover">
                                            </div>
                                         <?php else: ?>
                                            <div class="h-32 w-full bg-gray-100 flex items-center justify-center text-gray-400">
                                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                         <?php endif; ?>
                                         <div class="p-4">
                                            <h4 class="font-bold text-gray-900 mb-1 group-hover:text-red-600"><?php echo htmlspecialchars($c['title']); ?></h4>
                                            <p class="font-medium text-green-600">Rs. <?php echo number_format($c['price'], 2); ?></p>
                                         </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Step 3: Class Payments View -->
            <?php if ($selected_assignment): ?>
                <div class="bg-white rounded-lg shadow">
                    <!-- Filters -->
                    <div class="p-6 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <input type="hidden" name="tab" value="classes">
                            <input type="hidden" name="teacher_id" value="<?php echo $selected_teacher['user_id']; ?>">
                            <input type="hidden" name="assignment_id" value="<?php echo $selected_assignment['id']; ?>">
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Year</label>
                                <input type="number" name="year" value="<?php echo $_GET['year']??date('Y'); ?>" class="w-24 border-gray-300 rounded text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Month</label>
                                <select name="month" class="border-gray-300 rounded text-sm w-32">
                                    <?php for($m=1; $m<=12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == ($_GET['month']??date('n')) ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                                <select name="status" class="border-gray-300 rounded text-sm w-32">
                                    <option value="all" <?php echo ($_GET['status']??'all')=='all'?'selected':''; ?>>All</option>
                                    <option value="paid" <?php echo ($_GET['status']??'')=='paid'?'selected':''; ?>>Paid</option>
                                    <option value="not_paid" <?php echo ($_GET['status']??'')=='not_paid'?'selected':''; ?>>Not Paid</option>
                                </select>
                            </div>

                            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-red-700">Filter</button>
                        </form>
                    </div>

                    <div class="p-6">
                        <?php if(empty($class_students)): ?>
                            <p class="text-center text-gray-500 py-10">No students found.</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach($class_students as $s): 
                                    $is_paid = $s['monthly_status'] === 'paid';
                                ?>
                                    <div class="flex items-center p-3 border rounded-lg <?php echo $is_paid ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3 <?php echo $is_paid ? 'bg-green-400' : 'bg-red-400'; ?>">
                                            <?php echo substr($s['first_name'],0,1); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($s['first_name'].' '.$s['second_name']); ?></p>
                                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($s['user_id']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <?php if($is_paid): ?>
                                                <span class="block px-2 py-1 bg-green-200 text-green-800 text-xs font-bold rounded-full">Paid</span>
                                                <span class="text-xs text-gray-500"><?php echo date('M d', strtotime($s['paid_at'])); ?></span>
                                            <?php else: ?>
                                                <span class="block px-2 py-1 bg-red-200 text-red-800 text-xs font-bold rounded-full">Not Paid</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                </div>
            <?php endif; ?>

            <!-- Step 3: Course Payments View -->
            <?php if ($selected_course): ?>
                <div class="bg-white rounded-lg shadow">
                    <!-- Filters -->
                    <div class="p-6 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <input type="hidden" name="tab" value="classes">
                            <input type="hidden" name="teacher_id" value="<?php echo $selected_teacher['user_id']; ?>">
                            <input type="hidden" name="course_id" value="<?php echo $selected_course['id']; ?>">
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                                <select name="status" class="border-gray-300 rounded text-sm w-32">
                                    <option value="all" <?php echo ($_GET['status']??'all')=='all'?'selected':''; ?>>All</option>
                                    <option value="paid" <?php echo ($_GET['status']??'')=='paid'?'selected':''; ?>>Paid</option>
                                    <option value="not_paid" <?php echo ($_GET['status']??'')=='not_paid'?'selected':''; ?>>Not Paid</option>
                                </select>
                            </div>

                            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-red-700">Filter</button>
                        </form>
                    </div>

                    <div class="p-6">
                        <?php if(empty($course_students)): ?>
                            <p class="text-center text-gray-500 py-10">No students enrolled.</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach($course_students as $s): 
                                    $is_paid = $s['payment_status'] === 'paid';
                                ?>
                                    <div class="flex items-center p-3 border rounded-lg <?php echo $is_paid ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3 <?php echo $is_paid ? 'bg-green-400' : 'bg-red-400'; ?>">
                                            <?php echo substr($s['first_name'],0,1); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($s['first_name'].' '.$s['second_name']); ?></p>
                                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($s['user_id']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <?php if($is_paid): ?>
                                                <span class="block px-2 py-1 bg-green-200 text-green-800 text-xs font-bold rounded-full">Paid</span>
                                                <span class="text-xs text-gray-500"><?php echo date('M d', strtotime($s['paid_at'])); ?></span>
                                            <?php else: ?>
                                                <span class="block px-2 py-1 bg-red-200 text-red-800 text-xs font-bold rounded-full">Pending</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</body>
</html>
