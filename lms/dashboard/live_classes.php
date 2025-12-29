<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Get live classes
$live_classes = [];
$teacher_assignments = [];
$unique_subjects = [];
$unique_years = [];

if ($role === 'teacher') {
    // Get teacher's live classes
    $live_query = "SELECT lc.id, lc.title, lc.description, lc.status, lc.youtube_url, lc.actual_start_time, lc.end_time,
                          lc.created_at, lc.scheduled_start_time,
                          ta.stream_subject_id, ta.academic_year,
                          s.name as stream_name, sub.name as subject_name, sub.code as subject_code
                   FROM live_classes lc
                   INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
                   INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                   INNER JOIN streams s ON ss.stream_id = s.id
                   INNER JOIN subjects sub ON ss.subject_id = sub.id
                   WHERE ta.teacher_id = ?
                   ORDER BY lc.status DESC, lc.created_at DESC";
    
    $live_stmt = $conn->prepare($live_query);
    $live_stmt->bind_param("s", $user_id);
    $live_stmt->execute();
    $live_result = $live_stmt->get_result();
    
    while ($row = $live_result->fetch_assoc()) {
        $live_classes[] = $row;
    }
    $live_stmt->close();
    
    // Get teacher assignments (similar to recordings.php)
    $assign_query = "SELECT ta.id, ta.stream_subject_id, ta.academic_year, ta.batch_name, ta.status, 
                            ta.assigned_date, ta.start_date, ta.end_date, ta.notes,
                            s.name as stream_name, sub.name as subject_name, sub.code as subject_code
                     FROM teacher_assignments ta
                     INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                     INNER JOIN streams s ON ss.stream_id = s.id
                     INNER JOIN subjects sub ON ss.subject_id = sub.id
                     WHERE ta.teacher_id = ? AND ta.status = 'active'
                     ORDER BY ta.academic_year DESC, s.name, sub.name";
    
    $assign_stmt = $conn->prepare($assign_query);
    $assign_stmt->bind_param("s", $user_id);
    $assign_stmt->execute();
    $assign_result = $assign_stmt->get_result();
    
    while ($row = $assign_result->fetch_assoc()) {
        $teacher_assignments[] = $row;
        if (!in_array($row['subject_name'], $unique_subjects)) {
            $unique_subjects[] = $row['subject_name'];
        }
        if (!in_array($row['academic_year'], $unique_years)) {
            $unique_years[] = $row['academic_year'];
        }
    }
    if (!empty($unique_subjects)) sort($unique_subjects);
    if (!empty($unique_years)) rsort($unique_years);
    $assign_stmt->close();
    
} elseif ($role === 'student') {
    // Get ongoing live classes for student's enrollments
    $live_query = "SELECT lc.id, lc.title, lc.description, lc.status, lc.youtube_url, lc.actual_start_time,
                          ta.stream_subject_id, ta.academic_year,
                          s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                          u.first_name, u.second_name, u.profile_picture
                   FROM live_classes lc
                   INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
                   INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                   INNER JOIN streams s ON ss.stream_id = s.id
                   INNER JOIN subjects sub ON ss.subject_id = sub.id
                   INNER JOIN users u ON ta.teacher_id = u.user_id
                   INNER JOIN student_enrollment se ON se.stream_subject_id = ta.stream_subject_id 
                       AND se.academic_year = ta.academic_year
                   WHERE se.student_id = ? AND se.status = 'active' AND lc.status = 'ongoing'
                   ORDER BY lc.actual_start_time DESC";
    
    $live_stmt = $conn->prepare($live_query);
    $live_stmt->bind_param("s", $user_id);
    $live_stmt->execute();
    $live_result = $live_stmt->get_result();
    
    while ($row = $live_result->fetch_assoc()) {
        $live_classes[] = $row;
    }
    $live_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Classes - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
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
            font-size: 1rem;
            color: white;
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

        .mobile-chat-modal-input:-webkit-autofill,
        .mobile-chat-modal-input:-webkit-autofill:hover,
        .mobile-chat-modal-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff;
            -webkit-box-shadow: 0 0 0px 1000px #252525 inset;
            box-shadow: 0 0 0px 1000px #252525 inset;
            border: 1px solid #333;
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

        @media (min-width: 769px) {
            .mobile-chat-modal-messages {
                max-height: calc(100vh - 140px);
            }
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
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Welcome Section -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Live Classes</h1>
                <p class="text-gray-600">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!
                </p>
            </div>

            <?php if ($role === 'teacher'): ?>
                <!-- Ongoing Live Classes -->
                <?php 
                $ongoing_classes = array_filter($live_classes, function($lc) { return $lc['status'] === 'ongoing'; });
                $scheduled_classes = array_filter($live_classes, function($lc) { return $lc['status'] === 'scheduled'; });
                ?>
                
                <?php if (!empty($ongoing_classes)): ?>
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-2xl font-bold text-gray-900">Ongoing Live Classes</h2>
                            <?php if (count($ongoing_classes) > 0): ?>
                                <button onclick="toggleChatPanel()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center space-x-2">
                                    <i class="fas fa-comments"></i>
                                    <span>Chat</span>
                                    <span id="chat-badge" class="hidden bg-red-800 text-white text-xs rounded-full px-2 py-1 ml-1">0</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($ongoing_classes as $live_class): ?>
                                <div class="bg-white rounded-lg shadow-md border-l-4 border-red-600 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($live_class['title']); ?></h3>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($live_class['subject_name']); ?> - <?php echo htmlspecialchars($live_class['stream_name']); ?>
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            <i class="fas fa-circle text-red-600 mr-1"></i>
                                            LIVE
                                        </span>
                                    </div>
                                    
                                    <?php if ($live_class['description']): ?>
                                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($live_class['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="flex gap-2">
                                        <a href="../player/live_player.php?id=<?php echo $live_class['id']; ?>" 
                                           class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-center text-sm font-medium">
                                            <i class="fas fa-play mr-2"></i>Join
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Scheduled Live Classes -->
                <?php if (!empty($scheduled_classes)): ?>
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Scheduled Live Classes</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($scheduled_classes as $live_class): ?>
                                <div class="bg-white rounded-lg shadow-md border-l-4 border-yellow-500 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($live_class['title']); ?></h3>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($live_class['subject_name']); ?> - <?php echo htmlspecialchars($live_class['stream_name']); ?>
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                            Scheduled
                                        </span>
                                    </div>
                                    
                                    <?php if ($live_class['description']): ?>
                                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($live_class['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($live_class['scheduled_start_time']): ?>
                                        <p class="text-gray-600 text-sm mb-4">
                                            <i class="far fa-clock mr-2"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($live_class['scheduled_start_time'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="flex gap-2">
                                        <button onclick="startLiveClass(<?php echo $live_class['id']; ?>)" 
                                                class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium">
                                            <i class="fas fa-play mr-2"></i>Start
                                        </button>
                                        <button onclick="deleteLiveClass(<?php echo $live_class['id']; ?>)" 
                                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- My Subjects / Create New Live Class -->
                <div class="mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">My Subjects</h2>
                        <?php if (!empty($teacher_assignments)): ?>
                            <button onclick="openCreateModal()" class="w-full sm:w-auto px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Create New Live Class
                            </button>
                        <?php endif; ?>
                    </div>
                    
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
                                <div class="bg-white rounded-lg shadow-md border-l-4 border-red-500 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($assignment['subject_name']); ?></h3>
                                           
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 mb-4">
                                        <div class="flex items-center text-gray-600 text-sm">
                                            <i class="fas fa-graduation-cap mr-2 text-red-600"></i>
                                            <span class="font-medium">Stream:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($assignment['stream_name']); ?></span>
                                        </div>
                                        
                                        <div class="flex items-center text-gray-600 text-sm">
                                            <i class="far fa-calendar mr-2 text-red-600"></i>
                                            <span class="font-medium">Year:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($assignment['academic_year']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <button onclick="openCreateModal(<?php echo $assignment['id']; ?>)" 
                                            class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                                        <i class="fas fa-video mr-2"></i>Create Live Class
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($role === 'student'): ?>
                <!-- Student Ongoing Live Classes -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-900">Ongoing Live Classes</h2>
                        <?php if (!empty($live_classes)): ?>
                            <button onclick="toggleChatPanel()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center space-x-2">
                                <i class="fas fa-comments"></i>
                                <span>Chat</span>
                                <span id="chat-badge" class="hidden bg-red-800 text-white text-xs rounded-full px-2 py-1 ml-1">0</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($live_classes)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No ongoing live classes at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($live_classes as $live_class): ?>
                                <div class="bg-white rounded-lg shadow-md border-l-4 border-red-600 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($live_class['title']); ?></h3>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($live_class['subject_name']); ?> - <?php echo htmlspecialchars($live_class['stream_name']); ?>
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            <i class="fas fa-circle text-red-600 mr-1"></i>
                                            LIVE
                                        </span>
                                    </div>
                                    
                                    <!-- Teacher Info -->
                                    <?php if (isset($live_class['first_name'])): ?>
                                        <div class="flex items-center mb-4">
                                            <?php if ($live_class['profile_picture']): ?>
                                                <img src="../<?php echo htmlspecialchars($live_class['profile_picture']); ?>" 
                                                     alt="Teacher" 
                                                     class="w-10 h-10 rounded-full object-cover mr-2"
                                                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode(trim(($live_class['first_name'] ?? '') . ' ' . ($live_class['second_name'] ?? ''))); ?>&background=dc2626&color=fff&size=128'">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-2">
                                                    <i class="fas fa-user text-red-600"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars(trim(($live_class['first_name'] ?? '') . ' ' . ($live_class['second_name'] ?? ''))); ?>
                                                </p>
                                                <p class="text-xs text-gray-500">Teacher</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($live_class['description']): ?>
                                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($live_class['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <a href="../player/live_player.php?id=<?php echo $live_class['id']; ?>" 
                                       class="block w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-center text-sm font-medium">
                                        <i class="fas fa-play mr-2"></i>Join Live Class
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Live Class Modal (Teachers Only) -->
    <?php if ($role === 'teacher'): ?>
        <div id="createModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Create New Live Class</h3>
                    <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="createLiveClassForm" class="space-y-4">
                    <input type="hidden" id="teacher_assignment_id" name="teacher_assignment_id" value="">
                    
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" id="title" name="title" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
                    </div>
                    
                    <div>
                        <label for="youtube_url" class="block text-sm font-medium text-gray-700 mb-1">YouTube URL *</label>
                        <input type="url" id="youtube_url" name="youtube_url" required
                               placeholder="https://www.youtube.com/watch?v=..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                        <p class="text-xs text-gray-500 mt-1">Enter the YouTube Live URL</p>
                    </div>
                    
                    <div>
                        <label for="scheduled_start_time" class="block text-sm font-medium text-gray-700 mb-1">Scheduled Start Time (Optional)</label>
                        <input type="datetime-local" id="scheduled_start_time" name="scheduled_start_time"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="closeCreateModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Create Live Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Chat Modal for Ongoing Live Classes -->
    <?php if (($role === 'teacher' && !empty($ongoing_classes)) || ($role === 'student' && !empty($live_classes))): ?>
        <?php 
        $first_ongoing = null;
        if ($role === 'teacher' && !empty($ongoing_classes)) {
            $first_ongoing = reset($ongoing_classes);
        } elseif ($role === 'student' && !empty($live_classes)) {
            $first_ongoing = reset($live_classes);
        }
        ?>
        <div class="mobile-chat-modal" id="mobile-chat-modal">
            <div class="mobile-chat-modal-header">
                <h3>
                    <i class="fas fa-comments"></i>
                    Chat
                </h3>
                <button class="mobile-chat-modal-close" onclick="toggleChatPanel()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mobile-chat-modal-content">
                <div class="mobile-chat-modal-messages" id="mobile-chat-modal-messages">
                    <div class="chat-empty">
                        <div>
                            <i class="fas fa-comments"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    </div>
                </div>
                <div class="mobile-chat-modal-input-container">
                    <form class="mobile-chat-modal-input-form" id="mobile-chat-modal-form" onsubmit="sendChatMessage(event)">
                        <input type="hidden" id="currentLiveClassId" value="<?php echo $first_ongoing['id']; ?>">
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

        <!-- Chat Notifications Container -->
        <div id="chat-notifications-container"></div>
    <?php endif; ?>

    <script>
        // Create Live Class Modal
        function openCreateModal(assignmentId = null) {
            if (assignmentId) {
                document.getElementById('teacher_assignment_id').value = assignmentId;
            }
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
            document.getElementById('createLiveClassForm').reset();
        }

        document.getElementById('createLiveClassForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('create_live_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Live class created successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create live class'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating live class');
            });
        });

        // Start Live Class
        function startLiveClass(liveClassId) {
            if (!confirm('Are you sure you want to start this live class? Students will be able to join.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('live_class_id', liveClassId);
            
            fetch('start_live_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('Live class started successfully!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to start live class'));
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text);
                    alert('Error: Invalid response from server. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error starting live class: ' + error.message);
            });
        }

        // Delete Live Class
        function deleteLiveClass(liveClassId) {
            if (!confirm('Are you sure you want to delete this live class? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('live_class_id', liveClassId);
            
            fetch('delete_live_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Live class deleted successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete live class'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting live class');
            });
        }

        // Close modal on outside click
        document.getElementById('createModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });

        // Chat functionality (similar to player.php)
        let chatPollInterval = null;
        let lastChatMessageId = 0;
        let currentLiveClassId = null;
        let chatInitialized = false;
        let isChatOpen = false;
        let unreadCount = 0;

        function initializeChat() {
            if (chatInitialized) return;
            chatInitialized = true;
            
            const liveClassId = document.getElementById('currentLiveClassId')?.value;
            if (liveClassId) {
                currentLiveClassId = parseInt(liveClassId);
                loadChatMessages();
                startChatPolling();
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

        function toggleChatPanel() {
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

        // Close modal when clicking on overlay
        function setupMobileChatModalClose() {
            const mobileChatModal = document.getElementById('mobile-chat-modal');
            const mobileChatModalContent = mobileChatModal ? mobileChatModal.querySelector('.mobile-chat-modal-content') : null;
            
            if (mobileChatModal) {
                mobileChatModal.addEventListener('click', function(e) {
                    // Close if clicking directly on the modal (overlay), not on content
                    if (e.target === mobileChatModal || (mobileChatModalContent && !mobileChatModalContent.contains(e.target))) {
                        toggleChatPanel();
                    }
                });
            }
        }

        function loadChatMessages(polling = false) {
            if (!currentLiveClassId) return;
            
            const url = polling 
                ? `get_live_messages.php?live_class_id=${currentLiveClassId}&last_message_id=${lastChatMessageId}`
                : `get_live_messages.php?live_class_id=${currentLiveClassId}`;

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
                            lastChatMessageId = Math.max(...data.messages.map(m => m.id));
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
                toggleChatPanel();
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
            const badge = document.getElementById('chat-badge');
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
            sendChatMessageFromInput(true);
        }

        function sendChatMessageFromInput(isMobile) {
            const chatInput = document.getElementById('mobile-chat-modal-input');
            const chatSendBtn = document.getElementById('mobile-chat-modal-send-btn');
                
            if (!chatInput || !currentLiveClassId) return;
            
            const message = chatInput.value.trim();
            if (!message) return;
            
            // Disable input and button
            chatInput.disabled = true;
            if (chatSendBtn) chatSendBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('live_class_id', currentLiveClassId);
            formData.append('message', message);
            
            fetch('send_live_message.php', {
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
                        lastChatMessageId = Math.max(lastChatMessageId, data.data.id);
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

        function startChatPolling() {
            if (chatPollInterval) clearInterval(chatPollInterval);
            chatPollInterval = setInterval(() => loadChatMessages(true), 2000); // Poll every 2 seconds
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

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (chatPollInterval) {
                clearInterval(chatPollInterval);
            }
        });
    </script>
</body>
</html>

