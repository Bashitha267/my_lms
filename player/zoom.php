<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Get Zoom class ID from URL
$zoom_class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$current_zoom_class = null;
$can_access = false;
$is_teacher_owner = false;

if ($zoom_class_id > 0) {
    // Get Zoom class details
    $query = "SELECT zc.*, ta.stream_subject_id, ta.academic_year, ta.teacher_id,
                     s.name as stream_name, sub.name as subject_name,
                     u.first_name, u.second_name, u.profile_picture
              FROM zoom_classes zc
              INNER JOIN teacher_assignments ta ON zc.teacher_assignment_id = ta.id
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              WHERE zc.id = ? AND zc.status IN ('scheduled', 'ongoing', 'ended')";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $zoom_class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $current_zoom_class = $result->fetch_assoc();
        $stream_subject_id = $current_zoom_class['stream_subject_id'];
        $academic_year = $current_zoom_class['academic_year'];
        
        // Check if teacher owns this class
        $is_teacher_owner = ($role === 'teacher' && $current_zoom_class['teacher_id'] === $user_id);
        
        // Check access permissions
        if ($is_teacher_owner) {
            $can_access = true;
        } elseif ($role === 'student') {
            // Check enrollment
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
                
                // If free class, allow access
                if ($current_zoom_class['free_class'] == 1) {
                    $can_access = true;
                } else {
                    // Check payment for the month
                    $class_month = date('n', strtotime($current_zoom_class['scheduled_start_time']));
                    $class_year = date('Y', strtotime($current_zoom_class['scheduled_start_time']));
                    
                    $paid_query = "SELECT id FROM monthly_payments 
                                  WHERE student_enrollment_id = ? AND month = ? AND year = ? AND payment_status = 'paid'
                                  LIMIT 1";
                    $paid_stmt = $conn->prepare($paid_query);
                    $paid_stmt->bind_param("iii", $enrollment_id, $class_month, $class_year);
                    $paid_stmt->execute();
                    $paid_result = $paid_stmt->get_result();
                    $can_access = $paid_result->num_rows > 0;
                    $paid_stmt->close();
                }
            }
            $enroll_stmt->close();
        } elseif (empty($role) && $current_zoom_class['free_class'] == 1) {
            // Allow guest access to free classes
            $can_access = true;
        }
    }
    $stmt->close();
}

// Prepare Zoom embed URL
$zoom_embed_url = '';
if ($current_zoom_class && $can_access) {
    $zoom_link = $current_zoom_class['zoom_meeting_link'];
    
    // Get user's full name for auto-fill
    $user_name = '';
    if (!empty($user_id)) {
        // Fetch current user's name from database
        $user_query = "SELECT first_name, second_name FROM users WHERE user_id = ? LIMIT 1";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param("s", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_row = $user_result->fetch_assoc();
            $user_name = trim(($user_row['first_name'] ?? '') . ' ' . ($user_row['second_name'] ?? ''));
        }
        $user_stmt->close();
    }
    
    // Extract meeting ID and passcode from Zoom URL
    $meeting_id = '';
    $passcode = '';
    
    // Try to extract meeting ID and passcode from URL
    if (preg_match('/zoom\.us\/j\/(\d+)/', $zoom_link, $matches)) {
        $meeting_id = $matches[1];
        
        // Try to extract passcode from URL query string
        $parsed_url = parse_url($zoom_link);
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            if (isset($query_params['pwd'])) {
                $passcode = $query_params['pwd'];
            }
        }
        
        // If no passcode in URL, use database passcode
        if (empty($passcode) && !empty($current_zoom_class['zoom_passcode'])) {
            $passcode = $current_zoom_class['zoom_passcode'];
        }
        
        // Construct embed URL with all parameters
        $zoom_embed_url = "https://zoom.us/wc/join/" . $meeting_id;
        
        $params = [];
        if (!empty($passcode)) {
            $params['pwd'] = $passcode;
        }
        if (!empty($user_name)) {
            $params['uname'] = $user_name;
        }
        
        if (!empty($params)) {
            $zoom_embed_url .= '?' . http_build_query($params);
        }
    } else {
        // Use the link as-is for non-standard formats
        $zoom_embed_url = $zoom_link;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo $current_zoom_class ? htmlspecialchars($current_zoom_class['title']) : 'Zoom Class'; ?> - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            background: #000;
        }

        .zoom-container {
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            background: #000;
        }




        /* Main Zoom Area */
        .zoom-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            background: #000;
        }

        .zoom-wrapper {
            flex: 1;
            position: relative;
            background: #000;
        }

        #zoom-frame {
            width: 100%;
            height: 100%;
            border: 0;
        }

        .zoom-overlay {
            position: absolute;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            z-index: 100;
            pointer-events: none;
        }

        .zoom-overlay > * {
            pointer-events: auto;
        }

        .zoom-info {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            color: white;
        }

        .zoom-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .zoom-status {
            font-size: 0.75rem;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-ongoing {
            background: #10b981;
            color: white;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .zoom-controls {
            display: flex;
            gap: 0.5rem;
        }

        .control-btn {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
        }

        .control-btn:hover {
            background: rgba(220, 38, 38, 0.9);
        }

        /* Chat and Files Sidebar */
        .right-sidebar {
            width: 350px;
            background: #1a1a1a;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #333;
        }


        @media (max-width: 1024px), (max-height: 600px) and (orientation: landscape) {
            .right-sidebar {
                position: absolute;
                right: 0;
                height: 100%;
                z-index: 1000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            }
            
            .right-sidebar.show {
                transform: translateX(0);
            }
        }

        .tabs {
            display: flex;
            background: #0f0f0f;
            border-bottom: 1px solid #333;
        }

        .tab {
            flex: 1;
            padding: 1rem;
            background: transparent;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
        }

        .tab.active {
            color: white;
            border-bottom-color: #dc2626;
        }

        .tab-content {
            flex: 1;
            display: none;
            flex-direction: column;
            min-height: 0;
        }

        .tab-content.active {
            display: flex;
        }

        /* Chat Styles */
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
        }

        .chat-message.own-message {
            flex-direction: row-reverse;
        }

        .chat-message.own-message .chat-message-content {
            background: #dc2626;
        }

        .chat-message.other-message .chat-message-content {
            background: #252525;
        }

        .chat-message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .chat-message-content {
            padding: 0.75rem;
            border-radius: 0.5rem;
            color: white;
            font-size: 0.875rem;
            max-width: 70%;
        }

        .chat-message-sender {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.75rem;
            color: #dc2626;
        }

        .chat-message.own-message .chat-message-sender {
            color: white;
            opacity: 0.8;
        }

        .chat-input-container {
            padding: 1rem;
            background: #0f0f0f;
            border-top: 1px solid #333;
        }

        .chat-input-form {
            display: flex;
            gap: 0.5rem;
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
            min-height: 44px;
        }

        .chat-send-btn:hover {
            background: #b91c1c;
        }

        /* Files Styles */
        .files-section {
            padding: 1rem;
            overflow-y: auto;
        }

        .file-upload-area {
            background: #252525;
            border: 2px dashed #333;
            border-radius: 0.5rem;
            padding: 2rem 1rem;
            text-align: center;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .file-upload-area:hover {
            border-color: #dc2626;
        }

        .file-item {
            background: #252525;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            background: #dc2626;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .file-info {
            flex: 1;
            min-width: 0;
        }

        .file-name {
            color: white;
            font-size: 0.875rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-meta {
            color: #9ca3af;
            font-size: 0.75rem;
        }

        .file-download-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.75rem;
        }

        /* Floating Buttons (Mobile) */
        .floating-btn {
            position: fixed;
            bottom: 1rem;
            background: rgba(220, 38, 38, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            border: none;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            cursor: pointer;
            pointer-events: auto;
            touch-action: manipulation;
        }


        @media (max-width: 768px), (max-height: 500px) and (orientation: landscape) {
            .floating-btn {
                display: flex;
            }
        }



        .btn-chat {
            right: 1rem;
        }
        
        /* Ensure floating buttons are always on top in mobile */
        @media (max-width: 768px), (max-height: 500px) and (orientation: landscape) {
            .btn-chat {
                z-index: 10001 !important;
                position: fixed !important;
            }
        }

        .overlay-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }

        .overlay-backdrop.show {
            display: block;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #1a1a1a;
        }

        ::-webkit-scrollbar-thumb {
            background: #dc2626;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #b91c1c;
        }

        .back-btn {
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 1001;
            font-weight: 500;
        }

        .back-btn:hover {
            background: rgba(220, 38, 38, 0.9);
        }

        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            padding: 2rem;
            text-align: center;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php if (!$current_zoom_class || !$can_access): ?>
        <div class="flex items-center justify-center h-screen bg-black text-white">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-6xl text-red-600 mb-4"></i>
                <h1 class="text-2xl font-bold mb-2">Access Denied</h1>
                <p class="text-gray-400 mb-6">
                    <?php 
                    if (!$current_zoom_class) {
                        echo "Zoom class not found.";
                    } elseif (!$can_access) {
                        echo "You don't have access to this Zoom class. Please check your enrollment or payment status.";
                    }
                    ?>
                </p>
                <a href="../dashboard/live_classes.php" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg inline-block">
                    Back to Live Classes
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="zoom-container">
            <!-- Back Button -->
            <a href="../dashboard/live_classes.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>


            <!-- Main Zoom Area -->
            <div class="zoom-main">
                <div class="zoom-wrapper">
                    <iframe id="zoom-frame" src="<?php echo htmlspecialchars($zoom_embed_url); ?>" allow="microphone; camera; fullscreen"></iframe>
                    
                    <div class="zoom-overlay">
                        <div class="zoom-info">
                            <div class="zoom-title"><?php echo htmlspecialchars($current_zoom_class['title']); ?></div>
                            <div class="zoom-status">
                                <span class="status-badge status-ongoing">
                                    <span class="live-dot"></span>
                                    <?php echo ucfirst($current_zoom_class['status']); ?>
                                </span>
                                <span><?php echo htmlspecialchars($current_zoom_class['subject_name']); ?></span>
                            </div>
                        </div>
                        
                        <div class="zoom-controls">
                            <?php if ($is_teacher_owner && $current_zoom_class['status'] !== 'ended'): ?>
                                <button class="control-btn" onclick="endZoomClass()" title="End Class">
                                    <i class="fas fa-stop-circle"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat and Files Sidebar -->
            <div class="right-sidebar" id="right-sidebar">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('chat')">
                        <i class="fas fa-comments mr-2"></i>Chat
                    </button>
                    <button class="tab" onclick="switchTab('files')">
                        <i class="fas fa-file mr-2"></i>Files
                    </button>
                </div>

                <!-- Chat Tab -->
                <div class="tab-content active" id="chat-content">
                    <div class="chat-messages" id="chat-messages">
                        <div class="empty-state">
                            <i class="fas fa-comment-dots"></i>
                            <p>No messages yet</p>
                        </div>
                    </div>
                    <div class="chat-input-container">
                        <form class="chat-input-form" onsubmit="sendMessage(event)">
                            <textarea id="chat-input" class="chat-input" placeholder="Type a message..." rows="1"></textarea>
                            <button type="submit" class="chat-send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Files Tab -->
                <div class="tab-content" id="files-content">
                    <div class="files-section">
                        <div class="file-upload-area" onclick="document.getElementById('file-input').click()">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-500 mb-2"></i>
                            <p class="text-white text-sm mb-1">Click to upload file</p>
                            <p class="text-gray-500 text-xs">Max 50MB</p>
                            <input type="file" id="file-input" style="display: none;" onchange="uploadFile()">
                        </div>

                        <div class="mb-4">
                            <h3 class="text-white font-semibold mb-2 text-sm">Downloads (Teacher Files)</h3>
                            <div id="downloads-list">
                                <div class="empty-state">
                                    <i class="fas fa-download"></i>
                                    <p class="text-xs">No files uploaded by teacher</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-white font-semibold mb-2 text-sm">Uploads (Student Files)</h3>
                            <div id="uploads-list">
                                <div class="empty-state">
                                    <i class="fas fa-upload"></i>
                                    <p class="text-xs">No files uploaded by students</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button class="floating-btn btn-chat" onclick="toggleRightSidebar()">
            <i class="fas fa-comments"></i>
        </button>

        <!-- Overlay Backdrop -->
        <div class="overlay-backdrop" id="overlay-backdrop" onclick="closeAllSidebars()"></div>

        <script>
            const zoomClassId = <?php echo $zoom_class_id; ?>;
            const userId = '<?php echo $user_id; ?>';
            const role = '<?php echo $role; ?>';
            const isTeacher = <?php echo $is_teacher_owner ? 'true' : 'false'; ?>;
            
            let chatPollInterval;
            let lastMessageId = 0;

            // Initialize
            document.addEventListener('DOMContentLoaded', function() {
                // Join the Zoom class
                joinZoomClass();
                
                // Load initial chat messages
                loadChatMessages();
                chatPollInterval = setInterval(loadChatMessages, 2000);
                
                // Load files
                loadFiles();
                setInterval(loadFiles, 5000);
                
                // Auto-resize textarea
                const chatInput = document.getElementById('chat-input');
                chatInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
                });
            });



            function loadChatMessages() {
                fetch(`get_zoom_messages.php?zoom_class_id=${zoomClassId}&last_id=${lastMessageId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.messages.length > 0) {
                            data.messages.forEach(msg => {
                                appendChatMessage(msg);
                                lastMessageId = Math.max(lastMessageId, msg.id);
                            });
                        }
                    })
                    .catch(error => console.error('Error loading messages:', error));
            }

            function appendChatMessage(msg) {
                const container = document.getElementById('chat-messages');
                const empty = container.querySelector('.empty-state');
                if (empty) empty.remove();
                
                const isOwn = msg.sender_id === userId;
                const div = document.createElement('div');
                div.className = `chat-message ${isOwn ? 'own-message' : 'other-message'}`;
                div.innerHTML = `
                    <img src="${msg.sender_avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(msg.sender_name) + '&background=dc2626&color=fff'}" 
                         alt="${msg.sender_name}" 
                         class="chat-message-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=' + encodeURIComponent('${msg.sender_name}') + '&background=dc2626&color=fff'">
                    <div class="chat-message-content">
                        ${!isOwn ? `<div class="chat-message-sender">${escapeHtml(msg.sender_name)}</div>` : ''}
                        <div>${escapeHtml(msg.message)}</div>
                    </div>
                `;
                container.appendChild(div);
                container.scrollTop = container.scrollHeight;
            }

            function sendMessage(event) {
                event.preventDefault();
                const input = document.getElementById('chat-input');
                const message = input.value.trim();
                
                if (!message) return;
                
                const formData = new FormData();
                formData.append('zoom_class_id', zoomClassId);
                formData.append('message', message);
                
                fetch('send_zoom_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        input.value = '';
                        input.style.height = 'auto';
                        if (data.message) {
                            appendChatMessage(data.message);
                            lastMessageId = Math.max(lastMessageId, data.message.id);
                        }
                    }
                })
                .catch(error => console.error('Error sending message:', error));
            }

            function loadFiles() {
                fetch(`get_zoom_files.php?zoom_class_id=${zoomClassId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderFiles(data.downloads, 'downloads-list', 'No files uploaded by teacher');
                            renderFiles(data.uploads, 'uploads-list', 'No files uploaded by students');
                        }
                    })
                    .catch(error => console.error('Error loading files:', error));
            }

            function renderFiles(files, containerId, emptyMessage) {
                const container = document.getElementById(containerId);
                
                if (files.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-${containerId === 'downloads-list' ? 'download' : 'upload'}"></i>
                            <p class="text-xs">${emptyMessage}</p>
                        </div>
                    `;
                    return;
                }
                
                container.innerHTML = files.map(file => {
                    const isOwnFile = file.uploader_id === userId;
                    return `
                        <div class="file-item">
                            <div class="file-icon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="file-info">
                                <div class="uploader-name text-[11px] font-black uppercase tracking-wider mb-0.5 ${isOwnFile ? 'text-red-500' : 'text-gray-100'}">
                                    ${escapeHtml(file.uploader_name)} ${isOwnFile ? '(You)' : ''}
                                </div>
                                <div class="file-name text-white font-semibold text-[13px] leading-tight mb-1">${escapeHtml(file.file_name)}</div>
                                <div class="file-meta text-[10px] text-gray-500 font-medium">${file.file_size} • Just now</div>
                            </div>
                            <a href="../uploads/zoom/${file.file_path}" download class="file-download-btn">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    `;
                }).join('');
            }

            function uploadFile() {
                const input = document.getElementById('file-input');
                if (!input.files.length) return;
                
                const file = input.files[0];
                const maxSize = 50 * 1024 * 1024;
                
                if (file.size > maxSize) {
                    alert('File size exceeds 50MB limit');
                    return;
                }
                
                const formData = new FormData();
                formData.append('zoom_class_id', zoomClassId);
                formData.append('file', file);
                
                fetch('upload_zoom_file.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        input.value = '';
                        loadFiles();
                        alert('File uploaded successfully');
                    } else {
                        alert('Error uploading file: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error uploading file:', error);
                    alert('Error uploading file');
                });
            }

            function endZoomClass() {
                if (!confirm('Are you sure you want to end this Zoom class? Participants will no longer be tracked.')) {
                    return;
                }
                
                const formData = new FormData();
                formData.append('zoom_class_id', zoomClassId);
                
                fetch('end_zoom_class.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Zoom class ended successfully');
                        window.location.href = '../dashboard/live_classes.php';
                    } else {
                        alert('Error ending class: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error ending class:', error);
                    alert('Error ending class');
                });
            }

            function switchTab(tab) {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                event.target.classList.add('active');
                document.getElementById(tab + '-content').classList.add('active');
            }

            function toggleRightSidebar() {
                const sidebar = document.getElementById('right-sidebar');
                const backdrop = document.getElementById('overlay-backdrop');
                
                sidebar.classList.toggle('show');
                backdrop.classList.toggle('show');
            }

            function closeAllSidebars() {
                document.getElementById('right-sidebar').classList.remove('show');
                document.getElementById('overlay-backdrop').classList.remove('show');
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
        </script>
    <?php endif; ?>
</body>
</html>
