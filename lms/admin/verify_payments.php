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

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $payment_type = $_POST['payment_type'] ?? ''; // 'enrollment' or 'monthly'
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? ''; // 'approve' or 'reject'
    
    if ($payment_id > 0 && in_array($action, ['approve', 'reject']) && in_array($payment_type, ['enrollment', 'monthly'])) {
        $table = $payment_type === 'enrollment' ? 'enrollment_payments' : 'monthly_payments';
        $status = $action === 'approve' ? 'paid' : 'failed';
        
        if ($action === 'approve') {
            $query = "UPDATE {$table} SET payment_status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $status, $admin_id, $payment_id);
        } else {
            $query = "UPDATE {$table} SET payment_status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $status, $payment_id);
        }
        
        if ($stmt->execute()) {
            $success_message = "Payment {$action}d successfully!";
        } else {
            $error_message = "Error updating payment: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get pending payments
$pending_payments = [];

// Enrollment payments
$enroll_query = "SELECT ep.*, se.student_id, se.stream_subject_id, se.academic_year,
                        u.username, u.first_name, u.second_name, u.email,
                        s.name as stream_name, sub.name as subject_name
                 FROM enrollment_payments ep
                 INNER JOIN student_enrollment se ON ep.student_enrollment_id = se.id
                 INNER JOIN users u ON se.student_id = u.user_id
                 INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                 INNER JOIN streams s ON ss.stream_id = s.id
                 INNER JOIN subjects sub ON ss.subject_id = sub.id
                 WHERE ep.payment_status = 'pending'
                 ORDER BY ep.created_at DESC";
$enroll_result = $conn->query($enroll_query);

while ($row = $enroll_result->fetch_assoc()) {
    $row['payment_type'] = 'enrollment';
    $pending_payments[] = $row;
}

// Monthly payments
$monthly_query = "SELECT mp.*, se.student_id, se.stream_subject_id, se.academic_year,
                         u.username, u.first_name, u.second_name, u.email,
                         s.name as stream_name, sub.name as subject_name
                  FROM monthly_payments mp
                  INNER JOIN student_enrollment se ON mp.student_enrollment_id = se.id
                  INNER JOIN users u ON se.student_id = u.user_id
                  INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                  INNER JOIN streams s ON ss.stream_id = s.id
                  INNER JOIN subjects sub ON ss.subject_id = sub.id
                  WHERE mp.payment_status = 'pending'
                  ORDER BY mp.created_at DESC";
$monthly_result = $conn->query($monthly_query);

while ($row = $monthly_result->fetch_assoc()) {
    $row['payment_type'] = 'monthly';
    $pending_payments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payments - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Verify Payments</h1>
                <p class="text-gray-600">Review and verify pending payment requests</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
                    <p class="text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
                    <p class="text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <!-- Pending Payments -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900">Pending Payments (<?php echo count($pending_payments); ?>)</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($pending_payments)): ?>
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No pending payments to verify.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proof</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pending_payments as $payment): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars(trim(($payment['first_name'] ?? '') . ' ' . ($payment['second_name'] ?? ''))); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo htmlspecialchars($payment['username']); ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($payment['subject_name']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo htmlspecialchars($payment['stream_name']); ?>
                                                </div>
                                                <?php if ($payment['payment_type'] === 'monthly'): ?>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <?php echo date('M Y', mktime(0, 0, 0, $payment['month'], 1, $payment['year'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    <?php echo ucfirst($payment['payment_type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-semibold text-gray-900">
                                                    <?php echo number_format($payment['amount'], 2); ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($payment['created_at'])); ?>
                                                <div class="text-xs text-gray-400">
                                                    <?php echo date('H:i', strtotime($payment['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <?php if ($payment['payment_method'] === 'bank_transfer' && $payment['receipt_path']): ?>
                                                    <button onclick="showReceipt('<?php echo htmlspecialchars($payment['receipt_path']); ?>', '<?php echo htmlspecialchars($payment['receipt_type']); ?>', <?php echo $payment['id']; ?>)" 
                                                            class="px-3 py-1 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors">
                                                        Show
                                                    </button>
                                                <?php elseif ($payment['payment_method'] === 'card' && $payment['card_number']): ?>
                                                    <button onclick="showCardDetails('<?php echo htmlspecialchars($payment['card_number']); ?>', '<?php echo htmlspecialchars($payment['notes'] ?? ''); ?>', <?php echo $payment['id']; ?>)" 
                                                            class="px-3 py-1 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors">
                                                        Show
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="payment_type" value="<?php echo htmlspecialchars($payment['payment_type']); ?>">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" name="verify_payment" value="1" 
                                                                class="px-3 py-1 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="payment_type" value="<?php echo htmlspecialchars($payment['payment_type']); ?>">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" name="verify_payment" value="1" 
                                                                class="px-3 py-1 text-xs font-medium bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </div>
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
    </div>

    <!-- Receipt Modal -->
    <div id="receiptModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-3xl shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">Payment Receipt</h3>
                <button onclick="closeReceiptModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="receiptContent" class="max-h-96 overflow-y-auto">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Card Details Modal -->
    <div id="cardModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">Card Payment Details</h3>
                <button onclick="closeCardModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="cardContent" class="space-y-3">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function showReceipt(receiptPath, receiptType, paymentId) {
            const modal = document.getElementById('receiptModal');
            const content = document.getElementById('receiptContent');
            
            if (receiptType === 'pdf') {
                content.innerHTML = `
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 text-center">
                        <svg class="w-16 h-16 text-red-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <p class="text-gray-700 mb-4">PDF Receipt</p>
                        <a href="../${receiptPath}" 
                           target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Open PDF
                        </a>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <img src="../${receiptPath}" 
                             alt="Payment Receipt" 
                             class="max-w-full h-auto rounded-lg shadow-md mx-auto cursor-pointer"
                             onclick="window.open('../${receiptPath}', '_blank')">
                    </div>
                `;
            }
            
            modal.classList.remove('hidden');
        }

        function closeReceiptModal() {
            document.getElementById('receiptModal').classList.add('hidden');
        }

        function showCardDetails(cardNumber, notes, paymentId) {
            const modal = document.getElementById('cardModal');
            const content = document.getElementById('cardContent');
            
            content.innerHTML = `
                <div class="space-y-3">
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Card Number</p>
                        <p class="text-sm text-gray-900 font-mono">****${cardNumber}</p>
                    </div>
                    ${notes ? `
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-1">Notes</p>
                            <p class="text-sm text-gray-900">${notes}</p>
                        </div>
                    ` : ''}
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeCardModal() {
            document.getElementById('cardModal').classList.add('hidden');
        }

        // Close modals on outside click
        document.getElementById('receiptModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeReceiptModal();
            }
        });

        document.getElementById('cardModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCardModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeReceiptModal();
                closeCardModal();
            }
        });
    </script>
</body>
</html>

