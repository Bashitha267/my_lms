<?php
session_start();
require_once __DIR__ . '/../config.php';

// Fetch all published results
$query = "SELECT * FROM al_exam_submissions WHERE agreed_to_publish = 1 ORDER BY stream ASC, created_at DESC";
$result = $conn->query($query);

$results_by_stream = [];
$streams = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stream = $row['stream'] ?: 'Other';
        $results_by_stream[$stream][] = $row;
        if (!in_array($stream, $streams)) {
            $streams[] = $stream;
        }
    }
}

// Get filter from URL
$filter_stream = isset($_GET['stream']) ? $_GET['stream'] : 'all';

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
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Very light grey background */
        }
        h1, h2, h3, .font-outfit {
            font-family: 'Outfit', sans-serif;
        }
        .crimson-banner {
            background-color: #dc2626; /* Crimson Red */
        }
        .student-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.1), 0 8px 10px -6px rgba(220, 38, 38, 0.1);
        }
        .result-typography {
            color: #dc2626;
            font-size: 1.5rem;
            font-weight: 800;
        }
        .filter-btn-active {
            background-color: #dc2626;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                    A/L Results <span class="text-red-600">Portal</span>
                </h1>
                <p class="text-gray-600 mt-2 font-medium italic">අපගේ පසුගිය උසස් පෙළ ප්‍රතිඵල</p>
            </div>
            
            <!-- Stream Filter -->
            <div class="flex flex-wrap gap-2">
                <a href="?stream=all" 
                   class="px-4 py-2 rounded-full text-sm font-bold border-2 transition-all <?php echo $filter_stream === 'all' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-700 border-gray-200 hover:border-red-600 hover:text-red-600'; ?>">
                    සියලුම අංශ
                </a>
                <?php foreach ($streams as $stream): ?>
                    <a href="?stream=<?php echo urlencode($stream); ?>" 
                       class="px-4 py-2 rounded-full text-sm font-bold border-2 transition-all <?php echo $filter_stream === $stream ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-700 border-gray-200 hover:border-red-600 hover:text-red-600'; ?>">
                        <?php echo htmlspecialchars($stream); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($results_by_stream)): ?>
            <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-graduation-cap text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900">No results found</h3>
                <p class="text-gray-500 mt-2">Results will be displayed here once students submit and agree to publish.</p>
            </div>
        <?php else: ?>
            <?php 
            foreach ($results_by_stream as $stream_name => $students): 
                if ($filter_stream !== 'all' && $filter_stream !== $stream_name) continue;
            ?>
                <div class="mb-12">
                    <!-- Stream Group Header -->
                    <div class="flex items-center gap-4 mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 uppercase tracking-wider"><?php echo htmlspecialchars($stream_name); ?></h2>
                        <div class="h-1 flex-1 bg-gradient-to-r from-red-600 to-transparent rounded-full opacity-20"></div>
                    </div>

                    <!-- Students Grid (Exactly 4 columns on desktop) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php foreach ($students as $student): ?>
                            <div class="student-card bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl flex flex-col h-full border border-gray-100 transition-all duration-300">
                                <!-- Card Header -->
                                <div class="crimson-banner py-2.5 px-4 shadow-inner">
                                    <p class="text-white text-center font-extrabold text-[10px] tracking-widest uppercase">
                                        <?php echo htmlspecialchars($student['stream']); ?>
                                    </p>
                                </div>

                                <!-- Student Image -->
                                <div class="relative pt-10 pb-6 flex justify-center">
                                    <div class="relative">
                                        <?php if (!empty($student['photo_path'])): ?>
                                            <div class="p-1.5 bg-white rounded-full shadow-lg border-2 border-red-500/10">
                                                <img src="<?php echo $root_url . htmlspecialchars($student['photo_path']); ?>" 
                                                     alt="Student" 
                                                     class="w-32 h-32 rounded-full object-cover border-[6px] border-red-600 shadow-xl"
                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\"w-32 h-32 rounded-full bg-gray-50 border-[6px] border-red-600 shadow-xl flex items-center justify-center\"><i class=\"fas fa-user-graduate text-5xl text-red-600\"></i></div>';">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-32 h-32 rounded-full bg-gray-50 border-[6px] border-red-600 shadow-xl flex items-center justify-center">
                                                <i class="fas fa-user-graduate text-5xl text-red-600"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute -bottom-2 -right-2 bg-green-500 text-white w-10 h-10 rounded-full flex items-center justify-center shadow-lg border-4 border-white">
                                            <i class="fas fa-award text-sm"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div class="px-8 py-6 flex-grow">
                                    <div class="space-y-6">
                                        <!-- Subject 1 -->
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="text-gray-700 text-xs font-bold uppercase tracking-tight truncate leading-tight flex-1" title="<?php echo htmlspecialchars($student['subject_1']); ?>">
                                                <?php echo htmlspecialchars($student['subject_1']); ?>
                                            </span>
                                            <span class="result-typography leading-none">
                                                <?php echo htmlspecialchars($student['result_1']); ?>
                                            </span>
                                        </div>
                                        <!-- Subject 2 -->
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="text-gray-700 text-xs font-bold uppercase tracking-tight truncate leading-tight flex-1" title="<?php echo htmlspecialchars($student['subject_2']); ?>">
                                                <?php echo htmlspecialchars($student['subject_2']); ?>
                                            </span>
                                            <span class="result-typography leading-none">
                                                <?php echo htmlspecialchars($student['result_2']); ?>
                                            </span>
                                        </div>
                                        <!-- Subject 3 -->
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="text-gray-700 text-xs font-bold uppercase tracking-tight truncate leading-tight flex-1" title="<?php echo htmlspecialchars($student['subject_3']); ?>">
                                                <?php echo htmlspecialchars($student['subject_3']); ?>
                                            </span>
                                            <span class="result-typography leading-none">
                                                <?php echo htmlspecialchars($student['result_3']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Footer -->
                                <div class="bg-gray-50 px-8 py-4 border-t border-gray-50">
                                    <div class="flex justify-between items-center text-[9px] text-gray-400 font-extrabold uppercase tracking-widest">
                                        <div class="flex items-center gap-1.5 grayscale brightness-110">
                                            <i class="fas fa-id-card-alt opacity-70"></i>
                                            <span>#<?php echo htmlspecialchars($student['index_number']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-1.5 grayscale brightness-110">
                                            <i class="fas fa-map-marked-alt opacity-70"></i>
                                            <span><?php echo htmlspecialchars($student['district']); ?></span>
                                        </div>
                                    </div>
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
