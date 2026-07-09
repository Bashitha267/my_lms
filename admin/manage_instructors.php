<?php
require_once '../check_session.php';
require_once '../config.php';

// Verify user is admin
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

$page_title = "Manage Instructors";
$success_message = '';
$error_message = '';

// Handle Assignment (Add/Remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_instructor'])) {
        $instructor_id = $_POST['instructor_id'];
        $subject_id = intval($_POST['subject_id']);
        
        $stmt = $conn->prepare("INSERT INTO instructor_subjects (instructor_id, subject_id) VALUES (?, ?)");
        $stmt->bind_param("si", $instructor_id, $subject_id);
        
        if ($stmt->execute()) {
            $success_message = "Instructor assigned successfully.";
        } else {
            if ($conn->errno == 1062) {
                $error_message = "Instructor is already assigned to this subject.";
            } else {
                $error_message = "Error assigning instructor: " . $conn->error;
            }
        }
        $stmt->close();
    } elseif (isset($_POST['create_subject'])) {
        $sub_name = trim($_POST['new_subject_name']);
        $sub_code = trim($_POST['new_subject_code'] ?? '');
        
        if (!empty($sub_name)) {
            $stmt = $conn->prepare("INSERT INTO subjects (name, code, status) VALUES (?, ?, 1)");
            $stmt->bind_param("ss", $sub_name, $sub_code);
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                $success_message = "Subject '$sub_name' created successfully!";
            } else {
                $error_message = "Error creating subject: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['remove_assignment'])) {
        $assignment_id = intval($_POST['assignment_id']);
        $stmt = $conn->prepare("DELETE FROM instructor_subjects WHERE id = ?");
        $stmt->bind_param("i", $assignment_id);
        if ($stmt->execute()) {
            $success_message = "Assignment removed successfully.";
        }
        $stmt->close();
    }
}

// Fetch Reporting Data (Current Month)
$current_month = date('Y-m');
$report_query = "
    SELECT 
        u.user_id, 
        CONCAT(u.first_name, ' ', u.second_name) as name,
        COUNT(ir.id) as total_accepted
    FROM users u
    LEFT JOIN instructor_requests ir ON u.user_id = ir.accepted_by 
        AND DATE_FORMAT(ir.accepted_at, '%Y-%m') = ? 
        AND ir.status = 'accepted'
    WHERE u.role = 'instructor' AND u.status = 1
    GROUP BY u.user_id
    ORDER BY total_accepted DESC
";
$stmt = $conn->prepare($report_query);
$stmt->bind_param("s", $current_month);
$stmt->execute();
$report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Active Instructors with Extra Info for Searching
$instructors_query = "
    SELECT 
        u.user_id, u.first_name, u.second_name, u.mobile_number, u.whatsapp_number,
        GROUP_CONCAT(s.name SEPARATOR ', ') as assigned_subjects
    FROM users u
    LEFT JOIN instructor_subjects isub ON u.user_id = isub.instructor_id
    LEFT JOIN subjects s ON isub.subject_id = s.id
    WHERE u.role = 'instructor' AND u.status = 1
    GROUP BY u.user_id
    ORDER BY u.first_name
";
$instructors = $conn->query($instructors_query)->fetch_all(MYSQLI_ASSOC);

// Fetch Subjects
$subjects = $conn->query("SELECT id, name, code FROM subjects WHERE 1=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch Assignments
$assignments_query = "
    SELECT 
        is_sub.id,
        u.first_name, u.second_name,
        s.name as subject_name, s.code
    FROM instructor_subjects is_sub
    JOIN users u ON is_sub.instructor_id = u.user_id
    JOIN subjects s ON is_sub.subject_id = s.id
    ORDER BY u.first_name, s.name
";
$assignments = $conn->query($assignments_query)->fetch_all(MYSQLI_ASSOC);

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
    <title>Manage Instructors - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
        /* Tom Select Styling Fixes for Tailwind */
        .ts-control { border-radius: 0.375rem !important; border-color: #d1d5db !important; padding: 0.5rem !important; }
        .ts-wrapper.focus .ts-control { box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important; border-color: #3b82f6 !important; }
        .ts-dropdown { border-radius: 0.375rem !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important; }
        .ts-dropdown .active { background-color: #eff6ff !important; color: #1e40af !important; }
    </style>
</head>
<body class="bg-gray-100">
    
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <div class="px-4 py-6 sm:px-0">
             <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Manage Instructors</h1>
                <a href="../instructor_register.php" target="_blank" class="text-blue-600 hover:text-blue-800 underline">
                    Registration Link <i class="fas fa-external-link-alt text-sm"></i>
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Assignment Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Assign Form -->
                <div class="bg-white p-6 rounded-lg shadow-md h-fit">
                    <h2 class="text-lg font-bold mb-4 border-b pb-2">Assign Instructor</h2>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Instructor</label>
                            <select id="instructor_select" name="instructor_id" required>
                                <option value="">-- Choose Instructor --</option>
                                <?php foreach ($instructors as $inst): ?>
                                    <option value="<?php echo $inst['user_id']; ?>" 
                                            data-id="<?php echo htmlspecialchars($inst['user_id']); ?>"
                                            data-mobile="<?php echo htmlspecialchars($inst['mobile_number'] ?? ''); ?>"
                                            data-whatsapp="<?php echo htmlspecialchars($inst['whatsapp_number'] ?? ''); ?>"
                                            data-subjects="<?php echo htmlspecialchars($inst['assigned_subjects'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($inst['first_name'] . ' ' . $inst['second_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Search by Name, ID, Mobile, or Subject</p>
                        </div>
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-sm font-medium text-gray-700">Select Subject</label>
                                <button type="button" onclick="showNewSubjectModal()" class="text-xs text-blue-600 hover:underline font-bold">+ Create New</button>
                            </div>
                            <select id="subject_select" name="subject_id" required>
                                <option value="">-- Choose Subject --</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?php echo $sub['id']; ?>">
                                        <?php echo htmlspecialchars($sub['name'] . ' (' . $sub['code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="assign_instructor" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition">Assign</button>
                    </form>
                </div>

                <!-- Current Assignments -->
                <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-lg font-bold mb-4 border-b pb-2">Current Assignments</h2>
                    <div class="overflow-x-auto max-h-80 overflow-y-auto border rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-50">Instructor</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-50">Subject</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-50">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($assignments as $asg): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($asg['first_name'] . ' ' . $asg['second_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($asg['subject_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <form method="POST" action="" onsubmit="return confirm('Remove this assignment?');" class="inline">
                                            <input type="hidden" name="assignment_id" value="<?php echo $asg['id']; ?>">
                                            <button type="submit" name="remove_assignment" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reporting Section -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h2 class="text-lg font-bold">Monthly Report (<?php echo date('F Y'); ?>)</h2>
                    <span class="text-sm text-gray-500">Accepted Requests</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Instructor Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Accepted Requests</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($row['user_id']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold">
                                    <?php echo $row['total_accepted']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 max-w-xs">
                                        <!-- Simple visual bar, max 50 for scale -->
                                        <div class="bg-green-600 h-2.5 rounded-full" style="width: <?php echo min(($row['total_accepted'] / 50) * 100, 100); ?>%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="users.php?search=<?php echo urlencode($row['user_id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View in Users">
                                        <i class="fas fa-user-cog"></i>
                                    </a>
                                    <a href="edit_user.php?user_id=<?php echo urlencode($row['user_id']); ?>" class="text-purple-600 hover:text-purple-900" title="Edit Instructor">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-400">No accepted requests this month.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

    <!-- Create Subject Modal -->
    <div id="newSubjectModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-800">Add New Subject</h3>
                <button onclick="closeNewSubjectModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Name *</label>
                    <input type="text" name="new_subject_name" required class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. Higher Mathematics">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Code (Optional)</label>
                    <input type="text" name="new_subject_code" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. HMATH">
                </div>
                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="closeNewSubjectModal()" class="flex-1 px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" name="create_subject" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md transform active:scale-95 transition">Save Subject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function showNewSubjectModal() {
            document.getElementById('newSubjectModal').classList.remove('hidden');
        }
        function closeNewSubjectModal() {
            document.getElementById('newSubjectModal').classList.add('hidden');
        }

        // Initialize Tom Select
        document.addEventListener('DOMContentLoaded', function() {
            // Instructor Select
            new TomSelect("#instructor_select", {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                },
                render: {
                    option: function(data, escape) {
                        return `<div>
                            <div class="font-bold">${escape(data.text)}</div>
                            <div class="text-xs text-gray-500">
                                <span>ID: ${escape(data.id)}</span>
                                <span class="mx-1">|</span>
                                <span>📞 ${escape(data.mobile)}</span>
                            </div>
                            ${data.subjects ? `<div class="text-[10px] text-blue-600 truncate">${escape(data.subjects)}</div>` : ''}
                        </div>`;
                    },
                    item: function(data, escape) {
                        return `<div>${escape(data.text)} <span class="text-xs text-gray-400 ml-1">(${escape(data.id)})</span></div>`;
                    }
                },
                searchField: ["text", "id", "mobile", "subjects"],
                placeholder: "-- Choose Instructor --"
            });

            // Subject Select
            new TomSelect("#subject_select", {
                create: false,
                placeholder: "-- Choose Subject --"
            });
        });
    </script>
</body>
</html>
