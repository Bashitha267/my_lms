<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Get live class ID from URL
$live_class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$live_class = null;
$is_teacher = false;
$can_access = false;

if ($live_class_id > 0) {
    // Get live class details
    $query = "SELECT lc.id, lc.title, lc.description, lc.youtube_video_id, lc.youtube_url, lc.stream_url,
                     lc.status, lc.actual_start_time, lc.end_time,
                     ta.teacher_id, ta.stream_subject_id, ta.academic_year,
                     s.name as stream_name, sub.name as subject_name,
                     u.first_name, u.second_name, u.profile_picture
              FROM live_classes lc
              INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              WHERE lc.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $live_class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $live_class = $result->fetch_assoc();
        $is_teacher = ($role === 'teacher' && $live_class['teacher_id'] === $user_id);
        
        // Check access: Teacher can access anytime, students only when status is 'ongoing'
        if ($is_teacher) {
            $can_access = true;
        } elseif ($role === 'student' && $live_class['status'] === 'ongoing') {
            // Check if student is enrolled
            $enroll_query = "SELECT id FROM student_enrollment 
                           WHERE student_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active'
                           LIMIT 1";
            $enroll_stmt = $conn->prepare($enroll_query);
            $enroll_stmt->bind_param("sii", $user_id, $live_class['stream_subject_id'], $live_class['academic_year']);
            $enroll_stmt->execute();
            $enroll_result = $enroll_stmt->get_result();
            
            if ($enroll_result->num_rows > 0) {
                $can_access = true;
                
                // Track participant (if not already tracked)
                $participant_query = "INSERT INTO live_class_participants (live_class_id, student_id) 
                                     VALUES (?, ?)
                                     ON DUPLICATE KEY UPDATE joined_at = CURRENT_TIMESTAMP, left_at = NULL";
                $participant_stmt = $conn->prepare($participant_query);
                $participant_stmt->bind_param("is", $live_class_id, $user_id);
                $participant_stmt->execute();
                $participant_stmt->close();
            }
            $enroll_stmt->close();
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo $live_class ? htmlspecialchars($live_class['title']) : 'Live Class'; ?> - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        .hidden { display: none !important; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            height: 100dvh;
            width: 100%;
            width: 100dvw;
            overflow: hidden;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            font-family: 'Inter', sans-serif;
            background: #000;
        }

        body { z-index: 9999; }

        .player-container {
            display: flex;
            height: 100dvh;
            width: 100dvw;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            z-index: 10000;
            background: #000;
        }

        /* Right Sidebar - Participants */
        .participants-sidebar {
            width: 320px;
            background: #1a1a1a;
            overflow-y: auto;
            border-left: 1px solid #333;
            display: flex;
            flex-direction: column;
        }

        @media (max-width: 768px) {
            .participants-sidebar {
                display: none;
            }
        }

        .participants-sidebar-header {
            padding: 1.5rem;
            background: #0f0f0f;
            border-bottom: 1px solid #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .participants-list {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }

        .participant-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: #252525;
            border-radius: 0.5rem;
        }

        .participant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid #dc2626;
        }

        .participant-info {
            flex: 1;
            min-width: 0;
        }

        .participant-name {
            font-size: 0.875rem;
            font-weight: 500;
            color: #fff;
        }

        .participant-role {
            font-size: 0.75rem;
            color: #9ca3af;
        }

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
        }

        #player {
            width: 100%;
            height: 100%;
            border: 0;
        }

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

        .end-live-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .end-live-btn:hover {
            background: #b91c1c;
        }

        .spacer {
            flex: 1;
        }

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
        }

        .video-info {
            padding: 1.5rem;
            background: #0f0f0f;
            border-top: 1px solid #333;
            flex-shrink: 0;
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

        /* Chat Modal - Same as player.php */
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
        }

        .mobile-chat-modal.active .mobile-chat-modal-content {
            transform: translateY(0);
        }

        @media (min-width: 769px) {
            .mobile-chat-modal {
                align-items: stretch;
                justify-content: flex-end;
            }
            .mobile-chat-modal-content {
                width: 400px;
                max-height: 100vh;
                height: 100vh;
                border-radius: 0;
                transform: translateX(100%);
            }
            .mobile-chat-modal.active .mobile-chat-modal-content {
                transform: translateX(0);
            }
        }

        /* End Live Class Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: #1a1a1a;
            border-radius: 0.5rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            border: 1px solid #333;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            margin-bottom: 1rem;
        }

        .modal-body {
            color: #e5e7eb;
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 500;
            border: none;
            transition: background 0.2s;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #4b5563;
            color: white;
        }

        .btn-secondary:hover {
            background: #374151;
        }

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
        }

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
        }

        .chat-notification-badge.hidden {
            display: none;
        }

        /* Chat styles from player.php */
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
            scroll-behavior: smooth;
        }

        .chat-message {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
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
        }

        .chat-message-content {
            padding: 0.75rem;
            border-radius: 0.5rem;
            word-wrap: break-word;
            font-size: 0.875rem;
        }

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
            color: white;
            font-size: 0.875rem;
            resize: none;
            max-height: 100px;
            min-height: 44px;
        }

        .mobile-chat-modal-send-btn {
            padding: 0.75rem 1rem;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
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

        /* Chat message styles */
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

        .mobile-chat-modal-messages .chat-empty {
            padding: 2rem 1rem;
        }

        .mobile-chat-modal-messages .chat-empty i {
            font-size: 3rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .mobile-chat-modal-messages .chat-empty p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
        }
    </style>
</head>
<body>
    <?php if (!$live_class || !$can_access): ?>
        <div class="flex items-center justify-center h-screen bg-black text-white">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-6xl text-red-600 mb-4"></i>
                <h1 class="text-2xl font-bold mb-2">Live Class Not Available</h1>
                <p class="text-gray-400 mb-4">
                    <?php if (!$live_class): ?>
                        The requested live class could not be found.
                    <?php elseif ($live_class['status'] !== 'ongoing' && !$is_teacher): ?>
                        This live class is not currently active.
                    <?php else: ?>
                        You do not have access to this live class.
                    <?php endif; ?>
                </p>
                <a href="../dashboard/live_classes.php" 
                   class="inline-block px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Live Classes
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="player-container">
            <!-- Main Player Area -->
            <div class="player-main">
                <!-- Back Button -->
                <a href="../dashboard/live_classes.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back</span>
                </a>

                <!-- Player Wrapper -->
                <div class="player-wrapper">
                    <!-- YouTube Player -->
                    <div id="player"></div>

                    <!-- Controls -->
                    <div class="controls-container">
                        <div class="controls">
                            <?php if ($is_teacher): ?>
                                <button id="end-live-btn" class="end-live-btn" onclick="showEndLiveModal()">
                                    <i class="fas fa-stop mr-2"></i>
                                    End Live Class
                                </button>
                            <?php endif; ?>
                            <div class="spacer"></div>
                        </div>
                    </div>
                </div>

                <!-- Video Info -->
                <div class="video-info">
                    <h1 class="video-title"><?php echo htmlspecialchars($live_class['title']); ?></h1>
                    <div class="video-meta">
                        <span>
                            <i class="fas fa-user mr-1"></i>
                            <?php echo htmlspecialchars(trim(($live_class['first_name'] ?? '') . ' ' . ($live_class['second_name'] ?? ''))); ?>
                        </span>
                        <span>
                            <i class="fas fa-book mr-1"></i>
                            <?php echo htmlspecialchars($live_class['subject_name']); ?>
                        </span>
                        <span>
                            <i class="fas fa-graduation-cap mr-1"></i>
                            <?php echo htmlspecialchars($live_class['stream_name']); ?>
                        </span>
                        <span>
                            <i class="fas fa-circle text-red-600 mr-1"></i>
                            <span class="text-red-600 font-semibold">LIVE</span>
                        </span>
                    </div>
                    <?php if ($live_class['description']): ?>
                        <p class="text-gray-300 mt-3 text-sm"><?php echo htmlspecialchars($live_class['description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar - Participants -->
            <div class="participants-sidebar">
                <div class="participants-sidebar-header">
                    <h3 class="text-white font-semibold text-lg">
                        <i class="fas fa-users mr-2 text-red-600"></i>
                        Participants
                    </h3>
                    <p class="text-gray-400 text-sm mt-1" id="participant-count">0 participants</p>
                </div>
                <div class="participants-list" id="participants-list">
                    <div class="text-gray-500 text-sm text-center py-4">Loading participants...</div>
                </div>
            </div>

            <!-- Chat Floating Button -->
            <?php if ($role === 'student' || $role === 'teacher'): ?>
            <button class="mobile-chat-btn" id="mobile-chat-btn" onclick="toggleMobileChatModal()" title="Chat">
                <i class="fas fa-comments"></i>
                <span class="chat-notification-badge hidden" id="chat-notification-badge">0</span>
            </button>

            <!-- Mobile Chat Modal -->
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
                                <i class="fas fa-comments"></i>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        </div>
                    </div>
                    <div class="mobile-chat-modal-input-container">
                        <form class="mobile-chat-modal-input-form" id="mobile-chat-modal-form" onsubmit="sendMessage(event)">
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

            <!-- Chat Notifications Container -->
            <?php if ($role === 'student' || $role === 'teacher'): ?>
            <div id="chat-notifications-container"></div>
            <?php endif; ?>
        </div>

        <!-- End Live Class Modal -->
        <?php if ($is_teacher): ?>
        <div class="modal-overlay" id="end-live-modal">
            <div class="modal-content">
                <h3 class="modal-title">End Live Class</h3>
                <div class="modal-body">
                    <p>Are you sure you want to end this live class?</p>
                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="save-as-recording" class="mr-2" checked>
                            <span>Save as recording</span>
                        </label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeEndLiveModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="endLiveClass()">End Class</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script>
            var tag = document.createElement('script');
            tag.src = "https://www.youtube.com/iframe_api";
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

            var player;
            var liveClassId = <?php echo $live_class_id; ?>;
            var isTeacher = <?php echo $is_teacher ? 'true' : 'false'; ?>;
            var role = '<?php echo $role; ?>';
            var participantsInterval = null;
            var chatPollInterval = null;
            var lastMessageId = 0;
            var isChatOpen = false;
            var chatInitialized = false;
            var unreadCount = 0;

            function onYouTubeIframeAPIReady() {
                var videoId = '<?php echo htmlspecialchars($live_class['youtube_video_id'] ?? ''); ?>';
                if (!videoId) {
                    document.getElementById('player').innerHTML = '<div class="text-white text-center p-8">No video stream available</div>';
                    return;
                }

                player = new YT.Player('player', {
                    height: '100%',
                    width: '100%',
                    videoId: videoId,
                    playerVars: {
                        'autoplay': 1,
                        'controls': 1,
                        'rel': 0,
                        'showinfo': 0,
                        'modestbranding': 1,
                        'playsinline': 1
                    }
                });
            }

            // Participants tracking
            function loadParticipants() {
                fetch(`get_participants.php?live_class_id=${liveClassId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.participants) {
                            renderParticipants(data.participants);
                            document.getElementById('participant-count').textContent = 
                                `${data.participants.length} participant${data.participants.length !== 1 ? 's' : ''}`;
                        }
                    })
                    .catch(error => console.error('Error loading participants:', error));
            }

            function renderParticipants(participants) {
                const container = document.getElementById('participants-list');
                if (participants.length === 0) {
                    container.innerHTML = '<div class="text-gray-500 text-sm text-center py-4">No participants yet</div>';
                    return;
                }

                container.innerHTML = participants.map(p => `
                    <div class="participant-item">
                        <img src="${p.profile_picture ? '../' + p.profile_picture : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(p.name) + '&background=dc2626&color=fff&size=128'}" 
                             alt="${p.name}" class="participant-avatar"
                             onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.name)}&background=dc2626&color=fff&size=128'">
                        <div class="participant-info">
                            <div class="participant-name">${escapeHtml(p.name)}</div>
                            <div class="participant-role">${p.role === 'teacher' ? 'Teacher' : 'Student'}</div>
                        </div>
                    </div>
                `).join('');
            }

            // Chat functionality (similar to player.php)
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
                const mobileChatModal = document.getElementById('mobile-chat-modal');
                if (mobileChatModal) {
                    const wasActive = mobileChatModal.classList.contains('active');
                    mobileChatModal.classList.toggle('active');
                    isChatOpen = mobileChatModal.classList.contains('active');
                    
                    if (isChatOpen) {
                        // Clear unread count when opening chat
                        unreadCount = 0;
                        updateChatBadge();
                        
                        // Load messages if not already loaded
                        if (!wasActive) {
                            loadChatMessages();
                        }
                        
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

            // Close modal when clicking on overlay
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
                    ? `../dashboard/get_live_messages.php?live_class_id=${liveClassId}&last_message_id=${lastMessageId}`
                    : `../dashboard/get_live_messages.php?live_class_id=${liveClassId}`;
                
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
                
                if (mobileChatModalMessages) {
                    if (messages.length === 0) {
                        mobileChatModalMessages.innerHTML = `
                            <div class="chat-empty">
                                <div>
                                    <i class="fas fa-comments"></i>
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

            function sendMessage(event) {
                event.preventDefault();
                sendChatMessageFromInput(true);
            }

            function sendChatMessageFromInput(isMobile) {
                const chatInput = document.getElementById('mobile-chat-modal-input');
                const chatSendBtn = document.getElementById('mobile-chat-modal-send-btn');
                    
                if (!chatInput) return;
                
                const message = chatInput.value.trim();
                if (!message) return;
                
                // Disable input and button
                chatInput.disabled = true;
                if (chatSendBtn) chatSendBtn.disabled = true;
                
                const formData = new FormData();
                formData.append('live_class_id', liveClassId);
                formData.append('message', message);
                
                fetch('../dashboard/send_live_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        chatInput.value = '';
                        chatInput.style.height = 'auto';
                        
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

            // End Live Class
            function showEndLiveModal() {
                document.getElementById('end-live-modal').classList.add('active');
            }

            function closeEndLiveModal() {
                document.getElementById('end-live-modal').classList.remove('active');
            }

            function endLiveClass() {
                const saveAsRecording = document.getElementById('save-as-recording').checked;
                
                fetch('end_live_class.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `live_class_id=${liveClassId}&save_as_recording=${saveAsRecording ? '1' : '0'}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (saveAsRecording && data.recording_url) {
                            window.location.href = data.recording_url;
                        } else {
                            window.location.href = '../dashboard/live_classes.php';
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Failed to end live class'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error ending live class');
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

            // Initialize
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    loadParticipants();
                    participantsInterval = setInterval(loadParticipants, 5000); // Update every 5 seconds
                    initializeChat();
                    setupMobileChatModalClose();
                });
            } else {
                loadParticipants();
                participantsInterval = setInterval(loadParticipants, 5000);
                initializeChat();
                setupMobileChatModalClose();
            }

            // Cleanup - mark student as left when leaving
            window.addEventListener('beforeunload', function() {
                if (participantsInterval) clearInterval(participantsInterval);
                if (chatPollInterval) clearInterval(chatPollInterval);
                
                // Mark student as left (only for students, not teachers)
                if (!isTeacher && role === 'student') {
                    // Use sendBeacon for reliable delivery on page unload
                    const data = new URLSearchParams();
                    data.append('live_class_id', liveClassId);
                    navigator.sendBeacon('leave_live_class.php', data);
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>

