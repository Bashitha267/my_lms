<?php
require_once '../check_session.php';
require_once '../config.php';

// Verify user is admin or super_admin
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: " . BASE_PATH . "login.php?error=" . urlencode("Access denied. Admin only."));
    exit();
}

// Handle POST Actions (e.g. approve user)
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve' && !empty($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("UPDATE users SET approved = 1 WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    if ($stmt->execute()) {
        // Automatically update teacher_assignments to active for this teacher
        $conn->query("UPDATE teacher_assignments SET status = 'active' WHERE teacher_id = '$user_id'");
        $success_message = "User approved successfully.";

        // WhatsApp notification
        if (file_exists('../whatsapp_config.php')) {
            require_once '../whatsapp_config.php';
            if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
                $info_stmt = $conn->prepare("SELECT first_name, whatsapp_number, role FROM users WHERE user_id = ?");
                $info_stmt->bind_param("s", $user_id);
                $info_stmt->execute();
                $u_info = $info_stmt->get_result()->fetch_assoc();
                
                if ($u_info && !empty($u_info['whatsapp_number'])) {
                    $role_name = ucfirst($u_info['role']);
                    if ($u_info['role'] === 'teacher') {
                        $admin_wa = defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : '0768368202';
                        $msg = "✅ *Account Approved / ගිණුම තහවුරු කරන ලදී*\n\n" .
                               "Hello *{$u_info['first_name']}*,\n\n" .
                               "Congratulations! Your teacher account on Lernerr.LK has been approved.\n\n" .
                               "--------------------------\n\n" .
                               "🎉 අප හා ගුරුවරයෙකු ලෙස එකතු වූ ඔබට Lernerr.LK වෙතින් සුබ පැතුම්!\n\n" .
                               "ඔබගේ අයදුම්පත අප විසින් තහවුරු කර ඇත. දැන් ඔබට ඔබගේ ගිණුම වෙත සාර්ථකව පිවිස ඔබගේ පන්ති ආරම්භ කළ හැක.\n\n" .
                               "📌 *නව පන්තියක් ආරම්භ කිරීමට:*\n" .
                               "Profile වෙත පිවිස *Create New Enroll* දී අදාල විෂය ධාරාව හා විෂය තෝරා අදාළ වර්ෂය ඇතුළත් කරන්න.\n\n" .
                               "🎬 *පසුගිය රෙකෝඩින් එකතු කිරීමට:*\n" .
                               "*Recordings* වෙත පිවිස අදාල පන්තිය තෝරා *Add New Recording* ක්ලික් කර ඇතුළත් කළ හැක.\n\n" .
                               "📡 *සජීවී පන්තියක් පැවත්වීමට:*\n" .
                               "*Live Class* වෙත පිවිස ආරම්භ කළ හැක.\n\n" .
                               "📞 *සහාය අවශ්‍ය නම්:*\n" .
                               "අපගේ WhatsApp අංකයට පණිවිඩයක් යොමු කරන්න: *{$admin_wa}*";
                    } else {
                        $msg = "✅ *Account Approved / ගිණුම තහවුරු කරන ලදී*\n\n" .
                               "Hello {$u_info['first_name']},\n" .
                               "Your Lernerr.LK {$role_name} account has been approved. You can now log in to the system.\n\n" .
                               "--------------------------\n\n" .
                               "ඔබේ Lernerr.LK {$role_name} ගිණුම තහවුරු කර ඇත. ඔබට දැන් පද්ධතියට පිවිසිය හැක.";
                    }
                    sendWhatsAppMessage($u_info['whatsapp_number'], $msg);
                }
            }
        }
    } else {
        $error_message = "Error approving user: " . $conn->error;
    }
    $stmt->close();
}

// Get dashboard background image from system settings
$dashboard_background = null;
$bg_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'dashboard_background' LIMIT 1");
if ($bg_stmt) {
    $bg_stmt->execute();
    $bg_result = $bg_stmt->get_result();
    if ($bg_row = $bg_result->fetch_assoc()) {
        $dashboard_background = $bg_row['setting_value'];
    }
    $bg_stmt->close();
}

// 1. Fetch Newly Joined Users (Limit 10 entries)
$new_users = [];
$new_users_res = @$conn->query("SELECT user_id, first_name, second_name, role, registering_date, profile_picture, mobile_number FROM users ORDER BY registering_date DESC LIMIT 10");
if ($new_users_res) {
    while ($row = $new_users_res->fetch_assoc()) {
        $new_users[] = $row;
    }
}

// 2. Fetch Pending User Registration Requests (Limit 5)
$pending_users = [];
try {
    $pu_q = "
        SELECT user_id, first_name, second_name, role, registering_date, email, mobile_number, profile_picture
        FROM users 
        WHERE approved = 0 OR status = 0
        ORDER BY registering_date DESC 
        LIMIT 5
    ";
    $pu_res = @$conn->query($pu_q);
    if ($pu_res) {
        while ($row = $pu_res->fetch_assoc()) {
            $pending_users[] = $row;
        }
    }
} catch (Throwable $e) {
    $pending_users = [];
}

// 3. Fetch Pending Student Payment Requests (Limit 5)
$pending_payments = [];
try {
    $pp_q = "
        SELECT ep.id, ep.amount, ep.payment_method, ep.payment_date, ep.payment_status,
               u.first_name as student_fname, u.second_name as student_sname, u.user_id as student_id,
               sub.name as subject_name, st.name as stream_name, se.academic_year
        FROM enrollment_payments ep
        JOIN student_enrollment se ON ep.student_enrollment_id = se.id
        JOIN users u ON se.student_id = u.user_id
        JOIN stream_subjects ss ON se.stream_subject_id = ss.id
        JOIN streams st ON ss.stream_id = st.id
        JOIN subjects sub ON ss.subject_id = sub.id
        WHERE ep.payment_status = 'pending'
        ORDER BY ep.payment_date DESC, ep.id DESC
        LIMIT 5
    ";
    $pp_res = @$conn->query($pp_q);
    if ($pp_res) {
        while ($row = $pp_res->fetch_assoc()) {
            $pending_payments[] = $row;
        }
    }
} catch (Throwable $e) {
    $pending_payments = [];
}

// 4. Fetch System Stats (Counts for Teachers, Students, Classes)
$count_teachers = 0;
$count_students = 0;
$count_classes = 0;

$t_res = @$conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'teacher'");
if ($t_res) { $count_teachers = (int)($t_res->fetch_assoc()['c'] ?? 0); }

$s_res = @$conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'student'");
if ($s_res) { $count_students = (int)($s_res->fetch_assoc()['c'] ?? 0); }

$c_res = @$conn->query("SELECT COUNT(*) as c FROM teacher_assignments WHERE status = 'active'");
if ($c_res) { $count_classes = (int)($c_res->fetch_assoc()['c'] ?? 0); }

// 5. Fetch Ongoing Live Classes (Limit 5)
$live_classes_list = [];
try {
    $zoom_q = "
        SELECT zc.id, zc.title as subject_name, st.name as stream_name, ta.academic_year,
               u.first_name, u.second_name, u.profile_picture, u.user_id as teacher_id,
               (SELECT COUNT(DISTINCT se.student_id) 
                FROM student_enrollment se 
                WHERE se.stream_subject_id = ta.stream_subject_id 
                  AND se.academic_year = ta.academic_year 
                  AND (se.teacher_id = ta.teacher_id OR se.teacher_id IS NULL) 
                  AND se.status = 'active') as participating_students
        FROM zoom_classes zc
        JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
        JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
        JOIN streams st ON ss.stream_id = st.id
        JOIN subjects sub ON ss.subject_id = sub.id
        JOIN users u ON ta.teacher_id = u.user_id
        WHERE zc.status = 'ongoing'
        ORDER BY zc.created_at DESC
        LIMIT 5
    ";
    $z_res = @$conn->query($zoom_q);
    if ($z_res) {
        while ($row = $z_res->fetch_assoc()) {
            $live_classes_list[] = $row;
        }
    }

    if (empty($live_classes_list)) {
        $rec_q = "
            SELECT r.id, sub.name as subject_name, st.name as stream_name, ta.academic_year,
                   u.first_name, u.second_name, u.profile_picture, u.user_id as teacher_id,
                   (SELECT COUNT(DISTINCT se.student_id) 
                    FROM student_enrollment se 
                    WHERE se.stream_subject_id = ta.stream_subject_id 
                      AND se.academic_year = ta.academic_year 
                      AND (se.teacher_id = ta.teacher_id OR se.teacher_id IS NULL) 
                      AND se.status = 'active') as participating_students
            FROM recordings r
            JOIN stream_subjects ss ON r.stream_subject_id = ss.id
            JOIN streams st ON ss.stream_id = st.id
            JOIN subjects sub ON ss.subject_id = sub.id
            JOIN users u ON r.teacher_id = u.user_id
            JOIN teacher_assignments ta ON (ta.teacher_id = r.teacher_id AND ta.stream_subject_id = r.stream_subject_id)
            WHERE r.is_live = 1 AND r.status = 'ongoing'
            ORDER BY r.created_at DESC
            LIMIT 5
        ";
        $r_res = @$conn->query($rec_q);
        if ($r_res) {
            while ($row = $r_res->fetch_assoc()) {
                $live_classes_list[] = $row;
            }
        }
    }
} catch (Throwable $e) {
    $live_classes_list = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            <?php if ($dashboard_background): ?>
            background-image: url('../<?php echo htmlspecialchars($dashboard_background); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            <?php endif; ?>
        }
        
        <?php if ($dashboard_background): ?>
        .content-overlay {
            background-color: rgba(243, 244, 246, 0.85);
            min-height: 100vh;
        }
        
        .transparent-card {
            background-color: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(10px);
        }
        
        .transparent-card-light {
            background-color: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
        }
        <?php else: ?>
        .transparent-card {
            background-color: #ffffff;
        }
        
        .transparent-card-light {
            background-color: #ffffff;
        }
        <?php endif; ?>

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>
    
    <?php if ($dashboard_background): ?>
    <div class="content-overlay">
    <?php endif; ?>
    
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Dashboard Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            
            <!-- Main Content Area (Left 3 Columns) -->
            <div class="lg:col-span-3 space-y-6">
                
                <!-- 1. Top Header Banner with Stats Badges in Top Right Corner -->
                <div class="transparent-card rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Admin Dashboard</h1>
                            <p class="text-xs text-gray-500 mt-1">Welcome back, <span class="font-bold text-red-600"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>!</p>
                        </div>
                        
                        <!-- Stats Badges in Top Right Corner -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="px-3.5 py-2 bg-red-50/80 border border-red-200/60 rounded-xl flex items-center gap-2.5 shadow-sm">
                                <div class="w-8 h-8 rounded-lg bg-red-600 text-white flex items-center justify-center text-xs font-bold">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-extrabold uppercase text-gray-400">Teachers</span>
                                    <span class="text-sm font-black text-gray-900 font-mono"><?php echo number_format($count_teachers); ?></span>
                                </div>
                            </div>

                            <div class="px-3.5 py-2 bg-blue-50/80 border border-blue-200/60 rounded-xl flex items-center gap-2.5 shadow-sm">
                                <div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center text-xs font-bold">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-extrabold uppercase text-gray-400">Students</span>
                                    <span class="text-sm font-black text-gray-900 font-mono"><?php echo number_format($count_students); ?></span>
                                </div>
                            </div>

                            <div class="px-3.5 py-2 bg-emerald-50/80 border border-emerald-200/60 rounded-xl flex items-center gap-2.5 shadow-sm">
                                <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-extrabold uppercase text-gray-400">Classes</span>
                                    <span class="text-sm font-black text-gray-900 font-mono"><?php echo number_format($count_classes); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Quick Action Shortcut Buttons (Shown Prominently First!) -->
                <div class="transparent-card rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="mb-4 pb-2 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-500"></i>
                            <span>Quick Action Shortcuts</span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <!-- 1. Verify Users -->
                        <a href="users?tab=pending" class="group flex flex-col items-center justify-center p-4 bg-indigo-50/80 hover:bg-indigo-600 rounded-xl border border-indigo-200/80 hover:border-indigo-600 transition-all duration-200 shadow-sm hover:shadow-md text-center">
                            <div class="w-10 h-10 rounded-xl bg-indigo-600 group-hover:bg-white text-white group-hover:text-indigo-600 flex items-center justify-center mb-2 shadow-sm transition-colors text-base">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <span class="text-xs font-extrabold text-indigo-950 group-hover:text-white transition-colors">Verify Users</span>
                        </a>

                        <!-- 2. Verify Payments -->
                        <a href="verify_payments" class="group flex flex-col items-center justify-center p-4 bg-emerald-50/80 hover:bg-emerald-600 rounded-xl border border-emerald-200/80 hover:border-emerald-600 transition-all duration-200 shadow-sm hover:shadow-md text-center">
                            <div class="w-10 h-10 rounded-xl bg-emerald-600 group-hover:bg-white text-white group-hover:text-emerald-600 flex items-center justify-center mb-2 shadow-sm transition-colors text-base">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <span class="text-xs font-extrabold text-emerald-950 group-hover:text-white transition-colors">Verify Payments</span>
                        </a>

                        <!-- 3. Teacher Payouts -->
                        <a href="teacher_payments" class="group flex flex-col items-center justify-center p-4 bg-amber-50/80 hover:bg-amber-500 rounded-xl border border-amber-200/80 hover:border-amber-500 transition-all duration-200 shadow-sm hover:shadow-md text-center">
                            <div class="w-10 h-10 rounded-xl bg-amber-500 group-hover:bg-white text-white group-hover:text-amber-600 flex items-center justify-center mb-2 shadow-sm transition-colors text-base">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <span class="text-xs font-extrabold text-amber-950 group-hover:text-white transition-colors">Teacher Payouts</span>
                        </a>

                        <!-- 4. Manage Content -->
                        <a href="manage_content" class="group flex flex-col items-center justify-center p-4 bg-purple-50/80 hover:bg-purple-600 rounded-xl border border-purple-200/80 hover:border-purple-600 transition-all duration-200 shadow-sm hover:shadow-md text-center">
                            <div class="w-10 h-10 rounded-xl bg-purple-600 group-hover:bg-white text-white group-hover:text-purple-600 flex items-center justify-center mb-2 shadow-sm transition-colors text-base">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <span class="text-xs font-extrabold text-purple-950 group-hover:text-white transition-colors">Manage Content</span>
                        </a>

                        <!-- 5. Messaging -->
                        <a href="mass_messaging" class="group flex flex-col items-center justify-center p-4 bg-sky-50/80 hover:bg-sky-600 rounded-xl border border-sky-200/80 hover:border-sky-600 transition-all duration-200 shadow-sm hover:shadow-md text-center">
                            <div class="w-10 h-10 rounded-xl bg-sky-600 group-hover:bg-white text-white group-hover:text-sky-600 flex items-center justify-center mb-2 shadow-sm transition-colors text-base">
                                <i class="fas fa-comments"></i>
                            </div>
                            <span class="text-xs font-extrabold text-sky-950 group-hover:text-white transition-colors">Messaging</span>
                        </a>

                        <!-- 6. Settings -->
                        <a href="settings" class="group flex flex-col items-center justify-center p-4 bg-slate-100 hover:bg-slate-800 rounded-xl border border-slate-200 hover:border-slate-800 transition-all duration-200 shadow-sm hover:shadow-md text-center">
                            <div class="w-10 h-10 rounded-xl bg-slate-800 group-hover:bg-white text-white group-hover:text-slate-800 flex items-center justify-center mb-2 shadow-sm transition-colors text-base">
                                <i class="fas fa-cog"></i>
                            </div>
                            <span class="text-xs font-extrabold text-slate-800 group-hover:text-white transition-colors">Settings</span>
                        </a>
                    </div>
                </div>

                <!-- 3. Pending User Registration Requests -->
                <div class="transparent-card rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                        <h2 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-user-clock text-amber-500"></i>
                            <span>Pending Registration Requests</span>
                            <span class="text-xs bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded-full border border-amber-200"><?php echo count($pending_users); ?></span>
                        </h2>
                        <a href="users?tab=pending" class="text-xs font-bold text-red-600 hover:text-red-700 hover:underline flex items-center gap-1">
                            <span>See More</span> <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    <?php if (empty($pending_users)): ?>
                        <div class="p-6 text-center text-gray-400 text-xs italic bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                            <i class="fas fa-check-circle text-gray-300 text-2xl mb-1 block"></i>
                            No pending user registration requests.
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($pending_users as $pu): 
                                $pu_name = trim(($pu['first_name'] ?? '') . ' ' . ($pu['second_name'] ?? '')) ?: $pu['user_id'];
                                $role_badge = ($pu['role'] === 'teacher') ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200';
                            ?>
                                <div class="py-3 flex items-center justify-between gap-3 hover:bg-gray-50/80 px-2 rounded-lg transition-colors">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <?php if (!empty($pu['profile_picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($pu['profile_picture']); ?>" class="w-9 h-9 rounded-full object-cover border border-gray-200 flex-shrink-0">
                                        <?php else: ?>
                                            <div class="w-9 h-9 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                <?php echo strtoupper(substr($pu_name, 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-gray-900 truncate"><?php echo htmlspecialchars($pu_name); ?></p>
                                            <p class="text-[11px] text-gray-500 truncate"><?php echo htmlspecialchars($pu['email'] ?? $pu['user_id']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-full border <?php echo $role_badge; ?>">
                                            <?php echo strtoupper($pu['role'] ?? 'user'); ?>
                                        </span>
                                        <button type="button" onclick="viewUser('<?php echo htmlspecialchars($pu['user_id'], ENT_QUOTES); ?>')" class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white font-bold text-[11px] rounded-md shadow-sm transition-colors cursor-pointer">
                                            Review
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 4. Pending Payment Verification Requests -->
                <div class="transparent-card rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                        <h2 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-file-invoice-dollar text-emerald-600"></i>
                            <span>Pending Payment Verification Requests</span>
                            <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full border border-emerald-200"><?php echo count($pending_payments); ?></span>
                        </h2>
                        <a href="verify_payments" class="text-xs font-bold text-red-600 hover:text-red-700 hover:underline flex items-center gap-1">
                            <span>See More</span> <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    <?php if (empty($pending_payments)): ?>
                        <div class="p-6 text-center text-gray-400 text-xs italic bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                            <i class="fas fa-receipt text-gray-300 text-2xl mb-1 block"></i>
                            No pending student payment requests to verify.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-gray-700">
                                <thead class="bg-gray-50 uppercase text-[10px] font-extrabold text-gray-500 border-b border-gray-200">
                                    <tr>
                                        <th class="px-3 py-2.5">Student</th>
                                        <th class="px-3 py-2.5">Subject & Stream</th>
                                        <th class="px-3 py-2.5">Amount</th>
                                        <th class="px-3 py-2.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-medium">
                                    <?php foreach ($pending_payments as $pp): 
                                        $st_name = trim(($pp['student_fname'] ?? '') . ' ' . ($pp['student_sname'] ?? '')) ?: $pp['student_id'];
                                    ?>
                                        <tr class="hover:bg-gray-50/80 transition-colors">
                                            <td class="px-3 py-2.5 font-bold text-gray-900">
                                                <span><?php echo htmlspecialchars($st_name); ?></span>
                                                <span class="block text-[10px] text-gray-400 font-mono"><?php echo htmlspecialchars($pp['student_id']); ?></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-gray-700">
                                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($pp['subject_name']); ?></span>
                                                <span class="block text-[10px] text-gray-400"><?php echo htmlspecialchars($pp['stream_name']); ?></span>
                                            </td>
                                            <td class="px-3 py-2.5 font-extrabold text-emerald-700 font-mono">
                                                LKR <?php echo number_format($pp['amount'], 2); ?>
                                            </td>
                                            <td class="px-3 py-2.5 text-right">
                                                <a href="verify_payments" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] rounded-md shadow-sm transition-colors inline-block">
                                                    Verify Slip
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 5. Ongoing Live Classes Section -->
                <div class="transparent-card rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                        <h2 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping"></span>
                            <span>Ongoing Live Classes</span>
                            <span class="text-xs bg-red-100 text-red-800 font-bold px-2 py-0.5 rounded-full border border-red-200"><?php echo count($live_classes_list); ?></span>
                        </h2>
                        <a href="live_classes" class="text-xs font-bold text-red-600 hover:text-red-700 hover:underline flex items-center gap-1">
                            <span>See More</span> <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    <?php if (empty($live_classes_list)): ?>
                        <div class="p-6 text-center text-gray-400 text-xs italic bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                            <i class="fas fa-video-slash text-gray-300 text-2xl mb-1 block"></i>
                            No live classes are currently ongoing.
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($live_classes_list as $lc): 
                                $t_name = trim(($lc['first_name'] ?? '') . ' ' . ($lc['second_name'] ?? '')) ?: $lc['teacher_id'];
                            ?>
                                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 flex flex-col justify-between">
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <div>
                                            <span class="text-[10px] font-bold text-red-600 uppercase tracking-wider bg-red-50 px-2 py-0.5 rounded border border-red-100">LIVE NOW</span>
                                            <h4 class="font-extrabold text-sm text-gray-900 mt-1"><?php echo htmlspecialchars($lc['subject_name']); ?></h4>
                                            <p class="text-[11px] text-gray-500 font-medium"><?php echo htmlspecialchars($lc['stream_name']); ?> (<?php echo htmlspecialchars($lc['academic_year']); ?>)</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between pt-3 border-t border-gray-200/80 mt-2">
                                        <div class="flex items-center gap-2">
                                            <?php if (!empty($lc['profile_picture'])): ?>
                                                <img src="../<?php echo htmlspecialchars($lc['profile_picture']); ?>" class="w-6 h-6 rounded-full object-cover">
                                            <?php else: ?>
                                                <div class="w-6 h-6 rounded-full bg-red-100 text-red-700 flex items-center justify-center font-bold text-[10px]">
                                                    <?php echo strtoupper(substr($t_name, 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="text-xs font-semibold text-gray-700"><?php echo htmlspecialchars($t_name); ?></span>
                                        </div>
                                        <span class="text-xs font-extrabold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 flex items-center gap-1">
                                            <i class="fas fa-users text-[10px]"></i> <?php echo number_format($lc['participating_students']); ?> Students
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Right Sidebar Area (Right 1 Column) -->
            <div class="lg:col-span-1">
                <!-- Newly Joined Users Card (10 Entries) -->
                <div class="transparent-card rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col h-[600px] sticky top-6">
                    <h3 class="text-base font-extrabold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        <span>Newly Joined Users</span>
                    </h3>
                    <div class="overflow-y-auto space-y-3 flex-1 pr-1 custom-scrollbar">
                        <?php if (empty($new_users)): ?>
                            <p class="text-xs text-gray-500 italic text-center py-8">No recent users.</p>
                        <?php else: ?>
                            <?php foreach ($new_users as $nu): 
                                $nu_name = trim(($nu['first_name'] ?? '') . ' ' . ($nu['second_name'] ?? '')) ?: $nu['user_id'];
                                $nu_role = strtoupper($nu['role'] ?? 'student');
                                $nu_date = $nu['registering_date'] ? date('M d, Y', strtotime($nu['registering_date'])) : 'N/A';
                                
                                $role_class = 'bg-blue-100 text-blue-800 border-blue-200';
                                if ($nu['role'] === 'teacher') $role_class = 'bg-red-100 text-red-800 border-red-200';
                                elseif ($nu['role'] === 'instructor') $role_class = 'bg-purple-100 text-purple-800 border-purple-200';
                                elseif ($nu['role'] === 'admin' || $nu['role'] === 'super_admin') $role_class = 'bg-amber-100 text-amber-800 border-amber-200';
                            ?>
                                <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <?php if ($nu['profile_picture']): ?>
                                        <img src="../<?php echo htmlspecialchars($nu['profile_picture']); ?>" class="w-9 h-9 rounded-full object-cover border border-gray-200 flex-shrink-0">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-bold text-xs flex-shrink-0">
                                            <?php echo strtoupper(substr($nu_name, 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-900 truncate" title="<?php echo htmlspecialchars($nu_name); ?>"><?php echo htmlspecialchars($nu_name); ?></p>
                                        <?php if (!empty($nu['mobile_number'])): ?>
                                            <p class="text-[10px] text-gray-500 font-mono flex items-center gap-1 mt-0.5">
                                                <i class="fas fa-phone text-[9px] text-emerald-600"></i>
                                                <span><?php echo htmlspecialchars($nu['mobile_number']); ?></span>
                                            </p>
                                        <?php endif; ?>
                                        <div class="flex items-center gap-1.5 mt-1">
                                            <span class="px-1.5 py-0.5 text-[9px] font-extrabold rounded-full border <?php echo $role_class; ?>"><?php echo $nu_role; ?></span>
                                            <span class="text-[10px] text-gray-400 font-medium"><?php echo $nu_date; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal" class="fixed inset-0 z-50 overflow-y-auto hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl max-w-2xl w-full shadow-2xl overflow-hidden border border-gray-100 transform transition-all">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-user-circle text-red-600"></i>
                    <span>User Details</span>
                </h3>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl font-bold p-1 rounded-lg hover:bg-gray-100 transition">&times;</button>
            </div>

            <!-- Body -->
            <div id="modalContent" class="p-6 max-h-[70vh] overflow-y-auto">
                <!-- Loaded dynamically via AJAX -->
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                <div id="modalFooterActions"></div>
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-xs font-bold transition">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewUser(userId) {
            document.getElementById('userDetailsModal').classList.remove('hidden');
            document.getElementById('modalContent').innerHTML = `
                <div class="flex justify-center items-center py-12">
                    <svg class="animate-spin h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            `;
            document.getElementById('modalFooterActions').innerHTML = '';

            fetch('get_user_details.php?user_id=' + encodeURIComponent(userId))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderUserDetails(data);
                    } else {
                        document.getElementById('modalContent').innerHTML = `
                            <div class="text-center py-6 text-red-600 font-semibold">
                                Error: ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('modalContent').innerHTML = `
                        <div class="text-center py-6 text-red-600 font-semibold">
                            Failed to fetch user details.
                        </div>
                    `;
                });
        }

        function renderUserDetails(data) {
            const user = data.user;
            const isTeacher = user.role === 'teacher';
            
            const fullName = ((user.first_name || '') + ' ' + (user.second_name || '')).trim() || 'N/A';
            const statusText = user.status == 1 ? 'Active' : 'Inactive';
            const statusClass = user.status == 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
            const approvedText = user.approved == 1 ? 'Approved' : 'Pending';
            const approvedClass = user.approved == 1 ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800';
            const registeringDate = user.registering_date ? new Date(user.registering_date).toLocaleDateString() : 'N/A';
            
            let photoHtml = '';
            if (user.profile_picture) {
                photoHtml = `<img class="h-20 w-20 object-cover rounded-full border-2 border-gray-200" src="../${user.profile_picture}" alt="Profile photo" />`;
            } else {
                const initial = (user.first_name || user.user_id || 'U').substring(0, 1).toUpperCase();
                photoHtml = `
                    <div class="h-20 w-20 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-2xl font-bold">
                        ${initial}
                    </div>
                `;
            }

            let modalHtml = `
                <div class="space-y-6">
                    <!-- User Basic Profile -->
                    <div class="flex items-center space-x-6 pb-6 border-b border-gray-100">
                        <div class="shrink-0">${photoHtml}</div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">${fullName}</h3>
                            <p class="text-sm text-gray-500">${user.email || 'No email available'}</p>
                            <div class="mt-2 flex space-x-2">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">${user.role.toUpperCase()}</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full ${statusClass}">${statusText}</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full ${approvedClass}">${approvedText}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="block text-xs text-gray-500 uppercase font-semibold">User ID</span>
                            <span class="text-gray-900 font-medium">${user.user_id}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 uppercase font-semibold">Registering Date</span>
                            <span class="text-gray-900 font-medium">${registeringDate}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 uppercase font-semibold">Mobile Number</span>
                            <span class="text-gray-900 font-medium">${user.mobile_number || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 uppercase font-semibold">WhatsApp Number</span>
                            <span class="text-gray-900 font-medium">${user.whatsapp_number || 'N/A'}</span>
                        </div>
                    </div>
            `;

            if (isTeacher) {
                // Education Details
                let eduHtml = `
                    <div class="pt-6 border-t border-gray-100">
                        <h4 class="text-md font-bold text-gray-800 mb-3">Education Details</h4>
                `;
                if (data.education && data.education.length > 0) {
                    eduHtml += `
                        <div class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Qualification</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Institution</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Year</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                    `;
                    data.education.forEach(edu => {
                        eduHtml += `
                            <tr>
                                <td class="px-4 py-2 text-gray-900 font-medium">${edu.qualification}</td>
                                <td class="px-4 py-2 text-gray-500">${edu.institution || 'N/A'}</td>
                                <td class="px-4 py-2 text-gray-500">${edu.year_obtained || 'N/A'}</td>
                            </tr>
                        `;
                    });
                    eduHtml += `
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    eduHtml += `<p class="text-xs text-gray-500 italic">No education details recorded.</p>`;
                }
                eduHtml += `</div>`;
                modalHtml += eduHtml;

                // Assigned Classes
                let assignHtml = `
                    <div class="pt-6 border-t border-gray-100">
                        <h4 class="text-md font-bold text-gray-800 mb-3">Assigned Classes</h4>
                `;
                if (data.assignments && data.assignments.length > 0) {
                    assignHtml += `
                        <div class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Academic Year</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Stream</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Subject</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                    `;
                    data.assignments.forEach(asg => {
                        assignHtml += `
                            <tr>
                                <td class="px-4 py-2 text-gray-900 font-medium">${asg.academic_year}</td>
                                <td class="px-4 py-2 text-gray-500">${asg.stream_name}</td>
                                <td class="px-4 py-2 text-gray-500">${asg.subject_name}</td>
                            </tr>
                        `;
                    });
                    assignHtml += `
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    assignHtml += `<p class="text-xs text-gray-500 italic">No classes assigned.</p>`;
                }
                assignHtml += `</div>`;
                modalHtml += assignHtml;
            }

            modalHtml += `</div>`;
            document.getElementById('modalContent').innerHTML = modalHtml;

            const footerActions = document.getElementById('modalFooterActions');
            if (user.approved == 0) {
                footerActions.innerHTML = `
                    <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to approve this user?');">
                        <input type="hidden" name="user_id" value="${user.user_id}">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-medium text-sm transition-colors flex items-center space-x-1 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Approve User</span>
                        </button>
                    </form>
                `;
            } else {
                footerActions.innerHTML = '';
            }
        }

        function closeModal() {
            document.getElementById('userDetailsModal').classList.add('hidden');
        }
    </script>
</body>
</html>
