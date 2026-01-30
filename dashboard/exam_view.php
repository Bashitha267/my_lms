<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Only students and teachers can access
if ($role !== 'student' && $role !== 'teacher') {
    header('Location: exam_center.php');
    exit;
}

$exam_id = intval($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) {
    header('Location: exam_center.php');
    exit;
}

// Get exam details
$exam_stmt = $conn->prepare("SELECT e.*, sub.name as subject_name, u.first_name, u.second_name
                              FROM exams e
                              INNER JOIN subjects sub ON e.subject_id = sub.id
                              INNER JOIN users u ON e.teacher_id = u.user_id
                              WHERE e.id = ? AND (e.is_published = 1 OR e.teacher_id = ?) AND e.status = 'active'");
$exam_stmt->bind_param("is", $exam_id, $user_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();

if ($exam_result->num_rows === 0) {
    header('Location: exam_center.php');
    exit;
}

$exam = $exam_result->fetch_assoc();
$exam_stmt->close();

// Check if deadline passed (Only for students)
if ($role === 'student' && strtotime($exam['deadline']) < time()) {
    header('Location: exam_center.php?error=Exam deadline has passed');
    exit;
}

// Check if student already completed this exam
if ($role === 'student') {
    $attempt_stmt = $conn->prepare("SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ?");
    $attempt_stmt->bind_param("is", $exam_id, $user_id);
    $attempt_stmt->execute();
    $attempt_result = $attempt_stmt->get_result();
    $existing_attempt = $attempt_result->fetch_assoc();
    $attempt_stmt->close();
    
    // If completed, show results
    if ($existing_attempt && $existing_attempt['status'] === 'completed') {
        $show_results = true;
    } else {
        $show_results = false;
        
        // Create or get attempt
        if (!$existing_attempt) {
            // Create new attempt
            $start_time = date('Y-m-d H:i:s');
            $create_attempt = $conn->prepare("INSERT INTO exam_attempts (exam_id, student_id, start_time, status) VALUES (?, ?, ?, 'in_progress')");
            $create_attempt->bind_param("iss", $exam_id, $user_id, $start_time);
            $create_attempt->execute();
            $attempt_id = $conn->insert_id;
            $create_attempt->close();
            
            $attempt = [
                'id' => $attempt_id,
                'start_time' => $start_time,
                'status' => 'in_progress'
            ];
        } else {
            $attempt = $existing_attempt;
            $attempt_id = $attempt['id'];
        }
    }
} else {
    // Teacher preview mode
    $show_results = false;
    $attempt_id = null;
    $attempt = null;
}

// Handle answer submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Save answer
    if (isset($_POST['action']) && $_POST['action'] === 'save_answer') {
        $question_id = intval($_POST['question_id'] ?? 0);
        $answer_ids = json_decode($_POST['answer_ids'] ?? '[]', true);
        
        // Delete existing answers for this question
        $delete_stmt = $conn->prepare("DELETE FROM student_answers WHERE attempt_id = ? AND question_id = ?");
        $delete_stmt->bind_param("ii", $attempt_id, $question_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Insert new answers
        if (!empty($answer_ids)) {
            $insert_stmt = $conn->prepare("INSERT INTO student_answers (attempt_id, question_id, answer_id) VALUES (?, ?, ?)");
            foreach ($answer_ids as $answer_id) {
                $answer_id = intval($answer_id);
                $insert_stmt->bind_param("iii", $attempt_id, $question_id, $answer_id);
                $insert_stmt->execute();
            }
            $insert_stmt->close();
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Submit exam
    if (isset($_POST['action']) && $_POST['action'] === 'submit_exam') {
        // Get all questions for this exam
        $questions_stmt = $conn->prepare("SELECT eq.id, eq.question_type FROM exam_questions eq WHERE eq.exam_id = ?");
        $questions_stmt->bind_param("i", $exam_id);
        $questions_stmt->execute();
        $questions_result = $questions_stmt->get_result();
        
        $total_questions = 0;
        $correct_count = 0;
        
        while ($question = $questions_result->fetch_assoc()) {
            $total_questions++;
            $question_id = $question['id'];
            
            // Get correct answers for this question
            $correct_stmt = $conn->prepare("SELECT id FROM question_answers WHERE question_id = ? AND is_correct = 1");
            $correct_stmt->bind_param("i", $question_id);
            $correct_stmt->execute();
            $correct_result = $correct_stmt->get_result();
            $correct_answers = [];
            while ($row = $correct_result->fetch_assoc()) {
                $correct_answers[] = $row['id'];
            }
            $correct_stmt->close();
            
            // Get student's answers
            $student_stmt = $conn->prepare("SELECT answer_id FROM student_answers WHERE attempt_id = ? AND question_id = ?");
            $student_stmt->bind_param("ii", $attempt_id, $question_id);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            $student_answers = [];
            while ($row = $student_result->fetch_assoc()) {
                $student_answers[] = $row['answer_id'];
            }
            $student_stmt->close();
            
            // Compare answers
            sort($correct_answers);
            sort($student_answers);
            
            if ($correct_answers == $student_answers) {
                $correct_count++;
            }
        }
        $questions_stmt->close();
        
        // Calculate score
        $score = $total_questions > 0 ? ($correct_count / $total_questions) * 100 : 0;
        
        // Update attempt as completed
        $end_time = date('Y-m-d H:i:s');
        $update_stmt = $conn->prepare("UPDATE exam_attempts SET end_time = ?, score = ?, correct_count = ?, total_questions = ?, status = 'completed' WHERE id = ?");
        $update_stmt->bind_param("sdiii", $end_time, $score, $correct_count, $total_questions, $attempt_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode([
            'success' => true,
            'score' => $score,
            'correct_count' => $correct_count,
            'total_questions' => $total_questions
        ]);
        exit;
    }
    
    exit;
}

// Get questions with answers
$questions = [];
$questions_stmt = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_index ASC");
$questions_stmt->bind_param("i", $exam_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

while ($row = $questions_result->fetch_assoc()) {
    // Get answers
    $answers_stmt = $conn->prepare("SELECT id, answer_text FROM question_answers WHERE question_id = ? ORDER BY order_index ASC");
    $answers_stmt->bind_param("i", $row['id']);
    $answers_stmt->execute();
    $answers_result = $answers_stmt->get_result();
    $row['answers'] = $answers_result->fetch_all(MYSQLI_ASSOC);
    $answers_stmt->close();
    
    // Get images
    $images_stmt = $conn->prepare("SELECT * FROM question_images WHERE question_id = ? ORDER BY order_index ASC");
    $images_stmt->bind_param("i", $row['id']);
    $images_stmt->execute();
    $images_result = $images_stmt->get_result();
    $row['images'] = $images_result->fetch_all(MYSQLI_ASSOC);
    $images_stmt->close();
    
    // Get student's saved answers for this question
    if (!$show_results && isset($attempt_id)) {
        $saved_stmt = $conn->prepare("SELECT answer_id FROM student_answers WHERE attempt_id = ? AND question_id = ?");
        $saved_stmt->bind_param("ii", $attempt_id, $row['id']);
        $saved_stmt->execute();
        $saved_result = $saved_stmt->get_result();
        $row['saved_answers'] = [];
        while ($saved = $saved_result->fetch_assoc()) {
            $row['saved_answers'][] = $saved['answer_id'];
        }
        $saved_stmt->close();
    }
    
    $questions[] = $row;
}
$questions_stmt->close();

// Calculate end time
if (!$show_results && $role === 'student') {
    $start_timestamp = strtotime($attempt['start_time']);
    $end_timestamp = $start_timestamp + ($exam['duration_minutes'] * 60);
} else {
    $end_timestamp = time() + (3600 * 24); // Far in the future for preview
}

// Get dashboard background
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($exam['title']); ?> - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            <?php if ($dashboard_background): ?>
            background-image: url('../<?php echo htmlspecialchars($dashboard_background); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            <?php endif; ?>
        }
        .content-overlay {
            <?php if ($dashboard_background): ?>
            backdrop-filter: blur(8px);
            <?php endif; ?>
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }
        .exam-container {
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            padding: 1rem;
            width: 100%;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
        }
        .question-container {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
        }
        .question-slide {
            display: flex;
            flex-direction: column;
            min-height: min-content;
        }
        .answers-section {
            flex: 1;
            overflow-y: auto;
        }
        .option-card {
            transition: all 0.2s ease;
        }
        .option-card:hover {
            transform: translateX(4px);
        }
        .option-card.selected {
            background-color: #fef2f2;
            border-color: #dc2626;
        }
        /* Mobile scaling */
        @media (max-width: 640px) {
            body {
                overflow: auto !important;
            }
            .content-overlay {
                height: auto;
                min-height: 100dvh;
                overflow: visible;
            }
            .exam-container {
                height: auto;
                min-height: 100dvh;
                padding: 0.5rem;
                overflow: visible;
            }
            .question-container {
                flex: none;
                overflow: visible;
            }
            .glass-card {
                padding: 0.75rem !important;
            }
            .option-card {
                padding: 0.75rem !important;
            }
            .question-text {
                font-size: 0.95rem;
            }
            #time-display {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body class="bg-gray-100 sm:overflow-hidden">
    <div class="content-overlay">
        <div class="max-w-6xl mx-auto exam-container">
            <?php if ($show_results): ?>
                <!-- Results View -->
                <div class="glass-card rounded-2xl p-8 text-center">
                    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-check-circle text-green-500 text-5xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Exam Completed!</h1>
                    <p class="text-gray-500 mb-6"><?php echo htmlspecialchars($exam['title']); ?></p>
                    
                    <div class="bg-gray-50 rounded-xl p-6 mb-6">
                        <div class="text-5xl font-bold text-red-600 mb-2"><?php echo number_format($existing_attempt['score'], 1); ?>%</div>
                        <p class="text-gray-600"><?php echo $existing_attempt['correct_count']; ?> out of <?php echo $existing_attempt['total_questions']; ?> correct</p>
                    </div>
                    
                    <a href="exam_center.php" class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Exam Center
                    </a>
                </div>
            <?php elseif (empty($questions)): ?>
                <!-- No Questions -->
                <div class="glass-card rounded-2xl p-8 text-center">
                    <i class="fas fa-exclamation-circle text-gray-400 text-5xl mb-4"></i>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">No Questions Available</h2>
                    <p class="text-gray-500 mb-4">This exam doesn't have any questions yet.</p>
                    <a href="exam_center.php" class="text-red-600 hover:underline">Back to Exam Center</a>
                </div>
            <?php else: ?>
                <!-- Exam Header -->
                <div class="glass-card rounded-xl p-3 mb-2 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-3">
                        <?php if ($role === 'teacher'): ?>
                            <a href="exam_center.php" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors">
                                <i class="fas fa-arrow-left text-gray-600"></i>
                            </a>
                        <?php endif; ?>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-base sm:text-lg font-bold text-gray-900"><?php echo htmlspecialchars($exam['title']); ?></h1>
                                <?php if ($role === 'teacher'): ?>
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded uppercase">Admin Preview</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs sm:text-sm text-gray-500"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <?php if ($role === 'teacher'): ?>
                            <button onclick="deleteExam(<?php echo $exam['id']; ?>)" class="text-red-500 hover:text-red-700 p-2" title="Delete Exam">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php endif; ?>
                        <div id="timer" class="text-right">
                            <div class="text-xl sm:text-2xl font-bold text-red-600" id="time-display">--:--</div>
                            <p class="text-xs text-gray-500">Time Left</p>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="glass-card rounded-xl p-2 mb-2 flex-shrink-0">
                    <div class="flex items-center justify-between text-xs sm:text-sm text-gray-600 mb-1">
                        <span>Q <span id="current-num">1</span> of <?php echo count($questions); ?></span>
                        <span id="progress-text">0%</span>
                    </div>
                    <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div id="progress-bar" class="h-full bg-red-600 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
                
                <!-- Questions Container -->
                <div id="questions-container" class="question-container mb-2">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-slide glass-card rounded-xl p-4 h-full <?php echo $index > 0 ? 'hidden' : ''; ?>" 
                             data-question-id="<?php echo $question['id']; ?>"
                             data-question-type="<?php echo $question['question_type']; ?>"
                             data-index="<?php echo $index; ?>">
                            
                            <!-- Question Text -->
                            <div class="mb-3 flex-shrink-0">
                                <p class="question-text text-base sm:text-lg font-medium text-gray-900"><?php echo htmlspecialchars($question['question_text']); ?></p>
                            </div>
                            
                            <!-- Question Images -->
                            <?php if (!empty($question['images'])): ?>
                                <div class="flex flex-wrap gap-3 mb-4 flex-shrink-0">
                                    <?php foreach ($question['images'] as $image): ?>
                                        <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" 
                                             class="h-56 sm:h-64 rounded-xl border-2 border-gray-100 cursor-pointer hover:opacity-90 object-contain bg-gray-50/50 p-1 shadow-sm"
                                             onclick="openImage(this.src)" alt="Question image">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Answer Options -->
                            <div class="answers-section grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($question['answers'] as $answerIndex => $answer): ?>
                                    <?php 
                                    $is_saved = isset($question['saved_answers']) && in_array($answer['id'], $question['saved_answers']);
                                    $number = ($answerIndex + 1) . ")";
                                    ?>
                                    <label class="option-card block p-3 border-2 rounded-lg cursor-pointer <?php echo $is_saved ? 'selected border-red-500' : 'border-gray-200 hover:border-gray-300'; ?>"
                                           data-answer-id="<?php echo $answer['id']; ?>">
                                        <div class="flex items-center">
                                            <span class="w-8 h-6 sm:w-10 sm:h-7 rounded-full bg-gray-100 flex items-center justify-center text-xs sm:text-sm font-bold text-gray-700 mr-3 flex-shrink-0"><?php echo $number; ?></span>
                                            <input type="<?php echo $question['question_type'] === 'single' ? 'radio' : 'checkbox'; ?>"
                                                   name="question-<?php echo $question['id']; ?>"
                                                   value="<?php echo $answer['id']; ?>"
                                                   class="w-4 h-4 sm:w-5 sm:h-5 text-red-600 focus:ring-red-500 mr-3"
                                                   <?php echo $is_saved ? 'checked' : ''; ?>
                                                   onchange="selectAnswer(<?php echo $question['id']; ?>, this)">
                                            <span class="text-sm sm:text-base text-gray-800"><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Navigation Buttons -->
                <div class="glass-card rounded-xl p-3 flex items-center justify-between flex-shrink-0">
                    <button id="prev-btn" onclick="prevQuestion()" 
                            class="px-4 py-2 sm:px-6 sm:py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold flex items-center disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base" disabled>
                        <i class="fas fa-arrow-left mr-1 sm:mr-2"></i><span class="hidden sm:inline">Previous</span><span class="sm:hidden">Prev</span>
                    </button>
                    
                    <button id="next-btn" onclick="nextQuestion()" 
                            class="px-4 py-2 sm:px-6 sm:py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold flex items-center text-sm sm:text-base">
                        Next<i class="fas fa-arrow-right ml-1 sm:ml-2"></i>
                    </button>
                    
                    <button id="submit-btn" onclick="showSubmitConfirm()" 
                            class="hidden px-4 py-2 sm:px-6 sm:py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold flex items-center text-sm sm:text-base">
                        <i class="fas fa-check mr-1 sm:mr-2"></i>Submit
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Submit Confirmation Modal -->
    <div id="submit-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full text-center">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Submission</h3>
            <p class="text-gray-500 mb-6">Are you sure you want to submit your exam? You cannot change your answers after submission.</p>
            <div class="flex gap-3">
                <button onclick="closeSubmitModal()" class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">
                    Cancel
                </button>
                <button onclick="submitExam()" id="confirm-submit-btn" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                    Submit
                </button>
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div id="image-modal" class="hidden fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50 p-4" onclick="closeImage()">
        <img id="modal-image" src="" class="max-w-full max-h-full rounded-lg" alt="">
    </div>
    
    <!-- Results Modal -->
    <div id="results-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check-circle text-green-500 text-4xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Exam Submitted!</h3>
            <div class="text-4xl font-bold text-red-600 my-4" id="final-score">0%</div>
            <p class="text-gray-500 mb-6" id="final-stats">0/0 correct answers</p>
            <a href="exam_center.php" class="block w-full px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                Back to Exam Center
            </a>
        </div>
    </div>

    <?php if (!$show_results && !empty($questions)): ?>
    <script>
        const examId = <?php echo $exam_id; ?>;
        const totalQuestions = <?php echo count($questions); ?>;
        const endTime = <?php echo $end_timestamp; ?> * 1000;
        let currentIndex = 0;
        
        // Timer
        function updateTimer() {
            const now = Date.now();
            const remaining = endTime - now;
            
            if (remaining <= 0) {
                document.getElementById('time-display').textContent = '00:00';
                submitExam();
                return;
            }
            
            const hours = Math.floor(remaining / (1000 * 60 * 60));
            const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
            
            let display = '';
            if (hours > 0) {
                display = `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            } else {
                display = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
            
            document.getElementById('time-display').textContent = display;
            
            // Flash red when less than 5 minutes
            if (remaining < 5 * 60 * 1000) {
                document.getElementById('timer').classList.add('animate-pulse');
            }
        }
        
        setInterval(updateTimer, 1000);
        updateTimer();
        
        // Navigation
        function showQuestion(index) {
            const slides = document.querySelectorAll('.question-slide');
            slides.forEach((slide, i) => {
                slide.classList.toggle('hidden', i !== index);
            });
            
            currentIndex = index;
            document.getElementById('current-num').textContent = index + 1;
            
            // Update progress
            const progress = ((index + 1) / totalQuestions) * 100;
            document.getElementById('progress-bar').style.width = progress + '%';
            document.getElementById('progress-text').textContent = Math.round(progress) + '% Complete';
            
            // Update buttons
            document.getElementById('prev-btn').disabled = index === 0;
            
            if (index === totalQuestions - 1) {
                document.getElementById('next-btn').classList.add('hidden');
                document.getElementById('submit-btn').classList.remove('hidden');
            } else {
                document.getElementById('next-btn').classList.remove('hidden');
                document.getElementById('submit-btn').classList.add('hidden');
            }
        }
        
        function nextQuestion() {
            if (currentIndex < totalQuestions - 1) {
                showQuestion(currentIndex + 1);
            }
        }
        
        function prevQuestion() {
            if (currentIndex > 0) {
                showQuestion(currentIndex - 1);
            }
        }
        
        // Answer selection
        function selectAnswer(questionId, input) {
            const slide = document.querySelector(`[data-question-id="${questionId}"]`);
            const type = slide.dataset.questionType;
            
            // Update visual selection
            const labels = slide.querySelectorAll('.option-card');
            labels.forEach(label => {
                const checkbox = label.querySelector('input');
                if (type === 'single') {
                    label.classList.toggle('selected', checkbox.checked);
                    label.classList.toggle('border-red-500', checkbox.checked);
                    label.classList.toggle('border-gray-200', !checkbox.checked);
                } else {
                    if (checkbox.checked) {
                        label.classList.add('selected', 'border-red-500');
                        label.classList.remove('border-gray-200');
                    } else {
                        label.classList.remove('selected', 'border-red-500');
                        label.classList.add('border-gray-200');
                    }
                }
            });
            
            // Get selected answers
            const selectedInputs = slide.querySelectorAll('input:checked');
            const answerIds = Array.from(selectedInputs).map(inp => parseInt(inp.value));
            
            // Save to server
            const formData = new FormData();
            formData.append('action', 'save_answer');
            formData.append('question_id', questionId);
            formData.append('answer_ids', JSON.stringify(answerIds));
            
            fetch(`exam_view.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            });
        }
        
        // Submit
        function showSubmitConfirm() {
            document.getElementById('submit-modal').classList.remove('hidden');
        }
        
        function closeSubmitModal() {
            document.getElementById('submit-modal').classList.add('hidden');
        }
        
        function submitExam() {
            if ("<?php echo $role; ?>" === "teacher") {
                alert("This is a preview mode. Teachers cannot submit exams.");
                location.href = 'exam_center.php';
                return;
            }
            const btn = document.getElementById('confirm-submit-btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'submit_exam');
            
            fetch(`exam_view.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeSubmitModal();
                    document.getElementById('final-score').textContent = data.score.toFixed(1) + '%';
                    document.getElementById('final-stats').textContent = data.correct_count + '/' + data.total_questions + ' correct answers';
                    document.getElementById('results-modal').classList.remove('hidden');
                } else {
                    alert('Error submitting exam');
                    btn.innerHTML = 'Submit';
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error(error);
                alert('Error submitting exam');
                btn.innerHTML = 'Submit';
                btn.disabled = false;
            });
        }
        
        // Image modal
        function openImage(src) {
            document.getElementById('modal-image').src = src;
            document.getElementById('image-modal').classList.remove('hidden');
        }
        
        function closeImage() {
            document.getElementById('image-modal').classList.add('hidden');
        }

        function deleteExam(examId) {
            if (!confirm('Are you sure you want to delete this entire exam? This action cannot be undone.')) return;
            const formData = new FormData();
            formData.append('delete_exam', '1');
            formData.append('exam_id', examId);
            
            fetch('exam_center.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.href = 'exam_center.php';
                else alert('Delete failed: ' + data.error);
            });
        }
        
        // Initialize
        showQuestion(0);
    </script>
    <?php endif; ?>
</body>
</html>
