<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'super_admin']) && $role !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$type = $_GET['type'] ?? ''; // 'stream' or 'class'
$id = intval($_GET['id'] ?? 0);

if ($id <= 0 || ($type !== 'stream' && $type !== 'class')) {
    header('Location: request_al_details.php');
    exit;
}

// Teacher permission check: teachers can only view their assigned classes
if ($role === 'teacher') {
    if ($type !== 'class') {
        header('Location: request_al_details.php');
        exit;
    }
    $stmt_perm = $conn->prepare("SELECT 1 FROM teacher_assignments WHERE teacher_id = ? AND stream_subject_id = ?");
    $stmt_perm->bind_param("si", $user_id, $id);
    $stmt_perm->execute();
    $perm_res = $stmt_perm->get_result();
    if ($perm_res->num_rows === 0) {
        $stmt_perm->close();
        header('Location: request_al_details.php?error=unauthorized');
        exit;
    }
    $stmt_perm->close();
}

function has_column(mysqli $conn, string $table, string $column): bool {
    $safe_table = $conn->real_escape_string($table);
    $safe_column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_column}'");
    return ($res && $res->num_rows > 0);
}

$has_district_rank = has_column($conn, 'al_exam_submissions', 'district_rank');
$has_island_rank = has_column($conn, 'al_exam_submissions', 'island_rank');
$has_exam_year = has_column($conn, 'al_exam_submissions', 'exam_year');

$district_rank_select = $has_district_rank ? 'als.district_rank' : 'NULL AS district_rank';
$island_rank_select = $has_island_rank ? 'als.island_rank' : 'NULL AS island_rank';
$exam_year_select = $has_exam_year ? 'als.exam_year' : 'NULL AS exam_year';

$title_name = '';
$stream_name_only = '';
$subject_name_for_matching = '';

// 1. Fetch Header Info
if ($type === 'stream') {
    $stmt = $conn->prepare("SELECT name FROM streams WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$res) {
        header('Location: request_al_details.php');
        exit;
    }
    $title_name = $res['name'];
    $stream_name_only = $res['name'];
} else { // class
    $stmt = $conn->prepare("SELECT s.name as stream_name, sub.name as subject_name 
                            FROM stream_subjects ss
                            JOIN streams s ON ss.stream_id = s.id
                            JOIN subjects sub ON ss.subject_id = sub.id
                            WHERE ss.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$res) {
        header('Location: request_al_details.php');
        exit;
    }
    $title_name = $res['subject_name'] . ' (' . $res['stream_name'] . ')';
    $stream_name_only = $res['stream_name'];
    $subject_name_for_matching = strtolower(trim($res['subject_name']));
}

// 2. Fetch Enrolled Students & Submissions
if ($type === 'stream') {
    $query = "SELECT u.user_id, u.first_name, u.second_name, u.whatsapp_number, u.mobile_number,
                     als.id as submission_id, als.subject_1, als.subject_2, als.subject_3, 
                     als.index_number, als.district, als.photo_path, als.created_at,
                     als.result_1, als.result_2, als.result_3, als.results_submitted_at,
                     als.agreed_to_publish, {$district_rank_select}, {$island_rank_select}, {$exam_year_select}
              FROM users u
              INNER JOIN student_enrollment se ON u.user_id = se.student_id
              INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
              LEFT JOIN al_exam_submissions als ON u.user_id = als.student_id
              WHERE ss.stream_id = ? AND se.status = 'active' AND u.status = 1
              ORDER BY (als.id IS NOT NULL) DESC, (als.district_rank IS NULL OR als.district_rank = 0) ASC, als.district_rank ASC, als.id DESC, u.first_name ASC";
} else {
    $query = "SELECT u.user_id, u.first_name, u.second_name, u.whatsapp_number, u.mobile_number,
                     als.id as submission_id, als.subject_1, als.subject_2, als.subject_3, 
                     als.index_number, als.district, als.photo_path, als.created_at,
                     als.result_1, als.result_2, als.result_3, als.results_submitted_at,
                     als.agreed_to_publish, {$district_rank_select}, {$island_rank_select}, {$exam_year_select}
              FROM users u
              INNER JOIN student_enrollment se ON u.user_id = se.student_id
              LEFT JOIN al_exam_submissions als ON u.user_id = als.student_id
              WHERE se.stream_subject_id = ? AND se.status = 'active' AND u.status = 1
              ORDER BY (als.id IS NOT NULL) DESC, (als.district_rank IS NULL OR als.district_rank = 0) ASC, als.district_rank ASC, als.id DESC, u.first_name ASC";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
$seen_students = [];
$responded_count = 0;
$results_submitted_count = 0;
$published_count = 0;
$not_published_count = 0;
$grade_counts = ['A' => 0, 'B' => 0, 'C' => 0, 'S' => 0, 'F' => 0, 'AB' => 0];

while ($row = $result->fetch_assoc()) {
    $uid = $row['user_id'];
    if (isset($seen_students[$uid])) {
        continue; // Deduplicate in case of multiple stream_subject joins
    }
    $seen_students[$uid] = true;

    // Contact number resolution
    $contact_number = !empty($row['whatsapp_number']) ? $row['whatsapp_number'] : $row['mobile_number'];
    $row['effective_phone'] = $contact_number;

    $has_submission = !empty($row['submission_id']);
    $has_results = !empty($row['results_submitted_at']);
    $agreed_pub = !empty($row['agreed_to_publish']);

    if ($has_submission) {
        $responded_count++;
    }

    $grade = null;
    if ($has_results) {
        $results_submitted_count++;
        if ($agreed_pub) {
            $published_count++;
        } else {
            $not_published_count++;
        }

        if ($type === 'class') {
            if (strtolower(trim($row['subject_1'] ?? '')) === $subject_name_for_matching) {
                $grade = strtoupper(trim($row['result_1'] ?? ''));
            } elseif (strtolower(trim($row['subject_2'] ?? '')) === $subject_name_for_matching) {
                $grade = strtoupper(trim($row['result_2'] ?? ''));
            } elseif (strtolower(trim($row['subject_3'] ?? '')) === $subject_name_for_matching) {
                $grade = strtoupper(trim($row['result_3'] ?? ''));
            }

            if ($grade && isset($grade_counts[$grade])) {
                $grade_counts[$grade]++;
            }
        }
    }

    $row['subject_grade'] = $grade;
    $students[] = $row;
}
$stmt->close();

$total_count = count($students);
$not_responded_count = $total_count - $responded_count;
$response_rate = $total_count > 0 ? round(($responded_count / $total_count) * 100, 1) : 0;
$results_rate = $total_count > 0 ? round(($results_submitted_count / $total_count) * 100, 1) : 0;
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
    <title>A/L Responses - <?php echo htmlspecialchars($title_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <?php 
    if (in_array($role, ['admin', 'super_admin'])) {
        $admin_header_prefix = '../admin/';
        include '../admin/header.php';
    } else {
        include 'navbar.php'; 
    }
    ?>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Top Navigation / Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <a href="request_al_details.php" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-blue-600 transition-colors mb-2">
                    <i class="fas fa-arrow-left text-[11px]"></i>
                    <span>Back to A/L Collections</span>
                </a>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-xl font-bold text-slate-900 leading-tight">
                        <?php echo htmlspecialchars($title_name); ?>
                    </h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?php echo $type === 'stream' ? 'bg-blue-100 text-blue-700' : 'bg-indigo-100 text-indigo-700'; ?>">
                        <?php echo $type === 'stream' ? 'Stream Report' : 'Class Report'; ?>
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Track student examination subject submissions, results, ranks and publication consents.
                </p>
            </div>
            
            <div class="flex items-center gap-2">
                <button type="button" onclick="exportToCSV()" class="px-3 py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg shadow-xs flex items-center gap-1.5 transition-colors">
                    <i class="fas fa-file-csv text-emerald-600"></i>
                    <span>Export CSV</span>
                </button>
            </div>
        </div>

        <!-- 5-Card Metrics Summary Bar -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 mb-6">
            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-users text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-gray-900 leading-tight"><?php echo number_format($total_count); ?></div>
                    <div class="text-[11px] text-gray-500 font-medium">Total Enrolled</div>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-check-circle text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-emerald-600 leading-tight">
                        <?php echo number_format($responded_count); ?>
                        <span class="text-xs font-normal text-gray-400">(<?php echo $response_rate; ?>%)</span>
                    </div>
                    <div class="text-[11px] text-gray-500 font-medium">Subjects Submitted</div>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-clock text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-amber-600 leading-tight"><?php echo number_format($not_responded_count); ?></div>
                    <div class="text-[11px] text-gray-500 font-medium">Pending Subjects</div>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-award text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-blue-600 leading-tight">
                        <?php echo number_format($results_submitted_count); ?>
                        <span class="text-xs font-normal text-gray-400">(<?php echo $results_rate; ?>%)</span>
                    </div>
                    <div class="text-[11px] text-gray-500 font-medium">Results Entered</div>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3 col-span-2 sm:col-span-1">
                <div class="w-9 h-9 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-bullhorn text-sm"></i>
                </div>
                <div>
                    <div class="text-base font-bold text-teal-600 leading-tight"><?php echo number_format($published_count); ?></div>
                    <div class="text-[11px] text-gray-500 font-medium">Agreed to Publish</div>
                </div>
            </div>
        </div>

        <!-- Grade Distribution Chips (Class view only) -->
        <?php if ($type === 'class'): ?>
        <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                    <i class="fas fa-chart-pie text-blue-500 text-[11px]"></i> Subject Grade Breakdown
                </span>
                <span class="text-[11px] text-slate-400">Click a grade to filter the list</span>
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                <?php 
                $grade_styles = [
                    'A'  => 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
                    'B'  => 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100',
                    'C'  => 'bg-cyan-50 text-cyan-700 border-cyan-200 hover:bg-cyan-100',
                    'S'  => 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100',
                    'F'  => 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100',
                    'AB' => 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'
                ];
                foreach ($grade_counts as $g => $cnt): 
                    $style = $grade_styles[$g] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                ?>
                <button type="button" 
                        onclick="setQuickGradeFilter('<?php echo $g; ?>')"
                        class="px-2.5 py-1.5 rounded-lg border text-center transition-colors cursor-pointer <?php echo $style; ?>">
                    <div class="text-sm font-bold leading-tight"><?php echo $cnt; ?></div>
                    <div class="text-[10px] font-semibold uppercase">Grade <?php echo $g; ?></div>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search & Filter Controls -->
        <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs mb-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 w-full sm:w-auto flex-1">
                <div class="relative flex-1 sm:w-80">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" 
                           id="studentSearchInput" 
                           onkeyup="filterTable()" 
                           placeholder="Search by student name, user ID or index..." 
                           class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500">
                </div>
                <select id="statusFilter" onchange="filterTable()" class="px-2.5 py-1.5 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-600">
                    <option value="all">All Students (<?php echo $total_count; ?>)</option>
                    <option value="responded">Subjects Submitted</option>
                    <option value="pending">Pending Subjects</option>
                    <option value="results">Results Submitted</option>
                    <option value="published">Agreed to Publish</option>
                    <option value="unpublished">Declined Publish</option>
                    <?php if ($type === 'class'): ?>
                        <option value="A">Grade A</option>
                        <option value="B">Grade B</option>
                        <option value="C">Grade C</option>
                        <option value="S">Grade S</option>
                        <option value="F">Grade F</option>
                        <option value="AB">Absent (AB)</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="text-[11px] text-gray-500 font-medium whitespace-nowrap self-end sm:self-center">
                Showing <span id="visibleCount" class="font-bold text-gray-900"><?php echo $total_count; ?></span> of <?php echo $total_count; ?> students
            </div>
        </div>

        <!-- Student Responses Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs" id="studentsTable">
                    <thead>
                        <tr class="bg-slate-50 border-b border-gray-200 text-[11px] uppercase font-semibold text-gray-500 tracking-wider">
                            <th class="px-4 py-3">Student</th>
                            <th class="px-3 py-3">User ID</th>
                            <th class="px-3 py-3">Subjects Status</th>
                            <th class="px-3 py-3">Result / Grade</th>
                            <th class="px-3 py-3">District Rank</th>
                            <th class="px-3 py-3">Island Rank</th>
                            <th class="px-3 py-3">Consent</th>
                            <th class="px-4 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($students as $student): 
                            $has_sub = !empty($student['submission_id']);
                            $has_res = !empty($student['results_submitted_at']);
                            $agreed  = !empty($student['agreed_to_publish']);
                            $fullname = trim($student['first_name'] . ' ' . $student['second_name']);
                            $phone = $student['effective_phone'];
                            $clean_phone = preg_replace('/\D/', '', $phone);
                            $grade = $student['subject_grade'] ?? '';
                        ?>
                        <tr class="hover:bg-slate-50/70 transition-colors student-row"
                            data-name="<?php echo htmlspecialchars(strtolower($fullname)); ?>"
                            data-userid="<?php echo htmlspecialchars(strtolower($student['user_id'])); ?>"
                            data-index="<?php echo htmlspecialchars(strtolower($student['index_number'] ?? '')); ?>"
                            data-grade="<?php echo htmlspecialchars($grade); ?>"
                            data-responded="<?php echo $has_sub ? 'yes' : 'no'; ?>"
                            data-results="<?php echo $has_res ? 'yes' : 'no'; ?>"
                            data-published="<?php echo ($has_res && $agreed) ? 'yes' : 'no'; ?>"
                            data-unpublished="<?php echo ($has_res && !$agreed) ? 'yes' : 'no'; ?>">
                            
                            <!-- Student Info -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <?php if (!empty($student['photo_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($student['photo_path']); ?>" alt="Photo" class="w-8 h-8 rounded-lg object-cover border border-gray-200 shrink-0">
                                    <?php else: ?>
                                        <div class="w-8 h-8 rounded-lg bg-slate-100 border border-gray-200 text-slate-600 font-bold flex items-center justify-center text-xs shrink-0">
                                            <?php echo strtoupper(substr($student['first_name'] ?: 'U', 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-semibold text-gray-900 leading-tight">
                                            <?php echo htmlspecialchars($fullname ?: 'Student'); ?>
                                        </div>
                                        <?php if (!empty($phone)): ?>
                                            <a href="https://wa.me/<?php echo $clean_phone; ?>" target="_blank" class="text-[11px] text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-1 mt-0.5">
                                                <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($phone); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- User ID -->
                            <td class="px-3 py-3 font-mono text-[11px] text-gray-500">
                                <?php echo htmlspecialchars($student['user_id']); ?>
                            </td>

                            <!-- Subjects Response Status -->
                            <td class="px-3 py-3">
                                <?php if ($has_sub): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fas fa-check-circle text-[9px]"></i> Submitted
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        <i class="fas fa-clock text-[9px]"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Result / Grade -->
                            <td class="px-3 py-3">
                                <?php if ($type === 'class'): ?>
                                    <?php if ($grade !== ''): 
                                        $g_cls = [
                                            'A'  => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold',
                                            'B'  => 'bg-blue-100 text-blue-800 border-blue-300 font-bold',
                                            'C'  => 'bg-cyan-100 text-cyan-800 border-cyan-300 font-bold',
                                            'S'  => 'bg-slate-100 text-slate-800 border-slate-300 font-bold',
                                            'F'  => 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
                                            'AB' => 'bg-gray-100 text-gray-600 border-gray-300 font-semibold'
                                        ];
                                        $cls = $g_cls[$grade] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                                    ?>
                                        <span class="inline-flex items-center justify-center w-7 h-5 rounded text-xs border <?php echo $cls; ?>">
                                            <?php echo htmlspecialchars($grade); ?>
                                        </span>
                                    <?php elseif ($has_res): ?>
                                        <span class="text-gray-400 text-[11px] italic">Not Matched</span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-[11px] italic">Waiting</span>
                                    <?php endif; ?>
                                <?php else: // Stream view ?>
                                    <?php if ($has_res): ?>
                                        <div class="flex items-center gap-1">
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[10px] font-bold text-slate-700" title="<?php echo htmlspecialchars($student['subject_1']); ?>">
                                                <?php echo htmlspecialchars($student['result_1'] ?: '-'); ?>
                                            </span>
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[10px] font-bold text-slate-700" title="<?php echo htmlspecialchars($student['subject_2']); ?>">
                                                <?php echo htmlspecialchars($student['result_2'] ?: '-'); ?>
                                            </span>
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[10px] font-bold text-slate-700" title="<?php echo htmlspecialchars($student['subject_3']); ?>">
                                                <?php echo htmlspecialchars($student['result_3'] ?: '-'); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-[11px] italic">Waiting</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <!-- District Rank -->
                            <td class="px-3 py-3 font-medium text-gray-700">
                                <?php if (!empty($student['district_rank'])): ?>
                                    <span class="inline-flex items-center gap-1 font-bold text-slate-800">
                                        <i class="fas fa-medal text-[10px] text-amber-500"></i>
                                        <?php echo number_format($student['district_rank']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-300">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Island Rank -->
                            <td class="px-3 py-3 font-medium text-gray-700">
                                <?php if (!empty($student['island_rank'])): ?>
                                    <span class="inline-flex items-center gap-1 font-bold text-indigo-700">
                                        <i class="fas fa-trophy text-[10px] text-indigo-500"></i>
                                        <?php echo number_format($student['island_rank']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-300">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Consent to Publish -->
                            <td class="px-3 py-3">
                                <?php if ($has_res): ?>
                                    <?php if ($agreed): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fas fa-check text-[9px]"></i> Published
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                            <i class="fas fa-ban text-[9px]"></i> Private
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-300 text-[11px]">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Action -->
                            <td class="px-4 py-3 text-center">
                                <?php if ($has_sub): ?>
                                    <button type="button" 
                                            onclick='viewDetails(<?php echo json_encode($student, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-white hover:bg-blue-50 border border-gray-200 hover:border-blue-300 text-gray-700 hover:text-blue-600 rounded-md text-[11px] font-medium transition-colors shadow-2xs"
                                            title="View Student Result Card">
                                        <i class="fas fa-id-card text-[11px]"></i>
                                        <span>Details</span>
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-300 text-[11px]">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($students)): ?>
                        <tr id="emptyTableRow">
                            <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                <i class="fas fa-users-slash text-3xl mb-2 block text-gray-300"></i>
                                <p class="text-xs font-medium">No students enrolled in this <?php echo $type === 'stream' ? 'stream' : 'class'; ?>.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Redesigned Student Details Modal -->
    <div id="detailsModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full overflow-hidden border border-gray-200 transform transition-all scale-100">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-5 py-4 flex items-center justify-between text-white">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center text-xs">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm leading-tight">Student A/L Result Profile</h3>
                        <p class="text-[11px] text-blue-100">Verified examination submission</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal()" class="w-7 h-7 rounded-lg hover:bg-white/20 flex items-center justify-center text-white/80 hover:text-white transition-colors">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="p-5">
                <!-- Student Header Card -->
                <div class="flex items-center gap-3.5 p-3 rounded-xl bg-slate-50 border border-gray-200 mb-4">
                    <img id="modalPhoto" src="" alt="Student Photo" class="w-14 h-14 rounded-xl object-cover border border-gray-200 bg-white shadow-2xs">
                    <div class="min-w-0 flex-1">
                        <h4 id="modalName" class="font-bold text-sm text-gray-900 truncate">Student Name</h4>
                        <div class="flex items-center gap-2 text-[11px] text-gray-500 mt-0.5">
                            <span>ID: <strong id="modalId" class="font-mono text-gray-700"></strong></span>
                            <span>•</span>
                            <span id="modalPhoneWrap" class="inline-flex items-center gap-1 text-emerald-600">
                                <i class="fab fa-whatsapp"></i> <span id="modalPhone">-</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Submission Details Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 mb-4 text-xs">
                    <div class="bg-slate-50 p-2.5 rounded-lg border border-gray-200">
                        <span class="text-[10px] text-gray-400 font-semibold uppercase block mb-0.5">Index Number</span>
                        <span id="modalIndex" class="font-bold text-gray-900 font-mono">-</span>
                    </div>
                    <div class="bg-slate-50 p-2.5 rounded-lg border border-gray-200">
                        <span class="text-[10px] text-gray-400 font-semibold uppercase block mb-0.5">District</span>
                        <span id="modalDistrict" class="font-semibold text-gray-800">-</span>
                    </div>
                    <div class="bg-slate-50 p-2.5 rounded-lg border border-gray-200">
                        <span class="text-[10px] text-gray-400 font-semibold uppercase block mb-0.5">Exam Year</span>
                        <span id="modalExamYear" class="font-semibold text-gray-800">-</span>
                    </div>
                    <div class="bg-slate-50 p-2.5 rounded-lg border border-gray-200">
                        <span class="text-[10px] text-gray-400 font-semibold uppercase block mb-0.5">District Rank</span>
                        <span id="modalDistrictRank" class="font-bold text-amber-600">-</span>
                    </div>
                    <div class="bg-slate-50 p-2.5 rounded-lg border border-gray-200">
                        <span class="text-[10px] text-gray-400 font-semibold uppercase block mb-0.5">Island Rank</span>
                        <span id="modalIslandRank" class="font-bold text-indigo-600">-</span>
                    </div>
                    <div class="bg-slate-50 p-2.5 rounded-lg border border-gray-200">
                        <span class="text-[10px] text-gray-400 font-semibold uppercase block mb-0.5">Publish Consent</span>
                        <span id="modalPublish" class="font-semibold text-gray-800">-</span>
                    </div>
                </div>

                <!-- 3 Examination Subjects & Results Card -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-4">
                    <div class="bg-slate-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between text-xs font-semibold text-gray-700">
                        <span>Examination Subject</span>
                        <span>Grade / Result</span>
                    </div>
                    <div class="divide-y divide-gray-100 text-xs">
                        <div class="px-3 py-2 flex items-center justify-between">
                            <span id="modalSub1" class="font-medium text-gray-800 truncate pr-2">Subject 1</span>
                            <span id="modalRes1" class="font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-800 text-[11px]">-</span>
                        </div>
                        <div class="px-3 py-2 flex items-center justify-between">
                            <span id="modalSub2" class="font-medium text-gray-800 truncate pr-2">Subject 2</span>
                            <span id="modalRes2" class="font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-800 text-[11px]">-</span>
                        </div>
                        <div class="px-3 py-2 flex items-center justify-between">
                            <span id="modalSub3" class="font-medium text-gray-800 truncate pr-2">Subject 3</span>
                            <span id="modalRes3" class="font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-800 text-[11px]">-</span>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                    <a id="modalWhatsAppLink" href="#" target="_blank" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg shadow-xs flex items-center gap-1.5 transition-colors">
                        <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp Student</span>
                    </a>
                    <button type="button" onclick="closeModal()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewDetails(data) {
            const fullName = (data.first_name || '') + ' ' + (data.second_name || '');
            document.getElementById('modalName').textContent = fullName.trim() || 'Student';
            document.getElementById('modalId').textContent = data.user_id || '-';
            
            const phone = data.effective_phone || data.whatsapp_number || data.mobile_number || '';
            const phoneEl = document.getElementById('modalPhone');
            const waLink = document.getElementById('modalWhatsAppLink');
            
            if (phone) {
                phoneEl.textContent = phone;
                const cleanPhone = phone.replace(/\D/g, '');
                waLink.href = 'https://wa.me/' + cleanPhone;
                waLink.classList.remove('hidden');
                document.getElementById('modalPhoneWrap').classList.remove('hidden');
            } else {
                phoneEl.textContent = 'Not provided';
                waLink.classList.add('hidden');
                document.getElementById('modalPhoneWrap').classList.add('hidden');
            }

            document.getElementById('modalIndex').textContent = data.index_number || 'Not provided';
            document.getElementById('modalDistrict').textContent = data.district || '-';
            document.getElementById('modalExamYear').textContent = data.exam_year || '-';
            document.getElementById('modalDistrictRank').textContent = data.district_rank ? '#' + data.district_rank : '-';
            document.getElementById('modalIslandRank').textContent = data.island_rank ? '#' + data.island_rank : '-';
            
            // Publish status
            const pubEl = document.getElementById('modalPublish');
            if (data.results_submitted_at) {
                if (data.agreed_to_publish == 1) {
                    pubEl.innerHTML = '<span class="text-emerald-600 font-bold"><i class="fas fa-check-circle text-[11px]"></i> Agreed to Publish</span>';
                } else {
                    pubEl.innerHTML = '<span class="text-slate-500 font-semibold"><i class="fas fa-lock text-[11px]"></i> Kept Private</span>';
                }
            } else {
                pubEl.innerHTML = '<span class="text-amber-500 font-semibold"><i class="fas fa-clock text-[11px]"></i> Results Pending</span>';
            }
            
            document.getElementById('modalSub1').textContent = data.subject_1 || 'Subject 1';
            document.getElementById('modalSub2').textContent = data.subject_2 || 'Subject 2';
            document.getElementById('modalSub3').textContent = data.subject_3 || 'Subject 3';
            
            renderGradeBadge('modalRes1', data.result_1);
            renderGradeBadge('modalRes2', data.result_2);
            renderGradeBadge('modalRes3', data.result_3);
            
            const photoEl = document.getElementById('modalPhoto');
            if (data.photo_path) {
                photoEl.src = '../' + data.photo_path;
            } else {
                photoEl.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(fullName || 'User') + '&background=e2e8f0&color=334155&size=128';
            }
            
            document.getElementById('detailsModal').classList.remove('hidden');
        }

        function renderGradeBadge(elementId, grade) {
            const el = document.getElementById(elementId);
            if (!grade) {
                el.textContent = '-';
                el.className = 'font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-400 text-[11px]';
                return;
            }
            el.textContent = grade;
            const g = grade.toUpperCase();
            if (g === 'A') el.className = 'font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-300 text-[11px]';
            else if (g === 'B') el.className = 'font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-800 border border-blue-300 text-[11px]';
            else if (g === 'C') el.className = 'font-bold px-2 py-0.5 rounded bg-cyan-100 text-cyan-800 border border-cyan-300 text-[11px]';
            else if (g === 'S') el.className = 'font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-300 text-[11px]';
            else if (g === 'F') el.className = 'font-bold px-2 py-0.5 rounded bg-rose-100 text-rose-800 border border-rose-300 text-[11px]';
            else el.className = 'font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-700 border border-gray-300 text-[11px]';
        }
        
        function closeModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
        
        // Close on backdrop click
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Quick grade filter from chips
        function setQuickGradeFilter(grade) {
            const filterSelect = document.getElementById('statusFilter');
            filterSelect.value = grade;
            filterTable();
        }

        // Unified live table filtering
        function filterTable() {
            const searchVal = (document.getElementById("studentSearchInput").value || "").toLowerCase().trim();
            const filterVal = document.getElementById("statusFilter").value;
            const rows = document.querySelectorAll("#studentsTable tbody tr.student-row");
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const userId = row.getAttribute('data-userid') || '';
                const index = row.getAttribute('data-index') || '';
                const grade = row.getAttribute('data-grade') || '';
                const responded = row.getAttribute('data-responded') || 'no';
                const results = row.getAttribute('data-results') || 'no';
                const published = row.getAttribute('data-published') || 'no';
                const unpublished = row.getAttribute('data-unpublished') || 'no';

                // Keyword match
                const matchSearch = !searchVal || name.includes(searchVal) || userId.includes(searchVal) || index.includes(searchVal);

                // Category match
                let matchFilter = false;
                if (filterVal === 'all') {
                    matchFilter = true;
                } else if (filterVal === 'responded') {
                    matchFilter = (responded === 'yes');
                } else if (filterVal === 'pending') {
                    matchFilter = (responded === 'no');
                } else if (filterVal === 'results') {
                    matchFilter = (results === 'yes');
                } else if (filterVal === 'published') {
                    matchFilter = (published === 'yes');
                } else if (filterVal === 'unpublished') {
                    matchFilter = (unpublished === 'yes');
                } else {
                    // Grade filter (A, B, C, S, F, AB)
                    matchFilter = (grade.toUpperCase() === filterVal.toUpperCase());
                }

                if (matchSearch && matchFilter) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById("visibleCount").textContent = visibleCount;
        }

        // Export table data to CSV
        function exportToCSV() {
            const rawData = <?php echo json_encode($students, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            if (!rawData || rawData.length === 0) {
                alert('No student records available to export.');
                return;
            }

            const headers = [
                "User ID", "Full Name", "Contact Number", 
                "Subjects Submitted", "Index Number", "District", "Exam Year",
                "District Rank", "Island Rank", "Consent Published",
                "Subject 1", "Result 1", 
                "Subject 2", "Result 2", 
                "Subject 3", "Result 3"
            ];

            const csvRows = [headers.join(',')];

            rawData.forEach(s => {
                const fullName = ((s.first_name || '') + ' ' + (s.second_name || '')).trim();
                const phone = s.effective_phone || s.whatsapp_number || s.mobile_number || '';
                const subStatus = s.submission_id ? 'Yes' : 'No';
                const pubStatus = s.results_submitted_at ? (s.agreed_to_publish == 1 ? 'Published' : 'Private') : 'Pending';

                const row = [
                    escapeCsv(s.user_id),
                    escapeCsv(fullName),
                    escapeCsv(phone),
                    escapeCsv(subStatus),
                    escapeCsv(s.index_number || ''),
                    escapeCsv(s.district || ''),
                    escapeCsv(s.exam_year || ''),
                    escapeCsv(s.district_rank || ''),
                    escapeCsv(s.island_rank || ''),
                    escapeCsv(pubStatus),
                    escapeCsv(s.subject_1 || ''),
                    escapeCsv(s.result_1 || ''),
                    escapeCsv(s.subject_2 || ''),
                    escapeCsv(s.result_2 || ''),
                    escapeCsv(s.subject_3 || ''),
                    escapeCsv(s.result_3 || '')
                ];
                csvRows.push(row.join(','));
            });

            const csvString = csvRows.join("\r\n");
            const blob = new Blob(["\uFEFF" + csvString], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            const filename = "AL_Responses_<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $title_name); ?>_" + new Date().toISOString().slice(0,10) + ".csv";
            
            link.setAttribute("href", url);
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function escapeCsv(val) {
            if (val === null || val === undefined) return '""';
            const str = String(val).replace(/"/g, '""');
            return '"' + str + '"';
        }
    </script>
</body>
</html>
