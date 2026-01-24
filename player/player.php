<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');
$current_month = date('n');

// Get video ID from URL
$recording_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stream_subject_id = isset($_GET['stream_subject_id']) ? intval($_GET['stream_subject_id']) : 0;
$academic_year = isset($_GET['academic_year']) ? intval($_GET['academic_year']) : date('Y');

$current_recording = null;
$other_recordings = [];

if ($recording_id > 0) {
    // Get current recording details - handle both regular recordings and live classes
    $query = "SELECT r.id, r.title, r.description, r.youtube_video_id, r.youtube_url, r.thumbnail_url, 
                     r.created_at, r.free_video, r.watch_limit, r.is_live, r.status,
                     r.scheduled_start_time, r.actual_start_time, r.end_time,
                     ta.stream_subject_id, ta.academic_year, ta.teacher_id,
                     s.name as stream_name, sub.name as subject_name,
                     u.first_name, u.second_name, u.profile_picture
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              WHERE r.id = ? AND (r.status = 'active' OR (r.is_live = 1 AND r.status IN ('scheduled', 'ongoing', 'ended', 'cancelled')))";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $recording_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $current_recording = $result->fetch_assoc();
        $stream_subject_id = $current_recording['stream_subject_id'];
        $academic_year = $current_recording['academic_year'];
        
        // Get current month from recording
        $recording_month = date('n', strtotime($current_recording['created_at']));
        $recording_year = date('Y', strtotime($current_recording['created_at']));
        
        // Get other recordings from the same month
        $other_query = "SELECT r.id, r.title, r.thumbnail_url, r.youtube_video_id, r.created_at, r.free_video
                       FROM recordings r
                       INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                       WHERE ta.stream_subject_id = ? 
                         AND ta.academic_year = ?
                         AND r.status = 'active'
                         AND MONTH(r.created_at) = ?
                         AND YEAR(r.created_at) = ?
                         AND r.id != ?
                       ORDER BY r.created_at ASC";
        
        $other_stmt = $conn->prepare($other_query);
        $other_stmt->bind_param("iiiii", $stream_subject_id, $academic_year, $recording_month, $recording_year, $recording_id);
        $other_stmt->execute();
        $other_result = $other_stmt->get_result();
        
        while ($row = $other_result->fetch_assoc()) {
            // Double-check: ensure current video is not included
            if ($row['id'] != $recording_id) {
                $other_recordings[] = $row;
            }
        }
        $other_stmt->close();
    }
    $stmt->close();
}

// Check if this is a live class
$is_live_class = false;
$is_teacher_owner = false;
if ($current_recording) {
    $is_live_class = ($current_recording['is_live'] == 1);
    $is_teacher_owner = ($role === 'teacher' && $current_recording['teacher_id'] === $user_id);
}

// For students: Check payment access and watch limit (skip for live classes)
// For teachers: Always allow access to their own content
$can_watch = true;
$paid_months = [];
$watch_count = 0;
$watch_limit = 0;
$remaining_watches = 0;

// Teachers can always watch their own recordings/live classes
if ($role === 'teacher' && $current_recording && $is_teacher_owner) {
    $can_watch = true;
} elseif ($role === 'student' && $current_recording && !$is_live_class) {
    // Get watch limit from recording
    $watch_limit = intval($current_recording['watch_limit'] ?? 3);
    
    // Get student enrollment
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
                      WHERE student_enrollment_id = ? AND payment_status = 'paid'";
        $paid_stmt = $conn->prepare($paid_query);
        $paid_stmt->bind_param("i", $enrollment_id);
        $paid_stmt->execute();
        $paid_result = $paid_stmt->get_result();
        
        while ($paid_row = $paid_result->fetch_assoc()) {
            $paid_months[] = $paid_row['year'] . '-' . str_pad($paid_row['month'], 2, '0', STR_PAD_LEFT);
        }
        $paid_stmt->close();
        
        // Check if can watch (payment check)
        if ($current_recording['free_video'] == 1) {
            $can_watch = true;
        } else {
            $recording_month_key = date('Y-m', strtotime($current_recording['created_at']));
            $can_watch = in_array($recording_month_key, $paid_months);
        }
        
        // Check watch limit (only if watch_limit > 0, 0 means unlimited)
        if ($can_watch && $watch_limit > 0) {
            // Get current watch count for this student and video
            $watch_query = "SELECT COUNT(*) as watch_count FROM video_watch_log 
                          WHERE recording_id = ? AND student_id = ?";
            $watch_stmt = $conn->prepare($watch_query);
            $watch_stmt->bind_param("is", $recording_id, $user_id);
            $watch_stmt->execute();
            $watch_result = $watch_stmt->get_result();
            $watch_row = $watch_result->fetch_assoc();
            $watch_count = intval($watch_row['watch_count']);
            $watch_stmt->close();
            
            $remaining_watches = max(0, $watch_limit - $watch_count);
            
            // If watch limit exceeded, cannot watch
            if ($watch_count >= $watch_limit) {
                $can_watch = false;
            }
        } else if ($can_watch && $watch_limit == 0) {
            // Unlimited watches
            $remaining_watches = -1; // -1 means unlimited
        }
    } else {
        $can_watch = $current_recording['free_video'] == 1;
    }
    $enroll_stmt->close();
} elseif ($is_live_class) {
    // For live classes, check if free or requires payment
    if ($role === 'teacher' && $is_teacher_owner) {
        // Teachers can always watch their own live classes
        $can_watch = true;
    } elseif ($role === 'student') {
        // Check enrollment first
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
            
            // If free video, allow access
            if ($current_recording['free_video'] == 1) {
                $can_watch = true;
            } else {
                // Check if student has paid for the month of the live class
                // Use scheduled_start_time if available, otherwise use created_at
                $live_class_date = !empty($current_recording['scheduled_start_time']) 
                    ? $current_recording['scheduled_start_time'] 
                    : $current_recording['created_at'];
                $live_class_month = date('n', strtotime($live_class_date));
                $live_class_year = date('Y', strtotime($live_class_date));
                
                $paid_query = "SELECT id FROM monthly_payments 
                              WHERE student_enrollment_id = ? 
                              AND month = ? 
                              AND year = ? 
                              AND payment_status = 'paid'
                              LIMIT 1";
                $paid_stmt = $conn->prepare($paid_query);
                $paid_stmt->bind_param("iii", $enrollment_id, $live_class_month, $live_class_year);
                $paid_stmt->execute();
                $paid_result = $paid_stmt->get_result();
                $can_watch = $paid_result->num_rows > 0;
                $paid_stmt->close();
            }
        } else {
            $can_watch = false;
        }
        $enroll_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo $current_recording ? htmlspecialchars($current_recording['title']) : 'Video Player'; ?> - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        .hidden {
            display: none !important;
        }

        html {
            margin: 0;
            padding: 0;
            height: 100%;
            height: 100dvh; /* Dynamic viewport height for mobile */
            width: 100%;
            width: 100dvw; /* Dynamic viewport width for mobile */
            overflow: hidden;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: #000;
            overflow: hidden;
            height: 100%;
            height: 100dvh; /* Dynamic viewport height for mobile */
            width: 100%;
            width: 100dvw; /* Dynamic viewport width for mobile */
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
        }

        .player-container {
            display: flex;
            height: 100%;
            height: 100dvh; /* Dynamic viewport height for mobile */
            width: 100%;
            width: 100dvw; /* Dynamic viewport width for mobile */
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            z-index: 10000;
            background: #000;
        }

        /* Left Sidebar - Other Videos */
        .videos-sidebar {
            width: 320px;
            background: #1a1a1a;
            overflow-y: auto;
            border-right: 1px solid #333;
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease, opacity 0.3s ease;
        }

        /* Hide sidebar on mobile */
        @media (max-width: 768px) {
            .videos-sidebar {
                display: none !important;
            }
        }

        /* Hide sidebar in fullscreen */
        .player-wrapper:fullscreen ~ .videos-sidebar,
        .player-wrapper:-webkit-full-screen ~ .videos-sidebar,
        .player-wrapper:-moz-full-screen ~ .videos-sidebar,
        .player-wrapper:-ms-fullscreen ~ .videos-sidebar,
        :fullscreen .videos-sidebar,
        :-webkit-full-screen .videos-sidebar,
        :-moz-full-screen .videos-sidebar,
        :-ms-fullscreen .videos-sidebar {
            display: none !important;
            width: 0 !important;
            opacity: 0 !important;
            overflow: hidden !important;
            border-right: none !important;
        }

        /* Right Sidebar - Chat (Hidden on desktop, use popup instead) */
        .chat-sidebar {
            display: none;
        }

        /* Overlay when chat is open (hidden by default, shown only in landscape) */
        .chat-sidebar-overlay {
            display: none;
        }


        /* Hide chat sidebar in fullscreen */
        .player-container:fullscreen .chat-sidebar,
        .player-container:-webkit-full-screen .chat-sidebar,
        .player-container:-moz-full-screen .chat-sidebar,
        .player-container:-ms-fullscreen .chat-sidebar {
            width: 0;
            opacity: 0;
            overflow: hidden;
            border-left: none;
        }

        .chat-sidebar-header {
            padding: 1.5rem;
            background: #0f0f0f;
            border-bottom: 1px solid #333;
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .chat-sidebar-header-content {
            flex: 1;
        }

        .chat-sidebar-close-btn {
            display: none;
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            font-size: 1.25rem;
            align-self: flex-start;
            transition: color 0.2s;
        }

        .chat-sidebar-close-btn:hover {
            color: #dc2626;
        }


        .chat-sidebar-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .chat-message {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            animation: fadeIn 0.2s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chat-message.own-message {
            flex-direction: row-reverse;
        }

        .chat-message.own-message .chat-message-content {
            background: #dc2626;
            color: white;
        }

        .chat-message.other-message .chat-message-content {
            background: #252525;
            color: white;
        }

        .chat-message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid #333;
        }

        .chat-message.own-message .chat-message-avatar {
            border-color: #dc2626;
        }

        .chat-message-content-wrapper {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .chat-message-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .chat-message.own-message .chat-message-header {
            flex-direction: row-reverse;
        }

        .chat-message-sender {
            font-weight: 500;
            color: #dc2626;
        }

        .chat-message-time {
            color: #6b7280;
        }

        .chat-message-content {
            padding: 0.75rem;
            border-radius: 0.5rem;
            word-wrap: break-word;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .chat-input-container {
            padding: 1rem;
            background: #0f0f0f;
            border-top: 1px solid #333;
            position: sticky;
            bottom: 0;
            z-index: 10;
        }

        .chat-input-form {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            padding: 0.75rem;
            background: #252525;
            border: 1px solid #333;
            border-radius: 0.5rem;
            color: white;
            font-size: 0.875rem;
            resize: none;
            max-height: 100px;
            min-height: 44px;
            font-family: inherit;
            line-height: 1.5;
            box-sizing: border-box;
            overflow-y: auto;
            -webkit-appearance: none;
            appearance: none;
            margin: 0;
            outline: none;
        }

        .chat-input::placeholder {
            color: #9ca3af;
        }

        .chat-input:focus {
            outline: none;
            border-color: #dc2626;
        }

        .chat-send-btn {
            padding: 0.75rem 1.25rem;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex-shrink: 0;
            min-height: 44px;
            box-sizing: border-box;
        }

        .chat-send-btn:hover {
            background: #b91c1c;
        }

        .chat-send-btn:disabled {
            background: #4b5563;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .chat-send-btn i {
            font-size: 0.875rem;
        }

        .chat-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 0.875rem;
            text-align: center;
            padding: 2rem;
        }

        .videos-sidebar-header {
            padding: 1.5rem;
            background: #0f0f0f;
            border-bottom: 1px solid #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .videos-sidebar-content {
            flex: 1;
            padding: 1rem;
        }

        .video-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            background: #252525;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .video-item:hover {
            background: #2d2d2d;
            border-color: #dc2626;
        }

        .video-item.active {
            background: #dc2626;
            border-color: #dc2626;
        }

        /* Ensure no video-item in sidebar has active class on desktop */
        @media (min-width: 769px) {
            .videos-sidebar .video-item.active {
                background: #252525;
                border-color: transparent;
            }
        }

        .video-item-thumbnail {
            width: 120px;
            height: 68px;
            border-radius: 0.375rem;
            object-fit: cover;
            flex-shrink: 0;
        }

        .video-item-info {
            flex: 1;
            min-width: 0;
        }

        .video-item-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: #fff;
            margin-bottom: 0.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-item-date {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        /* Main Player Area */
        .player-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            background: #000;
            overflow-y: auto;
            overflow-x: hidden;
            height: 100vh;
        }

        .player-wrapper {
            position: relative;
            width: 100%;
            height: 75vh;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        /* Fullscreen styles for player-wrapper */
        .player-wrapper:fullscreen,
        .player-wrapper:-webkit-full-screen,
        .player-wrapper:-moz-full-screen,
        .player-wrapper:-ms-fullscreen {
            width: 100vw;
            height: 100vh;
            max-width: 100vw;
            max-height: 100vh;
        }
        
        /* Aggressive Fullscreen rules to remove all distractions */
        .player-main:fullscreen,
        .player-main:-webkit-full-screen,
        .player-main:-moz-full-screen,
        .player-main:-ms-fullscreen {
            background-color: #000 !important;
            width: 100vw !important;
            height: 100vh !important;
            overflow: hidden !important; /* Prevents scrolling into hidden content */
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Specifically hide unwanted sections when in fullscreen */
        .player-main:fullscreen .video-info,
        .player-main:fullscreen .downloads-section,
        .player-main:fullscreen .other-videos-section,
        .player-main:fullscreen .back-btn,
        .player-main:fullscreen .mobile-videos-section {
            display: none !important;
        }

        /* Force the player wrapper to fill the absolute entire screen */
        .player-main:fullscreen .player-wrapper {
            height: 100vh !important;
            width: 100vw !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            z-index: 1 !important;
            margin: 0 !important;
        }

        /* Maintain floating buttons on top of the video */
        .player-main:fullscreen .mobile-chat-btn,
        .player-main:fullscreen #participants-btn {
            z-index: 10002 !important;
            display: flex !important;
            position: fixed !important;
        }

        /* Ensure Chat and Participant Modals display correctly on top of everything */
        .player-main:fullscreen .mobile-chat-modal {
            z-index: 10003 !important;
        }
        
        /* Ensure the YouTube player iframe fills the wrapper in fullscreen */
        .player-main:fullscreen #player,
        .player-main:-webkit-full-screen #player,
        .player-main:-moz-full-screen #player,
        .player-main:-ms-fullscreen #player {
            width: 100% !important;
            height: 100% !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
        }
        
        /* Ensure overlay covers the entire player in fullscreen */
        .player-main:fullscreen #overlay,
        .player-main:-webkit-full-screen #overlay,
        .player-main:-moz-full-screen #overlay,
        .player-main:-ms-fullscreen #overlay {
            width: 100% !important;
            height: 100% !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
        }

        /* Ensure mobile videos section is hidden on desktop */
        /* Hide mobile videos section on desktop - consolidated */
        @media (min-width: 769px) {
            .mobile-videos-section {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                overflow: hidden !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }

        #player {
            width: 100% !important;
            height: 100% !important;
            border: 0;
            display: block;
            margin: 0 !important;
            padding: 0 !important;
            position: relative;
        }

        /* Ensure player fills fullscreen wrapper */
        .player-wrapper:fullscreen #player,
        .player-wrapper:-webkit-full-screen #player,
        .player-wrapper:-moz-full-screen #player,
        .player-wrapper:-ms-fullscreen #player {
            width: 100% !important;
            height: 100% !important;
        }

        /* Transparent Overlay - Prevents interaction with YouTube player */
        #overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 10;
            background: transparent;
        }

        /* Controls Container */
        .controls-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0));
            padding: 1rem 1.5rem;
            z-index: 20;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .player-wrapper:not(:hover) .controls-container {
            opacity: 0;
        }

        /* Timeline */
        .timeline-container {
            margin-bottom: 0.75rem;
        }

        .timeline-wrapper {
            position: relative;
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            cursor: pointer;
        }

        .timeline-progress {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: #dc2626;
            border-radius: 3px;
            width: 0%;
            transition: width 0.1s linear;
        }

        .timeline-buffer {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 3px;
            width: 0%;
        }

        .timeline-time {
            display: flex;
            justify-content: space-between;
            color: white;
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }

        /* Controls */
        .controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .control-btn {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .control-btn i {
            font-size: 1.25rem;
        }

        .volume-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .volume-slider {
            width: 100px;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
            outline: none;
            -webkit-appearance: none;
        }

        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 14px;
            height: 14px;
            background: white;
            border-radius: 50%;
            cursor: pointer;
        }

        .volume-slider::-moz-range-thumb {
            width: 14px;
            height: 14px;
            background: white;
            border-radius: 50%;
            cursor: pointer;
            border: none;
        }

        .spacer {
            flex: 1;
        }

        /* Quality Selector */
        .quality-control {
            position: relative;
            display: inline-block;
        }

        .quality-btn {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .quality-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .quality-dropdown {
            position: absolute;
            bottom: 100%;
            right: 0;
            margin-bottom: 0.5rem;
            background: rgba(0, 0, 0, 0.9);
            border-radius: 0.375rem;
            min-width: 120px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 100;
        }

        .quality-dropdown.show {
            display: block;
        }

        .quality-option {
            padding: 0.75rem 1rem;
            color: white;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 0.875rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quality-option:last-child {
            border-bottom: none;
        }

        .quality-option:hover {
            background: rgba(220, 38, 38, 0.5);
        }

        .quality-option.active {
            background: rgba(220, 38, 38, 0.7);
            color: white;
        }

        .quality-option i {
            width: 16px;
            font-size: 0.75rem;
        }

        /* Video Info */
        .video-info {
            padding: 1.5rem;
            background: #0f0f0f;
            border-top: 1px solid #333;
            width: 100%;
        }


        /* Hide video info in fullscreen */
        .player-container:fullscreen .video-info,
        .player-container:-webkit-full-screen .video-info,
        .player-container:-moz-full-screen .video-info,
        .player-container:-ms-fullscreen .video-info {
            display: none;
        }

        .video-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.5rem;
        }

        .video-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.875rem;
            color: #9ca3af;
            align-items: center;
            flex-wrap: wrap;
        }

        .video-meta .teacher-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .video-meta .teacher-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dc2626;
        }

        /* Downloads Section */
        .downloads-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #333;
            overflow-y: visible;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            margin: 0;
        }

        .upload-toggle-btn {
            padding: 0.5rem 1rem;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upload-toggle-btn:hover {
            background: #b91c1c;
        }

        .section-title i {
            color: #dc2626;
        }

        /* Other Videos Section - Bottom (Mobile only) */
        .other-videos-section {
            padding: 1.5rem;
            background: #0f0f0f;
            border-top: 1px solid #333;
        }

        /* Hide other videos section on desktop (sidebar is used instead) */
        @media (min-width: 769px) {
            .other-videos-section {
                display: none !important;
            }
        }

        .other-videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        /* Show more videos per row on larger screens */
        @media (min-width: 1200px) {
            .other-videos-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (min-width: 1600px) {
            .other-videos-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        @media (max-width: 768px) {
            .other-videos-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .other-videos-grid {
                grid-template-columns: 1fr;
            }
        }

        .other-video-card {
            background: #1a1a1a;
            border-radius: 0.75rem;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .other-video-card:hover {
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        .other-video-card img {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
        }

        .other-video-card-info {
            padding: 1rem;
        }

        .other-video-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }

        .other-video-card-date {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .files-list {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
        }

        .file-item {
            display: flex;
            flex-direction: column;
            padding: 1.25rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .file-item:hover {
            background: #252525;
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        .file-icon {
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #252525 0%, #1a1a1a 100%);
            border-radius: 0.75rem;
            font-size: 2rem;
            color: #dc2626;
            margin: 0 auto 1rem;
            border: 2px solid #333;
        }

        .file-item:hover .file-icon {
            border-color: #dc2626;
            transform: scale(1.05);
        }

        .file-info {
            flex: 1;
            text-align: center;
            margin-bottom: 1rem;
        }

        .file-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.75rem;
            word-break: break-word;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .file-meta {
            font-size: 0.75rem;
            color: #9ca3af;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
        }

        .file-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-actions {
            display: flex;
            width: 100%;
            margin-top: auto;
        }

        .file-download-btn {
            width: 100%;
            padding: 0.75rem;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .file-download-btn:hover {
            background: #b91c1c;
        }

        /* Responsive grid */
        @media (max-width: 1400px) {
            .files-list {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .files-list {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .files-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .files-list {
                grid-template-columns: 1fr;
            }
        }

        .files-loading, .files-empty {
            text-align: center;
            padding: 2rem;
            color: #9ca3af;
            font-size: 0.875rem;
        }

        .upload-area {
            background: #1a1a1a;
            border: 2px dashed #333;
            border-radius: 0.5rem;
            padding: 2rem;
            margin-bottom: 1rem;
        }

        .upload-input-wrapper {
            margin-bottom: 1rem;
        }

        .upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .upload-label:hover {
            color: #dc2626;
        }

        .upload-label i {
            font-size: 3rem;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .upload-label span {
            color: white;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .upload-label small {
            color: #9ca3af;
            font-size: 0.875rem;
        }

        .upload-progress {
            margin: 1rem 0;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #252525;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: #dc2626;
            width: 0%;
            transition: width 0.3s ease;
        }

        .progress-text {
            color: #9ca3af;
            font-size: 0.875rem;
        }

        .upload-btn {
            width: 100%;
            padding: 0.75rem;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
            position: relative;
            z-index: 10;
        }

        .upload-btn:hover {
            background: #b91c1c;
        }

        .upload-btn:disabled {
            background: #4b5563;
            cursor: not-allowed;
        }

        /* Back Button */
        .back-btn {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 100;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
            text-decoration: none;
        }

        .back-btn:hover {
            background: rgba(0, 0, 0, 0.9);
        }

        /* Chat Floating Button (for desktop and mobile) */
        .mobile-chat-btn {
            position: fixed;
            top: 1.2rem;
            right: 1.5rem;
            width: 56px;
            height: 56px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            z-index: 10002; /* Higher than modals (10001) so buttons are always clickable */
            transition: background 0.2s, transform 0.2s;
        }
        
        /* Participants button - ensure it's visible */
        #participants-btn {
            z-index: 10002 !important; /* Higher than modals (10001) so buttons are always clickable */
            top: 5rem !important;
        }
        
        /* Ensure buttons are always visible in fullscreen - global rule */
        :fullscreen .mobile-chat-btn,
        :-webkit-full-screen .mobile-chat-btn,
        :-moz-full-screen .mobile-chat-btn,
        :-ms-fullscreen .mobile-chat-btn,
        :fullscreen #participants-btn,
        :-webkit-full-screen #participants-btn,
        :-moz-full-screen #participants-btn,
        :-ms-fullscreen #participants-btn {
            display: flex !important;
            z-index: 10002 !important;
            position: fixed !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        .mobile-chat-btn:hover {
            background: #b91c1c;
            transform: scale(1.1);
        }

        .mobile-chat-btn:active {
            transform: scale(0.95);
        }

        /* Chat notification badge */
        .chat-notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ff0000;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid #000;
            animation: pulse 2s infinite;
        }

        .chat-notification-badge.hidden {
            display: none;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        /* Chat Modal/Overlay - Popup Style (Mobile and Desktop) */
        .mobile-chat-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10001; /* Higher than player-container (10000) */
            display: none;
            align-items: flex-end;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        /* Ensure participants modal is visible - override base styles */
        #participants-modal {
            z-index: 10001 !important; /* Higher than player-container (10000) */
        }
        
        /* Ensure chat modal is also above player container */
        #mobile-chat-modal {
            z-index: 10001 !important; /* Higher than player-container (10000) */
        }
        
        #participants-modal.active {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: auto !important;
        }
        
        /* Ensure participants modal content is visible */
        #participants-modal.active .mobile-chat-modal-content {
            display: flex !important;
            pointer-events: auto !important;
        }
        
        /* Mobile: participants modal should slide from bottom */
        @media (max-width: 768px) {
            #participants-modal .mobile-chat-modal-content {
                transform: translateY(100%) !important;
            }
            
            #participants-modal.active .mobile-chat-modal-content {
                transform: translateY(0) !important;
            }
        }

        .mobile-chat-modal.active {
            display: flex;
            opacity: 1;
            visibility: visible;
        }

        /* Close modal when clicking outside (on overlay) */
        .mobile-chat-modal-content {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 100%;
            max-height: 70vh;
            background: #1a1a1a;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            flex-direction: column;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
        }

        .mobile-chat-modal.active .mobile-chat-modal-content {
            transform: translateY(0);
        }

        /* Chat Modal Input Styles - Global (applies to all devices) */
        .mobile-chat-modal-input-container {
            padding: 1rem;
            background: #0f0f0f;
            border-top: 1px solid #333;
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
            z-index: 10;
        }

        .mobile-chat-modal-input-form {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .mobile-chat-modal-input {
            flex: 1;
            padding: 0.75rem;
            background: #252525;
            border: 1px solid #333;
            border-radius: 0.5rem;
            color: #ffffff;
            font-size: 0.875rem;
            resize: none;
            max-height: 100px;
            min-height: 44px;
            font-family: inherit;
            line-height: 1.5;
            box-sizing: border-box;
            overflow-y: auto;
            -webkit-appearance: none;
            appearance: none;
            margin: 0;
            outline: none;
            width: 100%;
        }

        .mobile-chat-modal-input::placeholder {
            color: #9ca3af;
        }

        .mobile-chat-modal-input:focus {
            outline: none;
            border-color: #dc2626;
            background: #252525;
            color: #ffffff;
        }

        .mobile-chat-modal-input:-webkit-autofill,
        .mobile-chat-modal-input:-webkit-autofill:hover,
        .mobile-chat-modal-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff;
            -webkit-box-shadow: 0 0 0px 1000px #252525 inset;
            box-shadow: 0 0 0px 1000px #252525 inset;
            border: 1px solid #333;
        }

        .mobile-chat-modal-send-btn {
            padding: 0.75rem 1rem;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            min-width: 50px;
        }

        .mobile-chat-modal-send-btn:hover {
            background: #b91c1c;
        }

        .mobile-chat-modal-send-btn:disabled {
            background: #4b5563;
            cursor: not-allowed;
        }

        /* Desktop chat modal - right sidebar */
        @media (min-width: 769px) {
            .mobile-chat-modal {
                align-items: stretch;
                justify-content: flex-end;
            }

            .mobile-chat-modal-content {
                width: 400px;
                max-width: 90vw;
                max-height: 100vh;
                height: 100vh;
                border-radius: 0;
                transform: translateX(100%);
                box-shadow: -4px 0 20px rgba(0, 0, 0, 0.5);
                border-left: 1px solid #333;
                display: flex;
                flex-direction: column;
            }

            .mobile-chat-modal.active .mobile-chat-modal-content {
                transform: translateX(0) !important;
            }
            
            /* Ensure participants modal works on desktop */
            /* Participants modal on desktop - slide from right */
            #participants-modal .mobile-chat-modal-content {
                transform: translateX(100%) !important;
            }
            
            #participants-modal.active .mobile-chat-modal-content {
                transform: translateX(0) !important;
            }

            .mobile-chat-modal-messages {
                max-height: calc(100vh - 140px) !important;
                overflow-y: auto !important;
                overflow-x: hidden;
                scroll-behavior: smooth;
            }
            
        /* Desktop participants list */
        #participants-list {
            max-height: calc(100vh - 80px) !important;
            overflow-y: auto !important;
            display: block !important;
            flex: 1 !important;
            min-height: 0 !important;
            padding: 0 !important;
        }
        
        /* Ensure participants modal content displays properly */
        #participants-modal .mobile-chat-modal-content {
            display: flex !important;
            flex-direction: column !important;
        }
        
        #participants-modal .mobile-chat-modal-messages {
            display: block !important;
            flex: 1 !important;
            min-height: 0 !important;
        }

            .mobile-chat-modal-input-container {
                position: sticky;
                bottom: 0;
                margin-top: auto;
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            html {
                height: 100dvh !important;
                width: 100dvw !important;
                overflow: hidden !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
            }

            body {
                height: 100dvh !important;
                width: 100dvw !important;
                overflow: hidden !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                z-index: 9999 !important;
            }

            .player-container {
                flex-direction: column;
                height: 100dvh !important;
                width: 100dvw !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                z-index: 10000 !important;
                background: #000 !important;
            }

            /* Hide sidebars from their original positions on mobile portrait */
            .videos-sidebar {
                display: none !important;
            }

            /* Chat notifications */
            .chat-notification {
                position: fixed;
                top: 1rem;
                right: 1rem;
                background: #1a1a1a;
                border: 1px solid #333;
                border-radius: 0.75rem;
                padding: 1rem;
                min-width: 300px;
                max-width: 400px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
                z-index: 3000;
                transform: translateX(400px);
                opacity: 0;
                transition: transform 0.3s ease, opacity 0.3s ease;
                cursor: pointer;
            }

            .chat-notification.show {
                transform: translateX(0);
                opacity: 1;
            }

            .chat-notification-header {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 0.5rem;
            }

            .chat-notification-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid #dc2626;
            }

            .chat-notification-info {
                flex: 1;
                min-width: 0;
            }

            .chat-notification-sender {
                font-weight: 600;
                color: #dc2626;
                font-size: 0.875rem;
                margin-bottom: 0.25rem;
            }

            .chat-notification-message {
                color: #e5e7eb;
                font-size: 0.875rem;
                line-height: 1.4;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .chat-notification-close {
                background: transparent;
                border: none;
                color: #9ca3af;
                cursor: pointer;
                padding: 0.25rem;
                font-size: 1rem;
                transition: color 0.2s;
            }

            .chat-notification-close:hover {
                color: white;
            }

            /* Show sidebar after video-info */
            .video-info::after {
                content: '';
                display: block;
                width: 100%;
            }

            .player-main {
                flex: 1;
                min-height: 0;
                overflow-y: auto;
                overflow-x: hidden;
                display: flex;
                flex-direction: column;
                height: 100dvh;
            }

            .player-wrapper {
                position: relative;
                width: 100%;
                height: 75vh;
                background: #000;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                overflow: hidden;
            }
            
            #player {
                width: 100% !important;
                height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            #overlay {
                height: 100% !important;
            }

            .video-info {
                flex-shrink: 0;
                max-height: none;
                overflow-y: auto;
                overflow-x: hidden;
                padding-bottom: 0;
                border-bottom: none;
                -webkit-overflow-scrolling: touch;
            }

            /* Mobile Videos Section - Horizontal Scrollable */
            .mobile-videos-section {
                display: block;
                width: 100%;
                background: #1a1a1a;
                border-top: 1px solid #333;
                padding: 1rem;
                flex-shrink: 0;
            }

            /* Hide mobile videos section on desktop */
            @media (min-width: 769px) {
                .mobile-videos-section {
                    display: none !important;
                }
            }

            .mobile-videos-header {
                margin-bottom: 0.75rem;
            }

            /* Always show chat buttons for live classes */
            .live-class-chat-btn {
                display: flex !important;
                z-index: 1400;
            }
            
            /* Landscape mode: Use toggle button for popup chat */
            @media (max-width: 768px) and (orientation: landscape) {
                .mobile-chat-btn {
                    display: flex;
                    z-index: 1400;
                }

                /* In landscape, make chat popup smaller */
                .mobile-chat-modal-content {
                    max-height: 60vh;
                }

                .mobile-chat-modal-messages {
                    max-height: calc(60vh - 140px);
                }
            }
            
            /* Desktop: Always show chat buttons */
            @media (min-width: 769px) {
                .mobile-chat-btn {
                    display: flex !important;
                }
            }

            /* Show chat and participants buttons in fullscreen mode (for both desktop and mobile) */
            /* Target buttons when player-container is fullscreen */
            .player-container:fullscreen ~ .mobile-chat-btn,
            .player-container:-webkit-full-screen ~ .mobile-chat-btn,
            .player-container:-moz-full-screen ~ .mobile-chat-btn,
            .player-container:-ms-fullscreen ~ .mobile-chat-btn,
            /* Target buttons when player-wrapper is fullscreen */
            .player-wrapper:fullscreen ~ .mobile-chat-btn,
            .player-wrapper:-webkit-full-screen ~ .mobile-chat-btn,
            .player-wrapper:-moz-full-screen ~ .mobile-chat-btn,
            .player-wrapper:-ms-fullscreen ~ .mobile-chat-btn,
            /* Target buttons when any element is fullscreen (global) */
            :fullscreen .mobile-chat-btn,
            :-webkit-full-screen .mobile-chat-btn,
            :-moz-full-screen .mobile-chat-btn,
            :-ms-fullscreen .mobile-chat-btn,
            /* Target buttons directly (when document is fullscreen) */
            html:fullscreen .mobile-chat-btn,
            html:-webkit-full-screen .mobile-chat-btn,
            html:-moz-full-screen .mobile-chat-btn,
            html:-ms-fullscreen .mobile-chat-btn,
            body:fullscreen .mobile-chat-btn,
            body:-webkit-full-screen .mobile-chat-btn,
            body:-moz-full-screen .mobile-chat-btn,
            body:-ms-fullscreen .mobile-chat-btn,
            /* Same for participants button */
            .player-container:fullscreen ~ #participants-btn,
            .player-container:-webkit-full-screen ~ #participants-btn,
            .player-container:-moz-full-screen ~ #participants-btn,
            .player-container:-ms-fullscreen ~ #participants-btn,
            .player-wrapper:fullscreen ~ #participants-btn,
            .player-wrapper:-webkit-full-screen ~ #participants-btn,
            .player-wrapper:-moz-full-screen ~ #participants-btn,
            .player-wrapper:-ms-fullscreen ~ #participants-btn,
            :fullscreen #participants-btn,
            :-webkit-full-screen #participants-btn,
            :-moz-full-screen #participants-btn,
            :-ms-fullscreen #participants-btn,
            html:fullscreen #participants-btn,
            html:-webkit-full-screen #participants-btn,
            html:-moz-full-screen #participants-btn,
            html:-ms-fullscreen #participants-btn,
            body:fullscreen #participants-btn,
            body:-webkit-full-screen #participants-btn,
            body:-moz-full-screen #participants-btn,
            body:-ms-fullscreen #participants-btn {
                display: flex !important;
                z-index: 10002 !important;
                position: fixed !important;
                visibility: visible !important;
                opacity: 1 !important;
            }


            .mobile-chat-modal-header {
                padding: 1rem;
                background: #1a1a1a;
                border-bottom: 1px solid #333;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-shrink: 0;
            }

            .mobile-chat-modal-header h3 {
                color: white;
                font-size: 1rem;
                font-weight: 600;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .mobile-chat-modal-header h3 i {
                color: #dc2626;
            }

            .mobile-chat-modal-close {
                background: transparent;
                border: none;
                color: white;
                cursor: pointer;
                padding: 0.5rem;
                font-size: 1.25rem;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                transition: background 0.2s;
            }

            .mobile-chat-modal-close:hover {
                background: rgba(255, 255, 255, 0.1);
            }

            .mobile-chat-modal-messages {
                flex: 1;
                padding: 1rem;
                overflow-y: auto !important;
                overflow-x: hidden;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                -webkit-overflow-scrolling: touch;
                min-height: 0;
                max-height: calc(70vh - 140px);
                scroll-behavior: smooth;
            }
            
            /* Ensure participants list scrolls properly */
            #participants-list {
                overflow-y: auto !important;
                max-height: calc(70vh - 80px);
                display: block !important;
                flex: 1 !important;
                min-height: 0 !important;
            }

            /* Ensure chat message styles work in mobile modal */
            .mobile-chat-modal-messages .chat-message {
                font-size: 0.875rem;
            }

            .mobile-chat-modal-messages .chat-message-avatar {
                width: 32px;
                height: 32px;
            }

            .mobile-chat-modal-messages .chat-message-content {
                font-size: 0.8125rem;
                padding: 0.625rem;
            }

            .mobile-chat-modal-messages .chat-message-header {
                font-size: 0.75rem;
            }

            .mobile-chat-modal-messages .chat-empty {
                padding: 2rem 1rem;
            }

            .mobile-chat-modal-messages .chat-empty i {
                font-size: 3rem;
            }

            .mobile-chat-modal-messages .chat-empty p {
                font-size: 0.875rem;
            }


            .mobile-videos-header h3 {
                color: white;
                font-size: 0.875rem;
                font-weight: 600;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .mobile-videos-header h3 i {
                color: #dc2626;
            }

            .mobile-videos-scroll {
                display: flex;
                gap: 0.75rem;
                overflow-x: auto;
                overflow-y: hidden;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                scrollbar-color: #dc2626 #1a1a1a;
                padding-bottom: 0.5rem;
            }

            .mobile-videos-scroll::-webkit-scrollbar {
                height: 4px;
            }

            .mobile-videos-scroll::-webkit-scrollbar-track {
                background: #1a1a1a;
            }

            .mobile-videos-scroll::-webkit-scrollbar-thumb {
                background: #dc2626;
                border-radius: 2px;
            }

            .mobile-video-item {
                flex: 0 0 auto;
                width: 140px;
                background: #252525;
                border-radius: 0.5rem;
                overflow: hidden;
                cursor: pointer;
                transition: all 0.2s;
                border: 2px solid transparent;
                scroll-snap-align: start;
            }

            .mobile-video-item:hover,
            .mobile-video-item:active {
                background: #2d2d2d;
                border-color: #dc2626;
                transform: scale(1.02);
            }

            /* Remove active state from mobile videos - current video is not shown */
            .mobile-video-item.active {
                background: #252525;
                border-color: transparent;
            }

            .mobile-video-thumbnail {
                width: 100%;
                aspect-ratio: 16/9;
                object-fit: cover;
            }

            .mobile-video-info {
                padding: 0.5rem;
            }

            .mobile-video-title {
                font-size: 0.75rem;
                font-weight: 500;
                color: #fff;
                margin-bottom: 0.25rem;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                line-height: 1.3;
            }

            .mobile-video-date {
                font-size: 0.65rem;
                color: #9ca3af;
            }

            /* Hide any potential content from other pages */
            body > *:not(.player-container) {
                display: none !important;
            }
        }
    </style>
</head>
<body >
    <?php if (!$current_recording || (!$can_watch && !$is_live_class)): ?>
        <div class="flex items-center justify-center h-screen bg-black text-white">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-6xl text-red-600 mb-4"></i>
                <h1 class="text-2xl font-bold mb-2">Video Not Available</h1>
                <p class="text-gray-400 mb-4">
                    <?php if (!$current_recording): ?>
                        The requested video could not be found.
                    <?php elseif ($role === 'student' && $watch_limit > 0 && $watch_count >= $watch_limit): ?>
                        You have reached the watch limit for this video (<?php echo $watch_count; ?>/<?php echo $watch_limit; ?> watches used).
                    <?php else: ?>
                        This video requires payment to watch.
                    <?php endif; ?>
                </p>
                <a href="../dashboard/content.php?stream_subject_id=<?php echo $stream_subject_id; ?>&academic_year=<?php echo $academic_year; ?>" 
                   class="inline-block px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Recordings
                </a>
            </div>
        </div>
        
        <!-- Show chat and participant buttons for live classes even if payment required -->
        <?php if ($is_live_class && $current_recording): ?>
        <!-- Chat Floating Button (shown on desktop and mobile) -->
        <?php if ($role === 'student' || $role === 'teacher'): ?>
        <button class="mobile-chat-btn live-class-chat-btn" id="mobile-chat-btn" onclick="toggleMobileChatModal()" title="Chat" style="top: 1.2rem; right: 1.5rem; display: flex !important;">
            <i class="fas fa-comments"></i>
            <span class="chat-notification-badge hidden" id="chat-notification-badge">0</span>
        </button>
        <?php endif; ?>

        <!-- Participants Button (for live classes only) -->
        <?php if ($role === 'student' || $role === 'teacher'): ?>
        <button class="mobile-chat-btn live-class-chat-btn" id="participants-btn" onclick="toggleParticipantsModal()" title="Participants" style="top: 5rem; right: 1.5rem; background: #059669; display: flex !important;">
            <i class="fas fa-users"></i>
            <span class="chat-notification-badge hidden" id="participants-count-badge">0</span>
        </button>
        <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="player-container">
            <!-- Left Sidebar - Other Videos (Desktop only) -->
            <div class="videos-sidebar">
                <div class="videos-sidebar-header">
                    <h3 class="text-white font-semibold text-lg">
                        <i class="fas fa-calendar-alt mr-2 text-red-600"></i>
                        <?php echo date('F Y', strtotime($current_recording['created_at'])); ?>
                    </h3>
                    <p class="text-gray-400 text-sm mt-1">Other videos this month</p>
                </div>
                <div class="videos-sidebar-content">
                    <?php if (empty($other_recordings)): ?>
                        <p class="text-gray-500 text-sm text-center py-4">No other videos this month</p>
                    <?php else: ?>
                        <?php foreach ($other_recordings as $recording): ?>
                            <?php 
                            // Skip if this is the current video
                            if ($recording['id'] == $recording_id) continue; 
                            ?>
                            <div class="video-item" onclick="switchVideo(<?php echo $recording['id']; ?>)">
                                <img src="<?php echo htmlspecialchars($recording['thumbnail_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($recording['title']); ?>"
                                     class="video-item-thumbnail"
                                     onerror="this.src='https://img.youtube.com/vi/<?php echo htmlspecialchars($recording['youtube_video_id']); ?>/hqdefault.jpg'">
                                <div class="video-item-info">
                                    <div class="video-item-title"><?php echo htmlspecialchars($recording['title']); ?></div>
                                    <div class="video-item-date">
                                        <i class="far fa-clock mr-1"></i>
                                        <?php echo date('M d, Y', strtotime($recording['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Player Area -->
            <div class="player-main">
                <!-- Back Button -->
                <a href="../dashboard/content.php?stream_subject_id=<?php echo $stream_subject_id; ?>&academic_year=<?php echo $academic_year; ?>" 
                   class="back-btn" style="display: flex !important; z-index: 100 !important;">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back</span>
                </a>

                <!-- Player Wrapper -->
                <div class="player-wrapper">
                    <!-- YouTube Player -->
                    <div id="player"></div>

                    <!-- Transparent Overlay -->
                    <div id="overlay"></div>

                    <!-- Controls -->
                    <div class="controls-container">
                        <!-- Timeline -->
                        <div class="timeline-container">
                            <div class="timeline-wrapper" id="timeline-wrapper">
                                <div class="timeline-buffer" id="timeline-buffer"></div>
                                <div class="timeline-progress" id="timeline-progress"></div>
                            </div>
                            <div class="timeline-time">
                                <span id="current-time">0:00</span>
                                <span id="total-time">0:00</span>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="controls">
                            <button id="play-pause-btn" class="control-btn">
                                <i class="fas fa-play" id="play-icon"></i>
                                <i class="fas fa-pause hidden" id="pause-icon"></i>
                            </button>

                            <div class="volume-control">
                                <i class="fas fa-volume-up text-white"></i>
                                <input type="range" id="volume-slider" class="volume-slider" min="0" max="100" value="100">
                            </div>

                            <div class="spacer"></div>

                            <!-- Quality Selector -->
                            <div class="quality-control">
                                <button id="quality-btn" class="quality-btn" onclick="toggleQualityDropdown(event)">
                                    <i class="fas fa-cog mr-1"></i>
                                    <span id="current-quality">Auto</span>
                                </button>
                                <div id="quality-dropdown" class="quality-dropdown">
                                    <div class="quality-option" data-quality="auto" onclick="setQuality('auto')">
                                        <i class="fas fa-check hidden"></i> <span>Auto</span>
                                    </div>
                                    <div class="quality-option" data-quality="tiny" onclick="setQuality('tiny')">
                                        <i class="fas fa-check hidden"></i> <span>240p</span>
                                    </div>
                                    <div class="quality-option" data-quality="small" onclick="setQuality('small')">
                                        <i class="fas fa-check hidden"></i> <span>360p</span>
                                    </div>
                                    <div class="quality-option" data-quality="medium" onclick="setQuality('medium')">
                                        <i class="fas fa-check hidden"></i> <span>480p</span>
                                    </div>
                                    <div class="quality-option" data-quality="large" onclick="setQuality('large')">
                                        <i class="fas fa-check hidden"></i> <span>720p</span>
                                    </div>
                                    <div class="quality-option" data-quality="hd720" onclick="setQuality('hd720')">
                                        <i class="fas fa-check hidden"></i> <span>720p HD</span>
                                    </div>
                                    <div class="quality-option" data-quality="hd1080" onclick="setQuality('hd1080')">
                                        <i class="fas fa-check hidden"></i> <span>1080p HD</span>
                                    </div>
                                    <div class="quality-option" data-quality="highres" onclick="setQuality('highres')">
                                        <i class="fas fa-check hidden"></i> <span>1440p+</span>
                                    </div>
                                </div>
                            </div>

                            <button id="fullscreen-btn" class="control-btn">
                                <i class="fas fa-expand" id="expand-icon"></i>
                                <i class="fas fa-compress hidden" id="compress-icon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Video Info -->
                <div class="video-info">
                    <h1 class="video-title"><?php echo htmlspecialchars($current_recording['title']); ?></h1>
                    <div class="video-meta">
                        <!-- Teacher Info -->
                        <span class="teacher-info">
                            <?php 
                            $teacher_name = trim(($current_recording['first_name'] ?? '') . ' ' . ($current_recording['second_name'] ?? ''));
                            $profile_picture = $current_recording['profile_picture'] ?? '';
                            if (!empty($profile_picture)): 
                                $profile_path = '../' . $profile_picture;
                            ?>
                                <img src="<?php echo htmlspecialchars($profile_path); ?>" 
                                     alt="<?php echo htmlspecialchars($teacher_name); ?>"
                                     class="teacher-avatar"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($teacher_name); ?>&background=dc2626&color=fff&size=128'">
                            <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($teacher_name); ?>&background=dc2626&color=fff&size=128" 
                                     alt="<?php echo htmlspecialchars($teacher_name); ?>"
                                     class="teacher-avatar">
                            <?php endif; ?>
                            <span>
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($teacher_name); ?>
                            </span>
                        </span>
                        <span>
                            <i class="fas fa-book mr-1"></i>
                            <?php echo htmlspecialchars($current_recording['subject_name']); ?>
                        </span>
                        <span>
                            <i class="fas fa-graduation-cap mr-1"></i>
                            <?php echo htmlspecialchars($current_recording['stream_name']); ?>
                        </span>
                        <span>
                            <i class="far fa-calendar mr-1"></i>
                            <?php echo date('F d, Y', strtotime($current_recording['created_at'])); ?>
                        </span>
                    </div>
                    <?php if ($role === 'student' && $watch_limit > 0): ?>
                        <div class="mt-3 p-3 bg-gray-800 rounded-lg border border-gray-700">
                            <p class="text-sm text-gray-300">
                                <i class="fas fa-eye mr-2 text-red-600"></i>
                                <?php if ($remaining_watches > 0): ?>
                                    <span class="text-white font-semibold">You can only watch this video <?php echo $remaining_watches; ?> more time<?php echo $remaining_watches > 1 ? 's' : ''; ?>.</span>
                                    <span class="text-gray-400 ml-2">(Watched: <?php echo $watch_count; ?>/<?php echo $watch_limit; ?>)</span>
                                <?php else: ?>
                                    <span class="text-red-400 font-semibold">You have reached the watch limit for this video.</span>
                                    <span class="text-gray-400 ml-2">(Watched: <?php echo $watch_count; ?>/<?php echo $watch_limit; ?>)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <?php if ($current_recording['description']): ?>
                        <p class="text-gray-300 mt-3 text-sm"><?php echo htmlspecialchars($current_recording['description']); ?></p>
                    <?php endif; ?>
                    
                    <!-- End Live Class Button (for teachers only, in description section) -->
                    <?php if ($is_live_class && $is_teacher_owner && $current_recording['status'] === 'ongoing'): ?>
                    <div class="mt-4">
                        <button onclick="showEndLiveClassModal()" class="w-full px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center justify-center gap-2">
                            <i class="fas fa-stop-circle"></i>
                            <span>End Live Class</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Downloads Section -->
                    <div class="downloads-section mt-4">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-download mr-2"></i>
                                Downloads
                            </h3>
                            <?php if ($role === 'student' || $role === 'teacher'): ?>
                            <button class="upload-toggle-btn" id="upload-toggle-btn" onclick="toggleUploadArea()">
                                <i class="fas fa-upload"></i>
                                Upload File
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($role === 'student' || $role === 'teacher'): ?>
                        <div class="upload-area" id="upload-area" style="display: none;">
                            <form id="upload-form" enctype="multipart/form-data">
                                <input type="hidden" name="recording_id" value="<?php echo $recording_id; ?>">
                                <div class="upload-input-wrapper">
                                    <input type="file" id="file-input" name="file" accept="*/*" style="display: none;">
                                    <label for="file-input" class="upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Click to select file or drag and drop</span>
                                        <small>Maximum file size: 50MB</small>
                                    </label>
                                </div>
                                <div class="upload-progress" id="upload-progress" style="display: none;">
                                    <div class="progress-bar">
                                        <div class="progress-fill" id="progress-fill"></div>
                                    </div>
                                    <span class="progress-text" id="progress-text">0%</span>
                                </div>
                                <button type="submit" class="upload-btn" id="upload-btn" style="display: none;">
                                    <i class="fas fa-upload"></i>
                                    Upload File
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <div class="files-list" id="files-list">
                            <div class="files-loading">Loading files...</div>
                        </div>
                    </div>
                </div>

                <!-- Other Videos Section -->
                <div class="other-videos-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <?php echo date('F Y', strtotime($current_recording['created_at'])); ?> - Other Videos
                    </h3>
                    <?php if (empty($other_recordings)): ?>
                        <p class="text-gray-500 text-sm text-center py-4">No other videos this month</p>
                    <?php else: ?>
                        <div class="other-videos-grid">
                            <?php foreach ($other_recordings as $recording): ?>
                                <?php 
                                // Skip if this is the current video
                                if ($recording['id'] == $recording_id) continue; 
                                ?>
                                <div class="other-video-card" onclick="switchVideo(<?php echo $recording['id']; ?>)">
                                    <img src="<?php echo htmlspecialchars($recording['thumbnail_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($recording['title']); ?>"
                                         onerror="this.src='https://img.youtube.com/vi/<?php echo htmlspecialchars($recording['youtube_video_id']); ?>/hqdefault.jpg'">
                                    <div class="other-video-card-info">
                                        <div class="other-video-card-title"><?php echo htmlspecialchars($recording['title']); ?></div>
                                        <div class="other-video-card-date">
                                            <i class="far fa-clock mr-1"></i>
                                            <?php echo date('M d, Y', strtotime($recording['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Chat Floating Button (shown on desktop and mobile) -->
                <?php if ($role === 'student' || $role === 'teacher'): ?>
                <button class="mobile-chat-btn <?php echo $is_live_class ? 'live-class-chat-btn' : ''; ?>" id="mobile-chat-btn" onclick="toggleMobileChatModal()" title="Chat" style="top: 1.2rem; right: 1.5rem; display: flex !important;">
                    <i class="fas fa-comments"></i>
                    <span class="chat-notification-badge hidden" id="chat-notification-badge">0</span>
                </button>
                <?php endif; ?>

                <!-- Participants Button (for live classes only) -->
                <?php if ($is_live_class && ($role === 'student' || $role === 'teacher')): ?>
                <button class="mobile-chat-btn live-class-chat-btn" id="participants-btn" onclick="toggleParticipantsModal()" title="Participants" style="top: 5rem; right: 1.5rem; background: #059669; display: flex !important;">
                    <i class="fas fa-users"></i>
                    <span class="chat-notification-badge hidden" id="participants-count-badge">0</span>
                </button>
                <?php endif; ?>

                <!-- Mobile Chat Modal -->
                <?php if ($role === 'student' || $role === 'teacher'): ?>
        <div class="mobile-chat-modal" id="mobile-chat-modal">
            <div class="mobile-chat-modal-content">
                <div class="mobile-chat-modal-header">
                    <h3>
                        <i class="fas fa-comments"></i>
                        Chat
                    </h3>
                    <button class="mobile-chat-modal-close" onclick="toggleMobileChatModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mobile-chat-modal-messages" id="mobile-chat-modal-messages">
                    <div class="chat-empty">
                        <div>
                            <i class="fas fa-comments text-4xl mb-2 text-gray-600"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    </div>
                </div>
                <div class="mobile-chat-modal-input-container">
                    <form class="mobile-chat-modal-input-form" id="mobile-chat-modal-form" onsubmit="sendMobileChatModalMessage(event)">
                        <textarea 
                            id="mobile-chat-modal-input" 
                            class="mobile-chat-modal-input" 
                            placeholder="Type your message..."
                            rows="1"
                            maxlength="2000"></textarea>
                        <button type="submit" class="mobile-chat-modal-send-btn" id="mobile-chat-modal-send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
                </div>
                <?php endif; ?>

                <!-- Participants Modal (for live classes) -->
                <?php if ($is_live_class && ($role === 'student' || $role === 'teacher')): ?>
        <div class="mobile-chat-modal" id="participants-modal">
            <div class="mobile-chat-modal-content">
                <div class="mobile-chat-modal-header">
                    <h3>
                        <i class="fas fa-users"></i>
                        Participants
                    </h3>
                    <button class="mobile-chat-modal-close" onclick="toggleParticipantsModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mobile-chat-modal-messages" id="participants-list" style="overflow-y: auto; display: block; flex: 1; min-height: 0;">
                    <div class="chat-empty">
                        <div>
                            <i class="fas fa-users text-4xl mb-2 text-gray-600"></i>
                            <p>Loading participants...</p>
                        </div>
                    </div>
                </div>
            </div>
                </div>
                <?php endif; ?>

                <!-- End Live Class Confirmation Modal -->
                <?php if ($is_live_class && $is_teacher_owner): ?>
        <div class="mobile-chat-modal" id="end-live-modal">
            <div class="mobile-chat-modal-header">
                <h3>
                    <i class="fas fa-stop-circle"></i>
                    End Live Class
                </h3>
                <button class="mobile-chat-modal-close" onclick="closeEndLiveClassModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mobile-chat-modal-content" style="padding: 2rem;">
                <div class="text-center mb-6">
                    <svg class="w-16 h-16 text-red-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-white text-lg font-semibold mb-2">Are you sure you want to end this live class?</p>
                    <p class="text-gray-400 text-sm">This action cannot be undone.</p>
                </div>
                
                <div class="mb-6">
                    <label class="flex items-center p-4 bg-gray-800 rounded-lg cursor-pointer hover:bg-gray-700 transition mb-3">
                        <input type="radio" name="save_option" value="yes" class="mr-3" checked>
                        <div>
                            <div class="text-white font-semibold">Save to Recordings</div>
                            <div class="text-gray-400 text-sm">The live class will be saved and available as a recording</div>
                        </div>
                    </label>
                    <label class="flex items-center p-4 bg-gray-800 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                        <input type="radio" name="save_option" value="no" class="mr-3">
                        <div>
                            <div class="text-white font-semibold">Cancel (Don't Save)</div>
                            <div class="text-gray-400 text-sm">The live class will be cancelled and not saved</div>
                        </div>
                    </label>
                </div>
                
                <div class="flex gap-3">
                    <button onclick="closeEndLiveClassModal()" 
                            class="flex-1 px-4 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <button onclick="confirmEndLiveClass()" 
                            class="flex-1 px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        End Live Class
                    </button>
                </div>
                </div>
                <?php endif; ?>

                <!-- Chat Notifications Container -->
                <?php if ($role === 'student' || $role === 'teacher'): ?>
                <div id="chat-notifications-container"></div>
                <?php endif; ?>

            </div>
            <!-- End of player-main -->

        </div>
        <!-- End of player-container -->

        <!-- Watch Limit Confirmation Modal -->
        <div id="watchLimitModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-75 z-[20000] flex items-center justify-center">
            <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4 shadow-xl border-t-4 border-red-600 relative">
                <button onclick="closeWatchLimitModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm View</h3>
                    <p class="text-gray-600">You can watch this video <span id="remaining-count-display" class="font-bold text-red-600 text-lg">X</span> more times only.</p>
                    <p class="text-sm text-gray-500 mt-2 bg-gray-50 p-2 rounded border border-gray-100">Proceeding will utilize one view count.</p>
                </div>
                <div class="flex gap-3 justify-center">
                    <button onclick="closeWatchLimitModal()" class="px-5 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-md hover:bg-gray-50 transition w-full">
                        Cancel
                    </button>
                    <button onclick="confirmWatch()" class="px-5 py-2.5 bg-red-600 text-white font-medium rounded-md hover:bg-red-700 shadow-lg hover:shadow-xl transition w-full flex items-center justify-center gap-2">
                        <i class="fas fa-play text-sm"></i> Watch Now
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

        <script>
            // YouTube API
            var tag = document.createElement('script');
            tag.src = "https://www.youtube.com/iframe_api";
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

            var player;
            var currentVideoId = '<?php echo htmlspecialchars($current_recording['youtube_video_id']); ?>';
            var updateInterval = null;
            var viewTracked = false;
            var recordingId = <?php echo $recording_id; ?>;
            var currentQuality = localStorage.getItem('videoQuality') || 'auto';
            
            // Chat variables
            var chatPollInterval = null;
            var lastMessageId = 0;
            var chatInitialized = false;
            var unreadCount = 0;
            var isChatOpen = false;
            
            // Live class variables
            var isLiveClass = <?php echo $is_live_class ? 'true' : 'false'; ?>;
            var isTeacherOwner = <?php echo $is_teacher_owner ? 'true' : 'false'; ?>;
            var liveClassStatus = '<?php echo $current_recording['status'] ?? ''; ?>';
            var participantsPollInterval = null;
            var isParticipantsOpen = false;
            var participantsPollInterval = null;
            var isParticipantsOpen = false;
            var hasJoinedLiveClass = false;
            
            // Watch Limit variables
            var role = '<?php echo $role; ?>';
            var watchLimit = <?php echo $watch_limit; ?>;
            var remainingWatches = <?php echo $remaining_watches; ?>;
            var hasConfirmedWatch = false;
            
            // Quality mapping for display
            var qualityLabels = {
                'auto': 'Auto',
                'tiny': '240p',
                'small': '360p',
                'medium': '480p',
                'large': '720p',
                'hd720': '720p HD',
                'hd1080': '1080p HD',
                'highres': '1440p+'
            };

            function onYouTubeIframeAPIReady() {
                // Only initialize YouTube player if we have a video ID and it's not a live class without video
                if (currentVideoId && (!isLiveClass || currentVideoId)) {
                    player = new YT.Player('player', {
                        height: '100%',
                        width: '100%',
                        videoId: currentVideoId,
                        playerVars: {
                            'autoplay': isLiveClass ? 1 : 0,
                            'controls': 1,
                            'rel': 0,
                            'showinfo': 0,
                            'modestbranding': 1,
                            'playsinline': 1,
                            'enablejsapi': 1,
                            'origin': window.location.origin
                        },
                        events: {
                            'onReady': onPlayerReady,
                            'onStateChange': onPlayerStateChange
                        }
                    });
                } else if (isLiveClass && !currentVideoId) {
                    // For live classes without YouTube video, show a placeholder
                    document.getElementById('player').innerHTML = `
                        <div class="flex items-center justify-center h-full bg-black text-white">
                            <div class="text-center">
                                <svg class="w-24 h-24 mx-auto mb-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                <h2 class="text-2xl font-bold mb-2">Live Class</h2>
                                <p class="text-gray-400">Live stream will appear here</p>
                            </div>
                        </div>
                    `;
                }
            }

            function onPlayerReady(event) {
                // Initialize timeline
                updateTimeline();
                updateInterval = setInterval(updateTimeline, 100);

                // Apply saved quality preference
                if (currentQuality && currentQuality !== 'auto') {
                    try {
                        player.setPlaybackQuality(currentQuality);
                    } catch (e) {
                        console.log('Quality not available:', e);
                    }
                }
                updateQualityUI();

                // Overlay click to play/pause
                document.getElementById('overlay').addEventListener('click', togglePlay);
                
                // Play/Pause button
                document.getElementById('play-pause-btn').addEventListener('click', togglePlay);

                // Volume control
                document.getElementById('volume-slider').addEventListener('input', function(e) {
                    player.setVolume(e.target.value);
                });

                // Timeline click to seek
                document.getElementById('timeline-wrapper').addEventListener('click', function(e) {
                    const rect = this.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const percentage = clickX / rect.width;
                    const duration = player.getDuration();
                    player.seekTo(duration * percentage, true);
                });

                // Fullscreen
                document.getElementById('fullscreen-btn').addEventListener('click', toggleFullscreen);
                
                // Close quality dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    const qualityControl = document.querySelector('.quality-control');
                    if (qualityControl && !qualityControl.contains(e.target)) {
                        closeQualityDropdown();
                    }
                });
            }
            
            function toggleQualityDropdown(event) {
                event.stopPropagation();
                const dropdown = document.getElementById('quality-dropdown');
                dropdown.classList.toggle('show');
            }
            
            function closeQualityDropdown() {
                const dropdown = document.getElementById('quality-dropdown');
                dropdown.classList.remove('show');
            }
            
            function setQuality(quality) {
                if (!player) return;
                
                currentQuality = quality;
                localStorage.setItem('videoQuality', quality);
                
                try {
                    if (quality === 'auto') {
                        player.setPlaybackQuality('default');
                    } else {
                        player.setPlaybackQuality(quality);
                    }
                } catch (e) {
                    console.log('Error setting quality:', e);
                }
                
                updateQualityUI();
                closeQualityDropdown();
            }
            
            function updateQualityUI() {
                // Update current quality label
                const currentQualityEl = document.getElementById('current-quality');
                if (currentQualityEl) {
                    currentQualityEl.textContent = qualityLabels[currentQuality] || 'Auto';
                }
                
                // Update checkmarks
                document.querySelectorAll('.quality-option').forEach(option => {
                    const checkIcon = option.querySelector('i');
                    const optionQuality = option.getAttribute('data-quality');
                    
                    if (checkIcon) {
                        if (optionQuality === currentQuality) {
                            checkIcon.classList.remove('hidden');
                            option.classList.add('active');
                        } else {
                            checkIcon.classList.add('hidden');
                            option.classList.remove('active');
                        }
                    }
                });
            }

            function togglePlay() {
                if (player.getPlayerState() == YT.PlayerState.PLAYING) {
                    player.pauseVideo();
                } else {
                    // Check for watch limit confirmation
                    // Show modal if: Student + Not Live + Watch Limit Exists + First Play + Not Confirmed
                    if (!hasConfirmedWatch && role === 'student' && !isLiveClass && watchLimit > 0 && !viewTracked) {
                        showWatchLimitModal();
                        return;
                    }
                    player.playVideo();
                }
            }
            
            function showWatchLimitModal() {
                document.getElementById('remaining-count-display').textContent = remainingWatches;
                document.getElementById('watchLimitModal').classList.remove('hidden');
            }

            function closeWatchLimitModal() {
                document.getElementById('watchLimitModal').classList.add('hidden');
            }

            function confirmWatch() {
                hasConfirmedWatch = true;
                closeWatchLimitModal();
                player.playVideo();
            }

            function onPlayerStateChange(event) {
                const playIcon = document.getElementById('play-icon');
                const pauseIcon = document.getElementById('pause-icon');
                
                if (event.data == YT.PlayerState.PLAYING) {
                    playIcon.classList.add('hidden');
                    pauseIcon.classList.remove('hidden');
                    
                    // Track view when video starts playing (only once per page load)
                    if (!viewTracked && recordingId > 0 && '<?php echo $role; ?>' === 'student') {
                        trackVideoView();
                        viewTracked = true;
                    }
                } else {
                    playIcon.classList.remove('hidden');
                    pauseIcon.classList.add('hidden');
                }
            }
            
            function trackVideoView() {
                const formData = new FormData();
                formData.append('recording_id', recordingId);
                
                fetch('track_view.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update watch count display if needed
                        if (data.remaining !== undefined && data.remaining >= 0) {
                            // Reload page to update watch count display
                            // Or update DOM directly if preferred
                            const watchInfo = document.querySelector('.video-info .bg-gray-800');
                            if (watchInfo && data.remaining > 0) {
                                watchInfo.querySelector('span').textContent = 
                                    `You can only watch this video ${data.remaining} more time${data.remaining > 1 ? 's' : ''}.`;
                                const countSpan = watchInfo.querySelector('.text-gray-400');
                                if (countSpan) {
                                    countSpan.textContent = `(Watched: ${data.watch_count}/${data.watch_limit})`;
                                }
                            } else if (watchInfo && data.remaining === 0) {
                                watchInfo.querySelector('span').textContent = 
                                    'You have reached the watch limit for this video.';
                                const countSpan = watchInfo.querySelector('.text-gray-400');
                                if (countSpan) {
                                    countSpan.textContent = `(Watched: ${data.watch_count}/${data.watch_limit})`;
                                }
                            }
                        }
                    } else if (data.message === 'Watch limit exceeded') {
                        // Stop video and show message
                        player.pauseVideo();
                        alert('You have reached the watch limit for this video.');
                        window.location.href = '../dashboard/content.php?stream_subject_id=<?php echo $stream_subject_id; ?>&academic_year=<?php echo $academic_year; ?>';
                    }
                })
                .catch(error => {
                    console.error('Error tracking view:', error);
                });
            }

            function formatTime(seconds) {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = Math.floor(seconds % 60);
                
                if (hours > 0) {
                    return hours + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
                }
                return minutes + ':' + String(secs).padStart(2, '0');
            }

            function updateTimeline() {
                try {
                    const currentTime = player.getCurrentTime();
                    const duration = player.getDuration();
                    
                    if (duration && duration > 0) {
                        const progress = (currentTime / duration) * 100;
                        document.getElementById('timeline-progress').style.width = progress + '%';
                        document.getElementById('current-time').textContent = formatTime(currentTime);
                        
                        const totalTimeEl = document.getElementById('total-time');
                        if (totalTimeEl.textContent === '0:00') {
                            totalTimeEl.textContent = formatTime(duration);
                        }
                        
                        const buffered = player.getVideoLoadedFraction();
                        if (buffered) {
                            document.getElementById('timeline-buffer').style.width = (buffered * 100) + '%';
                        }
                    }
                } catch (e) {
                    // Player might not be ready
                }
            }

            function toggleFullscreen() {
                const playerMain = document.querySelector('.player-main');
                
                // Comprehensive check for fullscreen status across browsers
                const isFullScreen = document.fullscreenElement || 
                                     document.webkitFullscreenElement || 
                                     document.mozFullScreenElement || 
                                     document.msFullscreenElement;

                if (!isFullScreen) {
                    // Request fullscreen on player-main
                    if (playerMain.requestFullscreen) {
                        playerMain.requestFullscreen();
                    } else if (playerMain.webkitRequestFullscreen) {
                        playerMain.webkitRequestFullscreen();
                    } else if (playerMain.mozRequestFullScreen) {
                        playerMain.mozRequestFullScreen();
                    } else if (playerMain.msRequestFullscreen) {
                        playerMain.msRequestFullscreen();
                    }
                } else {
                    // Exit fullscreen
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else if (document.mozCancelFullScreen) {
                        document.mozCancelFullScreen();
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen();
                    }
                }
            }

            // Listen for fullscreen changes (e.g., when user presses ESC)
            document.addEventListener('fullscreenchange', handleFullscreenChange);
            document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
            document.addEventListener('mozfullscreenchange', handleFullscreenChange);
            document.addEventListener('MSFullscreenChange', handleFullscreenChange);

            function handleFullscreenChange() {
                const expandIcon = document.getElementById('expand-icon');
                const compressIcon = document.getElementById('compress-icon');
                
                const isFullScreen = document.fullscreenElement || 
                                     document.webkitFullscreenElement || 
                                     document.mozFullScreenElement || 
                                     document.msFullscreenElement;

                if (isFullScreen) {
                    // Entered fullscreen
                    if (expandIcon) expandIcon.classList.add('hidden');
                    if (compressIcon) compressIcon.classList.remove('hidden');
                    // Prevent body scrolling just in case
                    document.body.style.overflow = 'hidden';
                } else {
                    // Exited fullscreen
                    if (expandIcon) expandIcon.classList.remove('hidden');
                    if (compressIcon) compressIcon.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            }

            function switchVideo(recordingId) {
                window.location.href = 'player.php?id=' + recordingId + '&stream_subject_id=<?php echo $stream_subject_id; ?>&academic_year=<?php echo $academic_year; ?>';
            }

            // Prevent right-click on overlay
            document.getElementById('overlay').addEventListener('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });

            // Prevent scrolling on mobile (except in scrollable areas)
            document.addEventListener('touchmove', function(e) {
                // Allow scrolling only in sidebar, video-info, mobile-videos-scroll, and mobile-chat-modal-messages
                const target = e.target;
                const sidebar = document.querySelector('.videos-sidebar');
                const videoInfo = document.querySelector('.video-info');
                const mobileVideosScroll = document.querySelector('.mobile-videos-scroll');
                const mobileChatModalMessages = document.querySelector('.mobile-chat-modal-messages');
                
                if (sidebar && videoInfo && mobileVideosScroll && 
                    !sidebar.contains(target) && 
                    !videoInfo.contains(target) && 
                    !mobileVideosScroll.contains(target) &&
                    !(mobileChatModalMessages && mobileChatModalMessages.contains(target))) {
                    e.preventDefault();
                }
            }, { passive: false });

            // Ensure page takes full height on mobile
            function setFullHeight() {
                const vh = window.innerHeight * 0.01;
                document.documentElement.style.setProperty('--vh', `${vh}px`);
            }
            
            setFullHeight();
            window.addEventListener('resize', setFullHeight);
            window.addEventListener('orientationchange', function() {
                setTimeout(setFullHeight, 100);
                // Close chat popup when switching orientations (to avoid layout issues)
                const mobileChatModal = document.getElementById('mobile-chat-modal');
                if (mobileChatModal && mobileChatModal.classList.contains('active')) {
                    mobileChatModal.classList.remove('active');
                    const input = document.getElementById('mobile-chat-modal-input');
                    if (input) {
                        input.blur();
                    }
                }
            });

            // Initialize quality UI on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    updateQualityUI();
                });
            } else {
                updateQualityUI();
            }

            // Clean up on page unload
            window.addEventListener('beforeunload', function() {
                if (updateInterval) {
                    clearInterval(updateInterval);
                }
                if (chatPollInterval) {
                    clearInterval(chatPollInterval);
                }
                if (participantsPollInterval) {
                    clearInterval(participantsPollInterval);
                }
                <?php if ($is_live_class && $role === 'student'): ?>
                leaveLiveClass();
                <?php endif; ?>
            });

            // Chat functionality
            <?php if ($role === 'student' || $role === 'teacher'): ?>
            function initializeChat() {
                if (chatInitialized) return;
                chatInitialized = true;
                
                // Load initial messages
                loadChatMessages();
                
                // Start polling for new messages every 2 seconds
                chatPollInterval = setInterval(function() {
                    if (lastMessageId > 0) {
                        loadChatMessages(true); // Poll for new messages only
                    }
                }, 2000);
                
                // Setup desktop chat input
                const chatInput = document.getElementById('chat-input');
                if (chatInput) {
                    chatInput.addEventListener('input', function() {
                        this.style.height = 'auto';
                        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
                    });
                    
                    // Handle Enter key (Shift+Enter for new line)
                    chatInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            if (this.value.trim()) {
                                document.getElementById('chat-form').dispatchEvent(new Event('submit'));
                            }
                        }
                    });
                }
                
                // Setup mobile chat modal input
                const mobileChatModalInput = document.getElementById('mobile-chat-modal-input');
                if (mobileChatModalInput) {
                    mobileChatModalInput.addEventListener('input', function() {
                        this.style.height = 'auto';
                        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
                    });
                    
                    // Handle Enter key (Shift+Enter for new line)
                    mobileChatModalInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            if (this.value.trim()) {
                                document.getElementById('mobile-chat-modal-form').dispatchEvent(new Event('submit'));
                            }
                        }
                    });
                }
            }

            function toggleMobileChatModal() {
                const modal = document.getElementById('mobile-chat-modal');
                if (!modal) return;

                isChatOpen = !modal.classList.contains('active');
                modal.classList.toggle('active');
                
                if (modal.classList.contains('active')) {
                    // Clear unread count when opening chat
                    unreadCount = 0;
                    updateChatBadge();
                    // Focus input when opening
                    const input = document.getElementById('mobile-chat-modal-input');
                    if (input) {
                        setTimeout(() => input.focus(), 300);
                    }
                    // Scroll to bottom when opening
                    setTimeout(() => scrollChatToBottom(true), 300);
                } else {
                    // Blur input when closing
                    const input = document.getElementById('mobile-chat-modal-input');
                    if (input) {
                        input.blur();
                    }
                }
            }

            // Close modal when clicking on overlay (outside content)
            function setupMobileChatModalClose() {
                const mobileChatModal = document.getElementById('mobile-chat-modal');
                const mobileChatModalContent = mobileChatModal ? mobileChatModal.querySelector('.mobile-chat-modal-content') : null;
                
                if (mobileChatModal) {
                    mobileChatModal.addEventListener('click', function(e) {
                        // Close if clicking directly on the modal (overlay), not on content
                        if (e.target === mobileChatModal || (mobileChatModalContent && !mobileChatModalContent.contains(e.target))) {
                            toggleMobileChatModal();
                        }
                    });
                }
            }
            
            function setupParticipantsModalClose() {
                const participantsModal = document.getElementById('participants-modal');
                const participantsModalContent = participantsModal ? participantsModal.querySelector('.mobile-chat-modal-content') : null;
                
                if (participantsModal) {
                    participantsModal.addEventListener('click', function(e) {
                        // Close if clicking directly on the modal (overlay), not on content
                        if (e.target === participantsModal || (participantsModalContent && !participantsModalContent.contains(e.target))) {
                            toggleParticipantsModal();
                        }
                    });
                }
            }

            function loadChatMessages(polling = false) {
                const url = polling 
                    ? `get_messages.php?recording_id=${recordingId}&last_message_id=${lastMessageId}`
                    : `get_messages.php?recording_id=${recordingId}`;
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.messages) {
                            if (polling && data.messages.length > 0) {
                                // Append new messages
                                data.messages.forEach(message => {
                                    appendChatMessage(message);
                                    // Show notification if chat is closed and message is not from current user
                                    if (!isChatOpen && !message.is_own_message) {
                                        showChatNotification(message);
                                        unreadCount++;
                                        updateChatBadge();
                                    }
                                });
                                // Always scroll to bottom when new messages arrive (if chat is open)
                                if (isChatOpen) {
                                    scrollChatToBottom();
                                }
                            } else if (!polling) {
                                // Initial load - render all messages
                                renderChatMessages(data.messages);
                                // Scroll to bottom on initial load
                                scrollChatToBottom();
                            }
                            
                            // Update last message ID
                            if (data.messages.length > 0) {
                                lastMessageId = Math.max(...data.messages.map(m => m.id));
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading messages:', error);
                    });
            }

            function renderChatMessages(messages) {
                const mobileChatModalMessages = document.getElementById('mobile-chat-modal-messages');
                
                // Render in chat modal (used for both desktop and mobile)
                if (mobileChatModalMessages) {
                    if (messages.length === 0) {
                        mobileChatModalMessages.innerHTML = `
                            <div class="chat-empty">
                                <div>
                                    <i class="fas fa-comments text-4xl mb-2 text-gray-600"></i>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            </div>
                        `;
                    } else {
                        mobileChatModalMessages.innerHTML = '';
                        messages.forEach(message => appendChatMessage(message, false, true));
                    }
                }
            }

            function appendChatMessage(message, animate = true, isMobile = null) {
                // Always append to mobile chat modal (used for both desktop and mobile)
                const chatMessages = document.getElementById('mobile-chat-modal-messages');
                    
                if (!chatMessages) return;
                
                // Remove empty state if exists
                const emptyState = chatMessages.querySelector('.chat-empty');
                if (emptyState) {
                    emptyState.remove();
                }
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `chat-message ${message.is_own_message ? 'own-message' : 'other-message'}`;
                if (!animate) {
                    messageDiv.style.animation = 'none';
                }
                
                const avatarUrl = message.sender_avatar 
                    ? `../${message.sender_avatar}`
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(message.sender_name)}&background=dc2626&color=fff&size=128`;
                
                const messageTime = formatChatTime(message.created_at);
                
                messageDiv.innerHTML = `
                    <img src="${escapeHtml(avatarUrl)}" 
                         alt="${escapeHtml(message.sender_name)}"
                         class="chat-message-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(message.sender_name)}&background=dc2626&color=fff&size=128'">
                    <div class="chat-message-content-wrapper">
                        <div class="chat-message-header">
                            <span class="chat-message-sender">${escapeHtml(message.sender_name)}</span>
                            <span class="chat-message-time">${messageTime}</span>
                        </div>
                        <div class="chat-message-content">${escapeHtml(message.message).replace(/\n/g, '<br>')}</div>
                    </div>
                `;
                
                chatMessages.appendChild(messageDiv);
                // Auto-scroll to bottom when new message is added (only if user is near bottom)
                const chatMessagesEl = document.getElementById('mobile-chat-modal-messages');
                if (chatMessagesEl && isChatOpen) {
                    const isNearBottom = chatMessagesEl.scrollHeight - chatMessagesEl.scrollTop - chatMessagesEl.clientHeight < 100;
                    if (isNearBottom || message.is_own_message) {
                        scrollChatToBottom(isMobile);
                    }
                }
            }

            function scrollChatToBottom(isMobile = null) {
                const chatMessages = document.getElementById('mobile-chat-modal-messages');
                    
                if (chatMessages) {
                    // Use requestAnimationFrame for smoother scrolling
                    requestAnimationFrame(() => {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    });
                }
            }

            function showChatNotification(message) {
                const container = document.getElementById('chat-notifications-container');
                if (!container) return;

                const notification = document.createElement('div');
                notification.className = 'chat-notification';
                notification.onclick = function() {
                    toggleMobileChatModal();
                    removeNotification(notification);
                };

                const avatarUrl = message.sender_avatar 
                    ? `../${message.sender_avatar}`
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(message.sender_name)}&background=dc2626&color=fff&size=128`;

                notification.innerHTML = `
                    <div class="chat-notification-header">
                        <img src="${escapeHtml(avatarUrl)}" 
                             alt="${escapeHtml(message.sender_name)}"
                             class="chat-notification-avatar"
                             onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(message.sender_name)}&background=dc2626&color=fff&size=128'">
                        <div class="chat-notification-info">
                            <div class="chat-notification-sender">${escapeHtml(message.sender_name)}</div>
                            <div class="chat-notification-message">${escapeHtml(message.message)}</div>
                        </div>
                        <button class="chat-notification-close" onclick="event.stopPropagation(); removeNotification(this.parentElement);">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;

                container.appendChild(notification);
                
                // Trigger animation
                setTimeout(() => {
                    notification.classList.add('show');
                }, 10);

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    removeNotification(notification);
                }, 5000);
            }

            function removeNotification(notification) {
                if (notification && notification.parentElement) {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentElement) {
                            notification.parentElement.removeChild(notification);
                        }
                    }, 300);
                }
            }

            function updateChatBadge() {
                const badge = document.getElementById('chat-notification-badge');
                if (badge) {
                    if (unreadCount > 0) {
                        badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            }

            function formatChatTime(timestamp) {
                const date = new Date(timestamp);
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                if (diffMins < 1) {
                    return 'Just now';
                } else if (diffMins < 60) {
                    return `${diffMins}m ago`;
                } else if (diffHours < 24) {
                    return `${diffHours}h ago`;
                } else if (diffDays < 7) {
                    return `${diffDays}d ago`;
                } else {
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
                }
            }

            function sendChatMessage(event) {
                event.preventDefault();
                sendChatMessageFromInput(false);
            }

            function sendMobileChatModalMessage(event) {
                event.preventDefault();
                sendChatMessageFromInput(true);
            }

            function sendChatMessageFromInput(isMobile) {
                const chatInput = isMobile 
                    ? document.getElementById('mobile-chat-modal-input')
                    : document.getElementById('chat-input');
                const chatSendBtn = isMobile
                    ? document.getElementById('mobile-chat-modal-send-btn')
                    : document.getElementById('chat-send-btn');
                    
                if (!chatInput) return;
                
                const message = chatInput.value.trim();
                if (!message) return;
                
                // Disable input and button
                chatInput.disabled = true;
                if (chatSendBtn) chatSendBtn.disabled = true;
                
                const formData = new FormData();
                formData.append('recording_id', recordingId);
                formData.append('message', message);
                
                fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        chatInput.value = '';
                        chatInput.style.height = 'auto';
                        
                        // Clear the other input if it exists
                        const otherInput = isMobile 
                            ? document.getElementById('chat-input')
                            : document.getElementById('mobile-chat-modal-input');
                        if (otherInput && otherInput.value.trim() === message.trim()) {
                            otherInput.value = '';
                            otherInput.style.height = 'auto';
                        }
                        
                        // Append the sent message immediately
                        if (data.data) {
                            data.data.is_own_message = true;
                            appendChatMessage(data.data, true, true);
                            lastMessageId = Math.max(lastMessageId, data.data.id);
                        }
                    } else {
                        alert('Error sending message: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    alert('Error sending message. Please try again.');
                })
                .finally(() => {
                    chatInput.disabled = false;
                    if (chatSendBtn) chatSendBtn.disabled = false;
                    chatInput.focus();
                });
            }

            function escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, m => map[m]);
            }

            // Initialize chat when page loads
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    initializeChat();
                    setupMobileChatModalClose();
                    <?php if ($is_live_class): ?>
                    setupParticipantsModalClose();
                    <?php endif; ?>
                    initializeFileUpload();
                    loadFiles();
                });
            } else {
                initializeChat();
                setupMobileChatModalClose();
                <?php if ($is_live_class): ?>
                setupParticipantsModalClose();
                <?php endif; ?>
                initializeFileUpload();
                loadFiles();
            }

            // File Upload and Download functionality
            function toggleUploadArea() {
                const uploadArea = document.getElementById('upload-area');
                if (uploadArea) {
                    uploadArea.style.display = uploadArea.style.display === 'none' ? 'block' : 'none';
                }
            }

            function initializeFileUpload() {
                const fileInput = document.getElementById('file-input');
                const uploadForm = document.getElementById('upload-form');
                const uploadBtn = document.getElementById('upload-btn');
                const uploadArea = document.getElementById('upload-area');
                
                if (!fileInput || !uploadForm || !uploadArea) return;

                const uploadLabel = uploadArea.querySelector('.upload-label');

                // Show upload button when file is selected
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        uploadBtn.style.display = 'flex';
                        uploadLabel.querySelector('span').textContent = this.files[0].name;
                        // Scroll to upload button to make it visible
                        setTimeout(() => {
                            uploadBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 100);
                    } else {
                        uploadBtn.style.display = 'none';
                        uploadLabel.querySelector('span').textContent = 'Click to select file or drag and drop';
                    }
                });

                // Drag and drop
                uploadArea.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#dc2626';
                });

                uploadArea.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#333';
                });

                uploadArea.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#333';
                    
                    if (e.dataTransfer.files.length > 0) {
                        fileInput.files = e.dataTransfer.files;
                        fileInput.dispatchEvent(new Event('change'));
                        // Scroll to upload button after file is dropped
                        setTimeout(() => {
                            if (uploadBtn) {
                                uploadBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        }, 100);
                    }
                });

                // Handle form submission
                uploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    uploadFile();
                });
            }

            function uploadFile() {
                const fileInput = document.getElementById('file-input');
                const uploadForm = document.getElementById('upload-form');
                const uploadBtn = document.getElementById('upload-btn');
                const progressContainer = document.getElementById('upload-progress');
                const progressFill = document.getElementById('progress-fill');
                const progressText = document.getElementById('progress-text');

                if (!fileInput.files.length) {
                    alert('Please select a file to upload');
                    return;
                }

                const file = fileInput.files[0];
                const maxSize = 50 * 1024 * 1024; // 50MB

                if (file.size > maxSize) {
                    alert('File size exceeds 50MB limit');
                    return;
                }

                const formData = new FormData(uploadForm);
                formData.append('file', file);

                uploadBtn.disabled = true;
                progressContainer.style.display = 'block';
                progressFill.style.width = '0%';
                progressText.textContent = '0%';

                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressFill.style.width = percentComplete + '%';
                        progressText.textContent = Math.round(percentComplete) + '%';
                    }
                });

                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                progressFill.style.width = '100%';
                                progressText.textContent = '100%';
                                setTimeout(() => {
                                    progressContainer.style.display = 'none';
                                    uploadForm.reset();
                                    uploadBtn.style.display = 'none';
                                    const uploadArea = document.getElementById('upload-area');
                                    const uploadLabel = uploadArea ? uploadArea.querySelector('.upload-label') : null;
                                    if (uploadLabel) {
                                        uploadLabel.querySelector('span').textContent = 'Click to select file or drag and drop';
                                    }
                                    // Close upload area after successful upload
                                    if (uploadArea) {
                                        uploadArea.style.display = 'none';
                                    }
                                    loadFiles();
                                    alert('File uploaded successfully!');
                                }, 500);
                            } else {
                                alert('Error: ' + (response.message || 'Upload failed'));
                                progressContainer.style.display = 'none';
                            }
                        } catch (e) {
                            alert('Error parsing server response');
                            progressContainer.style.display = 'none';
                        }
                    } else {
                        alert('Upload failed. Please try again.');
                        progressContainer.style.display = 'none';
                    }
                    uploadBtn.disabled = false;
                });

                xhr.addEventListener('error', function() {
                    alert('Upload failed. Please check your connection.');
                    progressContainer.style.display = 'none';
                    uploadBtn.disabled = false;
                });

                xhr.open('POST', 'upload_file.php');
                xhr.send(formData);
            }

            function loadFiles() {
                const filesList = document.getElementById('files-list');
                if (!filesList) return;

                filesList.innerHTML = '<div class="files-loading">Loading files...</div>';

                fetch(`get_files.php?recording_id=<?php echo $recording_id; ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.files) {
                            renderFiles(data.files);
                        } else {
                            filesList.innerHTML = '<div class="files-empty">No files uploaded yet</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading files:', error);
                        filesList.innerHTML = '<div class="files-empty">Error loading files</div>';
                    });
            }

            function renderFiles(files) {
                const filesList = document.getElementById('files-list');
                if (!filesList) return;

                if (files.length === 0) {
                    filesList.innerHTML = '<div class="files-empty">No files uploaded yet</div>';
                    return;
                }

                filesList.innerHTML = files.map(file => {
                    const fileSize = formatFileSize(file.file_size);
                    const fileIcon = getFileIcon(file.file_extension);
                    const uploadDate = formatFileDate(file.upload_date);
                    const uploaderInfo = file.is_own_file 
                        ? 'You' 
                        : `${file.uploader_name} (${file.uploader_role === 'teacher' ? 'Teacher' : 'Student'})`;

                    return `
                        <div class="file-item">
                            <div class="file-icon">
                                <i class="${fileIcon}"></i>
                            </div>
                            <div class="file-info">
                                <div class="file-name" title="${escapeHtml(file.file_name)}">${escapeHtml(file.file_name)}</div>
                                <div class="file-meta">
                                    <span><i class="fas fa-user"></i> ${escapeHtml(uploaderInfo)}</span>
                                    <span><i class="fas fa-calendar"></i> ${uploadDate}</span>
                                    <span><i class="fas fa-weight"></i> ${fileSize}</span>
                                </div>
                            </div>
                            <div class="file-actions">
                                <a href="../${escapeHtml(file.file_path)}" download="${escapeHtml(file.file_name)}" class="file-download-btn" onclick="event.stopPropagation();">
                                    <i class="fas fa-download"></i>
                                    Download
                                </a>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            }

            function formatFileDate(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);

                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m ago`;
                if (diffHours < 24) return `${diffHours}h ago`;
                if (diffDays < 7) return `${diffDays}d ago`;
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
            }

            function getFileIcon(extension) {
                const ext = extension ? extension.toLowerCase() : '';
                const iconMap = {
                    'pdf': 'fas fa-file-pdf',
                    'doc': 'fas fa-file-word',
                    'docx': 'fas fa-file-word',
                    'xls': 'fas fa-file-excel',
                    'xlsx': 'fas fa-file-excel',
                    'ppt': 'fas fa-file-powerpoint',
                    'pptx': 'fas fa-file-powerpoint',
                    'zip': 'fas fa-file-archive',
                    'rar': 'fas fa-file-archive',
                    'jpg': 'fas fa-file-image',
                    'jpeg': 'fas fa-file-image',
                    'png': 'fas fa-file-image',
                    'gif': 'fas fa-file-image',
                    'txt': 'fas fa-file-alt',
                    'mp4': 'fas fa-file-video',
                    'mp3': 'fas fa-file-audio'
                };
                return iconMap[ext] || 'fas fa-file';
            }

            // Live Class Functions - Always define these functions
            function toggleParticipantsModal() {
                const modal = document.getElementById('participants-modal');
                if (!modal) {
                    console.error('Participants modal not found');
                    return;
                }

                const isActive = modal.classList.contains('active');
                const modalContent = modal.querySelector('.mobile-chat-modal-content');
                
                // Remove ALL inline styles to let CSS handle everything
                modal.removeAttribute('style');
                if (modalContent) {
                    modalContent.removeAttribute('style');
                }
                
                if (isActive) {
                    // Closing modal
                    modal.classList.remove('active');
                    
                    // Stop polling
                    if (participantsPollInterval) {
                        clearInterval(participantsPollInterval);
                        participantsPollInterval = null;
                    }
                } else {
                    // Opening modal
                    modal.classList.add('active');
                    
                    // Load participants
                    loadParticipants('participants-list');
                    
                    // Start polling for participants
                    if (!participantsPollInterval) {
                        participantsPollInterval = setInterval(() => loadParticipants('participants-list'), 3000);
                    }
                }
            }
            
            function loadParticipants(listId = 'participants-list') {
                const container = document.getElementById(listId);
                if (!container) {
                    console.error('Participants list container not found:', listId);
                    return;
                }
                
                fetch(`get_participants.php?recording_id=${recordingId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderParticipants(data.participants, data.online_count, listId);
                            // Update badge
                            const badge = document.getElementById('participants-count-badge');
                            if (badge) {
                                if (data.online_count > 0) {
                                    badge.textContent = data.online_count;
                                    badge.classList.remove('hidden');
                                } else {
                                    badge.classList.add('hidden');
                                }
                            }
                        } else {
                            console.error('Failed to load participants:', data.message);
                            container.innerHTML = `
                                <div class="chat-empty">
                                    <div>
                                        <i class="fas fa-users text-4xl mb-2 text-gray-600"></i>
                                        <p>Error loading participants: ${data.message || 'Unknown error'}</p>
                                    </div>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error loading participants:', error);
                        if (container) {
                            container.innerHTML = `
                                <div class="chat-empty">
                                    <div>
                                        <i class="fas fa-users text-4xl mb-2 text-gray-600"></i>
                                        <p>Error loading participants. Please try again.</p>
                                    </div>
                                </div>
                            `;
                        }
                    });
            }

            function renderParticipants(participants, onlineCount, listId = 'participants-list') {
                const container = document.getElementById(listId);
                if (!container) return;

                if (participants.length === 0) {
                    container.innerHTML = `
                        <div class="chat-empty">
                            <div>
                                <i class="fas fa-users text-4xl mb-2 text-gray-600"></i>
                                <p>No participants yet</p>
                            </div>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div style="padding: 1rem; border-bottom: 1px solid #374151; margin-bottom: 0.5rem;">
                        <p style="color: white; font-weight: 600; margin: 0;">Total: ${participants.length} | Online: ${onlineCount}</p>
                    </div>
                    ${participants.map(p => {
                        const avatarUrl = p.profile_picture 
                            ? `../${p.profile_picture}`
                            : `https://ui-avatars.com/api/?name=${encodeURIComponent(p.name)}&background=059669&color=fff&size=128`;
                        return `
                            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid #374151;">
                                <img src="${escapeHtml(avatarUrl)}" 
                                     alt="${escapeHtml(p.name)}"
                                     style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid ${p.is_online ? '#10b981' : '#4b5563'};"
                                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.name)}&background=059669&color=fff&size=128'">
                                <div style="flex: 1; min-width: 0;">
                                    <p style="color: white; font-weight: 500; margin: 0 0 0.25rem 0;">${escapeHtml(p.name)}</p>
                                    <p style="color: #9ca3af; font-size: 0.75rem; margin: 0;">
                                        ${p.is_online ? '<span style="color: #10b981;">● Online</span>' : 'Joined: ' + formatChatTime(p.joined_at)}
                                    </p>
                                </div>
                            </div>
                        `;
                    }).join('')}
                `;
            }
            
            <?php if ($is_live_class): ?>
            function showEndLiveClassModal() {
                const modal = document.getElementById('end-live-modal');
                if (modal) {
                    modal.classList.add('active');
                }
            }

            function closeEndLiveClassModal() {
                const modal = document.getElementById('end-live-modal');
                if (modal) {
                    modal.classList.remove('active');
                }
            }

            function confirmEndLiveClass() {
                const saveOption = document.querySelector('input[name="save_option"]:checked');
                const saveToRecordings = saveOption ? saveOption.value === 'yes' : true;

                const formData = new FormData();
                formData.append('action', 'end');
                formData.append('recording_id', recordingId);
                formData.append('save_to_recordings', saveToRecordings ? 'yes' : 'no');

                fetch('../dashboard/manage_live_class.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => {
                            window.location.href = '../dashboard/content.php?stream_subject_id=<?php echo $stream_subject_id; ?>&academic_year=<?php echo $academic_year; ?>';
                        }, 1500);
                    } else {
                        showToast(data.message || 'Error ending live class', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error ending live class. Please try again.', 'error');
                });
            }

            // Auto-join live class for students
            function joinLiveClass() {
                if (!isLiveClass || hasJoinedLiveClass || '<?php echo $role; ?>' !== 'student') return;
                
                if (liveClassStatus === 'ongoing' || liveClassStatus === 'scheduled') {
                    const formData = new FormData();
                    formData.append('recording_id', recordingId);

                    fetch('join_live_class.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            hasJoinedLiveClass = true;
                            if (!data.already_joined) {
                                showToast('Joined live class', 'success');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error joining live class:', error);
                    });
                }
            }

            // Leave live class when page unloads
            function leaveLiveClass() {
                if (!isLiveClass || !hasJoinedLiveClass || '<?php echo $role; ?>' !== 'student') return;
                
                if (liveClassStatus === 'ongoing') {
                    const formData = new FormData();
                    formData.append('recording_id', recordingId);

                    // Use sendBeacon for reliable delivery on page unload
                    navigator.sendBeacon('leave_live_class.php', formData);
                }
            }

            // Initialize live class features
            if (isLiveClass) {
                // Auto-join for students
                if ('<?php echo $role; ?>' === 'student') {
                    setTimeout(joinLiveClass, 1000); // Delay to ensure page is loaded
                }

                // Load participants if teacher
                if (isTeacherOwner) {
                    setTimeout(() => {
                        loadParticipants();
                        participantsPollInterval = setInterval(loadParticipants, 3000);
                    }, 1000);
                }
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
                
                setTimeout(() => {
                    toast.classList.remove('translate-x-full', 'opacity-0');
                }, 10);
                
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
            <?php endif; ?>
        </script>
    <?php endif; ?>
</body>
</html>
</body>
</html>