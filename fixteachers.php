<?php
// fixteachers.php - Executable script to reformat all teacher user_ids to T_0001, T_0002...
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

// Fetch all teachers ordered by registering_date ASC, user_id ASC
$teachers_query = "SELECT user_id, first_name, second_name, email, registering_date FROM users WHERE role = 'teacher' ORDER BY registering_date ASC, user_id ASC";
$result = $conn->query($teachers_query);
$teachers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$updated_records = [];

// Helper function to update table foreign key column if table & column exist
function safe_update_fk($conn, $table, $col, $new_id, $old_id) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check && $check->num_rows > 0) {
        $col_check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($col_check && $col_check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE `$table` SET `$col` = ? WHERE `$col` = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $new_id, $old_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

$counter = 1;
foreach ($teachers as $teacher) {
    $old_id = $teacher['user_id'];
    $new_id = sprintf('T_%04d', $counter);
    $full_name = trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['second_name'] ?? ''));

    if ($old_id !== $new_id) {
        // Update main users table
        $update_user = $conn->prepare("UPDATE users SET user_id = ? WHERE user_id = ? AND role = 'teacher'");
        if ($update_user) {
            $update_user->bind_param("ss", $new_id, $old_id);
            $update_user->execute();
            $update_user->close();
        }

        // Update related tables
        safe_update_fk($conn, 'teacher_assignments', 'teacher_id', $new_id, $old_id);
        safe_update_fk($conn, 'teacher_education', 'teacher_id', $new_id, $old_id);
        safe_update_fk($conn, 'courses', 'teacher_id', $new_id, $old_id);
        safe_update_fk($conn, 'live_classes', 'teacher_id', $new_id, $old_id);
        safe_update_fk($conn, 'physical_classes', 'teacher_id', $new_id, $old_id);
        safe_update_fk($conn, 'teacher_payments', 'teacher_id', $new_id, $old_id);
        safe_update_fk($conn, 'teacher_bank_details', 'teacher_id', $new_id, $old_id);
        safe_update_fk($conn, 'teacher_requests', 'teacher_id', $new_id, $old_id);
        safe_update_fk($conn, 'subject_teacher', 'teacher_id', $new_id, $old_id);

        $updated_records[] = [
            'status' => 'Updated',
            'old_id' => $old_id,
            'new_id' => $new_id,
            'name'   => $full_name,
            'email'  => $teacher['email']
        ];
    } else {
        $updated_records[] = [
            'status' => 'Already Correct',
            'old_id' => $old_id,
            'new_id' => $new_id,
            'name'   => $full_name,
            'email'  => $teacher['email']
        ];
    }
    $counter++;
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Teachers IDs - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6 sm:p-12">
    <div class="max-w-4xl mx-auto bg-slate-800/90 rounded-3xl p-8 sm:p-10 border border-slate-700 shadow-2xl backdrop-blur-xl">
        <div class="flex items-center justify-between mb-8 pb-6 border-b border-slate-700">
            <div>
                <span class="bg-emerald-500/20 text-emerald-400 text-xs font-extrabold uppercase px-3 py-1 rounded-full border border-emerald-500/30">Execution Complete</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Teacher IDs Re-formatted (T_0001 Pattern)</h1>
            </div>
            <a href="admin/users.php" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all">Go to Admin Users</a>
        </div>

        <div class="mb-6 text-sm text-slate-300">
            Processed <strong><?php echo count($updated_records); ?></strong> teacher records. All teacher IDs and associated relational data have been synchronized.
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-700">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/60 uppercase font-extrabold text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-4">#</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Old ID</th>
                        <th class="px-6 py-4">New ID</th>
                        <th class="px-6 py-4">Teacher Name</th>
                        <th class="px-6 py-4">Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60">
                    <?php if (empty($updated_records)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">No teacher accounts found in system.</td></tr>
                    <?php else: ?>
                        <?php foreach ($updated_records as $idx => $rec): ?>
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-400"><?php echo $idx + 1; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase <?php echo $rec['status'] === 'Updated' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-slate-700 text-slate-400'; ?>">
                                        <?php echo $rec['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono font-semibold text-rose-400"><?php echo htmlspecialchars($rec['old_id']); ?></td>
                                <td class="px-6 py-4 font-mono font-bold text-emerald-400"><?php echo htmlspecialchars($rec['new_id']); ?></td>
                                <td class="px-6 py-4 font-semibold text-white"><?php echo htmlspecialchars($rec['name']); ?></td>
                                <td class="px-6 py-4 text-slate-400"><?php echo htmlspecialchars($rec['email']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
