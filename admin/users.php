<?php
require_once '../check_session.php';

// Verify user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: /lms/login.php?error=" . urlencode("Access denied. Admin only."));
    exit();
}

require_once '../config.php';

$success_message = '';
$error_message = '';

// Handle actions (approve, delete, activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];
        
        switch ($action) {
            case 'approve':
                $stmt = $conn->prepare("UPDATE users SET approved = 1 WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                if ($stmt->execute()) {
                    $success_message = "User approved successfully.";
                }
                $stmt->close();
                break;
                
            case 'disapprove':
                $stmt = $conn->prepare("UPDATE users SET approved = 0 WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                if ($stmt->execute()) {
                    $success_message = "User disapproved successfully.";
                }
                $stmt->close();
                break;
                
            case 'activate':
                $stmt = $conn->prepare("UPDATE users SET status = 1 WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                if ($stmt->execute()) {
                    $success_message = "User activated successfully.";
                }
                $stmt->close();
                break;
                
            case 'deactivate':
                $stmt = $conn->prepare("UPDATE users SET status = 0 WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                if ($stmt->execute()) {
                    $success_message = "User deactivated successfully.";
                }
                $stmt->close();
                break;
                
            case 'delete':
                $conn->begin_transaction();
                try {
                    // Check if user is a teacher
                    $check_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
                    $check_stmt->bind_param("s", $user_id);
                    $check_stmt->execute();
                    $check_res = $check_stmt->get_result();
                    $role_row = $check_res->fetch_assoc();
                    $check_stmt->close();

                    if ($role_row && $role_row['role'] === 'teacher') {
                        // 1. Delete Course Payments associated with teacher's courses
                        // "payments for that course"
                        $del_pay_sql = "DELETE cp FROM course_payments cp 
                                      JOIN course_enrollments ce ON cp.course_enrollment_id = ce.id 
                                      JOIN courses c ON ce.course_id = c.id 
                                      WHERE c.teacher_id = ?";
                        $del_pay_stmt = $conn->prepare($del_pay_sql);
                        $del_pay_stmt->bind_param("s", $user_id);
                        $del_pay_stmt->execute();
                        $del_pay_stmt->close();

                        // 2. Delete Course Enrollments associated with teacher's courses
                        $del_enr_sql = "DELETE ce FROM course_enrollments ce 
                                      JOIN courses c ON ce.course_id = c.id 
                                      WHERE c.teacher_id = ?";
                        $del_enr_stmt = $conn->prepare($del_enr_sql);
                        $del_enr_stmt->bind_param("s", $user_id);
                        $del_enr_stmt->execute();
                        $del_enr_stmt->close();

                        // 3. Delete Courses
                        $del_course_stmt = $conn->prepare("DELETE FROM courses WHERE teacher_id = ?");
                        $del_course_stmt->bind_param("s", $user_id);
                        $del_course_stmt->execute();
                        $del_course_stmt->close();
                        
                        // 4. Delete Teacher Assignments (Optional but recommended for consistency)
                        $del_assign_stmt = $conn->prepare("DELETE FROM teacher_assignments WHERE teacher_id = ?");
                        $del_assign_stmt->bind_param("s", $user_id);
                        $del_assign_stmt->execute();
                        $del_assign_stmt->close();
                    }

                    // Finally delete the user
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("s", $user_id);
                    if ($stmt->execute()) {
                        $success_message = "User and associated data deleted successfully.";
                    }
                    $stmt->close();
                    
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error deleting user: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$filter_role = $_GET['filter'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$filter_approved = $_GET['approved'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT user_id, email, role, first_name, second_name, mobile_number, whatsapp_number, status, approved, registering_date FROM users WHERE 1=1";
$params = [];
$types = '';

if ($filter_role !== 'all') {
    $query .= " AND role = ?";
    $params[] = $filter_role;
    $types .= 's';
}

if ($filter_status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter_status;
    $types .= 'i';
}

if ($filter_approved !== 'all') {
    $query .= " AND approved = ?";
    $params[] = $filter_approved;
    $types .= 'i';
}

if (!empty($search)) {
    $query .= " AND (email LIKE ? OR first_name LIKE ? OR second_name LIKE ? OR user_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$query .= " ORDER BY registering_date DESC, user_id ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
    SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teachers,
    SUM(CASE WHEN role = 'instructor' THEN 1 ELSE 0 END) as instructors,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive
    FROM users";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 flex items-center space-x-2">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            <span>Manage Users</span>
                        </h1>
                        <p class="text-gray-600 mt-1">View and manage all system users</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="copyTeacherLink()" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium flex items-center space-x-2 transition-colors">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <span>Copy Teacher Link</span>
                        </button>
                        <a href="add_user.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                            </svg>
                            <span>Add New User</span>
                        </a>
                    </div>
                </div>
            </div>

            <script>
                function copyTeacherLink() {
                    // Construct the URL (assuming /lms/teacher_register.php is the path relative to domain root)
                    // If current path is /lms/admin/users.php, we want /lms/teacher_register.php
                    const path = window.location.pathname;
                    // Remove 'admin/users.php' and append 'teacher_register.php'
                    // Careful with simple replace if 'admin' appears elsewhere.
                    // Better: resolve relative to current location
                    const adminIndex = path.indexOf('/admin/');
                    let rootPath = path;
                    if (adminIndex !== -1) {
                         rootPath = path.substring(0, adminIndex);
                    }
                    // Handle case where admin is not in path (unlikely given file location)
                    const link = window.location.origin + rootPath + '/teacher_register.php';
                    
                    navigator.clipboard.writeText(link).then(() => {
                        // Show temporary success feedback
                        const btn = document.querySelector('button[onclick="copyTeacherLink()"]');
                        const originalContent = btn.innerHTML;
                        btn.innerHTML = `
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-green-600">Copied!</span>
                        `;
                        setTimeout(() => {
                            btn.innerHTML = originalContent;
                        }, 2000);
                    }).catch(err => {
                        console.error('Failed to copy: ', err);
                        alert('Failed to copy link. Manual link: ' + link);
                    });
                }
            </script>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-600">
                    <div class="text-sm text-gray-600">Total Users</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-600">
                    <div class="text-sm text-gray-600">Students</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['students']; ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-600">
                    <div class="text-sm text-gray-600">Teachers</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['teachers']; ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-600">
                    <div class="text-sm text-gray-600">Instructors</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['instructors']; ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-600">
                    <div class="text-sm text-gray-600">Admins</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['admins']; ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-600">
                    <div class="text-sm text-gray-600">Pending</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['pending']; ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-600">
                    <div class="text-sm text-gray-600">Inactive</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['inactive']; ?></div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6" role="alert">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6" role="alert">
                    <div class="flex">
                        <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <form method="GET" action="" id="filterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search by email, name, or ID"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select id="filter" name="filter" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                            <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo $filter_role === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="instructor" <?php echo $filter_role === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                            <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <!-- Approved Filter -->
                    <div>
                        <label for="approved" class="block text-sm font-medium text-gray-700 mb-1">Approval</label>
                        <select id="approved" name="approved" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                            <option value="all" <?php echo $filter_approved === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="1" <?php echo $filter_approved === '1' ? 'selected' : ''; ?>>Approved</option>
                            <option value="0" <?php echo $filter_approved === '0' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <!-- Clear Filters -->
                    <div class="md:col-span-4 flex justify-end">
                        <a href="users.php" class="px-4 py-2 text-red-600 hover:text-red-800 font-medium flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Clear Filters
                        </a>
                    </div>
                </form>
                
                <script>
                    // Debounce search input
                    const searchInput = document.getElementById('search');
                    let timeoutId;
                    
                    searchInput.addEventListener('input', function() {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => {
                            this.form.submit();
                        }, 500);
                    });
                </script>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-red-600">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">User ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Approved</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        No users found matching your criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo htmlspecialchars($user['user_id']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['second_name'] ?? '')) ?: 'N/A'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                                <?php
                                                echo match($user['role']) {
                                                    'admin' => 'bg-red-100 text-red-800',
                                                    'teacher' => 'bg-yellow-100 text-yellow-800',
                                                    'instructor' => 'bg-purple-100 text-purple-800',
                                                    default => 'bg-green-100 text-green-800'
                                                };
                                                ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php if ($user['status'] == 1): ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php if ($user['approved'] == 1): ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Approved</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <?php if ($user['approved'] == 0): ?>
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="text-green-600 hover:text-green-900" title="Approve">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                        <input type="hidden" name="action" value="disapprove">
                                                        <button type="submit" class="text-orange-600 hover:text-orange-900" title="Disapprove">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($user['status'] == 1): ?>
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <button type="submit" class="text-yellow-600 hover:text-yellow-900" title="Deactivate">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="text-green-600 hover:text-green-900" title="Activate">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <a href="edit_user.php?user_id=<?php echo htmlspecialchars($user['user_id']); ?>" class="text-blue-600 hover:text-blue-900" title="Edit">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </a>

                                                <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Results Count -->
            <div class="mt-4 text-sm text-gray-600">
                Showing <strong><?php echo count($users); ?></strong> user(s)
            </div>
        </div>
    </div>
</body>
</html>

