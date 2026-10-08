<?php
/**
 * setup_al_teacher.php
 * ONE-TIME migration:
 *  1. Add `teacher_id` column to `al_exam_submissions`
 *  2. Assign ALL existing results to T_0002
 * Run once, then DELETE this file.
 */
require_once __DIR__ . '/config.php';

$steps = [];
$errors = [];

// Step 1: Check if column already exists
$col_check = $conn->query("SHOW COLUMNS FROM `al_exam_submissions` LIKE 'teacher_id'");
if ($col_check && $col_check->num_rows > 0) {
    $steps[] = "Column <strong>teacher_id</strong> already exists — skipping ALTER.";
} else {
    $alter = $conn->query("ALTER TABLE `al_exam_submissions` ADD COLUMN `teacher_id` VARCHAR(20) NULL DEFAULT NULL AFTER `student_id`");
    if ($alter) {
        $steps[] = "Column <strong>teacher_id VARCHAR(20)</strong> added to <code>al_exam_submissions</code>.";
    } else {
        $errors[] = "Failed to add column: " . $conn->error;
    }
}

// Step 2: Add index
$idx_check = $conn->query("SHOW INDEX FROM `al_exam_submissions` WHERE Key_name = 'idx_al_teacher'");
if ($idx_check && $idx_check->num_rows > 0) {
    $steps[] = "Index <strong>idx_al_teacher</strong> already exists.";
} else {
    $idx = $conn->query("ALTER TABLE `al_exam_submissions` ADD INDEX `idx_al_teacher` (`teacher_id`)");
    $steps[] = $idx ? "Index <strong>idx_al_teacher</strong> created." : "Index creation failed (non-critical): " . $conn->error;
}

// Step 3: Verify teacher T_0002 exists
$teacher_id = 'T_0002';
$t_check = $conn->prepare("SELECT user_id, first_name, second_name, role FROM users WHERE user_id = ? LIMIT 1");
$t_check->bind_param("s", $teacher_id);
$t_check->execute();
$teacher = $t_check->get_result()->fetch_assoc();
$t_check->close();

if (!$teacher) {
    $errors[] = "Teacher <strong>$teacher_id</strong> NOT found in users table. Assign step skipped.";
} else {
    $teacher_name = trim($teacher['first_name'] . ' ' . $teacher['second_name']);
    $steps[] = "Teacher found: <strong>$teacher_name ($teacher_id)</strong> — Role: {$teacher['role']}";

    // Step 4: Count unassigned
    $count_res = $conn->query("SELECT COUNT(*) as total FROM `al_exam_submissions` WHERE teacher_id IS NULL OR teacher_id = ''");
    $unassigned = $count_res ? $count_res->fetch_assoc()['total'] : 0;
    $steps[] = "Found <strong>$unassigned</strong> result(s) with no teacher assigned.";

    // Step 5: Assign all → T_0002
    $update = $conn->prepare("UPDATE `al_exam_submissions` SET teacher_id = ? WHERE teacher_id IS NULL OR teacher_id = ''");
    $update->bind_param("s", $teacher_id);
    $update->execute();
    $affected = $update->affected_rows;
    $update->close();
    $steps[] = "<strong>$affected</strong> result(s) assigned to <strong>$teacher_name ($teacher_id)</strong>.";
}

// Step 6: Summary
$summary = $conn->query("
    SELECT als.teacher_id, CONCAT(u.first_name, ' ', u.second_name) as teacher_name, COUNT(*) as result_count
    FROM al_exam_submissions als
    LEFT JOIN users u ON u.user_id = als.teacher_id
    GROUP BY als.teacher_id
    ORDER BY result_count DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AL Teacher Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen p-8">
<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-red-600 to-red-800 p-8">
            <h1 class="text-2xl font-black text-white flex items-center gap-3">
                <i class="fas fa-database"></i> A/L Teacher Setup Migration
            </h1>
            <p class="text-red-100 text-sm mt-1">Adds teacher_id to al_exam_submissions &amp; assigns existing results to T_0002</p>
        </div>
        <div class="p-8 space-y-3">
            <?php foreach ($steps as $s): ?>
                <div class="p-4 bg-green-50 rounded-2xl border border-green-100 text-sm text-green-800 font-medium">
                    ✅ <?php echo $s; ?>
                </div>
            <?php endforeach; ?>
            <?php foreach ($errors as $e): ?>
                <div class="p-4 bg-red-50 rounded-2xl border border-red-100 text-sm text-red-800 font-medium">
                    ❌ <?php echo $e; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($summary && $summary->num_rows > 0): ?>
        <div class="px-8 pb-6">
            <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Assignment Summary</h2>
            <div class="overflow-hidden rounded-2xl border border-gray-100">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-[10px] font-black text-gray-400 uppercase">Teacher ID</th>
                            <th class="px-5 py-3 text-left text-[10px] font-black text-gray-400 uppercase">Name</th>
                            <th class="px-5 py-3 text-right text-[10px] font-black text-gray-400 uppercase">Results</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php while ($row = $summary->fetch_assoc()): ?>
                        <tr>
                            <td class="px-5 py-4 font-mono font-bold text-red-600"><?php echo htmlspecialchars($row['teacher_id'] ?? 'NULL'); ?></td>
                            <td class="px-5 py-4 font-bold text-gray-800"><?php echo htmlspecialchars($row['teacher_name'] ?? 'Unassigned'); ?></td>
                            <td class="px-5 py-4 text-right font-black text-gray-900"><?php echo $row['result_count']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="px-8 pb-8">
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-800 font-medium">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong>Important:</strong> Delete <code>setup_al_teacher.php</code> from your server after running. It should only run once.
            </div>
        </div>
    </div>
</div>
</body>
</html>
