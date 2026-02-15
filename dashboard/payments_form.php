<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_type = $_POST['payment_type'] ?? ''; // 'enrollment' or 'monthly'
    $student_enrollment_id = intval($_POST['student_enrollment_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? '';
    $month = isset($_POST['month']) ? intval($_POST['month']) : null;
    $year = isset($_POST['year']) ? intval($_POST['year']) : null;
    
    // Validate
    if (empty($payment_type) || $student_enrollment_id <= 0 || $amount <= 0 || empty($payment_method)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Verify student owns this enrollment
        $verify_query = "SELECT id FROM student_enrollment WHERE id = ? AND student_id = ? AND status = 'active'";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param("is", $student_enrollment_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            $error_message = 'Invalid enrollment.';
        } else {
            if ($payment_method === 'card') {
                // Card payment - just save with pending status
                $card_number = substr($_POST['card_number'] ?? '', -4); // Last 4 digits only
                $card_holder = $_POST['card_holder'] ?? '';
                $expiry_month = $_POST['expiry_month'] ?? '';
                $expiry_year = $_POST['expiry_year'] ?? '';
                $cvv = $_POST['cvv'] ?? '';
                
                if (empty($card_number) || empty($card_holder) || empty($expiry_month) || empty($expiry_year) || empty($cvv)) {
                    $error_message = 'Please fill in all card details.';
                } else {
                    if ($payment_type === 'enrollment') {
                        $insert_query = "INSERT INTO enrollment_payments (student_enrollment_id, amount, payment_method, payment_status, card_number, notes) 
                                        VALUES (?, ?, 'card', 'pending', ?, ?)";
                        $insert_stmt = $conn->prepare($insert_query);
                        $notes = "Card: ****" . $card_number . ", Holder: " . $card_holder;
                        $insert_stmt->bind_param("idss", $student_enrollment_id, $amount, $card_number, $notes);
                        } else {
                            // Monthly payment
                            if ($month === null || $year === null) {
                                $error_message = 'Month and year are required for monthly payments.';
                            } else {
                                $insert_query = "INSERT INTO monthly_payments (student_enrollment_id, month, year, amount, payment_method, payment_status, card_number, notes) 
                                            VALUES (?, ?, ?, ?, 'card', 'pending', ?, ?)";
                                $insert_stmt = $conn->prepare($insert_query);
                                $notes = "Card: ****" . $card_number . ", Holder: " . $card_holder;
                                $insert_stmt->bind_param("iiiiss", $student_enrollment_id, $month, $year, $amount, $card_number, $notes);
                            }
                        }
                    
                    if (!isset($error_message) || empty($error_message)) {
                        if ($insert_stmt->execute()) {
                            $success_message = 'Payment submitted successfully! It will be processed shortly.';
                            
                            // WhatsApp Notifications
                            if (file_exists('../whatsapp_config.php')) {
                                require_once '../whatsapp_config.php';
                                if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
                                    // Fetch Student, Subject, Stream and Teacher info
                                    $info_q = "SELECT u.first_name, u.whatsapp_number, s.name as subject_name, st.name as stream_name, t.first_name as teacher_name
                                              FROM student_enrollment se
                                              JOIN users u ON se.student_id = u.user_id
                                              JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                                              JOIN subjects s ON ss.subject_id = s.id
                                              JOIN streams st ON ss.stream_id = st.id
                                              LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id AND ta.academic_year = se.academic_year AND ta.status = 'active'
                                              LEFT JOIN users t ON ta.teacher_id = t.user_id
                                              WHERE se.id = ?";
                                    $i_stmt = $conn->prepare($info_q);
                                    $i_stmt->bind_param("i", $student_enrollment_id);
                                    $i_stmt->execute();
                                    $i_res = $i_stmt->get_result();
                                    if ($i_row = $i_res->fetch_assoc()) {
                                        $s_name = $i_row['first_name'];
                                        $s_wa = $i_row['whatsapp_number'];
                                        $subj = $i_row['subject_name'];
                                        $stream = $i_row['stream_name'];
                                        $teacher = $i_row['teacher_name'] ?? 'Teacher';
                                        $p_type = ucfirst($payment_type);
                                        $month_str = "";
                                        if ($payment_type === 'monthly' && !empty($month)) {
                                            $month_name = date("F", mktime(0, 0, 0, $month, 10));
                                            $month_str = "Month: *{$month_name}*\\n";
                                        }
                                        
                                        // 1. Notify Student
                                        $s_msg = "💸 *Payment Submitted / ගෙවීම් ඉදිරිපත් කරන ලදී*\n\n" .
                                               "Hello {$s_name},\n" .
                                               "Thank you, we received your payment.\n\n" .
                                               "Teacher: *{$teacher}*\n" .
                                               "Stream: *{$stream}*\n" .
                                               "Subject: *{$subj}*\n" .
                                               "Type: *{$p_type}*\n" . $month_str . "\n" .
                                               "Our staff will quickly approve your payment.\n" .
                                               "ආයතනය මගින් ඔබගේ ගෙවීම් කඩිනමින් අනුමත කරනු ඇත.\n\n" .
                                               "--------------------------\n\n" .
                                               "Thank you, LearnerX Team";
                                        sendWhatsAppMessage($s_wa, $s_msg);
                                        
                                        // 2. Notify Admin
                                        if (defined('ADMIN_WHATSAPP')) {
                                            $a_msg = "🔔 *New Payment Pending Approval*\n\n" .
                                                   "Student: *{$s_name}* ({$user_id})\n" .
                                                   "Stream: *{$stream}*\n" .
                                                   "Subject: *{$subj}*\n" .
                                                   "Type: *{$p_type}*\n" . $month_str .
                                                   "Amount: *Rs. " . number_format($amount, 2) . "*\n\n" .
                                                   "Please check the admin panel to verify.";
                                            sendWhatsAppMessage(ADMIN_WHATSAPP, $a_msg);
                                        }
                                    }
                                    $i_stmt->close();
                                }
                            }
                        } else {
                            $error_message = 'Error submitting payment: ' . $conn->error;
                        }
                        $insert_stmt->close();
                    }
                }
            } elseif ($payment_method === 'bank_transfer') {
                // Bank transfer - requires receipt upload
                if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
                    $error_message = 'Please upload a payment receipt.';
                } else {
                    $file = $_FILES['receipt'];
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($file['type'], $allowed_types)) {
                        $error_message = 'Invalid file type. Please upload an image (JPG, PNG, GIF) or PDF.';
                    } elseif ($file['size'] > $max_size) {
                        $error_message = 'File size too large. Maximum size is 5MB.';
                    } else {
                        // Create uploads directory if it doesn't exist
                        $upload_dir = '../uploads/payments/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Generate unique filename
                        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $file_type = strpos($file['type'], 'pdf') !== false ? 'pdf' : 'image';
                        $filename = 'payment_' . $user_id . '_' . time() . '.' . $file_ext;
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            $receipt_path = 'uploads/payments/' . $filename;
                            
                            if ($payment_type === 'enrollment') {
                                $insert_query = "INSERT INTO enrollment_payments (student_enrollment_id, amount, payment_method, payment_status, receipt_path, receipt_type) 
                                                VALUES (?, ?, 'bank_transfer', 'pending', ?, ?)";
                                $insert_stmt = $conn->prepare($insert_query);
                                $insert_stmt->bind_param("idss", $student_enrollment_id, $amount, $receipt_path, $file_type);
                            } else {
                                // Monthly payment
                                if ($month === null || $year === null) {
                                    $error_message = 'Month and year are required for monthly payments.';
                                } else {
                                    $insert_query = "INSERT INTO monthly_payments (student_enrollment_id, month, year, amount, payment_method, payment_status, receipt_path, receipt_type) 
                                                    VALUES (?, ?, ?, ?, 'bank_transfer', 'pending', ?, ?)";
                                    $insert_stmt = $conn->prepare($insert_query);
                                    $insert_stmt->bind_param("iiiiss", $student_enrollment_id, $month, $year, $amount, $receipt_path, $file_type);
                                }
                            }
                            
                            if (!isset($error_message) || empty($error_message)) {
                                if ($insert_stmt->execute()) {
                                    $success_message = 'Payment receipt uploaded successfully! It will be verified by admin shortly.';
                                    
                                    // WhatsApp Notifications
                                    if (file_exists('../whatsapp_config.php')) {
                                        require_once '../whatsapp_config.php';
                                        if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
                                            // Fetch Student, Subject, Stream and Teacher info
                                            $info_q = "SELECT u.first_name, u.whatsapp_number, s.name as subject_name, st.name as stream_name, t.first_name as teacher_name
                                                      FROM student_enrollment se
                                                      JOIN users u ON se.student_id = u.user_id
                                                      JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                                                      JOIN subjects s ON ss.subject_id = s.id
                                                      JOIN streams st ON ss.stream_id = st.id
                                                      LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id AND ta.academic_year = se.academic_year AND ta.status = 'active'
                                                      LEFT JOIN users t ON ta.teacher_id = t.user_id
                                                      WHERE se.id = ?";
                                            $i_stmt = $conn->prepare($info_q);
                                            $i_stmt->bind_param("i", $student_enrollment_id);
                                            $i_stmt->execute();
                                            $i_res = $i_stmt->get_result();
                                            if ($i_row = $i_res->fetch_assoc()) {
                                                $s_name = $i_row['first_name'];
                                                $s_wa = $i_row['whatsapp_number'];
                                                $subj = $i_row['subject_name'];
                                                $stream = $i_row['stream_name'];
                                                $teacher = $i_row['teacher_name'] ?? 'Teacher';
                                                $p_type = ucfirst($payment_type);
                                                $month_str = "";
                                                if ($payment_type === 'monthly' && !empty($month)) {
                                                    $month_name = date("F", mktime(0, 0, 0, $month, 10));
                                                    $month_str = "Month: *{$month_name}*\\n";
                                                }
                                                
                                                // 1. Notify Student
                                               $s_msg = "💸 *ඔබගේ මුදල් ගෙවීමට ස්තූතියි!*\n\n" .
         "ආයුබෝවන් {$s_name},\n\n" .
         "ඔබ විසින් සිදු කළ ගෙවීම අපගේ ආයතනය වෙත ලැබී ඇති අතර, එය සමාලෝචනය සඳහා යොමු කර ඇත.\n" .
         "අපගේ ආයතනය විසින් එය කඩිනමින් පරීක්ෂා කර අනුමත කරනු ඇත.\n\n" .
         "ඔබගේ ගෙවීම අනුමත වූ පසු ඔබට දැනුම් දෙනු ලැබේ.\n\n" .
         "------------------------------------\n\n" .
         "💸 *Thank You for Your Payment!*\n\n" .
         "Hello {$s_name},\n\n" . ($month_str ? $month_str . "\n" : "") .
         "Thank you for your payment. It has been received and forwarded to our institution for review.\n" .
         "Our institution will verify and approve it shortly.\n\n" .
         "You will be notified once your payment has been approved.\n\n" .
         "Thank you,\n" .
         "*LearnerX Team*";

sendWhatsAppMessage($s_wa, $s_msg);
                                                // 2. Notify Admin
                                                if (defined('ADMIN_WHATSAPP')) {
                                                    $a_msg = " *New Bank Transfer Payment Pending*\n\n" .
                                                           "Student: *{$s_name}* ({$user_id})\n" .
                                                           "Stream: *{$stream}*\n" .
                                                           "Subject: *{$subj}*\n" .
                                                           "Type: *{$p_type}*\n" . $month_str .
                                                           "Amount: *Rs. " . number_format($amount, 2) . "*\n\n" .
                                                           "Please check the admin panel to verify receipt.";
                                                    sendWhatsAppMessage(ADMIN_WHATSAPP, $a_msg);
                                                }
                                            }
                                            $i_stmt->close();
                                        }
                                    }
                                } else {
                                    $error_message = 'Error submitting payment: ' . $conn->error;
                                    // Delete uploaded file on error
                                    @unlink($filepath);
                                }
                                $insert_stmt->close();
                            } else {
                                // Delete uploaded file if validation failed
                                @unlink($filepath);
                            }
                        } else {
                            $error_message = 'Error uploading file. Please try again.';
                        }
                    }
                }
            } else {
                $error_message = 'Invalid payment method.';
            }
        }
        $verify_stmt->close();
    }
}


// Get enrollment details for display
$enrollment_id = isset($_GET['enrollment_id']) ? intval($_GET['enrollment_id']) : 0;
$payment_type = $_GET['type'] ?? 'monthly'; // 'enrollment' or 'monthly'
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$enrollment = null;
$fee_info = null;

if ($enrollment_id > 0) {
    $query = "SELECT se.*, s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                     ta.id as teacher_assignment_id
              FROM student_enrollment se
              INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id 
                AND ta.academic_year = se.academic_year 
                AND ta.status = 'active'
              WHERE se.id = ? AND se.student_id = ? AND se.status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $enrollment_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $enrollment = $result->fetch_assoc();
        
        // Get fee information
        if ($enrollment['teacher_assignment_id']) {
            $fee_query = "SELECT enrollment_fee, monthly_fee FROM enrollment_fees WHERE teacher_assignment_id = ?";
            $fee_stmt = $conn->prepare($fee_query);
            $fee_stmt->bind_param("i", $enrollment['teacher_assignment_id']);
            $fee_stmt->execute();
            $fee_result = $fee_stmt->get_result();
            if ($fee_result->num_rows > 0) {
                $fee_info = $fee_result->fetch_assoc();
            }
            $fee_stmt->close();
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Form - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Payment Form</h1>
                <a href="payments.php" class="text-red-600 hover:text-red-700 flex items-center">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Payments
                </a>
            </div>

            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6" role="alert">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6" role="alert">
                    <div class="flex">
                        <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($enrollment): ?>
                <!-- Enrollment Info -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Enrollment Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Subject</p>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($enrollment['subject_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Stream</p>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($enrollment['stream_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Academic Year</p>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($enrollment['academic_year']); ?></p>
                        </div>
                        <?php if ($payment_type === 'monthly'): ?>
                            <div>
                                <p class="text-sm text-gray-600">Payment Month</p>
                                <p class="font-semibold text-gray-900"><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Payment Information</h2>
                    
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="payment_type" value="<?php echo htmlspecialchars($payment_type); ?>">
                        <input type="hidden" name="student_enrollment_id" value="<?php echo $enrollment_id; ?>">
                        <?php if ($payment_type === 'monthly'): ?>
                            <input type="hidden" name="month" value="<?php echo $month; ?>">
                            <input type="hidden" name="year" value="<?php echo $year; ?>">
                        <?php endif; ?>
                        
                        <!-- Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0.01" required
                                   value="<?php echo $fee_info ? ($payment_type === 'enrollment' ? $fee_info['enrollment_fee'] : $fee_info['monthly_fee']) : ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        </div>
                        
                        <!-- Payment Method -->
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                    onchange="togglePaymentFields()">
                                <option value="">Select Payment Method</option>
                                <option value="card">Card Payment</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                        
                        <!-- Card Payment Fields -->
                        <div id="cardFields" class="hidden space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="card_number" class="block text-sm font-medium text-gray-700 mb-1">Card Number *</label>
                                    <input type="text" id="card_number" name="card_number" maxlength="19" 
                                           placeholder="1234 5678 9012 3456"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                </div>
                                <div>
                                    <label for="card_holder" class="block text-sm font-medium text-gray-700 mb-1">Card Holder Name *</label>
                                    <input type="text" id="card_holder" name="card_holder" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label for="expiry_month" class="block text-sm font-medium text-gray-700 mb-1">Expiry Month *</label>
                                    <select id="expiry_month" name="expiry_month" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                        <option value="">MM</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                                <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="expiry_year" class="block text-sm font-medium text-gray-700 mb-1">Expiry Year *</label>
                                    <select id="expiry_year" name="expiry_year" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                        <option value="">YYYY</option>
                                        <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="cvv" class="block text-sm font-medium text-gray-700 mb-1">CVV *</label>
                                    <input type="text" id="cvv" name="cvv" maxlength="4" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bank Transfer Fields -->
                        <div id="bankFields" class="hidden">
                            <div>
                                <label for="receipt" class="block text-sm font-medium text-gray-700 mb-1">Upload Payment Receipt *</label>
                                <input type="file" id="receipt" name="receipt" accept="image/*,.pdf"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, GIF, PDF (Max 5MB)</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <a href="payments.php" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                Submit Payment
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-gray-500 text-lg">Invalid enrollment or enrollment not found.</p>
                    <a href="payments.php" class="mt-4 inline-block text-red-600 hover:text-red-700">Back to Payments</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePaymentFields() {
            const method = document.getElementById('payment_method').value;
            const cardFields = document.getElementById('cardFields');
            const bankFields = document.getElementById('bankFields');
            
            if (method === 'card') {
                cardFields.classList.remove('hidden');
                bankFields.classList.add('hidden');
                // Make card fields required
                document.getElementById('card_number').required = true;
                document.getElementById('card_holder').required = true;
                document.getElementById('expiry_month').required = true;
                document.getElementById('expiry_year').required = true;
                document.getElementById('cvv').required = true;
                document.getElementById('receipt').required = false;
            } else if (method === 'bank_transfer') {
                cardFields.classList.add('hidden');
                bankFields.classList.remove('hidden');
                // Make bank fields required
                document.getElementById('receipt').required = true;
                document.getElementById('card_number').required = false;
                document.getElementById('card_holder').required = false;
                document.getElementById('expiry_month').required = false;
                document.getElementById('expiry_year').required = false;
                document.getElementById('cvv').required = false;
            } else {
                cardFields.classList.add('hidden');
                bankFields.classList.add('hidden');
                document.getElementById('card_number').required = false;
                document.getElementById('card_holder').required = false;
                document.getElementById('expiry_month').required = false;
                document.getElementById('expiry_year').required = false;
                document.getElementById('cvv').required = false;
                document.getElementById('receipt').required = false;
            }
        }
        
        // Format card number
        document.getElementById('card_number')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });
    </script>
</body>
</html>

