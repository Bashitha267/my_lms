<?php
// Start session safely if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');

// Get live classes background image
$live_classes_background = null;
$bg_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'live_classes_background' LIMIT 1";
$bg_result = $conn->query($bg_query);
if ($bg_result && $bg_result->num_rows > 0) {
    $bg_row = $bg_result->fetch_assoc();
    $live_classes_background = $bg_row['setting_value'];
}

$success_message = $_GET['success'] ?? '';
$error_message = '';

// Handle live class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_live_class']) && $role === 'teacher') {
    $stream_subject_id = isset($_POST['stream_subject_id']) ? intval($_POST['stream_subject_id']) : 0;
    $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $scheduled_start_time = trim($_POST['scheduled_start_time'] ?? '');
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $free_video = isset($_POST['free_video']) ? intval($_POST['free_video']) : 0;
    
    if (empty($title)) {
        $error_message = 'Title is required.';
    } elseif (empty($youtube_url)) {
        $error_message = 'YouTube live class link is required.';
    } elseif ($stream_subject_id <= 0) {
        $error_message = 'Please select a subject.';
    } else {
        // Get teacher assignment ID
        $assign_query = "SELECT id FROM teacher_assignments 
                       WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active' 
                       LIMIT 1";
        $assign_stmt = $conn->prepare($assign_query);
        $assign_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $assign_stmt->execute();
        $assign_result = $assign_stmt->get_result();
        
        if ($assign_result->num_rows > 0) {
            $assign_row = $assign_result->fetch_assoc();
            $teacher_assignment_id = $assign_row['id'];
            
            // Parse scheduled start time
            $scheduled_datetime = null;
            if (!empty($scheduled_start_time)) {
                $scheduled_datetime = date('Y-m-d H:i:s', strtotime($scheduled_start_time));
                if ($scheduled_datetime === false || $scheduled_datetime === '1970-01-01 00:00:00') {
                    $scheduled_datetime = null;
                }
            }
            
            // Extract YouTube video ID from URL
            $youtube_video_id = '';
            $thumbnail_url = null;
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/live\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $matches)) {
                $youtube_video_id = $matches[1];
                $thumbnail_url = "https://img.youtube.com/vi/{$youtube_video_id}/maxresdefault.jpg";
            } else {
                $error_message = 'Invalid YouTube URL. Please provide a valid YouTube live stream link.';
            }
            
            if (empty($error_message)) {
                // Create live class recording with status 'scheduled'
                $insert_query = "INSERT INTO recordings (teacher_assignment_id, is_live, title, description, status, scheduled_start_time, youtube_url, youtube_video_id, thumbnail_url, free_video, watch_limit) 
                               VALUES (?, 1, ?, ?, 'scheduled', ?, ?, ?, ?, ?, 3)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("issssssi", $teacher_assignment_id, $title, $description, $scheduled_datetime, $youtube_url, $youtube_video_id, $thumbnail_url, $free_video);
            
                if ($insert_stmt->execute()) {
                    header('Location: live_classes.php?success=' . urlencode('Live class created successfully!'));
                    exit;
                } else {
                    $error_message = 'Error creating live class: ' . $conn->error;
                }
                $insert_stmt->close();
            }
        } else {
            $error_message = 'You do not have an active assignment for this subject and academic year.';
        }
        $assign_stmt->close();
    }
}

// Handle Zoom class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_zoom_class']) && $role === 'teacher') {
    $stream_subject_id = isset($_POST['stream_subject_id']) ? intval($_POST['stream_subject_id']) : 0;
    $academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $scheduled_start_time = trim($_POST['scheduled_start_time'] ?? '');
    $zoom_meeting_link = trim($_POST['zoom_meeting_link'] ?? '');
    $zoom_meeting_id = trim($_POST['zoom_meeting_id'] ?? '');
    $zoom_passcode = trim($_POST['zoom_passcode'] ?? '');
    $free_class = isset($_POST['free_class']) ? intval($_POST['free_class']) : 0;
    
    if (empty($title)) {
        $error_message = 'Title is required.';
    } elseif (empty($zoom_meeting_link)) {
        $error_message = 'Zoom meeting link is required.';
    } elseif (empty($scheduled_start_time)) {
        $error_message = 'Scheduled start time is required.';
    } elseif ($stream_subject_id <= 0) {
        $error_message = 'Please select a subject.';
    } else {
        // Get teacher assignment ID
        $assign_query = "SELECT id FROM teacher_assignments 
                       WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active' 
                       LIMIT 1";
        $assign_stmt = $conn->prepare($assign_query);
        $assign_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $assign_stmt->execute();
        $assign_result = $assign_stmt->get_result();
        
        if ($assign_result->num_rows > 0) {
            $assign_row = $assign_result->fetch_assoc();
            $teacher_assignment_id = $assign_row['id'];
            
            // Parse scheduled start time
            $scheduled_datetime = date('Y-m-d H:i:s', strtotime($scheduled_start_time));
            
            // Create Zoom class
            $insert_query = "INSERT INTO zoom_classes (teacher_assignment_id, title, description, zoom_meeting_link, zoom_meeting_id, zoom_passcode, scheduled_start_time, free_class, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("issssssi", $teacher_assignment_id, $title, $description, $zoom_meeting_link, $zoom_meeting_id, $zoom_passcode, $scheduled_datetime, $free_class);
        
            if ($insert_stmt->execute()) {
                header('Location: live_classes.php?success=' . urlencode('Zoom class created successfully!'));
                exit;
            } else {
                $error_message = 'Error creating Zoom class: ' . $conn->error;
            }
            $insert_stmt->close();
        } else {
            $error_message = 'You do not have an active assignment for this subject and academic year.';
        }
        $assign_stmt->close();
    }
}

// Get live classes based on role
$student_enrollments = [];
$teacher_assignments = [];
$live_classes_by_enrollment = [];
$live_classes_by_assignment = [];
$ongoing_live_classes_by_assignment = [];

// Guest User Logic - Fetch ALL active live classes
if (empty($role)) {
    $guest_live_classes = [];
    
    // Fetch all ongoing or scheduled live classes with teacher and subject info
    $query = "SELECT r.id, r.title, r.description, r.status, r.scheduled_start_time, 
                     r.actual_start_time, r.end_time, r.created_at, r.thumbnail_url, r.youtube_video_id, r.free_video,
                     ta.teacher_id, ta.academic_year, ta.stream_subject_id,
                     u.first_name, u.second_name,
                     s.name as stream_name, sub.name as subject_name, sub.code as subject_code
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              WHERE r.is_live = 1 
                AND r.status IN ('ongoing', 'scheduled')
              ORDER BY 
                CASE r.status
                  WHEN 'ongoing' THEN 1
                  WHEN 'scheduled' THEN 2
                  ELSE 3
                END,
                r.scheduled_start_time DESC";
                
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $guest_live_classes[] = $row;
        }
    }
} elseif ($role === 'student') {
    // Get student enrollments
    $query = "SELECT se.id, se.stream_subject_id, se.academic_year, se.batch_name, se.enrolled_date,
                     se.status, se.notes,
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
    
    while ($row = $result->fetch_assoc()) {
        $enrollment_id = $row['id'];
        $stream_subject_id = $row['stream_subject_id'];
        $academic_year = $row['academic_year'];
        
        // Get live classes for this enrollment
        $live_query = "SELECT r.id, r.title, r.description, r.status, r.scheduled_start_time, 
                              r.actual_start_time, r.end_time, r.created_at, r.thumbnail_url, r.youtube_video_id, r.free_video,
                              ta.teacher_id, u.first_name, u.second_name
                       FROM recordings r
                       INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                       INNER JOIN users u ON ta.teacher_id = u.user_id
                       WHERE ta.stream_subject_id = ? 
                         AND ta.academic_year = ?
                         AND r.is_live = 1 
                         AND r.status != 'inactive'
                       ORDER BY 
                         CASE r.status
                           WHEN 'ongoing' THEN 1
                           WHEN 'scheduled' THEN 2
                           WHEN 'ended' THEN 3
                           WHEN 'cancelled' THEN 4
                           ELSE 5
                         END,
                         r.scheduled_start_time DESC, r.created_at DESC";
        
        $live_stmt = $conn->prepare($live_query);
        $live_stmt->bind_param("ii", $stream_subject_id, $academic_year);
        $live_stmt->execute();
        $live_result = $live_stmt->get_result();
        
        $live_classes = [];
        while ($live_row = $live_result->fetch_assoc()) {
            $live_classes[] = $live_row;
        }
        $live_stmt->close();
        
        // Get Zoom classes for this enrollment
        $zoom_query = "SELECT zc.id, zc.title, zc.description, zc.status, zc.scheduled_start_time, 
                              zc.actual_start_time, zc.end_time, zc.created_at, zc.free_class, zc.zoom_meeting_link,
                              ta.teacher_id, u.first_name, u.second_name
                       FROM zoom_classes zc
                       INNER JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
                       INNER JOIN users u ON ta.teacher_id = u.user_id
                       WHERE ta.stream_subject_id = ? 
                         AND ta.academic_year = ?
                         AND zc.status != 'cancelled'
                       ORDER BY 
                         CASE zc.status
                           WHEN 'ongoing' THEN 1
                           WHEN 'scheduled' THEN 2
                           WHEN 'ended' THEN 3
                           ELSE 4
                         END,
                         zc.scheduled_start_time DESC, zc.created_at DESC";
        
        $zoom_stmt = $conn->prepare($zoom_query);
        $zoom_stmt->bind_param("ii", $stream_subject_id, $academic_year);
        $zoom_stmt->execute();
        $zoom_result = $zoom_stmt->get_result();
        
        while ($zoom_row = $zoom_result->fetch_assoc()) {
            $zoom_row['is_zoom'] = true; // Mark as Zoom class
            $live_classes[] = $zoom_row; // Add to live classes array for unified display
        }
        $zoom_stmt->close();
        
        if (!empty($live_classes)) {
            $live_classes_by_enrollment[$enrollment_id] = $live_classes;
        }
        
        $student_enrollments[] = $row;
    }
    $stmt->close();
} elseif ($role === 'teacher') {
    // Get teacher assignments with live classes
    $query = "SELECT ta.id, ta.stream_subject_id, ta.academic_year, ta.batch_name, ta.status, 
                     ta.assigned_date, ta.start_date, ta.end_date, ta.notes,
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
    
    while ($row = $result->fetch_assoc()) {
        $assignment_id = $row['id'];
        $stream_subject_id = $row['stream_subject_id'];
        $academic_year = $row['academic_year'];
        
        // Get live classes for this assignment
        $live_query = "SELECT r.id, r.title, r.description, r.status, r.scheduled_start_time, 
                              r.actual_start_time, r.end_time, r.created_at, r.thumbnail_url, r.youtube_video_id, r.free_video
                       FROM recordings r
                       WHERE r.teacher_assignment_id = ? 
                         AND r.is_live = 1 
                         AND r.status != 'inactive'
                       ORDER BY 
                         CASE r.status
                           WHEN 'ongoing' THEN 1
                           WHEN 'scheduled' THEN 2
                           WHEN 'ended' THEN 3
                           WHEN 'cancelled' THEN 4
                           ELSE 5
                         END,
                         r.scheduled_start_time DESC, r.created_at DESC";
        
        $live_stmt = $conn->prepare($live_query);
        $live_stmt->bind_param("i", $assignment_id);
        $live_stmt->execute();
        $live_result = $live_stmt->get_result();
        
        $live_classes = [];
        $ongoing_classes = [];
        while ($live_row = $live_result->fetch_assoc()) {
            $live_classes[] = $live_row;
            if ($live_row['status'] === 'ongoing') {
                $ongoing_classes[] = $live_row;
            }
        }
        $live_stmt->close();
        
        // Get Zoom classes for this assignment
        $zoom_query = "SELECT zc.id, zc.title, zc.description, zc.status, zc.scheduled_start_time, 
                              zc.actual_start_time, zc.end_time, zc.created_at, zc.free_class, zc.zoom_meeting_link
                       FROM zoom_classes zc
                       WHERE zc.teacher_assignment_id = ? 
                         AND zc.status != 'cancelled'
                       ORDER BY 
                         CASE zc.status
                           WHEN 'ongoing' THEN 1
                           WHEN 'scheduled' THEN 2
                           WHEN 'ended' THEN 3
                           ELSE 4
                         END,
                         zc.scheduled_start_time DESC, zc.created_at DESC";
        
        $zoom_stmt = $conn->prepare($zoom_query);
        $zoom_stmt->bind_param("i", $assignment_id);
        $zoom_stmt->execute();
        $zoom_result = $zoom_stmt->get_result();
        
        $zoom_classes = [];
        while ($zoom_row = $zoom_result->fetch_assoc()) {
            $zoom_row['is_zoom'] = true; // Mark as Zoom class
            $live_classes[] = $zoom_row; // Add to live classes array for unified display
            if ($zoom_row['status'] === 'ongoing') {
                $ongoing_classes[] = $zoom_row;
            }
        }
        $zoom_stmt->close();
        
        if (!empty($live_classes)) {
            $live_classes_by_assignment[$assignment_id] = $live_classes;
        }
        if (!empty($ongoing_classes)) {
            $ongoing_live_classes_by_assignment[$assignment_id] = $ongoing_classes;
        }
        
        $teacher_assignments[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Classes - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            <?php if (!empty($live_classes_background)): ?>
            background-image: url('../<?php echo htmlspecialchars($live_classes_background); ?>');
            background-size: cover; background-position: center; background-attachment: fixed; background-repeat: no-repeat;
            <?php endif; ?>
        }
        .content-overlay {
            background: linear-gradient(to bottom, rgba(243, 244, 246, 0.4), rgba(243, 244, 246, 0.6));
            backdrop-filter: blur(8px);
            min-height: 100vh;
        }
        .premium-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .premium-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            background: rgba(255, 255, 255, 1);
        }
        .zoom-placeholder {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            position: relative; overflow: hidden;
        }
        .zoom-placeholder::after {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .live-pulse {
            position: relative; display: flex; align-items: center; gap: 4px;
            background: rgba(220, 38, 38, 0.9); color: white; padding: 2px 8px; border-radius: 9999px;
            font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;
        }
        .live-pulse::before {
            content: ''; width: 6px; height: 6px; background: white; border-radius: 50%;
            animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.2); } 100% { opacity: 1; transform: scale(1); } }
        .glass-card-header {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="content-overlay">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Welcome Section -->
            

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

            <!-- Public Live Classes Section (for Guests) -->
            <?php if (empty($role)): ?>
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-white mb-6 p-4 rounded-lg bg-red-600/90 backdrop-blur-sm shadow-lg flex items-center">
                        <svg class="w-8 h-8 mr-3 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        Available Live Classes
                    </h2>
                    
                    <?php if (empty($guest_live_classes)): ?>
                        <div class="glass-card rounded-xl p-12 text-center text-gray-500">
                            <svg class="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <h3 class="text-xl font-bold text-gray-700 mb-2">No Live Classes Available</h3>
                            <p class="text-gray-500">Check back later for upcoming sessions.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($guest_live_classes as $live_class): ?>
                                <?php
                                $status_colors = [
                                    'scheduled' => 'bg-yellow-100 text-yellow-800',
                                    'ongoing' => 'bg-green-100 text-green-800'
                                ];
                                $status_color = $status_colors[$live_class['status']] ?? 'bg-gray-100 text-gray-800';
                                
                                // Get thumbnail URL
                                $thumb_url = $live_class['thumbnail_url'] ?? null;
                                if (empty($thumb_url) && !empty($live_class['youtube_video_id'])) {
                                    $thumb_url = "https://img.youtube.com/vi/{$live_class['youtube_video_id']}/maxresdefault.jpg";
                                }
                                ?>
                                <div class="bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 overflow-hidden group">
                                    <!-- Thumbnail -->
                                    <div class="relative aspect-video bg-gray-200 overflow-hidden">
                                        <?php if ($thumb_url): ?>
                                            <img src="<?php echo htmlspecialchars($thumb_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($live_class['title']); ?>"
                                                 class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-500 to-red-700">
                                                <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Badges -->
                                        <div class="absolute top-3 left-3 flex gap-2">
                                            <span class="px-2 py-1 rounded-md text-xs font-bold shadow-sm backdrop-blur-md <?php echo $status_color; ?>">
                                                <?php echo ucfirst($live_class['status']); ?>
                                            </span>
                                            <?php if ($live_class['free_video']): ?>
                                                <span class="px-2 py-1 rounded-md text-xs font-bold bg-blue-100 text-blue-800 shadow-sm">
                                                    Free
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($live_class['status'] === 'ongoing'): ?>
                                            <div class="absolute top-3 right-3">
                                                <span class="flex h-3 w-3 relative">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Content -->
                                    <div class="p-5">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-semibold text-red-600 uppercase tracking-wider">
                                                <?php echo htmlspecialchars($live_class['subject_name']); ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($live_class['stream_name']); ?>
                                            </span>
                                        </div>
                                        
                                        <h3 class="font-bold text-lg text-gray-900 mb-2 line-clamp-2 leading-tight group-hover:text-red-700 transition-colors">
                                            <?php echo htmlspecialchars($live_class['title']); ?>
                                        </h3>
                                        
                                        <div class="flex items-center mb-4">
                                            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xs font-bold mr-2">
                                                <?php echo substr($live_class['first_name'] ?? 'T', 0, 1); ?>
                                            </div>
                                            <span class="text-sm text-gray-600 truncate">
                                                <?php echo htmlspecialchars(trim(($live_class['first_name'] ?? '') . ' ' . ($live_class['second_name'] ?? ''))); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Action Button -->
                                        <?php if ($role): ?>
                                            <!-- Logic handled in role section actually, but for guest: -->
                                            <!-- This block is only for guests so role is empty -->
                                        <?php endif; ?>
                                        
                                        <div class="mt-4">
                                            <?php if (empty($role)): // Show Login/Join buttons for guest ?>
                                                <div class="flex gap-2">
                                                    <?php if ($live_class['free_video']): ?>
                                                        <a href="../player/player.php?id=<?php echo $live_class['id']; ?>&stream_subject_id=<?php echo $live_class['stream_subject_id']; ?>&academic_year=<?php echo $live_class['academic_year']; ?>" 
                                                        class="flex-1 bg-red-600 text-white text-center py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors shadow-md hover:shadow-lg">
                                                            Watch Now
                                                        </a>
                                                    <?php else: ?>
                                                        <button onclick="showAuthModal()" 
                                                                class="flex-1 bg-gray-800 text-white text-center py-2 rounded-lg font-semibold hover:bg-gray-900 transition-colors shadow-md">
                                                            Login to Watch
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- My Live Classes Section (for both students and teachers) -->
            <?php if ($role === 'student'): ?>
                <?php 
                // Collect all live classes from all enrollments for a unified view
                $all_student_live_classes = [];
                foreach ($student_enrollments as $enrollment) {
                    if (isset($live_classes_by_enrollment[$enrollment['id']])) {
                        foreach ($live_classes_by_enrollment[$enrollment['id']] as $class) {
                            $class['subject_name'] = $enrollment['subject_name'];
                            $class['subject_code'] = $enrollment['subject_code'];
                            $class['enrollment_data'] = $enrollment;
                            $all_student_live_classes[] = $class;
                        }
                    }
                }

                // Sort unified list: ongoing -> scheduled -> ended -> cancelled
                usort($all_student_live_classes, function($a, $b) {
                    $priority = ['ongoing' => 1, 'scheduled' => 2, 'ended' => 3, 'cancelled' => 4];
                    $p1 = $priority[$a['status']] ?? 9;
                    $p2 = $priority[$b['status']] ?? 9;
                    if ($p1 !== $p2) return $p1 - $p2;
                    return strtotime($b['scheduled_start_time'] ?? $b['created_at']) - strtotime($a['scheduled_start_time'] ?? $a['created_at']);
                });
                ?>

                <?php if (empty($all_student_live_classes)): ?>
                    <div class="mb-8">
                        <div class="glass-card-header rounded-3xl p-16 text-center shadow-xl border border-white/40">
                            <div class="w-24 h-24 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                <svg class="w-12 h-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            </div>
                            <h3 class="text-2xl font-black text-gray-900 mb-2">No Live Classes Right Now</h3>
                            <p class="text-gray-500 max-w-sm mx-auto">We couldn't find any ongoing or scheduled sessions for your current enrollments.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h1 class="text-3xl font-black text-gray-900 tracking-tight">Live Classes</h1>
                                <p class="text-white font-medium">Ongoing and upcoming sessions from your enrollments</p>
                            </div>
                        </div>

                        <!-- Unified Live Classes Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                            <?php foreach ($all_student_live_classes as $live_class): ?>
                                <?php
                                $status_colors = [
                                    'scheduled' => 'bg-yellow-100 text-yellow-800',
                                    'ongoing' => 'bg-green-100 text-green-800',
                                    'ended' => 'bg-gray-100 text-gray-800',
                                    'cancelled' => 'bg-red-100 text-red-800'
                                ];
                                $status_color = $status_colors[$live_class['status']] ?? 'bg-gray-100 text-gray-800';
                                $can_join = ($live_class['status'] === 'ongoing' || $live_class['status'] === 'scheduled');
                                $enrollment_data = $live_class['enrollment_data'];
                                
                                // Get thumbnail URL
                                $thumb_url = $live_class['thumbnail_url'] ?? null;
                                if (empty($thumb_url) && !empty($live_class['youtube_video_id'])) {
                                    $thumb_url = "https://img.youtube.com/vi/{$live_class['youtube_video_id']}/maxresdefault.jpg";
                                }
                                ?>
                                <div class="premium-card rounded-2xl shadow-sm overflow-hidden border-t-4 <?php echo $live_class['status'] === 'ongoing' ? 'border-red-600' : 'border-blue-600'; ?> max-w-[360px] mx-auto w-full flex flex-col">
                                    <!-- Thumbnail or Live Badge -->
                                    <div class="relative aspect-video bg-gray-100">
                                        <?php if ($thumb_url): ?>
                                            <img src="<?php echo htmlspecialchars($thumb_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($live_class['title']); ?>"
                                                 class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center zoom-placeholder">
                                                <svg class="w-12 h-12 text-white/90 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Status Badge -->
                                        <div class="absolute top-3 left-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-bold tracking-tight uppercase <?php echo $status_color; ?> shadow-sm">
                                                <?php echo ucfirst($live_class['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Live Indicator -->
                                        <?php if ($live_class['status'] === 'ongoing'): ?>
                                            <div class="absolute top-3 right-3">
                                                <div class="live-pulse shadow-lg">LIVE</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Live Class Info -->
                                    <div class="p-5 flex-1 flex flex-col">
                                        <div class="mb-3">
                                            <span class="text-[10px] font-black uppercase tracking-widest text-blue-600 mb-1 block">
                                                <?php echo htmlspecialchars($live_class['subject_name']); ?>
                                            </span>
                                            <h3 class="text-base font-bold text-gray-900 line-clamp-2 leading-snug" title="<?php echo htmlspecialchars($live_class['title']); ?>">
                                                <?php echo htmlspecialchars($live_class['title']); ?>
                                            </h3>
                                        </div>
                                        
                                        <div class="flex flex-wrap items-center gap-2 text-gray-500 mb-4 mt-auto">
                                            <?php if ($live_class['scheduled_start_time']): ?>
                                                <div class="flex items-center text-[11px] font-semibold bg-gray-50 px-2 py-1 rounded-md">
                                                    <svg class="w-3.5 h-3.5 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    <?php echo date('M d, H:i', strtotime($live_class['scheduled_start_time'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($live_class['is_zoom']) && $live_class['is_zoom']): ?>
                                                <span class="px-2 py-1 bg-indigo-50 text-indigo-700 text-[10px] rounded-md font-bold uppercase border border-indigo-100">
                                                    Zoom
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                            <?php if ($can_join): ?>
                                                <?php 
                                                $player_url = isset($live_class['is_zoom']) && $live_class['is_zoom'] 
                                                    ? "../player/zoom.php?id=" . $live_class['id']
                                                    : "../player/player.php?id=" . $live_class['id'] . "&stream_subject_id=" . $enrollment_data['stream_subject_id'] . "&academic_year=" . $enrollment_data['academic_year'];
                                                ?>
                                                <a href="<?php echo $player_url; ?>" 
                                                   class="flex items-center px-5 py-2 <?php echo $live_class['status'] === 'ongoing' ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-900 hover:bg-black'; ?> text-white text-[11px] rounded-xl font-bold transition-all shadow-md active:scale-95">
                                                    <?php echo $live_class['status'] === 'ongoing' ? 'Join Now' : 'Enter Class'; ?>
                                                    <svg class="w-3.5 h-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs font-semibold text-gray-400">
                                                    <?php echo $live_class['status'] === 'ended' ? 'Session Ended' : 'Cancelled'; ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($live_class['first_name'])): ?>
                                                <div class="flex items-center gap-2" title="<?php echo htmlspecialchars($live_class['first_name']); ?>">
                                                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-[10px] font-black text-white shadow-sm ring-2 ring-white">
                                                        <?php echo substr($live_class['first_name'], 0, 1); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif ($role === 'teacher'): ?>
                <!-- Show My Live Classes First -->
                <?php if (!empty($live_classes_by_assignment)): ?>
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-white mb-4 bg-red-700 p-3">My Live Classes</h2>
                <?php foreach ($teacher_assignments as $assignment): ?>
                    <?php if (isset($live_classes_by_assignment[$assignment['id']])): ?>
                        <div class="mb-8">
                            <!-- Assignment Header -->
                            <div class="glass-card-header rounded-2xl p-6 mb-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-2xl font-black text-gray-900 flex items-center gap-3 mb-2">
                                            <span class="w-2 h-8 bg-blue-600 rounded-full shadow-sm"></span>
                                            <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                            <?php if ($assignment['subject_code']): ?>
                                                <span class="text-gray-400 font-bold text-lg tracking-tight">(<?php echo htmlspecialchars($assignment['subject_code']); ?>)</span>
                                            <?php endif; ?>
                                        </h2>
                                        <div class="flex items-center gap-4 text-sm font-medium text-gray-500">
                                            <span class="flex items-center px-3 py-1 bg-white/50 rounded-full border border-gray-100 shadow-sm">
                                                <svg class="w-4 h-4 mr-1.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                                <?php echo htmlspecialchars($assignment['stream_name']); ?>
                                            </span>
                                            <span class="flex items-center px-3 py-1 bg-white/50 rounded-full border border-gray-100 shadow-sm">
                                                <svg class="w-4 h-4 mr-1.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                <?php echo htmlspecialchars($assignment['academic_year']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                       <!-- Live Classes Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php 
                                // Sort live classes: ongoing first, then by status priority
                                $sorted_classes = $live_classes_by_assignment[$assignment['id']];
                                usort($sorted_classes, function($a, $b) {
                                    $status_priority = ['ongoing' => 1, 'scheduled' => 2, 'ended' => 3, 'cancelled' => 4];
                                    $a_priority = $status_priority[$a['status']] ?? 5;
                                    $b_priority = $status_priority[$b['status']] ?? 5;
                                    if ($a_priority !== $b_priority) {
                                        return $a_priority - $b_priority;
                                    }
                                    return strtotime($b['scheduled_start_time'] ?? $b['created_at']) - strtotime($a['scheduled_start_time'] ?? $a['created_at']);
                                });
                                ?>
                                <?php foreach ($sorted_classes as $live_class): ?>
                                    <?php
                                    $status_colors = [
                                        'scheduled' => 'bg-yellow-100 text-yellow-800',
                                        'ongoing' => 'bg-green-100 text-green-800',
                                        'ended' => 'bg-gray-100 text-gray-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $status_color = $status_colors[$live_class['status']] ?? 'bg-gray-100 text-gray-800';
                                    $can_join = ($live_class['status'] === 'ongoing' || $live_class['status'] === 'scheduled');
                                    
                                    // Get thumbnail URL
                                    $thumb_url = $live_class['thumbnail_url'] ?? null;
                                    if (empty($thumb_url) && !empty($live_class['youtube_video_id'])) {
                                        $thumb_url = "https://img.youtube.com/vi/{$live_class['youtube_video_id']}/maxresdefault.jpg";
                                    }
                                    ?>
                                    <div class="premium-card rounded-2xl shadow-sm overflow-hidden border-t-4 <?php echo $live_class['status'] === 'ongoing' ? 'border-red-600' : 'border-blue-600'; ?> max-w-[360px] mx-auto w-full">
                                        <!-- Thumbnail or Live Badge -->
                                        <div class="relative aspect-video bg-gray-100">
                                            <?php if ($thumb_url): ?>
                                                <img src="<?php echo htmlspecialchars($thumb_url); ?>" 
                                                     alt="<?php echo htmlspecialchars($live_class['title']); ?>"
                                                     class="w-full h-full object-cover"
                                                     onerror="this.src='<?php echo !empty($live_class['youtube_video_id']) ? "https://img.youtube.com/vi/{$live_class['youtube_video_id']}/hqdefault.jpg" : ""; ?>'; this.onerror=null;">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center <?php echo isset($live_class['is_zoom']) ? 'zoom-placeholder' : 'bg-gradient-to-br from-blue-600 to-blue-800'; ?>">
                                                    <svg class="w-12 h-12 text-white/90 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Status Badge -->
                                            <div class="absolute top-3 left-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-bold tracking-tight uppercase <?php echo $status_color; ?> shadow-sm">
                                                    <?php echo ucfirst($live_class['status']); ?>
                                                </span>
                                            </div>
                                            <!-- Live Indicator -->
                                            <?php if ($live_class['status'] === 'ongoing'): ?>
                                                <div class="absolute top-3 right-3">
                                                    <div class="live-pulse shadow-lg">LIVE</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Live Class Info -->
                                        <div class="p-5">
                                            <h3 class="text-lg font-bold text-gray-900 line-clamp-2 mb-2 leading-snug" title="<?php echo htmlspecialchars($live_class['title']); ?>">
                                                <?php echo htmlspecialchars($live_class['title']); ?>
                                            </h3>
                                            
                                            <div class="flex flex-wrap items-center gap-3 text-gray-500 mb-4">
                                                <?php if ($live_class['scheduled_start_time']): ?>
                                                    <div class="flex items-center text-[11px] font-medium">
                                                        <svg class="w-3.5 h-3.5 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        <?php echo date('M d, H:i', strtotime($live_class['scheduled_start_time'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($live_class['is_zoom']) && $live_class['is_zoom']): ?>
                                                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[10px] rounded-md font-bold uppercase border border-indigo-100">
                                                        Zoom
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="flex items-center justify-between mt-auto pt-2 border-t border-gray-50">
                                                <?php if ($can_join): ?>
                                                    <?php 
                                                    $player_url = isset($live_class['is_zoom']) && $live_class['is_zoom'] 
                                                        ? "../player/zoom.php?id=" . $live_class['id']
                                                        : "../player/player.php?id=" . $live_class['id'] . "&stream_subject_id=" . $assignment['stream_subject_id'] . "&academic_year=" . $assignment['academic_year'];
                                                    ?>
                                                    <a href="<?php echo $player_url; ?>" 
                                                       class="flex items-center px-5 py-2 <?php echo $live_class['status'] === 'ongoing' ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-900 hover:bg-black'; ?> text-white text-xs rounded-xl font-bold transition-all shadow-md active:scale-95">
                                                        <?php echo $live_class['status'] === 'ongoing' ? 'Join Now' : 'Enter Class'; ?>
                                                        <svg class="w-3.5 h-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-xs font-semibold text-gray-400">
                                                        <?php echo $live_class['status'] === 'ended' ? 'Session Ended' : 'Cancelled'; ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <div class="flex gap-2">
                                                    <?php if (isset($live_class['is_zoom']) && $live_class['is_zoom']): ?>
                                                        <span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-xs rounded font-semibold">
                                                            <i class="fas fa-video mr-1"></i>Zoom
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($live_class['status'] === 'scheduled' && (!isset($live_class['is_zoom']) || !$live_class['is_zoom'])): ?>
                                                        <button onclick="startLiveClass(<?php echo $live_class['id']; ?>)" 
                                                                class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                                            Start
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($live_class['status'] === 'scheduled' || $live_class['status'] === 'cancelled'): ?>
                                                        <button onclick="<?php echo isset($live_class['is_zoom']) && $live_class['is_zoom'] ? 'deleteZoomClass' : 'deleteLiveClass'; ?>(<?php echo $live_class['id']; ?>, '<?php echo htmlspecialchars(addslashes($live_class['title'])); ?>')" 
                                                                class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                                            Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-white mb-4 bg-red-700 p-3">My Live Classes</h2>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No live classes found.</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Show Teacher Assignments (Subjects) -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-white mb-4 bg-red-700 p-3">My Subjects</h2>
                    <?php if (empty($teacher_assignments)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No active teaching subjects found.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($teacher_assignments as $assignment): ?>
                                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow border-l-4 border-blue-500 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($assignment['subject_name']); ?></h3>
                                            <?php if ($assignment['subject_code']): ?>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($assignment['subject_code']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 mb-4">
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                            </svg>
                                            <span class="font-medium">Stream:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($assignment['stream_name']); ?></span>
                                        </div>
                                        
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span class="font-medium">Academic Year:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($assignment['academic_year']); ?></span>
                                        </div>
                                        
                                        <?php if ($assignment['batch_name']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zm-7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                                <span class="font-medium">Batch:</span>
                                                <span class="ml-2"><?php echo htmlspecialchars($assignment['batch_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-4 flex gap-2">
                                        <button onclick="openCreateLiveClassModal(<?php echo $assignment['stream_subject_id']; ?>, <?php echo $assignment['academic_year']; ?>, '<?php echo htmlspecialchars(addslashes($assignment['subject_name'])); ?>', '<?php echo htmlspecialchars(addslashes($assignment['stream_name'])); ?>')" 
                                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center text-sm">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                            </svg>
                                            Create Live Class
                                        </button>
                                        <button onclick="openCreateZoomClassModal(<?php echo $assignment['stream_subject_id']; ?>, <?php echo $assignment['academic_year']; ?>, '<?php echo htmlspecialchars(addslashes($assignment['subject_name'])); ?>', '<?php echo htmlspecialchars(addslashes($assignment['stream_name'])); ?>')" 
                                                class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center justify-center text-sm">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                            </svg>
                                            Create Zoom Class
                                        </button>
                                    </div>
                                </div>
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

    <!-- Create Live Class Modal (Teachers Only) -->
    <?php if ($role === 'teacher'): ?>
        <div id="createLiveClassModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Create Live Class</h3>
                    <button onclick="closeCreateLiveClassModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="" id="createLiveClassForm" class="space-y-4">
                    <input type="hidden" name="create_live_class" value="1">
                    <input type="hidden" id="live_stream_subject_id" name="stream_subject_id" value="">
                    <input type="hidden" id="live_academic_year" name="academic_year" value="">
                    
                    <!-- Selected Subject Info (Read-only display) -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-blue-900 mb-2">Selected Subject:</h4>
                        <p class="text-blue-800" id="selected_subject_info">-</p>
                    </div>
                    
                    <!-- Title -->
                    <div>
                        <label for="live_title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" id="live_title" name="title" required
                               placeholder="e.g., Live Class: Introduction to Calculus"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label for="live_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="live_description" name="description" rows="4"
                                  placeholder="Describe what will be covered in this live class..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    
                    <!-- YouTube Live Class Link -->
                    <div>
                        <label for="live_youtube_url" class="block text-sm font-medium text-gray-700 mb-1">YouTube Live Class Link *</label>
                        <input type="url" id="live_youtube_url" name="youtube_url" required
                               placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Enter the YouTube live stream URL</p>
                    </div>
                    
                    <!-- Scheduled Start Time -->
                    <div>
                        <label for="live_scheduled_start_time" class="block text-sm font-medium text-gray-700 mb-1">Scheduled Start Time (Optional)</label>
                        <input type="datetime-local" id="live_scheduled_start_time" name="scheduled_start_time"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to start immediately when you click "Start Live Class"</p>
                    </div>
                    
                    <!-- Free Video or Payment Required -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Access Type *</label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 border border-gray-300 rounded-md cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="free_video" value="1" checked class="mr-3 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="font-medium text-gray-900">Free - Anyone can watch</span>
                                    <p class="text-xs text-gray-500">All enrolled students can watch this live class without payment</p>
                                </div>
                            </label>
                            <label class="flex items-center p-3 border border-gray-300 rounded-md cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="free_video" value="0" class="mr-3 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="font-medium text-gray-900">Requires Payment</span>
                                    <p class="text-xs text-gray-500">Only students who have paid for the month of the live class can watch</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="closeCreateLiveClassModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Create Live Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Create Zoom Class Modal (Teachers Only) -->
    <?php if ($role === 'teacher'): ?>
        <div id="createZoomClassModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Create Zoom Class</h3>
                    <button onclick="closeCreateZoomClassModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="" id="createZoomClassForm" class="space-y-4">
                    <input type="hidden" name="create_zoom_class" value="1">
                    <input type="hidden" id="zoom_stream_subject_id" name="stream_subject_id" value="">
                    <input type="hidden" id="zoom_academic_year" name="academic_year" value="">
                    
                    <!-- Selected Subject Info -->
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-indigo-900 mb-2">Selected Subject:</h4>
                        <p class="text-indigo-800" id="zoom_selected_subject_info">-</p>
                    </div>
                    
                    <!-- Title -->
                    <div>
                        <label for="zoom_title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" id="zoom_title" name="title" required
                               placeholder="e.g., Zoom Class: Advanced Mathematics"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label for="zoom_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="zoom_description" name="description" rows="3"
                                  placeholder="Describe what will be covered in this Zoom class..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    
                    <!-- Zoom Meeting Link -->
                    <div>
                        <label for="zoom_meeting_link" class="block text-sm font-medium text-gray-700 mb-1">Zoom Meeting Link *</label>
                        <input type="url" id="zoom_meeting_link" name="zoom_meeting_link" required
                               placeholder="https://zoom.us/j/1234567890"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-xs text-gray-500 mt-1">Enter the Zoom meeting URL</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Meeting ID -->
                        <div>
                            <label for="zoom_meeting_id" class="block text-sm font-medium text-gray-700 mb-1">Meeting ID (Optional)</label>
                            <input type="text" id="zoom_meeting_id" name="zoom_meeting_id"
                                   placeholder="123 456 7890"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <!-- Passcode -->
                        <div>
                            <label for="zoom_passcode" class="block text-sm font-medium text-gray-700 mb-1">Passcode (Optional)</label>
                            <input type="text" id="zoom_passcode" name="zoom_passcode"
                                   placeholder="abc123"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <!-- Scheduled Start Time -->
                    <div>
                        <label for="zoom_scheduled_start_time" class="block text-sm font-medium text-gray-700 mb-1">Scheduled Start Time *</label>
                        <input type="datetime-local" id="zoom_scheduled_start_time" name="scheduled_start_time" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-xs text-gray-500 mt-1">When should this Zoom class start?</p>
                    </div>
                    
                    <!-- Free Class or Payment Required -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Access Type *</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="free_class" value="0" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">
                                    <span class="font-medium">Paid Class</span> - Only students who have paid for the month can access
                                </span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="free_class" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">
                                    <span class="font-medium">Free Class</span> - All enrolled students (and guests) can access
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="closeCreateZoomClassModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Create Zoom Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Login/Register Popup for Guests -->
    <?php if (empty($role)): ?>
    <div id="authModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-75 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-8 max-w-md mx-4">
            <div class="text-center mb-6">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Authentication Required</h3>
                <p class="text-gray-600">Please log in or register to watch this live class.</p>
            </div>
            
            <div class="space-y-3">
                <a href="dashboard.php#login-section" class="block w-full bg-red-600 text-white text-center py-3 px-4 rounded-lg hover:bg-red-700 font-semibold transition shadow-md">
                    Login
                </a>
                <a href="../register.php" class="block w-full bg-gray-600 text-white text-center py-3 px-4 rounded-lg hover:bg-gray-700 font-semibold transition shadow-md">
                    Register
                </a>
            </div>
            
            <button onclick="closeAuthModal()" class="w-full mt-4 text-gray-500 hover:text-gray-700 py-2 text-sm font-medium">
                Cancel
            </button>
        </div>
    </div>

    <script>
        function showAuthModal() {
            document.getElementById('authModal').classList.remove('hidden');
        }

        function closeAuthModal() {
            document.getElementById('authModal').classList.add('hidden');
        }
        
        // Close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('authModal');
            if (event.target == modal) {
                closeAuthModal();
            }
        }
    </script>
    <?php endif; ?>

    <script>
        // Toast notification function
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
                    if (toast.parentElement) {
                        toast.parentElement.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }

        // Create Live Class Modal Functions
        <?php if ($role === 'teacher'): ?>
        function openCreateLiveClassModal(streamSubjectId, academicYear, subjectName, streamName) {
            document.getElementById('live_stream_subject_id').value = streamSubjectId;
            document.getElementById('live_academic_year').value = academicYear;
            document.getElementById('selected_subject_info').textContent = subjectName + ' - ' + streamName + ' (' + academicYear + ')';
            document.getElementById('createLiveClassModal').classList.remove('hidden');
        }

        function closeCreateLiveClassModal() {
            document.getElementById('createLiveClassModal').classList.add('hidden');
            document.getElementById('createLiveClassForm').reset();
        }

        // Zoom Class Modal Functions
        function openCreateZoomClassModal(streamSubjectId, academicYear, subjectName, streamName) {
            document.getElementById('zoom_stream_subject_id').value = streamSubjectId;
            document.getElementById('zoom_academic_year').value = academicYear;
            document.getElementById('zoom_selected_subject_info').textContent = subjectName + ' - ' + streamName + ' (' + academicYear + ')';
            document.getElementById('createZoomClassModal').classList.remove('hidden');
        }

        function closeCreateZoomClassModal() {
            document.getElementById('createZoomClassModal').classList.add('hidden');
            document.getElementById('createZoomClassForm').reset();
        }

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

        function deleteZoomClass(zoomClassId, title) {
            if (!confirm(`Are you sure you want to delete Zoom class "${title}"? This action cannot be undone.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('zoom_class_id', zoomClassId);
            
            fetch('../player/end_zoom_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Zoom class deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Error deleting Zoom class', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting Zoom class. Please try again.', 'error');
            });
        }

        // Close modal on outside click
        document.getElementById('createLiveClassModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateLiveClassModal();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

