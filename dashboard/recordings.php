<?php
require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');

// Get recordings background image
$recordings_background = null;
$bg_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'recordings_background' LIMIT 1";
$bg_result = $conn->query($bg_query);
if ($bg_result && $bg_result->num_rows > 0) {
    $bg_row = $bg_result->fetch_assoc();
    $recordings_background = $bg_row['setting_value'];
}

$success_message = '';
$error_message = '';

// Handle new teacher assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment']) && $role === 'teacher') {
    // Check if teacher is approved in database
    $appr_check = $conn->prepare("SELECT approved FROM users WHERE user_id = ? LIMIT 1");
    $appr_check->bind_param("s", $user_id);
    $appr_check->execute();
    $appr_res = $appr_check->get_result();
    $is_approved_teacher = ($appr_row = $appr_res->fetch_assoc()) ? (int)$appr_row['approved'] : 0;
    $appr_check->close();

    if ($is_approved_teacher !== 1) {
        $error_message = 'Only approved teachers can create new class enrollments. Please wait for administrator approval.';
    } else {
    $stream_id = intval($_POST['stream_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');
    $batch_name = trim($_POST['batch_name'] ?? '');
    
    // Validate
    if ($stream_id <= 0) {
        $error_message = 'Please select a stream.';
    } elseif ($subject_id <= 0) {
        $error_message = 'Please select a subject.';
    } else {
        // Find or link stream_subject
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
        
        // Create teacher assignment
        if (empty($error_message) && $stream_subject_id) {
                // Check if assignment already exists
                $check_assign = $conn->prepare("SELECT id FROM teacher_assignments WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ?");
                $check_assign->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
                $check_assign->execute();
                $assign_result = $check_assign->get_result();
                
                if ($assign_result->num_rows > 0) {
                    $error_message = 'You are already assigned to this subject and academic year.';
                } else {
                    $batch_name_value = !empty($batch_name) ? $batch_name : null;
                    
                    // Handle Cover Image Upload
                    $cover_image_path = null;
                    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                        $allowed_extensions = ['jpg', 'jpeg', 'png'];
                        $file_name = $_FILES['cover_image']['name'];
                        $file_tmp = $_FILES['cover_image']['tmp_name'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        if (in_array($file_ext, $allowed_extensions)) {
                            // Ensure upload directory exists
                            $upload_dir = '../uploads/subject_covers/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }
                            
                            // Generate unique filename
                            $new_filename = uniqid() . '.' . $file_ext;
                            $destination = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($file_tmp, $destination)) {
                                $cover_image_path = 'uploads/subject_covers/' . $new_filename;
                            } else {
                                $error_message = "Failed to move uploaded file.";
                            }
                        } else {
                            $error_message = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
                        }
                    }

                    if (empty($error_message)) {
                        $enrollment_fee = isset($_POST['enrollment_fee']) ? floatval($_POST['enrollment_fee']) : 0.00;
                        $monthly_fee = isset($_POST['monthly_fee']) ? floatval($_POST['monthly_fee']) : 0.00;

                        $create_assign = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, batch_name, status, assigned_date, cover_image) VALUES (?, ?, ?, ?, 'active', CURDATE(), ?)");
                        $create_assign->bind_param("siiss", $user_id, $stream_subject_id, $academic_year, $batch_name_value, $cover_image_path);
                        
                        if ($create_assign->execute()) {
                            $new_assignment_id = $create_assign->insert_id;
                            
                            // Insert fees into enrollment_fees table
                            if ($enrollment_fee > 0 || $monthly_fee > 0) {
                                $fee_stmt = $conn->prepare("INSERT INTO enrollment_fees (teacher_assignment_id, enrollment_fee, monthly_fee) VALUES (?, ?, ?)");
                                $fee_stmt->bind_param("idd", $new_assignment_id, $enrollment_fee, $monthly_fee);
                                $fee_stmt->execute();
                                $fee_stmt->close();
                            }

                            // Redirect to prevent form resubmission
                            header('Location: recordings.php?success=' . urlencode('Assignment created successfully!'));
                            exit;
                        } else {
                            $error_message = 'Error creating assignment: ' . $conn->error;
                        }
                        $create_assign->close();
                    }
                }
                $check_assign->close();
            }
        }
    }
}

// Get all streams and subjects for dropdowns (for teachers)
$all_streams = [];
$all_subjects = [];
if ($role === 'teacher') {
    $streams_query = "SELECT id, name FROM streams WHERE status = 1 ORDER BY name";
    $streams_result = $conn->query($streams_query);
    $all_streams = $streams_result->fetch_all(MYSQLI_ASSOC);
    
    $subjects_query = "SELECT id, name, code FROM subjects WHERE status = 1 ORDER BY name";
    $subjects_result = $conn->query($subjects_query);
    $all_subjects = $subjects_result->fetch_all(MYSQLI_ASSOC);
}

// Initialize arrays
$teacher_assignments = [];
$student_enrollments = [];
$unique_subjects = [];
$unique_years = [];

if ($role === 'teacher') {
    // Get teacher assignments
    $query = "SELECT ta.id, ta.stream_subject_id, ta.academic_year, ta.batch_name, ta.status, 
                     ta.assigned_date, ta.start_date, ta.end_date, ta.notes, ta.cover_image,
                     (SELECT COUNT(*) FROM student_enrollment se WHERE se.stream_subject_id = ta.stream_subject_id AND se.academic_year = ta.academic_year AND se.status = 'active') as student_count,
                     s.name as stream_name, sub.name as subject_name, sub.code as subject_code
              FROM teacher_assignments ta
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              WHERE ta.teacher_id = ? AND ta.status = 'active'
              ORDER BY ta.academic_year DESC, s.name, sub.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $unique_subjects = [];
    $unique_years = [];
    
    while ($row = $result->fetch_assoc()) {
        $teacher_assignments[] = $row;
        // Collect unique subjects
        if (!in_array($row['subject_name'], $unique_subjects)) {
            $unique_subjects[] = $row['subject_name'];
        }
        // Collect unique academic years
        if (!in_array($row['academic_year'], $unique_years)) {
            $unique_years[] = $row['academic_year'];
        }
    }
    if (!empty($unique_subjects)) {
        sort($unique_subjects);
    }
    if (!empty($unique_years)) {
        rsort($unique_years); // Sort years descending
    }
    $stmt->close();
    
} elseif ($role === 'student') {
    // Get student enrollments with teacher information and payment status
    $query = "SELECT se.id, se.stream_subject_id, se.academic_year, se.batch_name, se.enrolled_date,
                     se.status, se.notes, ta.cover_image,
                     s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                     t.user_id as teacher_id, t.first_name as teacher_first_name, 
                     t.second_name as teacher_second_name, t.profile_picture as teacher_profile_picture,
                     t.email as teacher_email, t.whatsapp_number as teacher_whatsapp
              FROM student_enrollment se
              INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id 
                AND ta.academic_year = se.academic_year 
                AND ta.status = 'active'
              LEFT JOIN users t ON ta.teacher_id = t.user_id AND t.role = 'teacher' AND t.status = 1
              WHERE se.student_id = ? AND se.status = 'active'
              ORDER BY se.academic_year DESC, s.name, sub.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $unique_subjects = [];
    $unique_years = [];
    $current_month = date('n'); // 1-12
    $current_year = date('Y');
    
    while ($row = $result->fetch_assoc()) {
        $enrollment_id = $row['id'];
        
        // Get enrollment payment status
        $enroll_payment_query = "SELECT payment_status, amount, payment_date, payment_method 
                                 FROM enrollment_payments 
                                 WHERE student_enrollment_id = ? 
                                 ORDER BY created_at DESC 
                                 LIMIT 1";
        $enroll_payment_stmt = $conn->prepare($enroll_payment_query);
        $enroll_payment_stmt->bind_param("i", $enrollment_id);
        $enroll_payment_stmt->execute();
        $enroll_payment_result = $enroll_payment_stmt->get_result();
        $enroll_payment = $enroll_payment_result->fetch_assoc();
        $enroll_payment_stmt->close();
        
        // Determine enrollment payment status
        if ($enroll_payment) {
            if ($enroll_payment['payment_status'] === 'pending') {
                $row['enrollment_payment_status'] = 'pending_approval';
            } elseif ($enroll_payment['payment_status'] === 'paid') {
                $row['enrollment_payment_status'] = 'approved';
            } else {
                $row['enrollment_payment_status'] = 'not_paid';
            }
            $row['enrollment_payment_amount'] = $enroll_payment['amount'];
            $row['enrollment_payment_date'] = $enroll_payment['payment_date'];
            $row['enrollment_payment_method'] = $enroll_payment['payment_method'];
        } else {
            $row['enrollment_payment_status'] = 'not_paid';
            $row['enrollment_payment_amount'] = null;
            $row['enrollment_payment_date'] = null;
            $row['enrollment_payment_method'] = null;
        }
        
        // Get current month payment status
        $monthly_payment_query = "SELECT payment_status, amount, payment_date, payment_method 
                                 FROM monthly_payments 
                                 WHERE student_enrollment_id = ? 
                                   AND month = ? 
                                   AND year = ?
                                 ORDER BY created_at DESC 
                                 LIMIT 1";
        $monthly_payment_stmt = $conn->prepare($monthly_payment_query);
        $monthly_payment_stmt->bind_param("iii", $enrollment_id, $current_month, $current_year);
        $monthly_payment_stmt->execute();
        $monthly_payment_result = $monthly_payment_stmt->get_result();
        $monthly_payment = $monthly_payment_result->fetch_assoc();
        $monthly_payment_stmt->close();
        
        // Determine current month payment status
        if ($monthly_payment) {
            if ($monthly_payment['payment_status'] === 'pending') {
                $row['monthly_payment_status'] = 'pending_approval';
            } elseif ($monthly_payment['payment_status'] === 'paid') {
                $row['monthly_payment_status'] = 'approved';
            } else {
                $row['monthly_payment_status'] = 'not_paid';
            }
            $row['monthly_payment_amount'] = $monthly_payment['amount'];
            $row['monthly_payment_date'] = $monthly_payment['payment_date'];
            $row['monthly_payment_method'] = $monthly_payment['payment_method'];
        } else {
            $row['monthly_payment_status'] = 'not_paid';
            $row['monthly_payment_amount'] = null;
            $row['monthly_payment_date'] = null;
            $row['monthly_payment_method'] = null;
        }
        
        $student_enrollments[] = $row;
        // Collect unique subjects
        if (!in_array($row['subject_name'], $unique_subjects)) {
            $unique_subjects[] = $row['subject_name'];
        }
        // Collect unique academic years
        if (!in_array($row['academic_year'], $unique_years)) {
            $unique_years[] = $row['academic_year'];
        }
    }
    if (!empty($unique_subjects)) {
        sort($unique_subjects);
    }
    if (!empty($unique_years)) {
        rsort($unique_years); // Sort years descending
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordings - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* ── Fixed background (desktop only, 1280×720) ── */
        .bg-design {
            display: none;
        }
        @media (min-width: 641px) {
            body {
                background-color: transparent;
            }
            .bg-design {
                display: block;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 0;
                pointer-events: none;
                width: 100vw;
                height: 56.25vw;
                max-width: 1280px;
                max-height: 720px;
            }
            .bg-design .bg-img {
                width: 100%;
                height: 100%;
                object-fit: fill;
                display: block;
            }
        }

        .content-overlay {
            min-height: 100vh;
            padding-top: 5rem;
            position: relative;
            z-index: 1;
        }
        @media (min-width: 640px) {
            .content-overlay {
                padding-top: 6rem;
            }
        }
        .glass-card {
            background: rgba(255, 255, 255, 1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .glass-card-light {
            background: rgba(255, 255, 255, 1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    <!-- Fixed background image (desktop only, 1280×720) -->
    <div class="bg-design">
        <img src="<?php echo $root_url; ?>assests/recbg.jpeg" class="bg-img" alt="Recordings Background">
    </div>
    
    <div class="content-overlay">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
           
          
            <?php if ($role === 'teacher'): ?>
                <!-- Teacher Assignments -->
                <div class="mb-6" style="margin-top:120px;">

                    <!-- Peeking-students title bar (full width, outside flex row) -->
                    <div class="-mb-10 sm:-mb-12 md:-mb-14 relative z-0">
                        <picture>
                            <source media="(max-width: 640px)"
                                    srcset="https://res.cloudinary.com/dnfbik3if/image/upload/v1789102608/169ce9a2-424c-498b-849c-7d7785a0c959_vyxhfj.png">
                            <img src="https://res.cloudinary.com/dnfbik3if/image/upload/v1789102606/aa5ebd45-4d6c-444e-9721-384b7dd29de8_bughbh.png"
                                 alt=""
                                 fetchpriority="high"
                                 class="block mx-auto h-24 sm:h-28 md:h-32
                                        w-auto max-w-full object-contain
                                        pointer-events-none select-none">
                        </picture>
                    </div>
                    <h2 class="relative z-10 text-2xl font-bold text-white mb-4 bg-red-700 p-3">My Subjects</h2>

                    <!-- Filter controls row -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end mb-4">
                        <?php if (!empty($teacher_assignments)): ?>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <!-- Subject Filter -->
                                <div class="flex-1 sm:flex-initial">
                                    <label for="filterSubject" class="block text-sm font-medium text-gray-700 mb-1">Filter by Subject</label>
                                    <select id="filterSubject" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                        <option value="">All Subjects</option>
                                        <?php foreach ($unique_subjects as $subject): ?>
                                            <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Academic Year Filter -->
                                <div class="flex-1 sm:flex-initial">
                                    <label for="filterYear" class="block text-sm font-medium text-gray-700 mb-1">Filter by Year</label>
                                    <select id="filterYear" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                        <option value="">All Years</option>
                                        <?php foreach ($unique_years as $year): ?>
                                            <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Create New Enroll Button -->
                                <div class="flex-1 sm:flex-initial">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                                    <button onclick="openAssignmentModal()" class="w-full sm:w-48 px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center text-sm">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Create New Enroll
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <div class="flex-1 sm:flex-initial">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                                    <button onclick="openAssignmentModal()" class="w-full sm:w-48 px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center text-sm">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Create New Enroll
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($teacher_assignments)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No active teaching Subjects found.</p>
                        </div>
                    <?php else: ?>
                        <div id="teacherCardsContainer" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            <?php foreach ($teacher_assignments as $assignment): ?>
                                <a href="content.php?stream_subject_id=<?php echo $assignment['stream_subject_id']; ?>&academic_year=<?php echo $assignment['academic_year']; ?>" 
                                   class="teacher-card bg-white rounded-xl shadow hover:shadow-lg transition-shadow overflow-hidden block cursor-pointer" 
                                   data-subject="<?php echo htmlspecialchars($assignment['subject_name']); ?>"
                                   data-year="<?php echo htmlspecialchars($assignment['academic_year']); ?>">

                                    <!-- Cover Image -->
                                    <div class="aspect-[1.91/1] w-full overflow-hidden relative border-b border-gray-100">
                                        <?php if (!empty($assignment['cover_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($assignment['cover_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($assignment['subject_name']); ?>"
                                                 class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full bg-gradient-to-br from-red-100 to-white flex items-center justify-center border-b border-gray-100">
                                                <svg class="w-8 h-8 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="p-3">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex-1 min-w-0">
                                                <h3 class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($assignment['batch_name'] ?: $assignment['subject_name']); ?></h3>
                                                <p class="text-xs font-semibold text-blue-600 truncate">
                                                    <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                                    <?php if ($assignment['subject_code']): ?>
                                                        <span class="text-gray-400 font-normal">(<?php echo htmlspecialchars($assignment['subject_code']); ?>)</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 ml-1 flex-shrink-0">Active</span>
                                        </div>

                                        <div class="space-y-1 text-xs text-gray-600">
                                            <div class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                                <span class="font-medium">Stream:</span>
                                                <span class="truncate"><?php echo htmlspecialchars($assignment['stream_name']); ?></span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                <span class="font-medium">Year:</span>
                                                <span><?php echo htmlspecialchars($assignment['academic_year']); ?></span>
                                            </div>
                                            <?php if ($assignment['batch_name']): ?>
                                            <div class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zm-7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                <span class="font-medium">Batch:</span>
                                                <span class="truncate"><?php echo htmlspecialchars($assignment['batch_name']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($assignment['assigned_date']): ?>
                                            <div class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                <span class="font-medium">Assigned:</span>
                                                <span><?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mt-2 pt-2 border-t border-gray-100 flex items-center gap-1 text-xs text-gray-500">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zm-7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                            <span class="font-medium">Enrolled:</span>
                                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-800"><?php echo $assignment['student_count']; ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($role === 'student'): ?>
                <!-- Student Enrollments -->
                <div class="mb-6" style="margin-top:120px;">

                    <!-- Peeking-students title bar (full width, outside flex row) -->
                    <div class="-mb-10 sm:-mb-12 md:-mb-14 relative z-0">
                        <picture>
                            <source media="(max-width: 640px)"
                                    srcset="https://res.cloudinary.com/dnfbik3if/image/upload/v1789102608/169ce9a2-424c-498b-849c-7d7785a0c959_vyxhfj.png">
                            <img src="https://res.cloudinary.com/dnfbik3if/image/upload/v1789102606/aa5ebd45-4d6c-444e-9721-384b7dd29de8_bughbh.png"
                                 alt=""
                                 fetchpriority="high"
                                 class="block mx-auto h-24 sm:h-28 md:h-32
                                        w-auto max-w-full object-contain
                                        pointer-events-none select-none">
                        </picture>
                    </div>
                    <h2 class="relative z-10 text-2xl font-bold text-white mb-4 bg-red-700 p-3">My Enrollments</h2>

                    <!-- Filter controls row -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end mb-4">
                        <?php if (!empty($student_enrollments)): ?>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <!-- Subject Filter -->
                                <div class="flex-1 sm:flex-initial">
                                    <label for="filterSubjectStudent" class="block text-sm font-medium text-gray-700 mb-1">Filter by Subject</label>
                                    <select id="filterSubjectStudent" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">All Subjects</option>
                                        <?php foreach ($unique_subjects as $subject): ?>
                                            <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Academic Year Filter -->
                                <div class="flex-1 sm:flex-initial">
                                    <label for="filterYearStudent" class="block text-sm font-medium text-gray-700 mb-1">Filter by Year</label>
                                    <select id="filterYearStudent" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">All Years</option>
                                        <?php foreach ($unique_years as $year): ?>
                                            <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($student_enrollments)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No active enrollments found.</p>
                        </div>
                    <?php else: ?>
                        <div id="studentCardsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($student_enrollments as $enrollment): ?>
                                <a href="content.php?stream_subject_id=<?php echo $enrollment['stream_subject_id']; ?>&academic_year=<?php echo $enrollment['academic_year']; ?>" 
                                   class="student-card bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow overflow-hidden flex flex-col block cursor-pointer"
                                   data-subject="<?php echo htmlspecialchars($enrollment['subject_name']); ?>"
                                   data-year="<?php echo htmlspecialchars($enrollment['academic_year']); ?>">
                                    
                                    <!-- Cover Image or Gradient with Facebook Post Aspect Ratio (1.91:1) -->
                                    <?php if (!empty($enrollment['cover_image'])): ?>
                                        <div class="aspect-[1.91/1] w-full overflow-hidden relative border-b border-gray-100">
                                            <img src="../<?php echo htmlspecialchars($enrollment['cover_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($enrollment['subject_name']); ?>"
                                                 class="w-full h-full object-cover">
                                        </div>
                                    <?php else: ?>
                                        <div class="aspect-[1.91/1] w-full bg-gradient-to-br from-red-100 to-white flex items-center justify-center border-b border-gray-100">
                                            <svg class="w-12 h-12 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>

                                    <div class="p-4 flex-1 flex flex-col">
                                        <!-- Subject Name & Code -->
                                        <div class="mb-3">
                                            <h3 class="text-lg font-bold text-gray-900 leading-tight mb-1"><?php echo htmlspecialchars($enrollment['subject_name']); ?></h3>
                                            <?php if ($enrollment['subject_code']): ?>
                                                <p class="text-xs text-gray-500 uppercase tracking-wide"><?php echo htmlspecialchars($enrollment['subject_code']); ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Teacher Info (Inline) -->
                                        <?php if ($enrollment['teacher_id']): ?>
                                            <div class="flex items-center mb-4">
                                                <?php if ($enrollment['teacher_profile_picture']): ?>
                                                    <img src="../<?php echo htmlspecialchars($enrollment['teacher_profile_picture']); ?>" 
                                                         class="w-10 h-10 rounded-full border border-gray-200 object-cover mr-3">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center border border-gray-200 mr-3">
                                                        <i class="fas fa-user text-gray-400"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                     <p class="text-sm font-semibold text-gray-800 leading-tight">
                                                        <?php echo htmlspecialchars(trim(($enrollment['teacher_first_name'] ?? '') . ' ' . ($enrollment['teacher_second_name'] ?? ''))); ?>
                                                     </p>
                                                     <?php if ($enrollment['teacher_whatsapp']): ?>
                                                        <p class="text-xs text-gray-500 flex items-center mt-0.5">
                                                            <i class="fab fa-whatsapp text-green-500 mr-1"></i>
                                                            <?php echo htmlspecialchars($enrollment['teacher_whatsapp']); ?>
                                                        </p>
                                                     <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-400 italic mb-4">No teacher assigned</p>
                                        <?php endif; ?>
                                        
                                        <!-- Stream & Year Tags -->
                                        <div class="flex flex-wrap gap-2 mt-auto">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                                <?php echo htmlspecialchars($enrollment['stream_name']); ?>
                                            </span>
                                            
                                            <?php 
                                            $enroll_status = $enrollment['enrollment_payment_status'] ?? 'not_paid';
                                            $monthly_status = $enrollment['monthly_payment_status'] ?? 'not_paid';
                                            $is_enrollment_paid = ($enroll_status === 'approved');
                                            ?>

                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium border <?php 
                                                echo ($enroll_status === 'approved') 
                                                    ? 'bg-green-50 text-green-700 border-green-100' 
                                                    : (($enroll_status === 'pending_approval') ? 'bg-yellow-50 text-yellow-700 border-yellow-100' : 'bg-red-50 text-red-700 border-red-100');
                                            ?>">
                                                Enrollment: <?php echo ($enroll_status === 'approved') ? 'Paid' : (($enroll_status === 'pending_approval') ? 'Pending' : 'Unpaid'); ?>
                                            </span>

                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium border <?php 
                                                echo ($monthly_status === 'approved') 
                                                    ? 'bg-green-50 text-green-700 border-green-100' 
                                                    : (($monthly_status === 'pending_approval') ? 'bg-yellow-50 text-yellow-700 border-yellow-100' : 'bg-red-50 text-red-700 border-red-100');
                                            ?>">
                                                <?php echo date('F') . ': ' . ($monthly_status === 'approved' ? 'Paid' : ($monthly_status === 'pending_approval' ? 'Pending' : 'Unpaid')); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                                            <span>Enrolled: <?php echo date('M d, Y', strtotime($enrollment['enrolled_date'])); ?></span>
                                            <span class="flex items-center text-red-600 font-medium group-hover:translate-x-1 transition-transform">
                                                View Content <i class="fas fa-arrow-right ml-1"></i>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <!-- New Assignment Modal (Teachers Only) -->
    <?php if ($role === 'teacher'): ?>
        <div id="assignmentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Create New Class</h3>
                    <button onclick="closeAssignmentModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="" id="assignmentForm" class="space-y-4" enctype="multipart/form-data">
                    <input type="hidden" name="create_assignment" value="1">
                    
                    <!-- Stream Selection -->
                    <div>
                        <label for="stream_id" class="block text-sm font-medium text-gray-700 mb-1">Stream *</label>
                        <select id="stream_id" name="stream_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">Select Stream</option>
                            <?php foreach ($all_streams as $stream): ?>
                                <option value="<?php echo $stream['id']; ?>"><?php echo htmlspecialchars($stream['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Subject Selection -->
                    <div>
                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                        <select id="subject_id" name="subject_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">Select Subject</option>
                            <?php foreach ($all_subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?><?php echo $subject['code'] ? ' (' . htmlspecialchars($subject['code']) . ')' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Academic Year (Hidden, defaults to current year) -->
                    <input type="hidden" name="academic_year" value="<?php echo date('Y'); ?>">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Enrollment Fee -->
                        <div>
                            <label for="enrollment_fee" class="block text-sm font-medium text-gray-700 mb-1">Enrollment Fee (LKR)</label>
                            <input type="number" id="enrollment_fee" name="enrollment_fee" min="0" step="0.01" value="0.00"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        </div>
                        
                        <!-- Monthly Fee -->
                        <div>
                            <label for="monthly_fee" class="block text-sm font-medium text-gray-700 mb-1">Monthly Fee (LKR)</label>
                            <input type="number" id="monthly_fee" name="monthly_fee" min="0" step="0.01" value="0.00"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        </div>
                    </div>
                    
                    <!-- Name of the Class (Required) -->
                    <div>
                        <label for="batch_name" class="block text-sm font-medium text-gray-700 mb-1">Name of the Class *</label>
                        <input type="text" id="batch_name" name="batch_name" required
                               placeholder="e.g., 2027 Chemistry Paper Class"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    
                    <!-- Cover Image -->
                    <div>
                        <label for="cover_image" class="block text-sm font-medium text-gray-700 mb-1">Subject Cover Image</label>
                        <input type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm text-gray-500
                               file:mr-4 file:py-2 file:px-4
                               file:rounded-full file:border-0
                               file:text-sm file:font-semibold
                               file:bg-red-50 file:text-red-700
                               hover:file:bg-red-100">
                        <p class="mt-1 text-xs text-gray-500">Allowed formats: JPG, PNG, JPEG.</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="closeAssignmentModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Create Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Toast notification functions
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const icon = type === 'success' ? 
                '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' :
                '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
            
            toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 min-w-[300px] max-w-md transform transition-all duration-300 ease-in-out translate-x-full opacity-0`;
            toast.innerHTML = `
                ${icon}
                <span class="flex-1">${message}</span>
            `;
            
            container.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 10);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    container.removeChild(toast);
                }, 300);
            }, 3000);
        }

        // Assignment modal functions
        function openAssignmentModal() {
            document.getElementById('assignmentModal').classList.remove('hidden');
        }

        function closeAssignmentModal() {
            document.getElementById('assignmentModal').classList.add('hidden');
            // Reset form
            document.getElementById('assignmentForm').reset();
            document.getElementById('new_stream_name').classList.add('hidden');
            document.getElementById('new_subject_fields').classList.add('hidden');
        }

        function toggleStreamInput() {
            const streamSelect = document.getElementById('stream_id');
            const newStreamInput = document.getElementById('new_stream_name');
            if (streamSelect.value === 'new') {
                newStreamInput.classList.remove('hidden');
                newStreamInput.required = true;
            } else {
                newStreamInput.classList.add('hidden');
                newStreamInput.required = false;
                newStreamInput.value = '';
            }
        }

        function toggleSubjectInput() {
            const subjectSelect = document.getElementById('subject_id');
            const newSubjectFields = document.getElementById('new_subject_fields');
            if (subjectSelect.value === 'new') {
                newSubjectFields.classList.remove('hidden');
                document.getElementById('new_subject_name').required = true;
            } else {
                newSubjectFields.classList.add('hidden');
                document.getElementById('new_subject_name').required = false;
                document.getElementById('new_subject_name').value = '';
                document.getElementById('new_subject_code').value = '';
            }
        }

        // Show toasts on page load if messages exist
        <?php 
        $url_success = $_GET['success'] ?? '';
        $url_error = $_GET['error'] ?? '';
        if (!empty($success_message) || !empty($url_success)): 
            $msg = !empty($success_message) ? $success_message : $url_success;
        ?>
            showToast('<?php echo htmlspecialchars($msg); ?>', 'success');
        <?php endif; ?>
        <?php if (!empty($error_message) || !empty($url_error)): 
            $msg = !empty($error_message) ? $error_message : $url_error;
        ?>
            showToast('<?php echo htmlspecialchars($msg); ?>', 'error');
        <?php endif; ?>

        // Close modal on outside click
        document.getElementById('assignmentModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAssignmentModal();
            }
        });

        // Filter function for teacher cards
        function filterTeacherCards() {
            const subjectFilter = document.getElementById('filterSubject')?.value || '';
            const yearFilter = document.getElementById('filterYear')?.value || '';
            const cards = document.querySelectorAll('.teacher-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardSubject = card.getAttribute('data-subject');
                const cardYear = card.getAttribute('data-year');
                
                const subjectMatch = !subjectFilter || cardSubject === subjectFilter;
                const yearMatch = !yearFilter || cardYear === yearFilter;
                
                if (subjectMatch && yearMatch) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide empty message
            const container = document.getElementById('teacherCardsContainer');
            if (container) {
                let emptyMessage = container.querySelector('.no-results-message');
                if (visibleCount === 0 && cards.length > 0) {
                    if (!emptyMessage) {
                        emptyMessage = document.createElement('div');
                        emptyMessage.className = 'no-results-message col-span-full bg-white rounded-lg shadow p-8 text-center';
                        emptyMessage.innerHTML = `
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No results found for the selected filters.</p>
                        `;
                        container.appendChild(emptyMessage);
                    }
                    emptyMessage.style.display = 'block';
                } else if (emptyMessage) {
                    emptyMessage.style.display = 'none';
                }
            }
        }

        // Filter function for student cards
        function filterStudentCards() {
            const subjectFilter = document.getElementById('filterSubjectStudent')?.value || '';
            const yearFilter = document.getElementById('filterYearStudent')?.value || '';
            const cards = document.querySelectorAll('.student-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardSubject = card.getAttribute('data-subject');
                const cardYear = card.getAttribute('data-year');
                
                const subjectMatch = !subjectFilter || cardSubject === subjectFilter;
                const yearMatch = !yearFilter || cardYear === yearFilter;
                
                if (subjectMatch && yearMatch) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide empty message
            const container = document.getElementById('studentCardsContainer');
            if (container) {
                let emptyMessage = container.querySelector('.no-results-message');
                if (visibleCount === 0 && cards.length > 0) {
                    if (!emptyMessage) {
                        emptyMessage = document.createElement('div');
                        emptyMessage.className = 'no-results-message col-span-full bg-white rounded-lg shadow p-8 text-center';
                        emptyMessage.innerHTML = `
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No results found for the selected filters.</p>
                        `;
                        container.appendChild(emptyMessage);
                    }
                    emptyMessage.style.display = 'block';
                } else if (emptyMessage) {
                    emptyMessage.style.display = 'none';
                }
            }
        }

        // Add event listeners when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Teacher filters
            const filterSubject = document.getElementById('filterSubject');
            const filterYear = document.getElementById('filterYear');
            
            if (filterSubject) {
                filterSubject.addEventListener('change', filterTeacherCards);
            }
            if (filterYear) {
                filterYear.addEventListener('change', filterTeacherCards);
            }

            // Student filters
            const filterSubjectStudent = document.getElementById('filterSubjectStudent');
            const filterYearStudent = document.getElementById('filterYearStudent');
            
            if (filterSubjectStudent) {
                filterSubjectStudent.addEventListener('change', filterStudentCards);
            }
            if (filterYearStudent) {
                filterYearStudent.addEventListener('change', filterStudentCards);
            }

            // Show loading spinner inside the submit button when forms are submitted
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + submitBtn.textContent.trim();
                    }
                });
            });

            // Auto-open assignment modal if requested in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'create_enroll') {
                openAssignmentModal();
            }
        });
    </script>
</body>
</html>
