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
    // Get current recording details
    $query = "SELECT r.id, r.title, r.description, r.youtube_video_id, r.youtube_url, r.thumbnail_url, 
                     r.created_at, r.free_video, r.watch_limit,
                     ta.stream_subject_id, ta.academic_year,
                     s.name as stream_name, sub.name as subject_name,
                     u.first_name, u.second_name, u.profile_picture
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              WHERE r.id = ? AND r.status = 'active'";
    
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
                       ORDER BY r.created_at DESC";
        
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

// For students: Check payment access and watch limit
$can_watch = true;
$paid_months = [];
$watch_count = 0;
$watch_limit = 0;
$remaining_watches = 0;
if ($role === 'student' && $current_recording) {
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

        /* Hide sidebar in fullscreen */
        .player-container:fullscreen .videos-sidebar,
        .player-container:-webkit-full-screen .videos-sidebar,
        .player-container:-moz-full-screen .videos-sidebar,
        .player-container:-ms-fullscreen .videos-sidebar {
            width: 0;
            opacity: 0;
            overflow: hidden;
            border-right: none;
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
        }

        .player-wrapper {
            position: relative;
            width: 100%;
            flex: 1;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
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
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* Transparent Overlay - Prevents interaction with YouTube player */
        #overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: calc(100% - 100px);
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
            transition: opacity 0.3s ease, height 0.3s ease;
            flex-shrink: 0;
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

        /* Back Button */
        .back-btn {
            position: absolute;
            top: 1rem;
            left: 1rem;
            z-index: 30;
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
            z-index: 1000;
            transition: background 0.2s, transform 0.2s;
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
            z-index: 2000;
            display: none;
            align-items: flex-end;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
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
                transform: translateX(0);
            }

            .mobile-chat-modal-messages {
                max-height: calc(100vh - 140px);
                overflow-y: auto;
                overflow-x: hidden;
                scroll-behavior: smooth;
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
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .player-wrapper {
                flex: 1;
                min-height: 0;
                height: 100%;
                overflow: hidden;
            }

            .video-info {
                flex-shrink: 0;
                max-height: none;
                overflow-y: auto;
                padding-bottom: 0;
                border-bottom: none;
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

            /* Show chat button in fullscreen mode (for both desktop and mobile) */
            .player-container:fullscreen .mobile-chat-btn,
            .player-container:-webkit-full-screen .mobile-chat-btn,
            .player-container:-moz-full-screen .mobile-chat-btn,
            .player-container:-ms-fullscreen .mobile-chat-btn,
            :fullscreen .mobile-chat-btn,
            :-webkit-full-screen .mobile-chat-btn,
            :-moz-full-screen .mobile-chat-btn,
            :-ms-fullscreen .mobile-chat-btn {
                display: flex !important;
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
                overflow-y: auto;
                overflow-x: hidden;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                -webkit-overflow-scrolling: touch;
                min-height: 0;
                max-height: calc(70vh - 140px);
                scroll-behavior: smooth;
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
<body>
    <?php if (!$current_recording || !$can_watch): ?>
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
    <?php else: ?>
        <div class="player-container">
            <!-- Left Sidebar - Other Videos -->
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
                            // Triple-check: skip if this is the current video
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
                   class="back-btn">
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
                </div>

                <!-- Mobile Videos Section (shown only on mobile) -->
                <div class="mobile-videos-section">
                    <div class="mobile-videos-header">
                        <h3>
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F Y', strtotime($current_recording['created_at'])); ?> - Other Videos
                        </h3>
                    </div>
                    <div class="mobile-videos-scroll">
                        <?php if (empty($other_recordings)): ?>
                            <p class="text-gray-500 text-sm text-center py-4" style="width: 100%;">No other videos this month</p>
                        <?php else: ?>
                            <?php foreach ($other_recordings as $recording): ?>
                                <?php 
                                // Skip if this is the current video
                                if ($recording['id'] == $recording_id) continue; 
                                ?>
                                <div class="mobile-video-item" onclick="switchVideo(<?php echo $recording['id']; ?>)">
                                    <img src="<?php echo htmlspecialchars($recording['thumbnail_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($recording['title']); ?>"
                                         class="mobile-video-thumbnail"
                                         onerror="this.src='https://img.youtube.com/vi/<?php echo htmlspecialchars($recording['youtube_video_id']); ?>/hqdefault.jpg'">
                                    <div class="mobile-video-info">
                                        <div class="mobile-video-title"><?php echo htmlspecialchars($recording['title']); ?></div>
                                        <div class="mobile-video-date">
                                            <i class="far fa-clock mr-1"></i>
                                            <?php echo date('M d, Y', strtotime($recording['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        <!-- Chat Floating Button (shown on desktop and mobile) -->
        <?php if ($role === 'student' || $role === 'teacher'): ?>
        <button class="mobile-chat-btn" id="mobile-chat-btn" onclick="toggleMobileChatModal()" title="Chat">
            <i class="fas fa-comments"></i>
            <span class="chat-notification-badge hidden" id="chat-notification-badge">0</span>
        </button>

        <!-- Mobile Chat Modal -->
        <div class="mobile-chat-modal" id="mobile-chat-modal">
            <div class="mobile-chat-modal-header">
                <h3>
                    <i class="fas fa-comments"></i>
                    Chat
                </h3>
                <button class="mobile-chat-modal-close" onclick="toggleMobileChatModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mobile-chat-modal-content">
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
        </div>

            <!-- Chat Notifications Container -->
            <?php if ($role === 'student' || $role === 'teacher'): ?>
            <div id="chat-notifications-container"></div>
            <?php endif; ?>
        </div>

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
                player = new YT.Player('player', {
                    height: '100%',
                    width: '100%',
                    videoId: currentVideoId,
                    playerVars: {
                        'autoplay': 0,
                        'controls': 0,
                        'rel': 0,
                        'showinfo': 0,
                        'modestbranding': 1,
                        'playsinline': 1
                    },
                    events: {
                        'onReady': onPlayerReady,
                        'onStateChange': onPlayerStateChange
                    }
                });
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
                    player.playVideo();
                }
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
                const container = document.querySelector('.player-container');
                const sidebar = document.querySelector('.videos-sidebar');
                const chatSidebar = document.querySelector('.chat-sidebar');
                const videoInfo = document.querySelector('.video-info');
                
                if (!document.fullscreenElement) {
                    container.requestFullscreen().catch(err => {
                        console.log('Error attempting to enable fullscreen:', err);
                    });
                    document.getElementById('expand-icon').classList.add('hidden');
                    document.getElementById('compress-icon').classList.remove('hidden');
                    // Hide sidebars and video info
                    if (sidebar) {
                        sidebar.style.display = 'none';
                    }
                    if (chatSidebar) {
                        chatSidebar.style.display = 'none';
                    }
                    if (videoInfo) {
                        videoInfo.style.display = 'none';
                    }
                } else {
                    document.exitFullscreen();
                    document.getElementById('expand-icon').classList.remove('hidden');
                    document.getElementById('compress-icon').classList.add('hidden');
                    // Show sidebars and video info
                    if (sidebar) {
                        sidebar.style.display = 'flex';
                    }
                    if (chatSidebar) {
                        chatSidebar.style.display = 'flex';
                    }
                    if (videoInfo) {
                        videoInfo.style.display = 'block';
                    }
                }
            }

            // Listen for fullscreen changes (e.g., when user presses ESC)
            document.addEventListener('fullscreenchange', handleFullscreenChange);
            document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
            document.addEventListener('mozfullscreenchange', handleFullscreenChange);
            document.addEventListener('MSFullscreenChange', handleFullscreenChange);

            function handleFullscreenChange() {
                const sidebar = document.querySelector('.videos-sidebar');
                const chatSidebar = document.querySelector('.chat-sidebar');
                const videoInfo = document.querySelector('.video-info');
                const expandIcon = document.getElementById('expand-icon');
                const compressIcon = document.getElementById('compress-icon');
                
                if (document.fullscreenElement || 
                    document.webkitFullscreenElement || 
                    document.mozFullScreenElement || 
                    document.msFullscreenElement) {
                    // Entered fullscreen
                    if (sidebar) {
                        sidebar.style.display = 'none';
                    }
                    if (chatSidebar) {
                        chatSidebar.style.display = 'none';
                    }
                    if (videoInfo) {
                        videoInfo.style.display = 'none';
                    }
                    if (expandIcon) expandIcon.classList.add('hidden');
                    if (compressIcon) compressIcon.classList.remove('hidden');
                } else {
                    // Exited fullscreen
                    if (sidebar) {
                        sidebar.style.display = 'flex';
                    }
                    if (chatSidebar) {
                        chatSidebar.style.display = 'flex';
                    }
                    if (videoInfo) {
                        videoInfo.style.display = 'block';
                    }
                    if (expandIcon) expandIcon.classList.remove('hidden');
                    if (compressIcon) compressIcon.classList.add('hidden');
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
                // Always use popup modal (both desktop and mobile)
                const mobileChatModal = document.getElementById('mobile-chat-modal');
                if (mobileChatModal) {
                    isChatOpen = !mobileChatModal.classList.contains('active');
                    mobileChatModal.classList.toggle('active');
                    if (mobileChatModal.classList.contains('active')) {
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
                });
            } else {
                initializeChat();
                setupMobileChatModalClose();
            }
            <?php endif; ?>
        </script>
    <?php endif; ?>
</body>
</html>

