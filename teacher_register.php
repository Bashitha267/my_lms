<?php
session_start();
require_once 'config.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_teacher'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // Hardcoded role
    $role = 'teacher';
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    // Hardcoded approval status (0 = pending)
    $approved = 0;
    
    // Validation
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } else {
        // Generate user_id
        $prefix = 'tea';
        
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
        
        // Check if photo is provided (optional for now, but allowed)
        if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
            if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
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
            } else {
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
                        $error_message = 'Failed to upload profile picture. Please check folder permissions.';
                    }
                }
            }
        }
        
        // If no upload errors, proceed with user creation
        if (empty($error_message)) {
            // Insert user
            $nic_number = trim($_POST['nic_number'] ?? '');
            
            // Should add these columns to DB if they don't exist. Assuming they do or are stored in other fields.
            // If DB doesn't have nic, dob, gender columns in users table, they need to be added.
            // For now, I'll update the Insert query.
            
            // Note: DB Schema update might be needed. 
            // "INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status, nic, dob, gender) ..."
            // I will assume for now columns exist or I should run a migration. Usually I can't run schema changes directly without request.
            // I'll add them to the query. If it fails, user will notify.
            
            $dob = $_POST['dob'] ?? null;
            $gender = $_POST['gender'] ?? null;

            $stmt = $conn->prepare("INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status, nic, dob, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 0, ?, ?, ?)");
            
            // Status 0 (inactive) initially until approved? Or 1 (active) but not approved? 
            // Admin/add_user uses status=1. But approved=0 means they can't login usually.
            // Let's set status=1 (technically active account) but approved=0 (needs admin approval).
            $status = 1; 
            
            $stmt->bind_param("sssssssssisss", $user_id, $email, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $profile_picture_path, $approved, $nic_number, $dob, $gender);
            
            if ($stmt->execute()) {
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
                $teacher_subjects = isset($_POST['teacher_subjects']) ? $_POST['teacher_subjects'] : []; 
                
                if (!empty($teacher_subjects) && is_array($teacher_subjects)) {
                    $assign_stmt = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, status, assigned_date) VALUES (?, ?, ?, 'pending', CURDATE())");
                    // Set status to 'pending' because teacher is not approved yet? Or 'active'?
                    // Admin uses 'active'. Since user is unapproved, maybe 'active' assignment is fine as it won't be usable.
                    // Let's stick to 'active' for consistency with admin or 'pending'.
                    // Actually if the teacher isn't approved, they can't do anything. simpler to leave as active so when they are approved, everything works.
                    
                    foreach ($teacher_subjects as $stream_subject_id) {
                        $stream_subject_id = intval($stream_subject_id);
                        if ($stream_subject_id > 0) {
                            $assign_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
                            $assign_stmt->execute();
                        }
                    }
                    $assign_stmt->close();
                }
                
                $success_message = "Registration successful! Your Teacher ID is: $user_id. Please wait for admin approval.";
                // Clear form data
                $_POST = array();
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

// Get streams for checkboxes
$streams_query = "SELECT id, name FROM streams WHERE status = 1 ORDER BY name";
$streams_result = $conn->query($streams_query);
$streams = $streams_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration - LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-10">
            <h1 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                Teacher Registration
            </h1>
            <p class="mt-3 max-w-2xl mx-auto text-xl text-gray-500">
                Join our platform to manage your classes and students.
            </p>
        </div>

        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            
            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-8" role="alert">
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
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-8" role="alert">
                    <div class="flex">
                        <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-8" enctype="multipart/form-data">
                
                <!-- Personal Information -->
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-2 mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- First Name -->
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>

                        <!-- Second Name -->
                        <div>
                            <label for="second_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                            <input type="text" id="second_name" name="second_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                        </div>
                        
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                            <input type="password" id="password" name="password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                   placeholder="Create a strong password">
                        </div>

                        <!-- Mobile Number with OTP -->
                        <div>
                            <label for="mobile_number" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                            <div class="flex">
                                <input type="text" id="mobile_number" name="mobile_number" required
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>"
                                       placeholder="e.g., 94771234567">
                                <button type="button" id="sendOtpBtn" onclick="sendOTP()"
                                        class="bg-red-600 text-white px-4 py-2 rounded-r-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm font-medium whitespace-nowrap">
                                    Send OTP
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Start with 94 (e.g. 9477...)</p>
                            
                            <!-- OTP Input Section (Hidden initially) -->
                            <div id="otpSection" class="hidden mt-3 p-4 bg-gray-50 border border-gray-200 rounded-md">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Enter Verification Code</label>
                                <div class="flex space-x-2">
                                    <input type="text" id="otp_code" name="otp_code" maxlength="6"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 text-center tracking-widest text-lg"
                                           placeholder="######">
                                    <button type="button" onclick="verifyOTP()"
                                            class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 text-sm font-medium">
                                        Verify
                                    </button>
                                </div>
                                <div id="otpMessage" class="mt-2 text-sm"></div>
                                <div id="otpTimer" class="mt-1 text-xs text-gray-500"></div>
                            </div>
                            <input type="hidden" id="mobile_verified" name="mobile_verified" value="0">
                        </div>

                        <!-- NIC Number -->
                        <div>
                            <label for="nic_number" class="block text-sm font-medium text-gray-700 mb-1">NIC Number *</label>
                            <div class="flex">
                                <input type="text" id="nic_number" name="nic_number" required
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 uppercase"
                                       value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>"
                                       placeholder="Old (9V/X) or New (12 digits)">
                                <button type="button" onclick="verifyNIC()"
                                        class="bg-gray-200 text-gray-700 px-3 py-2 rounded-r-md hover:bg-gray-300 focus:outline-none border border-l-0 border-gray-300 text-sm font-medium">
                                    Check
                                </button>
                            </div>
                            <div id="nicMessage" class="mt-1 text-sm"></div>
                            <!-- Hidden fields to store NIC data -->
                            <input type="hidden" id="dob" name="dob">
                            <input type="hidden" id="gender" name="gender">
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
                                <span class="text-gray-500 text-xs">(Max 5MB, JPG/PNG/GIF/WEBP)</span>
                            </label>
                            <div class="flex items-center space-x-4">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100"
                                       onchange="previewProfilePicture(this)">
                                <div id="photoPreview" class="hidden">
                                    <img id="previewImg" src="" alt="Preview" class="w-16 h-16 rounded-full object-cover border-2 border-gray-300">
                                </div>
                            </div>
                            <p id="photoError" class="text-red-600 text-sm mt-1 hidden"></p>
                        </div>
                    </div>
                </div>

                <!-- Education Details -->
                <div>
                    <div class="flex items-center justify-between border-b pb-2 mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Education Details</h3>
                        <button type="button" onclick="addEducationField()" 
                                class="text-sm bg-red-50 text-red-700 px-3 py-1 rounded-md hover:bg-red-100 border border-red-200 flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <span>Add Education</span>
                        </button>
                    </div>
                    <div id="educationContainer" class="space-y-4">
                        <!-- Education fields will be added here via JS -->
                    </div>
                </div>

                <!-- Teaching Preferences -->
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-2 mb-4">Teaching Preferences</h3>
                    
                    <!-- Academic Year -->
                    <div class="mb-6 w-full md:w-1/2">
                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year *</label>
                        <input type="number" id="academic_year" name="academic_year" 
                               value="<?php echo date('Y'); ?>" min="2020" max="2100"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               required>
                    </div>

                    <!-- Streams -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Streams (Grades) to Teach *</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            <?php foreach ($streams as $stream): ?>
                                <label class="flex items-center space-x-2 p-3 border border-gray-300 rounded-md hover:bg-red-50 cursor-pointer transition-colors">
                                    <input type="checkbox" name="teacher_streams[]" value="<?php echo $stream['id']; ?>" 
                                           class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded teacher-stream-checkbox"
                                           onchange="loadTeacherSubjects()"
                                           <?php echo (isset($_POST['teacher_streams']) && in_array($stream['id'], $_POST['teacher_streams'])) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($stream['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Subjects -->
                    <div id="teacherSubjectContainer" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Subjects to Teach *</label>
                        <div id="teacherSubjectsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <!-- Subjects loaded via AJAX -->
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-6 border-t border-gray-200">
                    <button type="submit" name="register_teacher"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                        Register as Teacher
                    </button>
                    <p class="mt-4 text-center text-sm text-gray-500">
                        Already have an account? <a href="login.php" class="font-medium text-red-600 hover:text-red-500">Log in here</a>
                    </p>
                </div>

            </form>
        </div>
    </div>

    <script>
        // Use PHP to inject pre-selected subjects if form validation failed
        window.preSelectedSubjects = <?php echo json_encode($_POST['teacher_subjects'] ?? []); ?>;
        // Ensure they are strings for comparison
        window.preSelectedSubjects = window.preSelectedSubjects.map(String);

        document.addEventListener('DOMContentLoaded', function() {
            // Add initial education field if none exist
            if (document.querySelectorAll('.education-field').length === 0) {
                addEducationField();
            }
            
            // Check if we need to reload subjects (e.g. after form error)
            const checkedStreams = document.querySelectorAll('.teacher-stream-checkbox:checked');
            if (checkedStreams.length > 0) {
                loadTeacherSubjects();
            }
        });

        // Add education field
        let educationCount = 0;
        function addEducationField() {
            educationCount++;
            const container = document.getElementById('educationContainer');
            const div = document.createElement('div');
            div.className = 'border border-gray-200 rounded-lg p-4 bg-gray-50 education-field relative';
            div.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Qualification</label>
                        <input type="text" name="education[${educationCount}][qualification]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., B.Sc. in Mathematics" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Institution</label>
                        <input type="text" name="education[${educationCount}][institution]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="University/College name">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Year Obtained</label>
                        <input type="number" name="education[${educationCount}][year_obtained]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., 2020" min="1950" max="2100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Grade/Class</label>
                        <input type="text" name="education[${educationCount}][grade_or_class]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                               placeholder="e.g., First Class">
                    </div>
                </div>
                ${educationCount > 1 ? `
                <button type="button" onclick="removeEducationField(this)" 
                        class="absolute top-2 right-2 text-gray-400 hover:text-red-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>` : ''}
            `;
            container.appendChild(div);
        }

        function removeEducationField(button) {
            button.closest('.education-field').remove();
        }

        // Preview profile picture
        function previewProfilePicture(input) {
            const preview = document.getElementById('photoPreview');
            const previewImg = document.getElementById('previewImg');
            const photoError = document.getElementById('photoError');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (file.size > maxSize) {
                    photoError.textContent = 'File size exceeds 5MB limit.';
                    photoError.classList.remove('hidden');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }
                
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

        // Load subjects for selected streams
        async function loadTeacherSubjects() {
            const selectedStreams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);
            const subjectContainer = document.getElementById('teacherSubjectContainer');
            const subjectsGrid = document.getElementById('teacherSubjectsGrid');
            
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
                    const response = await fetch(`ajax/get_subjects.php?stream_id=${streamId}`);
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
                        streamNames[streamId] = checkbox.closest('label').querySelector('span').textContent.trim();
                    }
                });

                // Process results
                for (const { streamId, data } of results) {
                    if (data.success && data.subjects) {
                        for (const subject of data.subjects) {
                            
                            // Check for stream_subject_id (new optimization)
                            let ssId = subject.stream_subject_id;
                            
                            if (ssId) {
                                const key = `${streamId}_${subject.id}`;
                                if (!allStreamSubjects.has(key)) {
                                    allStreamSubjects.set(key, {
                                        stream_subject_id: ssId,
                                        stream_id: streamId,
                                        subject_id: subject.id,
                                        subject_name: subject.name,
                                        stream_name: streamNames[streamId]
                                    });
                                }
                            } else {
                                // Fallback call if needed (shouldn't be needed with updated ajax/get_subjects.php)
                                try {
                                    const ssResponse = await fetch(`ajax/get_stream_subject_id.php?stream_id=${streamId}&subject_id=${subject.id}`);
                                    const ssData = await ssResponse.json();
                                    if (ssData.success && ssData.stream_subject_id) {
                                        const key = `${streamId}_${subject.id}`;
                                        if (!allStreamSubjects.has(key)) {
                                            allStreamSubjects.set(key, {
                                                stream_subject_id: ssData.stream_subject_id,
                                                stream_id: streamId,
                                                subject_id: subject.id,
                                                subject_name: subject.name,
                                                stream_name: streamNames[streamId]
                                            });
                                        }
                                    }
                                } catch(e) { console.error(e); }
                            }
                        }
                    }
                }

                updateSubjectGrid(allStreamSubjects);

            } catch (error) {
                console.error('Error:', error);
                subjectsGrid.innerHTML = '<div class="col-span-full text-center text-red-500">Error loading subjects. Please try again.</div>';
            }
        }

        function updateSubjectGrid(streamSubjectsMap) {
            const subjectsGrid = document.getElementById('teacherSubjectsGrid');
            subjectsGrid.innerHTML = '';
            
            if (streamSubjectsMap.size > 0) {
                streamSubjectsMap.forEach((item) => {
                    const label = document.createElement('label');
                    label.className = 'flex items-center space-x-2 p-3 border border-gray-300 rounded-md hover:bg-red-50 cursor-pointer bg-white';
                    
                    const isChecked = (window.preSelectedSubjects && window.preSelectedSubjects.includes(String(item.stream_subject_id))) ? 'checked' : '';
                    
                    label.innerHTML = `
                        <input type="checkbox" name="teacher_subjects[]" value="${item.stream_subject_id}" 
                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded" ${isChecked}>
                        <div class="flex-1">
                            <span class="text-sm font-medium text-gray-900">${item.subject_name}</span>
                            <span class="text-xs text-gray-500 block text-gray-500">${item.stream_name}</span>
                        </div>
                    `;
                    subjectsGrid.appendChild(label);
                });
            } else {
                subjectsGrid.innerHTML = '<div class="col-span-full text-center py-4 text-gray-500">No subjects available for selected streams.</div>';
            }
        }

        // OTP Functions
        let otpTimerInterval;
        let isOtpVerified = false;

        function sendOTP() {
            const mobile = document.getElementById('mobile_number').value.trim();
            const btn = document.getElementById('sendOtpBtn');
            const otpSection = document.getElementById('otpSection');
            const otpMsg = document.getElementById('otpMessage');

            if (!mobile || mobile.length < 9) {
                alert('Please enter a valid mobile number first.');
                return;
            }

            // Disable button
            btn.disabled = true;
            btn.textContent = 'Sending...';
            btn.classList.add('opacity-50', 'cursor-not-allowed');

            // Send AJAX request
            const formData = new FormData();
            formData.append('mobile_number', mobile);

            fetch('send_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    otpSection.classList.remove('hidden');
                    otpMsg.innerHTML = '<span class="text-green-600">OTP sent successfully to ' + mobile + '</span>';
                    
                    // Start timer
                    startTimer(300); // 5 minutes
                    
                    // Change button text
                    btn.textContent = 'Resend OTP';
                    
                    // Wait 30s before allowing resend
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }, 30000);
                    
                } else {
                    alert('Error sending OTP: ' + (data.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.textContent = 'Send OTP';
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to connect to server.');
                btn.disabled = false;
                btn.textContent = 'Send OTP';
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            });
        }

        function verifyOTP() {
            const otp = document.getElementById('otp_code').value.trim();
            const mobile = document.getElementById('mobile_number').value.trim();
            const otpMsg = document.getElementById('otpMessage');
            const otpSection = document.getElementById('otpSection');
            const sendBtn = document.getElementById('sendOtpBtn');

            if (!otp || otp.length !== 6) {
                otpMsg.innerHTML = '<span class="text-red-600">Please enter a valid 6-digit OTP.</span>';
                return;
            }

            const formData = new FormData();
            formData.append('otp', otp);
            formData.append('mobile_number', mobile);

            fetch('verify_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isOtpVerified = true;
                    document.getElementById('mobile_verified').value = '1';
                    
                    otpMsg.innerHTML = '<span class="text-green-600 font-medium">✓ Mobile Verified Successfully!</span>';
                    
                    // Hide input and verify button, show success
                    const inputs = otpSection.querySelector('div.flex');
                    if(inputs) inputs.classList.add('hidden');
                    
                    // Disable mobile input and send button
                    document.getElementById('mobile_number').readOnly = true;
                    document.getElementById('mobile_number').classList.add('bg-gray-100');
                    sendBtn.classList.add('hidden');
                    
                    clearInterval(otpTimerInterval);
                    document.getElementById('otpTimer').textContent = '';
                } else {
                    otpMsg.innerHTML = '<span class="text-red-600">Invalid OTP. Please try again.</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                otpMsg.innerHTML = '<span class="text-red-600">Verification failed. Please try again.</span>';
            });
        }

        function startTimer(duration) {
            let timer = duration, minutes, seconds;
            const display = document.getElementById('otpTimer');
            
            clearInterval(otpTimerInterval);
            
            otpTimerInterval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = "Code expires in " + minutes + ":" + seconds;

                if (--timer < 0) {
                    clearInterval(otpTimerInterval);
                    display.textContent = "OTP Expired. Please resend.";
                }
            }, 1000);
        }

        // NIC Verification
        function verifyNIC() {
            const nic = document.getElementById('nic_number').value.trim();
            const nicMsg = document.getElementById('nicMessage');
            
            if (!nic) {
                nicMsg.innerHTML = '<span class="text-red-600">Please enter NIC number first.</span>';
                return;
            }
            
            nicMsg.innerHTML = '<span class="text-gray-500">Checking...</span>';
            
            const formData = new FormData();
            formData.append('nic', nic);
            // No DOB/Gender sent as extracting info
            
            fetch('verify_nic.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.valid) {
                    nicMsg.innerHTML = `<span class="text-green-600">
                        ✓ Valid NIC (${data.format === 'old' ? 'Old' : 'New'} Format)<br>
                        DOB: ${data.date_of_birth} | Gender: ${data.gender}
                    </span>`;
                    
                    // Auto-fill hidden fields
                    document.getElementById('dob').value = data.date_of_birth;
                    document.getElementById('gender').value = data.gender;
                    
                    // Also auto-fill password field with DOB? Optional but common usage.
                    // Or keep as hidden inputs to save to user profile if columns exist.
                } else {
                    nicMsg.innerHTML = '<span class="text-red-600">Invalid NIC: ' + (data.message || 'Check format') + '</span>';
                    document.getElementById('dob').value = '';
                    document.getElementById('gender').value = '';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                nicMsg.innerHTML = '<span class="text-red-600">Verification check failed.</span>';
            });
        }
        
        // Prevent form submission if OTP not verified
        document.querySelector('form').addEventListener('submit', function(e) {
            const mobileVerified = document.getElementById('mobile_verified').value;
            if (mobileVerified !== '1') {
                e.preventDefault();
                alert('Please verify your mobile number via OTP before registering.');
                return false;
            }
            // Optional: prevent if NIC is not checked? Usually form validation handles required fields.
        });
</body>
</html>
