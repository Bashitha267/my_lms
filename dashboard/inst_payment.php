<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = intval($_GET['request_id'] ?? 0);
$instructor_id = $_GET['instructor_id'] ?? '';

if (!$request_id || !$instructor_id) {
    die("Invalid Request parameters.");
}

// Fetch request details
$stmt = $conn->prepare("
    SELECT ir.*, s.name as subject_name, u.first_name, u.second_name, u.hourly_rate, u.user_id as selected_instructor
    FROM instructor_requests ir
    JOIN subjects s ON ir.subject_id = s.id
    JOIN users u ON u.user_id = ?
    WHERE ir.id = ? AND ir.student_id = ?
");
$stmt->bind_param("sis", $instructor_id, $request_id, $user_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
if (!$req) {
    die("Request or Instructor not found.");
}

$success_message = '';
$error_message = '';

// Check if payment already submitted
$existing_payment = null;
$ep_stmt = $conn->prepare("SELECT id, status, created_at, receipt_path FROM instructor_payments WHERE request_id = ? AND instructor_id = ? AND student_id = ?");
$ep_stmt->bind_param("iss", $request_id, $instructor_id, $user_id);
$ep_stmt->execute();
$ep_res = $ep_stmt->get_result();
if ($ep_res->num_rows > 0) {
    $existing_payment = $ep_res->fetch_assoc();
}
$ep_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt']) && !$existing_payment) {
    $upload_dir = '../uploads/instructor_payments/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file_ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
    $new_filename = 'PAY_' . $request_id . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
        $receipt_path = 'uploads/instructor_payments/' . $new_filename;
        
        $conn->begin_transaction();
        try {
            // Use $instructor_id from URL (the one the student chose), NOT $req['accepted_by']
            $ps = $conn->prepare("INSERT INTO instructor_payments (request_id, student_id, instructor_id, amount, receipt_path, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $ps->bind_param("issds", $request_id, $user_id, $instructor_id, $req['hourly_rate'], $receipt_path);
            $ps->execute();
            $payment_id = $conn->insert_id;
            
            $conn->query("UPDATE instructor_requests SET status = 'payment_pending', payment_id = $payment_id, accepted_by = '$instructor_id' WHERE id = $request_id");
            $conn->commit();
            $success_message = "Payment proof submitted successfully! Admin will verify shortly.";
            // Reload existing_payment so the UI updates
            $existing_payment = ['id' => $payment_id, 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'), 'receipt_path' => $receipt_path];
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Failed to upload receipt.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Payment | LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .form-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); }
    </style>
</head>
<body class="pb-20">
    <?php include 'navbar.php'; ?>

    <div class="max-w-xl mx-auto px-4 pt-24">
        <div class="form-card">
            <!-- Header -->
            <div class="mb-8 border-b border-slate-100 pb-6">
                <h1 class="text-xl font-bold text-slate-900">Secure Session Payment</h1>
                <p class="text-slate-500 text-xs mt-1">Finalize your session with <?php echo $req['first_name']; ?></p>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-emerald-50 border border-emerald-100 p-8 rounded-xl text-center">
                    <div class="w-12 h-12 bg-emerald-500 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-lg">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 class="text-lg font-bold text-emerald-900 mb-2">Proof Uploaded Successfully</h2>
                    <p class="text-emerald-700 text-sm italic"><?php echo $success_message; ?></p>
                    <a href="instructors.php" class="inline-block mt-8 bg-slate-900 text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-slate-800 transition">Back to Dashboard</a>
                </div>
            <?php elseif ($existing_payment): ?>
                <?php
                $status_cfg = match($existing_payment['status']) {
                    'verified' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-100', 'icon_bg' => 'bg-emerald-500', 'icon' => 'fa-check-circle', 'title' => 'Payment Approved!', 'msg' => 'Your payment has been verified by the admin. Your session is confirmed.', 'badge' => 'bg-emerald-100 text-emerald-700'],
                    'rejected' => ['bg' => 'bg-rose-50',    'border' => 'border-rose-100',    'icon_bg' => 'bg-rose-500',    'icon' => 'fa-times-circle', 'title' => 'Payment Denied',    'msg' => 'Your payment was not verified. Please contact support or re-submit.', 'badge' => 'bg-rose-100 text-rose-700'],
                    default    => ['bg' => 'bg-amber-50',   'border' => 'border-amber-100',   'icon_bg' => 'bg-amber-500',   'icon' => 'fa-clock',        'title' => 'Payment Pending',   'msg' => 'Your receipt has been submitted and is awaiting admin verification.', 'badge' => 'bg-amber-100 text-amber-700'],
                };
                ?>
                <div class="<?= $status_cfg['bg'] ?> border <?= $status_cfg['border'] ?> p-8 rounded-xl text-center">
                    <div class="w-14 h-14 <?= $status_cfg['icon_bg'] ?> text-white rounded-full flex items-center justify-center mx-auto mb-4 text-xl">
                        <i class="fas <?= $status_cfg['icon'] ?>"></i>
                    </div>
                    <h2 class="text-lg font-bold text-slate-900 mb-2"><?= $status_cfg['title'] ?></h2>
                    <p class="text-slate-600 text-sm mb-4"><?= $status_cfg['msg'] ?></p>
                    <span class="inline-block px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest <?= $status_cfg['badge'] ?>">
                        <?= ucfirst($existing_payment['status']) ?> &bull; <?= date('M d, Y', strtotime($existing_payment['created_at'])) ?>
                    </span>
                    <div class="mt-8">
                        <a href="instructors.php" class="inline-block bg-slate-900 text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-slate-800 transition">Back to Dashboard</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Subject</span>
                            <span class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($req['subject_name']); ?></span>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Total Fee</span>
                            <span class="text-sm font-bold text-red-600">LKR <?php echo number_format($req['hourly_rate'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    <div class="bg-indigo-50/30 border border-indigo-100 p-6 rounded-xl">
                        <h3 class="text-xs font-black text-indigo-700 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="fas fa-university"></i> Bank Transfer Details
                        </h3>
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between border-b border-indigo-50 pb-2">
                                <span class="text-indigo-400 font-bold uppercase">Bank</span>
                                <span class="font-bold text-slate-800">Commercial Bank / Sampath Bank</span>
                            </div>
                            <div class="flex justify-between border-b border-indigo-50 pb-2">
                                <span class="text-indigo-400 font-bold uppercase">Account Name</span>
                                <span class="font-bold text-slate-800 uppercase">LEARNERX HUB PVT LTD</span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span class="text-indigo-400 font-bold uppercase">Account Number</span>
                                <span class="text-lg font-black text-indigo-700 tracking-tight">800 245 7889</span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Upload Transfer Receipt / Screenshot</label>
                            <div class="relative group">
                                <input type="file" name="receipt" id="receipt" required accept="image/*"
                                       class="absolute inset-0 opacity-0 cursor-pointer z-20"
                                       onchange="updateFileName(this)">
                                <div class="w-full bg-slate-50 border-2 border-dashed border-slate-200 rounded-xl p-8 text-center transition-all group-hover:border-indigo-400 hover:bg-slate-50/50">
                                    <div id="upload_icon" class="w-10 h-10 bg-white rounded-lg shadow-sm flex items-center justify-center mx-auto mb-4 text-slate-300 transition-colors">
                                        <i class="fas fa-camera text-sm"></i>
                                    </div>
                                    <p id="file_name" class="font-bold text-slate-500 text-sm">Tap to select image</p>
                                    <p class="text-[9px] text-slate-400 font-bold mt-1 uppercase">Max Size: 5MB</p>
                                </div>
                            </div>
                        </div>

                        <?php if ($error_message): ?>
                            <p class="text-red-600 text-[11px] font-bold text-center bg-red-50 p-3 rounded-lg"><i class="fas fa-exclamation-circle mr-1"></i> <?php echo $error_message; ?></p>
                        <?php endif; ?>

                        <button type="submit" class="w-full bg-slate-900 text-white py-3.5 rounded-xl font-bold text-sm shadow-lg hover:bg-indigo-600 transition-all active:scale-95 flex items-center justify-center gap-2">
                            Submit Payment Proof <i class="fas fa-paper-plane text-[11px]"></i>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : 'Tap to select image';
            document.getElementById('file_name').innerText = fileName;
            document.getElementById('upload_icon').classList.add('text-emerald-500');
        }
    </script>
</body>
</html>
