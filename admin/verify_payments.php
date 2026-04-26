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
    
    if ($payment_id > 0 && in_array($action, ['approve', 'reject']) && in_array($payment_type, ['enrollment', 'monthly', 'course', 'instructor'])) {
        if ($payment_type === 'instructor') {
            $status = $action === 'approve' ? 'verified' : 'rejected';
            $conn->begin_transaction();
            try {
                // 1. Update Payment Record
                $stmt = $conn->prepare("UPDATE instructor_payments SET status = ?, verified_at = NOW(), verified_by = ? WHERE id = ?");
                $stmt->bind_param("ssi", $status, $admin_id, $payment_id);
                $stmt->execute();
                
                // 2. Fetch Request Info
                $req_q = "SELECT ip.*, ir.student_id, ir.accepted_by, s.name as subject_name 
                         FROM instructor_payments ip 
                         JOIN instructor_requests ir ON ip.request_id = ir.id 
                         JOIN subjects s ON ir.subject_id = s.id
                         WHERE ip.id = ?";
                $r_stmt = $conn->prepare($req_q);
                $r_stmt->bind_param("i", $payment_id);
                $r_stmt->execute();
                $r_info = $r_stmt->get_result()->fetch_assoc();
                
                if ($r_info) {
                    $req_id = $r_info['request_id'];
                    $instructor_id = $r_info['accepted_by'];
                    $amount = $r_info['amount'];

                    if ($action === 'approve') {
                        // 3. Update Instructor Wallet
                        // Check if wallet exists
                        $w_check = $conn->prepare("SELECT id FROM instructor_wallet WHERE instructor_id = ?");
                        $w_check->bind_param("s", $instructor_id);
                        $w_check->execute();
                        if ($w_check->get_result()->num_rows === 0) {
                            $w_init = $conn->prepare("INSERT INTO instructor_wallet (instructor_id) VALUES (?)");
                            $w_init->bind_param("s", $instructor_id);
                            $w_init->execute();
                            $w_init->close();
                        }
                        $w_check->close();

                        $w_up = $conn->prepare("UPDATE instructor_wallet SET total_points = total_points + ?, total_earned = total_earned + ? WHERE instructor_id = ?");
                        $w_up->bind_param("dds", $amount, $amount, $instructor_id);
                        $w_up->execute();
                        $w_up->close();
                    }

                    $up_status = $action === 'approve' ? 'paid' : 'pending';
                    $conn->query("UPDATE instructor_requests SET status = '$up_status' WHERE id = $req_id");
                    
                    // WhatsApp Notifications
                    if (file_exists('../whatsapp_config.php')) {
                        require_once '../whatsapp_config.php';
                        if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
                            // Student Notify
                            $s_stmt = $conn->prepare("SELECT first_name, whatsapp_number FROM users WHERE user_id = ?");
                            $s_stmt->bind_param("s", $r_info['student_id']);
                            $s_stmt->execute();
                            $s_user = $s_stmt->get_result()->fetch_assoc();
                            
                            // Instructor Notify
                            $i_stmt = $conn->prepare("SELECT first_name, whatsapp_number FROM users WHERE user_id = ?");
                            $i_stmt->bind_param("s", $r_info['accepted_by']);
                            $i_stmt->execute();
                            $i_user = $i_stmt->get_result()->fetch_assoc();
                            
                            if ($s_user) {
                                $s_msg = $action === 'approve' ? 
                                    "✅ *Session Payment Approved*\nHello {$s_user['first_name']}, your payment for the session on *{$r_info['subject_name']}* is verified. Enjoy your session!" :
                                    "❌ *Session Payment Rejected*\nHello {$s_user['first_name']}, your payment proof for *{$r_info['subject_name']}* was rejected. Please re-check and upload again.";
                                sendWhatsAppMessage($s_user['whatsapp_number'], $s_msg);
                            }
                            
                            if ($i_user && $action === 'approve') {
                                $i_msg = "💰 *Payment Verified*\nHello {$i_user['first_name']}, the payment for your session on *{$r_info['subject_name']}* has been verified by admin. You can now proceed with the session.";
                                sendWhatsAppMessage($i_user['whatsapp_number'], $i_msg);
                            }
                        }
                    }
                }
                
                $conn->commit();
                $success_message = "Instructor payment {$action}d successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error: " . $e->getMessage();
            }
        }
        elseif ($payment_type === 'course') {
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
                                  JOIN users u ON ce.student_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
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
                                  st.name as stream_name, t.first_name as teacher_name, t.whatsapp_number as teacher_wa
                          FROM {$table} p
                          JOIN student_enrollment se ON p.student_enrollment_id = se.id
                          JOIN users u ON se.student_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                          JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                          JOIN subjects s ON ss.subject_id = s.id
                          JOIN streams st ON ss.stream_id = st.id
                          LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id AND ta.academic_year = se.academic_year AND ta.status = 'active'
                          LEFT JOIN users t ON ta.teacher_id COLLATE utf8mb4_unicode_ci = t.user_id COLLATE utf8mb4_unicode_ci
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

                        // Notify Teacher if approved
                        if ($action === 'approve' && !empty($student_info['teacher_wa'])) {
                            $t_msg = "📢 *New Enrollment Payment Approved*\n\n" .
                                   "Hello {$teacher},\n" .
                                   "A student (*{$s_name}*) has paid for your class.\n\n" .
                                   "Subject: *{$subj}*\n" .
                                   "Stream: *{$stream}*\n" .
                                   "Amount: *Rs. {$amt}*\n\n" .
                                   "Learner.LK Team";
                            sendWhatsAppMessage($student_info['teacher_wa'], $t_msg);
                        }
                    }
                }
            } else {
                $error_message = "Error updating payment: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle paying teacher requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pay_teacher'] ?? '0') === '1') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $pay_amount = floatval($_POST['pay_amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    if ($request_id > 0 && $pay_amount > 0) {
        $conn->begin_transaction();
        try {
            // Get request info
            $req_stmt = $conn->prepare("SELECT teacher_id, amount, status FROM teacher_payment_requests WHERE id = ? FOR UPDATE");
            $req_stmt->bind_param("i", $request_id);
            $req_stmt->execute();
            $req_result = $req_stmt->get_result();
            if ($req_result->num_rows > 0) {
                $req_row = $req_result->fetch_assoc();
                if ($req_row['status'] === 'pending') {
                    // Update request status
                    $update_stmt = $conn->prepare("UPDATE teacher_payment_requests SET status = 'paid', amount = ?, payment_method = ?, admin_notes = ?, processed_date = NOW(), processed_by = ? WHERE id = ?");
                    $update_stmt->bind_param("dsssi", $pay_amount, $payment_method, $admin_notes, $admin_id, $request_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Deduct from teacher wallet
                    $wallet_stmt = $conn->prepare("UPDATE teacher_wallet SET total_points = total_points - ?, total_withdrawn = total_withdrawn + ? WHERE teacher_id = ?");
                    $wallet_stmt->bind_param("dds", $pay_amount, $pay_amount, $req_row['teacher_id']);
                    $wallet_stmt->execute();
                    $wallet_stmt->close();
                    
                    $conn->commit();
                    $success_message = "Teacher payment request processed successfully!";
                    
                    // Add script to automatically open print dialog for invoice
                    echo "<script>
                        window.onload = function() {
                            const printWindow = window.open('', '', 'width=600,height=600');
                            printWindow.document.write(`
                                <html><head><title>Payment Receipt</title>
                                <style>body{font-family: Arial, sans-serif; padding:20px;} .header{text-align:center; font-weight:bold; font-size:24px; margin-bottom:20px;}
                                .details{margin-bottom:10px;} .amount{font-size:20px; font-weight:bold; margin-top:20px;} </style>
                                </head><body>
                                <div class='header'>Teacher Payment Receipt</div>
                                <div class='details'><b>Date:</b> ` + new Date().toLocaleString() + `</div>
                                <div class='details'><b>Teacher ID:</b> " . htmlspecialchars($req_row['teacher_id']) . "</div>
                                <div class='details'><b>Payment Method:</b> " . htmlspecialchars(ucfirst($payment_method)) . "</div>
                                <div class='details'><b>Admin Notes:</b> " . htmlspecialchars($admin_notes) . "</div>
                                <div class='amount'>Paid Amount: Rs. " . number_format($pay_amount, 2) . "</div>
                                <hr>
                                <div style='text-align:center; margin-top:30px;'>Thank You</div>
                                <script>window.print(); window.close();<\/script>
                                </body></html>
                            `);
                        }
                    </script>";
                } else {
                    throw new Exception("Request is not pending.");
                }
            } else {
                throw new Exception("Request not found.");
            }
            $req_stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }


    } else {
        $error_message = "Invalid amount or request.";
    }
}


// Handle paying instructor requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pay_instructor'] ?? '0') === '1') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $pay_amount = floatval($_POST['pay_amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    if ($request_id > 0 && $pay_amount > 0) {
        $conn->begin_transaction();
        try {
            // Get request info
            $req_stmt = $conn->prepare("SELECT instructor_id, amount, status FROM instructor_payout_requests WHERE id = ? FOR UPDATE");
            $req_stmt->bind_param("i", $request_id);
            $req_stmt->execute();
            $req_result = $req_stmt->get_result();
            if ($req_result->num_rows > 0) {
                $req_row = $req_result->fetch_assoc();
                if ($req_row['status'] === 'pending') {
                    // Update request status
                    $update_stmt = $conn->prepare("UPDATE instructor_payout_requests SET status = 'paid', amount = ?, payment_method = ?, admin_notes = ?, processed_date = NOW(), processed_by = ? WHERE id = ?");
                    $update_stmt->bind_param("dsssi", $pay_amount, $payment_method, $admin_notes, $admin_id, $request_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Deduct from instructor wallet
                    $wallet_stmt = $conn->prepare("UPDATE instructor_wallet SET total_points = total_points - ?, total_withdrawn = total_withdrawn + ? WHERE instructor_id = ?");
                    $wallet_stmt->bind_param("dds", $pay_amount, $pay_amount, $req_row['instructor_id']);
                    $wallet_stmt->execute();
                    $wallet_stmt->close();
                    
                    $conn->commit();
                    $success_message = "Instructor payment request processed successfully!";
                    
                    // Add script to automatically open print dialog for invoice
                    echo "<script>
                        window.onload = function() {
                            const printWindow = window.open('', '', 'width=600,height=600');
                            printWindow.document.write(`
                                <html><head><title>Payment Receipt</title>
                                <style>body{font-family: Arial, sans-serif; padding:20px;} .header{text-align:center; font-weight:bold; font-size:24px; margin-bottom:20px;}
                                .details{margin-bottom:10px;} .amount{font-size:20px; font-weight:bold; margin-top:20px;} </style>
                                </head><body>
                                <div class='header'>Instructor Payment Receipt</div>
                                <div class='details'><b>Date:</b> ` + new Date().toLocaleString() + `</div>
                                <div class='details'><b>Instructor ID:</b> " . htmlspecialchars($req_row['instructor_id']) . "</div>
                                <div class='details'><b>Payment Method:</b> " . htmlspecialchars(ucfirst($payment_method)) . "</div>
                                <div class='details'><b>Admin Notes:</b> " . htmlspecialchars($admin_notes) . "</div>
                                <div class='amount'>Paid Amount: Rs. " . number_format($pay_amount, 2) . "</div>
                                <hr>
                                <div style='text-align:center; margin-top:30px;'>Thank You</div>
                                <script>window.print(); window.setTimeout(function(){window.close();}, 500);<\/script>
                                </body></html>
                            `);
                        }
                    </script>";
                } else {
                    throw new Exception("Request is not pending.");
                }
            } else {
                throw new Exception("Request not found.");
            }
            $req_stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid amount or request.";
    }
}



// --- DATA FETCHING ---

$teacher_requests = [];
if ($active_tab === 'teacher_req') {
    $req_sql = "SELECT tr.*, u.first_name, u.second_name FROM teacher_payment_requests tr JOIN users u ON tr.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci ORDER BY tr.request_date DESC";
    $req_res = $conn->query($req_sql);
    if ($req_res) {
        while ($row = $req_res->fetch_assoc()) {
            $teacher_requests[] = $row;
        }
    }
}

$instructor_requests = [];
if ($active_tab === 'inst_req') {
    $req_sql = "SELECT ir.*, u.first_name, u.second_name FROM instructor_payout_requests ir JOIN users u ON ir.instructor_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci ORDER BY ir.request_date DESC";
    $req_res = $conn->query($req_sql);
    if ($req_res) {
        while ($row = $req_res->fetch_assoc()) {
            $instructor_requests[] = $row;
        }
    }
}

// 1. Pending Payments & History (for Verify Tab)
$pending_payments = [];
$history_payments = [];

if ($active_tab === 'verify') {
    // Pending Enrollment
    $enroll_query = "SELECT ep.*, se.student_id, se.academic_year, u.first_name, u.second_name, 
                     s.name as stream_name, sub.name as subject_name
                     FROM enrollment_payments ep
                     JOIN student_enrollment se ON ep.student_enrollment_id = se.id
                     JOIN users u ON se.student_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
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
                      JOIN users u ON se.student_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
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

    // Pending Instructor Sessions
    $inst_query = "SELECT ip.*, ir.request_note, 
                          COALESCE(u.first_name, 'Unknown') as first_name, 
                          COALESCE(u.second_name, '') as second_name, 
                          s.name as subject_name, 'Instructor' as stream_name
                  FROM instructor_payments ip
                  JOIN instructor_requests ir ON ip.request_id = ir.id
                  LEFT JOIN users u ON ip.student_id = u.user_id
                  JOIN subjects s ON ir.subject_id = s.id
                  WHERE ip.status = 'pending' ORDER BY ip.created_at DESC";
    $i_res = $conn->query($inst_query);
    if ($i_res) {
        while ($row = $i_res->fetch_assoc()) {
            $row['payment_type'] = 'instructor';
            // created_at already present in instructor_payments, no alias needed
            $pending_payments[] = $row;
        }
    } else {
        // Log query error for debugging
        error_log("Instructor payments query error: " . $conn->error);
    }

    // History (Last 10 Approved)
    // 1. Fetch from Stream Payments (Enrollment + Monthly)
    $hist_sql = "
        (SELECT ep.id, ep.amount, ep.payment_status, ep.created_at, ep.receipt_path, 'enrollment' as type, 
                u.first_name, u.second_name,
                s.name as stream_name, sub.name as subject_name
         FROM enrollment_payments ep
         JOIN student_enrollment se ON ep.student_enrollment_id = se.id
         JOIN users u ON se.student_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
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
         JOIN users u ON se.student_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
         JOIN stream_subjects ss ON se.stream_subject_id = ss.id
         JOIN streams s ON ss.stream_id = s.id
         JOIN subjects sub ON ss.subject_id = sub.id
         WHERE mp.payment_status = 'paid')
        UNION ALL
        (SELECT ip.id, ip.amount, ip.status as payment_status, ip.created_at, ip.receipt_path, 'instructor' as type,
                u.first_name, u.second_name,
                'Instructor' as stream_name, s.name as subject_name
         FROM instructor_payments ip
         JOIN instructor_requests ir ON ip.request_id = ir.id
         JOIN users u ON ip.student_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
         JOIN subjects s ON ir.subject_id = s.id
         WHERE ip.status = 'verified')
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

// 2. Teacher Requests handled above in tab logic
$selected_teacher = null;
$selected_assignment = null;
$selected_course = null;

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
                    <a href="?tab=teacher_req" class="whitespace-nowrap py-4 px-6 text-center font-medium text-sm sm:text-base flex-1 shrink-0 <?php echo $active_tab === 'teacher_req' ? 'tab-active' : 'tab-inactive hover:bg-gray-50'; ?>">
                        Teacher Payouts
                    </a>
                    <a href="?tab=inst_req" class="whitespace-nowrap py-4 px-6 text-center font-medium text-sm sm:text-base flex-1 shrink-0 <?php echo $active_tab === 'inst_req' ? 'tab-active' : 'tab-inactive hover:bg-gray-50'; ?>">
                        Instructor Payouts
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

        <!-- TAB 2: INSTRUCTOR PAYMENT REQUESTS -->
        <?php if ($active_tab === 'inst_req'): ?>
            <div class="space-y-8">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">Instructor Payout Requests</h2>
                    </div>
                    <?php if (empty($instructor_requests)): ?>
                        <div class="p-12 text-center text-gray-500">No requests found.</div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Instructor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($instructor_requests as $req): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($req['first_name'].' '.$req['second_name']); ?></td>
                                            <td class="px-6 py-4 text-sm font-bold text-green-600">Rs. <?php echo number_format($req['amount'], 2); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $req['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($req['status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo ucfirst($req['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('M d, Y H:i', strtotime($req['request_date'])); ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <?php if ($req['status'] === 'pending'): ?>
                                                    <button onclick="openPayModal('<?php echo $req['id']; ?>', '<?php echo htmlspecialchars($req['first_name'].' '.$req['second_name']); ?>', '<?php echo $req['amount']; ?>', true)" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm hover:bg-indigo-700">Pay</button>
                                                <?php else: ?>
                                                    <div class="flex flex-col items-center justify-center gap-1">
                                                        <span class="text-gray-400 text-sm">Paid on <?php echo $req['processed_date'] ? date('M d', strtotime($req['processed_date'])) : 'Unknown'; ?></span>
                                                        <button onclick="printReceipt('<?php echo $req['instructor_id']; ?>', '<?php echo ucfirst(htmlspecialchars($req['payment_method'])); ?>', '<?php echo htmlspecialchars(addslashes($req['admin_notes'])); ?>', '<?php echo $req['amount']; ?>', '<?php echo $req['processed_date']; ?>', true)" class="text-blue-600 hover:text-blue-800 text-xs flex items-center">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                                            Print Receipt
                                                        </button>
                                                    </div>
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


        <!-- TAB 1.5: TEACHER PAYMENT REQUESTS -->
        <?php if ($active_tab === 'teacher_req'): ?>
            <div class="space-y-8">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">Teacher Payment Requests</h2>
                    </div>
                    <?php if (empty($teacher_requests)): ?>
                        <div class="p-12 text-center text-gray-500">No requests found.</div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($teacher_requests as $req): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($req['first_name'].' '.$req['second_name']); ?></td>
                                            <td class="px-6 py-4 text-sm font-bold text-green-600">Rs. <?php echo number_format($req['amount'], 2); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $req['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($req['status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo ucfirst($req['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('M d, Y H:i', strtotime($req['request_date'])); ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <?php if ($req['status'] === 'pending'): ?>
                                                    <button onclick="openPayModal('<?php echo $req['id']; ?>', '<?php echo htmlspecialchars($req['first_name'].' '.$req['second_name']); ?>', '<?php echo $req['amount']; ?>')" class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">Pay</button>
                                                <?php else: ?>
                                                    <div class="flex flex-col items-center justify-center gap-1">
                                                        <span class="text-gray-400 text-sm">Paid on <?php echo $req['processed_date'] ? date('M d', strtotime($req['processed_date'])) : 'Unknown'; ?></span>
                                                        <button onclick="printReceipt('<?php echo $req['teacher_id']; ?>', '<?php echo ucfirst(htmlspecialchars($req['payment_method'])); ?>', '<?php echo htmlspecialchars(addslashes($req['admin_notes'])); ?>', '<?php echo $req['amount']; ?>', '<?php echo $req['processed_date']; ?>')" class="text-blue-600 hover:text-blue-800 text-xs flex items-center">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                                            Print Receipt
                                                        </button>
                                                    </div>
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

        <!-- Payout Process Modal (Used for both Teachers and Instructors) -->
        <div id="payModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-xl font-bold mb-4">Process <span id="modalRoleType">Teacher</span> Payment for <span id="modalTeacherName"></span></h3>
                <form method="POST">
                    <input type="hidden" name="pay_teacher" id="modalPayTeacherFlag" value="1">
                    <input type="hidden" name="pay_instructor" id="modalPayInstructorFlag" value="0">
                    <input type="hidden" name="request_id" id="modalRequestId" value="">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount to Pay (Rs.)</label>
                        <input type="number" step="0.01" name="pay_amount" id="modalPayAmount" class="w-full border-gray-300 rounded focus:ring-green-500 focus:border-green-500" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" class="w-full border-gray-300 rounded focus:ring-green-500 focus:border-green-500">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" class="w-full border-gray-300 rounded focus:ring-green-500 focus:border-green-500" rows="3"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closePayModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Confirm & Print</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openPayModal(id, name, amount, isInstructor = false) {
                document.getElementById('modalRequestId').value = id;
                document.getElementById('modalTeacherName').textContent = name;
                document.getElementById('modalPayAmount').value = amount;
                document.getElementById('modalRoleType').textContent = isInstructor ? 'Instructor' : 'Teacher';
                document.getElementById('modalPayTeacherFlag').value = isInstructor ? '0' : '1';
                document.getElementById('modalPayInstructorFlag').value = isInstructor ? '1' : '0';
                document.getElementById('payModal').classList.remove('hidden');
            }
            function closePayModal() {
                document.getElementById('payModal').classList.add('hidden');
            }
            function printReceipt(userId, method, notes, amount, dateStr, isInstructor = false) {
                const printWindow = window.open('', '', 'width=600,height=600');
                const paymentDate = new Date(dateStr).toLocaleString();
                const title = isInstructor ? 'Instructor Payment Receipt' : 'Teacher Payment Receipt';
                const userLabel = isInstructor ? 'Instructor ID' : 'Teacher ID';
                printWindow.document.write(`
                    <html><head><title>${title}</title>
                    <style>
                        body{font-family: Arial, sans-serif; padding:20px; color: #333;} 
                        .header{text-align:center; font-weight:bold; font-size:24px; margin-bottom:20px; color: #1a2b3c;}
                        .details{margin-bottom:10px; font-size: 14px;} 
                        .amount{font-size:22px; font-weight:bold; margin-top:20px; color: #16a34a;}
                        hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
                    </style>
                    </head><body>
                    <div class='header'>${title}</div>
                    <div class='details'><b>Date:</b> ${paymentDate}</div>
                    <div class='details'><b>${userLabel}:</b> ${userId}</div>
                    <div class='details'><b>Payment Method:</b> ${method}</div>
                    <div class='details'><b>Admin Notes:</b> ${notes}</div>
                    <div class='amount'>Paid Amount: Rs. ${parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                    <hr>
                    <div style='text-align:center; margin-top:30px; font-size: 12px; color: #999;'>Generated by LearnerX Administration System</div>
                    <script>window.print(); window.setTimeout(function(){window.close();}, 1000);<\/script>
                    </body></html>
                `);
                printWindow.document.close();
            }
        </script>

    </div>
</body>
</html>
