<?php
// resetenrollments.php - Executable script to clear all student accounts and enrollment data
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die("Error: config.php file not found.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

$stats = [];

// Helper function to safely truncate/delete from a table
function safe_clear_table($conn, $table, $where = '') {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check && $check->num_rows > 0) {
        $sql = "DELETE FROM `$table` " . ($where ? "WHERE $where" : "");
        if ($conn->query($sql)) {
            return $conn->affected_rows;
        }
    }
    return 0;
}

// 1. Clear Payments and Submissions linked to enrollments
$stats['enrollment_payments'] = safe_clear_table($conn, 'enrollment_payments');
$stats['monthly_payments']    = safe_clear_table($conn, 'monthly_payments');
$stats['course_payments']     = safe_clear_table($conn, 'course_payments');
$stats['inst_payments']       = safe_clear_table($conn, 'inst_payments');

// 2. Clear Enrollments
$stats['student_enrollment'] = safe_clear_table($conn, 'student_enrollment');
$stats['course_enrollments'] = safe_clear_table($conn, 'course_enrollments');

// 3. Clear Class Exam & Attendance Data
$stats['class_attendance']    = safe_clear_table($conn, 'class_attendance');
$stats['attendance']          = safe_clear_table($conn, 'attendance');
$stats['exam_attempts']       = safe_clear_table($conn, 'exam_attempts');
$stats['exam_answers']        = safe_clear_table($conn, 'exam_answers');
$stats['exam_results']        = safe_clear_table($conn, 'exam_results');
$stats['instructor_requests'] = safe_clear_table($conn, 'instructor_requests');

// 4. Clear Enrolled Student Accounts
$stats['student_users'] = safe_clear_table($conn, 'users', "role = 'student'");

$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Enrollments & Students - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6 sm:p-12">
    <div class="max-w-3xl mx-auto bg-slate-800/90 rounded-3xl p-8 sm:p-10 border border-slate-700 shadow-2xl backdrop-blur-xl">
        <div class="flex items-center justify-between mb-8 pb-6 border-b border-slate-700">
            <div>
                <span class="bg-rose-500/20 text-rose-400 text-xs font-extrabold uppercase px-3 py-1 rounded-full border border-rose-500/30">System Cleanup</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">All Enrollments & Students Removed</h1>
            </div>
            <a href="admin/users.php" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all">Go to Admin Users</a>
        </div>

        <div class="mb-6 text-sm text-slate-300">
            The system cleanup operation completed successfully. Below is the summary of cleared records across database tables:
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-700 mb-8">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/60 uppercase font-extrabold text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-4">Data Category</th>
                        <th class="px-6 py-4 text-right">Cleared Records</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60 font-semibold">
                    <?php foreach ($stats as $category => $count): ?>
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-3.5 text-slate-200"><?php echo ucwords(str_replace('_', ' ', $category)); ?></td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold <?php echo $count > 0 ? 'text-rose-400' : 'text-slate-500'; ?>">
                                <?php echo number_format($count); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold text-xs rounded-xl transition-all">Go to Home</a>
        </div>
    </div>
</body>
</html>
