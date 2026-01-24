<?php
require_once '../config.php';

// MOCK DATA - This is a simulation. In a real application, you would query the database based on the GET parameters.
$all_results = [
    // Students for teacher tea_0001 (Kamal Fernando)
    ['name' => 'Ruwan Perera', 'index_number' => '123456', 'stream' => 'A/L Science', 'year' => '2024', 'rank' => 'District 1st, Island 32nd', 'teacher_id' => 'tea_0001', 'profile_picture' => 'https://xsgames.co/randomusers/assets/avatars/male/1.jpg'],
    ['name' => 'Priya Dissanayake', 'index_number' => '445566', 'stream' => 'A/L Science', 'year' => '2024', 'rank' => 'District 9th, Island 180th', 'teacher_id' => 'tea_0001', 'profile_picture' => 'https://xsgames.co/randomusers/assets/avatars/female/5.jpg'],
    ['name' => 'Geetha Kumarasinghe', 'index_number' => '345678', 'stream' => 'A/L Science', 'year' => '2023', 'rank' => 'District 3rd, Island 75th', 'teacher_id' => 'tea_0001', 'profile_picture' => 'https://xsgames.co/randomusers/assets/avatars/female/3.jpg'],

    // Students for teacher tea_0002
    ['name' => 'Nimali Silva', 'index_number' => '654321', 'stream' => 'A/L Commerce', 'year' => '2024', 'rank' => 'District 5th, Island 150th', 'teacher_id' => 'tea_0002', 'profile_picture' => 'https://xsgames.co/randomusers/assets/avatars/female/1.jpg'],
    ['name' => 'Mala Gamage', 'index_number' => '987654', 'stream' => 'A/L Commerce', 'year' => '2023', 'rank' => 'District 8th, Island 300th', 'teacher_id' => 'tea_0002', 'profile_picture' => 'https://xsgames.co/randomusers/assets/avatars/female/4.jpg'],

    // Students for teacher tea_0003
    ['name' => 'Kamal Jayasuriya', 'index_number' => '789012', 'stream' => 'A/L Arts', 'year' => '2023', 'rank' => 'District 2nd, Island 50th', 'teacher_id' => 'tea_0003', 'profile_picture' => 'https://xsgames.co/randomusers/assets/avatars/male/2.jpg'],
    ['name' => 'Jagath Withanage', 'index_number' => '112233', 'stream' => 'A/L Arts', 'year' => '2024', 'rank' => 'District 4th, Island 90th', 'teacher_id' => 'tea_0003', 'profile_picture' => 'https://xsgames.co/randomusers/assets/avatars/male/3.jpg'],

    // Unassigned or other teachers
    ['name' => 'Sunitha Fernando', 'index_number' => '210987', 'stream' => 'Technology', 'year' => '2024', 'rank' => 'District 10th, Island 200th', 'teacher_id' => 'tea_0004', 'profile_picture' => 'https://xsgames.co/randomusers/assets/avatars/female/2.jpg'],
];

$results = $all_results;
$filter_message = '';

// Check for URL parameters and filter results
if (isset($_GET['teacher_id']) && isset($_GET['stream_name'])) {
    $teacher_id = $_GET['teacher_id'];
    $stream_name = $_GET['stream_name'];
    $teacher_name = isset($_GET['teacher_name']) ? $_GET['teacher_name'] : 'the selected teacher';

    $results = array_filter($all_results, function ($result) use ($teacher_id, $stream_name) {
        return $result['teacher_id'] === $teacher_id && $result['stream'] === $stream_name;
    });

    $filter_message = "Showing results for <strong>" . htmlspecialchars($stream_name) . "</strong> students taught by <strong>" . htmlspecialchars($teacher_name) . "</strong>.";
}


$streams = array_unique(array_column($results, 'stream'));
$years = array_unique(array_column($results, 'year'));
sort($years, SORT_NUMERIC);
sort($streams);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - LMS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        heading: ['"Outfit"', 'sans-serif'],
                    },
                    colors: {
                        brand: { 50:"#fef2f2", 100:"#fee2e2", 200:"#fecaca", 300:"#fca5a5", 400:"#f87171", 500:"#ef4444", 600:"#dc2626", 700:"#b91c1c", 800:"#991b1b", 900:"#7f1d1d", 950:"#450a0a" }
                    },
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; }
        .result-card {
            transition: all 0.3s ease;
            transform-style: preserve-3d;
        }
        .result-card:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .profile-img-container {
            transform: translateZ(20px);
        }
    </style>
</head>
<body class="bg-slate-50 antialiased text-slate-800">

    <?php include 'navbar.php'; ?>

    <section class="relative pt-32 pb-20 overflow-hidden bg-brand-900">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 30px 30px;"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-brand-900/90"></div>
        <div class="container relative z-10 px-4 mx-auto text-center">
            <span class="inline-block px-3 py-1 mb-4 text-xs font-bold tracking-wider text-brand-200 uppercase bg-brand-800 rounded-full bg-opacity-50 border border-brand-700">
                Student Achievements
            </span>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Exam Results</h1>
            <p class="max-w-2xl mx-auto text-lg text-brand-100">
                Check the latest examination results and student performance reports.
            </p>
        </div>
    </section>

    <section class="py-20 px-4">
        <div class="container mx-auto max-w-screen-xl">

            <?php if ($filter_message): ?>
            <div class="mb-8 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md" role="alert">
                <div class="flex">
                    <div class="py-1"><i class="fas fa-info-circle fa-lg me-3"></i></div>
                    <div>
                        <p class="font-bold">Results are filtered</p>
                        <p class="text-sm"><?php echo $filter_message; ?> <a href="examresults.php" class="font-bold underline hover:text-yellow-800">Clear filter</a>.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-12 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="stream-filter" class="block text-sm font-medium text-slate-700 mb-2">Filter by Stream</label>
                        <select id="stream-filter" class="w-full p-3 rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500">
                            <option value="">All Streams</option>
                            <?php foreach ($streams as $stream): ?>
                                <option value="<?php echo htmlspecialchars($stream); ?>"><?php echo htmlspecialchars($stream); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="year-filter" class="block text-sm font-medium text-slate-700 mb-2">Filter by Year</label>
                        <select id="year-filter" class="w-full p-3 rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500">
                            <option value="">All Years</option>
                             <?php foreach ($years as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button id="reset-filters" class="w-full p-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition-colors font-bold">Reset Filters</button>
                    </div>
                </div>
            </div>

            <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-8 gap-y-16">
                <?php if (empty($results)): ?>
                    <div class="col-span-full bg-white rounded-2xl border border-slate-200 p-12 text-center">
                        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-400">
                            <i class="fas fa-search text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">No Results Found</h3>
                        <p class="text-slate-500 mt-2">No results match the current filter criteria.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($results as $result): ?>
                        <div class="result-card bg-gradient-to-br from-red-600 to-red-800 text-white rounded-2xl shadow-lg shadow-red-500/20 flex flex-col border-2 border-white/30" data-stream="<?php echo htmlspecialchars($result['stream']); ?>" data-year="<?php echo htmlspecialchars($result['year']); ?>">
                            <div class="p-6 text-center flex-grow">
                                <div class="profile-img-container w-24 h-24 rounded-full mx-auto -mt-16 border-4 border-white bg-white overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($result['profile_picture']); ?>" alt="Profile Picture" class="w-full h-full object-cover">
                                </div>
                                <h3 class="text-lg font-bold mt-4 mb-1"><?php echo htmlspecialchars($result['name']); ?></h3>
                                <p class="text-xs text-red-200 font-mono">#<?php echo htmlspecialchars($result['index_number']); ?></p>
                                
                                <div class="mt-4 text-left text-sm space-y-2">
                                    <p><i class="fas fa-book-open fa-fw me-2 opacity-70"></i><span class="font-semibold">Stream:</span> <?php echo htmlspecialchars($result['stream']); ?></p>
                                    <p><i class="fas fa-calendar-check fa-fw me-2 opacity-70"></i><span class="font-semibold">Year:</span> <?php echo htmlspecialchars($result['year']); ?></p>
                                </div>
                            </div>
                            <div class="bg-gradient-to-br from-gray-200 to-gray-400 rounded-b-2xl mt-auto py-3 px-4 text-center">
                                <p class="font-bold text-sm text-gray-800">
                                    <i class="fas fa-trophy me-2 text-amber-500"></i><?php echo htmlspecialchars($result['rank']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div id="no-results-message" class="hidden col-span-full bg-white rounded-2xl border border-slate-200 p-12 text-center">
                    <h3 class="text-xl font-bold text-slate-800">No Matching Results</h3>
                    <p class="text-slate-600 mt-2">Try adjusting your filters to find what you're looking for.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-slate-900 text-slate-300 py-12 border-t border-slate-700">
        <div class="container px-4 mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                <div class="col-span-1 md:col-span-2">
                    <a href="index.php" class="inline-flex items-center text-white text-2xl font-bold mb-4">
                        <i class="fas fa-graduation-cap me-2 text-brand-400"></i> LMS
                    </a>
                    <p class="text-slate-400 mb-6 max-w-sm">
                        An advanced Learning Management System designed to bridge the gap between students and education.
                    </p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="hover:text-brand-400 transition-colors">Home</a></li>
                        <li><a href="staff.php" class="hover:text-brand-400 transition-colors">Staff</a></li>
                        <li><a href="subjects.php" class="hover:text-brand-400 transition-colors">Subjects</a></li>
                        <li><a href="../login.php" class="hover:text-brand-400 transition-colors">Login</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4">Contact</h4>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1.5 me-3 text-brand-500"></i>
                            <span>123 Education Street</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-slate-700 text-center text-sm text-slate-500">
                <p>&copy; <?php echo date('Y'); ?> LMS Learning Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const streamFilter = document.getElementById('stream-filter');
        const yearFilter = document.getElementById('year-filter');
        const resultCards = document.querySelectorAll('.result-card');
        const noResultsMessage = document.getElementById('no-results-message');
        const resetFiltersBtn = document.getElementById('reset-filters');

        function filterResults() {
            const selectedStream = streamFilter.value;
            const selectedYear = yearFilter.value;
            let resultsFound = false;

            resultCards.forEach(card => {
                const cardStream = card.dataset.stream;
                const cardYear = card.dataset.year;

                const streamMatch = selectedStream === '' || cardStream === selectedStream;
                const yearMatch = selectedYear === '' || cardYear === selectedYear;

                if (streamMatch && yearMatch) {
                    card.style.display = 'flex'; 
                    resultsFound = true;
                } else {
                    card.style.display = 'none';
                }
            });
            
            noResultsMessage.style.display = resultsFound ? 'none' : 'block';
        }

        function resetFilters() {
            streamFilter.value = '';
            yearFilter.value = '';
            filterResults();
        }

        streamFilter.addEventListener('change', filterResults);
        yearFilter.addEventListener('change', filterResults);
        resetFiltersBtn.addEventListener('click', resetFilters);
    });
    </script>
</body>
</html>