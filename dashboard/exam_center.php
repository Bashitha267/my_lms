<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');

// Get dashboard background image from system settings
$dashboard_background = null;
$bg_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'dashboard_background' LIMIT 1");
if ($bg_stmt) {
    $bg_stmt->execute();
    $bg_result = $bg_stmt->get_result();
    if ($bg_result->num_rows > 0) {
        $bg_row = $bg_result->fetch_assoc();
        $dashboard_background = $bg_row['setting_value'];
    }
    $bg_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Center - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            <?php if ($dashboard_background): ?>
            background-image: url('../<?php echo htmlspecialchars($dashboard_background); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            <?php endif; ?>
        }
        
        .content-overlay {
            <?php if ($dashboard_background): ?>
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            <?php endif; ?>
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="content-overlay">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="glass-card rounded-2xl p-8 mb-8 text-center sm:text-left flex flex-col sm:flex-row items-center justify-between">
                <div>
                    <h1 class="text-4xl font-extrabold text-gray-900 mb-2">Exam Center</h1>
                    <p class="text-gray-600 text-lg">Manage your assessments, view results, and prepare for success.</p>
                </div>
                <div class="mt-6 sm:mt-0">
                    <div class="inline-flex items-center px-4 py-2 bg-red-100 text-red-700 rounded-full font-bold">
                        <i class="fas fa-graduation-cap mr-2"></i> Academic Year <?php echo $current_year; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content Area (Left/Center) -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Upcoming Exams -->
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="bg-red-600 px-6 py-4 flex items-center justify-between">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i class="fas fa-calendar-alt mr-3"></i> Upcoming Exams
                            </h2>
                            <span class="bg-white/20 text-white text-xs font-bold px-2 py-1 rounded">0 Scheduled</span>
                        </div>
                        <div class="p-12 text-center">
                            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-6">
                                <i class="fas fa-clipboard-list text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">No Scheduled Exams</h3>
                            <p class="text-gray-500 max-w-sm mx-auto">There are no upcoming exams scheduled at the moment. Keep checking your dashboard for updates from your teachers.</p>
                        </div>
                    </div>

                    <!-- Past Results Summary -->
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="bg-gray-900 px-6 py-4">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i class="fas fa-chart-line mr-3"></i> Recent Performance
                            </h2>
                        </div>
                        <div class="p-12 text-center">
                             <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-6">
                                <i class="fas fa-file-invoice text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">No Results Found</h3>
                            <p class="text-gray-500 max-w-sm mx-auto">Complete your first exam to see your performance metrics and progress tracking here.</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar (Right) -->
                <div class="space-y-8">
                    <!-- Statistics Widget -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-6 border-b pb-4">Exam Stats</h3>
                        <div class="space-y-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mr-4">
                                        <i class="fas fa-check-double"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Completed</p>
                                        <p class="text-lg font-bold text-gray-900">0</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg bg-yellow-100 text-yellow-600 flex items-center justify-center mr-4">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Pending</p>
                                        <p class="text-lg font-bold text-gray-900">0</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center mr-4">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Average Score</p>
                                        <p class="text-lg font-bold text-gray-900">N/A</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exam Guidelines -->
                    <div class="bg-gradient-to-br from-red-600 to-red-700 rounded-2xl p-6 text-white">
                        <h3 class="text-lg font-bold mb-4 flex items-center text-red-100">
                            <i class="fas fa-info-circle mr-2 "></i> Exam Instructions
                        </h3>
                        <ul class="text-sm space-y-3 opacity-90">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mt-1 mr-2"></i>
                                <span>Always check your internet connection before starting.</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mt-1 mr-2"></i>
                                <span>Do not refresh the page during a live exam.</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mt-1 mr-2"></i>
                                <span>Results are usually processed within 24-48 hours.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
