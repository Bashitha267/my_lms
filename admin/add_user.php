<?php
require_once '../check_session.php';

// Verify user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: /lms/login.php?error=" . urlencode("Access denied. Admin only."));
    exit();
}

require_once '../config.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $approved = isset($_POST['approved']) ? 1 : 0;
    
    // Validation
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } else {
        // Additional validation for students
        if ($role === 'student') {
            $stream_id_input = $_POST['stream_id'] ?? '';
            $subject_id_input = $_POST['subject_id'] ?? '';
            $selected_teacher_id = trim($_POST['selected_teacher_id'] ?? '');
            $new_stream_name = trim($_POST['new_stream_name'] ?? '');
            $new_subject_name = trim($_POST['new_subject_name'] ?? '');
            
            if ($stream_id_input === 'new' && empty($new_stream_name)) {
                $error_message = 'Please enter a stream name.';
            } elseif ($stream_id_input !== 'new' && intval($stream_id_input) <= 0) {
                $error_message = 'Please select a stream or create a new one.';
            } elseif ($subject_id_input === 'new' && empty($new_subject_name)) {
                $error_message = 'Please enter a subject name.';
            } elseif ($subject_id_input !== 'new' && intval($subject_id_input) <= 0) {
                $error_message = 'Please select a subject or create a new one.';
            } elseif (empty($selected_teacher_id)) {
                $error_message = 'Please select a teacher.';
            }
        }
        
        // Additional validation for teachers
        if ($role === 'teacher') {
            // No strict validation for stream/subjects for teachers as they can add them later
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
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Handle profile picture upload
            $profile_picture_path = null;
            
            // Check if photo is required (for teachers)
            if ($role === 'teacher') {
                if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                    // Handle other upload errors
                    switch ($_FILES['profile_picture']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = 'File size exceeds the maximum allowed size.';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = 'File upload was interrupted. Please try again.';
                            break;
                        default:
                            $error_message = 'An error occurred during file upload. Please try again.';
                    }
                }
            }
            
            // Process upload if file is provided
            if (empty($error_message) && isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
                // Check for upload errors
                if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                    switch ($_FILES['profile_picture']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = 'File size exceeds the maximum allowed size.';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = 'File upload was interrupted. Please try again.';
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            // No file uploaded, this is OK for non-teachers
                            break;
                        default:
                            $error_message = 'An error occurred during file upload. Please try again.';
                    }
                }
                
                // If no upload errors, process the file
                if (empty($error_message)) {
                    $upload_dir = '../uploads/profiles/';
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
                            $error_message = 'Failed to upload profile picture. Please check folder permissions.';
                        }
                    }
                }
            }
            
            // If no upload errors, proceed with user creation
            if (empty($error_message)) {
                // Insert user (profile_picture can be null)
                $stmt = $conn->prepare("INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1)");
                $stmt->bind_param("sssssssssi", $user_id, $email, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $profile_picture_path, $approved);
                
                if ($stmt->execute()) {
                    // Handle student-specific data
                    if ($role === 'student') {
                        $stream_id_input = $_POST['stream_id'] ?? '';
                        $subject_id_input = $_POST['subject_id'] ?? '';
                        $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');
                        $new_stream_name = trim($_POST['new_stream_name'] ?? '');
                        $new_subject_name = trim($_POST['new_subject_name'] ?? '');
                        $new_subject_code = trim($_POST['new_subject_code'] ?? '');
                        
                        $stream_id = 0;
                        $subject_id = 0;
                        
                        // Create new stream if needed
                        if ($stream_id_input === 'new' && !empty($new_stream_name)) {
                            $check_stream = $conn->prepare("SELECT id FROM streams WHERE name = ?");
                            $check_stream->bind_param("s", $new_stream_name);
                            $check_stream->execute();
                            $stream_result = $check_stream->get_result();
                            
                            if ($stream_result->num_rows > 0) {
                                $stream_row = $stream_result->fetch_assoc();
                                $stream_id = $stream_row['id'];
                            } else {
                                $create_stream = $conn->prepare("INSERT INTO streams (name, status) VALUES (?, 1)");
                                $create_stream->bind_param("s", $new_stream_name);
                                if ($create_stream->execute()) {
                                    $stream_id = $conn->insert_id;
                                } else {
                                    $error_message = 'Error creating stream: ' . $conn->error;
                                }
                                $create_stream->close();
                            }
                            $check_stream->close();
                        } else {
                            $stream_id = intval($stream_id_input);
                        }
                        
                        // Create new subject if needed
                        if (empty($error_message) && $subject_id_input === 'new' && !empty($new_subject_name)) {
                            $check_subject = $conn->prepare("SELECT id FROM subjects WHERE name = ?");
                            $check_subject->bind_param("s", $new_subject_name);
                            $check_subject->execute();
                            $subject_result = $check_subject->get_result();
                            
                            if ($subject_result->num_rows > 0) {
                                $subject_row = $subject_result->fetch_assoc();
                                $subject_id = $subject_row['id'];
                            } else {
                                $create_subject = $conn->prepare("INSERT INTO subjects (name, code, status) VALUES (?, ?, 1)");
                                $create_subject->bind_param("ss", $new_subject_name, $new_subject_code);
                                if ($create_subject->execute()) {
                                    $subject_id = $conn->insert_id;
                                } else {
                                    $error_message = 'Error creating subject: ' . $conn->error;
                                }
                                $create_subject->close();
                            }
                            $check_subject->close();
                        } else if ($subject_id_input !== 'new') {
                            $subject_id = intval($subject_id_input);
                        }
                        
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
                    }
                    
                    // Handle teacher-specific data
                    if ($role === 'teacher') {
                        // Save education details
                        if (isset($_POST['education']) && is_array($_POST['education'])) {
                            $edu_stmt = $conn->prepare("INSERT INTO teacher_education (teacher_id, qualification, institution, year_obtained, field_of_study, grade_or_class) VALUES (?, ?, ?, ?, ?, ?)");
                            foreach ($_POST['education'] as $edu) {
                                if (!empty($edu['qualification'])) {
                                    $institution = $edu['institution'] ?? '';
                                    $year = !empty($edu['year_obtained']) ? intval($edu['year_obtained']) : null;
                                    $field = $edu['field_of_study'] ?? '';
                                    $grade = $edu['grade_or_class'] ?? '';
                                    
                                    $edu_stmt->bind_param("sssiss", $user_id, $edu['qualification'], $institution, $year, $field, $grade);
                                    $edu_stmt->execute();
                                }
                            }
                            $edu_stmt->close();
                        }
                        
                        // Save teacher assignments (stream-subject combinations)
                        $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');
                        $teacher_subjects = isset($_POST['teacher_subjects']) ? $_POST['teacher_subjects'] : []; // Define if not already defined
                        
                        if (!empty($teacher_subjects) && is_array($teacher_subjects)) {
                            $assign_stmt = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, status, assigned_date) VALUES (?, ?, ?, 'active', CURDATE())");
                            
                            foreach ($teacher_subjects as $stream_subject_id) {
                            $stream_subject_id = intval($stream_subject_id);
                            if ($stream_subject_id > 0) {
                                $assign_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
                                $assign_stmt->execute();
                            }
                        }
                        $assign_stmt->close();
                        }
                    }
                    
                    if (empty($error_message)) {
                        $success_message = "User has been successfully created with User ID: $user_id";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>
    
    <div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center space-x-2">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        <span>Add New User</span>
                    </h1>
                    <a href="users.php" class="text-red-600 hover:text-red-700 font-medium flex items-center space-x-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        <span>Back to Users</span>
                    </a>
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

                <form method="POST" action="" class="space-y-6" id="addUserForm" enctype="multipart/form-data">
                    <!-- STEP 1: Role Selection (First) -->
                    <div class="bg-white border-2 border-gray-300 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-gray-900 mb-4">Step 1: Select User Role</h3>
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                            <select id="role" name="role" required
                                    class="w-full px-4 py-3 text-md border-2 border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                    onchange="updateUserIdPreview(); toggleRoleBasedFields();">
                                <option value="student" <?php echo (($_POST['role'] ?? 'student') === 'student') ? 'selected' : ''; ?>>Student</option>
                                <option value="teacher" <?php echo (($_POST['role'] ?? '') === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                                <option value="instructor" <?php echo (($_POST['role'] ?? '') === 'instructor') ? 'selected' : ''; ?>>Instructor</option>
                                <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>


                    <!-- Auto-Generated User ID Display (Red & White Theme) -->
                    <div class="bg-white border-2 border-red-500 rounded-lg shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-3 flex items-center">
                            <svg class="h-5 w-5 text-white mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                            </svg>
                            <span class="text-white font-semibold text-md uppercase tracking-wide">User ID</span>
                        </div>
                        <div class="px-6 py-5 flex items-center justify-between">
                            <div class="flex-1">
                               
                                <p class="text-lg font-bold text-red-600 tracking-tight" id="displayUserId">
                                    <?php 
                                        // Calculate default (student) next ID for initial display
                                        $default_prefix = 'stu';
                                        $stmt_preview = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
                                        $pattern_preview = $default_prefix . '_%';
                                        $stmt_preview->bind_param("s", $pattern_preview);
                                        $stmt_preview->execute();
                                        $result_preview = $stmt_preview->get_result();
                                        
                                        $next_preview = 1000;
                                        if ($result_preview->num_rows > 0) {
                                            $last_preview = $result_preview->fetch_assoc();
                                            $last_num_preview = intval(substr($last_preview['user_id'], strlen($default_prefix) + 1));
                                            $next_preview = max($last_num_preview + 1, 1000);
                                        }
                                        $stmt_preview->close();
                                        
                                        echo $default_prefix . '_' . str_pad($next_preview, 4, '0', STR_PAD_LEFT);
                                    ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <span id="roleHint" class="font-medium text-gray-600">Student</span> · 
                                    <span id="rolePattern" class="text-gray-500">Format: stu_XXXX</span>
                                </p>
                            </div>
                            <div id="loadingSpinner" class="hidden ml-4">
                                <svg class="animate-spin h-8 w-8 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        // Function to update user ID preview based on selected role
                        async function updateUserIdPreview() {
                            const roleSelect = document.getElementById('role');
                            const role = roleSelect.value;
                            const displayElem = document.getElementById('displayUserId');
                            const hintElem = document.getElementById('roleHint');
                            const patternElem = document.getElementById('rolePattern');
                            const spinner = document.getElementById('loadingSpinner');
                            
                            // Role metadata
                            const roleData = {
                                'student': { name: 'Student', prefix: 'stu' },
                                'teacher': { name: 'Teacher', prefix: 'tea' },
                                'instructor': { name: 'Instructor', prefix: 'ins' },
                                'admin': { name: 'Admin', prefix: 'adm' }
                            };
                            
                            const currentRole = roleData[role];
                            hintElem.textContent = currentRole.name;
                            patternElem.textContent = currentRole.prefix + '_XXXX';
                            
                            // Show loading
                            spinner.classList.remove('hidden');
                            displayElem.classList.add('opacity-50');
                            
                            try {
                                // Fetch next user_id from server
                                const response = await fetch('get_next_user_id.php?role=' + role);
                                const data = await response.json();
                                
                                if (data.success) {
                                    displayElem.textContent = data.user_id;
                                } else {
                                    // Fallback to default format
                                    displayElem.textContent = currentRole.prefix + '_1000';
                                }
                            } catch (error) {
                                console.error('Error fetching user ID:', error);
                                displayElem.textContent = currentRole.prefix + '_1000';
                            } finally {
                                spinner.classList.add('hidden');
                                displayElem.classList.remove('opacity-50');
                            }
                        }
                        
                        // Initialize on page load
                        document.addEventListener('DOMContentLoaded', function() {
                            // Update if role is pre-selected (e.g., after form error)
                            const role = document.getElementById('role').value;
                            if (role && role !== 'student') {
                                updateUserIdPreview();
                            }
                        });
                    </script>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                            <input type="password" id="password" name="password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   placeholder="Default: 1234">
                        </div>

                        <!-- Email -->

                        <!-- First Name -->
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" id="first_name" name="first_name"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>

                        <!-- Second Name -->
                        <div>
                            <label for="second_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" id="second_name" name="second_name"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                        </div>

                        <!-- Mobile Number -->
                        <div>
                            <label for="mobile_number" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                            <input type="text" id="mobile_number" name="mobile_number"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                        </div>

                        <!-- WhatsApp Number -->
                        <div>
                            <label for="whatsapp_number" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                            <input type="text" id="whatsapp_number" name="whatsapp_number"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                        </div>

                        <!-- Profile Picture -->
                        <div class="md:col-span-2">
                            <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-1">
                                Profile Picture 
                                <span id="photoRequired" class="text-red-600 hidden">*</span>
                                <span class="text-gray-500 text-xs">(Max 5MB, JPG/PNG/GIF/WEBP)</span>
                            </label>
                            <div class="flex items-center space-x-4">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100"
                                       onchange="previewProfilePicture(this)">
                                <div id="photoPreview" class="hidden">
                                    <img id="previewImg" src="" alt="Preview" class="w-20 h-20 rounded-full object-cover border-2 border-gray-300">
                                </div>
                            </div>
                            <p id="photoError" class="text-red-600 text-sm mt-1 hidden"></p>
                        </div>
                    </div>

                    <!-- Student-specific fields -->
                    <div id="studentFields" class="hidden space-y-6 border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Student Enrollment</h3>
                        
                        <!-- Academic Year -->
                        <div>
                            <label for="student_academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year *</label>
                            <input type="number" id="student_academic_year" name="academic_year" 
                                   value="<?php echo date('Y'); ?>" min="2020" max="2100"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   required>
                        </div>
                        
                        <!-- Stream Dropdown -->
                        <div>
                            <label for="stream_id" class="block text-sm font-medium text-gray-700 mb-1">Select Stream (Grade) *</label>
                            <select id="stream_id" name="stream_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                    onchange="handleStreamChange()">
                                <option value="">-- Select Stream --</option>
                                <option value="new">+ Create New Stream</option>
                                <?php foreach ($streams as $stream): ?>
                                    <option value="<?php echo $stream['id']; ?>"><?php echo htmlspecialchars($stream['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="newStreamContainer" class="hidden mt-2">
                                <input type="text" id="new_stream_name" name="new_stream_name" 
                                       placeholder="Enter new stream name"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                            </div>
                        </div>

                        <!-- Subject Dropdown -->
                        <div id="subjectContainer" class="hidden">
                            <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Select Subject *</label>
                            <select id="subject_id" name="subject_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                    onchange="handleSubjectChange()">
                                <option value="">-- Select Subject --</option>
                                <option value="new">+ Create New Subject</option>
                            </select>
                            <div id="newSubjectContainer" class="hidden mt-2 space-y-2">
                                <input type="text" id="new_subject_name" name="new_subject_name" 
                                       placeholder="Enter new subject name"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                                <input type="text" id="new_subject_code" name="new_subject_code" 
                                       placeholder="Enter subject code (optional)"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                            </div>
                        </div>

                        <!-- Teachers Grid -->
                        <div id="teachersContainer" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-4">Select Teacher *</label>
                            <div id="teachersGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <!-- Teachers will be loaded here -->
                            </div>
                            <input type="hidden" id="selected_teacher_id" name="selected_teacher_id" value="">
                        </div>
                    </div>

                    <!-- Teacher-specific fields -->
                    <div id="teacherFields" class="hidden space-y-6 border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Teacher Details & Assignments</h3>
                        
                        <!-- Academic Year -->
                        <div>
                            <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year *</label>
                            <input type="number" id="academic_year" name="academic_year" 
                                   value="<?php echo date('Y'); ?>" min="2020" max="2100"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   required>
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
                                <label class="block text-sm font-medium text-gray-700">Select Stream(s)</label>
                                <button type="button" onclick="openCreateStreamModal()" 
                                        class="text-sm bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <span>Create New Stream</span>
                                </button>
                            </div>
                            <div id="teacherStreamsGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
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
                                <label class="block text-sm font-medium text-gray-700">Select Subject(s)</label>
                                <button type="button" onclick="openCreateSubjectModal()" 
                                        class="text-sm bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <span>Create New Subject</span>
                                </button>
                            </div>
                            <div id="teacherSubjectsGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                <!-- Subjects will be loaded here based on selected streams -->
                            </div>
                        </div>
                    </div>

                    <!-- Approved Checkbox -->
                    <div class="flex items-center">
                        <input type="checkbox" id="approved" name="approved" value="1"
                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                               <?php echo (isset($_POST['approved']) || !isset($_POST['add_user'])) ? 'checked' : ''; ?>>
                        <label for="approved" class="ml-2 block text-sm text-gray-700">
                            Approve user immediately (user can login without approval)
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <a href="dashboard.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" name="add_user"
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 font-medium">
                            Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Stream Modal -->
    <div id="createStreamModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" onclick="closeStreamModal(event)">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-lg bg-white" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create New Stream
                </h3>
                <button onclick="closeStreamModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="createStreamForm" onsubmit="return submitCreateStream(event)" class="space-y-4">
                <div>
                    <label for="newStreamName" class="block text-sm font-medium text-gray-700 mb-2">
                        Stream Name *
                    </label>
                    <input type="text" id="newStreamName" name="stream_name" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                           placeholder="e.g., Grade 10, A/L Science">
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeStreamModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 font-medium transition">
                        <span id="streamSubmitText">Create Stream</span>
                        <span id="streamLoadingText" class="hidden">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Subject Modal -->
    <div id="createSubjectModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" onclick="closeSubjectModal(event)">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-lg bg-white" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create New Subject
                </h3>
                <button onclick="closeSubjectModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="createSubjectForm" onsubmit="return submitCreateSubject(event)" class="space-y-4">
                <div>
                    <label for="newSubjectName" class="block text-sm font-medium text-gray-700 mb-2">
                        Subject Name *
                    </label>
                    <input type="text" id="newSubjectName" name="subject_name" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                           placeholder="e.g., Mathematics, Physics">
                </div>
                
                <div>
                    <label for="newSubjectCode" class="block text-sm font-medium text-gray-700 mb-2">
                        Subject Code <span class="text-gray-400 text-xs">(Optional)</span>
                    </label>
                    <input type="text" id="newSubjectCode" name="subject_code"
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                           placeholder="e.g., MATH01, PHY01">
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeSubjectModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 font-medium transition">
                        <span id="subjectSubmitText">Create Subject</span>
                        <span id="subjectLoadingText" class="hidden">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle role-based fields
        function toggleRoleBasedFields() {
            const role = document.getElementById('role').value;
            const studentFields = document.getElementById('studentFields');
            const teacherFields = document.getElementById('teacherFields');
            
            if (role === 'student') {
                studentFields.classList.remove('hidden');
                teacherFields.classList.add('hidden');
            } else if (role === 'teacher') {
                studentFields.classList.add('hidden');
                teacherFields.classList.remove('hidden');
                // Clear student fields
                document.getElementById('subjectContainer').classList.add('hidden');
                document.getElementById('teachersContainer').classList.add('hidden');
                document.getElementById('newStreamContainer').classList.add('hidden');
                document.getElementById('newSubjectContainer').classList.add('hidden');
                document.getElementById('stream_id').value = '';
                document.getElementById('subject_id').value = '';
                document.getElementById('selected_teacher_id').value = '';
                document.getElementById('new_stream_name').value = '';
                document.getElementById('new_subject_name').value = '';
                document.getElementById('new_subject_code').value = '';
            } else {
                studentFields.classList.add('hidden');
                teacherFields.classList.add('hidden');
                // Clear all fields
                document.getElementById('subjectContainer').classList.add('hidden');
                document.getElementById('teachersContainer').classList.add('hidden');
                document.getElementById('newStreamContainer').classList.add('hidden');
                document.getElementById('newSubjectContainer').classList.add('hidden');
                document.getElementById('stream_id').value = '';
                document.getElementById('subject_id').value = '';
                document.getElementById('selected_teacher_id').value = '';
                document.getElementById('new_stream_name').value = '';
                document.getElementById('new_subject_name').value = '';
                document.getElementById('new_subject_code').value = '';
                // Clear teacher fields
                document.querySelectorAll('.teacher-stream-checkbox').forEach(cb => cb.checked = false);
                document.getElementById('teacherSubjectContainer').classList.add('hidden');
                document.getElementById('educationContainer').innerHTML = '';
            }
        }

        // Add education field
        let educationCount = 0;
        function addEducationField() {
            educationCount++;
            const container = document.getElementById('educationContainer');
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
            button.closest('.education-field').remove();
        }

        // Load subjects for selected streams (for teachers)
        async function loadTeacherSubjects() {
            const selectedStreams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);
            const subjectContainer = document.getElementById('teacherSubjectContainer');
            const subjectsGrid = document.getElementById('teacherSubjectsGrid');
            
            if (selectedStreams.length === 0) {
                subjectContainer.classList.add('hidden');
                subjectsGrid.innerHTML = '';
                return;
            }

            // Always show container when streams are selected
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
                        streamNames[streamId] = checkbox.closest('label').textContent.trim();
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
                // Show message when no subjects exist
                const noSubjectsMessage = document.createElement('div');
                noSubjectsMessage.className = 'col-span-full text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300';
                noSubjectsMessage.innerHTML = `
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <p class="text-gray-600 font-medium mb-2">No subjects available for selected stream(s)</p>
                    <p class="text-sm text-gray-500">Click "Create New Subject" above to add subjects</p>
                `;
                subjectsGrid.appendChild(noSubjectsMessage);
            }
            
            // Always keep container visible when streams are selected
            subjectContainer.classList.remove('hidden');
        }

        // Handle stream change
        function handleStreamChange() {
            const streamSelect = document.getElementById('stream_id');
            const streamId = streamSelect.value;
            const newStreamContainer = document.getElementById('newStreamContainer');
            const subjectContainer = document.getElementById('subjectContainer');
            const newSubjectContainer = document.getElementById('newSubjectContainer');
            const teachersContainer = document.getElementById('teachersContainer');
            
            // Check if "Create New Stream" was selected
            if (streamId === 'new') {
                // Reset selection to empty/default to avoid sticking on "new"
                streamSelect.value = "";
                
                // Open the modal
                openCreateStreamModal();
                return;
            }
            
            // Hide inline input if it was shown (though we're using modals now)
            newStreamContainer.classList.add('hidden');
            
            if (streamId) {
                document.getElementById('new_stream_name').value = '';
                loadSubjects();
            } else {
                subjectContainer.classList.add('hidden');
                teachersContainer.classList.add('hidden');
                newSubjectContainer.classList.add('hidden');
            }
        }

        // Load subjects based on selected stream
        function loadSubjects() {
            const streamId = document.getElementById('stream_id').value;
            const subjectContainer = document.getElementById('subjectContainer');
            const subjectSelect = document.getElementById('subject_id');
            const teachersContainer = document.getElementById('teachersContainer');
            const newSubjectContainer = document.getElementById('newSubjectContainer');
            
            if (!streamId || streamId === 'new') {
                subjectContainer.classList.add('hidden');
                teachersContainer.classList.add('hidden');
                return;
            }

            // Fetch subjects via AJAX
            fetch(`get_subjects.php?stream_id=${streamId}`)
                .then(response => response.json())
                .then(data => {
                    subjectSelect.innerHTML = '<option value="">-- Select Subject --</option><option value="new">+ Create New Subject</option>';
                    
                    if (data.success && data.subjects.length > 0) {
                        data.subjects.forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject.id;
                            option.textContent = subject.name;
                            subjectSelect.appendChild(option);
                        });
                        subjectContainer.classList.remove('hidden');
                    } else {
                        subjectContainer.classList.remove('hidden');
                    }
                    
                    newSubjectContainer.classList.add('hidden');
                    teachersContainer.classList.add('hidden');
                    document.getElementById('selected_teacher_id').value = '';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading subjects.');
                });
        }

        // Handle subject change
        function handleSubjectChange() {
            const subjectSelect = document.getElementById('subject_id');
            const subjectId = subjectSelect.value;
            const newSubjectContainer = document.getElementById('newSubjectContainer');
            const teachersContainer = document.getElementById('teachersContainer');
            
            // Check if "Create New Subject" was selected
            if (subjectId === 'new') {
                // Reset selection
                subjectSelect.value = "";
                
                // Open the modal
                openCreateSubjectModal();
                return;
            }
            
            // Hide inline input
            newSubjectContainer.classList.add('hidden');
            
            if (subjectId) {
                document.getElementById('new_subject_name').value = '';
                document.getElementById('new_subject_code').value = '';
                loadTeachers();
            } else {
                teachersContainer.classList.add('hidden');
                document.getElementById('selected_teacher_id').value = '';
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
                     <img src="../${teacher.profile_picture}" alt="Profile" class="w-32 h-32 rounded-full object-cover border-4 border-red-200 shadow-lg">
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
            
            // Academic year - left aligned at bottom
            const academicYearHTML = teacher.academic_year 
                ? `<div class="text-left mt-auto pt-4 border-t border-gray-200">
                     <div class="flex items-center">
                       <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                       </svg>
                       <span class="text-gray-700 font-medium">Academic Year: ${teacher.academic_year}</span>
                     </div>
                   </div>`
                : '';
            
            card.innerHTML = `
                ${profilePic}
                ${nameHTML}
                ${whatsappHTML}
                ${educationHTML}
                ${academicYearHTML}
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

        // Update photo requirement based on role
        // Update photo requirement based on role
        function updatePhotoRequirement() {
            const role = document.getElementById('role').value;
            const photoInput = document.getElementById('profile_picture');
            const photoRequired = document.getElementById('photoRequired');
            
            // Photo is now optional for everyone
            photoInput.removeAttribute('required');
            photoRequired.classList.add('hidden');
        }

        // Create New Stream Modal Functions
        function openCreateStreamModal() {
            document.getElementById('createStreamModal').classList.remove('hidden');
            document.getElementById('newStreamName').value = '';
            document.getElementById('newStreamName').focus();
        }
        
        function closeStreamModal(event) {
            if (!event || event.target.id === 'createStreamModal' || event.type === 'click') {
                document.getElementById('createStreamModal').classList.add('hidden');
            }
        }
        
        // Helper to get selected stream ID (works for both student dropdown and teacher checkboxes)
        function getSelectedStreamId() {
            // Check dropdown first (Student flow)
            const streamSelect = document.getElementById('stream_id');
            if (streamSelect && streamSelect.offsetParent !== null && streamSelect.value && streamSelect.value !== 'new') {
                return streamSelect.value;
            }
            
            // Check checkboxes (Teacher flow)
            const selectedCheckbox = document.querySelector('.teacher-stream-checkbox:checked');
            if (selectedCheckbox) {
                return selectedCheckbox.value;
            }
            
            return null;
        }

        // Create New Stream Modal Functions
        function openCreateStreamModal() {
            document.getElementById('createStreamModal').classList.remove('hidden');
            document.getElementById('newStreamName').value = '';
            document.getElementById('newStreamName').focus();
        }
        
        function closeStreamModal(event) {
            if (!event || event.target.id === 'createStreamModal' || event.type === 'click') {
                document.getElementById('createStreamModal').classList.add('hidden');
            }
        }
        
        function submitCreateStream(event) {
            event.preventDefault();
            
            const name = document.getElementById('newStreamName').value.trim();
            if (!name) {
                alert('Please enter a stream name');
                return false;
            }
            
            // Show loading state
            document.getElementById('streamSubmitText').classList.add('hidden');
            document.getElementById('streamLoadingText').classList.remove('hidden');
            
            const formData = new FormData();
            formData.append('name', name);
            
            fetch('../create_stream.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update Teacher Grid if it exists
                    const streamsGrid = document.getElementById('teacherStreamsGrid');
                    if (streamsGrid) {
                        const newStreamCheckbox = document.createElement('label');
                        newStreamCheckbox.className = 'flex items-center space-x-2 p-3 border border-gray-300 rounded-md hover:bg-red-50 cursor-pointer';
                        newStreamCheckbox.innerHTML = `
                            <input type="checkbox" name="teacher_streams[]" value="${data.stream_id}" 
                                   class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded teacher-stream-checkbox"
                                   onchange="loadTeacherSubjects()" checked>
                            <span class="text-sm text-gray-700">${name}</span>
                        `;
                        streamsGrid.appendChild(newStreamCheckbox);
                        
                        // Load subjects if teacher flow
                        if (typeof loadTeacherSubjects === 'function') {
                            loadTeacherSubjects();
                        }
                    }
                    
                    // Update Student Dropdown if it exists
                    const streamSelect = document.getElementById('stream_id');
                    if (streamSelect) {
                        const option = document.createElement('option');
                        option.value = data.stream_id;
                        option.textContent = name;
                        option.selected = true;
                        
                        // Insert before the "Create New" option which is likely the second option (index 1) or append
                        // Generally we want it just in the list.
                        streamSelect.appendChild(option);
                        
                        // Trigger change to load subjects
                        if (typeof loadSubjects === 'function') {
                            loadSubjects();
                        }
                    }
                    
                    // Close modal
                    closeStreamModal();
                    
                    // Show success message
                    showToast('Stream created successfully!', 'success');
                } else {
                    console.error('Error: ' + (data.message || 'Failed to create stream'));
                }
                
                // Reset loading state
                document.getElementById('streamSubmitText').classList.remove('hidden');
                document.getElementById('streamLoadingText').classList.add('hidden');
            })
            .catch(error => {
                console.error('Error:', error);
                // Reset loading state
                document.getElementById('streamSubmitText').classList.remove('hidden');
                document.getElementById('streamLoadingText').classList.add('hidden');
            });
            
            return false;
        }

        // Create New Subject Modal Functions
        function openCreateSubjectModal() {
            const streamId = getSelectedStreamId();
            
            if (!streamId) {
                alert('Please select a stream before creating a subject.');
                return;
            }
            
            document.getElementById('createSubjectModal').classList.remove('hidden');
            document.getElementById('newSubjectName').value = '';
            document.getElementById('newSubjectCode').value = '';
            document.getElementById('newSubjectName').focus();
        }
        
        function closeSubjectModal(event) {
            if (!event || event.target.id === 'createSubjectModal' || event.type === 'click') {
                document.getElementById('createSubjectModal').classList.add('hidden');
            }
        }
        
        function submitCreateSubject(event) {
            event.preventDefault();
            
            const streamId = getSelectedStreamId();
            if (!streamId) {
                alert('Please select a stream first.');
                return false;
            }
            
            const name = document.getElementById('newSubjectName').value.trim();
            if (!name) {
                alert('Please enter a subject name');
                return false;
            }
            
            const code = document.getElementById('newSubjectCode').value.trim();
            
            // Show loading state
            document.getElementById('subjectSubmitText').classList.add('hidden');
            document.getElementById('subjectLoadingText').classList.remove('hidden');
            
            const formData = new FormData();
            formData.append('name', name);
            formData.append('code', code);
            formData.append('stream_id', streamId);
            
            fetch('../create_subject.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update Teacher Checklist
                    if (typeof loadTeacherSubjects === 'function') {
                        loadTeacherSubjects();
                    }
                    
                    // Update Student Dropdown
                    const subjectSelect = document.getElementById('subject_id');
                    if (subjectSelect) {
                        // We usually just reload the subjects for simplicity as fetching one is tricky
                        // But since we have the ID and Name, we can append it
                        const option = document.createElement('option');
                        option.value = data.subject_id || 'new_id'; // backend might not return id, checking..
                        // If backend doesn't return ID, we forced to reload.
                        // Assuming create_subject returns subject_id
                        
                        // Safest is to reload subjects
                        if (typeof loadSubjects === 'function') {
                            loadSubjects(); 
                        }
                    }

                    // Close modal
                    closeSubjectModal();
                    
                    // Show success message
                    showToast('Subject created successfully!', 'success');
                } else {
                    console.error('Error: ' + (data.message || 'Failed to create subject'));
                }
                
                // Reset loading state
                document.getElementById('subjectSubmitText').classList.remove('hidden');
                document.getElementById('subjectLoadingText').classList.add('hidden');
            })
            .catch(error => {
                console.error('Error:', error);
                // Reset loading state
                document.getElementById('subjectSubmitText').classList.remove('hidden');
                document.getElementById('subjectLoadingText').classList.add('hidden');
            });
            
            return false;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleBasedFields();
            updatePhotoRequirement();
            
            // Update photo requirement when role changes
            document.getElementById('role').addEventListener('change', function() {
                updatePhotoRequirement();
                toggleRoleBasedFields();
            });
        });
    </script>
</body>
</html>
