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
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $update_stmt->close();
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
    // Get published exams for student with attempt status
    $exams_query = "SELECT e.*, sub.name as subject_name, sub.code as subject_code,
                           u.first_name, u.second_name,
                           ea.id as attempt_id, ea.status as attempt_status, ea.score, ea.correct_count, ea.total_questions
                    FROM exams e
                    INNER JOIN subjects sub ON e.subject_id = sub.id
                    INNER JOIN users u ON e.teacher_id = u.user_id
                    LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.student_id = ?
                    WHERE e.is_published = 1 AND e.status = 'active' AND e.deadline >= NOW()
                    ORDER BY e.deadline ASC";
    $stmt = $conn->prepare($exams_query);
    $stmt->bind_param("s", $user_id);
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
                                            <span class="text-xs text-gray-400">Click to manage questions</span>
                                            <i class="fas fa-arrow-right text-red-500"></i>
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

    <script>
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
        
        // Close modal when clicking outside
        document.getElementById('examModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeExamModal();
            }
        });
    </script>
</body>
</html>
