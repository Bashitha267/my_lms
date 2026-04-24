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
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['second_name'];
$user_wa = $_SESSION['whatsapp_number'] ?? '';

// Handle Zoom Details Update — writes to instructor_sessions (separate table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_zoom'])) {
    $rid = intval($_POST['request_id']);
    $z_link = trim($_POST['zoom_link']);
    $z_id   = trim($_POST['zoom_meeting_id']);
    $z_pass = trim($_POST['zoom_password']);
    
    // Security: only the accepted instructor for this request
    $auth = $conn->prepare("SELECT id FROM instructor_request_acceptances WHERE request_id = ? AND instructor_id = ?");
    $auth->bind_param("is", $rid, $user_id);
    $auth->execute();
    if ($auth->get_result()->num_rows > 0) {
        $up = $conn->prepare("INSERT INTO instructor_sessions (request_id, zoom_link, zoom_meeting_id, zoom_password, status)
                              VALUES (?, ?, ?, ?, 'scheduled')
                              ON DUPLICATE KEY UPDATE zoom_link = VALUES(zoom_link), zoom_meeting_id = VALUES(zoom_meeting_id), zoom_password = VALUES(zoom_password)");
        $up->bind_param("isss", $rid, $z_link, $z_id, $z_pass);
        if ($up->execute()) {
            $success_message = "✅ Zoom session details updated!";
        }
    } else {
        $error_message = "❌ Unauthorized.";
    }
}

$page_title = "Instructor Dashboard";

// =========================================================================
// INSTRUCTOR LOGIC
// =========================================================================
if ($user_role === 'instructor') {
    
    // Handle Acceptance — Multiple instructors CAN accept the same request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_request'])) {
        $request_id = intval($_POST['request_id']);
        
        // Check request still exists and is pending
        $check = $conn->prepare("SELECT id FROM instructor_requests WHERE id = ? AND status = 'pending'");
        $check->bind_param("i", $request_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            // Insert into acceptances (INSERT IGNORE prevents duplicates)
            $stmt = $conn->prepare("INSERT IGNORE INTO instructor_request_acceptances (request_id, instructor_id) VALUES (?, ?)");
            $stmt->bind_param("is", $request_id, $user_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $success_message = "Request accepted! The student will be notified and can choose you.";

                // Notify Student
                $query = "
                    SELECT u.whatsapp_number, u.first_name, s.name as subject_name
                    FROM instructor_requests ir
                    JOIN users u ON ir.student_id = u.user_id
                    JOIN subjects s ON ir.subject_id = s.id
                    WHERE ir.id = ?
                ";
                $stmt_info = $conn->prepare($query);
                $stmt_info->bind_param("i", $request_id);
                $stmt_info->execute();
                $req_info = $stmt_info->get_result()->fetch_assoc();
                $stmt_info->close();

                if ($req_info && !empty($req_info['whatsapp_number'])) {
                    $msg = "✅ *Instructor Accepted Your Request*\n\n" .
                           "Hello {$req_info['first_name']},\n" .
                           "An instructor has accepted your request for *{$req_info['subject_name']}*.\n\n" .
                           "👤 *Instructor:* $user_name\n" .
                           "📞 *WhatsApp:* $user_wa\n\n" .
                           "Log in to the portal to review and confirm.";
                    sendWhatsAppMessage($req_info['whatsapp_number'], $msg);
                }
            } elseif ($stmt->affected_rows === 0) {
                $success_message = "You have already accepted this request.";
            } else {
                $error_message = "Failed to accept request. Please try again.";
            }
            $stmt->close();
        } else {
            $error_message = "This request is no longer available (it may have been cancelled or closed).";
        }
    }

    // Fetch Pending Requests — show ALL pending requests for this instructor's subjects
    // EXCLUDING ones this instructor has already accepted
    $my_subjects_query = "SELECT subject_id FROM instructor_subjects WHERE instructor_id = '" . $conn->real_escape_string($user_id) . "'";
    $pending_query = "
        SELECT 
            ir.id, ir.created_at, ir.request_note, ir.session_date,
            s.name as subject_name,
            u.first_name as student_name
        FROM instructor_requests ir
        JOIN subjects s ON ir.subject_id = s.id
        JOIN users u ON ir.student_id = u.user_id
        WHERE ir.status = 'pending' 
        AND ir.subject_id IN ($my_subjects_query)
        AND ir.id NOT IN (
            SELECT request_id FROM instructor_request_acceptances WHERE instructor_id = '$user_id'
        )
        ORDER BY ir.created_at DESC
    ";
    $pending_res = $conn->query($pending_query);
    $pending_requests = $pending_res ? $pending_res->fetch_all(MYSQLI_ASSOC) : [];

    // Fetch requests this instructor has accepted (awaiting student confirmation)
    $accepted_query = "
        SELECT 
            ir.id, ir.created_at as accepted_at, ir.request_note, ir.session_date,
            s.name as subject_name,
            u.first_name, u.second_name, u.whatsapp_number, u.mobile_number,
            ir.status,
            isess.zoom_link, isess.zoom_meeting_id, isess.zoom_password
        FROM instructor_request_acceptances ira
        JOIN instructor_requests ir ON ira.request_id = ir.id
        JOIN subjects s ON ir.subject_id = s.id
        JOIN users u ON ir.student_id = u.user_id
        LEFT JOIN instructor_sessions isess ON isess.request_id = ir.id
        WHERE ira.instructor_id = ?
        ORDER BY ir.created_at DESC
    ";
    $stmt = $conn->prepare($accepted_query);
    if ($stmt) {
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $accepted_requests = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    } else {
        $accepted_requests = [];
        $error_message = "DB error: " . $conn->error;
    }
}

// =========================================================================
// STUDENT LOGIC
// =========================================================================
if ($user_role === 'student') {
    
    // Handle Request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_instructor'])) {
        $subject_id = intval($_POST['subject_id']);
        $note = trim($_POST['note'] ?? '');
        
        if ($subject_id > 0) {
            $stmt = $conn->prepare("INSERT INTO instructor_requests (student_id, subject_id, request_note, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("sis", $user_id, $subject_id, $note);
            
            if ($stmt->execute()) {
                $success_message = "Request sent to instructors!";
                
                // Notify Instructors
                $sub_res = $conn->query("SELECT name FROM subjects WHERE id = $subject_id")->fetch_assoc();
                $subject_name = $sub_res['name'];
                
                $query = "
                    SELECT u.whatsapp_number 
                    FROM instructor_subjects is_sub
                    JOIN users u ON is_sub.instructor_id = u.user_id
                    WHERE is_sub.subject_id = ? AND u.status = 1 AND u.approved = 1
                ";
                $stmt_notif = $conn->prepare($query);
                $stmt_notif->bind_param("i", $subject_id);
                $stmt_notif->execute();
                $result = $stmt_notif->get_result();
                
                while ($inst = $result->fetch_assoc()) {
                    if (!empty($inst['whatsapp_number'])) {
                        $msg = "📢 *New Request*\nSubject: *$subject_name*\nStudent is waiting.";
                        sendWhatsAppMessage($inst['whatsapp_number'], $msg);
                    }
                }
                $stmt_notif->close();
            } else {
                $error_message = "Error submitting request.";
            }
            $stmt->close();
        }
    }

    // Fetch Subjects
    $subjects_query = "
        SELECT DISTINCT s.id, s.name, s.code
        FROM subjects s
        JOIN instructor_subjects is_sub ON s.id = is_sub.subject_id
        WHERE 1=1
        ORDER BY s.name
    ";
    $subjects = $conn->query($subjects_query)->fetch_all(MYSQLI_ASSOC);

    // Fetch My Requests
    $my_requests_query = "
        SELECT 
            ir.id, ir.status, ir.created_at, ir.request_note,
            s.name as subject_name,
            u.first_name, u.second_name, u.whatsapp_number,
            ir.zoom_link, ir.zoom_meeting_id, ir.zoom_password
        FROM instructor_requests ir
        JOIN subjects s ON ir.subject_id = s.id
        LEFT JOIN users u ON ir.accepted_by = u.user_id
        WHERE ir.student_id = ?
        ORDER BY ir.created_at DESC
    ";
    $stmt = $conn->prepare($my_requests_query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $my_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; }
        .req-card { border: 1px solid #f1f5f9; border-radius: 16px; transition: all 0.2s; }
        .req-card:hover { border-color: #e2e8f0; background-color: white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
    </style>
</head>
<body class="pb-12">
    
    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 pt-8">
        
        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-6 rounded text-sm font-medium"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-4 mb-6 rounded text-sm font-medium"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- ================= INSTRUCTOR VIEW ================= -->
        <?php if ($user_role === 'instructor'): ?>
            <div class="flex justify-between items-center mb-8 border-b pb-6 border-slate-200">
                <h1 class="text-2xl font-bold text-slate-900">Instructor Dashboard</h1>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Logged in as</span>
                    <span class="bg-slate-900 text-white px-3 py-1 rounded text-[10px] font-black uppercase tracking-widest">Instructor</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Pending Requests -->
                <div class="card">
                    <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-bolt text-amber-500"></i> New Session Requests
                    </h2>
                    <div class="space-y-4">
                        <?php if (empty($pending_requests)): ?>
                            <div class="text-center py-16">
                                <i class="fas fa-inbox text-4xl text-slate-200 mb-3 block"></i>
                                <p class="text-slate-400 text-sm font-medium">No new requests at the moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_requests as $req): ?>
                                <div class="req-card bg-slate-50 p-6">
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <span class="text-[10px] bg-indigo-100 text-indigo-700 font-black px-2 py-0.5 rounded uppercase tracking-widest block w-fit mb-2">Subject Group</span>
                                            <h3 class="font-bold text-lg text-slate-900"><?php echo htmlspecialchars($req['subject_name']); ?></h3>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Received</span>
                                            <span class="text-xs font-medium text-slate-600"><?php echo time_elapsed_string($req['created_at']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div class="bg-white p-3 rounded-lg border border-slate-100">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase block mb-1">Session Date</span>
                                            <span class="text-sm font-bold text-slate-700"><?php echo date('M d, Y', strtotime($req['session_date'])); ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg border border-slate-100">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase block mb-1">Student</span>
                                            <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($req['student_name']); ?></span>
                                        </div>
                                    </div>

                                    <div class="bg-blue-50/50 p-4 rounded-lg mb-6 border border-blue-100/50">
                                        <span class="text-[9px] text-blue-400 font-bold uppercase block mb-1">Request Details</span>
                                        <p class="text-sm text-blue-900 font-medium leading-relaxed"><?php echo htmlspecialchars($req['request_note'] ?: "No additional notes provided."); ?></p>
                                    </div>

                                    <form method="POST">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="accept_request" class="w-full bg-slate-900 text-white py-3 rounded-xl font-bold hover:bg-emerald-600 transition-all flex items-center justify-center gap-2">
                                            Accept Request
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Accepted by Me -->
                <div class="card">
                    <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-users text-emerald-500"></i> Requests I've Accepted
                    </h2>
                    <div class="space-y-4">
                        <?php if (empty($accepted_requests)): ?>
                            <div class="text-center py-16">
                                <p class="text-slate-400 text-sm">You haven't accepted any requests yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($accepted_requests as $req): 
                                $statusBg = match($req['status']) {
                                    'paid'     => 'bg-indigo-100 text-indigo-700',
                                    'accepted' => 'bg-emerald-100 text-emerald-700',
                                    'pending'  => 'bg-amber-100 text-amber-700',
                                    'payment_pending' => 'bg-amber-100 text-amber-700',
                                    'completed' => 'bg-slate-200 text-slate-600',
                                    default    => 'bg-slate-100 text-slate-500',
                                };
                                $statusLabel = match($req['status']) {
                                    'paid'     => 'Payment Verified ✓',
                                    'accepted' => 'Confirmed',
                                    'pending'  => 'Awaiting Student',
                                    'payment_pending' => 'Payment Pending',
                                    'completed' => 'Session Completed',
                                    default    => ucfirst($req['status']),
                                };
                            ?>
                                <div class="p-4 border border-slate-100 bg-slate-50/50 rounded-xl">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h3 class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['second_name']); ?></h3>
                                            <p class="text-xs text-slate-500 font-medium uppercase tracking-tighter"><?php echo htmlspecialchars($req['subject_name']); ?></p>
                                            <p class="text-xs text-slate-400 mt-0.5">Session: <?php echo htmlspecialchars($req['session_date'] ?? '—'); ?></p>
                                        </div>
                                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-full <?php echo $statusBg; ?>"><?php echo $statusLabel; ?></span>
                                    </div>
                                    <?php if ($req['status'] === 'paid'): ?>
                                    <div class="flex flex-col gap-2 mt-4 pt-4 border-t border-slate-100">
                                        <?php if (!empty($req['zoom_link'])): ?>
                                            <div class="flex items-center justify-between bg-indigo-50 p-3 rounded-lg border border-indigo-100">
                                                <span class="text-[9px] font-black text-indigo-700 uppercase tracking-widest">Zoom Link Active</span>
                                                <button onclick="openZoomModal(<?php echo $req['id']; ?>, '<?php echo addslashes($req['zoom_link']); ?>', '<?php echo addslashes($req['zoom_meeting_id']); ?>', '<?php echo addslashes($req['zoom_password']); ?>')" class="text-[10px] font-black text-indigo-900 underline">Update</button>
                                            </div>
                                            <a href="../player/instructor_zoom.php?request_id=<?php echo $req['id']; ?>" class="bg-indigo-600 text-white py-2.5 rounded-lg text-xs font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2">
                                                <i class="fas fa-video"></i> Start Session
                                            </a>
                                        <?php else: ?>
                                            <button onclick="openZoomModal(<?php echo $req['id']; ?>)" class="bg-indigo-600 text-white py-2.5 rounded-lg text-xs font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2">
                                                <i class="fas fa-plus"></i> Create Zoom Class
                                            </button>
                                        <?php endif; ?>
                                        <div class="flex gap-2">
                                            <a href="https://wa.me/<?php echo str_replace(['+', ' '], '', $req['whatsapp_number']); ?>" target="_blank" class="flex-1 flex items-center justify-center gap-1 bg-emerald-500 text-white py-2 rounded-lg text-xs font-bold hover:bg-emerald-600 transition">
                                                <i class="fab fa-whatsapp"></i> WhatsApp
                                            </a>
                                            <a href="tel:<?php echo $req['mobile_number']; ?>" class="w-10 h-10 bg-slate-200 text-slate-700 rounded-lg flex items-center justify-center hover:bg-slate-300 transition">
                                                <i class="fas fa-phone text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php elseif ($req['status'] === 'accepted'): ?>
                                    <div class="flex gap-2 mt-3 pt-3 border-t border-slate-100">
                                        <a href="https://wa.me/<?php echo str_replace(['+', ' '], '', $req['whatsapp_number']); ?>" target="_blank" class="flex-1 flex items-center justify-center gap-1 bg-emerald-500 text-white py-2 rounded-lg text-xs font-bold hover:bg-emerald-600 transition">
                                            <i class="fab fa-whatsapp"></i> WhatsApp
                                        </a>
                                        <a href="tel:<?php echo $req['mobile_number']; ?>" class="w-10 h-10 bg-slate-200 text-slate-700 rounded-lg flex items-center justify-center hover:bg-slate-300 transition">
                                            <i class="fas fa-phone text-xs"></i>
                                        </a>
                                    </div>
                                    <?php elseif ($req['status'] === 'pending' || $req['status'] === 'payment_pending'): ?>
                                    <p class="text-[10px] text-amber-600 font-bold mt-2">⏳ Waiting for student to confirm and pay</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Zoom Modal -->
            <div id="zoomModal" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center z-50 backdrop-blur-sm p-4">
                <div class="bg-white rounded-2xl w-full max-w-md p-8 shadow-2xl relative">
                    <button onclick="closeZoomModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition"><i class="fas fa-times"></i></button>
                    <h3 class="text-xl font-bold text-slate-900 mb-6">Setup Zoom Session</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="update_zoom" value="1">
                        <input type="hidden" name="request_id" id="zoomReqId">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Zoom Meeting Link</label>
                            <input type="url" name="zoom_link" id="zoomLink" required placeholder="https://zoom.us/j/..." 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 outline-none transition text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Meeting ID</label>
                                <input type="text" name="zoom_meeting_id" id="zoomId" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 outline-none transition text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Passcode</label>
                                <input type="text" name="zoom_password" id="zoomPass" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 outline-none transition text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold hover:bg-indigo-600 transition shadow-lg">Save Session Details</button>
                    </form>
                </div>
            </div>

            <script>
                function openZoomModal(rid, link='', zid='', pass='') {
                    document.getElementById('zoomReqId').value = rid;
                    document.getElementById('zoomLink').value = link;
                    document.getElementById('zoomId').value = zid;
                    document.getElementById('zoomPass').value = pass;
                    document.getElementById('zoomModal').classList.remove('hidden');
                    document.getElementById('zoomModal').classList.add('flex');
                }
                function closeZoomModal() {
                    document.getElementById('zoomModal').classList.add('hidden');
                    document.getElementById('zoomModal').classList.remove('flex');
                }
            </script>

        <!-- ================= STUDENT VIEW ================= -->
        <?php elseif ($user_role === 'student'): ?>
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 border-b pb-6 border-slate-200">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Request Instructor Session</h1>
                    <p class="text-slate-500 text-sm">Select your subject and get help from expert mentors.</p>
                </div>
                
                <div class="relative w-full md:w-80">
                    <input type="text" id="searchSubject" onkeyup="filterSubjects()" placeholder="Search subjects..." 
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none transition">
                    <i class="fas fa-search absolute left-4 top-3.5 text-slate-300"></i>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($subjects as $sub): ?>
                            <div class="subject-card card flex justify-between items-center group" data-name="<?php echo htmlspecialchars($sub['name'] . ' ' . $sub['code']); ?>">
                                <div>
                                    <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($sub['name']); ?></h3>
                                    <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded uppercase font-black tracking-widest"><?php echo htmlspecialchars($sub['code']); ?></span>
                                </div>
                                <button onclick="openRequestModal(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['name']); ?>')" 
                                        class="bg-indigo-50 text-indigo-700 px-4 py-2 rounded-lg font-bold hover:bg-indigo-600 hover:text-white transition text-sm">
                                    Request
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="card sticky top-8">
                        <h2 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-6 border-b pb-4">My Requests</h2>
                        <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
                            <?php if (empty($my_requests)): ?>
                                <p class="text-slate-400 text-center text-xs italic py-4">No requests found.</p>
                            <?php else: ?>
                                <?php foreach ($my_requests as $req): ?>
                                    <div class="p-4 rounded-xl border <?php echo ($req['status'] === 'accepted') ? 'border-emerald-100 bg-emerald-50/30' : 'border-slate-100 bg-slate-50/50'; ?>">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="font-bold text-xs text-slate-800"><?php echo htmlspecialchars($req['subject_name']); ?></span>
                                            <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full <?php echo ($req['status'] === 'accepted') ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'; ?>"><?php echo $req['status']; ?></span>
                                        </div>
                                        <?php if ($req['status'] === 'paid' || $req['status'] === 'accepted'): ?>
                                            <div class="mt-3 pt-3 border-t border-emerald-100/50">
                                                <div class="flex items-center justify-between mb-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-8 h-8 rounded-full bg-white border border-emerald-100 flex items-center justify-center text-xs text-emerald-600">
                                                            <i class="fas fa-user-tie"></i>
                                                        </div>
                                                        <span class="text-[11px] font-bold text-slate-700"><?php echo htmlspecialchars($req['first_name']); ?></span>
                                                    </div>
                                                    <a href="https://wa.me/<?php echo str_replace(['+', ' '], '', $req['whatsapp_number']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 text-sm font-bold">
                                                        Chat <i class="fab fa-whatsapp ml-1"></i>
                                                    </a>
                                                </div>

                                                <?php if ($req['status'] === 'paid' && !empty($req['zoom_link'])): ?>
                                                    <a href="<?php echo htmlspecialchars($req['zoom_link']); ?>" target="_blank" class="w-full bg-indigo-600 text-white py-2 rounded-lg text-xs font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2 shadow-sm">
                                                        <i class="fas fa-video"></i> Join Zoom Session
                                                    </a>
                                                    <?php if (!empty($req['zoom_password'])): ?>
                                                        <p class="text-[9px] text-slate-400 mt-2 text-center">ID: <?php echo htmlspecialchars($req['zoom_meeting_id']); ?> | Passcode: <span class="font-bold text-slate-600"><?php echo htmlspecialchars($req['zoom_password']); ?></span></p>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="requestModal" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center z-50 backdrop-blur-sm p-4">
                <div class="bg-white rounded-2xl w-full max-w-md p-8 shadow-2xl relative">
                    <button onclick="closeRequestModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition"><i class="fas fa-times"></i></button>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">Request Mentor</h3>
                    <p class="text-slate-500 text-sm mb-6">Subject: <span id="modalSubjectName" class="font-bold text-indigo-600"></span></p>
                    <form method="POST">
                        <input type="hidden" name="subject_id" id="modalSubjectId">
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Detailed Note</label>
                            <textarea name="note" rows="4" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm" placeholder="Tell the instructor what you need help with..."></textarea>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" onclick="closeRequestModal()" class="flex-1 px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                            <button type="submit" name="request_instructor" class="flex-1 px-4 py-3 bg-slate-900 text-white rounded-xl text-sm font-bold hover:bg-slate-800 shadow-xl transition active:scale-95">Send Request</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function openRequestModal(id, name) {
                    document.getElementById('modalSubjectId').value = id;
                    document.getElementById('modalSubjectName').innerText = name;
                    document.getElementById('requestModal').classList.remove('hidden');
                    document.getElementById('requestModal').classList.add('flex');
                }
                function closeRequestModal() {
                    document.getElementById('requestModal').classList.add('hidden');
                    document.getElementById('requestModal').classList.remove('flex');
                }
                function filterSubjects() {
                    let filter = document.getElementById('searchSubject').value.toUpperCase();
                    let cards = document.getElementsByClassName('subject-card');
                    for (let card of cards) {
                        card.style.display = card.dataset.name.toUpperCase().includes(filter) ? "" : "none";
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
