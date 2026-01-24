<?php
require_once '../check_session.php';
require_once '../config.php';

// Get session variables
$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$second_name = $_SESSION['second_name'] ?? '';
$session_token = $_SESSION['session_token'] ?? '';

$stream_subject_id = isset($_GET['stream_subject_id']) ? intval($_GET['stream_subject_id']) : 0;
$academic_year = isset($_GET['academic_year']) ? intval($_GET['academic_year']) : date('Y');

$success_message = $_GET['success'] ?? '';
$error_message = '';

// Get teacher assignment ID for current user (if teacher) or find assignments for this stream-subject-year
$teacher_assignment_id = null;
$teacher_assignment_ids = [];

if ($stream_subject_id > 0) {
    if ($role === 'teacher') {
        // Find the teacher's specific assignment using session user_id
        $query = "SELECT id FROM teacher_assignments 
                  WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active' 
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $teacher_assignment_id = $row['id'];
            $teacher_assignment_ids = [$teacher_assignment_id];
        }
        $stmt->close();
    } else {
        // For students: get all active teacher assignments for this stream-subject-year
        $query = "SELECT id FROM teacher_assignments 
                  WHERE stream_subject_id = ? AND academic_year = ? AND status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $stream_subject_id, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $teacher_assignment_ids[] = $row['id'];
        }
        $stmt->close();
    }
}

// Handle toggle free video status (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_free_video']) && $role === 'teacher') {
    header('Content-Type: application/json');
    $recording_id = intval($_POST['recording_id'] ?? 0);
    $free_status = intval($_POST['free_status'] ?? 0);
    
    if ($recording_id > 0) {
        // Verify teacher owns this recording
        $verify_query = "SELECT r.id FROM recordings r
                        INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                        WHERE r.id = ? AND ta.teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param("is", $recording_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $update_query = "UPDATE recordings SET free_video = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $free_status, $recording_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Video status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating video status']);
            }
            $update_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        $verify_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
    }
    exit;
}

// Handle delete recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_recording']) && $role === 'teacher') {
    $recording_id = intval($_POST['recording_id'] ?? 0);
    
    if ($recording_id > 0) {
        // Verify teacher owns this recording
        $verify_query = "SELECT r.id FROM recordings r
                        INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                        WHERE r.id = ? AND ta.teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param("is", $recording_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            // Soft delete by setting status to inactive
            $delete_query = "UPDATE recordings SET status = 'inactive' WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $recording_id);
            
            if ($delete_stmt->execute()) {
                header('Location: content.php?stream_subject_id=' . $stream_subject_id . '&academic_year=' . $academic_year . '&success=' . urlencode('Recording deleted successfully!'));
                exit;
            } else {
                $error_message = 'Error deleting recording: ' . $conn->error;
            }
            $delete_stmt->close();
        } else {
            $error_message = 'Unauthorized: You do not own this recording.';
        }
        $verify_stmt->close();
    }
}

// Handle edit recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_recording']) && $role === 'teacher') {
    $recording_id = intval($_POST['recording_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $recording_date = trim($_POST['recording_date'] ?? date('Y-m-d'));
    $free_video = isset($_POST['free_video']) ? 1 : 0;
    $watch_limit = isset($_POST['watch_limit']) ? intval($_POST['watch_limit']) : 3;
    if ($watch_limit < 0) {
        $watch_limit = 3; // Default to 3 if negative
    }
    
    if ($recording_id > 0 && !empty($title)) {
        // Verify teacher owns this recording
        $verify_query = "SELECT r.id, r.youtube_video_id FROM recordings r
                        INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                        WHERE r.id = ? AND ta.teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param("is", $recording_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $recording_data = $verify_result->fetch_assoc();
            $youtube_video_id = $recording_data['youtube_video_id'];
            $thumbnail_url = "https://img.youtube.com/vi/{$youtube_video_id}/maxresdefault.jpg";
            
            // If YouTube URL changed, extract new video ID
            if (!empty($youtube_url) && $youtube_url !== $recording_data['youtube_url']) {
                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $matches)) {
                    $youtube_video_id = $matches[1];
                    $thumbnail_url = "https://img.youtube.com/vi/{$youtube_video_id}/maxresdefault.jpg";
                } else {
                    $error_message = 'Invalid YouTube URL.';
                    $verify_stmt->close();
                    // Continue to show error
                }
            }
            
            if (empty($error_message)) {
                // Validate and format recording date
                $recording_datetime = date('Y-m-d H:i:s', strtotime($recording_date));
                if ($recording_datetime === false || $recording_datetime === '1970-01-01 00:00:00') {
                    $recording_datetime = date('Y-m-d H:i:s');
                }
                
                $update_query = "UPDATE recordings SET title = ?, description = ?, youtube_url = ?, youtube_video_id = ?, thumbnail_url = ?, free_video = ?, watch_limit = ?, created_at = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssssiisi", $title, $description, $youtube_url, $youtube_video_id, $thumbnail_url, $free_video, $watch_limit, $recording_datetime, $recording_id);
                
                if ($update_stmt->execute()) {
                    header('Location: content.php?stream_subject_id=' . $stream_subject_id . '&academic_year=' . $academic_year . '&success=' . urlencode('Recording updated successfully!'));
                    exit;
                } else {
                    $error_message = 'Error updating recording: ' . $conn->error;
                }
                $update_stmt->close();
            }
        } else {
            $error_message = 'Unauthorized: You do not own this recording.';
        }
        $verify_stmt->close();
    } else {
        $error_message = 'Title is required.';
    }
}

// Handle form submission for adding new recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_recording']) && $role === 'teacher') {
    // Verify session is still valid
    if (empty($user_id) || empty($session_token)) {
        $error_message = 'Session expired. Please login again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        $recording_date = trim($_POST['recording_date'] ?? date('Y-m-d'));
        
        // Get teacher_assignment_id from session user_id (not from POST for security)
        // Use the stream_subject_id and academic_year from GET parameters
        $teacher_assignment_id_for_recording = null;
        if ($stream_subject_id > 0) {
            $query = "SELECT id FROM teacher_assignments 
                      WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active' 
                      LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $teacher_assignment_id_for_recording = $row['id'];
            } else {
                $error_message = 'You do not have an active assignment for this subject and academic year.';
            }
            $stmt->close();
        }
        
        if (empty($title) || empty($youtube_url)) {
            $error_message = 'Title and YouTube URL are required.';
        } elseif (!$teacher_assignment_id_for_recording) {
            // Error message already set above
        } else {
        // Extract YouTube video ID from various URL formats
        $youtube_video_id = '';
        
        // Pattern 1: https://www.youtube.com/watch?v=VIDEO_ID
        // Pattern 2: https://youtu.be/VIDEO_ID
        // Pattern 3: https://www.youtube.com/embed/VIDEO_ID
        // Pattern 4: https://youtube.com/watch?v=VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $matches)) {
            $youtube_video_id = $matches[1];
        } else {
            $error_message = 'Invalid YouTube URL. Please provide a valid YouTube video link.';
        }
        
            if (empty($error_message) && !empty($youtube_video_id)) {
                // Generate thumbnail URL
                $thumbnail_url = "https://img.youtube.com/vi/{$youtube_video_id}/maxresdefault.jpg";
                
                // Get free_video value (default to 0 if not set)
                $free_video = isset($_POST['free_video']) ? 1 : 0;
                
                // Get watch_limit (default to 3 if not set or invalid)
                $watch_limit = isset($_POST['watch_limit']) ? intval($_POST['watch_limit']) : 3;
                if ($watch_limit < 0) {
                    $watch_limit = 3; // Default to 3 if negative
                }
                
                // Validate and format recording date
                $recording_datetime = date('Y-m-d H:i:s', strtotime($recording_date));
                if ($recording_datetime === false || $recording_datetime === '1970-01-01 00:00:00') {
                    $recording_datetime = date('Y-m-d H:i:s'); // Use current date if invalid
                }
                
                // Insert recording using teacher_assignment_id from session user_id
                $stmt = $conn->prepare("INSERT INTO recordings (teacher_assignment_id, title, description, youtube_video_id, youtube_url, thumbnail_url, free_video, watch_limit, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                $stmt->bind_param("isssssiis", $teacher_assignment_id_for_recording, $title, $description, $youtube_video_id, $youtube_url, $thumbnail_url, $free_video, $watch_limit, $recording_datetime);
                
                if ($stmt->execute()) {
                    // Redirect to prevent form resubmission
                    header('Location: content.php?stream_subject_id=' . $stream_subject_id . '&academic_year=' . $academic_year . '&success=' . urlencode('Recording added successfully!'));
                    exit;
                } else {
                    $error_message = 'Error adding recording: ' . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

// Get subject and stream information
$subject_info = null;
if ($stream_subject_id > 0) {
    $query = "SELECT s.name as stream_name, sub.name as subject_name, sub.code as subject_code
              FROM stream_subjects ss
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              WHERE ss.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $stream_subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $subject_info = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get recordings for this teacher assignment(s) - include ended live classes as recordings
$recordings = [];
$paid_months = []; // Months student has paid for (for students)

if (!empty($teacher_assignment_ids)) {
    // Create placeholders for IN clause
    $placeholders = str_repeat('?,', count($teacher_assignment_ids) - 1) . '?';
    
    // Get regular recordings (not live) AND ended live classes
    $query = "SELECT DISTINCT r.id, r.title, r.description, r.youtube_video_id, r.youtube_url, r.thumbnail_url, 
                     r.view_count, r.status, r.created_at, r.free_video, r.watch_limit, r.is_live,
                     u.first_name, u.second_name
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              WHERE r.teacher_assignment_id IN ($placeholders) 
                AND (
                    (r.status = 'active' AND (r.is_live = 0 OR r.is_live IS NULL))
                    OR (r.is_live = 1 AND r.status = 'ended')
                )
              ORDER BY r.created_at ASC";
    $stmt = $conn->prepare($query);
    $types = str_repeat('i', count($teacher_assignment_ids));
    $stmt->bind_param($types, ...$teacher_assignment_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recordings[] = $row;
    }
    $stmt->close();
    
    // For students: Check which months they have paid for
    if ($role === 'student' && $stream_subject_id > 0) {
        // Get student enrollment ID
        $enroll_query = "SELECT id FROM student_enrollment 
                        WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'
                        LIMIT 1";
        $enroll_stmt = $conn->prepare($enroll_query);
        $enroll_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $enroll_stmt->execute();
        $enroll_result = $enroll_stmt->get_result();
        
        if ($enroll_result->num_rows > 0) {
            $enroll_row = $enroll_result->fetch_assoc();
            $enrollment_id = $enroll_row['id'];
            
            // Get paid months
            $paid_query = "SELECT month, year FROM monthly_payments 
                          WHERE student_enrollment_id = ? AND payment_status = 'paid'
                          UNION
                          SELECT MONTH(payment_date) as month, YEAR(payment_date) as year 
                          FROM enrollment_payments 
                          WHERE student_enrollment_id = ? AND payment_status = 'paid'";
            $paid_stmt = $conn->prepare($paid_query);
            $paid_stmt->bind_param("ii", $enrollment_id, $enrollment_id);
            $paid_stmt->execute();
            $paid_result = $paid_stmt->get_result();
            
            while ($paid_row = $paid_result->fetch_assoc()) {
                $paid_months[] = $paid_row['year'] . '-' . str_pad($paid_row['month'], 2, '0', STR_PAD_LEFT);
            }
            $paid_stmt->close();
        }
        $enroll_stmt->close();
    }
}

// Group recordings by month
$recordings_by_month = [];
foreach ($recordings as $recording) {
    $month_key = date('Y-m', strtotime($recording['created_at']));
    $month_name = date('F Y', strtotime($recording['created_at']));
    
    if (!isset($recordings_by_month[$month_key])) {
        $recordings_by_month[$month_key] = [
            'month_name' => $month_name,
            'recordings' => []
        ];
    }
    $recordings_by_month[$month_key]['recordings'][] = $recording;
}

// Sort months in ascending order (oldest first)
ksort($recordings_by_month);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordings - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            position: relative;
            min-height: 100vh;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://res.cloudinary.com/dnfbik3if/image/upload/v1768563143/11462_ytzt4d.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(4px);
            z-index: -1;
            transform: scale(1.1);
        }
    </style>
</head>
<body class="">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div class="mb-4 sm:mb-0">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Recordings</h1>
                        <?php if ($subject_info): ?>
                            <p class="text-gray-600">
                                <span class="font-semibold"><?php echo htmlspecialchars($subject_info['subject_name']); ?></span>
                                <?php if ($subject_info['subject_code']): ?>
                                    <span class="text-gray-500">(<?php echo htmlspecialchars($subject_info['subject_code']); ?>)</span>
                                <?php endif; ?>
                                - <?php echo htmlspecialchars($subject_info['stream_name']); ?>
                            </p>
                            <p class="text-gray-500 text-sm mt-1">Academic Year: <?php echo htmlspecialchars($academic_year); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-3">
                        <a href="recordings.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back
                        </a>
                        <?php if ($role === 'teacher' && $teacher_assignment_id): ?>
                            <button onclick="openAddModal()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add New Recording
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
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

            <!-- Recordings Grid (includes ended live classes) -->
            <?php if ($role === 'teacher' && !$teacher_assignment_id): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded mb-6">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm text-yellow-700">You don't have an active assignment for this subject and academic year.</p>
                    </div>
                </div>
            <?php elseif (empty($recordings)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                    <p class="text-gray-500 text-lg">No recordings available for this subject.</p>
                    <?php if ($role === 'teacher' && $teacher_assignment_id): ?>
                        <p class="text-gray-400 text-sm mt-2">Click "Add New Recording" to upload your first video.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Group recordings by month -->
                <?php foreach ($recordings_by_month as $month_key => $month_data): ?>
                    <div class="mb-8">
                        <!-- Month Heading -->
                        <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <?php echo htmlspecialchars($month_data['month_name']); ?>
                        </h2>
                        
                        <!-- Recordings Grid for this month -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            <?php foreach ($month_data['recordings'] as $recording): ?>
                                <?php
                                // Check if student can watch this video
                                $can_watch = false;
                                $is_locked = false;
                                $watch_limit_exceeded = false;
                                $watch_count = 0;
                                $watch_limit = intval($recording['watch_limit'] ?? 3);
                                
                                if ($role === 'teacher') {
                                    // Teachers can watch all videos
                                    $can_watch = true;
                                } elseif ($role === 'student') {
                                    // Check if video is free
                                    if ($recording['free_video'] == 1) {
                                        $can_watch = true;
                                    } else {
                                        // Check if student has paid for this month
                                        $recording_month = date('Y-m', strtotime($recording['created_at']));
                                        if (in_array($recording_month, $paid_months)) {
                                            $can_watch = true;
                                        } else {
                                            $is_locked = true;
                                        }
                                    }
                                    
                                    // Check watch limit (only if can_watch is true and watch_limit > 0)
                                    if ($can_watch && $watch_limit > 0) {
                                        // Get current watch count for this student and video
                                        $watch_query = "SELECT COUNT(*) as watch_count FROM video_watch_log 
                                                      WHERE recording_id = ? AND student_id = ?";
                                        $watch_stmt = $conn->prepare($watch_query);
                                        $watch_stmt->bind_param("is", $recording['id'], $user_id);
                                        $watch_stmt->execute();
                                        $watch_result = $watch_stmt->get_result();
                                        $watch_row = $watch_result->fetch_assoc();
                                        $watch_count = intval($watch_row['watch_count']);
                                        $watch_stmt->close();
                                        
                                        // If watch limit exceeded, disable video
                                        if ($watch_count >= $watch_limit) {
                                            $can_watch = false;
                                            $is_locked = true;
                                            $watch_limit_exceeded = true;
                                        }
                                    }
                                } else {
                                    $can_watch = true;
                                }
                                ?>
                                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow overflow-hidden <?php echo $is_locked ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'; ?>" 
                                     <?php if ($can_watch): ?>
                                         onclick="playVideo(<?php echo $recording['id']; ?>)"
                                     <?php else: ?>
                                         onclick="showPaymentRequired('<?php echo htmlspecialchars($month_data['month_name']); ?>')"
                                     <?php endif; ?>>
                                    <!-- Thumbnail -->
                                    <div class="relative aspect-video bg-gray-200">
                                        <img src="<?php echo htmlspecialchars($recording['thumbnail_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($recording['title']); ?>"
                                             class="w-full h-full object-cover"
                                             onerror="this.src='https://img.youtube.com/vi/<?php echo htmlspecialchars($recording['youtube_video_id']); ?>/hqdefault.jpg'">
                                        <!-- Play Button Overlay or Lock Icon -->
                                        <div class="absolute inset-0 flex items-center justify-center bg-black <?php echo $is_locked ? 'bg-opacity-50' : 'bg-opacity-30 hover:bg-opacity-40'; ?> transition-opacity">
                                            <?php if ($is_locked): ?>
                                                <div class="text-center">
                                                    <svg class="w-16 h-16 text-white mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                    </svg>
                                                    <p class="text-white text-xs font-semibold">
                                                        <?php echo $watch_limit_exceeded ? 'Watch Limit Reached' : 'Payment Required'; ?>
                                                    </p>
                                                </div>
                                            <?php else: ?>
                                                <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M8 5v14l11-7z"/>
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($recording['free_video'] == 1): ?>
                                            <div class="absolute top-2 right-2 bg-green-500 text-white text-xs font-semibold px-2 py-1 rounded">
                                                FREE
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Video Info -->
                                    <div class="p-4">
                                        <div class="flex items-start justify-between mb-2">
                                            <h3 class="font-semibold text-gray-900 line-clamp-2 flex-1" title="<?php echo htmlspecialchars($recording['title']); ?>">
                                                <?php echo htmlspecialchars($recording['title']); ?>
                                            </h3>
                                            <?php if ($role === 'teacher'): ?>
                                                <div class="flex items-center gap-2 ml-2 flex-shrink-0">
                                                    <!-- Free Video Toggle Button -->
                                                    <button onclick="toggleFreeVideo(event, <?php echo $recording['id']; ?>, <?php echo $recording['free_video'] == 1 ? 0 : 1; ?>)" 
                                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 <?php echo $recording['free_video'] == 1 ? 'bg-red-600' : 'bg-gray-300'; ?>"
                                                            title="<?php echo $recording['free_video'] == 1 ? 'Make Paid' : 'Make Free'; ?>">
                                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?php echo $recording['free_video'] == 1 ? 'translate-x-6' : 'translate-x-1'; ?>"></span>
                                                    </button>
                                                    <!-- Edit Button -->
                                                    <button onclick="event.stopPropagation(); openEditModal(<?php echo $recording['id']; ?>, '<?php echo htmlspecialchars(addslashes($recording['title'])); ?>', '<?php echo htmlspecialchars(addslashes($recording['description'] ?? '')); ?>', '<?php echo htmlspecialchars($recording['youtube_url']); ?>', '<?php echo date('Y-m-d', strtotime($recording['created_at'])); ?>', <?php echo $recording['free_video']; ?>, <?php echo intval($recording['watch_limit'] ?? 3); ?>)" 
                                                            class="p-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded transition-colors"
                                                            title="Edit Recording">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </button>
                                                    <!-- Delete Button -->
                                                    <button onclick="event.stopPropagation(); confirmDelete(<?php echo $recording['id']; ?>, '<?php echo htmlspecialchars(addslashes($recording['title'])); ?>')" 
                                                            class="p-1.5 text-red-600 hover:text-red-700 hover:bg-red-50 rounded transition-colors"
                                                            title="Delete Recording">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($recording['description']): ?>
                                            <p class="text-sm text-gray-600 mb-2 line-clamp-2">
                                                <?php echo htmlspecialchars(substr($recording['description'], 0, 100)); ?>
                                                <?php echo strlen($recording['description']) > 100 ? '...' : ''; ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                            <span><?php echo date('M d, Y', strtotime($recording['created_at'])); ?></span>
                                            <?php if ($recording['view_count'] > 0): ?>
                                                <span><?php echo number_format($recording['view_count']); ?> views</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($recording['first_name'] || $recording['second_name']): ?>
                                            <p class="text-xs text-gray-500 mt-1">
                                                By: <?php echo htmlspecialchars(trim(($recording['first_name'] ?? '') . ' ' . ($recording['second_name'] ?? ''))); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Recording Modal (Teachers Only) -->
    <?php if ($role === 'teacher' && $teacher_assignment_id): ?>
        <div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Edit Recording</h3>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="" id="editForm" class="space-y-4">
                    <input type="hidden" name="edit_recording" value="1">
                    <input type="hidden" name="recording_id" id="edit_recording_id">
                    
                    <div>
                        <label for="edit_title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" id="edit_title" name="title" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    
                    <div>
                        <label for="edit_youtube_url" class="block text-sm font-medium text-gray-700 mb-1">YouTube URL *</label>
                        <input type="url" id="edit_youtube_url" name="youtube_url" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               placeholder="https://www.youtube.com/watch?v=...">
                        <p class="text-xs text-gray-500 mt-1">Supports: youtube.com/watch?v=, youtu.be/, youtube.com/embed/</p>
                    </div>
                    
                    <div>
                        <label for="edit_recording_date" class="block text-sm font-medium text-gray-700 mb-1">Recording Date *</label>
                        <input type="date" id="edit_recording_date" name="recording_date" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="edit_description" name="description" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"></textarea>
                    </div>
                    
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="free_video" id="edit_free_video" value="1" 
                                   class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                            <span class="ml-2 text-sm text-gray-700">Mark as Free Video (students can watch without payment)</span>
                        </label>
                    </div>
                    
                    <div>
                        <label for="edit_watch_limit" class="block text-sm font-medium text-gray-700 mb-1">Watch Limit per Student *</label>
                        <input type="number" id="edit_watch_limit" name="watch_limit" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <p class="text-xs text-gray-500 mt-1">Maximum number of times a student can watch this video (0 = unlimited, default: 3)</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Update Recording
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Delete Recording</h3>
                    <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="mb-6">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-red-100 rounded-full">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    <p class="text-gray-700 text-center mb-2">Are you sure you want to delete this recording?</p>
                    <p class="text-sm text-gray-600 text-center font-semibold" id="delete_recording_title"></p>
                    <p class="text-xs text-gray-500 text-center mt-2">This action cannot be undone.</p>
                </div>
                
                <form method="POST" action="" id="deleteForm" class="flex justify-end space-x-3">
                    <input type="hidden" name="delete_recording" value="1">
                    <input type="hidden" name="recording_id" id="delete_recording_id">
                    <button type="button" onclick="closeDeleteModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Delete
                    </button>
                </form>
            </div>
        </div>

    <!-- Add Recording Modal (Teachers Only) -->
        <div id="addModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Add New Recording</h3>
                    <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="" class="space-y-4">
                    <!-- Note: teacher_assignment_id is retrieved from session user_id, not from form for security -->
                    
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" id="title" name="title" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="youtube_url" class="block text-sm font-medium text-gray-700 mb-1">YouTube URL *</label>
                        <input type="url" id="youtube_url" name="youtube_url" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               placeholder="https://www.youtube.com/watch?v=..." 
                               value="<?php echo htmlspecialchars($_POST['youtube_url'] ?? ''); ?>">
                        <p class="text-xs text-gray-500 mt-1">Supports: youtube.com/watch?v=, youtu.be/, youtube.com/embed/</p>
                    </div>
                    
                    <div>
                        <label for="recording_date" class="block text-sm font-medium text-gray-700 mb-1">Recording Date *</label>
                        <input type="date" id="recording_date" name="recording_date" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               value="<?php echo htmlspecialchars($_POST['recording_date'] ?? date('Y-m-d')); ?>">
                        <p class="text-xs text-gray-500 mt-1">Date when this recording was created</p>
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="free_video" value="1" 
                                   class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500"
                                   <?php echo (isset($_POST['free_video']) && $_POST['free_video'] == 1) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-sm text-gray-700">Mark as Free Video (students can watch without payment)</span>
                        </label>
                    </div>
                    
                    <div>
                        <label for="watch_limit" class="block text-sm font-medium text-gray-700 mb-1">Watch Limit per Student *</label>
                        <input type="number" id="watch_limit" name="watch_limit" min="0" value="<?php echo htmlspecialchars($_POST['watch_limit'] ?? '3'); ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <p class="text-xs text-gray-500 mt-1">Maximum number of times a student can watch this video (0 = unlimited, default: 3)</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="closeAddModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" name="add_recording"
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Add Recording
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Payment Required Modal -->
    <?php if ($role === 'student'): ?>
        <div id="paymentRequiredModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Payment Required</h3>
                    <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="text-center mb-6">
                    <svg class="w-16 h-16 text-red-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <p class="text-gray-700 mb-2">This video requires payment to watch.</p>
                    <p class="text-sm text-gray-600 mb-4" id="paymentMonthText"></p>
                </div>
                
                <div class="flex flex-col space-y-3">
                    <a href="payments.php" class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-center font-medium">
                        Go to Payments
                    </a>
                    <button onclick="closePaymentModal()" class="w-full px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Video Player Modal -->
    <div id="videoModal" class="hidden fixed inset-0 bg-black bg-opacity-90 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 w-full max-w-4xl">
            <div class="flex justify-end mb-4">
                <button onclick="closeVideoModal()" class="text-white hover:text-gray-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="aspect-video bg-black">
                <iframe id="videoPlayer" 
                        class="w-full h-full" 
                        src="" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function openEditModal(recordingId, title, description, youtubeUrl, recordingDate, freeVideo, watchLimit) {
            document.getElementById('edit_recording_id').value = recordingId;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_youtube_url').value = youtubeUrl;
            document.getElementById('edit_recording_date').value = recordingDate;
            document.getElementById('edit_free_video').checked = freeVideo == 1;
            document.getElementById('edit_watch_limit').value = watchLimit || 3;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editForm').reset();
        }

        function confirmDelete(recordingId, title) {
            document.getElementById('delete_recording_id').value = recordingId;
            document.getElementById('delete_recording_title').textContent = title;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function playVideo(recordingId) {
            // Redirect to custom player
            window.location.href = '../player/player.php?id=' + recordingId + 
                '&stream_subject_id=<?php echo $stream_subject_id; ?>&academic_year=<?php echo $academic_year; ?>';
        }

        function closeVideoModal() {
            const modal = document.getElementById('videoModal');
            const player = document.getElementById('videoPlayer');
            player.src = '';
            modal.classList.add('hidden');
        }

        function showPaymentRequired(monthName) {
            const modal = document.getElementById('paymentRequiredModal');
            const monthText = document.getElementById('paymentMonthText');
            if (monthText) {
                monthText.textContent = `Please make payment for ${monthName} to access these videos.`;
            }
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function closePaymentModal() {
            const modal = document.getElementById('paymentRequiredModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function toggleFreeVideo(event, recordingId, newStatus) {
            event.stopPropagation(); // Prevent triggering the video play
            
            if (!confirm(newStatus === 1 ? 'Make this video free for all students?' : 'Make this video require payment?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('toggle_free_video', '1');
            formData.append('recording_id', recordingId);
            formData.append('free_status', newStatus);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show updated status
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating video status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating video status. Please try again.');
            });
        }

        // Close modals on outside click
        document.getElementById('addModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        document.getElementById('editModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        document.getElementById('videoModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeVideoModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeDeleteModal();
                closeVideoModal();
                closePaymentModal();
            }
        });

        // Close payment modal on outside click
        document.getElementById('paymentRequiredModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });

        // Live Class Management Functions
        function startLiveClass(recordingId) {
            if (!confirm('Start this live class? Students will be able to join.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'start');
            formData.append('recording_id', recordingId);
            
            fetch('manage_live_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Error starting live class', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error starting live class. Please try again.', 'error');
            });
        }

        function deleteLiveClass(recordingId, title) {
            if (!confirm(`Are you sure you want to delete "${title}"? This action cannot be undone.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('recording_id', recordingId);
            
            fetch('manage_live_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Error deleting live class', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting live class. Please try again.', 'error');
            });
        }

        // Toast notification function
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer') || createToastContainer();
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const icon = type === 'success' ? 
                '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' :
                '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
            
            toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 min-w-[300px] max-w-md transform transition-all duration-300 ease-in-out translate-x-full opacity-0 fixed top-4 right-4 z-50`;
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
                    if (toast.parentElement) {
                        toast.parentElement.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
            return container;
        }
    </script>
</body>
</html>

