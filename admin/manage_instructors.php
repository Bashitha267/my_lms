<?php
require_once '../check_session.php';
require_once '../config.php';

// Verify user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: /lms/login.php");
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

// Fetch Active Instructors
$instructors = $conn->query("SELECT user_id, first_name, second_name FROM users WHERE role = 'instructor' AND status = 1 ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Instructors - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                            <select name="instructor_id" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border">
                                <option value="">-- Choose --</option>
                                <?php foreach ($instructors as $inst): ?>
                                    <option value="<?php echo $inst['user_id']; ?>">
                                        <?php echo htmlspecialchars($inst['first_name'] . ' ' . $inst['second_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Subject</label>
                            <select name="subject_id" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border">
                                <option value="">-- Choose --</option>
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
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Instructor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
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
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-400">No accepted requests this month.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
