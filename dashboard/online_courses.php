<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success_msg = '';
$error_msg = '';


// Handle AJAX Search Request
if ($role === 'student' && isset($_GET['ajax_search'])) {
    // 1. Get Enrolled IDs (to exclude)
    $stmt = $conn->prepare("SELECT course_id FROM course_enrollments WHERE student_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $enrolled_ids = [];
    while ($row = $res->fetch_assoc()) {
        $enrolled_ids[] = $row['course_id'];
    }
    $stmt->close();

    // 2. Search Available Courses
    $search = $_GET['search'] ?? '';
    // Simplify query to avoid JOIN collation issues
    $sql = "SELECT * FROM courses WHERE status = 1";
    
    if (!empty($search)) {
        $sql .= " AND (title LIKE '%" . $conn->real_escape_string($search) . "%' OR description LIKE '%" . $conn->real_escape_string($search) . "%')";
    }
    
    $result = $conn->query($sql);
    $ajax_courses = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['id'], $enrolled_ids)) {
                // Fetch teacher name manually
                $t_stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
                $t_stmt->bind_param("s", $row['teacher_id']);
                $t_stmt->execute();
                $t_res = $t_stmt->get_result();
                if ($t_data = $t_res->fetch_assoc()) {
                    $row['first_name'] = $t_data['first_name'];
                    $row['second_name'] = $t_data['second_name'];
                } else {
                    $row['first_name'] = 'Unknown';
                    $row['second_name'] = '';
                }
                $t_stmt->close();
                $ajax_courses[] = $row;
            }
        }
    }

    // 3. Generate HTML Output
    if (empty($ajax_courses)) {
        echo '<div class="col-span-full text-center py-10 text-gray-500">No courses found matching "' . htmlspecialchars($search) . '".</div>';
    } else {
        foreach($ajax_courses as $course) {
            $cover = !empty($course['cover_image']) ? '../'.htmlspecialchars($course['cover_image']) : '';
            $price_display = $course['price'] > 0 ? 'Rs. '.number_format($course['price']) : 'Free';
            $btn_text = $course['price'] > 0 ? 'Buy Now' : 'Enroll Now';
            $teacher_name = htmlspecialchars($course['first_name'] . ' ' . $course['second_name']);
            
            echo '
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-all flex flex-col h-full">
                <div class="h-48 bg-gray-200 relative overflow-hidden">';
            
            if ($cover) {
                echo '<img src="'.$cover.'" alt="Cover" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">';
            } else {
                echo '<div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400"><i class="fas fa-image text-4xl"></i></div>';
            }
            
            echo '
                    <div class="absolute top-2 right-2 px-2 py-1 bg-white/90 backdrop-blur rounded text-xs font-bold text-gray-800">
                        '.$price_display.'
                    </div>
                </div>
                <div class="p-6 flex-1 flex flex-col">
                    <div class="flex items-center text-xs text-gray-500 mb-2">
                        <span class="px-2 py-1 bg-gray-100 rounded-full mr-2"><i class="fas fa-user mr-1"></i> '.$teacher_name.'</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">'.htmlspecialchars($course['title']).'</h3>
                    <p class="text-gray-600 text-sm line-clamp-3 mb-4 flex-1">'.htmlspecialchars($course['description']).'</p>
                    
                    <form method="POST" class="mt-auto">
                        <input type="hidden" name="course_id" value="'.$course['id'].'">
                        <button type="submit" name="enroll_course" class="w-full py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition-colors shadow-md hover:shadow-lg transform active:scale-95 transition-transform">
                            '.$btn_text.'
                        </button>
                    </form>
                </div>
            </div>';
        }
    }
    exit; // Stop execution after sending AJAX response
}

// Handle Course Creation (Teacher Only)
if ($role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    
    // Handle Cover Image Upload
    $cover_image = '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/courses/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed)) {
            $filename = uniqid('course_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $filename)) {
                $cover_image = 'uploads/courses/' . $filename;
            }
        }
    }
    
    if (empty($title)) {
        $error_msg = "Course title is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (teacher_id, title, description, price, cover_image, status) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssds", $user_id, $title, $description, $price, $cover_image);
        
        if ($stmt->execute()) {
            $success_msg = "Course created successfully!";
        } else {
            $error_msg = "Error creating course: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Enrollment (Student Only)
if ($role === 'student' && isset($_POST['enroll_course'])) {
    $course_id = intval($_POST['course_id']);
    
    // Check if valid course
    $check = $conn->query("SELECT price FROM courses WHERE id = $course_id");
    if ($check->num_rows > 0) {
        $course = $check->fetch_assoc();
        $is_paid = ($course['price'] > 0);
        $payment_status = $is_paid ? 'pending' : 'free'; // Free if price is 0
        
        // Try Insert
        $stmt = $conn->prepare("INSERT IGNORE INTO course_enrollments (course_id, student_id, status, payment_status) VALUES (?, ?, 'active', ?)");
        $stmt->bind_param("iss", $course_id, $user_id, $payment_status);
        $stmt->execute();
        
        $enrollment_id = 0;
        
        if ($stmt->affected_rows > 0) {
            $enrollment_id = $stmt->insert_id;
            $success_msg = "Enrolled successfully!";
        } else {
            // Already Enrolled - Fetch ID
            $f_stmt = $conn->prepare("SELECT id, payment_status FROM course_enrollments WHERE course_id = ? AND student_id = ?");
            $f_stmt->bind_param("is", $course_id, $user_id);
            $f_stmt->execute();
            $res = $f_stmt->get_result();
            if($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $enrollment_id = $row['id'];
                $payment_status = $row['payment_status']; // Update status from DB
            }
            $error_msg = "You are already enrolled.";
        }
        $stmt->close();
        
        // Redirect if Pending Payment
        if ($enrollment_id > 0 && $payment_status === 'pending') {
            header("Location: course_payment_form.php?enrollment_id=" . $enrollment_id);
            exit;
        }
    }
}

// Fetch Data
$my_courses = [];
$all_courses = [];

if ($role === 'teacher') {
    // Get My Created Courses
    $stmt = $conn->prepare("SELECT * FROM courses WHERE teacher_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $my_courses[] = $row;
    }
    $stmt->close();
} else {
    // Student: Get Enrolled Courses
    $stmt = $conn->prepare("
        SELECT c.*, ce.payment_status 
        FROM courses c 
        JOIN course_enrollments ce ON c.id = ce.course_id 
        WHERE ce.student_id = ?
        ORDER BY ce.enrolled_at DESC
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrolled_ids = [];
    while ($row = $result->fetch_assoc()) {
        $my_courses[] = $row;
        $enrolled_ids[] = $row['id'];
    }
    $stmt->close();
    
    // Student: Get Available Courses (Search)
    $search = $_GET['search'] ?? '';
    // Simplify query to avoid JOIN collation issues
    $sql = "SELECT * FROM courses WHERE status = 1";
            
    if (!empty($search)) {
        $sql .= " AND (title LIKE '%" . $conn->real_escape_string($search) . "%' OR description LIKE '%" . $conn->real_escape_string($search) . "%')";
    }
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['id'], $enrolled_ids)) {
                // Fetch teacher name manually to avoid JOIN issues
                $t_stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
                $t_stmt->bind_param("s", $row['teacher_id']);
                $t_stmt->execute();
                $t_res = $t_stmt->get_result();
                if ($t_data = $t_res->fetch_assoc()) {
                    $row['first_name'] = $t_data['first_name'];
                    $row['second_name'] = $t_data['second_name'];
                } else {
                    $row['first_name'] = 'Unknown';
                    $row['second_name'] = '';
                }
                $t_stmt->close();
                
                $all_courses[] = $row;
            }
        }
    }
}

// Fetch Background Image
$background_image = '';
$bg_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'online_courses_background'");
$bg_stmt->execute();
$bg_result = $bg_stmt->get_result();
if ($row = $bg_result->fetch_assoc()) {
    $background_image = $row['setting_value'];
}
$bg_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Courses | LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        red: {
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
                            1000: '#500724',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 bg-cover bg-center bg-fixed bg-no-repeat min-h-screen backdrop-blur-sm" <?php echo $background_image ? 'style="background-image: url(\'../' . htmlspecialchars($background_image) . '\');"' : ''; ?>>
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 ">
        
        <!-- Messages -->
        <?php if($success_msg): ?>
            <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm" role="alert">
                <p><?php echo $success_msg; ?></p>
            </div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm" role="alert">
                <p><?php echo $error_msg; ?></p>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Online Courses</h1>
                <p class="mt-1 text-sm text-gray-500">Access high-quality educational content anywhere.</p>
            </div>
            
            <?php if($role === 'teacher'): ?>
                <button onclick="openCreateModal()" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <i class="fas fa-plus mr-2"></i> Create New Course
                </button>
            <?php else: ?>
                <div class="mt-4 md:mt-0 relative">
                    <input type="text" id="searchInput" placeholder="Search courses..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                           class="w-full md:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- TEACHER VIEW -->
        <?php if($role === 'teacher'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if(empty($my_courses)): ?>
                    <div class="col-span-full text-center py-12 bg-white rounded-lg border border-dashed border-gray-300">
                        <i class="fas fa-book-open text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900">No courses created</h3>
                        <p class="text-gray-500 mt-1">Get started by creating your first course.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($my_courses as $course): ?>
                        <div onclick="window.location.href='course_content.php?id=<?php echo $course['id']; ?>'" class="cursor-pointer bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-transform hover:-translate-y-1">
                            <div class="h-48 bg-gray-200 relative overflow-hidden">
                                <?php if($course['cover_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($course['cover_image']); ?>" alt="Cover" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">
                                        <i class="fas fa-image text-4xl"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute top-2 right-2 px-2 py-1 bg-white/90 backdrop-blur rounded text-xs font-bold text-gray-800">
                                    <?php echo $course['price'] > 0 ? 'Rs. '.number_format($course['price']) : 'Free'; ?>
                                </div>
                            </div>
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-gray-900 mb-2 truncate"><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="text-gray-600 text-sm line-clamp-2 mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                                <div class="flex justify-between items-center text-sm text-gray-500">
                                    <span><i class="far fa-clock mr-1"></i> <?php echo date('M d, Y', strtotime($course['created_at'])); ?></span>
                                    <span class="text-red-600 font-medium">Manage Content <i class="fas fa-arrow-right ml-1"></i></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        <!-- STUDENT VIEW -->
        <?php else: ?>
            
            <!-- Enrolled Courses -->
            <?php if(!empty($my_courses)): ?>
                <div class="mb-10">
                    <h2 class="text-2xl font-bold text-white mb-6 flex items-center bg-red-700 p-3">
                        <span class="bg-red-700 text-white p-2 rounded-lg mr-3"><i class="fas fa-graduation-cap"></i></span>
                        My Enrolled Courses
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($my_courses as $course): ?>
                            <div onclick="window.location.href='course_content.php?id=<?php echo $course['id']; ?>'" class="cursor-pointer bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-all">
                                <div class="h-40 bg-gray-200 relative overflow-hidden">
                                    <?php if($course['cover_image']): ?>
                                        <img src="../<?php echo htmlspecialchars($course['cover_image']); ?>" alt="Cover" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 bg-black/10"></div>
                                    <div class="absolute bottom-2 left-2 px-2 py-1 bg-green-500 text-white rounded text-xs font-bold">
                                        Enrolled
                                    </div>
                                </div>
                                <div class="p-5">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2 truncate"><?php echo htmlspecialchars($course['title']); ?></h3>
                                    <button class="w-full mt-2 py-2 bg-red-50 text-red-600 rounded-lg font-semibold hover:bg-red-100 transition-colors">
                                        Continue Learning
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Browse Courses -->
            <div>
                <h2 class="text-2xl font-bold text-white mb-6 flex items-center bg-red-700 p-3">
                    <span class=" text-white p-2 rounded-lg mr-3 bg-red-700 p-3"><i class="fas fa-compass"></i></span>
                    Browse Courses
                </h2>
                
                <div id="availableCoursesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 min-h-[200px]">
                    <?php if(empty($all_courses)): ?>
                         <div class="col-span-full text-center py-10 text-gray-900">No courses available.</div>
                    <?php else: ?>
                        <?php foreach($all_courses as $course): ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-all flex flex-col h-full">
                                <div class="h-48 bg-gray-200 relative overflow-hidden">
                                    <?php if($course['cover_image']): ?>
                                        <img src="../<?php echo htmlspecialchars($course['cover_image']); ?>" alt="Cover" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute top-2 right-2 px-2 py-1 bg-white/90 backdrop-blur rounded text-xs font-bold text-gray-800">
                                        <?php echo $course['price'] > 0 ? 'Rs. '.number_format($course['price']) : 'Free'; ?>
                                    </div>
                                </div>
                                <div class="p-6 flex-1 flex flex-col">
                                    <div class="flex items-center text-xs text-gray-500 mb-2">
                                        <span class="px-2 py-1 bg-gray-100 rounded-full mr-2"><i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['second_name']); ?></span>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($course['title']); ?></h3>
                                    <p class="text-gray-600 text-sm line-clamp-3 mb-4 flex-1"><?php echo htmlspecialchars($course['description']); ?></p>
                                    
                                    <form method="POST" class="mt-auto">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" name="enroll_course" class="w-full py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition-colors shadow-md hover:shadow-lg transform active:scale-95 transition-transform">
                                            <?php echo $course['price'] > 0 ? 'Buy Now' : 'Enroll Now'; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>

    <!-- Create Course Modal -->
    <div id="createCourseModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4 border-b pb-2">
                <h3 class="text-xl font-bold text-gray-900">Create New Course</h3>
                <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Course Title</label>
                    <input type="text" name="title" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Price (LKR) - 0 for Free</label>
                    <input type="number" name="price" min="0" step="0.01" value="0" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cover Image</label>
                    <input type="file" name="cover_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                </div>
                <div class="pt-4 flex justify-end">
                    <button type="button" onclick="closeCreateModal()" class="mr-3 px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</button>
                    <button type="submit" name="create_course" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 shadow-sm">Create Course</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createCourseModal').classList.remove('hidden');
        }
        function closeCreateModal() {
            document.getElementById('createCourseModal').classList.add('hidden');
        }
        // Close on click outside
        window.onclick = function(event) {
            const modal = document.getElementById('createCourseModal');
            if (event.target == modal) {
                closeCreateModal();
            }
        }
        
        // Auto-Search Feature
        const searchInput = document.getElementById('searchInput');
        const resultsGrid = document.getElementById('availableCoursesGrid');
        let searchTimeout;

        if (searchInput && resultsGrid) {
            searchInput.addEventListener('input', function() {
                const query = this.value;
                
                // Clear existing timeout to debounce
                clearTimeout(searchTimeout);
                
                // Set new timeout (debounce)
                searchTimeout = setTimeout(() => {
                    // Show loading state (optional, can just add opacity)
                    resultsGrid.style.opacity = '0.5';
                    
                    fetch('?ajax_search=1&search=' + encodeURIComponent(query))
                        .then(response => response.text())
                        .then(html => {
                            resultsGrid.innerHTML = html;
                            resultsGrid.style.opacity = '1';
                        })
                        .catch(err => {
                            console.error('Search failed', err);
                            resultsGrid.style.opacity = '1';
                        });
                }, 300); // 300ms delay
            });
        }
    </script>
</body>
</html>
