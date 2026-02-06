<?php




require_once 'config.php';

$success_message = '';
$error_message = '';

    // Initialize empty values for GET request
    $email = '';
    $first_name = '';
    $second_name = '';
    $mobile_number = '';
    $whatsapp_number = '';
    $verification_method = 'none';
    $nic_number = '';
    $nic_verified = 0;
    $otp_verified = 0;
    $dob = '';
    $school_name = '';
    $exam_year = '';
    $district = '';
    $address = '';
    $gender = '';

    // Get enrollment parameters from URL if not in POST
    $url_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $url_stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
    $url_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

    // Set initial values ensuring POST takes precedence
    $enrollment_type = $_POST['enrollment_type'] ?? ($url_course_id > 0 ? 'course' : 'subject');
    $course_id_selected = $_POST['course_id'] ?? $url_course_id;
    $stream_id_selected = $_POST['stream_id'] ?? $url_stream_id;
    $subject_id_selected = $_POST['subject_id'] ?? $url_subject_id;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Removed username input processing
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? ''; // Added confirm password
    
    $role = 'student'; // Only student registration allowed
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $verification_method = trim($_POST['verification_method'] ?? 'none');
    $nic_number = trim($_POST['nic_number'] ?? '');
    $nic_verified = isset($_POST['nic_verified']) ? intval($_POST['nic_verified']) : 0;
    $otp_verified = isset($_POST['otp_verified']) ? intval($_POST['otp_verified']) : 0;

    // Student-specific fields

    
    // Student-specific fields
    $dob = !empty($_POST['dob']) ? trim($_POST['dob']) : null;
    $school_name = !empty($_POST['school_name']) ? trim($_POST['school_name']) : null;
    $exam_year = !empty($_POST['exam_year']) ? intval($_POST['exam_year']) : null;
    $district = !empty($_POST['district']) ? trim($_POST['district']) : null;
    $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
    $gender = !empty($_POST['gender']) ? trim($_POST['gender']) : null;
    
    // Determine approval status based on verification (students only)
    $approved = 0;
    $verification_status = 'pending';
    
    // For students: verification determines approval
    if ($verification_method === 'nic' && $nic_verified === 1) {
        $approved = 1;
        $verification_status = 'verified_nic';
    } elseif ($verification_method === 'mobile' && $otp_verified === 1) {
        $approved = 1;
        $verification_status = 'verified_mobile';
    } else {
        // Verification failed or not completed - require admin approval
        $approved = 0;
        $verification_status = 'pending';
    }
    
    // Validation (students only)
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (empty($verification_method) || $verification_method === 'none') {
        $error_message = 'Please select a verification method and complete the verification.';
    } elseif ($verification_method === 'nic' && $nic_verified !== 1) {
        $error_message = 'Please verify your NIC number before submitting.';
    } elseif ($verification_method === 'mobile' && $otp_verified !== 1) {
        $error_message = 'Please verify your mobile number with OTP before submitting.';
    } else {
        // Additional validation for students
        $enrollment_type = $_POST['enrollment_type'] ?? 'subject';
        
        if ($enrollment_type === 'subject') {
            $stream_id_input = $_POST['stream_id'] ?? '';
            $subject_id_input = $_POST['subject_id'] ?? '';
            $selected_teacher_id = trim($_POST['selected_teacher_id'] ?? '');
            
            if (intval($stream_id_input) <= 0) {
                $error_message = 'Please select a stream.';
            } elseif (intval($subject_id_input) <= 0) {
                $error_message = 'Please select a subject.';
            } elseif (empty($selected_teacher_id)) {
                $error_message = 'Please select a teacher.';
            }
        } elseif ($enrollment_type === 'course') {
            $course_id_input = $_POST['course_id'] ?? '';
            if (intval($course_id_input) <= 0) {
                $error_message = 'Please select a course.';
            }
        }
        
        // If no validation errors, proceed with user creation
        if (empty($error_message)) {
            // Generate user_id based on role
            $role_prefix = [
                'student' => 'stu',
                'teacher' => 'tea',
                'instructor' => 'ins',
                'admin' => 'adm'
            ];
            $prefix = $role_prefix[$role] ?? 'usr';
            
            // Get next number for this role
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
            $pattern = $prefix . '_%';
            $stmt->bind_param("s", $pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $next_num = 1000; // Start from 1000
            if ($result->num_rows > 0) {
                $last_user = $result->fetch_assoc();
                $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
                $next_num = max($last_num + 1, 1000);
            }
            $stmt->close();
            
            $user_id = $prefix . '_' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            // $username = $user_id; // Removed username assignment
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Handle profile picture upload (optional for students)
            $profile_picture_path = null;
            
            // Process upload if file is provided
            if (empty($error_message) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK && !empty($_FILES['profile_picture']['name'])) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file = $_FILES['profile_picture'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                // Validate file type
                if (!in_array($file_ext, $allowed_extensions)) {
                    $error_message = 'Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.';
                } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error_message = 'File size too large. Maximum size is 5MB.';
                } else {
                    // Generate unique filename
                    $new_filename = $user_id . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $profile_picture_path = 'uploads/profiles/' . $new_filename;
                    } else {
                        $error_message = 'Failed to upload profile picture.';
                    }
                }
            }
            
            // If no upload errors, proceed with user creation
            if (empty($error_message)) {
                $nic_no_value = ($verification_method === 'nic' && !empty($nic_number)) ? $nic_number : null;
                $verification_method_value = ($verification_method !== 'none') ? $verification_method : 'none';
                
                $stmt = $conn->prepare("INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status, nic_no, verification_method, dob, school_name, exam_year, district, address, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssissssisss", $user_id, $email, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $profile_picture_path, $approved, $nic_no_value, $verification_method_value, $dob, $school_name, $exam_year, $district, $address, $gender);
                
                if ($stmt->execute()) {
                    // Handle student-specific data
                    // Handle student-specific data
                    if ($role === 'student') {
                        $enrollment_type = $_POST['enrollment_type'] ?? 'subject';
                        $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');

                        if ($enrollment_type === 'subject') {
                            $stream_id_input = $_POST['stream_id'] ?? '';
                            $subject_id_input = $_POST['subject_id'] ?? '';
                            
                            $stream_id = intval($stream_id_input);
                            $subject_id = intval($subject_id_input);
                            
                            // Create stream_subject if it doesn't exist
                            if (empty($error_message) && $stream_id > 0 && $subject_id > 0) {
                                $check_ss = $conn->prepare("SELECT id FROM stream_subjects WHERE stream_id = ? AND subject_id = ?");
                                $check_ss->bind_param("ii", $stream_id, $subject_id);
                                $check_ss->execute();
                                $ss_result = $check_ss->get_result();
                                
                                $stream_subject_id = null;
                                if ($ss_result->num_rows > 0) {
                                    $ss_row = $ss_result->fetch_assoc();
                                    $stream_subject_id = $ss_row['id'];
                                } else {
                                    $create_ss = $conn->prepare("INSERT INTO stream_subjects (stream_id, subject_id, status) VALUES (?, ?, 1)");
                                    $create_ss->bind_param("ii", $stream_id, $subject_id);
                                    if ($create_ss->execute()) {
                                        $stream_subject_id = $conn->insert_id;
                                    } else {
                                        $error_message = 'Error creating stream-subject combination: ' . $conn->error;
                                    }
                                    $create_ss->close();
                                }
                                $check_ss->close();
                                
                                // Insert student enrollment
                                if (empty($error_message) && $stream_subject_id) {
                                    $enroll_stmt = $conn->prepare("INSERT INTO student_enrollment (student_id, stream_subject_id, academic_year, enrolled_date, status, payment_status) VALUES (?, ?, ?, CURDATE(), 'active', 'pending')");
                                    $enroll_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
                                    
                                    if (!$enroll_stmt->execute()) {
                                        $error_message = 'User created but failed to enroll student: ' . $enroll_stmt->error;
                                    }
                                    $enroll_stmt->close();
                                }
                            }
                        } elseif ($enrollment_type === 'course') {
                            $course_id = intval($_POST['course_id'] ?? 0);
                            
                            if (empty($error_message) && $course_id > 0) {
                                // Enroll in course
                                $enroll_stmt = $conn->prepare("INSERT INTO course_enrollments (course_id, student_id, enrolled_at, status, payment_status) VALUES (?, ?, NOW(), 'active', 'pending')");
                                $enroll_stmt->bind_param("is", $course_id, $user_id);
                                
                                if (!$enroll_stmt->execute()) {
                                    if ($conn->errno != 1062) { // Ignore duplicate entry
                                        $error_message = 'User created but failed to enroll in course: ' . $enroll_stmt->error;
                                    }
                                }
                                $enroll_stmt->close();
                            }
                        }
                    }
                    
                    if (empty($error_message)) {
                        if ($approved == 1) {
                            header("Location: login.php?success=" . urlencode("Student registered successfully. Your User ID is $user_id. You can now login."));
                            exit;
                        } else {
                            $success_message = "Student registered successfully with User ID: $user_id. Your account is pending admin approval. You will be able to login once approved.";
                        }
                        // Clear form data
                        $_POST = array();
                    }
                } else {
                    if ($conn->errno == 1062) {
                        $error_message = 'Email or User ID already exists.';
                    } else {
                        $error_message = 'Error creating user: ' . $conn->error;
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Get streams for dropdown
$streams_query = "SELECT id, name FROM streams WHERE status = 1 ORDER BY name";
$streams_result = $conn->query($streams_query);
$streams = $streams_result->fetch_all(MYSQLI_ASSOC);

// Get available courses for selection
$courses_query = "SELECT id, teacher_id, title, price, cover_image FROM courses WHERE status = 1 ORDER BY title";
$courses_result = $conn->query($courses_query);
$courses = [];
if ($courses_result) {
    while($row = $courses_result->fetch_assoc()) {
        // Fetch teacher details safely
        $t_stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
        $t_stmt->bind_param("s", $row['teacher_id']);
        $t_stmt->execute();
        $t_res = $t_stmt->get_result();
        if ($t = $t_res->fetch_assoc()) {
            $row['teacher_name'] = $t['first_name'] . ' ' . $t['second_name'];
        } else {
            $row['teacher_name'] = 'Unknown Teacher';
        }
        $t_stmt->close();
        $courses[] = $row;
    }
}

// Sri Lanka Districts
$districts = [
    'Ampara', 'Anuradhapura', 'Badulla', 'Batticaloa', 'Colombo', 'Galle', 'Gampaha', 
    'Hambantota', 'Jaffna', 'Kalutara', 'Kandy', 'Kegalle', 'Kilinochchi', 'Kurunegala', 
    'Mannar', 'Matale', 'Matara', 'Monaragala', 'Mullaitivu', 'Nuwara Eliya', 
    'Polonnaruwa', 'Puttalam', 'Ratnapura', 'Trincomalee', 'Vavuniya'
];

// Pre-calculate next User ID for display (start from 1000)
$role_prefix_display = 'stu';
$stmt_display = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
$pattern_display = $role_prefix_display . '_%';
$stmt_display->bind_param("s", $pattern_display);
$stmt_display->execute();
$result_display = $stmt_display->get_result();

$next_num_display = 1000;
if ($result_display->num_rows > 0) {
    $last_user = $result_display->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], strlen($role_prefix_display) + 1));
    $next_num_display = max($last_num + 1, 1000);
}
$stmt_display->close();
$display_user_id = $role_prefix_display . '_' . str_pad($next_num_display, 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
                        url('https://res.cloudinary.com/dnfbik3if/image/upload/v1768487136/1220_avhcs8.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
        }
        .form-container {
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    
    <div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="form-container rounded-lg shadow-2xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center space-x-2">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        <span>Student Registration</span>
                    </h1>
                </div>

                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6" role="alert">
                        <div class="flex">
                            <svg class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6" role="alert">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Toast Container -->
                <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

                <form method="POST" action="" class="space-y-6" id="addUserForm" enctype="multipart/form-data">
                    <!-- Role (Hidden - Student Only) -->
                    <input type="hidden" id="role" name="role" value="student">
                    
                    <!-- SECTION 1: Student Information -->
                    <div class="space-y-6 border-2 border-red-500 rounded-lg p-6">
                        <div class="flex items-center justify-between bg-red-600 text-white px-6 py-3 rounded-t-lg -mx-6 -mt-6 mb-6">
                        <h3 class="text-xl font-bold">Student Information</h3>
                        <h3 class="text-xl font-bold"><span class="font-semibold">User ID:</span> <?php echo htmlspecialchars($display_user_id); ?></h3>
                        </div>
                        
                        <!-- User ID Display (Not an input) -->
                       
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- First Name and Last Name in single row -->
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required
                                       placeholder="Enter your first name / පළමු නම ඇතුළත් කරන්න"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="second_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                <input type="text" id="second_name" name="second_name" required
                                       placeholder="Enter your last name / දෙවන නම ඇතුළත් කරන්න"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                            </div>

                            <!-- Date of Birth and Gender -->
                            <div>
                                <label for="dob" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth *</label>
                                <input type="date" id="dob" name="dob" required
                                       max="<?php echo date('Y-m-d', strtotime('-10 years')); ?>"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                                <select id="gender" name="gender" required
                                        class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select Gender --</option>
                                    <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>

                            <!-- Password and Confirm Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                                <input type="password" id="password" name="password" required
                                       placeholder="Enter a strong password / ශක්තිමත් මුරපදයක් ඇතුළත් කරන්න"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       placeholder="Re-enter your password / මුරපදය නැවත ඇතුළත් කරන්න"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            </div>

                            <!-- School Name -->
                            <div>
                                <label for="school_name" class="block text-sm font-medium text-gray-700 mb-1">School Name</label>
                                <input type="text" id="school_name" name="school_name"
                                       placeholder="Enter your school name / පාසලේ නම ඇතුළත් කරන්න"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       value="<?php echo htmlspecialchars($_POST['school_name'] ?? ''); ?>">
                            </div>

                            <!-- District -->
                            <div>
                                <label for="district" class="block text-sm font-medium text-gray-700 mb-1">District *</label>
                                <div class="relative">
                                    <input type="text" id="district_search" 
                                           placeholder="Search or select your district / ඔබේ දිස්ත්‍රික්කය සොයන්න හෝ තෝරන්න"
                                           class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                           autocomplete="off"
                                           value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>"
                                           oninput="filterDistricts()" 
                                           onfocus="showDistricts()"
                                           onblur="setTimeout(hideDistricts, 200)">
                                    <input type="hidden" id="district" name="district" value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>">
                                    <div id="district_dropdown" class="absolute z-10 w-full bg-white border-2 border-red-300 rounded-md shadow-lg max-h-60 overflow-y-auto hidden">
                                        <!-- Options will be populated by JS -->
                                    </div>
                                </div>
                            </div>



                            <!-- Address (Full width) -->
                            <div class="md:col-span-2">
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                                <textarea id="address" name="address" rows="3" required
                                          placeholder="Enter your full address / සම්පූර්ණ ලිපිනය ඇතුළත් කරන්න"
                                          class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Contact Information -->
                    <div class="space-y-6 border-2 border-red-500 rounded-lg p-6">
                        <h3 class="text-xl font-bold bg-red-600 text-white px-6 py-3 rounded-t-lg -mx-6 -mt-6 mb-6">Contact Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- WhatsApp Number -->
                            <div>
                                <label for="whatsapp_number" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number *</label>
                                <input type="text" id="whatsapp_number" name="whatsapp_number" required
                                       placeholder="Enter WhatsApp number (e.g., 0771234567) / වට්ස්ඇප් අංකය ඇතුළත් කරන්න"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                            </div>

                            <!-- Mobile Number (Contact Number) -->
                            <div>
                                <label for="mobile_number" class="block text-sm font-medium text-gray-700 mb-1">Contact Number (Mobile) *</label>
                                <input type="text" id="mobile_number" name="mobile_number" required
                                       placeholder="Enter mobile number (e.g., 0771234567) / ජංගම දුරකථන අංකය ඇතුළත් කරන්න"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                            </div>

                            <!-- Email -->
                            <div class="md:col-span-2">
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <input type="email" id="email" name="email" required
                                       placeholder="Enter your email address (e.g., student@example.com) / ඊමේල් ලිපිනය ඇතුළත් කරන්න"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: Enrollment Details -->
                    <div class="space-y-6 border-2 border-red-500 rounded-lg p-6">
                        <h3 class="text-xl font-bold bg-red-600 text-white px-6 py-3 rounded-t-lg -mx-6 -mt-6 mb-6">Enrollment Details</h3>
                        
                        <!-- Academic Year (Hidden, set to current year automatically) -->
                        <input type="hidden" id="student_academic_year" name="academic_year" value="<?php echo date('Y'); ?>">
                        
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Stream Dropdown -->
                            <!-- Enrollment Type Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Enrollment Type *</label>
                                <div class="flex space-x-6">
                                    <label class="flex items-center space-x-3 cursor-pointer">
                                        <input type="radio" name="enrollment_type" value="subject" 
                                               <?php echo ($enrollment_type === 'subject') ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300"
                                               onchange="toggleEnrollmentType()">
                                        <span class="text-gray-900 font-medium">Class Enrollment</span>
                                    </label>
                                    <label class="flex items-center space-x-3 cursor-pointer">
                                        <input type="radio" name="enrollment_type" value="course"
                                               <?php echo ($enrollment_type === 'course') ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300"
                                               onchange="toggleEnrollmentType()">
                                        <span class="text-gray-900 font-medium">Online Course Enrollment</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Class Enrollment Fields -->
                            <div id="classEnrollmentContainer">
                                <div class="space-y-6">
                                    <!-- Stream Dropdown -->
                                    <div>
                                        <label for="stream_id" class="block text-sm font-medium text-gray-700 mb-1">Select Stream (Grade) *</label>
                                        <select id="stream_id" name="stream_id"
                                                class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                                onchange="handleStreamChange()">
                                            <option value="">-- Select Stream --</option>
                                            <?php foreach ($streams as $stream): ?>
                                                <option value="<?php echo $stream['id']; ?>" <?php echo ($stream_id_selected == $stream['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($stream['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <!-- Create New Subject Button removed -->
                                    </div>

                                    <!-- Subject Dropdown -->
                                    <div id="subjectContainer" class="hidden">
                                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Select Subject *</label>
                                        <select id="subject_id" name="subject_id"
                                                class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                                onchange="handleSubjectChange()">
                                            <option value="">-- Select Subject --</option>
                                        </select>
                                    </div>
        
                                    <!-- Teachers Grid -->
                                    <div id="teachersContainer" class="hidden">
                                        <label class="block text-sm font-medium text-gray-700 mb-4">Select Teacher *</label>
                                        <div id="teachersGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                            <!-- Teachers will be loaded here -->
                                        </div>
                                        <input type="hidden" id="selected_teacher_id" name="selected_teacher_id" value="">
                                    </div>
                                </div>
                            </div>

                            <!-- Course Enrollment Fields -->
                            <div id="courseEnrollmentContainer" class="hidden">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">Select Online Course *</label>
                                    <input type="hidden" id="course_id" name="course_id" value="<?php echo htmlspecialchars($course_id_selected); ?>">
                                    
                                    <?php if (empty($courses)): ?>
                                        <div class="text-center p-6 bg-gray-50 rounded-lg border border-gray-200">
                                            <p class="text-gray-500">No online courses available at the moment.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <?php foreach ($courses as $course): ?>
                                                <?php 
                                                    $isSelected = (intval($course_id_selected) === intval($course['id'])); 
                                                    $coverImage = !empty($course['cover_image']) ? htmlspecialchars($course['cover_image']) : 'https://via.placeholder.com/300x160?text=No+Image';
                                                ?>
                                                <div onclick="selectCourse(<?php echo $course['id']; ?>, this)" 
                                                     class="course-card cursor-pointer border-2 bg-white <?php echo $isSelected ? 'border-red-600 bg-red-50' : 'border-gray-400 hover:border-red-300'; ?> rounded-lg overflow-hidden transition-all duration-200 group relative">
                                                    
                                                    <!-- Selection Indicator -->
                                                    <div class="absolute top-2 right-2 z-10">
                                                        <div class="selection-circle w-6 h-6 rounded-full border-2 <?php echo $isSelected ? 'bg-red-600 border-red-600' : 'bg-white border-gray-300'; ?> flex items-center justify-center">
                                                            <?php if ($isSelected): ?>
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <!-- Cover Image -->
                                                    <div class="h-40 w-full overflow-hidden bg-gray-100">
                                                        <img src="<?php echo $coverImage; ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                                    </div>
                                                    
                                                    <!-- Content -->
                                                    <div class="p-4">
                                                        <h4 class="font-bold text-gray-900 mb-1 line-clamp-1"><?php echo htmlspecialchars($course['title']); ?></h4>
                                                        <p class="text-sm text-gray-600 mb-2">By <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                                                        <div class="flex items-center justify-between mt-3">
                                                            <span class="text-red-600 font-bold text-lg">Rs. <?php echo number_format($course['price'], 2); ?></span>
                                                            
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: Profile Picture (Optional) -->
                    <div class="space-y-6 border-2 border-red-500 rounded-lg p-6">
                        <h3 class="text-xl font-bold bg-red-600 text-white px-6 py-3 rounded-t-lg -mx-6 -mt-6 mb-6">Profile Picture (Optional)</h3>
                        
                        <div>
                            <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-2">
                                Upload Profile Picture 
                                <span class="text-gray-500 text-xs">(Max 5MB, JPG/PNG/GIF/WEBP)</span>
                            </label>
                            <div class="flex items-center space-x-4">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                       class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100"
                                       onchange="previewProfilePicture(this)">
                                <div id="photoPreview" class="hidden">
                                    <img id="previewImg" src="" alt="Preview" class="w-20 h-20 rounded-full object-cover border-2 border-red-300">
                                </div>
                            </div>
                            <p id="photoError" class="text-red-600 text-sm mt-1 hidden"></p>
                        </div>
                    </div>

                    <!-- Teacher-specific fields (Hidden for students) -->

                    <!-- Teacher-specific fields -->
                    <div id="teacherFields" class="hidden space-y-6 border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Teacher Details & Assignments</h3>
                        
                        <!-- Teacher Approval Notice -->
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 p-4 rounded mb-4" role="alert">
                            <div class="flex">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <p class="ml-3 text-sm font-medium">Teacher accounts require admin approval before you can log in to the system. No verification is needed.</p>
                            </div>
                        </div>
                        


                        <!-- Education Details -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-sm font-medium text-gray-700">Education Details</label>
                                <button type="button" onclick="addEducationField()" 
                                        class="text-sm bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <span>Add Education</span>
                                </button>
                            </div>
                            <div id="educationContainer" class="space-y-4">
                                <!-- Education fields will be added here -->
                            </div>
                        </div>

                        <!-- Stream Selection (Multi-select) -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700">Select Stream(s) *</label>
                                <button type="button" onclick="openCreateStreamModal()" 
                                        class="text-sm bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <!-- <span>Create New Stream</span> -->
                                </button>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                <?php foreach ($streams as $stream): ?>
                                    <label class="flex items-center space-x-2 p-3 border border-gray-300 rounded-md hover:bg-red-50 cursor-pointer">
                                        <input type="checkbox" name="teacher_streams[]" value="<?php echo $stream['id']; ?>" 
                                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded teacher-stream-checkbox"
                                               onchange="loadTeacherSubjects()">
                                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars($stream['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Subject Selection (Based on selected streams) -->
                        <div id="teacherSubjectContainer" class="hidden">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700">Select Subject(s) *</label>
                                <button type="button" onclick="openCreateSubjectModal()" 
                                        class="text-sm bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <!-- <span>Create New Subject</span> -->
                                </button>
                            </div>
                            <div id="teacherSubjectsGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                <!-- Subjects will be loaded here based on selected streams -->
                            </div>
                        </div>
                    </div>

                    <!-- Verification Section (Hidden for teachers) -->
                    <div id="verificationSection" class="border-t pt-6 space-y-4 border-2 border-red-500 rounded-lg p-6">
                        <h3 class="text-xl font-bold bg-red-600 text-white px-6 py-3 rounded-t-lg -mx-6 -mt-6 mb-6">Identity Verification</h3>
                        <p class="text-sm text-gray-600 mb-4">Please verify your identity using one of the following methods:</p>
                        
                        <!-- Verification Method Selection -->
                        <div class="space-y-3">
                            <label class="flex items-center space-x-3 p-4 border-2 border-gray-600 rounded-lg cursor-pointer hover:border-red-500 transition-colors">
                                <input type="radio" name="verification_method" value="nic" id="verify_nic" 
                                       class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-600"
                                       onchange="handleVerificationMethodChange()">
                                <div class="flex-1">
                                    <span class="block font-medium text-gray-900">Verify by NIC Number</span>
                                    <span class="block text-sm text-gray-500">Enter your Sri Lankan National Identity Card number</span>
                                </div>
                            </label>
                            
                            <label class="flex items-center space-x-3 p-4 border-2 border-gray-600 rounded-lg cursor-pointer hover:border-red-500 transition-colors">
                                <input type="radio" name="verification_method" value="mobile" id="verify_mobile"
                                       class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-600"
                                       onchange="handleVerificationMethodChange()">
                                <div class="flex-1">
                                    <span class="block font-medium text-gray-900">Verify by WhatsApp/Mobile Number</span>
                                    <span class="block text-sm text-gray-500">Receive an OTP code on your mobile number</span>
                                </div>
                            </label>
                        </div>
                        
                        <!-- NIC Verification -->
                        <div id="nicVerificationContainer" class="hidden">
                            <label for="nic_number" class="block text-sm font-medium text-gray-700 mb-1">NIC Number *</label>
                            <div class="flex space-x-2">
                                <input type="text" id="nic_number" name="nic_number" 
                                       placeholder="Enter NIC (e.g., 123456789V) / හැඳුනුම්පත් අංකය ඇතුළත් කරන්න"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       maxlength="12">
                                <button type="button" onclick="verifyNIC()" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                    Verify
                                </button>
                            </div>
                            <div id="nicVerificationResult" class="mt-2"></div>
                            <input type="hidden" id="nic_verified" name="nic_verified" value="0">
                        </div>
                        
                        <!-- Mobile/OTP Verification -->
                        <div id="mobileVerificationContainer" class="hidden space-y-4">
                            <div>
                                <label for="verification_mobile" class="block text-sm font-medium text-gray-700 mb-1">Mobile/WhatsApp Number *</label>
                                <div class="flex space-x-2">
                                    <input type="text" id="verification_mobile" name="verification_mobile" 
                                           placeholder="Mobile number will be auto-filled"
                                           readonly
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                    <button type="button" onclick="sendOTP()" id="sendOtpBtn"
                                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                        Send OTP
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Using the mobile number from your profile</p>
                            </div>
                            
                            <div id="otpInputContainer" class="hidden">
                                <label for="otp_code" class="block text-sm font-medium text-gray-700 mb-1">Enter OTP Code *</label>
                                <div class="flex space-x-2">
                                    <input type="text" id="otp_code" name="otp_code" 
                                           placeholder="Enter 6-digit OTP / ඉලක්කම් 6ක OTP කේතය ඇතුළත් කරන්න"
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                           maxlength="6">
                                    <button type="button" onclick="verifyOTP()" 
                                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                        Verify OTP
                                    </button>
                                </div>
                                <div id="otpVerificationResult" class="mt-2"></div>
                                <input type="hidden" id="otp_verified" name="otp_verified" value="0">
                            </div>
                        </div>
                        
                        <!-- Verification Status -->
                        <div id="verificationStatus" class="hidden"></div>
                    </div>
                    <!-- End of Verification Section -->

                    <!-- Terms and Conditions Section -->
                    <div class="border-t pt-6 mt-6">
                        <div class="flex items-start space-x-3">
                            <input type="checkbox" id="terms_checkbox" name="terms_accepted" value="1"
                                   class="h-5 w-5 text-red-600 focus:ring-red-500 border-gray-300 rounded mt-0.5 cursor-pointer"
                                   onclick="openTermsModal()">
                            <label for="terms_checkbox" class="text-sm text-gray-700 cursor-pointer">
                                I have read and agree to the <span class="text-red-600 font-medium hover:underline cursor-pointer" onclick="openTermsModal()">Terms and Conditions</span> *
                            </label>
                        </div>
                        <input type="hidden" id="terms_accepted" name="terms_accepted_confirmed" value="0">
                    </div>

                    <!-- Submit Button (Always Visible) - Direct child of form, NOT inside verificationSection -->
                    <div id="submitButtonContainer" class="flex justify-end space-x-3 pt-4 border-t mt-6">
                        <a href="login.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" name="add_user" id="registerButton"
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 font-medium">
                            Register
                        </button>
                    </div>
                    
                    <div class="mt-4 text-center pb-2">
                        <a href="client/index.php" class="text-sm font-medium text-gray-500 hover:text-red-600 flex items-center justify-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            Back to Website
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900">Terms and Conditions</h2>
                <button type="button" onclick="closeTermsModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="px-6 py-4 overflow-y-auto flex-1">
                <div class="prose prose-sm max-w-none">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">1. Acceptance of Terms</h3>
                    <p class="text-gray-700 mb-4">By registering for and using this Learning Management System (LMS), you agree to be bound by these Terms and Conditions. If you do not agree to these terms, please do not register or use our services.</p>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">2. User Registration</h3>
                    <p class="text-gray-700 mb-4">You must provide accurate, current, and complete information during the registration process. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">3. User Conduct</h3>
                    <p class="text-gray-700 mb-4">Users agree to use the LMS only for lawful purposes and in accordance with these Terms. You shall not:</p>
                    <ul class="list-disc pl-6 mb-4 text-gray-700">
                        <li>Upload or distribute any content that is illegal, harmful, or violates any rights of others</li>
                        <li>Attempt to gain unauthorized access to the system or other users' accounts</li>
                        <li>Interfere with or disrupt the operation of the LMS</li>
                        <li>Share your login credentials with others</li>
                    </ul>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">4. Privacy and Data Protection</h3>
                    <p class="text-gray-700 mb-4">We are committed to protecting your privacy. Your personal information will be collected, stored, and processed in accordance with applicable data protection laws. By registering, you consent to the collection and use of your information as described in our Privacy Policy.</p>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">5. Intellectual Property</h3>
                    <p class="text-gray-700 mb-4">All content, materials, and resources available through the LMS are the property of the institution or its licensors and are protected by copyright and other intellectual property laws. You may not reproduce, distribute, or create derivative works without explicit permission.</p>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">6. Course Enrollment and Payments</h3>
                    <p class="text-gray-700 mb-4">Enrollment in courses may be subject to approval and payment of applicable fees. Refund policies, if any, will be communicated separately. Failure to pay fees may result in suspension or termination of access to the LMS.</p>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">7. Account Termination</h3>
                    <p class="text-gray-700 mb-4">We reserve the right to suspend or terminate your account at any time for violation of these Terms or for any other reason deemed necessary to protect the integrity of the LMS.</p>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">8. Limitation of Liability</h3>
                    <p class="text-gray-700 mb-4">The LMS is provided "as is" without warranties of any kind. We shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of the system.</p>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">9. Changes to Terms</h3>
                    <p class="text-gray-700 mb-4">We reserve the right to modify these Terms at any time. Continued use of the LMS after changes constitutes acceptance of the modified Terms.</p>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">10. Contact Information</h3>
                    <p class="text-gray-700 mb-4">For questions or concerns about these Terms, please contact our support team.</p>

                    <p class="text-sm text-gray-600 mt-6 italic">Last updated: <?php echo date('F d, Y'); ?></p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="rejectTerms()" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 font-medium">
                    Reject
                </button>
                <button type="button" onclick="acceptTerms()" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">
                    Accept
                </button>
            </div>
        </div>
    </div>

    <script>
        // Toggle role-based fields (student only now)
        function toggleRoleBasedFields() {
            try {
                const studentFields = document.getElementById('studentFields');
                const teacherFields = document.getElementById('teacherFields');
                const verificationSection = document.getElementById('verificationSection');
                const submitButtonContainer = document.getElementById('submitButtonContainer');
                
                // Always show student fields and verification
                if (studentFields) studentFields.classList.remove('hidden');
                if (verificationSection) verificationSection.classList.remove('hidden');
                if (submitButtonContainer) submitButtonContainer.classList.remove('hidden');
                
                // Always hide teacher fields
                if (teacherFields) teacherFields.classList.add('hidden');
            } catch (error) {
                console.error('Error in toggleRoleBasedFields:', error);
            }
        }

        // Education field counter
        let educationCount = 0;

        // Add education field (for teachers)
        function addEducationField() {
            educationCount++;
            const container = document.getElementById('educationContainer');
            if (!container) return;
            
            const div = document.createElement('div');
            div.className = 'border border-gray-300 rounded-lg p-4 education-field';
            div.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qualification *</label>
                        <input type="text" name="education[${educationCount}][qualification]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., B.Sc. in Mathematics" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Institution</label>
                        <input type="text" name="education[${educationCount}][institution]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="University/College name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year Obtained</label>
                        <input type="number" name="education[${educationCount}][year_obtained]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., 2020" min="1950" max="2100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Field of Study</label>
                        <input type="text" name="education[${educationCount}][field_of_study]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., Mathematics, Physics">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Grade/Class</label>
                        <input type="text" name="education[${educationCount}][grade_or_class]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., First Class, Distinction">
                    </div>
                    <div class="flex items-end">
                        <button type="button" onclick="removeEducationField(this)" 
                                class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Remove
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }

        // Remove education field
        function removeEducationField(button) {
            const field = button.closest('.education-field');
            if (field) field.remove();
        }

        // Load subjects for selected streams (for teachers)
        async function loadTeacherSubjects() {
            const selectedStreams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);
            const subjectContainer = document.getElementById('teacherSubjectContainer');
            const subjectsGrid = document.getElementById('teacherSubjectsGrid');
            
            if (!subjectContainer || !subjectsGrid) return;
            
            if (selectedStreams.length === 0) {
                subjectContainer.classList.add('hidden');
                subjectsGrid.innerHTML = '';
                return;
            }

            subjectContainer.classList.remove('hidden');
            subjectsGrid.innerHTML = '<div class="col-span-full text-center py-4 text-gray-500">Loading subjects...</div>';

            try {
                // Fetch subjects for all selected streams
                const subjectPromises = selectedStreams.map(async streamId => {
                    const response = await fetch(`get_subjects.php?stream_id=${streamId}`);
                    const data = await response.json();
                    return { streamId, data };
                });

                const results = await Promise.all(subjectPromises);
                const allStreamSubjects = new Map();

                // Get stream names
                const streamNames = {};
                selectedStreams.forEach(streamId => {
                    const checkbox = document.querySelector(`input[value="${streamId}"].teacher-stream-checkbox`);
                    if (checkbox) {
                        const label = checkbox.closest('label');
                        if (label) {
                            streamNames[streamId] = label.textContent.trim();
                        }
                    }
                });

                // Process each stream's subjects
                for (const { streamId, data } of results) {
                    if (data.success && data.subjects) {
                        for (const subject of data.subjects) {
                            // Get stream_subject_id
                            const ssResponse = await fetch(`get_stream_subject_id.php?stream_id=${streamId}&subject_id=${subject.id}`);
                            const ssData = await ssResponse.json();
                            
                            if (ssData.success && ssData.stream_subject_id) {
                                const key = `${streamId}_${subject.id}`;
                                if (!allStreamSubjects.has(key)) {
                                    allStreamSubjects.set(key, {
                                        stream_subject_id: ssData.stream_subject_id,
                                        stream_id: streamId,
                                        subject_id: subject.id,
                                        subject_name: subject.name,
                                        stream_name: streamNames[streamId] || `Stream ${streamId}`
                                    });
                                }
                            }
                        }
                    }
                }

                updateSubjectGrid(allStreamSubjects);
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading subjects.');
                subjectsGrid.innerHTML = '';
            }
        }

        // Update subject grid
        function updateSubjectGrid(streamSubjectsMap) {
            const subjectsGrid = document.getElementById('teacherSubjectsGrid');
            const subjectContainer = document.getElementById('teacherSubjectContainer');
            
            if (!subjectsGrid || !subjectContainer) return;
            
            subjectsGrid.innerHTML = '';
            
            if (streamSubjectsMap.size > 0) {
                streamSubjectsMap.forEach((item, key) => {
                    const label = document.createElement('label');
                    label.className = 'flex items-center space-x-2 p-3 border border-gray-300 rounded-md hover:bg-red-50 cursor-pointer';
                    label.innerHTML = `
                        <input type="checkbox" name="teacher_subjects[]" value="${item.stream_subject_id}" 
                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                        <div class="flex-1">
                            <span class="text-sm font-medium text-gray-900">${item.subject_name}</span>
                            <span class="text-xs text-gray-500 block">${item.stream_name}</span>
                        </div>
                    `;
                    subjectsGrid.appendChild(label);
                });
            } else {
                subjectsGrid.innerHTML = '<div class="col-span-full text-center py-4 text-gray-500">No subjects available for selected streams.</div>';
            }
        }

        // Handle stream change (for students - no "new" option)
        function handleStreamChange() {
            const streamId = document.getElementById('stream_id').value;
            const subjectContainer = document.getElementById('subjectContainer');
            const teachersContainer = document.getElementById('teachersContainer');
            
            if (streamId) {
                loadSubjects();
            } else {
                subjectContainer.classList.add('hidden');
                teachersContainer.classList.add('hidden');
            }
        }

        // Load subjects based on selected stream
        function loadSubjects() {
            const streamId = document.getElementById('stream_id').value;
            const subjectContainer = document.getElementById('subjectContainer');
            const subjectSelect = document.getElementById('subject_id');
            const teachersContainer = document.getElementById('teachersContainer');
            
            if (!streamId) {
                subjectContainer.classList.add('hidden');
                teachersContainer.classList.add('hidden');
                return;
            }

            // Show loading state
            subjectContainer.classList.remove('hidden');
            subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';

            // Fetch subjects via AJAX
            fetch(`get_subjects.php?stream_id=${streamId}`)
                .then(response => {
                    // Check if response is ok
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Server error:', text);
                            throw new Error(`HTTP error! status: ${response.status}`);
                        });
                    }
                    // Check content type
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Invalid JSON response:', text);
                            throw new Error('Invalid JSON response from server');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';
                    
                    if (data && data.success && data.subjects && Array.isArray(data.subjects) && data.subjects.length > 0) {
                        // Subjects found - show subject dropdown
                        data.subjects.forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject.id;
                            option.textContent = subject.name;
                            subjectSelect.appendChild(option);
                        });
                        subjectContainer.classList.remove('hidden');
                    } else {
                        // No subjects found - hide subject dropdown
                        subjectContainer.classList.add('hidden');
                    }
                    
                    teachersContainer.classList.add('hidden');
                    document.getElementById('selected_teacher_id').value = '';
                })
                .catch(error => {
                    console.error('Error loading subjects:', error);
                    // On error, hide subject dropdown
                    subjectContainer.classList.add('hidden');
                });
        }

        // Handle subject change (for students - no "new" option)
        function handleSubjectChange() {
            const subjectId = document.getElementById('subject_id').value;
            const teachersContainer = document.getElementById('teachersContainer');
            
            if (subjectId) {
                loadTeachers();
            } else {
                teachersContainer.classList.add('hidden');
            }
        }

        // Load teachers based on selected subject
        function loadTeachers() {
            const streamId = document.getElementById('stream_id').value;
            const subjectId = document.getElementById('subject_id').value;
            const teachersContainer = document.getElementById('teachersContainer');
            const teachersGrid = document.getElementById('teachersGrid');
            
            if (!streamId || !subjectId || streamId === 'new' || subjectId === 'new') {
                teachersContainer.classList.add('hidden');
                return;
            }

            // Fetch teachers via AJAX
            fetch(`get_teachers.php?stream_id=${streamId}&subject_id=${subjectId}`)
                .then(response => response.json())
                .then(data => {
                    teachersGrid.innerHTML = '';
                    
                    if (data.success && data.teachers.length > 0) {
                        data.teachers.forEach(teacher => {
                            const card = createTeacherCard(teacher);
                            teachersGrid.appendChild(card);
                        });
                        teachersContainer.classList.remove('hidden');
                    } else {
                        teachersContainer.classList.add('hidden');
                        alert('No teachers available for this subject.');
                    }
                    
                    document.getElementById('selected_teacher_id').value = '';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading teachers.');
                });
        }

        // Create teacher card
        function createTeacherCard(teacher) {
            const card = document.createElement('div');
            card.className = 'bg-white border-2 border-red-500 rounded-lg p-6 hover:border-red-600 hover:shadow-xl cursor-pointer transition-all duration-200 teacher-card flex flex-col h-full';
            card.dataset.teacherId = teacher.teacher_id;
            
            // Large centered profile picture
            const profilePic = teacher.profile_picture 
                ? `<div class="flex justify-center mb-6">
                     <img src="${teacher.profile_picture}" alt="Profile" class="w-32 h-32 rounded-full object-cover border-4 border-red-200 shadow-lg">
                   </div>`
                : `<div class="flex justify-center mb-6">
                     <div class="w-32 h-32 rounded-full bg-red-100 flex items-center justify-center border-4 border-red-200 shadow-lg">
                       <svg class="w-16 h-16 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                       </svg>
                     </div>
                   </div>`;
            
            // Teacher name - left aligned
            const teacherName = (teacher.first_name || '') + ' ' + (teacher.second_name || '');
            const nameHTML = `<div class="text-left mb-4">
                <h4 class="font-bold text-xl text-gray-900 mb-1">${teacherName.trim() || 'Teacher'}</h4>
            </div>`;
            
            // WhatsApp number with icon - left aligned
            const whatsappHTML = teacher.whatsapp_number 
                ? `<div class="text-left mb-4 flex items-center">
                     <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                       <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                     </svg>
                     <span class="text-gray-700 font-medium">${teacher.whatsapp_number}</span>
                   </div>`
                : '';
            
            // Build education details HTML - left aligned
            let educationHTML = '';
            if (teacher.education && teacher.education.length > 0) {
                educationHTML = '<div class="text-left mb-4 flex-1">';
                educationHTML += '<h5 class="text-sm font-semibold text-gray-800 mb-3 uppercase tracking-wide flex items-center">';
                educationHTML += '<svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                educationHTML += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
                educationHTML += '</svg>Education Details</h5>';
                educationHTML += '<ul class="space-y-2">';
                
                teacher.education.forEach(edu => {
                    let eduText = edu.qualification || '';
                    if (edu.institution) {
                        eduText += ` - ${edu.institution}`;
                    }
                    if (edu.year_obtained) {
                        eduText += ` (${edu.year_obtained}`;
                        if (edu.grade_or_class) {
                            eduText += ` - ${edu.grade_or_class}`;
                        }
                        eduText += ')';
                    } else if (edu.grade_or_class) {
                        eduText += ` - ${edu.grade_or_class}`;
                    }
                    if (edu.field_of_study) {
                        eduText += ` - ${edu.field_of_study}`;
                    }
                    
                    educationHTML += `<li class="text-sm text-gray-600 flex items-start">
                        <svg class="w-4 h-4 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="flex-1">${eduText}</span>
                    </li>`;
                });
                
                educationHTML += '</ul></div>';
            } else {
                educationHTML = '<div class="text-left mb-4 flex-1">';
                educationHTML += '<h5 class="text-sm font-semibold text-gray-800 mb-3 uppercase tracking-wide flex items-center">';
                educationHTML += '<svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                educationHTML += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
                educationHTML += '</svg>Education Details</h5>';
                educationHTML += '<p class="text-sm text-gray-500 italic">No education details available</p>';
                educationHTML += '</div>';
            }
            

            
            card.innerHTML = `
                ${profilePic}
                ${nameHTML}
                ${whatsappHTML}
                ${educationHTML}
                <div class="mt-4 pt-4 border-t border-gray-200 text-center">
                    <span class="inline-flex items-center px-4 py-2 text-sm font-semibold bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Select Teacher
                    </span>
                </div>
            `;
            
            // Add click event
            card.addEventListener('click', function() {
                // Remove selection from all cards
                document.querySelectorAll('.teacher-card').forEach(c => {
                    c.classList.remove('border-red-600', 'bg-red-50', 'shadow-xl');
                    c.classList.add('border-red-500');
                });
                
                // Select this card
                card.classList.remove('border-red-500');
                card.classList.add('border-red-600', 'bg-red-50', 'shadow-xl');
                
                // Set selected teacher ID
                document.getElementById('selected_teacher_id').value = teacher.teacher_id;
            });
            
            return card;
        }

        // Preview profile picture
        function previewProfilePicture(input) {
            const preview = document.getElementById('photoPreview');
            const previewImg = document.getElementById('previewImg');
            const photoError = document.getElementById('photoError');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                // Validate file size
                if (file.size > maxSize) {
                    photoError.textContent = 'File size exceeds 5MB limit.';
                    photoError.classList.remove('hidden');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    photoError.textContent = 'Invalid file type. Please select an image file.';
                    photoError.classList.remove('hidden');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }
                
                photoError.classList.add('hidden');
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('hidden');
                photoError.classList.add('hidden');
            }
        }

        // Photo is optional for students
        function updatePhotoRequirement() {
            const photoInput = document.getElementById('profile_picture');
            const photoRequired = document.getElementById('photoRequired');
            
            photoInput.removeAttribute('required');
            if (photoRequired) photoRequired.classList.add('hidden');
        }

        // Create New Stream Modal (for teachers)
        function openCreateStreamModal() {
            const name = prompt('Enter new stream name:');
            if (!name || !name.trim()) {
                return;
            }
            
            const formData = new FormData();
            formData.append('name', name.trim());
            
            fetch('create_stream.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to refresh stream list
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create stream'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating stream.');
            });
        }

        // Create New Subject Modal (for teachers)
        function openCreateSubjectModal() {
            const selectedStreams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);
            
            if (selectedStreams.length === 0) {
                alert('Please select at least one stream first.');
                return;
            }
            
            // Use first selected stream for creation
            const streamId = selectedStreams[0];
            
            const name = prompt('Enter new subject name:');
            if (!name || !name.trim()) {
                return;
            }
            
            const code = prompt('Enter subject code (optional):') || '';
            
            const formData = new FormData();
            formData.append('name', name.trim());
            formData.append('code', code.trim());
            formData.append('stream_id', streamId);
            
            fetch('create_subject.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload subjects for all selected streams
                    loadTeacherSubjects();
                    alert('Subject created successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to create subject'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating subject.');
            });
        }



        // Toast Notification System
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const bgColor = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600';
            const icon = type === 'success' ? 
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>' :
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>';
            
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg mb-4 flex items-center space-x-3 transform transition-all duration-300 translate-x-full opacity-0 max-w-md`;
            toast.innerHTML = `
                <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    ${icon}
                </svg>
                <p class="flex-1 text-sm font-medium">${message}</p>
                <button onclick="closeToast('${toastId}')" class="text-white hover:text-gray-200">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 10);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                closeToast(toastId);
            }, 5000);
        }

        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }

        // Verification Method Change Handler
        function handleVerificationMethodChange() {
            const nicMethod = document.getElementById('verify_nic');
            const mobileMethod = document.getElementById('verify_mobile');
            const nicContainer = document.getElementById('nicVerificationContainer');
            const mobileContainer = document.getElementById('mobileVerificationContainer');
            const verificationStatus = document.getElementById('verificationStatus');
            
            // Reset verification status
            document.getElementById('nic_verified').value = '0';
            document.getElementById('otp_verified').value = '0';
            verificationStatus.classList.add('hidden');
            
            if (nicMethod.checked) {
                nicContainer.classList.remove('hidden');
                mobileContainer.classList.add('hidden');
                document.getElementById('otpInputContainer').classList.add('hidden');
                document.getElementById('otp_code').value = '';
                document.getElementById('otpVerificationResult').innerHTML = '';
            } else if (mobileMethod.checked) {
                nicContainer.classList.add('hidden');
                mobileContainer.classList.remove('hidden');
                document.getElementById('nic_number').value = '';
                document.getElementById('nicVerificationResult').innerHTML = '';
                
                // Auto-fill mobile number from form fields (use WhatsApp number first, then mobile number)
                const whatsappNumber = document.getElementById('whatsapp_number').value.trim();
                const mobileNumber = document.getElementById('mobile_number').value.trim();
                const verificationMobile = document.getElementById('verification_mobile');
                
                if (whatsappNumber) {
                    verificationMobile.value = whatsappNumber;
                    verificationMobile.readOnly = true;
                    verificationMobile.classList.add('bg-gray-50');
                } else if (mobileNumber) {
                    verificationMobile.value = mobileNumber;
                    verificationMobile.readOnly = true;
                    verificationMobile.classList.add('bg-gray-50');
                } else {
                    // If no number found, make field editable
                    verificationMobile.value = '';
                    verificationMobile.readOnly = false;
                    verificationMobile.classList.remove('bg-gray-50');
                    verificationMobile.placeholder = 'Enter mobile number';
                }
            } else {
                nicContainer.classList.add('hidden');
                mobileContainer.classList.add('hidden');
            }
        }

        // Verify NIC
        function verifyNIC() {
            const nicNumber = document.getElementById('nic_number').value.trim();
            const resultDiv = document.getElementById('nicVerificationResult');
            const dobField = document.getElementById('dob');
            const genderField = document.getElementById('gender');
            
            if (!nicNumber) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please enter NIC number</div>';
                showToast('Please enter NIC number', 'error');
                return;
            }
            
            // Check if DOB and Gender are filled first
            const dob = dobField ? dobField.value.trim() : '';
            const gender = genderField ? genderField.value.trim() : '';
            
            if (!dob) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please enter your Date of Birth first</div>';
                showToast('Please enter your Date of Birth before verifying NIC', 'error');
                dobField.focus();
                return;
            }
            
            if (!gender) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please select your Gender first</div>';
                showToast('Please select your Gender before verifying NIC', 'error');
                genderField.focus();
                return;
            }
            
            const formData = new FormData();
            formData.append('nic', nicNumber);
            
            // Send DOB and Gender for verification
            formData.append('dob', dob);
            formData.append('gender', gender);
            
            fetch('verify_nic.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.valid) {
                    resultDiv.innerHTML = `<div class="text-green-600 text-sm">NIC verified successfully against your DOB and Gender!</div>`;
                    document.getElementById('nic_verified').value = '1';
                    updateVerificationStatus();
                    showToast('NIC verified successfully against your information!', 'success');
                } else {
                    // Show verification failed message
                    resultDiv.innerHTML = '<div class="text-red-600 text-sm">NIC verification failed. The NIC number does not match with your Date of Birth and Gender. Please check and try again.</div>';
                    document.getElementById('nic_verified').value = '0';
                    updateVerificationStatus();
                    showToast('NIC verification failed. Please check your information and try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Error verifying NIC. Please try again.</div>';
                document.getElementById('nic_verified').value = '0';
                showToast('Error verifying NIC. Please try again.', 'error');
            });
        }

        // Send OTP
        function sendOTP() {
            const mobileNumber = document.getElementById('verification_mobile').value.trim();
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            
            if (!mobileNumber) {
                showToast('Please enter mobile number', 'error');
                return;
            }
            
            sendOtpBtn.disabled = true;
            sendOtpBtn.textContent = 'Sending...';
            
            const formData = new FormData();
            formData.append('mobile_number', mobileNumber);
            
            fetch('send_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('otpInputContainer').classList.remove('hidden');
                    // In production, remove the OTP display - it's only for testing
                    showToast('OTP sent successfully! Check your mobile. OTP: ' + data.otp, 'success');
                } else {
                    showToast('Error: ' + (data.message || 'Failed to send OTP'), 'error');
                }
                sendOtpBtn.disabled = false;
                sendOtpBtn.textContent = 'Send OTP';
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error sending OTP. Please try again.', 'error');
                sendOtpBtn.disabled = false;
                sendOtpBtn.textContent = 'Send OTP';
            });
        }

        // Verify OTP
        function verifyOTP() {
            const otpCode = document.getElementById('otp_code').value.trim();
            const resultDiv = document.getElementById('otpVerificationResult');
            
            if (!otpCode || otpCode.length !== 6) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please enter 6-digit OTP code</div>';
                return;
            }
            
            const formData = new FormData();
            formData.append('otp_code', otpCode);
            
            fetch('verify_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.verified) {
                    resultDiv.innerHTML = `<div class="text-green-600 text-sm">OTP verified successfully!</div>`;
                    document.getElementById('otp_verified').value = '1';
                    updateVerificationStatus();
                    showToast('Mobile number verified successfully!', 'success');
                } else {
                    resultDiv.innerHTML = `<div class="text-red-600 text-sm">${data.message || 'Invalid OTP code'}</div>`;
                    document.getElementById('otp_verified').value = '0';
                    updateVerificationStatus();
                    showToast(data.message || 'Invalid OTP code', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Error verifying OTP. Please try again.</div>';
                document.getElementById('otp_verified').value = '0';
                showToast('Error verifying OTP. Please try again.', 'error');
            });
        }

        // Update Verification Status
        function updateVerificationStatus() {
            const verificationStatus = document.getElementById('verificationStatus');
            const nicVerified = document.getElementById('nic_verified').value === '1';
            const otpVerified = document.getElementById('otp_verified').value === '1';
            
            if (nicVerified || otpVerified) {
                verificationStatus.innerHTML = `<div class="bg-green-50 border-l-4 border-green-400 text-green-700 p-4 rounded">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium">Verification successful! You can now submit the form.</p>
                    </div>
                </div>`;
                verificationStatus.classList.remove('hidden');
            } else {
                verificationStatus.classList.add('hidden');
            }
        }

        // Form Submission Validation (students only)
        document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
            const verificationMethod = document.querySelector('input[name="verification_method"]:checked');
            const nicVerified = document.getElementById('nic_verified').value === '1';
            const otpVerified = document.getElementById('otp_verified').value === '1';
            
            // Students need verification
            if (!verificationMethod) {
                e.preventDefault();
                showToast('Please select a verification method and complete the verification.', 'error');
                return false;
            }
            
            if (verificationMethod.value === 'nic' && !nicVerified) {
                e.preventDefault();
                showToast('Please verify your NIC number before submitting.', 'error');
                return false;
            }
            
            if (verificationMethod.value === 'mobile' && !otpVerified) {
                e.preventDefault();
                showToast('Please verify your mobile number with OTP before submitting.', 'error');
                return false;
            }
            
            // Check if terms are accepted
            const termsAccepted = document.getElementById('terms_accepted').value === '1';
            if (!termsAccepted) {
                e.preventDefault();
                showToast('Please read and accept the Terms and Conditions to continue.', 'error');
                return false;
            }
        });

        // Terms and Conditions Modal Functions
        function openTermsModal() {
            const modal = document.getElementById('termsModal');
            if (modal) {
                modal.classList.remove('hidden');
                // Prevent body scroll when modal is open
                document.body.style.overflow = 'hidden';
            }
        }

        function closeTermsModal() {
            const modal = document.getElementById('termsModal');
            if (modal) {
                modal.classList.add('hidden');
                // Restore body scroll
                document.body.style.overflow = 'auto';
            }
        }

        function acceptTerms() {
            const checkbox = document.getElementById('terms_checkbox');
            const hiddenInput = document.getElementById('terms_accepted');
            
            if (checkbox && hiddenInput) {
                checkbox.checked = true;
                hiddenInput.value = '1';
                showToast('Thank you for accepting our Terms and Conditions!', 'success');
            }
            
            closeTermsModal();
        }

        function rejectTerms() {
            const checkbox = document.getElementById('terms_checkbox');
            const hiddenInput = document.getElementById('terms_accepted');
            
            if (checkbox && hiddenInput) {
                checkbox.checked = false;
                hiddenInput.value = '0';
                showToast('You must accept the Terms and Conditions to register.', 'warning');
            }
            
            closeTermsModal();
        }

        // Close modal when clicking outside of it
        document.getElementById('termsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeTermsModal();
            }
        });

        // Prevent checkbox from toggling without reading terms
        document.getElementById('terms_checkbox')?.addEventListener('change', function(e) {
            if (this.checked) {
                // Uncheck it first, modal will handle the checking
                this.checked = false;
            }
        });

        // District Handling
        const districts = <?php echo json_encode($districts); ?>;
        const districtSearch = document.getElementById('district_search');
        const districtInput = document.getElementById('district');
        const districtDropdown = document.getElementById('district_dropdown');

        function populateDistricts(filter = '') {
            if (!districtDropdown) return;
            districtDropdown.innerHTML = '';
            
            const filtered = districts.filter(d => d.toLowerCase().includes(filter.toLowerCase()));
            
            if (filtered.length === 0) {
                const div = document.createElement('div');
                div.className = 'px-4 py-2 text-gray-500 text-sm';
                div.textContent = 'No results found';
                districtDropdown.appendChild(div);
                return;
            }

            filtered.forEach(d => {
                const div = document.createElement('div');
                div.className = 'px-4 py-2 hover:bg-red-50 cursor-pointer text-gray-700';
                div.textContent = d;
                div.onclick = function() {
                    selectDistrict(d);
                };
                districtDropdown.appendChild(div);
            });
        }

        function filterDistricts() {
            populateDistricts(districtSearch.value);
            districtDropdown.classList.remove('hidden');
        }

        function showDistricts() {
            populateDistricts(districtSearch.value);
            districtDropdown.classList.remove('hidden');
        }

        function hideDistricts() {
            // Small delay to allow click event to register
            if (districtDropdown) {
                 districtDropdown.classList.add('hidden');
            }
        }

        function selectDistrict(value) {
            if(districtSearch) districtSearch.value = value;
            if(districtInput) districtInput.value = value;
            hideDistricts();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleBasedFields();
            updatePhotoRequirement();
            
            // Handle URL parameters for pre-selection
            const urlParams = new URLSearchParams(window.location.search);
            const courseId = urlParams.get('course_id');
            const streamId = urlParams.get('stream_id');
            const subjectId = urlParams.get('subject_id');
            
            if (courseId && courseId > 0) {
                // Pre-select course enrollment
                const courseRadio = document.querySelector('input[name="enrollment_type"][value="course"]');
                if (courseRadio) {
                    courseRadio.checked = true;
                    toggleEnrollmentType();
                    
                    // Wait for DOM update, then select the course
                    setTimeout(() => {
                        const courseCard = document.querySelector(`.course-card[onclick*="${courseId}"]`);
                        if (courseCard) {
                            courseCard.click();
                        } else {
                            // Set hidden input directly if card click doesn't work
                            const hiddenInput = document.getElementById('course_id');
                            if (hiddenInput) hiddenInput.value = courseId;
                        }
                    }, 100);
                }
            } else if (streamId && streamId > 0) {
                // Pre-select subject enrollment
                const subjectRadio = document.querySelector('input[name="enrollment_type"][value="subject"]');
                if (subjectRadio) {
                    subjectRadio.checked = true;
                    toggleEnrollmentType();
                    
                    // Select stream
                    setTimeout(() => {
                        const streamSelect = document.getElementById('stream_id');
                        if (streamSelect) {
                            streamSelect.value = streamId;
                            handleStreamChange();
                            
                            // If subject_id is also provided, select it after subjects load
                            if (subjectId && subjectId > 0) {
                                setTimeout(() => {
                                    const subjectSelect = document.getElementById('subject_id');
                                    if (subjectSelect) {
                                        subjectSelect.value = subjectId;
                                        handleSubjectChange();
                                    }
                                }, 500);
                            }
                        }
                    }, 100);
                }
            } else {
                // Default toggle
                toggleEnrollmentType();
            }
        });

        function toggleEnrollmentType() {
            const enrollmentType = document.querySelector('input[name="enrollment_type"]:checked').value;
            const classContainer = document.getElementById('classEnrollmentContainer');
            const courseContainer = document.getElementById('courseEnrollmentContainer');
            // Element IDs inside class container that we need to target specifically if they are moved
            const subjectContainer = document.getElementById('subjectContainer');
            const teachersContainer = document.getElementById('teachersContainer');

            if (enrollmentType === 'subject') {
                classContainer.classList.remove('hidden');
                courseContainer.classList.add('hidden');
            } else {
                classContainer.classList.add('hidden');
                courseContainer.classList.remove('hidden');
            }
        }

        function selectCourse(courseId, element) {
            const hiddenInput = document.getElementById('course_id');
            const isCurrentlySelected = (hiddenInput.value == courseId);

            // Reset all cards first (Visual Reset)
            document.querySelectorAll('.course-card').forEach(card => {
                card.classList.remove('border-red-600', 'bg-red-50');
                card.classList.add('border-gray-400');
                
                // Reset Selection Circle
                const circle = card.querySelector('.selection-circle');
                if (circle) {
                    circle.classList.remove('bg-red-600', 'border-red-600');
                    circle.classList.add('bg-white', 'border-gray-300');
                    circle.innerHTML = '';
                }
            });

            if (isCurrentlySelected) {
                // Deselecting logic
                hiddenInput.value = '';
            } else {
                // Selecting logic
                hiddenInput.value = courseId;
                
                // Add selected state to clicked card
                element.classList.remove('border-gray-400');
                element.classList.add('border-red-600', 'bg-red-50');
                
                // Update Selection Circle
                const circle = element.querySelector('.selection-circle');
                if (circle) {
                    circle.classList.remove('bg-white', 'border-gray-300');
                    circle.classList.add('bg-red-600', 'border-red-600');
                    circle.innerHTML = '<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
            }
        }
    </script>
</body>
</html>
