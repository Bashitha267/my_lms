<?php
session_start();
require_once __DIR__ . '/../config.php';

// Fallback (navbar.php usually defines this)
$root_url = $root_url ?? '../';

function has_column(mysqli $conn, string $table, string $column): bool {
    $safe_table = $conn->real_escape_string($table);
    $safe_column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_column}'");
    return ($res && $res->num_rows > 0);
}

$has_district_rank = has_column($conn, 'al_exam_submissions', 'district_rank');
$has_island_rank = has_column($conn, 'al_exam_submissions', 'island_rank');
$has_exam_year = has_column($conn, 'al_exam_submissions', 'exam_year');

$district_rank_select = $has_district_rank ? 'district_rank' : 'NULL AS district_rank';
$island_rank_select = $has_island_rank ? 'island_rank' : 'NULL AS island_rank';
$exam_year_select = $has_exam_year 
    ? 'COALESCE(NULLIF(als.exam_year, 0), YEAR(als.results_submitted_at), YEAR(als.created_at)) AS display_exam_year' 
    : 'COALESCE(YEAR(als.results_submitted_at), YEAR(als.created_at)) AS display_exam_year';

// If logged in as student, fetch own submission state for CTA
$student_submission = null;
if (!empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'student')) {
    $uid = $_SESSION['user_id'];
    $student_query = "SELECT subject_1, subject_2, subject_3, result_1, result_2, result_3, district, {$district_rank_select}, {$island_rank_select}, agreed_to_publish, results_submitted_at FROM al_exam_submissions WHERE student_id = ? LIMIT 1";
    $stmt = $conn->prepare($student_query);
    if ($stmt) {
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $student_submission = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Pre-fetch all teachers for fast lookup
$all_teachers_lookup = [];
$t_query_res = $conn->query("SELECT user_id, first_name, second_name, profile_picture FROM users WHERE role = 'teacher'");
if ($t_query_res) {
    while ($t_row = $t_query_res->fetch_assoc()) {
        $all_teachers_lookup[$t_row['user_id']] = [
            'name' => trim($t_row['first_name'] . ' ' . ($t_row['second_name'] ?? '')),
            'picture' => $t_row['profile_picture'] ?? ''
        ];
    }
}

// Fetch all published results
$query = "SELECT als.*, {$district_rank_select}, {$island_rank_select}, {$exam_year_select},
                    u.first_name, u.second_name, u.profile_picture
                    FROM al_exam_submissions als
                    LEFT JOIN users u ON u.user_id = als.student_id
                    WHERE als.agreed_to_publish = 1
                        AND COALESCE(als.result_1, '') <> ''
                        AND COALESCE(als.result_2, '') <> ''
                        AND COALESCE(als.result_3, '') <> ''
                    ORDER BY display_exam_year DESC, als.stream ASC,
                             CASE WHEN {$district_rank_select} IS NULL THEN 1 ELSE 0 END ASC,
                             {$district_rank_select} ASC";
$result = $conn->query($query);

$results_by_stream = [];
$all_results = [];
$streams = [];
$exam_years = [];
$teachers_map = [];
$subjects_map = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stream = trim((string)($row['stream'] ?? ''));
        $stream = $stream !== '' ? $stream : 'Not Specified';
        $row['stream_label'] = $stream;

        $year_value = !empty($row['display_exam_year']) ? (int)$row['display_exam_year'] : null;
        $row['display_exam_year'] = $year_value;

        // Parse teacher IDs (can be single ID or comma-separated list)
        $t_ids = array_filter(array_map('trim', explode(',', $row['teacher_id'] ?? '')));
        $assigned_teachers = [];
        foreach ($t_ids as $tid) {
            if (isset($all_teachers_lookup[$tid])) {
                $assigned_teachers[] = array_merge(['id' => $tid], $all_teachers_lookup[$tid]);
                $teachers_map[$tid] = $all_teachers_lookup[$tid]['name'];
            }
        }
        $row['assigned_teachers'] = $assigned_teachers;
        $row['teacher_ids_list'] = $t_ids;

        // Parse subjects
        foreach (['subject_1', 'subject_2', 'subject_3'] as $subj_key) {
            $s_val = trim((string)($row[$subj_key] ?? ''));
            if ($s_val !== '') {
                $subjects_map[$s_val] = $s_val;
            }
        }

        $all_results[] = $row;
        $streams[$stream] = true;
        if (!empty($year_value)) {
            $exam_years[(string)$year_value] = true;
        }
    }
}

// Build ordered filter lists
$streams = array_keys($streams);
sort($streams, SORT_NATURAL | SORT_FLAG_CASE);

$exam_years = array_keys($exam_years);
rsort($exam_years, SORT_NUMERIC);

ksort($subjects_map, SORT_NATURAL | SORT_FLAG_CASE);

$default_exam_year = 'all';

// Get filters from URL
$filter_stream    = isset($_GET['stream'])    ? trim($_GET['stream'])    : 'all';
$filter_exam_year = isset($_GET['exam_year']) ? trim($_GET['exam_year']) : $default_exam_year;
$filter_teacher   = isset($_GET['teacher'])   ? trim($_GET['teacher'])   : 'all';
$filter_subject   = isset($_GET['subject'])   ? trim($_GET['subject'])   : 'all';

asort($teachers_map);

// Apply filters and group by stream
foreach ($all_results as $row) {
    if ($filter_stream !== 'all' && strcasecmp(trim($row['stream_label']), trim($filter_stream)) !== 0) continue;
    if ($filter_exam_year !== 'all' && (string)($row['display_exam_year'] ?? '') !== (string)$filter_exam_year) continue;
    if ($filter_teacher !== 'all' && !in_array($filter_teacher, $row['teacher_ids_list'])) continue;
    if ($filter_subject !== 'all') {
        $s1 = trim((string)($row['subject_1'] ?? ''));
        $s2 = trim((string)($row['subject_2'] ?? ''));
        $s3 = trim((string)($row['subject_3'] ?? ''));
        if (strcasecmp($s1, $filter_subject) !== 0 &&
            strcasecmp($s2, $filter_subject) !== 0 &&
            strcasecmp($s3, $filter_subject) !== 0) {
            continue;
        }
    }
    $results_by_stream[$row['stream_label']][] = $row;
}

ksort($results_by_stream, SORT_NATURAL | SORT_FLAG_CASE);

// Sort students within each stream group by district_rank ASC (nulls last)
foreach ($results_by_stream as $stream_key => $students) {
    usort($students, function($a, $b) {
        $ra = isset($a['district_rank']) && $a['district_rank'] !== null && $a['district_rank'] !== '' ? (int)$a['district_rank'] : PHP_INT_MAX;
        $rb = isset($b['district_rank']) && $b['district_rank'] !== null && $b['district_rank'] !== '' ? (int)$b['district_rank'] : PHP_INT_MAX;
        return $ra <=> $rb;
    });
    $results_by_stream[$stream_key] = $students;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Results | Lernerr.LK</title>
    <meta name="description" content="View and submit Advanced Level results on Lernerr.LK. Celebrate outstanding academic achievements with our student community.">
    <meta name="keywords" content="Lernerr.LK A/L results, exam results portal, Sri Lanka A/L achievements">
    <meta name="author" content="Lernerr.LK">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="A/L Results Portal | Lernerr.LK">
    <meta property="og:description" content="View and submit Advanced Level results on Lernerr.LK. Celebrate outstanding academic achievements.">
    <meta property="og:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">
    <meta property="og:site_name" content="Lernerr.LK">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="A/L Results Portal | Lernerr.LK">
    <meta property="twitter:description" content="View and submit Advanced Level results on Lernerr.LK. Celebrate outstanding academic achievements.">
    <meta property="twitter:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/assests/logo.jpeg'; ?>">

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        h1, h2, h3, .font-outfit {
            font-family: 'Outfit', sans-serif;
        }
        .result-typography { color: #dc2626; font-size: 1.35rem; font-weight: 800; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-10">
        <!-- Header & Filters Section -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between mb-10 gap-8 border-b border-gray-200 pb-10">
            <div>
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">
                    Our <span class="text-red-600">Results</span>
                </h1>
                <p class="text-gray-600 mt-3 text-lg font-medium italic">අපගේ පසුගිය උසස් පෙළ ප්‍රතිඵල</p>
            </div>

            <!-- Filters -->
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col md:flex-row items-center gap-6">
                <form method="GET" class="flex flex-col md:flex-row items-center gap-6 w-full">
                    <div class="flex flex-col gap-1.5 w-full md:w-auto">
                        <label for="exam_year" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Exam Year</label>
                        <select id="exam_year" name="exam_year" class="bg-gray-50 border border-gray-100 rounded-2xl px-5 py-3 text-sm font-bold text-gray-700 min-w-[160px] focus:ring-2 focus:ring-red-500/20 focus:border-red-600 outline-none transition-all cursor-pointer" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_exam_year === 'all' ? 'selected' : ''; ?>>All Years</option>
                            <?php foreach ($exam_years as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" <?php echo (string)$filter_exam_year === (string)$year ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?> Exam
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5 w-full md:w-auto">
                        <label for="stream" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Study Stream</label>
                        <select id="stream" name="stream" class="bg-gray-50 border border-gray-100 rounded-2xl px-5 py-3 text-sm font-bold text-gray-700 min-w-[240px] focus:ring-2 focus:ring-red-500/20 focus:border-red-600 outline-none transition-all cursor-pointer" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_stream === 'all' ? 'selected' : ''; ?>>All Streams</option>
                            <?php foreach ($streams as $stream): ?>
                                <option value="<?php echo htmlspecialchars($stream); ?>" <?php echo strcasecmp($filter_stream, $stream) === 0 ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($stream); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!empty($teachers_map)): ?>
                    <div class="flex flex-col gap-1.5 w-full md:w-auto">
                        <label for="teacher" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Teacher / Sir</label>
                        <select id="teacher" name="teacher" class="bg-gray-50 border border-gray-100 rounded-2xl px-5 py-3 text-sm font-bold text-gray-700 min-w-[200px] focus:ring-2 focus:ring-red-500/20 focus:border-red-600 outline-none transition-all cursor-pointer" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_teacher === 'all' ? 'selected' : ''; ?>>All Teachers</option>
                            <?php foreach ($teachers_map as $t_id => $t_name): ?>
                                <option value="<?php echo htmlspecialchars($t_id); ?>" <?php echo $filter_teacher === $t_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($subjects_map)): ?>
                    <div class="flex flex-col gap-1.5 w-full md:w-auto">
                        <label for="subject" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Subject</label>
                        <select id="subject" name="subject" class="bg-gray-50 border border-gray-100 rounded-2xl px-5 py-3 text-sm font-bold text-gray-700 min-w-[200px] focus:ring-2 focus:ring-red-500/20 focus:border-red-600 outline-none transition-all cursor-pointer" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_subject === 'all' ? 'selected' : ''; ?>>All Subjects</option>
                            <?php foreach ($subjects_map as $subj_name): ?>
                                <option value="<?php echo htmlspecialchars($subj_name); ?>" <?php echo strcasecmp($filter_subject, $subj_name) === 0 ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subj_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Student Results CTA Section (Dedicated Row) -->
        <?php if (!empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'student')): ?>
            <div class="mb-12">
                <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-[2.5rem] p-1 shadow-2xl shadow-red-200">
                    <div class="bg-white rounded-[2.3rem] p-8 md:p-10 flex flex-col md:flex-row items-center justify-between gap-8">
                        <div class="flex items-center gap-6">
                            <div class="w-16 h-16 bg-red-50 rounded-2xl flex items-center justify-center text-red-600 text-3xl">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-gray-900 tracking-tight">Your Achievement</h3>
                                <p class="text-gray-500 font-bold text-xs uppercase tracking-widest mt-1">
                                    <?php if (empty($student_submission)): ?>
                                        Share your results with the Lernerr.LK community
                                    <?php elseif (empty($student_submission['results_submitted_at'])): ?>
                                        Complete your results submission
                                    <?php else: ?>
                                        Your results have been successfully submitted
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col md:flex-row items-center gap-6">
                            <?php if (!empty($student_submission) && !empty($student_submission['results_submitted_at'])): ?>
                                <div class="grid grid-cols-2 gap-8 px-8 border-x border-gray-100">
                                    <div class="text-center">
                                        <p class="text-[10px] font-black text-gray-400 uppercase">D-Rank</p>
                                        <p class="text-sm font-bold text-red-600">#<?php echo !empty($student_submission['district_rank']) ? htmlspecialchars($student_submission['district_rank']) : 'N/A'; ?></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[10px] font-black text-gray-400 uppercase">I-Rank</p>
                                        <p class="text-sm font-bold text-red-600">#<?php echo !empty($student_submission['island_rank']) ? htmlspecialchars($student_submission['island_rank']) : 'N/A'; ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($student_submission)): ?>
                                <a href="<?php echo $root_url; ?>student/al_exam_form.php" class="bg-red-600 hover:bg-red-700 text-white font-black px-8 py-5 rounded-3xl transition-all shadow-xl shadow-red-200 flex items-center gap-3 active:scale-95 group">
                                    <span>ADD MY RESULTS</span>
                                    <i class="fas fa-plus group-hover:rotate-90 transition-transform"></i>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $root_url; ?>student/al_results_form.php" class="bg-gray-900 hover:bg-black text-white font-black px-8 py-5 rounded-3xl transition-all shadow-xl flex items-center gap-3 active:scale-95">
                                    <span>EDIT MY RESULTS</span>
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($results_by_stream)): ?>
            <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-graduation-cap text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900">No results found</h3>
                <p class="text-gray-500 mt-2">Results will be displayed here once students submit and agree to publish.</p>
            </div>
        <?php else: ?>
            <?php foreach ($results_by_stream as $stream_name => $students): 
                $display_years = [];
                if ($filter_exam_year !== 'all') {
                    $display_years[] = $filter_exam_year;
                } else {
                    foreach ($students as $s) {
                        if (!empty($s['display_exam_year'])) {
                            $display_years[(string)$s['display_exam_year']] = true;
                        }
                    }
                    $display_years = array_keys($display_years);
                    rsort($display_years, SORT_NUMERIC);
                }
                $year_suffix = !empty($display_years) ? ' - ' . implode(' / ', $display_years) : '';
            ?>
                <?php
                    // Build compact year range for mobile (e.g. 2021 – 2026)
                    $year_range_short = '';
                    if (!empty($display_years)) {
                        $min_y = min($display_years);
                        $max_y = max($display_years);
                        $year_range_short = ($min_y === $max_y) ? $min_y : $min_y . ' – ' . $max_y;
                    }
                    $year_full = !empty($display_years) ? implode(' / ', $display_years) : '';
                ?>
                <div class="mb-12">
                    <!-- Stream Group Header -->
                    <div class="mb-6">
                        <!-- Top row: Stream name + year + teacher pill -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="flex items-center gap-2 min-w-0">
                                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 uppercase tracking-wider whitespace-nowrap">
                                    <?php echo htmlspecialchars($stream_name); ?>
                                    <?php if ($filter_subject !== 'all'): ?>
                                        <span class="text-blue-600 font-black"> - <?php echo htmlspecialchars($filter_subject); ?></span>
                                    <?php endif; ?>
                                </h2>
                                <?php if (!empty($display_years)): ?>
                                    <!-- Mobile: compact range -->
                                    <span class="sm:hidden text-red-600 font-extrabold text-base whitespace-nowrap">
                                        – <?php echo htmlspecialchars($year_range_short); ?>
                                    </span>
                                    <!-- Desktop: full list -->
                                    <span class="hidden sm:inline text-red-600 font-extrabold text-xl whitespace-nowrap">
                                        – <?php echo htmlspecialchars($year_full); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($filter_teacher !== 'all' && !empty($teachers_map[$filter_teacher])): ?>
                                <?php
                                    $header_t_pic = $all_teachers_lookup[$filter_teacher]['picture'] ?? '';
                                    $header_t_name = $teachers_map[$filter_teacher];
                                ?>
                                <div class="flex items-center gap-2 bg-violet-50 border border-violet-100 rounded-2xl px-3 py-1.5 flex-shrink-0">
                                    <?php if (!empty($header_t_pic)): ?>
                                        <img src="<?php echo $root_url . htmlspecialchars($header_t_pic); ?>"
                                             alt="<?php echo htmlspecialchars($header_t_name); ?>"
                                             class="w-7 h-7 rounded-full object-cover border-2 border-violet-200 flex-shrink-0"
                                             onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($header_t_name); ?>&background=ede9fe&color=7c3aed&bold=true&size=64';">
                                    <?php else: ?>
                                        <div class="w-7 h-7 rounded-full bg-violet-100 border-2 border-violet-200 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-chalkboard-teacher text-[10px] text-violet-600"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="block text-[7px] font-black uppercase tracking-widest text-violet-400 leading-none">Teacher</span>
                                        <span class="block text-xs font-black text-violet-700 leading-snug"><?php echo htmlspecialchars($header_t_name); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="h-1 flex-1 bg-gradient-to-r from-red-600 to-transparent rounded-full opacity-20 hidden sm:block min-w-[40px]"></div>
                        </div>
                    </div>

                    <!-- Students Grid (Exactly 4 columns on desktop) -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <?php foreach ($students as $student): ?>
                            <div class="bg-white rounded-3xl p-5 flex flex-col h-full shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100 group">
                                <!-- 1. Profile Picture & Student Name -->
                                <div class="flex flex-col items-center text-center">
                                    <div class="relative mb-3">
                                        <?php
                                            $display_photo = '';
                                            if (!empty($student['photo_path'])) $display_photo = $student['photo_path'];
                                            elseif (!empty($student['profile_picture'])) $display_photo = $student['profile_picture'];
                                            $user_name = trim(($student['first_name'] ?? '') . ' ' . ($student['second_name'] ?? ''));
                                            $full_student_name = !empty($user_name) ? $user_name : (!empty($student['student_name']) ? $student['student_name'] : $student['student_id']);
                                        ?>
                                        <?php if (!empty($display_photo)): ?>
                                            <img src="<?php echo $root_url . htmlspecialchars($display_photo); ?>" 
                                                 alt="<?php echo htmlspecialchars($full_student_name); ?>" 
                                                 class="w-20 h-20 rounded-full object-cover border-4 border-red-50 shadow-md group-hover:scale-105 transition-transform duration-300"
                                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($full_student_name); ?>&background=fee2e2&color=dc2626&bold=true';">
                                        <?php else: ?>
                                            <div class="w-20 h-20 rounded-full bg-red-50 border-4 border-red-50 flex items-center justify-center shadow-md group-hover:scale-105 transition-transform duration-300">
                                                <i class="fas fa-user-graduate text-3xl text-red-600"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-sm font-extrabold text-gray-900 line-clamp-1 leading-snug" title="<?php echo htmlspecialchars($full_student_name); ?>">
                                        <?php echo htmlspecialchars($full_student_name); ?>
                                    </h3>
                                </div>

                                <!-- 2. Stream & Exam Year -->
                                <div class="mt-3 flex items-center justify-center gap-1.5 flex-wrap">
                                    <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 text-[10px] font-extrabold px-3 py-1 rounded-full border border-red-100">
                                        <i class="fas fa-graduation-cap text-[9px]"></i>
                                        <?php echo htmlspecialchars($student['stream_label']); ?>
                                    </span>
                                    <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 text-[10px] font-extrabold px-3 py-1 rounded-full border border-gray-200">
                                        <i class="fas fa-calendar-alt text-[9px]"></i>
                                        <?php echo !empty($student['display_exam_year']) ? htmlspecialchars($student['display_exam_year']) . ' A/L' : 'N/A'; ?>
                                    </span>
                                </div>

                                <!-- 3. Results -->
                                <div class="mt-4 bg-gray-50/80 rounded-2xl p-3.5 border border-gray-100 space-y-2 flex-grow">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-gray-700 text-xs font-bold truncate" title="<?php echo htmlspecialchars($student['subject_1']); ?>">
                                            <?php echo htmlspecialchars($student['subject_1']); ?>
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg bg-white border border-gray-200 text-red-600 font-black text-xs shadow-2xs">
                                            <?php echo htmlspecialchars($student['result_1']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-gray-700 text-xs font-bold truncate" title="<?php echo htmlspecialchars($student['subject_2']); ?>">
                                            <?php echo htmlspecialchars($student['subject_2']); ?>
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg bg-white border border-gray-200 text-red-600 font-black text-xs shadow-2xs">
                                            <?php echo htmlspecialchars($student['result_2']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-gray-700 text-xs font-bold truncate" title="<?php echo htmlspecialchars($student['subject_3']); ?>">
                                            <?php echo htmlspecialchars($student['subject_3']); ?>
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-lg bg-white border border-gray-200 text-red-600 font-black text-xs shadow-2xs">
                                            <?php echo htmlspecialchars($student['result_3']); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- 4. Ranks & Z-Score -->
                                <div class="mt-4 pt-3 border-t border-gray-100 grid grid-cols-3 gap-1.5 text-center text-[10px]">
                                    <div class="bg-gray-50 rounded-xl p-2 border border-gray-100">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">District Rank</span>
                                        <span class="font-extrabold text-red-600"><?php echo !empty($student['district_rank']) ? '#' . htmlspecialchars($student['district_rank']) : 'N/A'; ?></span>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl p-2 border border-gray-100">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">Island Rank</span>
                                        <span class="font-extrabold text-red-600"><?php echo !empty($student['island_rank']) ? '#' . htmlspecialchars($student['island_rank']) : 'N/A'; ?></span>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl p-2 border border-gray-100">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">Z-Score</span>
                                        <span class="font-bold text-gray-800"><?php echo isset($student['z_score']) && $student['z_score'] !== null ? number_format((float)$student['z_score'], 4) : 'N/A'; ?></span>
                                    </div>
                                </div>

                                <!-- 5. Teacher Strip -->
                                <?php if (!empty($student['assigned_teachers'])): ?>
                                <div class="mt-4 pt-3 border-t border-violet-50 space-y-2">
                                    <?php foreach ($student['assigned_teachers'] as $t_info): ?>
                                    <div class="flex items-center gap-2.5">
                                        <?php
                                            $t_pic = $t_info['picture'] ?? '';
                                            $t_name = $t_info['name'];
                                        ?>
                                        <?php if (!empty($t_pic)): ?>
                                            <img src="<?php echo $root_url . htmlspecialchars($t_pic); ?>"
                                                 alt="<?php echo htmlspecialchars($t_name); ?>"
                                                 class="w-7 h-7 rounded-full object-cover border-2 border-violet-200 flex-shrink-0"
                                                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($t_name); ?>&background=ede9fe&color=7c3aed&bold=true&size=64';">
                                        <?php else: ?>
                                            <div class="w-7 h-7 rounded-full bg-violet-100 border-2 border-violet-200 flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-chalkboard-teacher text-[10px] text-violet-600"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="min-w-0 flex-1">
                                            <span class="block text-[7px] font-black uppercase tracking-widest text-violet-400 leading-none">Teacher</span>
                                            <span class="block text-[11px] font-black text-violet-700 truncate leading-snug mt-0.5"><?php echo htmlspecialchars($t_name); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Celebration Effect (Subtle) -->
    <div class="fixed top-0 left-0 w-full h-full pointer-events-none z-0 opacity-10">
        <div class="absolute top-10 left-10 text-red-600 animate-bounce">
            <i class="fas fa-star text-2xl"></i>
        </div>
        <div class="absolute top-40 right-20 text-red-600 animate-pulse">
            <i class="fas fa-certificate text-3xl"></i>
        </div>
        <div class="absolute bottom-20 left-1/4 text-red-600 animate-bounce" style="animation-delay: 1s">
            <i class="fas fa-award text-4xl"></i>
        </div>
    </div>

</body>
</html>
