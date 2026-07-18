<?php
require_once '../check_session.php';
require_once '../config.php';

// Only admins can access this page
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
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
            } elseif ($action === 'deny') {
                $conn->begin_transaction();
                try {
                    // 1. Delete Course Payments associated with teacher's courses
                    $del_pay_sql = "DELETE cp FROM course_payments cp 
                                  JOIN course_enrollments ce ON cp.course_enrollment_id = ce.id 
                                  JOIN courses c ON ce.course_id = c.id 
                                  WHERE c.teacher_id = ?";
                    $del_pay_stmt = $conn->prepare($del_pay_sql);
                    $del_pay_stmt->bind_param("s", $teacher_id);
                    $del_pay_stmt->execute();
                    $del_pay_stmt->close();

                    // 2. Delete Course Enrollments associated with teacher's courses
                    $del_enr_sql = "DELETE ce FROM course_enrollments ce 
                                  JOIN courses c ON ce.course_id = c.id 
                                  WHERE c.teacher_id = ?";
                    $del_enr_stmt = $conn->prepare($del_enr_sql);
                    $del_enr_stmt->bind_param("s", $teacher_id);
                    $del_enr_stmt->execute();
                    $del_enr_stmt->close();

                    // 3. Delete Courses
                    $del_course_stmt = $conn->prepare("DELETE FROM courses WHERE teacher_id = ?");
                    $del_course_stmt->bind_param("s", $teacher_id);
                    $del_course_stmt->execute();
                    $del_course_stmt->close();
                    
                    // 4. Delete Teacher Assignments
                    $del_assign_stmt = $conn->prepare("DELETE FROM teacher_assignments WHERE teacher_id = ?");
                    $del_assign_stmt->bind_param("s", $teacher_id);
                    $del_assign_stmt->execute();
                    $del_assign_stmt->close();

                    // 5. Delete Education qualifications
                    $del_edu_stmt = $conn->prepare("DELETE FROM teacher_education WHERE teacher_id = ?");
                    $del_edu_stmt->bind_param("s", $teacher_id);
                    $del_edu_stmt->execute();
                    $del_edu_stmt->close();

                    // 6. Finally delete the user
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("s", $teacher_id);
                    if ($stmt->execute()) {
                        $success_message = "Teacher registration request denied and user deleted successfully.";
                    }
                    $stmt->close();
                    
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error denying teacher request: " . $e->getMessage();
                }
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
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Manage Teachers</h1>
                <p class="text-slate-500 text-sm">Approve and manage teacher payment percentages.</p>
                <div class="mt-3 flex items-center gap-3">
                    <button onclick="copyRegLink()" class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 text-slate-600 rounded-lg font-semibold text-xs hover:bg-slate-200 transition-all border border-slate-200">
                        <i class="fas fa-copy"></i>
                        Copy Registration Link
                    </button>
                    <span id="copyFeedback" class="text-emerald-600 text-xs font-semibold opacity-0 transition-opacity duration-300">Link Copied!</span>
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
                <button type="submit" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors text-sm">Search</button>
            </form>
        </div>

        <script>
            function copyRegLink() {
                // Get the current URL and extract the base path excluding the /admin/ portion
                const currentHref = window.location.href;
                const basePath = currentHref.split('/admin/')[0];
                const regLink = `${basePath}/teacher_registration.php`;
                
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
        <div class="flex space-x-2 mb-6 bg-white p-1 rounded-xl shadow-sm border border-slate-200 inline-flex">
            <a href="?tab=pending&search=<?php echo urlencode($search); ?>" 
               class="px-4 py-1.5 rounded-lg font-semibold text-sm transition-all duration-200 <?php echo $active_tab === 'pending' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'; ?>">
                Pending Requests (<?php echo count($pending_teachers); ?>)
            </a>
            <a href="?tab=active&search=<?php echo urlencode($search); ?>" 
               class="px-4 py-1.5 rounded-lg font-semibold text-sm transition-all duration-200 <?php echo $active_tab === 'active' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'; ?>">
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
                foreach ($teachers as $t): ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 hover:shadow-xl hover:border-blue-100 transition-all duration-300">
                        <div class="flex items-start gap-4 mb-4">
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
                                        <h3 class="text-lg font-bold text-slate-900 tracking-tight leading-tight"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['second_name']); ?></h3>
                                        <p class="text-slate-400 font-semibold text-[10px] uppercase tracking-wider mt-1"><?php echo $t['user_id']; ?> • Joined <?php echo date('M Y', strtotime($t['registering_date'])); ?></p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase <?php echo $t['approved'] ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'; ?>">
                                        <?php echo $t['approved'] ? 'Verified' : 'Pending Approval'; ?>
                                    </span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2 text-[10px] font-semibold">
                                    <div class="text-slate-500 flex items-center bg-slate-50 px-2 py-1 rounded-md"><i class="fas fa-phone-alt mr-2 text-blue-500"></i><?php echo htmlspecialchars($t['mobile_number']); ?></div>
                                    <div class="text-slate-500 flex items-center bg-slate-50 px-2 py-1 rounded-md"><i class="fab fa-whatsapp mr-2 text-green-500"></i><?php echo htmlspecialchars($t['whatsapp_number'] ?: 'N/A'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <button onclick="showTeacherDetails('<?php echo htmlspecialchars($t['user_id']); ?>')" class="w-full bg-slate-100 text-slate-700 hover:bg-slate-200 py-2.5 rounded-xl font-semibold transition-all text-sm flex items-center justify-center gap-2">
                                <i class="fas fa-eye text-xs"></i>
                                <span>View Details</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Teacher Details Modal -->
    <div id="teacherDetailsModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50 flex items-center justify-center p-4 hidden" onclick="if(event.target === this) closeTeacherModal();">
        <div class="bg-white rounded-2xl overflow-hidden shadow-xl transform transition-all sm:max-w-2xl sm:w-full max-h-[90vh] flex flex-col">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-800">Teacher Details</h3>
                <button onclick="closeTeacherModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <!-- Modal Body -->
            <div id="modalContent" class="p-6 overflow-y-auto space-y-6 flex-1">
                <!-- Content will be injected dynamically -->
            </div>
            <!-- Modal Footer -->
            <div class="px-6 py-3 border-t border-gray-200 flex justify-end bg-gray-50">
                <button onclick="closeTeacherModal()" class="bg-gray-100 border border-gray-300 text-gray-700 hover:bg-gray-200 px-4 py-2 rounded-xl font-semibold text-sm transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function showTeacherDetails(userId) {
            document.getElementById('teacherDetailsModal').classList.remove('hidden');
            document.getElementById('modalContent').innerHTML = `
                <div class="flex justify-center items-center py-12">
                    <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            `;

            fetch('get_user_details.php?user_id=' + encodeURIComponent(userId))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderTeacherDetails(data);
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
                            Failed to fetch teacher details.
                        </div>
                    `;
                });
        }

        function renderTeacherDetails(data) {
            const user = data.user;
            const fullName = ((user.first_name || '') + ' ' + (user.second_name || '')).trim() || 'N/A';
            const statusText = user.status == 1 ? 'Active' : 'Inactive';
            const statusClass = user.status == 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
            const approvedText = user.approved == 1 ? 'Verified' : 'Pending Approval';
            const approvedClass = user.approved == 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800';
            const registeringDate = user.registering_date ? new Date(user.registering_date).toLocaleDateString() : 'N/A';
            
            let photoHtml = '';
            if (user.profile_picture) {
                photoHtml = `<img class="h-20 w-20 object-cover rounded-xl border-2 border-gray-200" src="../${user.profile_picture}" alt="Profile photo" />`;
            } else {
                photoHtml = `
                    <div class="h-20 w-20 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400 text-3xl">
                        <i class="fas fa-user-tie"></i>
                    </div>
                `;
            }

            let modalHtml = `
                <div class="space-y-6 text-sm">
                    <!-- Profile Card -->
                    <div class="flex items-center space-x-6 pb-6 border-b border-gray-100">
                        <div class="shrink-0">${photoHtml}</div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">${fullName}</h3>
                            <p class="text-sm text-gray-500">${user.email || 'No email available'}</p>
                            <div class="mt-2 flex space-x-2">
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded-full ${approvedClass}">${approvedText}</span>
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded-full ${statusClass}">${statusText}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-bold">User ID</span>
                            <span class="text-gray-800 font-semibold">${user.user_id}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-bold">Registration Date</span>
                            <span class="text-gray-800 font-semibold">${registeringDate}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-bold">Mobile Number</span>
                            <span class="text-gray-800 font-semibold">${user.mobile_number || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-bold">WhatsApp Number</span>
                            <span class="text-gray-800 font-semibold">${user.whatsapp_number || 'N/A'}</span>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-2 gap-4 py-4 border-t border-b border-gray-100 bg-slate-50 rounded-2xl p-4">
                        <div class="text-center">
                            <span class="block text-[10px] text-gray-400 uppercase font-bold mb-1">Total Students</span>
                            <span class="text-2xl font-extrabold text-slate-800">${data.students_count}</span>
                        </div>
                        <div class="text-center">
                            <span class="block text-[10px] text-gray-400 uppercase font-bold mb-1">Active Classes</span>
                            <span class="text-2xl font-extrabold text-slate-800">${data.classes_count}</span>
                        </div>
                    </div>

                    <!-- Education Details -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Education Details</h4>
            `;

            if (data.education && data.education.length > 0) {
                modalHtml += `
                    <div class="overflow-x-auto border rounded-xl">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-500 uppercase">Qualification</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-500 uppercase">Institution</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-500 uppercase">Year</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-500 uppercase">Grade/Class</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                `;
                data.education.forEach(edu => {
                    modalHtml += `
                        <tr>
                            <td class="px-4 py-2 text-gray-900 font-semibold">${edu.qualification}</td>
                            <td class="px-4 py-2 text-gray-500">${edu.institution || 'N/A'}</td>
                            <td class="px-4 py-2 text-gray-500">${edu.year_obtained || 'N/A'}</td>
                            <td class="px-4 py-2 text-gray-500">${edu.grade_or_class || 'N/A'}</td>
                        </tr>
                    `;
                });
                modalHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                modalHtml += `<p class="text-xs text-gray-500 italic">No education details recorded.</p>`;
            }

            modalHtml += `
                    </div>

                    <!-- Assignments & Commissions -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Assigned Classes & Commissions</h4>
            `;

            if (data.assignments && data.assignments.length > 0) {
                data.assignments.forEach(asg => {
                    modalHtml += `
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-800 tracking-tight">${asg.subject_name}</p>
                                <p class="text-[10px] font-medium text-slate-400 uppercase">${asg.stream_name} (${asg.academic_year})</p>
                            </div>
                            
                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="assignment_id" value="${asg.id}">
                                <div class="relative w-20">
                                    <input type="number" name="commission_rate" value="${parseFloat(asg.commission_rate).toFixed(1)}" step="0.1" min="0" max="100" class="w-full pl-2.5 pr-6 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-slate-700 text-sm focus:border-blue-500 outline-none transition-all">
                                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-slate-300">%</span>
                                </div>
                                <button type="submit" name="update_assignment_rate" class="w-7 h-7 bg-slate-900 text-white rounded-lg flex items-center justify-center hover:bg-blue-600 transition-colors shadow-sm" title="Save this subject rate">
                                    <i class="fas fa-save text-[9px]"></i>
                                </button>
                            </form>
                        </div>
                    `;
                });
            } else {
                modalHtml += `<p class="text-xs text-gray-500 italic">No classes assigned yet.</p>`;
            }

            modalHtml += `</div>`;

            // Approval/Denial Section inside modal
            if (user.approved == 0) {
                modalHtml += `
                    <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100 mt-4 space-y-3">
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="teacher_id" value="${user.user_id}">
                            <input type="hidden" name="action" value="approve">
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 uppercase mb-1.5 block ml-1">Approve with Default Rate (%)</label>
                                <input type="number" name="commission_rate" value="75.00" step="0.01" class="w-full px-4 py-2 bg-white border border-blue-100 rounded-xl font-bold text-lg text-blue-900 outline-none focus:border-blue-500">
                            </div>
                            <button type="submit" name="update_teacher" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold hover:bg-blue-700 shadow-md shadow-blue-100 transition-all text-sm">
                                Verify & Approve Teacher
                            </button>
                        </form>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to deny this request and permanently delete this teacher and all associated data? This action cannot be undone.');">
                            <input type="hidden" name="teacher_id" value="${user.user_id}">
                            <input type="hidden" name="action" value="deny">
                            <input type="hidden" name="commission_rate" value="0">
                            <button type="submit" name="update_teacher" class="w-full bg-red-50 text-red-600 border border-red-200 py-2.5 rounded-xl font-semibold hover:bg-red-100 transition-all text-sm">
                                Deny & Delete Request
                            </button>
                        </form>
                    </div>
                `;
            }

            modalHtml += `</div>`;
            document.getElementById('modalContent').innerHTML = modalHtml;
        }

        function closeTeacherModal() {
            document.getElementById('teacherDetailsModal').classList.add('hidden');
        }
    </script>
</body>
</html>
