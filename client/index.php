<?php
require_once '../config.php';
require_once 'languages.php';

// Get all active teachers with their subjects and education
$teachers_query = "SELECT DISTINCT 
    u.user_id, u.first_name, u.second_name, u.profile_picture,
    sub.name as subject_name,
    s.name as stream_name,
    te.qualification, te.institution
FROM users u
INNER JOIN teacher_assignments ta ON u.user_id = ta.teacher_id
INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
INNER JOIN subjects sub ON ss.subject_id = sub.id
INNER JOIN streams s ON ss.stream_id = s.id
LEFT JOIN teacher_education te ON u.user_id = te.teacher_id
WHERE u.role = 'teacher' 
    AND u.status = 1 
    AND u.approved = 1
    AND ta.status = 'active'
ORDER BY u.first_name, u.second_name, sub.name
LIMIT 6";

$teachers_result = $conn->query($teachers_query);
$teachers = [];
while ($row = $teachers_result->fetch_assoc()) {
    $teacher_id = $row['user_id'];
    if (!isset($teachers[$teacher_id])) {
        $teachers[$teacher_id] = [
            'user_id' => $row['user_id'],
            'first_name' => $row['first_name'],
            'second_name' => $row['second_name'],
            'profile_picture' => $row['profile_picture'],
            'qualification' => $row['qualification'],
            'institution' => $row['institution'],
            'subjects' => []
        ];
    }
    if (!in_array($row['subject_name'], $teachers[$teacher_id]['subjects'])) {
        $teachers[$teacher_id]['subjects'][] = $row['subject_name'];
    }
}
$teachers = array_values($teachers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Excellence in Education</title>
    <!-- Bootstrap (Required for Navbar) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
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
                        brand: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            200: '#fecaca',
                            300: '#fca5a5',
                            400: '#f87171',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                            800: '#991b1b',
                            900: '#7f1d1d',
                            950: '#450a0a',
                        }
                    },
                    boxShadow: {
                        'glass': '0 8px 32px 0 rgba(31, 38, 135, 0.15)',
                        'glass-hover': '0 8px 32px 0 rgba(31, 38, 135, 0.30)',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .hero-pattern {
            background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        /* Gallery Scroll Animation */
        .scrolling-gallery {
            display: flex;
            gap: 1.5rem;
            width: max-content;
            animation: scrollGallery 60s linear infinite;
        }
        .scrolling-gallery:hover {
            animation-play-state: paused;
        }
        @keyframes scrollGallery {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
    </style>
</head>
<body class="antialiased text-slate-800">
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <section class="relative pt-28 pb-8 lg:pt-36 lg:pb-10 overflow-hidden bg-white">
        <div class="absolute inset-0 hero-pattern opacity-10"></div>
        
        <div class="container relative z-10 px-4 mx-auto">
            <div class="flex flex-col lg:flex-row items-center gap-12">
                <!-- Left Column -->
                <div class="w-full lg:w-1/2">
                <!-- Badge -->
                <div class="inline-flex items-center justify-center gap-2 px-4 py-2 mb-6 text-sm font-bold tracking-wider text-white uppercase bg-gradient-to-r from-brand-600 to-red-700 rounded-full border-2 border-white shadow-lg">
                    <i class="fas fa-crown text-yellow-300 text-lg"></i> <span data-translate="sri_lanka_hub">Sri Lanka's Number One LMS Platform</span>
                </div>

                <!-- Main Headline -->
                <h1 class="mb-6 text-4xl md:text-5xl lg:text-6xl font-black text-slate-900 leading-tight tracking-tight drop-shadow-lg">
                    <span class="block mb-2">
                        <span>Sri Lanka's</span>
                    </span>
                    <span class="block bg-gradient-to-r from-red-600 via-orange-500 to-red-600 bg-clip-text text-transparent drop-shadow-xl">
                        Number One LMS
                    </span>
                    <span class="block text-red-600 drop-shadow-lg">Platform</span>
                </h1>

                <!-- Description with Icons -->
                <div class="mb-8 space-y-4">
                    <p class="text-lg text-slate-600 font-medium leading-relaxed drop-shadow-md" data-translate="hero_desc">
                        🇱🇰 Empowering Sri Lankan students with <span class="text-red-600 font-black">world-class education</span>. Master diverse courses, learn from expert educators, and achieve your dreams with <span class="text-red-600 font-black">interactive learning</span> at your fingertips.
                    </p>
                </div>
                </div>

                <!-- Right Column -->
                <div class="w-full lg:w-1/2 relative">
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl border-4 border-red-600 group">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent z-10"></div>
                        <img src="assests/Main.jpg" alt="LMS Education" class="w-full h-auto object-cover transform group-hover:scale-105 transition-transform duration-700">
                    </div>
                    <!-- Decorative elements behind image -->
                    <div class="absolute -top-6 -right-6 w-24 h-24 bg-red-600/20 rounded-full blur-xl"></div>
                    <div class="absolute -bottom-6 -left-6 w-32 h-32 bg-yellow-400/10 rounded-full blur-xl"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Slideshow Section -->
    <section class="py-10 bg-red-600 overflow-hidden border-y-4 border-white">
        <div class="scrolling-gallery">
            <?php 
            $gallery_images = [
                'banner1.jpeg' => 'Interactive Classes', 'banner2.jpeg' => 'Expert Teachers', 
                'banner3.jpeg' => 'Modern Labs', 'banner4.jpeg' => 'Sports Activities',
                'banner5.jpeg' => 'Library Resources', 'banner6.jpeg' => 'Group Studies',
                'banner7.jpeg' => 'Online Learning', 'banner8.jpeg' => 'Student Life'
            ];
            // Repeat twice for infinite scroll
            for ($i = 0; $i < 2; $i++):
                foreach ($gallery_images as $img => $title): 
            ?>
                <div class="w-[450px] h-[300px] flex-shrink-0 rounded-xl p-1 bg-gradient-to-br from-gray-200 via-gray-400 to-gray-200 relative group cursor-pointer shadow-lg">
                    <div class="w-full h-full rounded-lg overflow-hidden relative bg-gray-100">
                        <img src="assests/<?php echo $img; ?>" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-700" alt="<?php echo $title; ?>">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
                            <span class="text-white font-bold text-lg transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300"><?php echo $title; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; endfor; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-gradient-to-b from-white to-slate-50">
        <div class="container px-4 mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="inline-block px-3 py-1 mb-4 text-xs font-bold tracking-wider text-red-600 uppercase bg-red-100 rounded-full border border-red-300">
                    🌟 Why Choose LMS Sri Lanka?
                </span>
                <h2 class="text-4xl md:text-5xl font-bold mb-6 text-slate-900" data-translate="complete_ecosystem">Your Complete Learning Ecosystem</h2>
                <div class="h-1 w-32 bg-gradient-to-r from-red-500 to-orange-500 mx-auto rounded-full mb-6"></div>
                <p class="text-slate-700 text-lg leading-relaxed" data-translate="choose_description">Everything you need to excel academically - from comprehensive subjects to live interactions with expert instructors.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1: All Subjects -->
                <div class="group relative p-8 bg-white rounded-2xl border-3 border-red-500 transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 hover:bg-red-50">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-red-100 border-3 border-red-500 rounded-full -mr-12 -mt-12 transition-all group-hover:scale-110 flex items-center justify-center">
                        <i class="fas fa-book-open text-red-500 text-3xl"></i>
                    </div>
                    <div class="w-16 h-16 rounded-2xl bg-red-500 text-white flex items-center justify-center text-3xl mb-6 transition-all group-hover:scale-110 group-hover:bg-red-600 relative z-10">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900 relative z-10" data-translate="all_subjects">All Subjects Available</h3>
                    <p class="text-slate-600 leading-relaxed relative z-10" data-translate="all_subjects_desc">Access complete curriculum across <span class="font-bold text-red-600">all major subjects</span> - Science, Mathematics, Languages, Social Studies and more. Learn everything you need in one platform.</p>
                </div>
                
                <!-- Feature 2: Access Class Records -->
                <div class="group relative p-8 bg-white rounded-2xl border-3 border-red-500 transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 hover:bg-red-50">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-red-100 border-3 border-red-500 rounded-full -mr-12 -mt-12 transition-all group-hover:scale-110 flex items-center justify-center">
                        <i class="fas fa-history text-red-500 text-3xl"></i>
                    </div>
                    <div class="w-16 h-16 rounded-2xl bg-red-500 text-white flex items-center justify-center text-3xl mb-6 transition-all group-hover:scale-110 group-hover:bg-red-600 relative z-10">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900 relative z-10" data-translate="access_records">Access All Class Records</h3>
                    <p class="text-slate-600 leading-relaxed relative z-10" data-translate="access_records_desc">Never miss a class! Access <span class="font-bold text-red-600">complete recording library</span> of all classes, lectures, and sessions. Review anytime, anywhere at your own pace.</p>
                </div>

                <!-- Feature 3: Online Courses -->
                <div class="group relative p-8 bg-white rounded-2xl border-3 border-red-500 transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 hover:bg-red-50">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-red-100 border-3 border-red-500 rounded-full -mr-12 -mt-12 transition-all group-hover:scale-110 flex items-center justify-center">
                        <i class="fas fa-laptop text-red-500 text-3xl"></i>
                    </div>
                    <div class="w-16 h-16 rounded-2xl bg-red-500 text-white flex items-center justify-center text-3xl mb-6 transition-all group-hover:scale-110 group-hover:bg-red-600 relative z-10">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900 relative z-10" data-translate="online_courses">Online Courses</h3>
                    <p class="text-slate-600 leading-relaxed relative z-10" data-translate="online_courses_desc">Structured <span class="font-bold text-red-600">online courses with flexibility</span> - learn at your convenience. Self-paced learning modules designed for maximum understanding.</p>
                </div>

                <!-- Feature 4: One-on-One Tutoring -->
                <div class="group relative p-8 bg-white rounded-2xl border-3 border-red-500 transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 hover:bg-red-50">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-red-100 border-3 border-red-500 rounded-full -mr-12 -mt-12 transition-all group-hover:scale-110 flex items-center justify-center">
                        <i class="fas fa-user-tie text-red-500 text-3xl"></i>
                    </div>
                    <div class="w-16 h-16 rounded-2xl bg-red-500 text-white flex items-center justify-center text-3xl mb-6 transition-all group-hover:scale-110 group-hover:bg-red-600 relative z-10">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900 relative z-10" data-translate="individual_tutoring">Individual Class Instructors</h3>
                    <p class="text-slate-600 leading-relaxed relative z-10" data-translate="individual_tutoring_desc">Struggling with a topic? Get <span class="font-bold text-red-600">personalized one-on-one guidance</span> from expert instructors. Direct interaction for doubts and clarifications.</p>
                </div>

                <!-- Feature 5: Online Assignments -->
                <div class="group relative p-8 bg-white rounded-2xl border-3 border-red-500 transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 hover:bg-red-50">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-red-100 border-3 border-red-500 rounded-full -mr-12 -mt-12 transition-all group-hover:scale-110 flex items-center justify-center">
                        <i class="fas fa-tasks text-red-500 text-3xl"></i>
                    </div>
                    <div class="w-16 h-16 rounded-2xl bg-red-500 text-white flex items-center justify-center text-3xl mb-6 transition-all group-hover:scale-110 group-hover:bg-red-600 relative z-10">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900 relative z-10" data-translate="assignments">Online Assignments & Tests</h3>
                    <p class="text-slate-600 leading-relaxed relative z-10" data-translate="assignments_desc">Regular <span class="font-bold text-red-600">interactive assignments and quizzes</span> to reinforce learning. Get instant feedback and track your progress efficiently.</p>
                </div>

                <!-- Feature 6: Live Class Chat -->
                <div class="group relative p-8 bg-white rounded-2xl border-3 border-red-500 transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 hover:bg-red-50">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-red-100 border-3 border-red-500 rounded-full -mr-12 -mt-12 transition-all group-hover:scale-110 flex items-center justify-center">
                        <i class="fas fa-comments text-red-500 text-3xl"></i>
                    </div>
                    <div class="w-16 h-16 rounded-2xl bg-red-500 text-white flex items-center justify-center text-3xl mb-6 transition-all group-hover:scale-110 group-hover:bg-red-600 relative z-10">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900 relative z-10" data-translate="live_chat">Live Class Chat & Discussion</h3>
                    <p class="text-slate-600 leading-relaxed relative z-10" data-translate="live_chat_desc">Interact in real-time! <span class="font-bold text-red-600">Live chat during sessions</span> - ask questions, participate in discussions, and engage with peers and teachers instantly.</p>
                </div>
            </div>

            <!-- Benefits Summary -->
            <div class="mt-16 p-8 bg-red-600 rounded-3xl border-2 border-white shadow-xl">
                <h3 class="text-2xl font-bold text-center text-white mb-6" data-translate="complete_learning">
                    🎯 Complete Learning Experience
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                        <span class="font-semibold text-white" data-translate="all_subjects">All Subjects</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                        <span class="font-semibold text-white" data-translate="access_records">Class Records</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                        <span class="font-semibold text-white" data-translate="online_courses">Online Courses</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                        <span class="font-semibold text-white" data-translate="individual_tutoring">Personal Tutoring</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                        <span class="font-semibold text-white" data-translate="assignments">Assignments</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                        <span class="font-semibold text-white" data-translate="live_chat">Live Chat</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Teachers Section -->
    <section class="py-20 bg-gradient-to-br from-red-50 via-white to-red-50 relative">
        <!-- Decorative blobs -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-brand-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>

        <div class="container px-4 mx-auto relative z-10">
            <div class="flex flex-col md:flex-row justify-between items-end mb-12">
                <div class="mb-6 md:mb-0">
                
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-900">Meet Our Expert Instructors</h2>
                </div>
                <a href="#" class="text-brand-600 font-semibold hover:text-brand-700 flex items-center group">
                    View All Teachers <i class="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (count($teachers) > 0): ?>
                    <?php foreach ($teachers as $teacher): ?>
                        <div class="group bg-gradient-to-br from-red-600 to-red-800 rounded-2xl shadow-sm hover:shadow-2xl transition-all duration-300 overflow-hidden border-4 border-red-500 flex flex-col h-full hover:-translate-y-1">
                            <div class="p-4 flex flex-col items-center border-b border-white/20">
                                <div class="relative w-32 h-32 mb-3 group-hover:scale-105 transition-transform duration-300">
                                    <!-- Red circle border -->
                                    <div class="absolute inset-0 rounded-full border-4 border-white"></div>
                                    <div class="absolute inset-0 rounded-full p-1">
                                        <?php if ($teacher['profile_picture']): ?>
                                            <img src="../<?php echo htmlspecialchars($teacher['profile_picture']); ?>" 
                                                 alt="<?php echo htmlspecialchars($teacher['first_name']); ?>" 
                                                 class="w-full h-full rounded-full object-cover shadow-lg">
                                        <?php else: ?>
                                            <div class="w-full h-full rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                                                <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['second_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <h3 class="text-lg font-bold text-white mb-1 text-center">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['second_name']); ?>
                                </h3>
                                
                                <?php if ($teacher['qualification']): ?>
                                    <div class="flex items-center justify-center gap-1 mb-3">
                                        <i class="fas fa-certificate text-white text-xs"></i>
                                        <p class="text-xs text-red-100 font-semibold">
                                            <?php echo htmlspecialchars($teacher['qualification']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($teacher['institution']): ?>
                                    <div class="w-full px-2 py-2 mb-2 bg-white/10 rounded-lg border-2 border-white/20 hover:border-white/40 transition-all hover:shadow-md">
                                        <div class="flex items-center justify-center gap-2">
                                            <i class="fas fa-university text-white text-sm"></i>
                                            <p class="text-xs text-white font-bold tracking-wide">
                                                <?php echo htmlspecialchars($teacher['institution']); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Card Body -->
                            <div class="p-3 flex-grow flex flex-col">
                                <!-- Subjects Section -->
                                <div class="mb-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-1 h-4 bg-white rounded"></div>
                                        <h4 class="text-xs font-bold text-white uppercase tracking-widest">Subjects</h4>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach (array_slice($teacher['subjects'], 0, 3) as $subject): ?>
                                            <span class="px-2 py-1 text-xs font-semibold text-white bg-white/20 rounded-full border border-white/30 hover:bg-white/30 transition-colors">
                                                <i class="fas fa-book-open me-1"></i><?php echo htmlspecialchars($subject); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="px-4 py-3 bg-black/10 border-t-2 border-white/20 mt-auto">
                                <div class="flex justify-center">
                                    <a href="examresults.php" class="text-white hover:text-red-100 text-sm font-bold flex items-center gap-1">
                                        <i class="fas fa-clipboard-list"></i> See Results
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-12 text-center bg-white rounded-xl shadow-sm border border-dashed border-slate-300">
                        <i class="fas fa-chalkboard-teacher text-5xl text-slate-300 mb-4"></i>
                        <p class="text-slate-500">No teachers available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Admission / CTA Section -->
    <section class="py-20 relative overflow-hidden">
        <div class="absolute inset-0 bg-brand-900">
            <div class="absolute inset-0 bg-gradient-to-r from-brand-900 to-brand-800"></div>
            <!-- Abstract shapes -->
            <div class="absolute top-0 left-0 w-full h-full opacity-10" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
        </div>
        
        <div class="container px-4 mx-auto relative z-10 text-center">
            <span class="inline-block py-1 px-3 rounded-full bg-yellow-400/20 text-yellow-300 text-sm font-bold tracking-wider mb-6 border border-yellow-400/30">
                ADMISSIONS OPEN
            </span>
            <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">Start Your Learning Journey Today</h2>
            <p class="text-xl text-brand-100 mb-10 max-w-2xl mx-auto">
                Join Sri Lanka's leading online academy. Hundreds of students are already learning on our platform&mdash;don't miss out on the opportunity to excel.
            </p>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="../register.php" class="px-8 py-4 bg-white text-brand-700 font-bold rounded-xl shadow-lg hover:bg-brand-50 transition-all hover:scale-105 flex items-center justify-center">
                    <i class="fas fa-user-plus me-2"></i> Register Now
                </a>
                <a href="examresults.php" class="px-8 py-4 bg-transparent border-2 border-white/30 text-white font-bold rounded-xl hover:bg-white/10 transition-all flex items-center justify-center">
                    <i class="fas fa-clipboard-check me-2"></i> Check Results
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-300 py-12 border-t border-slate-800">
        <div class="container px-4 mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                <div class="col-span-1 md:col-span-2">
                    <a href="index.php" class="inline-flex items-center text-white text-2xl font-bold mb-4">
                        <i class="fas fa-graduation-cap me-2 text-brand-400"></i> LMS
                    </a>
                    <p class="text-slate-400 mb-6 max-w-sm">
                        An advanced Learning Management System designed to bridge the gap between students and education, accessible anywhere, anytime.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-white hover:bg-brand-600 transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-white hover:bg-brand-600 transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-white hover:bg-brand-600 transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
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
                            <span>123 Education Street, Knowledge City, 10001</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt me-3 text-brand-500"></i>
                            <span>+1 (555) 123-4567</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope me-3 text-brand-500"></i>
                            <span>info@lms-school.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="pt-8 border-t border-slate-800 text-center text-sm text-slate-500">
                <p>&copy; <?php echo date('Y'); ?> LMS Learning Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS (Required for Navbar) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Animations -->
    <style>
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob {
            animation: blob 7s infinite;
        }
        .animation-delay-2000 {
            animation-delay: 2s;
        }
    </style>

    <script>
        // Translation data
        const translations = {
            'en': {
                'sri_lanka_hub': "Sri Lanka's Premier Learning Hub",
                'transform_future': 'Transform Your Future with LMS Excellence',
                'hero_desc': '🇱🇰 Empowering Sri Lankan students with world-class education. Master diverse courses, learn from expert educators, and achieve your dreams with interactive learning at your fingertips.',
                'start_learning': 'Start Learning Today',
                'student_login': 'Student Login',
                'why_choose': 'Your Complete Learning Ecosystem',
                'choose_desc': 'Everything you need to excel academically - from comprehensive subjects to live interactions with expert instructors.',
                'all_subjects': 'All Subjects Available',
                'subjects_desc': 'Access complete curriculum across all major subjects - Science, Mathematics, Languages, Social Studies and more. Learn everything you need in one platform.',
                'access_records': 'Access All Class Records',
                'records_desc': "Never miss a class! Access complete recording library of all classes, lectures, and sessions. Review anytime, anywhere at your own pace.",
                'online_courses': 'Online Courses',
                'courses_desc': 'Structured online courses with flexibility - learn at your convenience. Self-paced learning modules designed for maximum understanding.',
                'individual_tutoring': 'Individual Class Instructors',
                'tutoring_desc': 'Struggling with a topic? Get personalized one-on-one guidance from expert instructors. Direct interaction for doubts and clarifications.',
                'assignments': 'Online Assignments & Tests',
                'assignments_desc': 'Regular interactive assignments and quizzes to reinforce learning. Get instant feedback and track your progress efficiently.',
                'live_chat': 'Live Class Chat & Discussion',
                'chat_desc': "Interact in real-time! Live chat during sessions - ask questions, participate in discussions, and engage with peers and teachers instantly.",
                'complete_learning': '🎯 Complete Learning Experience',
                'meet_experts': 'Meet Our Expert Faculty',
                'experts_desc': 'Our dedicated team of qualified educators committed to shaping the future through excellence in teaching.',
                'view_all_teachers': 'View All Teachers',
            },
            'si': {
                'sri_lanka_hub': 'ශ්‍රී ලංකාවේ ප්‍රධාන ඉගෙනුම් කේන්ද්‍රය',
                'transform_future': 'LMS excellence සමඟ ඔබේ ඉතිරාසය පරිවර්තනය කරන්න',
                'hero_desc': '🇱🇰 ශ්‍රී ලංකාවේ සිසුන් ඉදිරියට ගෙනයාම ලෝක-ස්තරයේ ශික්ෂණය සමඟ. විවිධ පාඨමාලා ප්‍රගුණ කරන්න, විශේෂඥ අධ්‍යාපකයින්ගෙන් ඉගෙන ගන්න.',
                'start_learning': 'අද ඉගෙනීම ආරම්භ කරන්න',
                'student_login': 'සිසු ඇතුල්වීම',
                'why_choose': 'ඔබේ සම්පූර්ණ ඉගෙනුම් පරිසර පද්ධතිය',
                'choose_desc': 'ඉගෙනුම දෙකෙහි විෂයන් සිට ජීවත් ඉන්ටරැක්ෂන් සිට සම්පූර්ණ අධ්‍යාපකයින්ගේ සමඟ.',
                'all_subjects': 'සියලු විෂයන් ලබා ගත හැක',
                'subjects_desc': 'විද්‍යා, ගණිතය, භාෂා, සामාජික අධ්‍යයන සහ තවත් සම්පූර්ණ පාඨමාලා ප්‍රවේශ කරන්න.',
                'access_records': 'සියලු පන්තිවල වාර්තා ප්‍රවේශ කරන්න',
                'records_desc': 'පන්තිය කිසිවිට මිස් නොකරන්න! සියලු පන්තිවල සම්පූර්ණ 녹음පුස්තකාලය ප්‍රවේශ කරන්න.',
                'online_courses': 'ඉන්ටරනෙට් පාඨමාලා',
                'courses_desc': 'ලැබිය හැකි සමඟ ව්‍යුහගත සබැස්ස පාඨමාලා - ඔබේ පහසුවට ඉගෙන ගන්න.',
                'individual_tutoring': 'තනි පන්තිවල උපදේශකයින්',
                'tutoring_desc': 'කිසිමතක් සමඟ අරගල? විශේෂඥ උපදේශකයින්ගෙන් පුද්ගලගත එක-එක-එක මඟ පෙන්වීම ලබා ගන්න.',
                'assignments': 'සබැස්ස ඉතුරුම් හා ගණන්',
                'assignments_desc': 'නිත්‍ය ඉන්ටරැක්ටිව ඉතුරුම් සහ ප්‍රශ්න ඉගෙනුම ශක්තිමත් කිරීම සඳහා.',
                'live_chat': 'ජීවත් පන්තිවල කතාබස් හා සාකච්ඡා',
                'chat_desc': 'සත්‍ය වේලාවෙන් ඉන්ටරැක්ට් කරන්න! සැසි අතරතුර ජීවත් කතාබස්.',
                'complete_learning': '🎯 සම්පූර්ණ ඉගෙනුම පතිස්පර්ධන',
                'meet_experts': 'අපගේ විශේෂඥ උපදේශක පිරිසුන් හමුවන්න',
                'experts_desc': 'ශික්ෂණයේ වඩුතභාවයෙන් ලබා දුන් අනාගතය ගොඩනඟා සිටින අර්හතා ඇති අධ්‍යාපකවරුන්ගේ අපගේ කණ්ඩායම.',
                'view_all_teachers': 'සියලු උපදේශකයින් බලන්න',
            }
        };

        // Handle language changes
        window.addEventListener('languageChanged', function(e) {
            const lang = e.detail.language;
            updatePageLanguage(lang);
        });

        function updatePageLanguage(lang) {
            document.querySelectorAll('[data-translate]').forEach(element => {
                const key = element.getAttribute('data-translate');
                if (translations[lang] && translations[lang][key]) {
                    element.textContent = translations[lang][key];
                }
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentLang = localStorage.getItem('lms-language') || 'en';
            updatePageLanguage(currentLang);
        });
    </script>
</body>
</html>
