<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');
$current_month = date('n');

// Handle teacher fee setting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_fees']) && $role === 'teacher') {
    $teacher_assignment_id = intval($_POST['teacher_assignment_id'] ?? 0);
    $enrollment_fee = floatval($_POST['enrollment_fee'] ?? 0);
    $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
    
    if ($teacher_assignment_id > 0) {
        // Check if teacher owns this assignment
        $check_query = "SELECT id FROM teacher_assignments WHERE id = ? AND teacher_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("is", $teacher_assignment_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Insert or update fees
            $upsert_query = "INSERT INTO enrollment_fees (teacher_assignment_id, enrollment_fee, monthly_fee) 
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE enrollment_fee = ?, monthly_fee = ?";
            $upsert_stmt = $conn->prepare($upsert_query);
            $upsert_stmt->bind_param("idddd", $teacher_assignment_id, $enrollment_fee, $monthly_fee, $enrollment_fee, $monthly_fee);
            
            if ($upsert_stmt->execute()) {
                $success_message = 'Fees updated successfully!';
            } else {
                $error_message = 'Error updating fees: ' . $conn->error;
            }
            $upsert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get data based on role
$enrollments = [];
$teacher_assignments = [];
$payment_history = [];
$fee_settings = [];

if ($role === 'student') {
    // Get student enrollments with payment status
    $query = "SELECT se.*, s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                     ta.id as teacher_assignment_id,
                     (SELECT COUNT(*) FROM enrollment_payments ep WHERE ep.student_enrollment_id = se.id AND ep.payment_status = 'paid') as enrollment_paid,
                     (SELECT COUNT(*) FROM monthly_payments mp WHERE mp.student_enrollment_id = se.id AND mp.payment_status = 'paid') as monthly_paid_count
              FROM student_enrollment se
              INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id 
                AND ta.academic_year = se.academic_year 
                AND ta.status = 'active'
              WHERE se.student_id = ? AND se.status = 'active'
              ORDER BY se.academic_year DESC, s.name, sub.name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get fee info
        $fee_info = null;
        if ($row['teacher_assignment_id']) {
            $fee_query = "SELECT enrollment_fee, monthly_fee FROM enrollment_fees WHERE teacher_assignment_id = ?";
            $fee_stmt = $conn->prepare($fee_query);
            $fee_stmt->bind_param("i", $row['teacher_assignment_id']);
            $fee_stmt->execute();
            $fee_result = $fee_stmt->get_result();
            if ($fee_result->num_rows > 0) {
                $fee_info = $fee_result->fetch_assoc();
            }
            $fee_stmt->close();
        }
        $row['fee_info'] = $fee_info;
        
        // Check enrollment payment status (pending or paid)
        $enroll_payment_query = "SELECT payment_status FROM enrollment_payments 
                                WHERE student_enrollment_id = ? 
                                ORDER BY created_at DESC LIMIT 1";
        $enroll_payment_stmt = $conn->prepare($enroll_payment_query);
        $enroll_payment_stmt->bind_param("i", $row['id']);
        $enroll_payment_stmt->execute();
        $enroll_payment_result = $enroll_payment_stmt->get_result();
        $enroll_payment_status = null;
        if ($enroll_payment_result->num_rows > 0) {
            $enroll_payment_row = $enroll_payment_result->fetch_assoc();
            $enroll_payment_status = $enroll_payment_row['payment_status'];
        }
        $enroll_payment_stmt->close();
        $row['enroll_payment_status'] = $enroll_payment_status;
        
        // Check monthly payment status for current month
        $monthly_payment_query = "SELECT payment_status FROM monthly_payments 
                                 WHERE student_enrollment_id = ? 
                                   AND month = ? AND year = ?
                                 ORDER BY created_at DESC LIMIT 1";
        $monthly_payment_stmt = $conn->prepare($monthly_payment_query);
        $monthly_payment_stmt->bind_param("iii", $row['id'], $current_month, $current_year);
        $monthly_payment_stmt->execute();
        $monthly_payment_result = $monthly_payment_stmt->get_result();
        $monthly_payment_status = null;
        if ($monthly_payment_result->num_rows > 0) {
            $monthly_payment_row = $monthly_payment_result->fetch_assoc();
            $monthly_payment_status = $monthly_payment_row['payment_status'];
        }
        $monthly_payment_stmt->close();
        $row['monthly_payment_status'] = $monthly_payment_status;
        
        $enrollments[] = $row;
    }
    $stmt->close();
    
    // Get payment history
    $history_query = "SELECT 'enrollment' as type, id, amount, payment_method, payment_status, payment_date, receipt_path, receipt_type, created_at, NULL as period
                      FROM enrollment_payments
                      WHERE student_enrollment_id IN (SELECT id FROM student_enrollment WHERE student_id = ?)
                      UNION ALL
                      SELECT 'monthly' as type, id, amount, payment_method, payment_status, payment_date, receipt_path, receipt_type, created_at,
                             CONCAT(month, '/', year) as period
                      FROM monthly_payments
                      WHERE student_enrollment_id IN (SELECT id FROM student_enrollment WHERE student_id = ?)
                      ORDER BY created_at DESC
                      LIMIT 50";
    $history_stmt = $conn->prepare($history_query);
    if ($history_stmt === false) {
        $error_message = 'Error preparing payment history query: ' . $conn->error;
    } else {
        $history_stmt->bind_param("ss", $user_id, $user_id);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        
        while ($row = $history_result->fetch_assoc()) {
            $payment_history[] = $row;
        }
        $history_stmt->close();
    }
    
} elseif ($role === 'teacher') {
    // Get teacher assignments
    $query = "SELECT ta.*, s.name as stream_name, sub.name as subject_name, sub.code as subject_code
              FROM teacher_assignments ta
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              WHERE ta.teacher_id = ? AND ta.status = 'active'
              ORDER BY ta.academic_year DESC, s.name, sub.name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get fee settings
        $fee_query = "SELECT enrollment_fee, monthly_fee FROM enrollment_fees WHERE teacher_assignment_id = ?";
        $fee_stmt = $conn->prepare($fee_query);
        $fee_stmt->bind_param("i", $row['id']);
        $fee_stmt->execute();
        $fee_result = $fee_stmt->get_result();
        $fee_info = null;
        if ($fee_result->num_rows > 0) {
            $fee_info = $fee_result->fetch_assoc();
        }
        $fee_stmt->close();
        $row['fee_info'] = $fee_info;
        $teacher_assignments[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Payments</h1>
                <p class="text-gray-600">Manage your payments and fees</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message) && !empty($success_message)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
                    <p class="text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message) && !empty($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
                    <p class="text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($role === 'student'): ?>
                <!-- Student View -->
                <div class="space-y-6">
                    <!-- Enrollments with Payment Status -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-2xl font-bold text-gray-900">My Enrollments</h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($enrollments)): ?>
                                <p class="text-gray-500 text-center py-8">No active enrollments found.</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <div class="border border-gray-200 rounded-lg p-6">
                                            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($enrollment['subject_name']); ?></h3>
                                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($enrollment['stream_name']); ?> - <?php echo htmlspecialchars($enrollment['academic_year']); ?></p>
                                                </div>
                                                <div class="mt-4 md:mt-0 flex flex-col md:flex-row gap-3">
                                                    <?php if ($enrollment['fee_info']): ?>
                                                        <!-- Enrollment Payment Button/Status -->
                                                        <?php 
                                                        $enroll_status = $enrollment['enroll_payment_status'] ?? null;
                                                        $show_enroll_button = ($enroll_status === null || $enroll_status === 'failed') 
                                                            && $enrollment['fee_info']['enrollment_fee'] > 0;
                                                        ?>
                                                        
                                                        <?php if ($show_enroll_button): ?>
                                                            <a href="payments_form.php?enrollment_id=<?php echo $enrollment['id']; ?>&type=enrollment" 
                                                               class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-center">
                                                                Pay Enrollment Fee (<?php echo number_format($enrollment['fee_info']['enrollment_fee'], 2); ?>)
                                                            </a>
                                                        <?php elseif ($enroll_status === 'pending'): ?>
                                                            <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-md text-center">
                                                                Enrollment Pending Approval
                                                            </span>
                                                        <?php elseif ($enroll_status === 'paid'): ?>
                                                            <span class="px-4 py-2 bg-green-100 text-green-800 rounded-md text-center">
                                                                Enrollment Paid
                                                            </span>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Monthly Payment Button/Status -->
                                                        <?php 
                                                        $monthly_status = $enrollment['monthly_payment_status'] ?? null;
                                                        $show_monthly_button = ($monthly_status === null || $monthly_status === 'failed') 
                                                            && $enrollment['fee_info']['monthly_fee'] > 0;
                                                        ?>
                                                        
                                                        <?php if ($show_monthly_button): ?>
                                                            <a href="payments_form.php?enrollment_id=<?php echo $enrollment['id']; ?>&type=monthly&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" 
                                                               class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-center">
                                                                Pay Monthly Fee (<?php echo number_format($enrollment['fee_info']['monthly_fee'], 2); ?>)
                                                            </a>
                                                        <?php elseif ($monthly_status === 'pending'): ?>
                                                            <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-md text-center">
                                                                Monthly Payment Pending Approval
                                                            </span>
                                                        <?php elseif ($monthly_status === 'paid'): ?>
                                                            <span class="px-4 py-2 bg-green-100 text-green-800 rounded-md text-center">
                                                                This Month Paid
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-500 text-sm">No fees set for this enrollment</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Payment Status Summary -->
                                            <div class="mt-4 pt-4 border-t border-gray-200">
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                                    <div>
                                                        <p class="text-gray-600">Enrollment Fee</p>
                                                        <p class="font-semibold <?php echo $enrollment['enrollment_paid'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                            <?php echo $enrollment['enrollment_paid'] > 0 ? 'Paid' : 'Pending'; ?>
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p class="text-gray-600">Monthly Payments</p>
                                                        <p class="font-semibold text-gray-900"><?php echo $enrollment['monthly_paid_count']; ?> months paid</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Payment History -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-2xl font-bold text-gray-900">Payment History</h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($payment_history)): ?>
                                <p class="text-gray-500 text-center py-8">No payment history found.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($payment_history as $payment): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo ucfirst($payment['type']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo number_format($payment['amount'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php 
                                                            echo $payment['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                                                ($payment['payment_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                        ?>">
                                                            <?php echo ucfirst($payment['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php 
                                                        // Use payment_date if available (for approved payments), otherwise use created_at (for pending payments)
                                                        $display_date = $payment['payment_date'] ?? $payment['created_at'];
                                                        if ($display_date) {
                                                            echo date('M d, Y', strtotime($display_date));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($role === 'teacher'): ?>
                <!-- Teacher View -->
                <div class="space-y-6">
                    <!-- Fee Settings -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-2xl font-bold text-gray-900">Fee Settings</h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($teacher_assignments)): ?>
                                <p class="text-gray-500 text-center py-8">No active assignments found.</p>
                            <?php else: ?>
                                <div class="space-y-6">
                                    <?php foreach ($teacher_assignments as $assignment): ?>
                                        <div class="border border-gray-200 rounded-lg p-6">
                                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                                <?php echo htmlspecialchars($assignment['subject_name']); ?> - 
                                                <?php echo htmlspecialchars($assignment['stream_name']); ?> 
                                                (<?php echo htmlspecialchars($assignment['academic_year']); ?>)
                                            </h3>
                                            
                                            <form method="POST" action="" class="space-y-4">
                                                <input type="hidden" name="teacher_assignment_id" value="<?php echo $assignment['id']; ?>">
                                                
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="enrollment_fee_<?php echo $assignment['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">
                                                            Enrollment Fee
                                                        </label>
                                                        <input type="number" id="enrollment_fee_<?php echo $assignment['id']; ?>" 
                                                               name="enrollment_fee" step="0.01" min="0"
                                                               value="<?php echo $assignment['fee_info'] ? $assignment['fee_info']['enrollment_fee'] : '0.00'; ?>"
                                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                                                    </div>
                                                    <div>
                                                        <label for="monthly_fee_<?php echo $assignment['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">
                                                            Monthly Fee
                                                        </label>
                                                        <input type="number" id="monthly_fee_<?php echo $assignment['id']; ?>" 
                                                               name="monthly_fee" step="0.01" min="0"
                                                               value="<?php echo $assignment['fee_info'] ? $assignment['fee_info']['monthly_fee'] : '0.00'; ?>"
                                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" name="set_fees" 
                                                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                                    Update Fees
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Payment Statistics -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-2xl font-bold text-gray-900">Payment Statistics</h2>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-500">Payment statistics and student payment status will be displayed here.</p>
                            <!-- TODO: Add payment statistics by month -->
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

