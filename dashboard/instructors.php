<?php
session_start();
require_once '../config.php';
require_once '../whatsapp_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['second_name'] ?? '');
$user_wa = $_SESSION['whatsapp_number'] ?? '';

$success_message = '';
$error_message = '';

// =========================================================================
// INSTRUCTOR LOGIC
// =========================================================================
if ($user_role === 'instructor') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_request'])) {
        $request_id = intval($_POST['request_id']);
        
        // Insert into acceptances table
        $stmt = $conn->prepare("INSERT IGNORE INTO instructor_request_acceptances (request_id, instructor_id) VALUES (?, ?)");
        $stmt->bind_param("is", $request_id, $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "Request accepted successfully!";
            $q = "SELECT u.whatsapp_number, u.first_name, s.name as subject_name FROM instructor_requests ir JOIN users u ON ir.student_id = u.user_id JOIN subjects s ON ir.subject_id = s.id WHERE ir.id = ?";
            $si = $conn->prepare($q); $si->bind_param("i", $request_id); $si->execute(); $req_info = $si->get_result()->fetch_assoc(); $si->close();
            if ($req_info && !empty($req_info['whatsapp_number'])) {
                $msg = "✅ *Instructor Accepted*\n\nYour request for *{$req_info['subject_name']}* has been accepted by $user_name.\nYou will receive the session details shortly.\nContact: $user_wa";
                sendWhatsAppMessage($req_info['whatsapp_number'], $msg);
            }
        }
    }

    $pending_res = $conn->query("SELECT ir.*, s.name as subject_name, u.first_name as student_name FROM instructor_requests ir JOIN subjects s ON ir.subject_id = s.id JOIN users u ON ir.student_id = u.user_id WHERE ir.status = 'pending' AND ir.subject_id IN (SELECT subject_id FROM instructor_subjects WHERE instructor_id = '$user_id') ORDER BY ir.created_at DESC");
    $pending_requests = $pending_res ? $pending_res->fetch_all(MYSQLI_ASSOC) : [];
    
    $my_students_res = $conn->query("SELECT ir.*, s.name as subject_name, u.first_name, u.second_name, u.whatsapp_number FROM instructor_requests ir JOIN subjects s ON ir.subject_id = s.id JOIN users u ON ir.student_id = u.user_id WHERE ir.accepted_by = '$user_id' AND ir.status = 'paid' ORDER BY ir.accepted_at DESC");
    $my_students = $my_students_res ? $my_students_res->fetch_all(MYSQLI_ASSOC) : [];

    // Handle Zoom Details Update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_zoom'])) {
        $rid = intval($_POST['request_id']);
        $z_link = trim($_POST['zoom_link']);
        $z_id = trim($_POST['zoom_meeting_id']);
        $z_pass = trim($_POST['zoom_password']);
        
        $up = $conn->prepare("UPDATE instructor_requests SET zoom_link = ?, zoom_meeting_id = ?, zoom_password = ? WHERE id = ? AND accepted_by = ?");
        $up->bind_param("sssis", $z_link, $z_id, $z_pass, $rid, $user_id);
        if ($up->execute()) {
            $success_message = "Zoom session details updated!";
            // Optional: WhatsApp student
        }
    }

    // History: all requests the instructor has ever acted on (accepted) plus rejected ones in their subjects
    $inst_history_res = $conn->query("SELECT ir.*, s.name as subject_name, u.first_name as student_name, u.second_name as student_last FROM instructor_requests ir JOIN subjects s ON ir.subject_id = s.id JOIN users u ON ir.student_id = u.user_id WHERE ir.subject_id IN (SELECT subject_id FROM instructor_subjects WHERE instructor_id = '$user_id') AND ir.status IN ('accepted','rejected') ORDER BY ir.created_at DESC LIMIT 100");
    $inst_history = $inst_history_res ? $inst_history_res->fetch_all(MYSQLI_ASSOC) : [];
}

// =========================================================================
// STUDENT LOGIC
// =========================================================================
if ($user_role === 'student') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_instructor'])) {
        $subject_id = intval($_POST['subject_id']);
        $session_date = $_POST['session_date'];
        $note = trim($_POST['note'] ?? '');
        
        $stmt = $conn->prepare("INSERT INTO instructor_requests (student_id, subject_id, session_date, request_note, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("siss", $user_id, $subject_id, $session_date, $note);
        if ($stmt->execute()) {
            $request_id = $conn->insert_id;
            $inst_notify = $conn->query("SELECT whatsapp_number FROM users u JOIN instructor_subjects isub ON u.user_id = isub.instructor_id WHERE isub.subject_id = '$subject_id' AND u.status=1")->fetch_all(MYSQLI_ASSOC);
            $sub_name = $conn->query("SELECT name FROM subjects WHERE id='$subject_id'")->fetch_assoc()['name'];
            foreach($inst_notify as $wa) {
                if(!empty($wa['whatsapp_number'])) {
                    $msg = "📢 *New Request*\nSubject: $sub_name\nStudent: $user_name\nDate: $session_date\nPlease log in to the LMS to review.";
                    sendWhatsAppMessage($wa['whatsapp_number'], $msg);
                }
            }
            echo json_encode(['success' => true, 'request_id' => $request_id]);
            exit();
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
            exit();
        }
    }

    if (isset($_GET['check_status'])) {
        $req_id = intval($_GET['check_status']);
        
        // Fetch request basic status
        $req_q = $conn->query("SELECT status, created_at FROM instructor_requests WHERE id = $req_id");
        $req_info = $req_q->fetch_assoc();
        
        if (!$req_info) {
             echo json_encode(['status' => 'not_found']);
             exit();
        }

        if ($req_info['status'] == 'pending') {
            if (strtotime($req_info['created_at']) < time() - (20 * 60)) {
                $conn->query("UPDATE instructor_requests SET status = 'rejected' WHERE id = $req_id");
                $req_info['status'] = 'rejected';
            }
        }

        // Fetch all instructors who accepted this request + their payment status
        $acceptances_q = $conn->prepare("
            SELECT u.user_id as accepted_by, u.first_name, u.second_name, u.profile_picture, u.rating, u.hourly_rate,
                   ip.status as payment_status, isess.zoom_link, isess.zoom_meeting_id, isess.zoom_password
            FROM instructor_request_acceptances ira 
            JOIN users u ON ira.instructor_id = u.user_id 
            JOIN instructor_requests ir ON ira.request_id = ir.id
            LEFT JOIN instructor_payments ip ON ira.request_id = ip.request_id AND ira.instructor_id = ip.instructor_id
            LEFT JOIN instructor_sessions isess ON isess.request_id = ira.request_id
            WHERE ira.request_id = ?
        ");
        $acceptances_q->bind_param("i", $req_id);
        $acceptances_q->execute();
        $acceptances = $acceptances_q->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'status' => $req_info['status'], 
            'acceptances' => $acceptances
        ]);
        exit();
    }

    $all_subjects_res = $conn->query("SELECT s.* FROM subjects s WHERE s.id IN (SELECT DISTINCT subject_id FROM instructor_subjects) AND s.status = 1 ORDER BY s.name");
    $all_subjects = $all_subjects_res ? $all_subjects_res->fetch_all(MYSQLI_ASSOC) : [];

    // Student past requests history with acceptances
    $student_history_res = $conn->query("
        SELECT ir.id, ir.status, ir.session_date, ir.request_note, ir.created_at,
               s.name as subject_name
        FROM instructor_requests ir
        JOIN subjects s ON ir.subject_id = s.id
        WHERE ir.student_id = '$user_id'
        ORDER BY ir.created_at DESC
    ");
    $student_history = $student_history_res ? $student_history_res->fetch_all(MYSQLI_ASSOC) : [];

    // For each request, fetch instructors who accepted, sorted by rating DESC
    foreach ($student_history as &$h) {
        $rid = intval($h['id']);
        $acc_res = $conn->query("
            SELECT u.user_id, u.first_name, u.second_name,
                   u.profile_picture, u.rating, u.hourly_rate,
                   ip.status as payment_status,
                   isess.zoom_link, isess.zoom_meeting_id, isess.zoom_password
            FROM instructor_request_acceptances ira
            JOIN users u ON ira.instructor_id = u.user_id
            JOIN instructor_requests ir ON ira.request_id = ir.id
            LEFT JOIN instructor_payments ip ON ira.request_id = ip.request_id AND ira.instructor_id = ip.instructor_id
            LEFT JOIN instructor_sessions isess ON isess.request_id = ira.request_id
            WHERE ira.request_id = $rid
            ORDER BY u.rating DESC
        ");
        $h['acceptances'] = $acc_res ? $acc_res->fetch_all(MYSQLI_ASSOC) : [];
        // Zoom is stored in instructor_sessions table now, not on instructor_requests
    }
    unset($h);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Portal | LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .form-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); }
        .step-circle { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; transition: all 0.3s; }
        .step-inactive { background: #f1f5f9; color: #94a3b8; }
        .step-active { background: #ef4444; color: white; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.4); }
        .subject-btn { transition: all 0.2s; border: 1.5px solid #f1f5f9; }
        .subject-btn:hover { border-color: #ef4444; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); }
        @keyframes slide { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide { animation: slide 0.4s ease-out forwards; }
    </style>
</head>
<body class="pb-20">
    <?php include 'navbar.php'; ?>

    <!-- Simplified Header -->
    <div class="bg-slate-900 py-12 px-4 shadow-inner">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-2xl md:text-3xl font-bold text-white tracking-tight">පෞද්ගලික උපදේශන සහාය</h1>
            <p class="text-slate-400 text-sm mt-1 font-medium">ඔබේ ඉගනීමේ කටයුතු සඳහා ඉහළ පෙළේ උපදේශකයන් සමඟ 1-on-1 සම්බන්ධ වන්න.</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 -mt-6">

        <?php if ($user_role === 'student'): ?>
        <!-- Student Tabs -->
        <div class="flex gap-2 mb-6">
            <button id="tab_new" onclick="switchTab('new')" class="tab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-900 bg-slate-900 text-white transition shadow-sm">🆕 නව ඉල්ලීම</button>
            <button id="tab_hist" onclick="switchTab('hist')" class="tab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-300 bg-white text-slate-900 transition shadow-sm">🕑 ඉතිහාසය</button>
        </div>
        <div id="panel_new">
            <!-- Multi-step Wizard UI -->
            <div id="mentor_wizard" class="space-y-6">
                
                <!-- Wizard Progress Bar -->
                <div class="form-card flex items-center justify-between py-6 max-w-3xl mx-auto">
                    <div class="flex items-center gap-3">
                        <div id="step1_num" class="step-circle step-active">1</div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-800">විෂය තෝරන්න</span>
                    </div>
                    <div class="h-px bg-slate-100 flex-1 mx-6"></div>
                    <div class="flex items-center gap-3">
                        <div id="step2_num" class="step-circle step-inactive">2</div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">විස්තර එක් කරන්න</span>
                    </div>
                    <div class="h-px bg-slate-100 flex-1 mx-6"></div>
                    <div class="flex items-center gap-3">
                        <div id="step3_num" class="step-circle step-inactive">3</div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">ගුරුවරයෙකු සමඟ සම්බන්ධ වන්න</span>
                    </div>
                </div>

                <!-- Step 1: Subjects Grid -->
                <div id="section_step1" class="animate-slide">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <?php foreach($all_subjects as $sub): ?>
                            <button onclick="selectSubject(<?php echo $sub['id']; ?>, '<?php echo addslashes($sub['name']); ?>')" 
                                    class="subject-btn bg-white p-6 rounded-2xl flex flex-col items-center group text-center">
                                <div class="w-12 h-12 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:bg-red-600 group-hover:text-white transition-colors">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <span class="text-sm font-bold text-slate-700 tracking-tight leading-tight"><?php echo htmlspecialchars($sub['name']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 2: Form -->
                <div id="section_step2" class="hidden animate-slide max-w-xl mx-auto form-card">
                    <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-900">සහාය ඉල්ලීම</h2>
                        <button onclick="goStep1()" class="text-xs font-bold text-slate-400 hover:text-red-600 transition tracking-widest uppercase">ආපසු</button>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">තෝරාගත් විෂය</label>
                            <div id="display_subject" class="bg-slate-50 text-slate-900 p-4 rounded-xl font-bold text-sm border border-slate-100"></div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">කැමති දිනය *</label>
                            <input type="date" id="session_date" class="w-full px-4 py-3 bg-white rounded-xl border border-slate-200 text-sm font-medium focus:border-red-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">විශේෂ අවශ්‍යතා තිබේද?</label>
                            <textarea id="session_note" rows="3" class="w-full px-4 py-3 bg-white rounded-xl border border-slate-200 text-sm font-medium focus:border-red-500 outline-none transition" placeholder="ගුරුවරයා දැනගත යුතු කරුණු මෙහි සඳහන් කරන්න..."></textarea>
                        </div>
                        <button onclick="sendRequest()" id="send_btn" class="w-full bg-slate-900 text-white py-3.5 rounded-xl font-bold text-sm hover:bg-red-600 transition-all flex items-center justify-center gap-2 shadow-lg">
                            ඉල්ලීම යොමු කරන්න <i class="fas fa-paper-plane text-xs opacity-70"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Polling Result -->
                <div id="section_step3" class="hidden animate-slide">
                    <div id="waiting_ui" class="form-card text-center py-16 max-w-xl mx-auto">
                        <div class="w-16 h-16 border-4 border-slate-100 border-t-red-600 rounded-full animate-spin mx-auto mb-6"></div>
                        <h2 class="text-slate-900 font-bold text-lg">ගුරුවරයෙකු සොයා ගනිමින් පවතී...</h2>
                        <p class="text-slate-400 text-sm mt-1">ගුරුවරයෙක් විසින් ඔබගේ ඉල්ලීම තහවුරු කරන තුරු කරුණාකර රැඳී සිටින්න.</p>
                        <p class="text-[10px] text-slate-300 font-bold mt-8 uppercase tracking-[0.2em]">සාමාන්‍යයෙන් විනාඩි 2-5 ක් ගත විය හැක</p>
                    </div>

                    <div id="rejected_ui" class="hidden form-card text-center py-16 max-w-xl mx-auto">
                        <div class="w-12 h-12 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-xl">
                            <i class="fas fa-times"></i>
                        </div>
                        <h2 class="text-slate-900 font-bold">ගුරුවරුන් නොමැත</h2>
                        <p class="text-slate-400 text-sm mt-1">සමාවන්න, මෙම විෂය සඳහා මේ මොහොතේ කිසිදු ගුරුවරයෙකු සම්බන්ධ වී නොමැත.</p>
                        <button onclick="window.location.reload()" class="mt-8 text-sm font-bold text-red-600 hover:underline">වෙනත් විෂයක් තෝරන්න</button>
                    </div>

                    <div id="accepted_ui" class="hidden max-w-6xl mx-auto space-y-8">
                        <div class="text-center">
                            <h2 class="text-2xl font-black text-slate-900 mb-2">ගුරුවරුන් සම්බන්ධ වී ඇත!</h2>
                            <p class="text-slate-400 text-sm">පහත ගුරුවරුන් ඔබේ ඉල්ලීම පිළිගෙන ඇත. ඔබට ඒ අතරින් වඩාත් සුදුසු ගුරුවරයා තෝරාගත හැක.</p>
                        </div>
                        <div id="accepted_grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6"></div>
                    </div>
                </div>
            </div>
        </div><!-- /panel_new -->

        <!-- Student History Panel -->
        <div id="panel_hist" class="hidden">
            <div class="space-y-3">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 mb-4">
                    <i class="fas fa-history text-red-500"></i> අතීත ඉල්ලීම්
                    <span class="text-xs font-normal text-slate-400">— ඉල්ලීමක් ක්ලික් කර ගුරුවරුන් බලන්න</span>
                </h2>
                <?php if (empty($student_history)): ?>
                    <div class="form-card text-center py-10">
                        <p class="text-slate-400 text-sm">තවම ඉල්ලීම් ඉතිහාසයක් නොමැත.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($student_history as $h):
                        $badge = match($h['status']) {
                            'accepted' => ['bg-emerald-100 text-emerald-700', 'පිළිගත් ✓'],
                            'rejected' => ['bg-red-100 text-red-600', 'ප්‍රතික්ෂේප ✗'],
                            default    => ['bg-amber-100 text-amber-700', 'බලාපොරොත්තුවෙන්'],
                        };
                    ?>
                    <!-- Request Card -->
                    <div class="form-card cursor-pointer select-none" onclick="toggleHistory(<?= $h['id'] ?>)">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-book-open text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($h['subject_name']) ?></p>
                                    <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($h['session_date']) ?> &nbsp;·&nbsp; <?= date('Y-m-d', strtotime($h['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black <?= $badge[0] ?>"><?= $badge[1] ?></span>
                                <span class="text-xs font-bold text-slate-400"><?= count($h['acceptances']) ?> ගුරු</span>
                                <i id="arr_<?= $h['id'] ?>" class="fas fa-chevron-down text-slate-300 text-xs transition-transform"></i>
                            </div>
                        </div>

                        <!-- Instructors accordion -->
                        <div id="hist_detail_<?= $h['id'] ?>" class="hidden mt-5 pt-5 border-t border-slate-100" onclick="event.stopPropagation()">
                            <?php if (empty($h['acceptances'])): ?>
                                <p class="text-sm text-slate-400 text-center py-4">මෙම ඉල්ලීම කිසිදු ගුරුවරයෙකු විසින් පිළිගෙන නොමැත.</p>
                            <?php else: ?>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">ඉල්ලීම පිළිගත් ගුරුවරුන් (ශ්‍රේණිගතකිරීම අනුව)</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    <?php foreach ($h['acceptances'] as $ins):
                                        $pic = $ins['profile_picture'] ? '../'.$ins['profile_picture'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                                        $stars = '';
                                        for ($s = 1; $s <= 5; $s++) {
                                            $stars .= '<i class="fas fa-star text-xs ' . ($s <= round($ins['rating'] ?? 0) ? 'text-amber-400' : 'text-slate-200') . '"></i>';
                                        }
                                    ?>
                                    <div class="bg-slate-50 rounded-2xl p-5 flex flex-col items-center text-center border border-slate-100 hover:shadow-md transition">
                                        <div class="relative mb-3">
                                            <img src="<?= htmlspecialchars($pic) ?>" class="w-16 h-16 rounded-xl object-cover border-2 border-white shadow">
                                            <div class="absolute -bottom-1 -right-1 bg-emerald-500 text-white w-5 h-5 rounded-full flex items-center justify-center text-[9px] border border-white">
                                                <i class="fas fa-check"></i>
                                            </div>
                                        </div>
                                        <h4 class="font-bold text-slate-900 text-sm leading-tight"><?= htmlspecialchars($ins['first_name'].' '.$ins['second_name']) ?></h4>
                                        <div class="flex gap-0.5 my-1.5"><?= $stars ?></div>
                                        <p class="text-xs font-black text-slate-600 mb-2">LKR <?= number_format($ins['hourly_rate']) ?>/hr</p>
                                        
                                        <!-- Payment Status Indicator -->
                                        <?php if ($ins['payment_status'] === 'approved'): ?>
                                            <div class="mb-4 flex items-center gap-1.5 bg-emerald-50 text-emerald-600 px-3 py-1.5 rounded-lg border border-emerald-100">
                                                <i class="fas fa-check-circle text-xs"></i>
                                                <span class="text-[9px] font-black uppercase tracking-wider">Payment Approved</span>
                                            </div>
                                        <?php elseif ($ins['payment_status'] === 'pending'): ?>
                                            <div class="mb-4 flex items-center gap-1.5 bg-amber-50 text-amber-600 px-3 py-1.5 rounded-lg border border-amber-100">
                                                <i class="fas fa-clock text-xs"></i>
                                                <span class="text-[9px] font-black uppercase tracking-wider">Payment Pending</span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($ins['payment_status']): ?>
                                            <?php 
                                            $badge_class = match($ins['payment_status']) {
                                                'verified' => 'bg-emerald-100 text-emerald-700',
                                                'pending'  => 'bg-amber-100 text-amber-700',
                                                'rejected' => 'bg-rose-100 text-rose-700',
                                                default    => 'bg-slate-100 text-slate-500'
                                            };
                                            $badge_text = match($ins['payment_status']) {
                                                'verified' => 'Payment Approved ✓',
                                                'pending'  => 'Payment Pending...',
                                                'rejected' => 'Payment Denied ❌',
                                                default    => ucfirst($ins['payment_status'])
                                            };
                                            ?>
                                            <div class="w-full text-center py-2 rounded-xl text-[10px] font-black uppercase tracking-widest <?= $badge_class ?> mb-2">
                                                <?= $badge_text ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php 
                                            // Is this specific instructor's session completed? 
                                            // The whole request status $h['status'] is completed, OR payment is verified.
                                            // Actually, if the request status is completed, the payment was already verified.
                                            $is_completed = ($h['status'] === 'completed' && $ins['payment_status'] === 'verified');
                                        ?>
                                        <?php if ($ins['payment_status'] === 'verified' && !empty($ins['zoom_link'])): ?>
                                            <a href="../player/instructor_zoom.php?request_id=<?= $h['id'] ?>"
                                               class="w-full <?= $is_completed ? 'bg-slate-800 hover:bg-slate-900' : 'bg-indigo-600 hover:bg-indigo-700' ?> text-white py-2.5 rounded-xl text-xs font-extrabold transition flex items-center justify-center gap-2 mb-2">
                                                <i class="fas <?= $is_completed ? 'fa-star text-amber-400' : 'fa-video' ?>"></i> 
                                                <?= $is_completed ? 'View Completed Session' : 'Join Zoom Class' ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!$is_completed): ?>
                                            <a href="inst_payment.php?request_id=<?= $h['id'] ?>&instructor_id=<?= $ins['user_id'] ?>"
                                               class="w-full bg-slate-900 text-white py-2.5 rounded-xl text-xs font-extrabold hover:bg-red-600 transition flex items-center justify-center gap-2">
                                                <?= $ins['payment_status'] ? 'View / Update Payment' : 'ගෙවීම සිදු කරන්න' ?> <i class="fas fa-arrow-right text-[10px]"></i>
                                            </a>
                                        <?php else: ?>
                                            <p class="text-xs font-bold text-slate-400 mt-2"><i class="fas fa-check-circle text-emerald-500"></i> Course Completed</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div><!-- /panel_hist -->

            <script>
                let current_subject_id = null;
                let current_request_id = null;
                let status_interval = null;

                function selectSubject(id, name) {
                    current_subject_id = id;
                    document.getElementById('display_subject').innerText = name;
                    document.getElementById('section_step1').classList.add('hidden');
                    document.getElementById('section_step2').classList.remove('hidden');
                    document.getElementById('step2_num').classList.replace('step-inactive', 'step-active');
                }

                function goStep1() {
                    document.getElementById('section_step2').classList.add('hidden');
                    document.getElementById('section_step1').classList.remove('hidden');
                    document.getElementById('step2_num').classList.replace('step-active', 'step-inactive');
                }

                function sendRequest() {
                    const date = document.getElementById('session_date').value;
                    const note = document.getElementById('session_note').value;
                    if(!date) return alert('කරුණාකර දිනයක් තෝරන්න.');
                    
                    const btn = document.getElementById('send_btn');
                    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> යොමු කරමින්...';

                    const formData = new FormData();
                    formData.append('request_instructor', '1');
                    formData.append('subject_id', current_subject_id);
                    formData.append('session_date', date);
                    formData.append('note', note);

                    fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            current_request_id = data.request_id;
                            startPolling();
                            document.getElementById('section_step2').classList.add('hidden');
                            document.getElementById('section_step3').classList.remove('hidden');
                            document.getElementById('step3_num').classList.replace('step-inactive', 'step-active');
                        } else alert('දෝෂයකි: ' + data.error);
                    });
                }

                function startPolling() {
                    status_interval = setInterval(() => {
                        fetch(window.location.href + '?check_status=' + current_request_id)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'rejected') {
                                clearInterval(status_interval);
                                document.getElementById('waiting_ui').classList.add('hidden');
                                document.getElementById('rejected_ui').classList.remove('hidden');
                            } else if (data.acceptances && data.acceptances.length > 0) {
                                // Don't clear interval, keep polling for new acceptances unless desired
                                showAcceptedInstructor(data.acceptances);
                            }
                        });
                    }, 4000);
                }

                function showAcceptedInstructor(instructors) {
                    document.getElementById('waiting_ui').classList.add('hidden');
                    const accepted_ui = document.getElementById('accepted_ui');
                    accepted_ui.classList.remove('hidden');
                    
                    const grid = document.getElementById('accepted_grid');
                    grid.innerHTML = '';
                    
                    instructors.forEach(ins => {
                        const profile_pic = ins.profile_picture ? '../' + ins.profile_picture : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                        const rating_html = renderStarsJS(ins.rating);
                        
                        const card = `
                            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm hover:shadow-md transition-all animate-slide">
                                <div class="flex flex-col items-center text-center">
                                    <div class="relative mb-4">
                                        <img src="${profile_pic}" class="w-24 h-24 rounded-2xl object-cover border-4 border-slate-50 shadow-sm">
                                        <div class="absolute -bottom-2 -right-2 bg-emerald-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px] border-2 border-white">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-lg font-bold text-slate-900 mb-1 leading-tight">${ins.first_name} ${ins.second_name}</h3>
                                    <div class="flex gap-1 mb-2">${rating_html}</div>
                                    
                                    ${ins.payment_status === 'approved' ? `
                                        <div class="mb-3 flex items-center gap-1.5 bg-emerald-50 text-emerald-600 px-3 py-1.5 rounded-lg border border-emerald-100">
                                            <i class="fas fa-check-circle text-xs"></i>
                                            <span class="text-[9px] font-black uppercase tracking-wider">Payment Approved</span>
                                        </div>
                                    ` : ins.payment_status === 'pending' ? `
                                        <div class="mb-3 flex items-center gap-1.5 bg-amber-50 text-amber-600 px-3 py-1.5 rounded-lg border border-amber-100">
                                            <i class="fas fa-clock text-xs"></i>
                                            <span class="text-[9px] font-black uppercase tracking-wider">Payment Pending</span>
                                        </div>
                                    ` : `
                                        <span class="bg-emerald-50 text-emerald-600 text-[8px] font-black px-2 py-0.5 rounded tracking-widest uppercase mb-3">සුදුසුකම් සහිත ගුරුවරයෙක්</span>
                                    `}
                                    
                                    <div class="w-full bg-slate-50 rounded-xl p-4 mb-6">
                                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1 text-left ml-1">ගාස්තුව</p>
                                        <p class="font-black text-xl text-slate-800 text-left ml-1">LKR ${Math.round(ins.hourly_rate)}.00</p>
                                    </div>
                                    
                                    ${ins.payment_status ? `
                                        <div class="w-full text-center py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest mb-3 ${
                                            ins.payment_status === 'verified' ? 'bg-emerald-100 text-emerald-700' : 
                                            ins.payment_status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'
                                        }">
                                            ${ins.payment_status === 'verified' ? 'Payment Approved ✓' : 
                                              ins.payment_status === 'rejected' ? 'Payment Denied ❌' : 'Payment Pending...'}
                                        </div>
                                    ` : ''}

                                    <a href="inst_payment.php?request_id=${current_request_id}&instructor_id=${ins.accepted_by}" 
                                       class="w-full bg-slate-900 text-white py-3.5 rounded-xl font-bold text-xs hover:bg-red-600 transition-all flex items-center justify-center gap-2 group mb-2">
                                        ${ins.payment_status ? 'View / Update Payment' : 'තහවුරු කර ගෙවීම සිදු කරන්න'}
                                        <i class="fas fa-arrow-right text-[10px] transform group-hover:translate-x-1 transition-transform"></i>
                                    </a>
                                    
                                    ${ins.payment_status === 'verified' && ins.zoom_link ? `
                                        <a href="../player/instructor_zoom.php?request_id=${current_request_id}"
                                           class="w-full bg-indigo-600 text-white py-3.5 rounded-xl font-bold text-xs hover:bg-indigo-700 transition-all flex items-center justify-center gap-2">
                                            <i class="fas fa-video text-[10px]"></i> Join Zoom Class
                                        </a>
                                    ` : ''}
                                </div>
                            </div>`;
                        grid.innerHTML += card;
                    });
                }

                function renderStarsJS(rating) {
                    if(!rating) return '<span class="text-[9px] font-bold text-slate-400">NEW</span>';
                    let stars = '';
                    for (let i = 1; i <= 5; i++) {
                        let color = (i <= Math.round(rating)) ? 'text-amber-400' : 'text-slate-200';
                        stars += `<i class='fas fa-star ${color} text-xs'></i>`;
                    }
                    return stars;
                }
                const TAB_ACTIVE   = 'tab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-900 bg-slate-900 text-white transition shadow-sm';
                const TAB_INACTIVE = 'tab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-300 bg-white text-slate-900 transition shadow-sm';
                function switchTab(tab) {
                    ['new','hist'].forEach(t => {
                        document.getElementById('panel_' + t).classList.toggle('hidden', t !== tab);
                        document.getElementById('tab_' + t).className = (t === tab) ? TAB_ACTIVE : TAB_INACTIVE;
                    });
                }
                function toggleHistory(id) {
                    const detail = document.getElementById('hist_detail_' + id);
                    const arrow  = document.getElementById('arr_' + id);
                    const isOpen = !detail.classList.contains('hidden');
                    detail.classList.toggle('hidden', isOpen);
                    arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                }
            </script>

        <?php elseif ($user_role === 'instructor'): ?>
        <!-- Instructor Tabs -->
        <div class="flex gap-2 mb-6">
            <button id="itab_new" onclick="iSwitchTab('new')" class="itab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-900 bg-slate-900 text-white transition shadow-sm">📥 නව ඉල්ලීම්</button>
            <button id="itab_contacts" onclick="iSwitchTab('contacts')" class="itab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-300 bg-white text-slate-900 hover:border-slate-900 transition shadow-sm">✅ මගේ සම්බන්ධතා</button>
            <button id="itab_hist" onclick="iSwitchTab('hist')" class="itab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-300 bg-white text-slate-900 hover:border-slate-900 transition shadow-sm">🕑 ඉතිහාසය</button>
        </div>
        <!-- Instructor: New Requests Panel -->
        <div id="ipanel_new">
            <div class="space-y-4">
                <?php if (empty($pending_requests)): ?>
                    <div class="form-card text-center py-12"><p class="text-slate-400 text-sm">කිසිදු නව ඉල්ලීමක් නොමැත.</p></div>
                <?php else: ?>
                    <?php foreach ($pending_requests as $req): ?>
                        <div class="form-card animate-slide">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center font-bold text-slate-500"><?= substr($req['student_name'],0,1) ?></div>
                                    <div>
                                        <h4 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($req['student_name']) ?></h4>
                                        <div class="flex gap-2 mt-0.5">
                                            <span class="text-[9px] font-black text-red-600 uppercase tracking-widest"><?= htmlspecialchars($req['subject_name']) ?></span>
                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">• <?= $req['session_date'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-xl mb-4 text-xs text-slate-600 font-medium italic border border-slate-100">"<?= htmlspecialchars($req['request_note'] ?: 'විශේෂ කරුණු කිසිවක් නොමැත.') ?>"</div>
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <button type="submit" name="accept_request" class="w-full bg-slate-900 text-white py-2.5 rounded-lg font-bold text-xs hover:bg-emerald-600 transition shadow-sm">පිළිගන්න</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Instructor: Contacts Panel -->
        <div id="ipanel_contacts" class="hidden">
            <div class="space-y-4">
                <?php if (empty($my_students)): ?>
                    <div class="form-card text-center py-12"><p class="text-slate-400 text-sm">තවමත් තහවුරු කළ සම්බන්ධතා නොමැත.</p></div>
                <?php else: ?>
                    <?php foreach ($my_students as $req): ?>
                        <div class="form-card animate-slide">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center font-bold text-slate-300"><?= substr($req['first_name'],0,1) ?></div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-900"><?= htmlspecialchars($req['first_name'].' '.$req['second_name']) ?></h4>
                                        <div class="flex gap-2">
                                            <span class="text-[9px] font-black text-emerald-600 uppercase tracking-widest"><?= htmlspecialchars($req['subject_name']) ?></span>
                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">• <?= $req['session_date'] ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/','', $req['whatsapp_number']) ?>" class="w-10 h-10 bg-emerald-500 text-white rounded-lg flex items-center justify-center transition hover:bg-emerald-600 shadow-sm"><i class="fab fa-whatsapp"></i></a>
                                </div>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row gap-3">
                                <?php if (!empty($req['zoom_link'])): ?>
                                    <div class="flex-1 bg-indigo-50 border border-indigo-100 p-3 rounded-xl flex items-center justify-between">
                                        <div class="text-[9px] font-bold text-indigo-700 uppercase tracking-widest">
                                            Zoom Session Active
                                        </div>
                                        <button onclick="openZoomModal(<?= $req['id'] ?>, '<?= addslashes($req['zoom_link']) ?>', '<?= addslashes($req['zoom_meeting_id']) ?>', '<?= addslashes($req['zoom_password']) ?>')" 
                                                class="text-[10px] font-black text-indigo-900 hover:underline">Edit</button>
                                    </div>
                                    <a href="<?= htmlspecialchars($req['zoom_link']) ?>" target="_blank" class="bg-indigo-600 text-white px-6 py-3 rounded-xl text-xs font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2">
                                        <i class="fas fa-video"></i> Start Meeting
                                    </a>
                                <?php else: ?>
                                    <button onclick="openZoomModal(<?= $req['id'] ?>)" class="w-full bg-indigo-600 text-white py-3 rounded-xl text-xs font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2 shadow-md">
                                        <i class="fas fa-plus"></i> Create Zoom Class
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Zoom Modal -->
        <div id="zoom_modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl w-full max-w-md p-8 shadow-2xl animate-slide">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-black text-slate-900">Zoom Class Details</h3>
                    <button onclick="closeZoomModal()" class="text-slate-400 hover:text-red-600 transition"><i class="fas fa-times"></i></button>
                </div>
                <form method="POST" class="space-y-5">
                    <input type="hidden" name="update_zoom" value="1">
                    <input type="hidden" name="request_id" id="modal_rid">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Zoom Meeting Link *</label>
                        <input type="url" name="zoom_link" id="modal_link" required placeholder="https://zoom.us/j/..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 outline-none text-sm font-medium transition">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Meeting ID</label>
                            <input type="text" name="zoom_meeting_id" id="modal_zid" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 outline-none text-sm font-medium transition">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Passcode</label>
                            <input type="text" name="zoom_password" id="modal_pass" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 outline-none text-sm font-medium transition">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold text-sm hover:bg-indigo-600 transition-all shadow-lg active:scale-[0.98]">Save Details</button>
                </form>
            </div>
        </div>
        
        <script>
            function openZoomModal(rid, link='', zid='', pass='') {
                document.getElementById('modal_rid').value = rid;
                document.getElementById('modal_link').value = link;
                document.getElementById('modal_zid').value = zid;
                document.getElementById('modal_pass').value = pass;
                document.getElementById('zoom_modal').classList.remove('hidden');
            }
            function closeZoomModal() {
                document.getElementById('zoom_modal').classList.add('hidden');
            }
        </script>

        <!-- Instructor: History Panel -->
        <div id="ipanel_hist" class="hidden">
            <div class="form-card">
                <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-history text-red-500"></i> ඉල්ලීම් ඉතිහාසය</h2>
                <?php if (empty($inst_history)): ?>
                    <p class="text-center text-slate-400 text-sm py-10">ඉතිහාסයක් නොමැත.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-100">
                                <th class="pb-3 pr-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">සිසුවා</th>
                                <th class="pb-3 pr-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">විෂය</th>
                                <th class="pb-3 pr-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">සැසි දිනය</th>
                                <th class="pb-3 pr-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">තත්ත්වය</th>
                                <th class="pb-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">ඉල්ලූ දිනය</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                        <?php foreach ($inst_history as $h): ?>
                            <?php
                                $badge = match($h['status']) {
                                    'accepted' => ['bg-emerald-100 text-emerald-700', 'පිළිගත් ✓'],
                                    'rejected' => ['bg-red-100 text-red-600', 'ප්‍රතික්ෂේප ✗'],
                                    default    => ['bg-amber-100 text-amber-700', 'බලාපොරොත්තුවෙන්'],
                                };
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 pr-4 font-semibold text-slate-800"><?= htmlspecialchars($h['student_name'].' '.$h['student_last']) ?></td>
                                <td class="py-3 pr-4 text-slate-600"><?= htmlspecialchars($h['subject_name']) ?></td>
                                <td class="py-3 pr-4 text-slate-500"><?= htmlspecialchars($h['session_date']) ?></td>
                                <td class="py-3 pr-4"><span class="px-2 py-0.5 rounded text-[10px] font-black <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                                <td class="py-3 text-slate-400 text-xs"><?= date('Y-m-d', strtotime($h['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            const ITAB_ACTIVE   = 'itab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-900 bg-slate-900 text-white transition shadow-sm';
            const ITAB_INACTIVE = 'itab-btn px-6 py-2.5 rounded-lg text-sm font-extrabold border-2 border-slate-300 bg-white text-slate-900 transition shadow-sm';
            function iSwitchTab(tab) {
                ['new','contacts','hist'].forEach(t => {
                    document.getElementById('ipanel_'+t).classList.toggle('hidden', t !== tab);
                    document.getElementById('itab_'+t).className = (t === tab) ? ITAB_ACTIVE : ITAB_INACTIVE;
                });
            }
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
