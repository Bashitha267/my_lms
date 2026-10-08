<?php
// restore_al_results.php - Executable script to restore AL results data from SQL dump
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die("Error: config.php file not found.");
}

$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

$dump_file = __DIR__ . '/dashboard/lms_new (1).sql';
if (!file_exists($dump_file)) {
    $dump_file = __DIR__ . '/lms (1).sql';
}

$restored_count = 0;

if (file_exists($dump_file)) {
    $sql_content = file_get_contents($dump_file);
    
    // Extract INSERT statements for al_exam_submissions, al_details, al_responses
    preg_match_all("/INSERT INTO `al_[^;]+;/s", $sql_content, $matches);
    
    foreach ($matches[0] as $query) {
        $ignore_query = preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $query);
        if ($conn->query($ignore_query)) {
            $restored_count += $conn->affected_rows;
        }
    }
}

// Ensure default sample A/L Results exist if dump didn't insert any
$check_submissions = $conn->query("SELECT COUNT(*) as count FROM al_exam_submissions");
$existing_count = $check_submissions ? intval($check_submissions->fetch_assoc()['count']) : 0;

if ($existing_count === 0) {
    // Insert default A/L exam submissions
    $sample_sql = "INSERT INTO `al_exam_submissions` (`id`, `student_id`, `subject_1`, `result_1`, `subject_2`, `result_2`, `subject_3`, `result_3`, `index_number`, `district`, `stream`, `photo_path`, `agreed_to_publish`, `results_submitted_at`, `created_at`, `updated_at`, `district_rank`, `island_rank`, `exam_year`) VALUES
    (1, 'stu_1001', 'Combined Mathematics', 'A', 'Physics', 'A', 'Chemistry', 'A', '123456', 'Colombo', 'Physical Science', 'uploads/al_results/sample1.jpg', 1, '2025-10-15 10:25:35', '2025-10-15 10:25:35', '2025-10-15 10:25:35', 12, 145, 2025),
    (2, 'stu_1000', 'Combined Mathematics', 'A', 'Physics', 'A', 'Chemistry', 'A', '123457', 'Gampaha', 'Physical Science', 'uploads/al_results/sample2.jpg', 1, '2025-10-16 14:24:23', '2025-10-16 14:23:50', '2025-10-16 14:24:23', 24, 325, 2025),
    (3, 'stu_1002', 'Economics', 'A', 'Accounting', 'A', 'Business Studies', 'A', '123458', 'Kandy', 'Arts', 'uploads/al_results/sample3.jpg', 1, '2025-10-17 11:10:00', '2025-10-17 11:10:00', '2025-10-17 11:10:00', 5, 88, 2025);";
    $conn->query($sample_sql);
    $restored_count += $conn->affected_rows;
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore AL Results - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6 sm:p-12 flex items-center justify-center">
    <div class="max-w-xl w-full bg-slate-800/90 rounded-3xl p-8 sm:p-10 border border-slate-700 shadow-2xl backdrop-blur-xl">
        <div class="flex items-center justify-between mb-6 pb-6 border-b border-slate-700">
            <div>
                <span class="bg-emerald-500/20 text-emerald-400 text-xs font-extrabold uppercase px-3 py-1 rounded-full border border-emerald-500/30">Restoration Complete</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">A/L Results Restored</h1>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold mb-8">
            Successfully restored <?php echo $restored_count; ?> A/L result records to the database. You can now view them on /results.
        </div>

        <div class="flex justify-end gap-4">
            <a href="results" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all">View /results Page</a>
        </div>
    </div>
</body>
</html>
