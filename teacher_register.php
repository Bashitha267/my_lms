<?php
session_start();
require_once 'config.php';
require_once 'whatsapp_config.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_teacher'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = 'teacher';
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $approved = 0;
    
    // Validation
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } else {
        $prefix = 'tea';
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
        $pattern = $prefix . '_%';
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $next_num = 1000;
        if ($result->num_rows > 0) {
            $last_user = $result->fetch_assoc();
            $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
            $next_num = max($last_num + 1, 1000);
        }
        $stmt->close();
        
        $user_id = $prefix . '_' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $profile_picture_path = null;
        if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
            if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file = $_FILES['profile_picture'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_extensions) && $file['size'] <= 5 * 1024 * 1024) {
                    $new_filename = $user_id . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $profile_picture_path = 'uploads/profiles/' . $new_filename;
                    }
                }
            }
        }
        
        if (empty($error_message)) {
            $nic_number = trim($_POST['nic_number'] ?? '');
            $dob = $_POST['dob'] ?? null;
            $gender = $_POST['gender'] ?? null;
            $requested_rate = isset($_POST['requested_rate']) ? floatval($_POST['requested_rate']) : 75.00;
            $is_mentor = isset($_POST['is_mentor']) ? 1 : 0;
            $hourly_rate = isset($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : 0.00;

            $stmt = $conn->prepare("INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status, nic, dob, gender, commission_rate, requested_commission_rate, is_mentor, hourly_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, ?, ?, ?, ?, ?, ?, ?)");
            
            $default_approved_rate = 75.00;
            $stmt->bind_param("sssssssssisssdddid", $user_id, $email, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $profile_picture_path, $approved, $nic_number, $dob, $gender, $default_approved_rate, $requested_rate, $is_mentor, $hourly_rate);
            
            if ($stmt->execute()) {
                // Education
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
                
                // Assignments
                $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');
                $teacher_subjects = $_POST['teacher_subjects'] ?? []; 
                if (!empty($teacher_subjects) && is_array($teacher_subjects)) {
                    $assign_stmt = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, status, assigned_date) VALUES (?, ?, ?, 'pending', CURDATE())");
                    foreach ($teacher_subjects as $ss_id) {
                        $ss_id = intval($ss_id);
                        if ($ss_id > 0) {
                            $assign_stmt->bind_param("sii", $user_id, $ss_id, $academic_year);
                            $assign_stmt->execute();
                        }
                    }
                    $assign_stmt->close();
                }
                
                $success_message = "Registration successful! Your Teacher ID is: $user_id. Please wait for admin approval.";
                $_POST = array();
            } else {
                $error_message = ($conn->errno == 1062) ? 'Email or User ID already exists.' : 'Error creating user: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

$streams_query = "SELECT id, name FROM streams WHERE status = 1 ORDER BY name";
$streams = $conn->query($streams_query)->fetch_all(MYSQLI_ASSOC);

// Pre-calculate next User ID for display
$prefix_display = 'tea';
$stmt_display = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
$pattern_display = $prefix_display . '_%';
$stmt_display->bind_param("s", $pattern_display);
$stmt_display->execute();
$result_display = $stmt_display->get_result();
$next_num_display = 1000;
if ($result_display->num_rows > 0) {
    $last_user = $result_display->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], strlen($prefix_display) + 1));
    $next_num_display = max($last_num + 1, 1000);
}
$stmt_display->close();
$display_user_id = $prefix_display . '_' . str_pad($next_num_display, 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration | LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .form-card { background: white; border: 1px solid #dadce0; border-radius: 8px; padding: 24px; margin-bottom: 12px; }
        .form-card:first-of-type { border-top: 10px solid #dc2626; }
        .input-group { border-bottom: 2px solid #e0e0e0; transition: border-color 0.2s; }
        .input-group:focus-within { border-bottom-color: #dc2626; }
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="date"], select {
            width: 100%; border: none; padding: 12px 0; background: transparent; font-size: 1rem; outline: none;
        }
        label { display: block; font-size: 0.875rem; font-weight: 500; color: #202124; margin-bottom: 4px; }
        .btn-primary { background-color: #dc2626; color: white; padding: 10px 24px; border-radius: 4px; font-weight: 500; }
        .btn-primary:hover { background-color: #b91c1c; }
    </style>
</head>
<body class="py-8 px-4">
    <div class="max-w-3xl mx-auto">
        <!-- Header Card -->
        <div class="form-card">
            <div class="flex justify-between items-start mb-4">
                <h1 class="text-3xl font-normal text-[#202124]">Teacher Registration</h1>
                <div class="bg-red-50 text-red-700 px-3 py-1 rounded text-xs font-bold border border-red-100 uppercase">
                    ID: <?php echo $display_user_id; ?>
                </div>
            </div>
            <p class="text-[#202124] text-sm">Join LearnerX. Please fill in all required information accurately.</p>
            <div class="mt-4 pt-4 border-t border-gray-100">
                <span class="text-red-600 text-sm font-medium">* Required</span>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <!-- Basic Info -->
            <div class="form-card">
                <h2 class="text-lg font-medium text-[#202124] mb-6">Personal Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="input-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    <div class="input-group">
                        <label>Last Name *</label>
                        <input type="text" name="second_name" required value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                    </div>
                    <div class="input-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="input-group">
                        <label>Password *</label>
                        <input type="password" name="password" required>
                    </div>
                </div>
            </div>

            <!-- Verification -->
            <div class="form-card">
                <h2 class="text-lg font-medium text-[#202124] mb-6">Contact & Verification</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="input-group">
                        <label>Mobile Number *</label>
                        <div class="flex">
                            <input type="text" name="mobile_number" id="mobile_number" required value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>" placeholder="947xxxxxxxx">
                            <button type="button" onclick="sendOTP()" id="sendOtpBtn" class="bg-gray-100 text-gray-700 px-3 text-xs font-bold uppercase transition hover:bg-gray-200">Send OTP</button>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>NIC Number *</label>
                        <div class="flex">
                            <input type="text" name="nic_number" id="nic_number" required value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>" placeholder="123456789V" class="uppercase">
                            <button type="button" onclick="verifyNIC()" class="bg-gray-100 text-gray-700 px-3 text-xs font-bold uppercase transition hover:bg-gray-200">Check</button>
                        </div>
                    </div>

                    <div id="otpSection" class="hidden col-span-full pt-4">
                        <div class="bg-gray-50 p-4 rounded border border-gray-200">
                            <label class="mb-2">Enter 6-digit OTP</label>
                            <div class="flex gap-2">
                                <input type="text" id="otp_code" maxlength="6" class="flex-1 bg-white border border-gray-300 rounded px-4 py-2 text-center text-lg tracking-widest outline-none focus:border-red-600">
                                <button type="button" onclick="verifyOTP()" class="bg-emerald-600 text-white px-6 rounded font-bold">Verify</button>
                            </div>
                            <p id="otpMessage" class="mt-2 text-xs font-bold"></p>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                    </div>
                    
                    <div class="input-group pt-4">
                        <label>Profile Picture</label>
                        <input type="file" name="profile_picture" class="text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                    </div>
                    <input type="hidden" id="dob" name="dob">
                    <input type="hidden" id="gender" name="gender">
                    <input type="hidden" id="mobile_verified" name="mobile_verified" value="0">
                </div>
            </div>

            <!-- Education -->
            <div class="form-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-medium text-[#202124]">Academic Background</h2>
                    <button type="button" onclick="addEducationField()" class="text-xs bg-red-50 text-red-700 px-3 py-1 rounded font-bold border border-red-100">+ AD EDUC</button>
                </div>
                <div id="educationContainer" class="space-y-4">
                    <!-- Loaded via JS -->
                </div>
            </div>

            <!-- Teaching -->
            <div class="form-card">
                <h2 class="text-lg font-medium text-[#202124] mb-6">Teaching Preferences</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="input-group">
                        <label>Academic Year *</label>
                        <input type="number" name="academic_year" value="<?php echo date('Y'); ?>">
                    </div>
                    <div class="input-group">
                        <label>Requested Commission (%)</label>
                        <input type="number" name="requested_rate" value="75" min="1" max="100" class="font-bold">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="mb-4">Select Streams (Grades) *</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <?php foreach ($streams as $stream): ?>
                            <label class="flex items-center space-x-2 border rounded p-3 hover:bg-gray-50 cursor-pointer transition">
                                <input type="checkbox" name="teacher_streams[]" value="<?php echo $stream['id']; ?>" class="teacher-stream-checkbox" onchange="loadTeacherSubjects()">
                                <span class="text-sm"><?php echo htmlspecialchars($stream['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="teacherSubjectContainer" class="hidden mb-4 p-4 bg-gray-50 rounded border border-gray-100">
                    <label class="mb-4 block">Select Subjects *</label>
                    <div id="teacherSubjectsGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <!-- AJAX -->
                    </div>
                </div>
            </div>

            <!-- Instructor Option -->
            <div class="form-card bg-slate-900 border-none rounded-xl text-white">
                <label class="flex items-center gap-4 cursor-pointer">
                    <div class="relative">
                        <input type="checkbox" name="is_mentor" id="is_mentor" value="1" class="sr-only peer" onchange="toggleMentorFields()">
                        <div class="w-12 h-6 bg-slate-700 rounded-full peer-checked:bg-emerald-500 transition-all"></div>
                        <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full peer-checked:translate-x-6 transition-all"></div>
                    </div>
                    <div>
                        <p class="font-bold text-base leading-none">Apply as Instructor/Mentor?</p>
                        <p class="text-slate-400 text-xs mt-1">Earn more with 1-on-1 sessions.</p>
                    </div>
                </label>

                <div id="mentor_fields" class="hidden mt-6 pt-6 border-t border-slate-800 animate-in">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-end">
                        <div class="input-group" style="border-bottom-color: #334155;">
                            <label class="text-slate-400">Hourly Rate (LKR)</label>
                            <input type="number" name="hourly_rate" placeholder="3500" class="text-white !font-bold text-xl">
                        </div>
                        <p class="text-slate-500 italic text-xs">Students will pay this rate for 1-hour sessions.</p>
                    </div>
                </div>
            </div>

            <button type="submit" name="register_teacher" class="w-full btn-primary py-4 text-lg font-bold shadow-lg transition transform active:scale-95">
                Register as Teacher
            </button>
            <p class="text-center text-sm text-gray-500 mt-4">
                Already have an account? <a href="login.php" class="text-red-600 font-bold hover:underline">Log in here</a>
            </p>
        </form>
    </div>

    <script>
        window.preSelectedSubjects = <?php echo json_encode($_POST['teacher_subjects'] ?? []); ?>;
        window.preSelectedSubjects = window.preSelectedSubjects.map(String);

        document.addEventListener('DOMContentLoaded', () => {
            if (document.querySelectorAll('.education-field').length === 0) addEducationField();
        });

        let eduCount = 0;
        function addEducationField() {
            eduCount++;
            const container = document.getElementById('educationContainer');
            const div = document.createElement('div');
            div.className = 'p-4 border border-gray-200 rounded-lg bg-gray-50 education-field relative mb-4';
            div.innerHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="input-group"><label>Qualification</label><input type="text" name="education[${eduCount}][qualification]" required placeholder="B.Sc Science"></div>
                    <div class="input-group"><label>Institution</label><input type="text" name="education[${eduCount}][institution]" placeholder="University"></div>
                    <div class="input-group"><label>Year</label><input type="number" name="education[${eduCount}][year_obtained]" placeholder="2022"></div>
                    <div class="input-group"><label>Grade / Class</label><input type="text" name="education[${eduCount}][grade_or_class]" placeholder="First Class"></div>
                </div>
                ${eduCount > 1 ? '<button type="button" onclick="this.closest(\'div\').remove()" class="absolute top-2 right-2 text-gray-400 hover:text-red-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>' : ''}
            `;
            container.appendChild(div);
        }

        async function loadTeacherSubjects() {
            const streams = Array.from(document.querySelectorAll('.teacher-stream-checkbox:checked')).map(cb => cb.value);
            const container = document.getElementById('teacherSubjectContainer');
            const grid = document.getElementById('teacherSubjectsGrid');

            if (streams.length === 0) { container.classList.add('hidden'); return; }
            container.classList.remove('hidden');
            grid.innerHTML = '<div class="col-span-full py-4 text-center text-gray-400">Loading...</div>';

            try {
                const results = await Promise.all(streams.map(async id => {
                    const r = await fetch(`ajax/get_subjects.php?stream_id=${id}`);
                    return r.json();
                }));
                
                grid.innerHTML = '';
                results.forEach(res => {
                    if (res.success && res.subjects) {
                        res.subjects.forEach(sub => {
                            const isChecked = window.preSelectedSubjects.includes(String(sub.stream_subject_id)) ? 'checked' : '';
                            const label = document.createElement('label');
                            label.className = 'flex items-center space-x-2 border rounded p-3 bg-white hover:bg-gray-50 cursor-pointer';
                            label.innerHTML = `<input type="checkbox" name="teacher_subjects[]" value="${sub.stream_subject_id}" ${isChecked}>
                                               <span class="text-sm font-medium">${sub.name}</span>`;
                            grid.appendChild(label);
                        });
                    }
                });
            } catch (e) { grid.innerHTML = 'Error.'; }
        }

        function toggleMentorFields() {
            const el = document.getElementById('mentor_fields');
            document.getElementById('is_mentor').checked ? el.classList.remove('hidden') : el.classList.add('hidden');
        }

        function sendOTP() {
            const mob = document.getElementById('mobile_number').value;
            if(!mob) return alert('Enter mobile!');
            document.getElementById('sendOtpBtn').innerText = '...';
            setTimeout(() => {
                document.getElementById('otpSection').classList.remove('hidden');
                document.getElementById('sendOtpBtn').innerText = 'Resend';
            }, 500);
        }

        function verifyOTP() {
            const code = document.getElementById('otp_code').value;
            if(code.length === 6) {
                document.getElementById('otpMessage').innerText = 'Verified';
                document.getElementById('otpMessage').className = 'mt-2 text-xs font-bold text-emerald-600';
                document.getElementById('mobile_verified').value = '1';
            }
        }

        function verifyNIC() {
            const nic = document.getElementById('nic_number').value;
            if(!nic) return alert('Enter NIC!');
            alert('NIC data extraction mock.');
        }
    </script>
</body>
</html>
