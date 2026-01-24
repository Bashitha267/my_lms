<?php
require_once '../config.php';

// Get all active teachers with their subjects, streams, and education
$teachers_query = "SELECT DISTINCT 
    u.user_id, u.first_name, u.second_name, u.profile_picture, u.email, u.mobile_number,
    sub.name as subject_name,
    s.name as stream_name
FROM users u
INNER JOIN teacher_assignments ta ON u.user_id = ta.teacher_id
INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
INNER JOIN subjects sub ON ss.subject_id = sub.id
INNER JOIN streams s ON ss.stream_id = s.id
WHERE u.role = 'teacher' 
    AND u.status = 1 
    AND u.approved = 1
    AND ta.status = 'active'
ORDER BY u.first_name, u.second_name";

$teachers_result = $conn->query($teachers_query);
$teachers = [];
$all_subjects = [];
$all_streams = [];

while ($row = $teachers_result->fetch_assoc()) {
    $teacher_id = $row['user_id'];
    
    if (!isset($teachers[$teacher_id])) {
        // Get education details
        $edu_query = "SELECT qualification, institution, year_obtained, field_of_study 
                      FROM teacher_education 
                      WHERE teacher_id = ? 
                      ORDER BY year_obtained DESC 
                      LIMIT 1";
        $edu_stmt = $conn->prepare($edu_query);
        $edu_stmt->bind_param("s", $teacher_id);
        $edu_stmt->execute();
        $edu_result = $edu_stmt->get_result();
        $education = $edu_result->fetch_assoc();
        $edu_stmt->close();

        $teachers[$teacher_id] = [
            'user_id' => $row['user_id'],
            'first_name' => $row['first_name'],
            'second_name' => $row['second_name'],
            'profile_picture' => $row['profile_picture'],
            'email' => $row['email'],
            'mobile_number' => $row['mobile_number'],
            'qualification' => $education['qualification'] ?? null,
            'institution' => $education['institution'] ?? null,
            'subjects' => [],
            'streams' => []
        ];
    }
    
    if (!in_array($row['subject_name'], $teachers[$teacher_id]['subjects'])) {
        $teachers[$teacher_id]['subjects'][] = $row['subject_name'];
    }
    
    if (!in_array($row['stream_name'], $teachers[$teacher_id]['streams'])) {
        $teachers[$teacher_id]['streams'][] = $row['stream_name'];
    }
    
    // Collect all unique subjects and streams
    if (!in_array($row['subject_name'], $all_subjects)) {
        $all_subjects[] = $row['subject_name'];
    }
    if (!in_array($row['stream_name'], $all_streams)) {
        $all_streams[] = $row['stream_name'];
    }
}
$teachers = array_values($teachers);
sort($all_subjects);
sort($all_streams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Staff - LMS</title>
    
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
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; }
        
        /* Custom Scrollbar for Teacher Cards */
        .teacher-card ::-webkit-scrollbar {
            width: 4px;
        }
        .teacher-card ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        .teacher-card ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-slate-50 antialiased text-slate-800">

    <?php include 'navbar.php'; ?>

    <!-- Page Header -->
    <section class="relative pt-32 pb-20 overflow-hidden bg-brand-900">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 30px 30px;"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-brand-900/90"></div>
        <div class="container relative z-10 px-4 mx-auto text-center">
            <span class="inline-block px-3 py-1 mb-4 text-xs font-bold tracking-wider text-brand-200 uppercase bg-brand-800 rounded-full bg-opacity-50 border border-brand-700">
                World Class Education
            </span>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Meet Our Expert Faculty</h1>
            <p class="max-w-2xl mx-auto text-lg text-brand-100">
                Our dedicated team of qualified educators committed to shaping the future through excellence in teaching.
            </p>
        </div>
    </section>

    <!-- Staff Grid -->
    <section class="py-20 px-4 relative bg-gradient-to-br from-red-50 via-white to-red-50">
        <div class="container mx-auto max-w-7xl">
            <?php if (count($teachers) > 0): ?>
                <!-- Filter Section -->
                <div class="mb-12 bg-gradient-to-r from-red-50 to-orange-50 rounded-2xl p-8 border-2 border-red-200">
                    <h3 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                        <i class="fas fa-sliders-h text-red-600"></i> Filter Teachers
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Subject Filter -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-3">
                                <i class="fas fa-book text-red-500 me-2"></i>Filter by Subject
                            </label>
                            <select id="subjectFilter" class="w-full px-4 py-3 border-2 border-red-300 rounded-lg focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all">
                                <option value="">All Subjects</option>
                                <?php foreach ($all_subjects as $subject): ?>
                                    <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Stream Filter -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-3">
                                <i class="fas fa-layer-group text-red-500 me-2"></i>Filter by Class
                            </label>
                            <select id="streamFilter" class="w-full px-4 py-3 border-2 border-red-300 rounded-lg focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all">
                                <option value="">All Classes</option>
                                <?php foreach ($all_streams as $stream): ?>
                                    <option value="<?php echo htmlspecialchars($stream); ?>"><?php echo htmlspecialchars($stream); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch" id="teachersGrid">
                    <?php foreach ($teachers as $teacher): ?>
                        <div class="teacher-card group bg-gradient-to-br from-red-600 to-red-800 rounded-2xl shadow-sm hover:shadow-2xl transition-all duration-300 overflow-hidden border-4 border-red-500 flex flex-col hover:-translate-y-1" data-subjects="<?php echo htmlspecialchars(json_encode($teacher['subjects'])); ?>" data-streams="<?php echo htmlspecialchars(json_encode($teacher['streams'])); ?>">
                            
                            <!-- Card Header / Image -->
                            <div class="p-2 flex flex-col items-center border-b border-white/20">
                                <div class="relative w-20 h-20 mb-2 group-hover:scale-105 transition-transform duration-300">
                                    <!-- Red circle border with padding effect -->
                                    <div class="absolute inset-0 rounded-full border-4 border-white box-content" style="padding: 2px;"></div>
                                    <div class="absolute inset-0 rounded-full p-1">
                                        <?php if ($teacher['profile_picture']): ?>
                                            <img src="../<?php echo htmlspecialchars($teacher['profile_picture']); ?>" 
                                                 alt="<?php echo htmlspecialchars($teacher['first_name']); ?>"
                                                 class="w-full h-full rounded-full object-cover shadow-lg">
                                        <?php else: ?>
                                            <div class="w-full h-full rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white text-xl font-bold shadow-lg">
                                                <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['second_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <h3 class="text-sm font-bold text-white text-center mb-1 px-2">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['second_name']); ?>
                                </h3>
                                
                                <?php if ($teacher['qualification']): ?>
                                    <div class="flex items-center justify-center gap-1 mb-1 w-full px-2">
                                        <i class="fas fa-certificate text-white text-xs flex-shrink-0"></i>
                                        <p class="text-xs text-red-100 font-semibold text-center">
                                            <?php echo htmlspecialchars($teacher['qualification']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($teacher['institution']): ?>
                                    <div class="w-full px-2 py-1 mb-1 bg-white/10 rounded-lg border-2 border-white/20 hover:border-white/40 transition-all hover:shadow-md overflow-hidden">
                                        <div class="flex items-center justify-center gap-1">
                                            <i class="fas fa-university text-white text-xs flex-shrink-0"></i>
                                            <p class="text-[10px] text-white font-bold tracking-wide text-center">
                                                <?php echo htmlspecialchars($teacher['institution']); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Card Body -->
                            <div class="p-2 flex-grow flex flex-col">
                                <!-- Subjects Section -->
                                <div class="mb-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-1 h-3 bg-white rounded"></div>
                                        <h4 class="text-[9px] font-bold text-white uppercase tracking-widest">Subjects</h4>
                                    </div>
                                    <div class="flex flex-wrap gap-1 max-h-14 overflow-y-auto pr-1">
                                        <?php if (!empty($teacher['subjects'])): ?>
                                            <?php foreach ($teacher['subjects'] as $subject): ?>
                                                <span class="px-1 py-0.5 text-[9px] font-semibold text-white bg-white/20 rounded-full border border-white/30 hover:bg-white/30 transition-colors">
                                                    <i class="fas fa-book-open me-1"></i><?php echo htmlspecialchars($subject); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-red-200 italic">No subjects assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Streams Section -->
                                <?php if (!empty($teacher['streams'])): ?>
                                    <div class="mb-2">
                                        <div class="flex items-center gap-2 mb-1">
                                            <div class="w-1 h-3 bg-white rounded"></div>
                                            <h4 class="text-[9px] font-bold text-white uppercase tracking-widest">Classes</h4>
                                        </div>
                                        <div class="flex flex-wrap gap-1 max-h-14 overflow-y-auto pr-1">
                                            <?php foreach ($teacher['streams'] as $stream): ?>
                                                <span class="px-1 py-0.5 text-[9px] font-semibold text-white bg-white/20 rounded-full border border-white/30 hover:bg-white/30 transition-colors">
                                                    <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($stream); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Card Footer (Contacts) -->
                            <div class="px-2 py-1 bg-black/10 border-t-2 border-white/20 mt-auto text-center">
                                <div class="flex justify-center gap-3 mb-2">
                                    <?php if ($teacher['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($teacher['email']); ?>" 
                                           class="flex items-center justify-center w-8 h-8 rounded-full bg-white/20 border-2 border-white text-white hover:bg-white hover:text-red-600 transition-all duration-300 transform hover:scale-110"
                                           title="Send Email">
                                            <i class="fas fa-envelope text-sm"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($teacher['mobile_number']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($teacher['mobile_number']); ?>" 
                                           class="flex items-center justify-center w-8 h-8 rounded-full bg-white/20 border-2 border-white text-white hover:bg-white hover:text-green-600 transition-all duration-300 transform hover:scale-110"
                                           title="Call">
                                            <i class="fas fa-phone text-sm"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <a href="examresults.php" class="text-white hover:text-red-100 text-xs font-bold flex items-center justify-center gap-1">
                                    <i class="fas fa-clipboard-list"></i> See Results
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- No Results Message -->
                <div id="noResultsMessage" class="hidden bg-white rounded-3xl shadow-sm border border-slate-200 p-12 text-center max-w-lg mx-auto">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-400">
                        <i class="fas fa-search text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">No Teachers Found</h3>
                    <p class="text-slate-500">No teachers match your filter criteria. Please try different filters.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-12 text-center max-w-lg mx-auto">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-400">
                        <i class="fas fa-users-slash text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">No Staff Members Found</h3>
                    <p class="text-slate-500">We are currently updating our staff directory. Please check back later.</p>
                </div>
            <?php endif; ?>
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

    <!-- Teacher Filter Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const subjectFilter = document.getElementById('subjectFilter');
            const streamFilter = document.getElementById('streamFilter');
            const teacherCards = document.querySelectorAll('.teacher-card');
            const noResultsMessage = document.getElementById('noResultsMessage');

            function filterTeachers() {
                const selectedSubject = subjectFilter.value;
                const selectedStream = streamFilter.value;
                let visibleCount = 0;

                teacherCards.forEach(card => {
                    const subjectsStr = card.getAttribute('data-subjects');
                    const streamsStr = card.getAttribute('data-streams');
                    
                    const subjects = JSON.parse(subjectsStr);
                    const streams = JSON.parse(streamsStr);

                    let matchSubject = !selectedSubject || subjects.includes(selectedSubject);
                    let matchStream = !selectedStream || streams.includes(selectedStream);

                    if (matchSubject && matchStream) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Show/hide no results message
                if (visibleCount === 0) {
                    noResultsMessage.classList.remove('hidden');
                } else {
                    noResultsMessage.classList.add('hidden');
                }
            }

            // Add event listeners
            subjectFilter.addEventListener('change', filterTeachers);
            streamFilter.addEventListener('change', filterTeachers);
        });
    </script>
</body>
</html>