<?php
require_once '../check_session.php';
require_once '../config.php';

// Only admins can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';
$active_tab = $_GET['tab'] ?? 'pending';

// Handle Approval/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_assignment_rate'])) {
        $assignment_id = intval($_POST['assignment_id']);
        $new_rate = floatval($_POST['commission_rate']);
        
        $stmt = $conn->prepare("UPDATE teacher_assignments SET commission_rate = ? WHERE id = ?");
        $stmt->bind_param("di", $new_rate, $assignment_id);
        if ($stmt->execute()) {
            $success_message = "Assignment rate updated to $new_rate%.";
        } else {
            $error_message = "Error: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['update_teacher'])) {
        $teacher_id = $_POST['teacher_id'];
        $action = $_POST['action']; // 'approve' or 'update'
        $new_rate = floatval($_POST['commission_rate']);
        
        if ($teacher_id && $new_rate >= 0 && $new_rate <= 100) {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE users SET approved = 1 WHERE user_id = ?");
                $stmt->bind_param("s", $teacher_id);
                if ($stmt->execute()) {
                    // Also update all their assignments with the approved rate as default
                    $conn->query("UPDATE teacher_assignments SET commission_rate = $new_rate WHERE teacher_id = '$teacher_id'");
                    $success_message = "Teacher approved successfully.";
                } else {
                    $error_message = "Error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

// Fetch Teachers
$search = $_GET['search'] ?? '';
$where_clause = "";
if ($search) {
    $search_safe = $conn->real_escape_string($search);
    $where_clause = " AND (first_name LIKE '%$search_safe%' OR second_name LIKE '%$search_safe%' OR mobile_number LIKE '%$search_safe%' OR user_id LIKE '%$search_safe%')";
}

$pending_teachers = [];
$active_teachers = [];

// Separate queries to avoid long processing
$query_p = "SELECT * FROM users WHERE role = 'teacher' AND approved = 0 $where_clause ORDER BY registering_date DESC";
$res_p = $conn->query($query_p);
while ($row = $res_p->fetch_assoc()) $pending_teachers[] = $row;

$query_a = "SELECT * FROM users WHERE role = 'teacher' AND approved = 1 $where_clause ORDER BY first_name ASC";
$res_a = $conn->query($query_a);
while ($row = $res_a->fetch_assoc()) $active_teachers[] = $row;

$teachers = ($active_tab === 'pending') ? $pending_teachers : $active_teachers;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 min-h-screen">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Manage Teachers</h1>
                <p class="text-slate-500 font-medium tracking-tight">Approve and manage teacher payment percentages.</p>
                <div class="mt-4 flex items-center gap-3">
                    <button onclick="copyRegLink()" class="flex items-center gap-2 px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold text-sm hover:bg-slate-200 transition-all border border-slate-200">
                        <i class="fas fa-copy"></i>
                        Copy Registration Link
                    </button>
                    <span id="copyFeedback" class="text-emerald-600 text-xs font-bold opacity-0 transition-opacity duration-300">Link Copied!</span>
                </div>
            </div>
            
            <form method="GET" class="flex items-center bg-white rounded-2xl shadow-sm border border-slate-200 p-1 w-full md:w-96">
                <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                <div class="pl-4 text-slate-400">
                    <i class="fas fa-search"></i>
                </div>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search name, mobile or ID..." 
                       class="w-full pl-3 pr-4 py-2 bg-transparent focus:outline-none text-slate-700 font-medium">
                <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-xl font-bold hover:bg-blue-700 transition-colors">Search</button>
            </form>
        </div>

        <script>
            function copyRegLink() {
                // Get the current URL and extract the base path excluding the /admin/ portion
                const currentHref = window.location.href;
                const basePath = currentHref.split('/admin/')[0];
                const regLink = `${basePath}/teacher_register.php`;
                
                navigator.clipboard.writeText(regLink).then(() => {
                    const feedback = document.getElementById('copyFeedback');
                    feedback.style.opacity = '1';
                    setTimeout(() => feedback.style.opacity = '0', 2000);
                });
            }
        </script>

        <?php if ($success_message): ?>
            <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded-xl flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3 text-lg"></i>
                    <span class="font-bold"><?php echo $success_message; ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex space-x-2 mb-8 bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200 inline-flex">
            <a href="?tab=pending&search=<?php echo urlencode($search); ?>" 
               class="px-6 py-2.5 rounded-xl font-bold transition-all duration-200 <?php echo $active_tab === 'pending' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50'; ?>">
                Pending Requests (<?php echo count($pending_teachers); ?>)
            </a>
            <a href="?tab=active&search=<?php echo urlencode($search); ?>" 
               class="px-6 py-2.5 rounded-xl font-bold transition-all duration-200 <?php echo $active_tab === 'active' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50'; ?>">
                Active Teachers (<?php echo count($active_teachers); ?>)
            </a>
        </div>

        <!-- Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php 
            if (empty($teachers)): ?>
                <div class="col-span-full py-20 text-center bg-white rounded-3xl border-2 border-dashed border-slate-200">
                    <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <p class="text-slate-400 font-bold text-lg">No teachers found in this category.</p>
                </div>
            <?php else: 
                foreach ($teachers as $t): 
                    // Fetch stats
                    $tid = $t['user_id'];
                    
                    // Total students (Distinct across all subjects)
                    $s_res = $conn->query("SELECT COUNT(DISTINCT se.student_id) FROM student_enrollment se JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id AND se.academic_year = ta.academic_year WHERE ta.teacher_id = '$tid' AND se.status = 'active'");
                    $students_count = ($s_res) ? $s_res->fetch_row()[0] : 0;
                    
                    // Total classes
                    $c_res = $conn->query("SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id = '$tid' AND status = 'active'");
                    $classes_count = ($c_res) ? $c_res->fetch_row()[0] : 0;
                    ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 hover:shadow-xl hover:border-blue-100 transition-all duration-300">
                        <div class="flex items-start gap-4 mb-6">
                            <?php if ($t['profile_picture']): ?>
                                <img src="../<?php echo $t['profile_picture']; ?>" class="w-20 h-20 rounded-2xl object-cover border-2 border-slate-100">
                            <?php else: ?>
                                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center text-slate-400 border-2 border-slate-100">
                                    <i class="fas fa-user-tie text-3xl"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-xl font-black text-slate-900 tracking-tight leading-tight"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['second_name']); ?></h3>
                                        <p class="text-slate-400 font-bold text-xs tracking-widest mt-1"><?php echo $t['user_id']; ?> • Joined <?php echo date('M Y', strtotime($t['registering_date'])); ?></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?php echo $t['approved'] ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'; ?>">
                                        <?php echo $t['approved'] ? 'Verified' : 'Pending Approval'; ?>
                                    </span>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-4 text-xs font-bold">
                                    <div class="text-slate-500 flex items-center bg-slate-50 px-2 py-1 rounded-md"><i class="fas fa-phone-alt mr-2 text-blue-500"></i><?php echo htmlspecialchars($t['mobile_number']); ?></div>
                                    <div class="text-slate-500 flex items-center bg-slate-50 px-2 py-1 rounded-md"><i class="fab fa-whatsapp mr-2 text-green-500"></i><?php echo htmlspecialchars($t['whatsapp_number'] ?: 'N/A'); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Row -->
                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100">
                                <p class="text-[10px] text-slate-400 font-black uppercase mb-1">Total Students</p>
                                <p class="text-2xl font-black text-slate-900"><?php echo $students_count; ?></p>
                            </div>
                            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100">
                                <p class="text-[10px] text-slate-400 font-black uppercase mb-1">Active Classes</p>
                                <p class="text-2xl font-black text-slate-900"><?php echo $classes_count; ?></p>
                        </div>
                        <!-- Assignments List & Subject-wise Commissions -->
                        <div class="mt-8 space-y-4">
                            <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest px-2">Assigned Classes & Commissions</h4>
                            
                            <?php 
                            $assign_q = "SELECT ta.*, s.name as stream_name, sub.name as subject_name 
                                        FROM teacher_assignments ta 
                                        JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                                        JOIN streams s ON ss.stream_id = s.id
                                        JOIN subjects sub ON ss.subject_id = sub.id
                                        WHERE ta.teacher_id = '$tid'";
                            $assign_res = $conn->query($assign_q);
                            
                            if ($assign_res->num_rows === 0): ?>
                                <p class="text-[10px] text-slate-300 italic px-2">No subjects assigned yet.</p>
                            <?php else:
                                while ($a = $assign_res->fetch_assoc()): ?>
                                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex items-center justify-between group/assign">
                                        <div class="flex-1">
                                            <p class="text-sm font-black text-slate-800 tracking-tight"><?php echo htmlspecialchars($a['subject_name']); ?></p>
                                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"><?php echo htmlspecialchars($a['stream_name']); ?> (<?php echo $a['academic_year']; ?>)</p>
                                        </div>
                                        
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                            <div class="relative w-24">
                                                <input type="number" name="commission_rate" 
                                                       value="<?php echo number_format($a['commission_rate'], 1); ?>" 
                                                       step="0.1" min="0" max="100"
                                                       class="w-full pl-3 pr-7 py-2 bg-white border border-slate-200 rounded-xl font-black text-slate-700 text-sm focus:border-blue-500 outline-none transition-all">
                                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[9px] font-black text-slate-300">%</span>
                                            </div>
                                            <button type="submit" name="update_assignment_rate" class="w-8 h-8 bg-slate-900 text-white rounded-lg flex items-center justify-center hover:bg-blue-600 transition-colors shadow-sm" title="Save this subject rate">
                                                <i class="fas fa-save text-[10px]"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                            
                            <?php if (!$t['approved']): ?>
                                <div class="bg-blue-50 p-6 rounded-3xl border border-blue-100 mt-6">
                                    <form method="POST" class="space-y-4">
                                        <input type="hidden" name="teacher_id" value="<?php echo $t['user_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <div>
                                            <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-2">Approve with Default Rate (%)</label>
                                            <input type="number" name="commission_rate" value="75.00" step="0.01" class="w-full px-6 py-4 bg-white border border-blue-100 rounded-2xl font-black text-xl text-blue-900 outline-none">
                                        </div>
                                        <button type="submit" name="update_teacher" class="w-full bg-blue-600 text-white py-5 rounded-2xl font-black hover:bg-blue-700 shadow-xl shadow-blue-100 transition-all">
                                            Verify & Approve Teacher
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function updateShares(source, teacherId) {
            const teacherInput = document.getElementById('teacher_rate_' + teacherId);
            const instituteInput = document.getElementById('institute_rate_' + teacherId);
            
            if (source === 'teacher') {
                const val = parseFloat(teacherInput.value) || 0;
                instituteInput.value = (100 - val).toFixed(2);
            } else {
                const val = parseFloat(instituteInput.value) || 0;
                teacherInput.value = (100 - val).toFixed(2);
            }
        }
    </script>
</body>
</html>
