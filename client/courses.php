<?php
require_once '../config.php';
$courses = [];

// Get all active courses
$courses_query = "
    SELECT 
        c.id,
        c.title,
        c.description,
        c.cover_image,
        c.status,
        c.teacher_id
    FROM courses c
    WHERE c.status = 'published' OR c.status = 1
    ORDER BY c.title
";

$result = $conn->query($courses_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Fetch teacher details separately to avoid collation errors in JOIN
if (!empty($courses)) {
    $teacher_ids = array_unique(array_column($courses, 'teacher_id'));
    $teacher_ids = array_filter($teacher_ids); // Remove empty values
    
    if (!empty($teacher_ids)) {
        $ids_string = "'" . implode("','", array_map([$conn, 'real_escape_string'], $teacher_ids)) . "'";
        $teachers_query = "
            SELECT 
                user_id,
                first_name,
                second_name,
                profile_picture
            FROM users 
            WHERE user_id IN ($ids_string)
        ";
        
        $t_result = $conn->query($teachers_query);
        $teachers_map = [];
        if ($t_result) {
            while ($t = $t_result->fetch_assoc()) {
                $teachers_map[$t['user_id']] = $t;
            }
        }
        
        // Merge teacher info into courses
        foreach ($courses as &$course) {
            if (isset($course['teacher_id']) && isset($teachers_map[$course['teacher_id']])) {
                $t = $teachers_map[$course['teacher_id']];
                $course['first_name'] = $t['first_name'];
                $course['second_name'] = $t['second_name'];
                $course['teacher_image'] = $t['profile_picture'];
            } else {
                $course['first_name'] = '';
                $course['second_name'] = '';
                $course['teacher_image'] = '';
            }
        }
        unset($course); // Break reference
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - LMS</title>
    
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
                Start Learning
            </span>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">All Courses</h1>
            <p class="max-w-2xl mx-auto text-lg text-brand-100">
                Discover courses that spark your curiosity and advance your career.
            </p>
        </div>
    </section>

    <!-- Content Section -->
    <section class="py-20 px-4">
        <div class="container mx-auto max-w-7xl">
            
            <!-- Search Bar -->
            <div class="max-w-xl mx-auto mb-12 relative z-20">
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-red-600 to-red-400 rounded-full blur opacity-25 group-hover:opacity-50 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative">
                        <input type="text" id="courseSearch" placeholder="Search for courses..." 
                               class="block w-full p-3 pl-5 pr-12 text-base text-slate-900 bg-white border-2 border-red-100 rounded-full focus:ring-4 focus:ring-red-500/20 focus:border-red-500 outline-none shadow-xl transition-all placeholder-slate-400">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                            <i class="fas fa-search text-red-500 text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Courses Grid -->
            <?php if (count($courses) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8" id="coursesGrid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card group bg-white rounded-2xl shadow-sm hover:shadow-2xl transition-all duration-300 border border-slate-100 flex flex-col h-full hover:-translate-y-1 relative overflow-hidden">
                            <!-- Cover Image -->
                            <div class="relative h-48 overflow-hidden bg-slate-200">
                                <?php if (!empty($course['cover_image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($course['cover_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($course['title']); ?>"
                                         class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-100 to-red-50 text-red-300">
                                        <i class="fas fa-image text-4xl"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </div>
                            
                            <div class="p-6 pb-0 flex-grow">
                                <h3 class="text-xl font-bold text-slate-800 mb-2 group-hover:text-red-600 transition-colors line-clamp-2">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </h3>
                                
                                <?php if ($course['description']): ?>
                                    <p class="text-slate-500 text-sm leading-relaxed mb-4 line-clamp-3">
                                        <?php echo strip_tags(htmlspecialchars_decode($course['description'])); ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-slate-400 text-sm italic mb-4">No description available.</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-auto p-6 pt-0">
                                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                                    <!-- Teacher Info -->
                                    <div class="flex items-center space-x-2">
                                        <?php if (!empty($course['teacher_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($course['teacher_image']); ?>" 
                                                 class="w-8 h-8 rounded-full object-cover border border-slate-200"
                                                 alt="Instructor">
                                        <?php else: ?>
                                            <div class="w-8 h-8 rounded-full bg-red-100 text-red-500 flex items-center justify-center text-xs font-bold">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="text-xs text-slate-500 font-medium truncate max-w-[100px]" title="<?php echo htmlspecialchars($course['first_name'] . ' ' . $course['second_name']); ?>">
                                            <?php 
                                            echo htmlspecialchars(!empty($course['first_name']) ? $course['first_name'] . ' ' . $course['second_name'] : 'Instructor'); 
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <a href="../register.php" class="text-red-600 hover:text-red-700 font-bold text-sm flex items-center">
                                        Enroll <i class="fas fa-arrow-right ml-1 text-xs transition-transform group-hover:translate-x-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- No Results Message -->
                <div id="noResults" class="hidden text-center py-20">
                    <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mb-6 mx-auto text-red-300">
                        <i class="fas fa-search text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-2">No Courses Found</h3>
                    <p class="text-slate-500">Try adjusting your search terms.</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-dashed border-slate-300">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6 text-slate-300">
                        <i class="fas fa-layer-group text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-2">No Courses Available</h3>
                    <p class="text-slate-500">Check back later for new courses.</p>
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
                        An advanced Learning Management System designed to bridge the gap between students and education.
                    </p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="hover:text-brand-400 transition-colors">Home</a></li>
                        <li><a href="courses.php" class="hover:text-brand-400 transition-colors">Courses</a></li>
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
            <div class="pt-8 border-t border-slate-800 text-center text-sm text-slate-500">
                <p>&copy; <?php echo date('Y'); ?> LMS Learning Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS (Required for Navbar) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('courseSearch');
            const cards = document.querySelectorAll('.course-card');
            const noResults = document.getElementById('noResults');

            searchInput.addEventListener('keyup', function(e) {
                const term = e.target.value.toLowerCase();
                let visibleCount = 0;

                cards.forEach(card => {
                    const title = card.querySelector('h3').textContent.toLowerCase();
                    const teacher = card.querySelector('.text-xs.font-medium') ? card.querySelector('.text-xs.font-medium').textContent.toLowerCase() : '';
                    
                    if (title.includes(term) || teacher.includes(term)) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (visibleCount === 0 && term !== '') {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>
