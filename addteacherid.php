<?php
// addteacherid.php - Production-ready database schema updater for teacher_id in student_enrollment
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

$messages = [];

// 1. Check and add teacher_id column to student_enrollment if missing
$col_check = $conn->query("SHOW COLUMNS FROM student_enrollment LIKE 'teacher_id'");
if ($col_check && $col_check->num_rows === 0) {
    $alter = $conn->query("ALTER TABLE student_enrollment ADD COLUMN teacher_id VARCHAR(20) DEFAULT NULL AFTER student_id, ADD INDEX idx_teacher_id (teacher_id)");
    if ($alter) {
        $messages[] = ['type' => 'success', 'text' => 'Successfully added teacher_id column and index to student_enrollment table.'];
    } else {
        $messages[] = ['type' => 'error', 'text' => 'Error adding column: ' . $conn->error];
    }
} else {
    $messages[] = ['type' => 'info', 'text' => 'teacher_id column already exists in student_enrollment table.'];
}

// 2. Backfill teacher_id for existing records from teacher_assignments
$backfill_sql = "
    UPDATE student_enrollment se
    JOIN teacher_assignments ta 
      ON se.stream_subject_id = ta.stream_subject_id 
     AND se.academic_year = ta.academic_year
    SET se.teacher_id = ta.teacher_id
    WHERE se.teacher_id IS NULL
";
if ($conn->query($backfill_sql)) {
    $affected = $conn->affected_rows;
    $messages[] = ['type' => 'success', 'text' => "Backfill complete. Updated $affected existing enrollment records with matching teacher_id."];
} else {
    $messages[] = ['type' => 'warning', 'text' => 'Backfill notice: ' . $conn->error];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Schema Update - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6 sm:p-12 flex items-center justify-center">
    <div class="max-w-2xl w-full bg-slate-800/90 rounded-3xl p-8 sm:p-10 border border-slate-700 shadow-2xl backdrop-blur-xl">
        <div class="flex items-center justify-between mb-8 pb-6 border-b border-slate-700">
            <div>
                <span class="bg-emerald-500/20 text-emerald-400 text-xs font-extrabold uppercase px-3 py-1 rounded-full border border-emerald-500/30">Database Migration</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Student Enrollment Teacher Isolation</h1>
            </div>
        </div>

        <div class="space-y-4 mb-8">
            <?php foreach ($messages as $msg): ?>
                <div class="p-4 rounded-2xl text-xs font-bold border flex items-center gap-3 <?php 
                    echo match($msg['type']) {
                        'success' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                        'error'   => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                        'warning' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                        default   => 'bg-blue-500/10 text-blue-400 border-blue-500/30'
                    };
                ?>">
                    <i class="fas fa-check-circle text-base"></i>
                    <span><?php echo htmlspecialchars($msg['text']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all">Go to Home</a>
        </div>
    </div>
</body>
</html>
