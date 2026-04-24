<?php
session_start();
require_once '../config.php';

// Check if user is logged in as instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Instructor Payments";
$success_message = '';
$error_message = '';

// Handle Payout Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    $amount = floatval($_POST['payout_amount'] ?? 0);
    
    if ($amount <= 0) {
        $error_message = "Please enter a valid amount.";
    } else {
        // Check current balance
        $stmt = $conn->prepare("SELECT total_points FROM instructor_wallet WHERE instructor_id = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $wallet = $stmt->get_result()->fetch_assoc();
        $current_balance = $wallet ? $wallet['total_points'] : 0;
        
        // Check if there are already pending requests
        $stmt = $conn->prepare("SELECT SUM(amount) as pending_total FROM instructor_payout_requests WHERE instructor_id = ? AND status = 'pending'");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $pending = $stmt->get_result()->fetch_assoc();
        $pending_total = $pending ? $pending['pending_total'] : 0;
        
        if ($amount > ($current_balance - $pending_total)) {
            $error_message = "Insufficient balance. You have Rs. " . number_format($current_balance - $pending_total, 2) . " available for withdrawal.";
        } else {
            $stmt = $conn->prepare("INSERT INTO instructor_payout_requests (instructor_id, amount, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("sd", $user_id, $amount);
            if ($stmt->execute()) {
                $success_message = "Payout request submitted successfully! Admin will review it soon.";
            } else {
                $error_message = "Error submitting request: " . $conn->error;
            }
        }
    }
}

// Fetch Wallet Info
$stmt = $conn->prepare("SELECT * FROM instructor_wallet WHERE instructor_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$wallet = $stmt->get_result()->fetch_assoc();
if (!$wallet) {
    $wallet = ['total_points' => 0.00, 'total_earned' => 0.00, 'total_withdrawn' => 0.00];
}

// Fetch All Payment Attempts (Earnings History)
$earnings_query = "
    SELECT ip.*, s.name as subject_name, u.first_name as student_name
    FROM instructor_payments ip
    JOIN instructor_requests ir ON ip.request_id = ir.id
    JOIN subjects s ON ir.subject_id = s.id
    JOIN users u ON ip.student_id = u.user_id
    WHERE ip.instructor_id = ?
    ORDER BY ip.created_at DESC
";
$stmt = $conn->prepare($earnings_query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$earnings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Auto-Sync Wallet (Ensure all verified payments are reflected)
// This fixes cases where payments were verified before the wallet system was fully integrated
$sync_q = "SELECT SUM(amount) as earned FROM instructor_payments WHERE instructor_id = ? AND status = 'verified'";
$st_sync = $conn->prepare($sync_q);
$st_sync->bind_param("s", $user_id);
$st_sync->execute();
$sync_res = $st_sync->get_result()->fetch_assoc();
$actual_earned = floatval($sync_res['earned'] ?? 0);

// Get withdrawn total
$withdrawn_q = "SELECT SUM(amount) as withdrawn FROM instructor_payout_requests WHERE instructor_id = ? AND status = 'paid'";
$st_with = $conn->prepare($withdrawn_q);
$st_with->bind_param("s", $user_id);
$st_with->execute();
$with_res = $st_with->get_result()->fetch_assoc();
$actual_withdrawn = floatval($with_res['withdrawn'] ?? 0);

$actual_balance = $actual_earned - $actual_withdrawn;

// Update wallet if mismatch found
$check_w = $conn->prepare("SELECT id, total_earned FROM instructor_wallet WHERE instructor_id = ?");
$check_w->bind_param("s", $user_id);
$check_w->execute();
$w_data = $check_w->get_result()->fetch_assoc();

if (!$w_data) {
    $ins_w = $conn->prepare("INSERT INTO instructor_wallet (instructor_id, total_earned, total_points, total_withdrawn) VALUES (?, ?, ?, ?)");
    $ins_w->bind_param("sddd", $user_id, $actual_earned, $actual_balance, $actual_withdrawn);
    $ins_w->execute();
} else if (abs(floatval($w_data['total_earned']) - $actual_earned) > 0.01) {
    $up_w = $conn->prepare("UPDATE instructor_wallet SET total_earned = ?, total_points = ?, total_withdrawn = ? WHERE instructor_id = ?");
    $up_w->bind_param("ddds", $actual_earned, $actual_balance, $actual_withdrawn, $user_id);
    $up_w->execute();
}

// Fetch Payout Requests
$payout_query = "
    SELECT * FROM instructor_payout_requests 
    WHERE instructor_id = ? 
    ORDER BY request_date DESC
";
$stmt = $conn->prepare($payout_query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$payout_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Financial Overview</h1>
                <p class="text-gray-500 mt-1">Manage your earnings and payout requests</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="bg-purple-100 text-purple-800 px-4 py-1.5 rounded-full text-sm font-semibold shadow-sm">Instructor ID: <?php echo htmlspecialchars($user_id); ?></span>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-sm animate-bounce-short">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass-card p-6 rounded-2xl shadow-sm border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Wallet Balance</p>
                        <h3 class="text-3xl font-bold text-gray-900 mt-1">Rs. <?php echo number_format($wallet['total_points'], 2); ?></h3>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-xl">
                        <i class="fas fa-wallet text-purple-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <button onclick="document.getElementById('payoutModal').classList.remove('hidden')" class="w-full bg-purple-600 text-white py-2.5 rounded-xl font-semibold hover:bg-purple-700 transition duration-200 shadow-md flex items-center justify-center">
                        <i class="fas fa-paper-plane mr-2 text-sm"></i> Request Payout
                    </button>
                </div>
            </div>

            <div class="glass-card p-6 rounded-2xl shadow-sm border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Earned</p>
                        <h3 class="text-3xl font-bold text-gray-900 mt-1">Rs. <?php echo number_format($wallet['total_earned'], 2); ?></h3>
                    </div>
                    <div class="bg-green-100 p-3 rounded-xl">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-green-600 mt-4 font-medium"><i class="fas fa-arrow-up mr-1"></i> From verified sessions</p>
            </div>

            <div class="glass-card p-6 rounded-2xl shadow-sm border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Withdrawn</p>
                        <h3 class="text-3xl font-bold text-gray-900 mt-1">Rs. <?php echo number_format($wallet['total_withdrawn'], 2); ?></h3>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-xl">
                        <i class="fas fa-hand-holding-usd text-blue-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-blue-600 mt-4 font-medium">Successfully processed</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Earnings History -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
                <div class="p-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/50">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-history mr-2 text-purple-500"></i> Recent Activity
                    </h2>
                    <span class="text-xs font-medium text-gray-500 uppercase">All Payment Attempts</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xs font-semibold text-gray-500 uppercase bg-gray-50/50">
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Student & Subject</th>
                                <th class="px-6 py-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($earnings)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center text-gray-400 italic">No verified earnings yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($earnings as $earning): 
                                    $status_class = match($earning['status']) {
                                        'verified' => 'text-green-600',
                                        'rejected' => 'text-red-600',
                                        default => 'text-amber-600'
                                    };
                                ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                            <?php echo date('M d, Y', strtotime($earning['created_at'])); ?>
                                            <div class="text-[10px] uppercase font-bold <?php echo $status_class; ?>">
                                                <?php echo $earning['status']; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($earning['student_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($earning['subject_name']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="text-sm font-bold <?php echo $status_class; ?>">
                                                <?php echo ($earning['status'] === 'verified' ? '+' : ''); ?>Rs. <?php echo number_format($earning['amount'], 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payout Requests -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
                <div class="p-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/50">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-external-link-alt mr-2 text-blue-500"></i> Payout Requests
                    </h2>
                    <span class="text-xs font-medium text-gray-500 uppercase">Withdrawal History</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xs font-semibold text-gray-500 uppercase bg-gray-50/50">
                                <th class="px-6 py-4">Request Date</th>
                                <th class="px-6 py-4">Amount</th>
                                <th class="px-6 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($payout_requests)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center text-gray-400 italic">No payout requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payout_requests as $req): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                            <?php echo date('M d, Y', strtotime($req['request_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-bold text-gray-900">
                                            Rs. <?php echo number_format($req['amount'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php 
                                                $status_classes = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                    'paid' => 'bg-green-100 text-green-800 border-green-200',
                                                    'rejected' => 'bg-red-100 text-red-800 border-red-200'
                                                ];
                                                $status_class = $status_classes[$req['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold border <?php echo $status_class; ?>">
                                                <?php echo ucfirst($req['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Payout Request Modal -->
    <div id="payoutModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-8 text-white relative">
                <button onclick="document.getElementById('payoutModal').classList.add('hidden')" class="absolute top-4 right-4 text-white/80 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
                <div class="text-center">
                    <div class="bg-white/20 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-paper-plane text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold">Request Withdrawal</h3>
                    <p class="text-purple-100 text-sm mt-1">Funds will be sent via your registered method</p>
                </div>
            </div>
            <form method="POST" class="p-8 space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Withdrawal Amount (LKR)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                        <input type="number" name="payout_amount" step="0.01" min="1" max="<?php echo $wallet['total_points']; ?>" required 
                               class="w-full border-2 border-gray-100 rounded-2xl pl-12 pr-4 py-4 focus:border-purple-500 focus:ring-0 outline-none transition-all text-lg font-bold" 
                               placeholder="0.00">
                    </div>
                    <p class="text-xs text-gray-400 mt-2 flex items-center">
                        <i class="fas fa-info-circle mr-1.5"></i> Max available: Rs. <?php echo number_format($wallet['total_points'], 2); ?>
                    </p>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100">
                    <div class="flex">
                        <i class="fas fa-shield-alt text-blue-500 mt-1 mr-3"></i>
                        <p class="text-xs text-blue-700 leading-relaxed">
                            Payouts are processed within 3-5 business days. Ensure your payment details are up to date in your profile.
                        </p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="button" onclick="document.getElementById('payoutModal').classList.add('hidden')" 
                            class="flex-1 px-6 py-4 border-2 border-gray-100 rounded-2xl text-gray-600 font-bold hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" name="request_payout" 
                            class="flex-1 px-6 py-4 bg-purple-600 text-white rounded-2xl font-bold hover:bg-purple-700 shadow-lg shadow-purple-200 transform active:scale-95 transition">
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>

