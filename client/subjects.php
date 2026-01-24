<?php
require_once '../config.php';
$subjects = [];

// Get all active subjects with teacher info
$subjects_query = "
    SELECT 
        s.id,
        s.name,
        s.code,
        s.description,
        COUNT(DISTINCT ta.teacher_id) AS teacher_count,
        GROUP_CONCAT(DISTINCT CONCAT(COALESCE(u.profile_picture, 'NULL'), ':', u.first_name, ' ', u.second_name) SEPARATOR '|') AS teacher_info
    FROM subjects s
    LEFT JOIN stream_subjects ss ON s.id = ss.subject_id AND ss.status = 1
    LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id AND ta.status = 'active'
    LEFT JOIN users u ON ta.teacher_id = u.user_id
    WHERE s.status = 1
    GROUP BY s.id, s.name, s.code, s.description
    ORDER BY s.name
";

$result = $conn->query($subjects_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects - LMS</title>
    
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
                Explore Curriculum
            </span>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">All Subjects</h1>
            <p class="max-w-2xl mx-auto text-lg text-brand-100">
                Explore our comprehensive range of subjects taught by expert faculty.
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
                        <input type="text" id="subjectSearch" placeholder="Search for subjects..." 
                               class="block w-full p-3 pl-5 pr-12 text-base text-slate-900 bg-white border-2 border-red-100 rounded-full focus:ring-4 focus:ring-red-500/20 focus:border-red-500 outline-none shadow-xl transition-all placeholder-slate-400">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                            <i class="fas fa-search text-red-500 text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subjects Grid -->
                <?php if (count($subjects) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8" id="subjectsGrid">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="subject-card group bg-gradient-to-br from-red-600 to-red-800 rounded-2xl shadow-sm hover:shadow-2xl transition-all duration-300 border-4 border-red-500 flex flex-col h-full hover:-translate-y-1 relative overflow-hidden">
                                <div class="p-8 pb-0">
                                    <div class="w-16 h-16 bg-white/20 text-white rounded-full border-2 border-white/30 flex items-center justify-center text-2xl mb-6 backdrop-blur-sm shadow-inner">
                                        <i class="fas fa-book-open"></i>
                                    </div>
                                    
                                    <?php if ($subject['code']): ?>
                                        <span class="inline-block px-3 py-1 mb-3 text-xs font-bold tracking-wide text-white uppercase bg-white/20 rounded-full border border-white/30 backdrop-blur-sm">
                                            <?php echo htmlspecialchars($subject['code']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <h3 class="text-xl font-bold text-white mb-3">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </h3>
                                    
                                    <?php if ($subject['description']): ?>
                                        <p class="text-red-100 text-sm leading-relaxed mb-6 line-clamp-3">
                                            <?php echo htmlspecialchars($subject['description']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-red-200 text-sm italic mb-6">Explore this subject with our expert teachers.</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-auto p-6 pt-0">
                                    <div class="pt-6 border-t border-white/20">
                                        <!-- Teachers Avatar Stack -->
                                        <?php if (!empty($subject['teacher_info'])): ?>
                                            <div class="mb-4">
                                                <div class="flex flex-col space-y-2">
                                                    <?php 
                                                    $teachers = explode('|', $subject['teacher_info']);
                                                    $count = 0;
                                                    foreach ($teachers as $teacher_str): 
                                                        if ($count >= 3) break; // Limit to 3 teachers
                                                        $count++;
                                                        
                                                        // Parse "image:name" format
                                                        $parts = explode(':', $teacher_str, 2);
                                                        $img = isset($parts[0]) ? trim($parts[0]) : '';
                                                        $name = isset($parts[1]) ? trim($parts[1]) : 'Instructor';
                                                        
                                                        // Handle NULL image string from SQL
                                                        if ($img === 'NULL') $img = '';
                                                    ?>
                                                        <div class="flex items-center space-x-3 bg-red-800/20 p-2 rounded-lg backdrop-blur-sm border border-white/10">
                                                            <?php if (empty($img)): ?>
                                                                <div class="w-8 h-8 rounded-full border border-white/50 bg-white/20 flex items-center justify-center text-white text-xs font-bold shadow-sm flex-shrink-0">
                                                                    <i class="fas fa-user"></i>
                                                                </div>
                                                            <?php else: ?>
                                                                <img src="../<?php echo htmlspecialchars($img); ?>" 
                                                                     class="w-8 h-8 rounded-full border border-white/50 object-cover shadow-sm flex-shrink-0" 
                                                                     alt="<?php echo htmlspecialchars($name); ?>"
                                                                     onerror="this.onerror=null; this.parentNode.innerHTML='<div class=\'w-8 h-8 rounded-full border border-white/50 bg-white/20 flex items-center justify-center text-white text-xs font-bold shadow-sm flex-shrink-0\'><i class=\'fas fa-user\'></i></div>';">
                                                            <?php endif; ?>
                                                            
                                                            <span class="text-sm font-medium text-white line-clamp-1">
                                                                <?php echo htmlspecialchars($name); ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    
                                                    <?php if ($subject['teacher_count'] > 3): ?>
                                                        <div class="text-xs text-red-200 text-center pt-1 font-medium">
                                                            + <?php echo $subject['teacher_count'] - 3; ?> more instructor(s)
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <a href="../register.php" class="block w-full py-3 px-4 bg-white text-red-700 hover:bg-red-50 font-bold rounded-xl text-center transition-all duration-300 shadow-lg">
                                            Enroll Now <i class="fas fa-arrow-right ml-1"></i>
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
                        <h3 class="text-xl font-bold text-slate-800 mb-2">No Subjects Found</h3>
                        <p class="text-slate-500">Try adjusting your search terms.</p>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-dashed border-slate-300">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6 text-slate-300">
                            <i class="fas fa-box-open text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">No Subjects Available</h3>
                        <p class="text-slate-500">No subjects found for the selected stream.</p>
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
            const searchInput = document.getElementById('subjectSearch');
            const cards = document.querySelectorAll('.subject-card');
            const noResults = document.getElementById('noResults');

            searchInput.addEventListener('keyup', function(e) {
                const term = e.target.value.toLowerCase();
                let visibleCount = 0;

                cards.forEach(card => {
                    const title = card.querySelector('h3').textContent.toLowerCase();
                    const code = card.querySelector('span.uppercase') ? card.querySelector('span.uppercase').textContent.toLowerCase() : '';
                    
                    if (title.includes(term) || code.includes(term)) {
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
