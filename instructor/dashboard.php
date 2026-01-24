<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Courses - Coming Soon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#3B82F6',
                        'light-blue': '#DBEAFE',
                        'dark-blue': '#2563EB',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.4); }
            50% { box-shadow: 0 0 40px rgba(59, 130, 246, 0.8); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        .pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite;
        }

        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }

        .fade-in {
            animation: fadeIn 1s ease-out;
        }

        .slide-in-left {
            animation: slideInLeft 0.8s ease-out;
        }

        .slide-in-right {
            animation: slideInRight 0.8s ease-out;
        }

        /* Background gradient animation */
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .animated-gradient {
            background: linear-gradient(-45deg, #3B82F6, #2563EB, #1D4ED8, #3B82F6);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }

        /* Feature card hover effect */
        .feature-card {
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../dashboard/navbar.php'; ?>

    <!-- Coming Soon Section -->
    <section class="min-h-screen flex items-center justify-center animated-gradient py-20 px-4">
        <div class="container mx-auto">
            <div class="text-center">
                <!-- Animated Icon -->
                <div class="mb-8 fade-in">
                    <div class="inline-block p-8 bg-white/10 backdrop-blur-md rounded-full pulse-glow">
                        <i class="fas fa-rocket text-white text-7xl float-animation"></i>
                    </div>
                </div>

                <!-- Main Heading -->
                <h1 class="text-6xl md:text-8xl font-bold text-white mb-6 fade-in-up" style="animation-delay: 0.2s;">
                    Coming Soon
                </h1>

                <!-- Subtitle -->
                <p class="text-2xl md:text-3xl text-white/90 mb-4 fade-in-up" style="animation-delay: 0.4s;">
                    Online Courses Platform
                </p>

                <p class="text-lg md:text-xl text-white/80 max-w-2xl mx-auto mb-12 fade-in-up" style="animation-delay: 0.6s;">
                    We're working hard to bring you an amazing online learning experience. Stay tuned for something extraordinary!
                </p>

                <!-- Features Preview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto mb-12">
                    <!-- Feature 1 -->
                    <div class="feature-card bg-white/10 backdrop-blur-md rounded-2xl p-6 slide-in-left" style="animation-delay: 0.8s;">
                        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-video text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">HD Video Lessons</h3>
                        <p class="text-white/80 text-sm">High-quality video content from expert instructors</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="feature-card bg-white/10 backdrop-blur-md rounded-2xl p-6 fade-in-up" style="animation-delay: 1s;">
                        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-certificate text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">Certifications</h3>
                        <p class="text-white/80 text-sm">Earn certificates upon course completion</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="feature-card bg-white/10 backdrop-blur-md rounded-2xl p-6 slide-in-right" style="animation-delay: 1.2s;">
                        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-users text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">Interactive Learning</h3>
                        <p class="text-white/80 text-sm">Engage with instructors and fellow students</p>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center fade-in-up" style="animation-delay: 1.4s;">
                    <a href="dashboard.php" class="inline-flex items-center gap-2 bg-white text-primary-blue px-8 py-4 rounded-full font-bold text-lg hover:bg-gray-100 transition-all hover:scale-105 shadow-lg">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    <a href="mailto:info@lms.com" class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md text-white px-8 py-4 rounded-full font-bold text-lg hover:bg-white/20 transition-all hover:scale-105 border-2 border-white/30">
                        <i class="fas fa-envelope"></i>
                        Get Notified
                    </a>
                </div>

                <!-- Status Message -->
                <div class="mt-16 fade-in" style="animation-delay: 1.6s;">
                    <p class="text-white/70 text-sm">
                        <i class="fas fa-clock mr-2"></i>
                        Expected Launch: Q2 2026
                    </p>
                </div>
            </div>
        </div>

        <!-- Decorative Elements -->
        <div class="absolute top-20 left-10 w-20 h-20 bg-white/10 rounded-full blur-xl fade-in"></div>
        <div class="absolute bottom-20 right-10 w-32 h-32 bg-white/10 rounded-full blur-xl fade-in" style="animation-delay: 0.5s;"></div>
        <div class="absolute top-1/2 left-1/4 w-16 h-16 bg-white/10 rounded-full blur-xl fade-in" style="animation-delay: 1s;"></div>
    </section>

    <!-- Additional Info Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-4xl font-bold text-gray-800 mb-6">
                    What to Expect
                </h2>
                <p class="text-lg text-gray-600 mb-12">
                    Our upcoming online courses platform will revolutionize the way you learn, offering a comprehensive suite of tools and resources.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Benefit 1 -->
                    <div class="text-left p-6 bg-light-blue rounded-xl hover:shadow-lg transition-shadow">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-primary-blue rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-laptop text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Learn Anywhere</h3>
                                <p class="text-gray-600">Access courses from any device, anytime, anywhere</p>
                            </div>
                        </div>
                    </div>

                    <!-- Benefit 2 -->
                    <div class="text-left p-6 bg-light-blue rounded-xl hover:shadow-lg transition-shadow">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-primary-blue rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-chart-line text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Track Progress</h3>
                                <p class="text-gray-600">Monitor your learning journey with detailed analytics</p>
                            </div>
                        </div>
                    </div>

                    <!-- Benefit 3 -->
                    <div class="text-left p-6 bg-light-blue rounded-xl hover:shadow-lg transition-shadow">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-primary-blue rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Self-Paced</h3>
                                <p class="text-gray-600">Learn at your own pace without any pressure</p>
                            </div>
                        </div>
                    </div>

                    <!-- Benefit 4 -->
                    <div class="text-left p-6 bg-light-blue rounded-xl hover:shadow-lg transition-shadow">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-primary-blue rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-star text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Expert Instructors</h3>
                                <p class="text-gray-600">Learn from industry professionals and experienced teachers</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark-blue text-white py-10">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?php echo date('Y'); ?> LMS Learning Management System. All rights reserved.</p>
            <p class="text-white/70 text-sm mt-2">Building the future of online education</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
