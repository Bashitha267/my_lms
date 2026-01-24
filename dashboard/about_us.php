<?php
// about_us.php - Publicly accessible About Us page
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Get system statistics
$student_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 1";
$teacher_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND status = 1 AND approved = 1";

$student_count = $conn->query($student_count_query)->fetch_assoc()['count'];
$teacher_count = $conn->query($teacher_count_query)->fetch_assoc()['count'];

// Add some "fake" data to make it look impressive if counts are low
$course_count = 15; // Set a static or dynamic count for courses
$active_hours = "24/7";

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

// Get all teachers
$query = "SELECT DISTINCT u.user_id, u.email, u.first_name, u.second_name, 
                 u.mobile_number, u.whatsapp_number, u.profile_picture, u.role
          FROM users u
          WHERE u.role = 'teacher'
            AND u.status = 1
            AND u.approved = 1
          ORDER BY u.first_name, u.second_name";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$teachers = [];
while ($row = $result->fetch_assoc()) {
    $teacher_id = $row['user_id'];
    
    // Get education details
    $edu_query = "SELECT qualification, institution, year_obtained, field_of_study, grade_or_class 
                  FROM teacher_education 
                  WHERE teacher_id = ? 
                  ORDER BY year_obtained DESC, id ASC";
    $edu_stmt = $conn->prepare($edu_query);
    $edu_stmt->bind_param("s", $teacher_id);
    $edu_stmt->execute();
    $edu_result = $edu_stmt->get_result();
    $education = [];
    while ($edu_row = $edu_result->fetch_assoc()) { $education[] = $edu_row; }
    $edu_stmt->close();
    
    // Get assignments
    $assign_query = "SELECT DISTINCT s.name as stream_name, sub.name as subject_name
                     FROM teacher_assignments ta
                     INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                     INNER JOIN streams s ON ss.stream_id = s.id
                     INNER JOIN subjects sub ON ss.subject_id = sub.id
                     WHERE ta.teacher_id = ? AND ta.status = 'active'
                     ORDER BY s.name, sub.name";
    $assign_stmt = $conn->prepare($assign_query);
    $assign_stmt->bind_param("s", $teacher_id);
    $assign_stmt->execute();
    $assign_result = $assign_stmt->get_result();
    $assignments = [];
    while ($assign_row = $assign_result->fetch_assoc()) { $assignments[] = $assign_row; }
    $assign_stmt->close();
    
    $teachers[] = [
        'user_id' => $row['user_id'],
        'email' => $row['email'],
        'first_name' => $row['first_name'],
        'second_name' => $row['second_name'],
        'mobile_number' => $row['mobile_number'],
        'whatsapp_number' => $row['whatsapp_number'],
        'profile_picture' => $row['profile_picture'],
        'education' => $education,
        'assignments' => $assignments
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            scroll-behavior: smooth;
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
            background: rgba(255, 255, 255, 0.7);
            <?php else: ?>
            background: #f9fafb;
            <?php endif; ?>
            min-height: 100vh;
        }

        /* Carousel Styles */
        .carousel-item {
            display: none;
            transition: opacity 1s ease-in-out;
        }
        .carousel-item.active {
            display: block;
        }

        /* Stats Animation */
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1);
        }

        .gradient-text {
            background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>

    <div class="content-overlay">
        <!-- Hero Slideshow Section -->
        <section class="relative h-[500px] overflow-hidden">
            <div id="hero-carousel" class="h-full">
                <?php 
                $carousel_images = [
                    'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80' => 'Learning together for a brighter future',
                    'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?ixlib=rb-1.2.1&auto=format&fit=crop&w=1951&q=80' => 'Expert instructors guiding your path',
                    'https://images.unsplash.com/photo-1501504905252-473c47e087f8?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80' => 'Modern digital learning environments'
                ];
                $idx = 0;
                foreach ($carousel_images as $img => $caption): 
                ?>
                    <div class="carousel-item h-full w-full relative <?php echo $idx === 0 ? 'active' : ''; ?>">
                        <img src="<?php echo $img; ?>" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/50 flex flex-col justify-center items-center text-center p-8">
                            <h2 class="text-4xl md:text-6xl font-extrabold text-white mb-4 drop-shadow-lg">
                                <?php echo htmlspecialchars($caption); ?>
                            </h2>
                            <p class="text-white text-xl md:text-2xl font-light max-w-2xl opacity-90">
                                Empowering students across Sri Lanka with world-class education.
                            </p>
                        </div>
                    </div>
                <?php $idx++; endforeach; ?>
            </div>
        </section>

        <!-- Intro Section -->
        <section class="py-20 px-4">
            <div class="max-w-4xl mx-auto text-center">
                <span class="text-red-600 font-bold uppercase tracking-widest text-sm mb-4 block">About Our Academy</span>
                <h2 class="text-4xl md:text-5xl font-black text-gray-900 mb-8 leading-tight">
                    We Are The <span class="gradient-text">Best Online Academy</span> In Sri Lanka
                </h2>
                <div class="h-1.5 w-24 bg-red-600 mx-auto rounded-full mb-10"></div>
                <p class="text-gray-600 text-lg leading-relaxed mb-12">
                    Our mission is to democratize education by providing high-quality, accessible, and affordable learning resources to every student in Sri Lanka. From advanced level subjects to language proficiency and technical skills, we bridge the gap between education and opportunity.
                </p>
            </div>
        </section>

        <!-- Animated Statistics Section -->
        <section class="py-16 bg-red-600">
            <div class="max-w-7xl mx-auto px-4 grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="text-5xl font-black text-white mb-2 counter" data-target="<?php echo $student_count; ?>">0</div>
                    <div class="text-red-100 uppercase tracking-widest text-xs font-bold">Active Students</div>
                </div>
                <div class="text-center">
                    <div class="text-5xl font-black text-white mb-2 counter" data-target="<?php echo $teacher_count; ?>">0</div>
                    <div class="text-red-100 uppercase tracking-widest text-xs font-bold">Expert Teachers</div>
                </div>
                <div class="text-center">
                    <div class="text-5xl font-black text-white mb-2 counter" data-target="<?php echo $course_count; ?>">0</div>
                    <div class="text-red-100 uppercase tracking-widest text-xs font-bold">Special Courses</div>
                </div>
                <div class="text-center">
                    <div class="text-5xl font-black text-white mb-2"><?php echo $active_hours; ?></div>
                    <div class="text-red-100 uppercase tracking-widest text-xs font-bold">Available Online</div>
                </div>
            </div>
        </section>

        <!-- Teachers Grid Section -->
        <section class="py-24 px-4 bg-white/40">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900">Meet Our Instructors</h2>
                    <p class="text-gray-500 mt-4">Learn from the best educators in the country.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php if (empty($teachers)): ?>
                        <div class="col-span-full text-center py-12">
                            <p class="text-gray-500 italic">Faculty details are being updated. Check back soon.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($teachers as $teacher): ?>
                            <div class="stat-card rounded-3xl p-8 flex flex-col items-center group hover:scale-[1.02] transition-transform duration-500">
                                <div class="relative mb-8">
                                    <?php if ($teacher['profile_picture']): ?>
                                         <div class="w-44 h-44 rounded-[2rem] overflow-hidden shadow-2xl border-4 border-white group-hover:scale-105 transition-transform duration-500">
                                            <img src="../<?php echo htmlspecialchars($teacher['profile_picture']); ?>" class="w-full h-full object-cover">
                                        </div>
                                    <?php else: ?>
                                        <div class="w-44 h-44 rounded-[2rem] bg-gradient-to-br from-red-50 to-red-100 flex items-center justify-center text-red-600 shadow-xl border-4 border-white">
                                            <i class="fas fa-user-tie text-5xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <h3 class="text-xl font-bold text-gray-900 mb-1">
                                    <?php echo htmlspecialchars(trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['second_name'] ?? ''))); ?>
                                </h3>

                                <div class="flex items-center space-x-2 mb-6">
                                    <span class="px-3 py-1 bg-red-50 text-red-600 text-[10px] font-bold uppercase rounded-full border border-red-100 tracking-wider">Expert Teacher</span>
                                </div>

                                <div class="w-full space-y-6 mb-8">
                                    <!-- Education Section -->
                                    <?php if (!empty($teacher['education'])): ?>
                                        <div class="education-details">
                                            <p class="text-[10px] text-gray-400 uppercase font-black mb-3 flex items-center">
                                                <i class="fas fa-graduation-cap mr-2 text-red-600"></i>
                                                Educational Background
                                                <span class="h-px flex-1 bg-gray-100 ml-3"></span>
                                            </p>
                                            <ul class="space-y-3">
                                                <?php foreach ($teacher['education'] as $edu): ?>
                                                    <li class="flex items-start group/edu">
                                                        <div class="mt-1.5 mr-3 w-1.5 h-1.5 rounded-full bg-red-400 shrink-0 group-hover/edu:scale-150 transition-transform"></div>
                                                        <div>
                                                            <p class="text-xs font-bold text-gray-800 leading-tight">
                                                                <?php echo htmlspecialchars($edu['qualification']); ?>
                                                            </p>
                                                            <?php if (!empty($edu['institution'])): ?>
                                                                <p class="text-[11px] text-gray-500 mt-0.5 italic">
                                                                    <?php echo htmlspecialchars($edu['institution']); ?>
                                                                    <?php echo !empty($edu['year_obtained']) ? ' ('.$edu['year_obtained'].')' : ''; ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Subjects Section -->
                                    <?php if (!empty($teacher['assignments'])): ?>
                                        <div class="subjects-details">
                                            <p class="text-[10px] text-gray-400 uppercase font-black mb-3 flex items-center">
                                                <i class="fas fa-book-open mr-2 text-red-600"></i>
                                                Teaching Areas
                                                <span class="h-px flex-1 bg-gray-100 ml-3"></span>
                                            </p>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($teacher['assignments'] as $assign): ?>
                                                    <span class="text-[11px] text-gray-700 bg-red-50/50 px-2.5 py-1 rounded-lg border border-red-100/50 font-semibold">
                                                        <?php echo htmlspecialchars($assign['subject_name']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto flex space-x-4">
                                    <?php if ($teacher['whatsapp_number']): ?>
                                        <a href="https://wa.me/<?php echo $teacher['whatsapp_number']; ?>" target="_blank" class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center hover:bg-green-600 transition-colors">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($teacher['email']): ?>
                                        <a href="mailto:<?php echo $teacher['email']; ?>" class="w-10 h-10 rounded-full bg-red-600 text-white flex items-center justify-center hover:bg-red-700 transition-colors">
                                            <i class="far fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Location & Contact Section -->
        <section class="py-24 px-4 bg-gray-900 text-white">
            <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div>
                    <h2 class="text-4xl font-bold mb-8">Visit Us Today</h2>
                    <p class="text-gray-400 text-lg mb-12">
                        Questions? Feedback? Or just want to say hi? We'd love to hear from you. Visit our main administrative hub or reach out via our 24/7 support line.
                    </p>

                    <div class="space-y-8">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-red-600 rounded-2xl flex items-center justify-center mr-6 shrink-0">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg mb-1">Our Location</h4>
                                <p class="text-gray-400">123 Education Lane, Academic Plaza, Colombo 07, Sri Lanka.</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-red-600 rounded-2xl flex items-center justify-center mr-6 shrink-0">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg mb-1">Telephone</h4>
                                <p class="text-gray-400">+94 112 345 678<br>+94 777 123 456</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-red-600 rounded-2xl flex items-center justify-center mr-6 shrink-0">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg mb-1">Official Email</h4>
                                <p class="text-gray-400">support@lms.lk<br>info@ouracademy.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Simple Map Placeholder -->
                <div class="h-[400px] bg-gray-800 rounded-3xl relative overflow-hidden group border border-gray-700">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126743.58290333252!2d79.78616335131018!3d6.921838640033148!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae253d10f7a70ad%3A0x3914c40645009950!2sColombo!5e0!3m2!1sen!2slk!4v1700000000000!5m2!1sen!2slk" 
                            class="w-full h-full grayscale opacity-70 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-700" 
                            style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    <div class="absolute top-4 right-4 bg-red-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-lg">
                        COLOMBO HEAD OFFICE
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer Section -->
        <footer class="bg-black py-12 px-4 border-t border-gray-900">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center">
                <div class="mb-8 md:mb-0 text-center md:text-left">
                    <h2 class="text-2xl font-black text-white mb-2 tracking-tighter">LMS ACADEMY</h2>
                    <p class="text-gray-600 text-xs uppercase tracking-widest font-bold">&copy; <?php echo date('Y'); ?> All Rights Reserved.</p>
                </div>
                <div class="flex space-x-8">
                    <a href="#" class="text-gray-500 hover:text-white transition-colors"><i class="fab fa-facebook-f text-xl"></i></a>
                    <a href="#" class="text-gray-500 hover:text-white transition-colors"><i class="fab fa-instagram text-xl"></i></a>
                    <a href="#" class="text-gray-500 hover:text-white transition-colors"><i class="fab fa-youtube text-xl"></i></a>
                    <a href="#" class="text-gray-500 hover:text-white transition-colors"><i class="fab fa-twitter text-xl"></i></a>
                </div>
            </div>
        </footer>
    </div>

    <script>
        // Carousel Logic
        let currentItem = 0;
        const items = document.querySelectorAll('.carousel-item');
        
        function nextSlide() {
            items[currentItem].classList.remove('active');
            currentItem = (currentItem + 1) % items.length;
            items[currentItem].classList.add('active');
        }
        
        setInterval(nextSlide, 5000);

        // High-Performance Stats Counter Logic
        const animateValue = (obj, start, end, duration) => {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                
                // Easing function: easeOutExpo
                const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                
                const current = Math.floor(easeProgress * (end - start) + start);
                obj.innerHTML = current.toLocaleString() + (end > 1000 ? '+' : '');
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    // Final value with pop effect
                    obj.classList.add('scale-110');
                    setTimeout(() => obj.classList.remove('scale-110'), 200);
                }
            };
            window.requestAnimationFrame(step);
        };

        const counters = document.querySelectorAll('.counter');
        const observerOptions = {
            threshold: 0.7,
            rootMargin: '0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = parseInt(entry.target.getAttribute('data-target'));
                    animateValue(entry.target, 0, target, 2500); // 2.5 seconds duration
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        counters.forEach(counter => {
            counter.classList.add('transition-transform', 'duration-300');
            observer.observe(counter);
        });
    </script>
</body>
</html>
