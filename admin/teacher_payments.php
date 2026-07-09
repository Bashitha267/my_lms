<?php
require_once '../check_session.php';
require_once '../config.php';

// Only admins or super_admins can access this page
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: dashboard.php');
    exit;
}

$admin_id = $_SESSION['user_id'] ?? '';
$success_message = '';
$error_message = '';

// Handle manual payment or request processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $target_type = $_POST['target_type']; // 'teacher' or 'instructor'
    $target_id = $_POST['target_id'];
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : null;

    if ($amount <= 0) {
        $error_message = "Invalid amount.";
    } else {
        $conn->begin_transaction();
        try {
            if ($target_type === 'teacher') {
                // Update teacher wallet
                $stmt = $conn->prepare("UPDATE teacher_wallet SET total_points = total_points - ?, total_withdrawn = total_withdrawn + ? WHERE teacher_id = ?");
                $stmt->bind_param("dds", $amount, $amount, $target_id);
                $stmt->execute();
                $stmt->close();

                // If processing a request
                if ($request_id) {
                    $stmt = $conn->prepare("UPDATE teacher_payment_requests SET status = 'paid', processed_date = NOW(), processed_by = ?, admin_notes = ?, payment_method = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $admin_id, $description, $payment_method, $request_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Log manual payment in teacher_payment_requests
                    $stmt = $conn->prepare("INSERT INTO teacher_payment_requests (teacher_id, amount, status, payment_method, request_date, processed_date, admin_notes, processed_by) VALUES (?, ?, 'paid', ?, NOW(), NOW(), ?, ?)");
                    $stmt->bind_param("sdsss", $target_id, $amount, $payment_method, $description, $admin_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // Update instructor wallet
                $stmt = $conn->prepare("UPDATE instructor_wallet SET total_points = total_points - ?, total_withdrawn = total_withdrawn + ? WHERE instructor_id = ?");
                $stmt->bind_param("dds", $amount, $amount, $target_id);
                $stmt->execute();
                $stmt->close();

                // If processing a request
                if ($request_id) {
                    $stmt = $conn->prepare("UPDATE instructor_payout_requests SET status = 'paid', processed_date = NOW(), processed_by = ?, admin_notes = ?, payment_method = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $admin_id, $description, $payment_method, $request_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Log manual payment in instructor_payout_requests
                    $stmt = $conn->prepare("INSERT INTO instructor_payout_requests (instructor_id, amount, status, payment_method, request_date, processed_date, admin_notes, processed_by) VALUES (?, ?, 'paid', ?, NOW(), NOW(), ?, ?)");
                    $stmt->bind_param("sdsss", $target_id, $amount, $payment_method, $description, $admin_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();
            $success_message = "Payment of Rs. " . number_format($amount, 2) . " processed successfully for " . htmlspecialchars($target_id);
            
            // Generate receipt (simple print script)
            echo "<script>
                window.onload = function() {
                    const printWindow = window.open('', '', 'width=600,height=600');
                    printWindow.document.write(`
                        <html><head><title>Payment Receipt</title>
                        <style>body{font-family: Arial, sans-serif; padding:40px; border: 2px solid #333; margin: 20px;} 
                        .header{text-align:center; font-weight:bold; font-size:28px; margin-bottom:10px; color: #dc2626;}
                        .sub-header{text-align:center; font-size:14px; margin-bottom:30px; color: #666;}
                        .row{display:flex; justify-content:between; margin-bottom:15px; border-bottom: 1px dashed #ccc; padding-bottom: 5px;}
                        .label{font-weight:bold; width: 150px;} .value{flex:1;}
                        .total{font-size:22px; font-weight:bold; margin-top:30px; text-align:right; border-top: 2px solid #333; padding-top: 10px;} </style>
                        </head><body>
                        <div class='header'>LEARNER.LK</div>
                        <div class='sub-header'>Payment Voucher</div>
                        <div class='row'><span class='label'>Date:</span> <span class='value'>` + new Date().toLocaleString() + `</span></div>
                        <div class='row'><span class='label'>Payee ID:</span> <span class='value'>" . htmlspecialchars($target_id) . "</span></div>
                        <div class='row'><span class='label'>Role:</span> <span class='value'>" . ucfirst($target_type) . "</span></div>
                        <div class='row'><span class='label'>Method:</span> <span class='value'>" . htmlspecialchars(ucfirst($payment_method)) . "</span></div>
                        <div class='row'><span class='label'>Description:</span> <span class='value'>" . htmlspecialchars($description) . "</span></div>
                        <div class='total'>Paid Amount: Rs. " . number_format($amount, 2) . "</div>
                        <div style='margin-top: 50px; display: flex; justify-content: space-between;'>
                            <div style='border-top: 1px solid #000; width: 200px; text-align: center; font-size: 12px;'>Authorized Signature</div>
                            <div style='border-top: 1px solid #000; width: 200px; text-align: center; font-size: 12px;'>Payee Signature</div>
                        </div>
                        <script>window.print(); window.setTimeout(function(){window.close();}, 500);<\/script>
                        </body></html>
                    `);
                }
            </script>";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch all teachers and their points
$teachers = [];
$t_sql = "SELECT u.user_id, u.first_name, u.second_name, tw.total_points, tw.total_earned, tw.total_withdrawn 
          FROM users u 
          LEFT JOIN teacher_wallet tw ON u.user_id COLLATE utf8mb4_unicode_ci = tw.teacher_id COLLATE utf8mb4_unicode_ci 
          WHERE u.role = 'teacher' 
          ORDER BY u.first_name ASC";
$t_res = $conn->query($t_sql);
while ($row = $t_res->fetch_assoc()) {
    $row['type'] = 'teacher';
    $teachers[] = $row;
}

// Fetch all instructors and their points
$instructors = [];
$i_sql = "SELECT u.user_id, u.first_name, u.second_name, iw.total_points, iw.total_earned, iw.total_withdrawn 
          FROM users u 
          LEFT JOIN instructor_wallet iw ON u.user_id COLLATE utf8mb4_unicode_ci = iw.instructor_id COLLATE utf8mb4_unicode_ci 
          WHERE u.role = 'instructor' 
          ORDER BY u.first_name ASC";
$i_res = $conn->query($i_sql);
while ($row = $i_res->fetch_assoc()) {
    $row['type'] = 'instructor';
    $instructors[] = $row;
}

// Fetch Payout Requests
$requests = [];
$tr_sql = "SELECT tr.*, u.first_name, u.second_name, 'teacher' as type 
           FROM teacher_payment_requests tr 
           JOIN users u ON tr.teacher_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci 
           WHERE tr.status = 'pending' 
           UNION ALL 
           SELECT ir.id, ir.instructor_id as teacher_id, ir.amount, ir.status, ir.payment_method, ir.request_date, ir.processed_date, ir.admin_notes, ir.processed_by, u.first_name, u.second_name, 'instructor' as type 
           FROM instructor_payout_requests ir 
           JOIN users u ON ir.instructor_id COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci 
           WHERE ir.status = 'pending' 
           ORDER BY request_date DESC";
$r_res = $conn->query($tr_sql);
while ($row = $r_res->fetch_assoc()) {
    $requests[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Payouts | Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .payout-card { transition: transform 0.2s; }
        .payout-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'header.php'; ?>

    <main class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-money-check-alt text-blue-600"></i>
                    Teacher & Instructor Payouts
                </h1>
                <p class="mt-1 text-sm text-gray-500">Manage earnings and process payments for all faculty members.</p>
            </div>
            <div class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold shadow-lg flex items-center gap-2">
                <i class="fas fa-shield-alt"></i> Super Admin Mode
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8 rounded shadow-sm flex items-center gap-3">
                <i class="fas fa-check-circle text-xl"></i>
                <div><?php echo $success_message; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-8 rounded shadow-sm flex items-center gap-3">
                <i class="fas fa-exclamation-triangle text-xl"></i>
                <div><?php echo $error_message; ?></div>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: PENDING REQUESTS -->
        <div class="mb-12">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-red-100 text-red-600 w-8 h-8 rounded-full flex items-center justify-center text-sm"><?php echo count($requests); ?></span>
                Pending Payout Requests
            </h2>
            
            <?php if (empty($requests)): ?>
                <div class="bg-white border-2 border-dashed border-gray-200 rounded-xl p-10 text-center">
                    <div class="text-gray-400 mb-3 text-4xl"><i class="fas fa-inbox"></i></div>
                    <p class="text-gray-500 font-medium">No pending requests at the moment.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($requests as $req): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 payout-card">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-lg">
                                        <?php echo strtoupper(substr($req['first_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['second_name']); ?></h3>
                                        <p class="text-xs text-gray-500 font-mono"><?php echo $req['teacher_id']; ?> (<?php echo ucfirst($req['type']); ?>)</p>
                                    </div>
                                </div>
                                <span class="bg-yellow-100 text-yellow-700 text-[10px] font-bold uppercase px-2 py-1 rounded">Pending</span>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                                <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Requested Amount</p>
                                <p class="text-2xl font-black text-gray-900">Rs. <?php echo number_format($req['amount'], 2); ?></p>
                                <p class="text-[10px] text-gray-400 mt-2">Requested on <?php echo date('M d, Y', strtotime($req['request_date'])); ?></p>
                            </div>

                            <button onclick="openPaymentModal('<?php echo $req['type']; ?>', '<?php echo $req['teacher_id']; ?>', <?php echo $req['amount']; ?>, <?php echo $req['id']; ?>)" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-md transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-paper-plane"></i> Process Payment
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 2: WALLET OVERVIEW (ALL TEACHERS) -->
        <div class="mb-12">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Faculty Earnings & Wallets</h2>
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Faculty Member</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Role</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase">Total Earned</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase">Withdrawn</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase">Available Balance</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php $all_faculty = array_merge($teachers, $instructors); ?>
                            <?php foreach ($all_faculty as $f): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($f['first_name'] . ' ' . $f['second_name']); ?></div>
                                        <div class="text-xs text-gray-500 font-mono"><?php echo $f['user_id']; ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded text-[10px] font-bold uppercase <?php echo $f['type'] === 'teacher' ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700'; ?>">
                                            <?php echo $f['type']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-gray-600">Rs. <?php echo number_format($f['total_earned'] ?? 0, 2); ?></td>
                                    <td class="px-6 py-4 text-right font-medium text-red-600">Rs. <?php echo number_format($f['total_withdrawn'] ?? 0, 2); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-lg font-bold <?php echo ($f['total_points'] ?? 0) > 0 ? 'text-green-600' : 'text-gray-400'; ?>">
                                            Rs. <?php echo number_format($f['total_points'] ?? 0, 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="openPaymentModal('<?php echo $f['type']; ?>', '<?php echo $f['user_id']; ?>', 0)" 
                                                class="text-blue-600 hover:text-blue-800 font-bold text-sm flex items-center justify-center gap-1 mx-auto">
                                            <i class="fas fa-hand-holding-usd"></i> Pay Now
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- PAYMENT MODAL -->
    <div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="bg-blue-600 p-6 text-white">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-xl font-bold" id="modalTitle">Process Payout</h2>
                    <button onclick="closeModal()" class="text-white hover:text-gray-200"><i class="fas fa-times"></i></button>
                </div>
                <p class="text-blue-100 text-sm" id="modalSubtitle">Process faculty payment and generate receipt.</p>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="process_payment" value="1">
                <input type="hidden" name="target_type" id="modalTargetType">
                <input type="hidden" name="target_id" id="modalTargetId">
                <input type="hidden" name="request_id" id="modalRequestId">

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payee</label>
                    <div id="modalPayeeDisplay" class="bg-gray-50 px-4 py-3 rounded-xl font-bold text-gray-900 border border-gray-100"></div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount to Pay (Rs.)</label>
                    <input type="number" name="amount" id="modalAmount" required step="0.01" min="0.01"
                           class="w-full bg-white border-2 border-gray-100 rounded-xl px-4 py-3 font-bold text-xl text-blue-600 focus:border-blue-600 focus:outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Method</label>
                    <select name="payment_method" class="w-full bg-white border-2 border-gray-100 rounded-xl px-4 py-3 font-medium text-gray-700 focus:border-blue-600 focus:outline-none transition-colors">
                        <option value="cash">Cash Payment</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description / Notes</label>
                    <textarea name="description" rows="3" placeholder="e.g. Monthly salary payout, Special bonus..."
                              class="w-full bg-white border-2 border-gray-100 rounded-xl px-4 py-3 font-medium text-gray-700 focus:border-blue-600 focus:outline-none transition-colors"></textarea>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="closeModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-4 rounded-2xl transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg transition-all">Confirm & Pay</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPaymentModal(type, id, amount, requestId = null) {
            document.getElementById('modalTargetType').value = type;
            document.getElementById('modalTargetId').value = id;
            document.getElementById('modalAmount').value = amount > 0 ? amount : '';
            document.getElementById('modalRequestId').value = requestId || '';
            document.getElementById('modalPayeeDisplay').innerText = id + ' (' + type.charAt(0).toUpperCase() + type.slice(1) + ')';
            
            if (requestId) {
                document.getElementById('modalTitle').innerText = "Process Payout Request";
                document.getElementById('modalSubtitle').innerText = "Processing an existing faculty request.";
            } else {
                document.getElementById('modalTitle').innerText = "Direct Payout";
                document.getElementById('modalSubtitle').innerText = "Direct manual payment to faculty member.";
            }
            
            document.getElementById('paymentModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }

        // Close modal on escape key
        window.onkeydown = function(event) {
            if (event.keyCode === 27) {
                closeModal();
            }
        };
    </script>
</body>
</html>
