<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');

// Get dashboard background image from system settings
$dashboard_background = null;
$bg_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'dashboard_background' LIMIT 1");
if ($bg_stmt) {
    $bg_stmt->execute();
    $bg_result = $bg_stmt->get_result();
    if ($bg_result->num_rows > 0) {
        $bg_row = $bg_result->fetch_assoc();
        $dashboard_background = $bg_row['setting_value'];
    }
    $bg_stmt->close();
}

$success_message = '';
$error_message = '';

// Handle exam creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam']) && $role === 'teacher') {
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $deadline = $_POST['deadline'] ?? '';
    $duration_minutes = intval($_POST['duration_minutes'] ?? 60);
    
    if ($subject_id <= 0) {
        $error_message = 'Please select a subject.';
    } elseif (empty($title)) {
        $error_message = 'Please enter an exam title.';
    } elseif (empty($deadline)) {
        $error_message = 'Please select a deadline.';
    } elseif ($duration_minutes <= 0) {
        $error_message = 'Please enter a valid duration.';
    } else {
        $create_exam = $conn->prepare("INSERT INTO exams (teacher_id, subject_id, title, duration_minutes, deadline, is_published, status) VALUES (?, ?, ?, ?, ?, 0, 'active')");
        $create_exam->bind_param("sisis", $user_id, $subject_id, $title, $duration_minutes, $deadline);
        
        if ($create_exam->execute()) {
            header('Location: exam_center.php?success=' . urlencode('Exam created successfully!'));
            exit;
        } else {
            $error_message = 'Error creating exam: ' . $conn->error;
        }
        $create_exam->close();
    }
}

// Handle publish toggle via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_publish']) && $role === 'teacher') {
    $exam_id = intval($_POST['exam_id'] ?? 0);
    $is_published = intval($_POST['is_published'] ?? 0);
    
    $update_stmt = $conn->prepare("UPDATE exams SET is_published = ? WHERE id = ? AND teacher_id = ?");
    $update_stmt->bind_param("iis", $is_published, $exam_id, $user_id);
    
    if ($update_stmt->execute()) {
        
        // Send WhatsApp Notification if Published
        if ($is_published == 1) {
            // Load WhatsApp Config
            if (file_exists(__DIR__ . '/../whatsapp_config.php')) {
                require_once __DIR__ . '/../whatsapp_config.php';
            }
            
            if (function_exists('sendWhatsAppMessage') && defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
                // 1. Get Exam Details
                $exam_query = "SELECT e.title, e.duration_minutes, e.deadline, s.name as subject_name, u.first_name, u.second_name, e.subject_id
                               FROM exams e
                               INNER JOIN subjects s ON e.subject_id = s.id
                               INNER JOIN users u ON e.teacher_id = u.user_id
                               WHERE e.id = ?";
                $estmt = $conn->prepare($exam_query);
                $estmt->bind_param("i", $exam_id);
                $estmt->execute();
                $exam_details = $estmt->get_result()->fetch_assoc();
                $estmt->close();
                
                if ($exam_details) {
                    $subject_name = $exam_details['subject_name'];
                    $teacher_name = trim($exam_details['first_name'] . ' ' . $exam_details['second_name']);
                    $exam_title = $exam_details['title'];
                    $duration = $exam_details['duration_minutes'] . " Minutes";
                    $deadline = date('Y-m-d h:i A', strtotime($exam_details['deadline']));
                    
                    // 2. Get Enrolled Students
                    // Find students enrolled in any stream that has this subject
                    $std_query = "SELECT DISTINCT u.whatsapp_number, u.first_name 
                                  FROM users u
                                  INNER JOIN student_enrollment se ON u.user_id = se.student_id
                                  INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                                  WHERE ss.subject_id = ? AND se.status = 'active' AND u.status = 1";
                    
                    $sstmt = $conn->prepare($std_query);
                    $sstmt->bind_param("i", $exam_details['subject_id']);
                    $sstmt->execute();
                    $students_result = $sstmt->get_result();
                    
                    while ($std = $students_result->fetch_assoc()) {
                        if (!empty($std['whatsapp_number'])) {
                            // 3. Construct Sinhala Message
                            $msg = "📢 *New Exam Notification / නව විභාග දැනුම්දීම*\n\n" .
                                   "📌 *Subject / විෂය:* $subject_name\n" .
                                   "👨‍🏫 *Teacher / ගුරුතුමා:* $teacher_name\n\n" .
                                   "📄 *Exam / විභාගය:* $exam_title\n" .
                                   "⏳ *Duration / කාලය:* $duration\n" .
                                   "📅 *Deadline / අවසන් දිනය:* $deadline (Before)\n\n" .
                                   "Please attend and complete the exam before the deadline.\n" .
                                   "කරුණාකර නියමිත දිනට පෙර විභාගයට පෙනී සිටින්න.";
                            
                            sendWhatsAppMessage($std['whatsapp_number'], $msg);
                        }
                    }
                    $sstmt->close();
                }
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $update_stmt->close();
    exit;
}

// Handle delete exam via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_exam']) && $role === 'teacher') {
    $exam_id = intval($_POST['exam_id'] ?? 0);
    
    $delete_stmt = $conn->prepare("DELETE FROM exams WHERE id = ? AND teacher_id = ?");
    $delete_stmt->bind_param("is", $exam_id, $user_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $delete_stmt->close();
    exit;
}

// Handle fetch exam results via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_exam_results']) && $role === 'teacher') {
    $exam_id = intval($_POST['exam_id'] ?? 0);
    
    // Check if teacher owns this exam
    $check_stmt = $conn->prepare("SELECT id, title FROM exams WHERE id = ? AND teacher_id = ?");
    $check_stmt->bind_param("is", $exam_id, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $check_stmt->close();

    // Get attempts with student info
    $results_query = "SELECT ea.*, u.first_name, u.second_name, u.user_id as student_id
                      FROM exam_attempts ea
                      INNER JOIN users u ON ea.student_id = u.user_id
                      WHERE ea.exam_id = ? AND ea.status = 'completed'
                      ORDER BY ea.score DESC";
    $stmt = $conn->prepare($results_query);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attempts = [];
    $total_score = 0;
    $max_score = -1;
    $min_score = 101;
    
    while ($row = $result->fetch_assoc()) {
        $row['student_name'] = trim($row['first_name'] . ' ' . $row['second_name']);
        
        // Calculate duration
        $start = strtotime($row['start_time']);
        $end = strtotime($row['end_time']);
        $duration_seconds = max(0, $end - $start);
        
        $h = floor($duration_seconds / 3600);
        $m = floor(($duration_seconds % 3600) / 60);
        $s = $duration_seconds % 60;
        
        $duration_text = "";
        if ($h > 0) $duration_text .= $h . "h ";
        if ($m > 0 || $h > 0) $duration_text .= $m . "m ";
        $duration_text .= $s . "s";
        
        $row['duration_text'] = $duration_text;
        
        $score = floatval($row['score']);
        $total_score += $score;
        if ($score > $max_score) $max_score = $score;
        if ($score < $min_score) $min_score = $score;
        
        $attempts[] = $row;
    }
    $stmt->close();
    
    $count = count($attempts);
    echo json_encode([
        'success' => true,
        'attempts' => $attempts,
        'overview' => [
            'count' => $count,
            'highest' => $count > 0 ? number_format($max_score, 1) : 0,
            'lowest' => $count > 0 ? number_format($min_score, 1) : 0,
            'average' => $count > 0 ? number_format($total_score / $count, 1) : 0
        ]
    ]);
    exit;
}

// Get success message from URL
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Get teacher's subjects for dropdown
$teacher_subjects = [];
if ($role === 'teacher') {
    $subjects_query = "SELECT DISTINCT sub.id, sub.name, sub.code 
                       FROM subjects sub
                       INNER JOIN stream_subjects ss ON sub.id = ss.subject_id
                       INNER JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id
                       WHERE ta.teacher_id = ? AND ta.status = 'active' AND sub.status = 1
                       ORDER BY sub.name";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $teacher_subjects[] = $row;
    }
    $stmt->close();
}

// Get ongoing exams
$exams = [];
if ($role === 'teacher') {
    $exams_query = "SELECT e.*, sub.name as subject_name, sub.code as subject_code,
                           u.first_name, u.second_name
                    FROM exams e
                    INNER JOIN subjects sub ON e.subject_id = sub.id
                    INNER JOIN users u ON e.teacher_id = u.user_id
                    WHERE e.teacher_id = ? AND e.status = 'active'
                    ORDER BY e.created_at DESC";
    $stmt = $conn->prepare($exams_query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
} elseif ($role === 'student') {
    // Get published exams for student based on their enrollment
    $exams_query = "SELECT DISTINCT e.*, sub.name as subject_name, sub.code as subject_code,
                           u.first_name, u.second_name,
                           ea.id as attempt_id, ea.status as attempt_status, ea.score, ea.correct_count, ea.total_questions
                    FROM exams e
                    INNER JOIN subjects sub ON e.subject_id = sub.id
                    INNER JOIN users u ON e.teacher_id = u.user_id
                    INNER JOIN stream_subjects ss ON sub.id = ss.subject_id
                    INNER JOIN student_enrollment se ON ss.id = se.stream_subject_id 
                                                     AND se.student_id = ? 
                                                     AND se.status = 'active'
                    LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.student_id = ?
                    WHERE e.is_published = 1 AND e.status = 'active'
                    ORDER BY e.deadline DESC";
    $stmt = $conn->prepare($exams_query);
    $stmt->bind_param("ss", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Center - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            <?php if ($dashboard_background): ?>
            background-image: url('../<?php echo htmlspecialchars($dashboard_background); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            <?php endif; ?>
        }
        
        .content-overlay {
            <?php if ($dashboard_background): ?>
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            <?php endif; ?>
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }
        
        .toggle-checkbox:checked {
            right: 0;
            border-color: #10B981;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #10B981;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="content-overlay">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="glass-card rounded-2xl p-8 mb-8 text-center sm:text-left flex flex-col sm:flex-row items-center justify-between">
                <div>
                    <h1 class="text-4xl font-extrabold text-gray-900 mb-2">Exam Center</h1>
                    <p class="text-gray-600 text-lg">Create and manage your exams and assessments.</p>
                </div>
                <?php if ($role === 'teacher'): ?>
                <div class="mt-6 sm:mt-0">
                    <button onclick="openExamModal()" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center text-sm font-semibold shadow-lg transition-all">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Exam
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Ongoing Exams Section -->
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="bg-red-600 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-clipboard-list mr-3"></i> <?php echo $role === 'teacher' ? 'Ongoing Exams' : 'Available Exams'; ?>
                    </h2>
                    <span class="bg-white/20 text-white text-xs font-bold px-3 py-1 rounded-full"><?php echo count($exams); ?> Exams</span>
                </div>
                
                <?php if (empty($exams)): ?>
                    <div class="p-12 text-center">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-6">
                            <i class="fas fa-file-alt text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Exams <?php echo $role === 'teacher' ? 'Yet' : 'Available'; ?></h3>
                        <p class="text-gray-500 max-w-sm mx-auto">
                            <?php echo $role === 'teacher' ? 'Create your first exam by clicking the "Add Exam" button above.' : 'There are no exams available for you at the moment.'; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($exams as $exam): ?>
                            <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all overflow-hidden border border-gray-100">
                                <!-- Card Header -->
                                <div class="bg-gradient-to-r from-red-500 to-red-600 p-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-white text-xs font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                                        <?php if ($role === 'teacher'): ?>
                                            <!-- Publish Toggle for Teachers -->
                                            <div class="flex items-center">
                                                <span class="text-white text-xs mr-2"><?php echo $exam['is_published'] ? 'Published' : 'Draft'; ?></span>
                                                <div class="relative inline-block w-10 align-middle select-none">
                                                    <input type="checkbox" 
                                                           id="toggle-<?php echo $exam['id']; ?>" 
                                                           class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-200"
                                                           style="<?php echo $exam['is_published'] ? 'right: 0;' : 'right: 16px;'; ?>"
                                                           <?php echo $exam['is_published'] ? 'checked' : ''; ?>
                                                           onchange="togglePublish(<?php echo $exam['id']; ?>, this.checked)">
                                                    <label for="toggle-<?php echo $exam['id']; ?>" 
                                                           class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer transition-all duration-200 <?php echo $exam['is_published'] ? 'bg-green-400' : 'bg-gray-300'; ?>"></label>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Status Badge for Students -->
                                            <?php if ($exam['attempt_status'] === 'completed'): ?>
                                                <span class="bg-green-400 text-white text-xs font-bold px-2 py-1 rounded">Completed</span>
                                            <?php elseif ($exam['attempt_status'] === 'in_progress'): ?>
                                                <span class="bg-yellow-400 text-white text-xs font-bold px-2 py-1 rounded">In Progress</span>
                                            <?php else: ?>
                                                <span class="bg-white/20 text-white text-xs font-bold px-2 py-1 rounded">New</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <?php if ($role === 'teacher'): ?>
                                    <a href="questions.php?exam_id=<?php echo $exam['id']; ?>" class="block p-5 cursor-pointer hover:bg-gray-50 transition-colors">
                                <?php else: ?>
                                    <div class="p-5">
                                <?php endif; ?>
                                    <h3 class="text-lg font-bold text-gray-900 mb-3"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    
                                    <div class="space-y-2 text-sm">
                                        <div class="flex items-center text-gray-600">
                                            <i class="fas fa-clock w-5 text-red-500"></i>
                                            <span class="ml-2"><?php echo $exam['duration_minutes']; ?> minutes</span>
                                        </div>
                                        
                                        <div class="flex items-center text-gray-600">
                                            <i class="fas fa-calendar-alt w-5 text-red-500"></i>
                                            <span class="ml-2"><?php echo date('M d, Y - h:i A', strtotime($exam['deadline'])); ?></span>
                                        </div>
                                        
                                        <div class="flex items-center text-gray-600">
                                            <i class="fas fa-user w-5 text-red-500"></i>
                                            <span class="ml-2"><?php echo htmlspecialchars(trim($exam['first_name'] . ' ' . $exam['second_name'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($role === 'teacher'): ?>
                                        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between">
                                            <button onclick="event.preventDefault(); event.stopPropagation(); viewResults(<?php echo $exam['id']; ?>, '<?php echo htmlspecialchars($exam['title'], ENT_QUOTES); ?>')" 
                                                    class="text-xs font-bold text-red-600 hover:text-red-700 flex items-center bg-red-50 px-3 py-1.5 rounded-lg transition-colors">
                                                <i class="fas fa-chart-bar mr-2"></i>View Results
                                            </button>
                                            <div class="flex items-center gap-2">
                                                <button onclick="event.preventDefault(); event.stopPropagation(); deleteExam(<?php echo $exam['id']; ?>)" 
                                                        class="p-2 text-gray-400 hover:text-red-500 transition-colors" title="Delete Exam">
                                                    <i class="fas fa-trash-alt text-sm"></i>
                                                </button>
                                                <div class="flex items-center text-gray-400 group-hover:text-red-500 transition-colors">
                                                    <span class="text-[10px] mr-2 uppercase tracking-widest font-bold">Manage</span>
                                                    <i class="fas fa-arrow-right text-xs"></i>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Student Actions -->
                                        <div class="mt-4 pt-3 border-t border-gray-100">
                                            <?php if ($exam['attempt_status'] === 'completed'): ?>
                                                <div class="text-center">
                                                    <div class="text-2xl font-bold text-green-600 mb-1"><?php echo number_format($exam['score'], 1); ?>%</div>
                                                    <p class="text-xs text-gray-500"><?php echo $exam['correct_count']; ?>/<?php echo $exam['total_questions']; ?> correct answers</p>
                                                </div>
                                            <?php elseif ($exam['attempt_status'] === 'in_progress'): ?>
                                                <a href="exam_view.php?exam_id=<?php echo $exam['id']; ?>" 
                                                   class="w-full py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 flex items-center justify-center text-sm font-semibold">
                                                    <i class="fas fa-play mr-2"></i>Continue Exam
                                                </a>
                                            <?php else: ?>
                                                <a href="exam_view.php?exam_id=<?php echo $exam['id']; ?>" 
                                                   class="w-full py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center justify-center text-sm font-semibold">
                                                    <i class="fas fa-play mr-2"></i>Start Exam
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php if ($role === 'teacher'): ?>
                                    </a>
                                <?php else: ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Exam Modal -->
    <?php if ($role === 'teacher'): ?>
    <div id="examModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-xl bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Create New Exam</h3>
                <button onclick="closeExamModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form method="POST" action="" class="space-y-5">
                <input type="hidden" name="create_exam" value="1">
                
                <!-- Subject Selection -->
                <div>
                    <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                    <select id="subject_id" name="subject_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <option value="">Select Subject</option>
                        <?php foreach ($teacher_subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['name']); ?>
                                <?php echo $subject['code'] ? ' (' . htmlspecialchars($subject['code']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Exam Title *</label>
                    <input type="text" id="title" name="title" required
                           placeholder="e.g., Mid-term Exam, Chapter 5 Quiz"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                
                <!-- Deadline -->
                <div>
                    <label for="deadline" class="block text-sm font-medium text-gray-700 mb-2">Deadline *</label>
                    <input type="datetime-local" id="deadline" name="deadline" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                
                <!-- Duration -->
                <div>
                    <label for="duration_minutes" class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes) *</label>
                    <input type="number" id="duration_minutes" name="duration_minutes" required min="1" value="60"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeExamModal()" 
                            class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">
                        Create Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Results Modal -->
    <div id="resultsModal" class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-0 border-0 w-full max-w-4xl shadow-2xl rounded-2xl bg-white mb-10 overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-gray-800 to-gray-900 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-white" id="modalExamTitle">Exam Results</h3>
                    <p class="text-xs text-gray-400 uppercase tracking-widest">Teacher Report Overview</p>
                </div>
                <button onclick="closeResultsModal()" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="p-6">
                <!-- Overview Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl text-center">
                        <p class="text-[10px] text-blue-500 uppercase font-black tracking-widest mb-1">Participants</p>
                        <h4 class="text-2xl font-black text-blue-900" id="statParticipants">0</h4>
                    </div>
                    <div class="bg-green-50 border border-green-100 p-4 rounded-xl text-center">
                        <p class="text-[10px] text-green-500 uppercase font-black tracking-widest mb-1">Highest Mark</p>
                        <h4 class="text-2xl font-black text-green-900" id="statHighest">0%</h4>
                    </div>
                    <div class="bg-red-50 border border-red-100 p-4 rounded-xl text-center">
                        <p class="text-[10px] text-red-500 uppercase font-black tracking-widest mb-1">Lowest Mark</p>
                        <h4 class="text-2xl font-black text-red-900" id="statLowest">0%</h4>
                    </div>
                    <div class="bg-purple-50 border border-purple-100 p-4 rounded-xl text-center">
                        <p class="text-[10px] text-purple-500 uppercase font-black tracking-widest mb-1">Average</p>
                        <h4 class="text-2xl font-black text-purple-900" id="statAverage">0%</h4>
                    </div>
                </div>

                <!-- Detailed Table -->
                <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-sm">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b border-gray-100 text-[10px] uppercase font-bold text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Student</th>
                                <th class="px-6 py-4">Duration</th>
                                <th class="px-6 py-4 text-center">Correct</th>
                                <th class="px-6 py-4 text-right">Final Score</th>
                            </tr>
                        </thead>
                        <tbody id="resultsTableBody" class="divide-y divide-gray-50 text-sm">
                            <!-- Data injected here -->
                        </tbody>
                    </table>
                </div>
                
                <div id="noResultsMsg" class="hidden py-10 text-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-users-slash text-gray-300 text-xl"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No students have completed this exam yet.</p>
                </div>
            </div>
            
            <div class="bg-gray-50 px-6 py-4 flex justify-end border-t border-gray-100">
                <button onclick="closeResultsModal()" class="px-6 py-2 bg-white border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-100 font-bold text-xs uppercase tracking-widest transition-colors">
                    Close Report
                </button>
            </div>
        </div>
    </div>

    <script>
        function viewResults(examId, examTitle) {
            const modal = document.getElementById('resultsModal');
            document.getElementById('modalExamTitle').textContent = examTitle;
            modal.classList.remove('hidden');
            
            // Show loading state
            document.getElementById('resultsTableBody').innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 italic"><i class="fas fa-spinner fa-spin mr-2"></i>Loading results...</td></tr>';
            
            const formData = new FormData();
            formData.append('get_exam_results', '1');
            formData.append('exam_id', examId);
            
            fetch('exam_center.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderResults(data);
                } else {
                    alert('Error: ' + (data.error || 'Failed to fetch results'));
                    closeResultsModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while fetching results');
                closeResultsModal();
            });
        }

        function renderResults(data) {
            // Update stats
            document.getElementById('statParticipants').textContent = data.overview.count;
            document.getElementById('statHighest').textContent = data.overview.highest + '%';
            document.getElementById('statLowest').textContent = data.overview.lowest + '%';
            document.getElementById('statAverage').textContent = data.overview.average + '%';
            
            const tbody = document.getElementById('resultsTableBody');
            const noResults = document.getElementById('noResultsMsg');
            
            if (data.attempts.length === 0) {
                tbody.innerHTML = '';
                noResults.classList.remove('hidden');
                return;
            }
            
            noResults.classList.add('hidden');
            tbody.innerHTML = data.attempts.map(attempt => `
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-gray-800">${attempt.student_name}</div>
                        <div class="text-[10px] text-gray-400">ID: ${attempt.student_id}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center text-gray-600">
                            <i class="far fa-clock text-gray-300 mr-2 text-xs"></i>
                            ${attempt.duration_text}
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center font-medium text-gray-700">
                        ${attempt.correct_count} / ${attempt.total_questions}
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="inline-block px-3 py-1 rounded-full font-black text-xs ${getScoreClass(attempt.score)}">
                            ${parseFloat(attempt.score).toFixed(1)}%
                        </span>
                    </td>
                </tr>
            `).join('');
        }

        function getScoreClass(score) {
            score = parseFloat(score);
            if (score >= 75) return 'bg-green-100 text-green-700';
            if (score >= 50) return 'bg-blue-100 text-blue-700';
            if (score >= 35) return 'bg-yellow-100 text-yellow-700';
            return 'bg-red-100 text-red-700';
        }

        function closeResultsModal() {
            document.getElementById('resultsModal').classList.add('hidden');
        }

        function openExamModal() {
            document.getElementById('examModal').classList.remove('hidden');
        }
        
        function closeExamModal() {
            document.getElementById('examModal').classList.add('hidden');
        }
        
        function togglePublish(examId, isPublished) {
            const formData = new FormData();
            formData.append('toggle_publish', '1');
            formData.append('exam_id', examId);
            formData.append('is_published', isPublished ? 1 : 0);
            
            fetch('exam_center.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the label text
                    const toggle = document.getElementById('toggle-' + examId);
                    const label = toggle.nextElementSibling;
                    const statusText = toggle.parentElement.previousElementSibling;
                    
                    if (isPublished) {
                        label.classList.remove('bg-gray-300');
                        label.classList.add('bg-green-400');
                        statusText.textContent = 'Published';
                    } else {
                        label.classList.remove('bg-green-400');
                        label.classList.add('bg-gray-300');
                        statusText.textContent = 'Draft';
                    }
                } else {
                    alert('Error updating publish status');
                    // Revert the toggle
                    document.getElementById('toggle-' + examId).checked = !isPublished;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating publish status');
                document.getElementById('toggle-' + examId).checked = !isPublished;
            });
        }

        function deleteExam(examId) {
            if (!confirm('Are you sure you want to delete this entire exam? All questions and student results will be permanently removed.')) return;
            
            const formData = new FormData();
            formData.append('delete_exam', '1');
            formData.append('exam_id', examId);
            
            fetch('exam_center.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete exam'));
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Close modal when clicking outside
        document.getElementById('examModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeExamModal();
            }
        });
    </script>
</body>
</html>
