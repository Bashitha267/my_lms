<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$is_logged_in = !empty($user_id);

// Redefine dashboard content locally to avoid circular redirects or complexity
// We'll basically render the same view as dashboard.php but from the root
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LearnerX - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar only for guests, dashboard.php handles its own -->
    <?php if (!$is_logged_in): ?>
        <?php include 'dashboard/navbar.php'; ?>
    <?php endif; ?>

    <main>
        <!-- Reusing Dashboard Content Logic here -->
        <?php 
        // We can either require dashboard.php's core logic or just show a beautiful landing page
        // Since the user said "display the relevant dashboard", let's include dashboard.php 
        // but we need to handle paths correctly.
        
        // Actually, including dashboard.php directly might cause issues with paths there too.
        // Let's just make index.php show a high-quality landing section if not logged in
        // and if logged in, show the dashboard.
        
        if ($is_logged_in) {
            // Include dashboard content logic
            include 'dashboard/dashboard.php';
        } else {
            // Show a stunning landing page for guests
            ?>
            <div class="relative min-h-[80vh] flex items-center justify-center overflow-hidden">
                <!-- Background Decoration -->
                <div class="absolute top-0 left-0 w-full h-full -z-10">
                    <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-red-100 rounded-full blur-[100px] opacity-50"></div>
                    <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-100 rounded-full blur-[100px] opacity-50"></div>
                </div>

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 class="text-5xl md:text-7xl font-black text-gray-900 mb-6 tracking-tight">
                        Empower Your Future with <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-red-800">LearnerX</span>
                    </h1>
                    <p class="text-xl text-gray-600 mb-10 max-w-3xl mx-auto leading-relaxed">
                        Join Sri Lanka's leading LMS platform. Access live classes, recordings, and premium publications all in one place.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="register.php" class="px-8 py-4 bg-red-600 text-white rounded-2xl font-bold text-lg hover:bg-red-700 transition-all shadow-xl shadow-red-200 transform hover:-translate-y-1">
                            Get Started Now
                        </a>
                        <a href="dashboard/publications.php" class="px-8 py-4 bg-white text-gray-900 rounded-2xl font-bold text-lg hover:bg-gray-50 transition-all border border-gray-200 transform hover:-translate-y-1">
                            Explore Publications
                        </a>
                    </div>
                </div>
            </div>

            <!-- Additional Sections (Live Classes Preview, etc.) can go here -->
            <?php
        }
        ?>
    </main>

    <footer class="bg-gray-900 text-white py-12 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
                <div class="col-span-2">
                    <h2 class="text-2xl font-bold mb-6">LearnerX</h2>
                    <p class="text-gray-400 max-w-sm">The ultimate platform for modern education. Quality content delivered by top experts.</p>
                </div>
                <div>
                    <h3 class="text-lg font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="index.php" class="hover:text-white transition">Home</a></li>
                        <li><a href="dashboard/publications.php" class="hover:text-white transition">Publications</a></li>
                        <li><a href="dashboard/about_us.php" class="hover:text-white transition">About Us</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-bold mb-4">Contact</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li>info@learnerx.lk</li>
                        <li>+94 77 123 4567</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-12 pt-8 text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> LearnerX LMS. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>


