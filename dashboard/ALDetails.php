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
$exam_year_select = $has_exam_year ? 'als.exam_year AS display_exam_year' : 'YEAR(als.created_at) AS display_exam_year';

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

// Fetch all published results (only when results have been submitted and grades are present)
$query = "SELECT als.*, {$district_rank_select}, {$island_rank_select}, {$exam_year_select}, u.first_name, u.second_name, u.profile_picture
                    FROM al_exam_submissions als
                    INNER JOIN users u ON u.user_id = als.student_id
                    WHERE als.agreed_to_publish = 1
                        AND als.results_submitted_at IS NOT NULL
                        AND COALESCE(als.result_1, '') <> ''
                        AND COALESCE(als.result_2, '') <> ''
                        AND COALESCE(als.result_3, '') <> ''
                    ORDER BY display_exam_year DESC, als.stream ASC, als.created_at DESC";
$result = $conn->query($query);

$results_by_stream = [];
$all_results = [];
$streams = [];
$exam_years = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stream = trim((string)($row['stream'] ?? ''));
        $stream = $stream !== '' ? $stream : 'Not Specified';
        $row['stream_label'] = $stream;

        $year_value = !empty($row['display_exam_year']) ? (int)$row['display_exam_year'] : null;
        $row['display_exam_year'] = $year_value;

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

$default_exam_year = 'all';

// Get filters from URL
$filter_stream = isset($_GET['stream']) ? trim($_GET['stream']) : 'all';
$filter_exam_year = isset($_GET['exam_year']) ? trim($_GET['exam_year']) : $default_exam_year;

// Apply filters and group by stream
foreach ($all_results as $row) {
    if ($filter_stream !== 'all' && $row['stream_label'] !== $filter_stream) {
        continue;
    }
    if ($filter_exam_year !== 'all' && (string)($row['display_exam_year'] ?? '') !== $filter_exam_year) {
        continue;
    }
    $results_by_stream[$row['stream_label']][] = $row;
}

ksort($results_by_stream, SORT_NATURAL | SORT_FLAG_CASE);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A/L Results Portal | Lernerr.LK</title>
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

    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-10">
        <!-- Header & Filters Section -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between mb-10 gap-8 border-b border-gray-200 pb-10">
            <div>
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">
                    A/L Results <span class="text-red-600">Portal</span>
                </h1>
                <p class="text-gray-600 mt-3 text-lg font-medium italic">අපගේ පසුගිය උසස් පෙළ ප්‍රතිඵල</p>
            </div>

            <!-- Filters -->
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col md:flex-row items-center gap-6">
                <form method="GET" class="flex flex-col md:flex-row items-center gap-6 w-full">
                    <div class="flex flex-col gap-1.5 w-full md:w-auto">
                        <label for="exam_year" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Exam Year</label>
                        <select id="exam_year" name="exam_year" class="bg-gray-50 border border-gray-100 rounded-2xl px-5 py-3 text-sm font-bold text-gray-700 min-w-[160px] focus:ring-2 focus:ring-red-500/20 focus:border-red-600 outline-none transition-all" onchange="this.form.submit()">
                            <?php if (empty($exam_years)): ?>
                                <option value="all" selected>All Years</option>
                            <?php else: ?>
                                <option value="all" <?php echo $filter_exam_year === 'all' ? 'selected' : ''; ?>>All Years</option>
                                <?php foreach ($exam_years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $filter_exam_year === $year ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5 w-full md:w-auto">
                        <label for="stream" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Study Stream</label>
                        <select id="stream" name="stream" class="bg-gray-50 border border-gray-100 rounded-2xl px-5 py-3 text-sm font-bold text-gray-700 min-w-[240px] focus:ring-2 focus:ring-red-500/20 focus:border-red-600 outline-none transition-all" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_stream === 'all' ? 'selected' : ''; ?>>All Streams</option>
                            <?php foreach ($streams as $stream): ?>
                                <option value="<?php echo htmlspecialchars($stream); ?>" <?php echo $filter_stream === $stream ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($stream); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                                        Share your results with the LearnerX community
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
                                <div class="grid grid-cols-3 gap-8 px-8 border-x border-gray-100">
                                    <div class="text-center">
                                        <p class="text-[10px] font-black text-gray-400 uppercase">District</p>
                                        <p class="text-sm font-bold text-gray-900"><?php echo !empty($student_submission['district']) ? htmlspecialchars($student_submission['district']) : 'N/A'; ?></p>
                                    </div>
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
            <?php foreach ($results_by_stream as $stream_name => $students): ?>
                <div class="mb-12">
                    <!-- Stream Group Header -->
                    <div class="flex items-center gap-4 mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 uppercase tracking-wider"><?php echo htmlspecialchars($stream_name); ?></h2>
                        <div class="h-1 flex-1 bg-gradient-to-r from-red-600 to-transparent rounded-full opacity-20"></div>
                    </div>

                    <!-- Students Grid (Exactly 4 columns on desktop) -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <?php foreach ($students as $student): ?>
                            <div class="bg-white rounded-lg p-4 flex flex-col h-full">
                                <div class="flex items-start gap-3">
                                    <div class="shrink-0">
                                        <?php
                                            $display_photo = '';
                                            if (!empty($student['photo_path'])) $display_photo = $student['photo_path'];
                                            elseif (!empty($student['profile_picture'])) $display_photo = $student['profile_picture'];
                                        ?>
                                        <?php if (!empty($display_photo)): ?>
                                            <img src="<?php echo $root_url . htmlspecialchars($display_photo); ?>" 
                                                 alt="Student" 
                                                 class="w-16 h-16 rounded-full object-cover"
                                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode(trim(($student['first_name'] ?? '') . ' ' . ($student['second_name'] ?? '')) ?: $student['student_id']); ?>&background=e5e7eb&color=374151';">
                                        <?php else: ?>
                                            <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center">
                                                <i class="fas fa-user-graduate text-xl text-gray-600"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-gray-900 truncate">
                                            <?php echo htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['second_name'] ?? '')) ?: $student['student_id']); ?>
                                        </div>
                                        <div class="text-[11px] text-gray-500 mt-0.5">
                                            Exam Admission No: <?php echo !empty($student['index_number']) ? htmlspecialchars($student['index_number']) : 'N/A'; ?>
                                        </div>
                                        <div class="text-[11px] text-gray-500 mt-0.5">
                                            Stream: <?php echo htmlspecialchars($student['stream_label']); ?>
                                        </div>
                                        <div class="text-[11px] text-gray-500 mt-0.5">
                                            Exam Year: <?php echo !empty($student['display_exam_year']) ? htmlspecialchars($student['display_exam_year']) : 'N/A'; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3 flex-grow">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-gray-700 text-xs font-semibold truncate" title="<?php echo htmlspecialchars($student['subject_1']); ?>">
                                                <?php echo htmlspecialchars($student['subject_1']); ?>
                                            </span>
                                            <span class="result-typography leading-none">
                                                <?php echo htmlspecialchars($student['result_1']); ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-gray-700 text-xs font-semibold truncate" title="<?php echo htmlspecialchars($student['subject_2']); ?>">
                                                <?php echo htmlspecialchars($student['subject_2']); ?>
                                            </span>
                                            <span class="result-typography leading-none">
                                                <?php echo htmlspecialchars($student['result_2']); ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-gray-700 text-xs font-semibold truncate" title="<?php echo htmlspecialchars($student['subject_3']); ?>">
                                                <?php echo htmlspecialchars($student['subject_3']); ?>
                                            </span>
                                            <span class="result-typography leading-none">
                                                <?php echo htmlspecialchars($student['result_3']); ?>
                                            </span>
                                        </div>
                                </div>

                                <div class="mt-4 text-[11px] text-gray-600 space-y-1">
                                    <div>District: <span class="font-semibold text-gray-800"><?php echo !empty($student['district']) ? htmlspecialchars($student['district']) : 'N/A'; ?></span></div>
                                    <div>District Rank: <span class="font-semibold text-gray-800"><?php echo !empty($student['district_rank']) ? htmlspecialchars($student['district_rank']) : 'N/A'; ?></span></div>
                                    <div>Island Rank: <span class="font-semibold text-gray-800"><?php echo !empty($student['island_rank']) ? htmlspecialchars($student['island_rank']) : 'N/A'; ?></span></div>
                                </div>
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
