<?php
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Self-heal: Ensure all required columns exist in al_exam_submissions
$rank_columns = [
    'stream'               => "ALTER TABLE al_exam_submissions ADD COLUMN stream VARCHAR(50) DEFAULT NULL",
    'teacher_id'           => "ALTER TABLE al_exam_submissions ADD COLUMN teacher_id VARCHAR(255) DEFAULT NULL AFTER student_id",
    'result_1'             => "ALTER TABLE al_exam_submissions ADD COLUMN result_1 VARCHAR(5) DEFAULT NULL",
    'result_2'             => "ALTER TABLE al_exam_submissions ADD COLUMN result_2 VARCHAR(5) DEFAULT NULL",
    'result_3'             => "ALTER TABLE al_exam_submissions ADD COLUMN result_3 VARCHAR(5) DEFAULT NULL",
    'agreed_to_publish'    => "ALTER TABLE al_exam_submissions ADD COLUMN agreed_to_publish TINYINT(1) DEFAULT 0",
    'district_rank'        => "ALTER TABLE al_exam_submissions ADD COLUMN district_rank INT(11) DEFAULT NULL",
    'island_rank'          => "ALTER TABLE al_exam_submissions ADD COLUMN island_rank INT(11) DEFAULT NULL",
    'exam_year'            => "ALTER TABLE al_exam_submissions ADD COLUMN exam_year INT(11) DEFAULT NULL",
    'z_score'              => "ALTER TABLE al_exam_submissions ADD COLUMN z_score DECIMAL(6,4) DEFAULT NULL",
    'results_submitted_at' => "ALTER TABLE al_exam_submissions ADD COLUMN results_submitted_at TIMESTAMP NULL DEFAULT NULL"
];
foreach ($rank_columns as $col => $ddl) {
    $check_col = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE '{$col}'");
    if ($check_col && $check_col->num_rows === 0) {
        $conn->query($ddl);
    }
}

// Fetch all active teachers for linking (only teachers, no instructors)
$teachers_list = [];
$t_res = $conn->query("SELECT user_id, first_name, second_name, profile_picture FROM users WHERE role = 'teacher' AND status = 1 ORDER BY first_name, second_name");
if ($t_res) {
    while ($t_row = $t_res->fetch_assoc()) {
        $teachers_list[] = $t_row;
    }
}

// Fetch Existing Submission (if student already submitted, we let them view/edit)
$stmt = $conn->prepare("SELECT * FROM al_exam_submissions WHERE student_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

$already_submitted = !empty($submission);
$success_message = '';
$error_message = '';

// Handle Form Submission (Get A/L Subjects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stream = trim($_POST['stream'] ?? '');
    $subject1 = trim($_POST['subject_1'] ?? '');
    $subject2 = trim($_POST['subject_2'] ?? '');
    $subject3 = trim($_POST['subject_3'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $index_number = trim($_POST['index_number'] ?? '');
    $exam_year = (isset($_POST['exam_year']) && $_POST['exam_year'] !== '') ? intval($_POST['exam_year']) : null;
    $agreed_to_publish = isset($_POST['agreed_to_publish']) ? 1 : 0;
    
    // Teacher IDs from multi-picker
    $teacher_ids_raw = trim($_POST['selected_teacher_ids'] ?? '');
    $teacher_ids_arr = array_filter(array_map('trim', explode(',', $teacher_ids_raw)));
    $teacher_id_save = !empty($teacher_ids_arr) ? implode(',', $teacher_ids_arr) : null;
    
    // Validate
    if (empty($stream)) {
        $error_message = "Please select your A/L stream / කරුණාකර ඔබගේ උසස් පෙළ අංශය තෝරන්න.";
    } elseif (empty($subject1) || empty($subject2) || empty($subject3)) {
        $error_message = "Please provide all 3 A/L subjects / කරුණාකර විෂයන් 3ම ඇතුළත් කරන්න.";
    } else {
        // Handle Photo Upload
        $photo_path = $submission['photo_path'] ?? null;
        if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/al_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['student_photo']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = $user_id . '_al_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['student_photo']['tmp_name'], $target_path)) {
                    $photo_path = 'uploads/al_photos/' . $new_filename;
                } else {
                    $error_message = "Failed to upload photo.";
                }
            } else {
                $error_message = "Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.";
            }
        }
        
        if (empty($error_message)) {
            if ($already_submitted) {
                // Update existing record
                $update_query = "UPDATE al_exam_submissions SET stream = ?, teacher_id = ?, subject_1 = ?, subject_2 = ?, subject_3 = ?, index_number = ?, district = ?, exam_year = ?, photo_path = ?, agreed_to_publish = ? WHERE student_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("sssssssisss", $stream, $teacher_id_save, $subject1, $subject2, $subject3, $index_number, $district, $exam_year, $photo_path, $agreed_to_publish, $user_id);
                $exec_ok = $stmt->execute();
                $stmt->close();
            } else {
                // Insert new record
                $insert_query = "INSERT INTO al_exam_submissions (student_id, stream, teacher_id, subject_1, subject_2, subject_3, index_number, district, exam_year, photo_path, agreed_to_publish) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("ssssssssisi", $user_id, $stream, $teacher_id_save, $subject1, $subject2, $subject3, $index_number, $district, $exam_year, $photo_path, $agreed_to_publish);
                $exec_ok = $stmt->execute();
                $stmt->close();
            }
            
            if ($exec_ok) {
                $already_submitted = true;
                $_SESSION['al_subjects_submitted'] = true;
                $_SESSION['al_requested'] = false;
                $_SESSION['toast'] = ['message' => 'A/L subjects and details saved successfully!', 'type' => 'success'];
                
                // Clear requested flag in users table
                $clear_request = $conn->prepare("UPDATE users SET al_details_requested = 0 WHERE user_id = ?");
                if ($clear_request) {
                    $clear_request->bind_param("s", $user_id);
                    $clear_request->execute();
                    $clear_request->close();
                }

                header("Location: " . BASE_PATH . "dashboard/profile");
                exit();
            } else {
                $error_message = "Database error: " . $conn->error;
            }
        }
    }
}

// Handle Skip Action
if (isset($_GET['skip']) && $_GET['skip'] == '1') {
    $clear_request = $conn->prepare("UPDATE users SET al_details_requested = 0 WHERE user_id = ?");
    if ($clear_request) {
        $clear_request->bind_param("s", $user_id);
        $clear_request->execute();
        $clear_request->close();
    }
    $_SESSION['al_requested'] = false;
    header("Location: " . BASE_PATH . "dashboard/profile");
    exit();
}

// Complete Sri Lankan A/L Subjects List
$al_subjects = [
    "Biology", "Combined Mathematics", "Physics", "Chemistry", 
    "Agricultural Science", "Information & Communication Technology (ICT)",
    "Accounting", "Business Studies", "Economics", "Business Statistics",
    "Sinhala", "Tamil", "English", "French", "German", "Japanese", "Hindi", "Chinese", "Arabic",
    "History", "Political Science", "Geography", "Logic & Scientific Method",
    "Buddhist Civilization", "Christian Civilization", "Hindu Civilization", "Islamic Civilization",
    "Greek & Roman Civilization", "Art", "Dancing", "Music (Oriental)", "Music (Western)", "Music (Carnatic)",
    "Drama & Theatre", "Home Economics", "Communication & Media Studies", "Civil Technology",
    "Mechanical Technology", "Electrical, Electronic & Information Technology", "Food Technology",
    "Agro Technology", "Bio-Resource Technology", "Engineering Technology", "Science for Technology"
];

// Pre-fill values
$selected_stream = $_POST['stream'] ?? ($submission['stream'] ?? '');
$selected_s1 = $_POST['subject_1'] ?? ($submission['subject_1'] ?? '');
$selected_s2 = $_POST['subject_2'] ?? ($submission['subject_2'] ?? '');
$selected_s3 = $_POST['subject_3'] ?? ($submission['subject_3'] ?? '');
$selected_idx = $_POST['index_number'] ?? ($submission['index_number'] ?? '');
$selected_yr = $_POST['exam_year'] ?? ($submission['exam_year'] ?? '');
$selected_dst = $_POST['district'] ?? ($submission['district'] ?? '');
$selected_agreed = isset($_POST['agreed_to_publish']) ? 1 : ($submission['agreed_to_publish'] ?? 0);
$current_teachers_raw = $_POST['selected_teacher_ids'] ?? ($submission['teacher_id'] ?? '');
$current_teachers_arr = array_filter(array_map('trim', explode(',', $current_teachers_raw)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A/L Subjects Registration - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .autocomplete-items {
            position: absolute;
            border: 1px solid #fecaca;
            z-index: 9999;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            margin-top: 4px;
        }
        .autocomplete-items div {
            padding: 9px 14px;
            cursor: pointer;
            font-size: 12px;
            border-bottom: 1px solid #fef2f2;
            color: #334155;
            transition: all 0.15s ease;
        }
        .autocomplete-items div:last-child {
            border-bottom: none;
        }
        .autocomplete-items div:hover {
            background-color: #fef2f2;
            color: #dc2626;
            font-weight: 600;
        }
        .autocomplete-active {
            background-color: #fee2e2 !important;
            color: #b91c1c !important;
            font-weight: 600;
        }
        .teacher-card.selected {
            border-color: #dc2626 !important;
            background-color: #fef2f2 !important;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2);
        }
        .teacher-card.selected .selected-check {
            display: inline-flex !important;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen py-8 sm:py-12 px-3 sm:px-6">

    <div class="max-w-3xl w-full mx-auto bg-white rounded-3xl shadow-xl border border-red-100 overflow-hidden">
        
        <!-- Header Banner with Red Theme -->
        <div class="relative bg-gradient-to-r from-red-600 via-rose-600 to-red-700 p-6 sm:p-8 text-white text-center shadow-inner">
            <a href="al_results_form.php?skip=1" class="absolute top-4 sm:top-5 left-4 sm:left-5 inline-flex items-center gap-1.5 text-white/90 hover:text-white text-xs font-semibold px-3 py-1.5 bg-white/15 hover:bg-white/25 rounded-full backdrop-blur-xs transition-all">
                <i class="fas fa-arrow-left text-[11px]"></i>
                <span>Skip for now</span>
            </a>

            <!-- Institute Logo -->
            <div class="inline-flex items-center justify-center p-2 bg-white rounded-2xl shadow-md mb-3">
                <img src="../assests/logo.jpeg" alt="LMS Logo" class="h-12 sm:h-14 w-auto object-contain rounded-xl">
            </div>

            <h1 class="text-xl sm:text-2xl font-black tracking-tight">A/L Subjects Registration</h1>
            <p class="text-red-100 text-xs sm:text-sm mt-1 max-w-lg mx-auto font-medium">
                උසස් පෙළ විෂය තොරතුරු ලබාගැනීම — Select your A/L stream, 3 subjects, and link with your teachers.
            </p>
        </div>

        <div class="p-5 sm:p-8">
            <?php if ($success_message): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-xl mb-6 shadow-xs" role="alert">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-circle-check text-emerald-600 text-base"></i>
                        <p class="font-bold text-sm">Success!</p>
                    </div>
                    <p class="text-xs mt-1 text-emerald-700"><?php echo htmlspecialchars($success_message); ?></p>
                    <div class="mt-3 flex items-center gap-3">
                        <a href="al_exam_form.php" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all shadow-xs">
                            <i class="fas fa-trophy"></i>
                            <span>Got Results? Enter Results Now</span>
                        </a>
                        <a href="../index.php" class="text-xs text-slate-600 font-semibold hover:underline">
                            Go to Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-rose-50 border-l-4 border-red-600 text-red-900 p-4 rounded-xl mb-6 shadow-xs" role="alert">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-triangle-exclamation text-red-600 text-base"></i>
                        <p class="font-bold text-sm">Notice</p>
                    </div>
                    <p class="text-xs mt-1 text-red-700 font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-7">
                
                <!-- STEP 1: SELECT STREAM -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                        <label class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-red-600 text-white text-xs flex items-center justify-center font-black">1</span>
                            <span>Select Your A/L Stream <span class="text-red-600">*</span></span>
                        </label>
                        <span class="text-[11px] text-slate-400 font-medium">Step 1 of 4</span>
                    </div>

                    <div class="relative">
                        <select name="stream" id="selected_stream" required
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 focus:bg-white text-xs font-bold text-slate-800 transition-all outline-none appearance-none cursor-pointer">
                            <option value="">-- Select Your Stream --</option>
                            <option value="Physical Science" <?php echo $selected_stream === 'Physical Science' ? 'selected' : ''; ?>>Physical Science</option>
                            <option value="Biological Science" <?php echo $selected_stream === 'Biological Science' ? 'selected' : ''; ?>>Biological Science</option>
                            <option value="Commerce" <?php echo $selected_stream === 'Commerce' ? 'selected' : ''; ?>>Commerce</option>
                            <option value="Arts" <?php echo $selected_stream === 'Arts' ? 'selected' : ''; ?>>Arts</option>
                            <option value="Engineering Technology" <?php echo $selected_stream === 'Engineering Technology' ? 'selected' : ''; ?>>Engineering Technology</option>
                            <option value="Bio Systems Technology" <?php echo $selected_stream === 'Bio Systems Technology' ? 'selected' : ''; ?>>Bio Systems Technology</option>
                            <option value="Other" <?php echo $selected_stream === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: 3 SUBJECTS -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                        <label class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-red-600 text-white text-xs flex items-center justify-center font-black">2</span>
                            <span>Your 3 A/L Examination Subjects <span class="text-red-600">*</span></span>
                        </label>
                        <span class="text-[11px] text-slate-400 font-medium">Step 2 of 4</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                        <!-- Subject 1 -->
                        <div class="p-3.5 bg-slate-50/90 rounded-2xl border border-slate-200">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Subject 1 <span class="text-red-600">*</span></label>
                            <div class="relative">
                                <input type="text" name="subject_1" id="subject_1" required autocomplete="off"
                                       value="<?php echo htmlspecialchars($selected_s1); ?>"
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 text-xs font-semibold text-slate-800 transition-all outline-none"
                                       placeholder="Subject 1 Name...">
                            </div>
                        </div>

                        <!-- Subject 2 -->
                        <div class="p-3.5 bg-slate-50/90 rounded-2xl border border-slate-200">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Subject 2 <span class="text-red-600">*</span></label>
                            <div class="relative">
                                <input type="text" name="subject_2" id="subject_2" required autocomplete="off"
                                       value="<?php echo htmlspecialchars($selected_s2); ?>"
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 text-xs font-semibold text-slate-800 transition-all outline-none"
                                       placeholder="Subject 2 Name...">
                            </div>
                        </div>

                        <!-- Subject 3 -->
                        <div class="p-3.5 bg-slate-50/90 rounded-2xl border border-slate-200">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Subject 3 <span class="text-red-600">*</span></label>
                            <div class="relative">
                                <input type="text" name="subject_3" id="subject_3" required autocomplete="off"
                                       value="<?php echo htmlspecialchars($selected_s3); ?>"
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 text-xs font-semibold text-slate-800 transition-all outline-none"
                                       placeholder="Subject 3 Name...">
                            </div>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2">
                        <i class="fas fa-info-circle text-red-500 mr-1"></i> Start typing to search and select your 3 subjects from the list.
                    </p>
                </div>

                <!-- STEP 3: LINK TEACHERS -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                        <label class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-red-600 text-white text-xs flex items-center justify-center font-black">3</span>
                            <span>Link with Your Teacher(s) <span class="text-slate-400 font-normal text-xs">(Optional)</span></span>
                        </label>
                        <span class="text-[11px] text-slate-400 font-medium">Step 3 of 4</span>
                    </div>

                    <p class="text-xs text-slate-500 mb-3">
                        Select the teacher(s) who guided you for your A/L subjects. Click card(s) to select.
                    </p>

                    <!-- Selected chips -->
                    <div id="selected-chips" class="flex flex-wrap gap-2 mb-3 min-h-[24px]"></div>

                    <!-- Hidden input to submit -->
                    <input type="hidden" name="selected_teacher_ids" id="selected_teacher_ids" value="<?php echo htmlspecialchars($current_teachers_raw); ?>">

                    <!-- Search input -->
                    <div class="relative mb-3">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="teacher-search" oninput="filterTeachers()"
                               class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-red-500 focus:border-red-500 focus:bg-white outline-none"
                               placeholder="Type teacher name to search..." autocomplete="off">
                    </div>

                    <!-- No results message -->
                    <div id="teacher-no-results" class="hidden text-center py-6 text-slate-400 text-xs bg-slate-50 rounded-2xl border border-dashed border-slate-200 mb-2">
                        <i class="fas fa-user-slash text-lg mb-1 block text-slate-300"></i>
                        No teachers found matching your search.
                    </div>

                    <!-- Teacher cards grid -->
                    <div id="teacher-grid" class="hidden grid grid-cols-2 sm:grid-cols-3 gap-3.5 max-h-72 overflow-y-auto pr-1">
                        <?php foreach ($teachers_list as $t): ?>
                        <?php
                            $t_fullname = trim($t['first_name'] . ' ' . $t['second_name']);
                            $t_pic = !empty($t['profile_picture']) ? '../' . htmlspecialchars($t['profile_picture']) : '';
                            $t_avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($t_fullname) . '&background=fee2e2&color=dc2626&bold=true&size=120';
                            $is_preselected = in_array($t['user_id'], $current_teachers_arr);
                        ?>
                        <div class="teacher-card cursor-pointer border-2 <?php echo $is_preselected ? 'selected border-red-600 bg-red-50/60' : 'border-slate-200 bg-white'; ?> rounded-2xl p-4 flex flex-col items-center text-center hover:border-red-400 hover:bg-red-50/50 transition-all select-none shadow-xs"
                             data-id="<?php echo htmlspecialchars($t['user_id']); ?>"
                             data-name="<?php echo htmlspecialchars($t_fullname); ?>"
                             onclick="toggleTeacher(this)">
                            <?php if (!empty($t_pic)): ?>
                                <img src="<?php echo $t_pic; ?>"
                                     alt="<?php echo htmlspecialchars($t_fullname); ?>"
                                     class="w-16 h-16 sm:w-18 sm:h-18 rounded-full object-cover border-2 border-red-100 shadow-xs mb-2.5"
                                     onerror="this.onerror=null;this.src='<?php echo $t_avatar_url; ?>'">
                            <?php else: ?>
                                <img src="<?php echo $t_avatar_url; ?>"
                                     alt="<?php echo htmlspecialchars($t_fullname); ?>"
                                     class="w-16 h-16 sm:w-18 sm:h-18 rounded-full object-cover border-2 border-red-100 shadow-xs mb-2.5">
                            <?php endif; ?>
                            <span class="text-xs sm:text-sm font-bold text-slate-800 leading-snug line-clamp-2"><?php echo htmlspecialchars($t_fullname); ?></span>
                            <span class="selected-check <?php echo $is_preselected ? 'inline-flex' : 'hidden'; ?> mt-1.5 text-xs font-black text-red-600 items-center gap-1"><i class="fas fa-check-circle"></i> Linked</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- STEP 4: INDEX, DISTRICT & PHOTO -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                        <label class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-red-600 text-white text-xs flex items-center justify-center font-black">4</span>
                            <span>Student Details <span class="text-slate-400 font-normal text-xs">(Optional)</span></span>
                        </label>
                        <span class="text-[11px] text-slate-400 font-medium">Step 4 of 4</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Index Number <span class="text-slate-400 font-normal">(Optional)</span></label>
                            <input type="text" name="index_number" value="<?php echo htmlspecialchars($selected_idx); ?>"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 focus:bg-white text-xs font-mono text-slate-800 transition-all outline-none"
                                   placeholder="A/L Index Number">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Exam Year <span class="text-slate-400 font-normal">(Optional)</span></label>
                            <input type="number" name="exam_year" min="2000" max="2100" value="<?php echo htmlspecialchars($selected_yr); ?>"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 focus:bg-white text-xs font-semibold text-slate-800 transition-all outline-none"
                                   placeholder="e.g. 2024">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">District <span class="text-slate-400 font-normal">(Optional)</span></label>
                            <select name="district"
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 focus:bg-white text-xs font-medium text-slate-800 transition-all outline-none cursor-pointer">
                                <option value="">Select District</option>
                                <?php
                                $districts = ["Colombo", "Gampaha", "Kalutara", "Kandy", "Matale", "Nuwara Eliya", "Galle", "Matara", "Hambantota", "Jaffna", "Kilinochchi", "Mannar", "Vavuniya", "Mullaitivu", "Batticaloa", "Ampara", "Trincomalee", "Kurunegala", "Puttalam", "Anuradhapura", "Polonnaruwa", "Badulla", "Monaragala", "Ratnapura", "Kegalle"];
                                foreach ($districts as $d): ?>
                                    <option value="<?php echo $d; ?>" <?php echo $selected_dst === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Your Photo <span class="text-slate-400 font-normal">(Optional)</span></label>
                        <div class="flex items-center gap-3 p-3 bg-slate-50 border border-dashed border-slate-300 rounded-2xl cursor-pointer hover:border-red-400 transition-all" onclick="document.getElementById('photo-upload').click()">
                            <div class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                                <i class="fas fa-camera text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold text-slate-800" id="file-name">Upload passport size photo</div>
                                <div class="text-[10px] text-slate-400">JPG, PNG up to 5MB</div>
                            </div>
                            <input id="photo-upload" name="student_photo" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                            <button type="button" class="px-3 py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold text-red-600 hover:bg-red-50 transition-colors">
                                Browse
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Consent -->
                <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200">
                    <label class="inline-flex items-start gap-2.5 cursor-pointer select-none">
                        <input id="agreed_to_publish" name="agreed_to_publish" type="checkbox" <?php echo $selected_agreed ? 'checked' : ''; ?>
                               class="mt-0.5 h-4 w-4 text-red-600 border-slate-300 rounded focus:ring-red-500">
                        <div class="text-xs">
                            <span class="font-bold text-slate-800">Consent to display achievements publicly (Optional)</span>
                            <p class="text-[11px] text-slate-500 mt-0.5">Tick if you allow your examination achievements & ranks to be showcased on our institute results board.</p>
                            <p class="text-[11px] text-red-700 font-medium mt-1">මෙම තොරතුරු අපගේ වෙබ් අඩවිය තුළ ප්‍රසිද්ධියේ පළ කිරීමට මම එකඟ වෙමි.</p>
                        </div>
                    </label>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" class="w-full flex items-center justify-center gap-2 py-3.5 px-6 rounded-2xl shadow-md text-sm font-bold text-white bg-red-600 hover:bg-red-700 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all">
                        <i class="fas fa-paper-plane text-xs"></i>
                        <span><?php echo $already_submitted ? 'Update A/L Subjects' : 'Save A/L Subjects'; ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS for Autocomplete and Teacher multi-select -->
    <script>
        const subjects = <?php echo json_encode($al_subjects); ?>;
        let selectedTeacherIds = new Set(<?php echo json_encode(array_values($current_teachers_arr)); ?>);

        function initTeachers() {
            updateSelectedTeachers();
        }

        // Teacher Picker Logic
        function toggleTeacher(card) {
            const id = card.getAttribute('data-id');
            const name = card.getAttribute('data-name');
            if (selectedTeacherIds.has(id)) {
                selectedTeacherIds.delete(id);
                card.classList.remove('selected');
                card.querySelector('.selected-check')?.classList.add('hidden');
            } else {
                selectedTeacherIds.add(id);
                card.classList.add('selected');
                card.querySelector('.selected-check')?.classList.remove('hidden');
                card.querySelector('.selected-check')?.classList.add('inline-flex');
            }
            updateSelectedTeachers();
        }

        function updateSelectedTeachers() {
            document.getElementById('selected_teacher_ids').value = Array.from(selectedTeacherIds).join(',');
            const chips = document.getElementById('selected-chips');
            chips.innerHTML = '';
            
            selectedTeacherIds.forEach(id => {
                const card = document.querySelector(`.teacher-card[data-id="${id}"]`);
                const name = card ? card.getAttribute('data-name') : id;
                
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200';
                chip.innerHTML = `<i class="fas fa-chalkboard-user text-[10px]"></i> ${name} <button type="button" onclick="removeTeacher('${id}')" class="hover:text-red-950 ml-1 font-extrabold">&times;</button>`;
                chips.appendChild(chip);
            });
        }

        function removeTeacher(id) {
            selectedTeacherIds.delete(id);
            const card = document.querySelector(`.teacher-card[data-id="${id}"]`);
            if (card) {
                card.classList.remove('selected');
                card.querySelector('.selected-check')?.classList.add('hidden');
            }
            updateSelectedTeachers();
        }

        function filterTeachers() {
            const searchInput = document.getElementById('teacher-search');
            const query = (searchInput?.value || '').toLowerCase().trim();
            const grid = document.getElementById('teacher-grid');
            const noResults = document.getElementById('teacher-no-results');
            
            if (query.length === 0) {
                grid.classList.add('hidden');
                if (noResults) noResults.classList.add('hidden');
                return;
            }
            
            grid.classList.remove('hidden');
            let matchCount = 0;
            
            document.querySelectorAll('#teacher-grid .teacher-card').forEach(card => {
                const name = (card.getAttribute('data-name') || '').toLowerCase();
                if (name.includes(query)) {
                    card.style.display = '';
                    matchCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            if (noResults) {
                if (matchCount === 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
            }
        }

        // Autocomplete setup
        function autocomplete(inp, arr) {
            let currentFocus;
            inp.addEventListener("input", function(e) {
                let a, b, i, val = this.value;
                closeAllLists();
                if (!val) { return false; }
                currentFocus = -1;
                a = document.createElement("DIV");
                a.setAttribute("id", this.id + "autocomplete-list");
                a.setAttribute("class", "autocomplete-items");
                this.parentNode.appendChild(a);
                for (i = 0; i < arr.length; i++) {
                    if (arr[i].toUpperCase().indexOf(val.toUpperCase()) > -1) {
                        b = document.createElement("DIV");
                        const matchIndex = arr[i].toUpperCase().indexOf(val.toUpperCase());
                        b.innerHTML = arr[i].substr(0, matchIndex);
                        b.innerHTML += "<strong>" + arr[i].substr(matchIndex, val.length) + "</strong>";
                        b.innerHTML += arr[i].substr(matchIndex + val.length);
                        b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
                        b.addEventListener("click", function(e) {
                            inp.value = this.getElementsByTagName("input")[0].value;
                            closeAllLists();
                        });
                        a.appendChild(b);
                    }
                }
            });
            inp.addEventListener("keydown", function(e) {
                let x = document.getElementById(this.id + "autocomplete-list");
                if (x) x = x.getElementsByTagName("div");
                if (e.keyCode == 40) {
                    currentFocus++;
                    addActive(x);
                } else if (e.keyCode == 38) {
                    currentFocus--;
                    addActive(x);
                } else if (e.keyCode == 13) {
                    e.preventDefault();
                    if (currentFocus > -1) {
                        if (x) x[currentFocus].click();
                    }
                }
            });
            function addActive(x) {
                if (!x) return false;
                removeActive(x);
                if (currentFocus >= x.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = (x.length - 1);
                x[currentFocus].classList.add("autocomplete-active");
            }
            function removeActive(x) {
                for (let i = 0; i < x.length; i++) {
                    x[i].classList.remove("autocomplete-active");
                }
            }
            function closeAllLists(elmnt) {
                let x = document.getElementsByClassName("autocomplete-items");
                for (let i = 0; i < x.length; i++) {
                    if (elmnt != x[i] && elmnt != inp) {
                        x[i].parentNode.removeChild(x[i]);
                    }
                }
            }
            document.addEventListener("click", function (e) {
                closeAllLists(e.target);
            });
        }

        autocomplete(document.getElementById("subject_1"), subjects);
        autocomplete(document.getElementById("subject_2"), subjects);
        autocomplete(document.getElementById("subject_3"), subjects);

        function previewImage(input) {
            if (input.files && input.files[0]) {
                document.getElementById('file-name').textContent = input.files[0].name;
            }
        }

        document.addEventListener('DOMContentLoaded', initTeachers);
    </script>
</body>
</html>

