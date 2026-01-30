<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Get exam ID from URL
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id <= 0) {
    header('Location: exam_center.php');
    exit;
}

// Get exam details
$exam = null;
$exam_stmt = $conn->prepare("SELECT e.*, sub.name as subject_name, sub.code as subject_code,
                                    u.first_name, u.second_name
                             FROM exams e
                             INNER JOIN subjects sub ON e.subject_id = sub.id
                             INNER JOIN users u ON e.teacher_id = u.user_id
                             WHERE e.id = ? AND e.teacher_id = ?");
$exam_stmt->bind_param("is", $exam_id, $user_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();
if ($exam_result->num_rows > 0) {
    $exam = $exam_result->fetch_assoc();
} else {
    header('Location: exam_center.php');
    exit;
}
$exam_stmt->close();

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Create new question
    if (isset($_POST['action']) && $_POST['action'] === 'create_question') {
        $max_order = 0;
        $order_stmt = $conn->prepare("SELECT MAX(order_index) as max_order FROM exam_questions WHERE exam_id = ?");
        $order_stmt->bind_param("i", $exam_id);
        $order_stmt->execute();
        $order_result = $order_stmt->get_result();
        if ($row = $order_result->fetch_assoc()) {
            $max_order = intval($row['max_order']);
        }
        $order_stmt->close();
        
        $new_order = $max_order + 1;
        $default_text = "New Question " . $new_order;
        $default_type = "single";
        
        $create_stmt = $conn->prepare("INSERT INTO exam_questions (exam_id, question_text, question_type, order_index) VALUES (?, ?, ?, ?)");
        $create_stmt->bind_param("issi", $exam_id, $default_text, $default_type, $new_order);
        
        if ($create_stmt->execute()) {
            $question_id = $conn->insert_id;
            
            // Create 4 default answer options
            for ($i = 1; $i <= 4; $i++) {
                $answer_text = "Option " . $i;
                $is_correct = ($i === 1) ? 1 : 0; // First option is correct by default
                $answer_stmt = $conn->prepare("INSERT INTO question_answers (question_id, answer_text, is_correct, order_index) VALUES (?, ?, ?, ?)");
                $answer_stmt->bind_param("isii", $question_id, $answer_text, $is_correct, $i);
                $answer_stmt->execute();
                $answer_stmt->close();
            }
            
            echo json_encode(['success' => true, 'question_id' => $question_id]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $create_stmt->close();
        exit;
    }
    
    // Update question
    if (isset($_POST['action']) && $_POST['action'] === 'update_question') {
        $question_id = intval($_POST['question_id'] ?? 0);
        $question_text = trim($_POST['question_text'] ?? '');
        $question_type = $_POST['question_type'] ?? 'single';
        
        $update_stmt = $conn->prepare("UPDATE exam_questions SET question_text = ?, question_type = ? WHERE id = ? AND exam_id = ?");
        $update_stmt->bind_param("ssii", $question_text, $question_type, $question_id, $exam_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $update_stmt->close();
        exit;
    }
    
    // Delete question
    if (isset($_POST['action']) && $_POST['action'] === 'delete_question') {
        $question_id = intval($_POST['question_id'] ?? 0);
        
        $delete_stmt = $conn->prepare("DELETE FROM exam_questions WHERE id = ? AND exam_id = ?");
        $delete_stmt->bind_param("ii", $question_id, $exam_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $delete_stmt->close();
        exit;
    }
    
    // Update answer
    if (isset($_POST['action']) && $_POST['action'] === 'update_answer') {
        $answer_id = intval($_POST['answer_id'] ?? 0);
        $answer_text = trim($_POST['answer_text'] ?? '');
        
        $update_stmt = $conn->prepare("UPDATE question_answers SET answer_text = ? WHERE id = ?");
        $update_stmt->bind_param("si", $answer_text, $answer_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $update_stmt->close();
        exit;
    }
    
    // Set correct answer(s)
    if (isset($_POST['action']) && $_POST['action'] === 'set_correct') {
        $question_id = intval($_POST['question_id'] ?? 0);
        $correct_answers = json_decode($_POST['correct_answers'] ?? '[]', true);
        
        // First, set all answers to incorrect
        $reset_stmt = $conn->prepare("UPDATE question_answers SET is_correct = 0 WHERE question_id = ?");
        $reset_stmt->bind_param("i", $question_id);
        $reset_stmt->execute();
        $reset_stmt->close();
        
        // Then set the correct ones
        if (!empty($correct_answers)) {
            $placeholders = implode(',', array_fill(0, count($correct_answers), '?'));
            $types = str_repeat('i', count($correct_answers));
            $update_stmt = $conn->prepare("UPDATE question_answers SET is_correct = 1 WHERE id IN ($placeholders)");
            $update_stmt->bind_param($types, ...$correct_answers);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Add new answer option
    if (isset($_POST['action']) && $_POST['action'] === 'add_answer') {
        $question_id = intval($_POST['question_id'] ?? 0);
        
        // Check if already has 5 answers
        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM question_answers WHERE question_id = ?");
        $count_stmt->bind_param("i", $question_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count = $count_result->fetch_assoc()['cnt'];
        $count_stmt->close();
        
        if ($count >= 5) {
            echo json_encode(['success' => false, 'error' => 'Maximum 5 answers allowed']);
            exit;
        }
        
        $new_order = $count + 1;
        $answer_text = "Option " . $new_order;
        
        $add_stmt = $conn->prepare("INSERT INTO question_answers (question_id, answer_text, is_correct, order_index) VALUES (?, ?, 0, ?)");
        $add_stmt->bind_param("isi", $question_id, $answer_text, $new_order);
        
        if ($add_stmt->execute()) {
            echo json_encode(['success' => true, 'answer_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $add_stmt->close();
        exit;
    }
    
    // Delete answer
    if (isset($_POST['action']) && $_POST['action'] === 'delete_answer') {
        $answer_id = intval($_POST['answer_id'] ?? 0);
        $question_id = intval($_POST['question_id'] ?? 0);
        
        // Check if more than 2 answers remain
        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM question_answers WHERE question_id = ?");
        $count_stmt->bind_param("i", $question_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count = $count_result->fetch_assoc()['cnt'];
        $count_stmt->close();
        
        if ($count <= 2) {
            echo json_encode(['success' => false, 'error' => 'Minimum 2 answers required']);
            exit;
        }
        
        $delete_stmt = $conn->prepare("DELETE FROM question_answers WHERE id = ?");
        $delete_stmt->bind_param("i", $answer_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $delete_stmt->close();
        exit;
    }
    
    // Upload question image (now supports multiple)
    if (isset($_POST['action']) && $_POST['action'] === 'upload_question_image') {
        $question_id = intval($_POST['question_id'] ?? 0);
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_dir = '../uploads/exam_images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = uniqid() . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $image_path = 'uploads/exam_images/' . $new_filename;
                    
                    // Get max order
                    $order_stmt = $conn->prepare("SELECT MAX(order_index) as max_order FROM question_images WHERE question_id = ?");
                    $order_stmt->bind_param("i", $question_id);
                    $order_stmt->execute();
                    $order_result = $order_stmt->get_result();
                    $max_order = 0;
                    if ($row = $order_result->fetch_assoc()) {
                        $max_order = intval($row['max_order']);
                    }
                    $order_stmt->close();
                    
                    $new_order = $max_order + 1;
                    
                    // Insert into question_images table
                    $insert_stmt = $conn->prepare("INSERT INTO question_images (question_id, image_path, order_index) VALUES (?, ?, ?)");
                    $insert_stmt->bind_param("isi", $question_id, $image_path, $new_order);
                    
                    if ($insert_stmt->execute()) {
                        $image_id = $conn->insert_id;
                        echo json_encode(['success' => true, 'image_path' => $image_path, 'image_id' => $image_id]);
                    } else {
                        echo json_encode(['success' => false, 'error' => $conn->error]);
                    }
                    $insert_stmt->close();
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to upload image']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid file type']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'No image uploaded']);
        }
        exit;
    }
    
    // Remove question image
    if (isset($_POST['action']) && $_POST['action'] === 'remove_question_image') {
        $image_id = intval($_POST['image_id'] ?? 0);
        
        $delete_stmt = $conn->prepare("DELETE FROM question_images WHERE id = ?");
        $delete_stmt->bind_param("i", $image_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $delete_stmt->close();
        exit;
    }
    
    exit;
}

// Get all questions for this exam
$questions = [];
$questions_stmt = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_index ASC");
$questions_stmt->bind_param("i", $exam_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();
while ($row = $questions_result->fetch_assoc()) {
    // Get answers for each question
    $answers_stmt = $conn->prepare("SELECT * FROM question_answers WHERE question_id = ? ORDER BY order_index ASC");
    $answers_stmt->bind_param("i", $row['id']);
    $answers_stmt->execute();
    $answers_result = $answers_stmt->get_result();
    $row['answers'] = $answers_result->fetch_all(MYSQLI_ASSOC);
    $answers_stmt->close();
    
    // Get images for each question
    $images_stmt = $conn->prepare("SELECT * FROM question_images WHERE question_id = ? ORDER BY order_index ASC");
    $images_stmt->bind_param("i", $row['id']);
    $images_stmt->execute();
    $images_result = $images_stmt->get_result();
    $row['images'] = $images_result->fetch_all(MYSQLI_ASSOC);
    $images_stmt->close();
    
    $questions[] = $row;
}
$questions_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions - <?php echo htmlspecialchars($exam['title']); ?> - LMS</title>
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
        
        .correct-answer {
            background-color: #4ade80 !important;
            border-color: #22c55e !important;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="content-overlay">
        <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 ">
            <!-- Header Section -->
            <div class="glass-card rounded-2xl p-6 mb-8">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center">
                        <a href="exam_center.php" class="mr-4 p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors">
                            <i class="fas fa-arrow-left text-gray-600"></i>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($exam['title']); ?></h1>
                            <p class="text-gray-500 text-sm mt-1">
                                <span class="text-red-600 font-medium"><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                                • <?php echo $exam['duration_minutes']; ?> min 
                                • Due: <?php echo date('M d, Y', strtotime($exam['deadline'])); ?>
                            </p>
                        </div>
                    </div>
                    <button onclick="createQuestion()" class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center text-sm font-semibold shadow-lg transition-all">
                        <i class="fas fa-plus mr-2"></i>
                        Add Question
                    </button>
                </div>
            </div>

            <!-- Questions List -->
            <div id="questionsList" class="space-y-6 mb-24">
                <?php if (empty($questions)): ?>
                    <div class="glass-card rounded-2xl p-12 text-center">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-6">
                            <i class="fas fa-question-circle text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Questions Yet</h3>
                        <p class="text-gray-500 max-w-sm mx-auto mb-6">Start by adding your first question to this exam.</p>
                        <button onclick="createQuestion()" class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 inline-flex items-center text-sm font-semibold">
                            <i class="fas fa-plus mr-2"></i>
                            Add First Question
                        </button>
                    </div>
                <?php else: ?>
                <?php foreach ($questions as $index => $question): ?>
                        <div class="glass-card rounded-xl overflow-hidden question-card" data-question-id="<?php echo $question['id']; ?>">
                            <!-- Compact View (Default) -->
                            <div class="question-view p-4" id="view-<?php echo $question['id']; ?>">
                                <div class="flex items-start gap-3">
                                    <span class="flex-shrink-0 w-8 h-8 bg-red-600 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-800 font-medium mb-2"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                        
                                        <!-- Images -->
                                        <?php if (!empty($question['images'])): ?>
                                            <div class="flex flex-wrap gap-1 mb-2">
                                                <?php foreach ($question['images'] as $image): ?>
                                                    <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" class="h-12 w-12 object-cover rounded border" alt="">
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Answer Preview -->
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($question['answers'] as $answer): ?>
                                                <span class="text-xs px-2 py-0.5 rounded <?php echo $answer['is_correct'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>">
                                                    <?php echo htmlspecialchars(mb_strimwidth($answer['answer_text'], 0, 20, '...')); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="flex-shrink-0 flex gap-1">
                                        <button type="button" onclick="toggleEdit(<?php echo $question['id']; ?>)" class="p-2 text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Edit">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                        <button type="button" onclick="deleteQuestion(<?php echo $question['id']; ?>)" class="p-2 text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit View (Hidden by default) -->
                            <div class="question-edit hidden" id="edit-<?php echo $question['id']; ?>">
                                <div class="bg-gradient-to-r from-gray-800 to-gray-900 px-4 py-2 flex items-center justify-between">
                                    <span class="text-white font-semibold text-sm">Editing Q<?php echo $index + 1; ?></span>
                                    <div class="flex items-center gap-2">
                                        <select class="question-type-select bg-white/10 text-white text-xs rounded px-2 py-1 border border-white/20 focus:outline-none"
                                                onchange="updateQuestionType(<?php echo $question['id']; ?>, this.value)">
                                            <option value="single" <?php echo $question['question_type'] === 'single' ? 'selected' : ''; ?>>Single</option>
                                            <option value="multiple" <?php echo $question['question_type'] === 'multiple' ? 'selected' : ''; ?>>Multiple</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="p-4">
                                    <!-- Question Text -->
                                    <div class="mb-3">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Question</label>
                                        <textarea class="question-text w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"
                                                  rows="2"><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                    </div>
                                    
                                    <!-- Images -->
                                    <div class="mb-3">
                                        <div class="flex flex-wrap gap-2 items-center images-container" data-question-id="<?php echo $question['id']; ?>">
                                            <?php if (!empty($question['images'])): ?>
                                                <?php foreach ($question['images'] as $image): ?>
                                                    <div class="relative inline-block" data-image-id="<?php echo $image['id']; ?>">
                                                        <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" class="h-14 w-14 object-cover rounded border" alt="">
                                                        <button type="button" onclick="removeQuestionImage(<?php echo $image['id']; ?>)" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center hover:bg-red-600">
                                                            <i class="fas fa-times" style="font-size: 8px;"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <input type="file" id="image-<?php echo $question['id']; ?>" accept="image/*" class="hidden" onchange="uploadQuestionImage(<?php echo $question['id']; ?>, this)">
                                            <button type="button" onclick="document.getElementById('image-<?php echo $question['id']; ?>').click()" class="h-14 w-14 border-2 border-dashed border-gray-300 rounded flex items-center justify-center text-gray-400 hover:text-gray-500 hover:border-gray-400">
                                                <i class="fas fa-plus text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Answers -->
                                    <div class="mb-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="block text-xs font-medium text-gray-600">Answers</label>
                                            <?php if (count($question['answers']) < 5): ?>
                                                <button type="button" onclick="addAnswer(<?php echo $question['id']; ?>)" class="text-xs text-red-600 hover:text-red-700 font-medium">
                                                    <i class="fas fa-plus mr-1"></i>Add
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="answers-container space-y-2" data-question-id="<?php echo $question['id']; ?>" data-type="<?php echo $question['question_type']; ?>">
                                            <?php foreach ($question['answers'] as $answer): ?>
                                                <div class="answer-row flex items-center gap-2 p-2 rounded border-2 transition-all <?php echo $answer['is_correct'] ? 'correct-answer' : 'bg-gray-50 border-gray-200'; ?>" data-answer-id="<?php echo $answer['id']; ?>">
                                                    <input type="<?php echo $question['question_type'] === 'single' ? 'radio' : 'checkbox'; ?>" 
                                                           name="correct-<?php echo $question['id']; ?>"
                                                           class="correct-checkbox w-4 h-4 text-green-600 focus:ring-green-500 flex-shrink-0"
                                                           <?php echo $answer['is_correct'] ? 'checked' : ''; ?>
                                                           onchange="toggleCorrectAnswer(<?php echo $question['id']; ?>, <?php echo $answer['id']; ?>, this)">
                                                    <input type="text" 
                                                           class="answer-text flex-1 min-w-0 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-red-500 bg-white"
                                                           value="<?php echo htmlspecialchars($answer['answer_text']); ?>">
                                                    <?php if (count($question['answers']) > 2): ?>
                                                        <button type="button" onclick="deleteAnswer(<?php echo $question['id']; ?>, <?php echo $answer['id']; ?>)" class="text-gray-400 hover:text-red-500 transition-colors flex-shrink-0 p-1">
                                                            <i class="fas fa-times text-xs"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1"><i class="fas fa-check-circle text-green-500 mr-1"></i>Green = correct</p>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-200">
                                        <button type="button" onclick="saveAndClose(<?php echo $question['id']; ?>)" 
                                                id="save-btn-<?php echo $question['id']; ?>"
                                                class="flex-1 sm:flex-none px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 inline-flex items-center justify-center text-sm font-medium">
                                            <i class="fas fa-save mr-2"></i>Save
                                        </button>
                                        <button type="button" onclick="toggleEdit(<?php echo $question['id']; ?>)" 
                                                class="flex-1 sm:flex-none px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 inline-flex items-center justify-center text-sm font-medium">
                                            <i class="fas fa-times mr-2"></i>Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const examId = <?php echo $exam_id; ?>;
        
        // Auto-open edit mode if ?edit= parameter is present
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');
            if (editId) {
                toggleEdit(parseInt(editId));
                // Scroll to the question
                const card = document.querySelector(`[data-question-id="${editId}"]`);
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        function createQuestion() {
            const formData = new FormData();
            formData.append('action', 'create_question');
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload with edit mode open for new question
                    window.location.href = `questions.php?exam_id=${examId}&edit=${data.question_id}`;
                } else {
                    alert('Error creating question: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating question');
            });
        }
        
        function toggleEdit(questionId) {
            const viewEl = document.getElementById(`view-${questionId}`);
            const editEl = document.getElementById(`edit-${questionId}`);
            
            if (viewEl.classList.contains('hidden')) {
                // Switch to view mode
                viewEl.classList.remove('hidden');
                editEl.classList.add('hidden');
            } else {
                // Switch to edit mode
                viewEl.classList.add('hidden');
                editEl.classList.remove('hidden');
            }
        }
        
        async function saveAndClose(questionId) {
            const editEl = document.getElementById(`edit-${questionId}`);
            const btn = document.getElementById(`save-btn-${questionId}`);
            const originalBtnHtml = btn.innerHTML;
            
            // Gather data
            const questionText = editEl.querySelector('.question-text').value;
            const questionType = editEl.querySelector('.question-type-select').value;
            const answerRows = editEl.querySelectorAll('.answer-row');
            
            const answers = Array.from(answerRows).map(row => ({
                id: row.dataset.answerId,
                text: row.querySelector('.answer-text').value,
                is_correct: row.querySelector('.correct-checkbox').checked
            }));

            // UI Feedback
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            btn.disabled = true;

            try {
                // 1. Update Question Text and Type
                const qFormData = new FormData();
                qFormData.append('action', 'update_question');
                qFormData.append('question_id', questionId);
                qFormData.append('question_text', questionText);
                qFormData.append('question_type', questionType);

                const qResponse = await fetch(`questions.php?exam_id=${examId}`, { method: 'POST', body: qFormData });
                const qResult = await qResponse.json();
                if (!qResult.success) throw new Error(qResult.error || 'Failed to save question text');

                // 2. Update all Answers in parallel
                const answerPromises = answers.map(async (ans) => {
                    const aFormData = new FormData();
                    aFormData.append('action', 'update_answer');
                    aFormData.append('answer_id', ans.id);
                    aFormData.append('answer_text', ans.text);
                    const aResponse = await fetch(`questions.php?exam_id=${examId}`, { method: 'POST', body: aFormData });
                    const aResult = await aResponse.json();
                    if (!aResult.success) throw new Error(aResult.error || 'Failed to save answer');
                });

                // 3. Set Correct Answers
                const correctPromise = (async () => {
                    const correctAnswers = answers.filter(a => a.is_correct).map(a => parseInt(a.id));
                    const cFormData = new FormData();
                    cFormData.append('action', 'set_correct');
                    cFormData.append('question_id', questionId);
                    cFormData.append('correct_answers', JSON.stringify(correctAnswers));
                    const cResponse = await fetch(`questions.php?exam_id=${examId}`, { method: 'POST', body: cFormData });
                    const cResult = await cResponse.json();
                    if (!cResult.success) throw new Error(cResult.error || 'Failed to set correct answers');
                })();

                await Promise.all([...answerPromises, correctPromise]);

                // Success! Clear URL and reload
                const url = new URL(window.location);
                url.searchParams.delete('edit');
                window.location.href = url.toString();

            } catch (error) {
                console.error('Save Error:', error);
                alert('Save Failed: ' + error.message);
                btn.innerHTML = originalBtnHtml;
                btn.disabled = false;
            }
        }
        
        function saveQuestion(questionId) {
            const card = document.querySelector(`[data-question-id="${questionId}"]`);
            const btn = document.getElementById(`save-btn-${questionId}`);
            const indicator = document.getElementById(`saved-indicator-${questionId}`);
            
            // Get question data
            const questionText = card.querySelector('.question-text').value;
            const questionType = card.querySelector('.question-type-select').value;
            
            // Get all answers
            const answerRows = card.querySelectorAll('.answer-row');
            const answers = [];
            answerRows.forEach(row => {
                answers.push({
                    id: row.dataset.answerId,
                    text: row.querySelector('.answer-text').value,
                    is_correct: row.querySelector('.correct-checkbox').checked ? 1 : 0
                });
            });
            
            // Show loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            btn.disabled = true;
            
            // Save question
            const formData = new FormData();
            formData.append('action', 'update_question');
            formData.append('question_id', questionId);
            formData.append('question_text', questionText);
            formData.append('question_type', questionType);
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Save answers
                    const answerPromises = answers.map(answer => {
                        const answerData = new FormData();
                        answerData.append('action', 'update_answer');
                        answerData.append('answer_id', answer.id);
                        answerData.append('answer_text', answer.text);
                        return fetch(`questions.php?exam_id=${examId}`, {
                            method: 'POST',
                            body: answerData
                        });
                    });
                    
                    // Save correct answers
                    const correctAnswers = answers.filter(a => a.is_correct).map(a => parseInt(a.id));
                    const correctData = new FormData();
                    correctData.append('action', 'set_correct');
                    correctData.append('question_id', questionId);
                    correctData.append('correct_answers', JSON.stringify(correctAnswers));
                    answerPromises.push(fetch(`questions.php?exam_id=${examId}`, {
                        method: 'POST',
                        body: correctData
                    }));
                    
                    return Promise.all(answerPromises);
                } else {
                    throw new Error('Failed to save question');
                }
            })
            .then(() => {
                // Show success
                btn.innerHTML = '<i class="fas fa-check mr-2"></i> Saved!';
                btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                btn.classList.add('bg-green-500');
                indicator.classList.remove('hidden');
                
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Question';
                    btn.classList.remove('bg-green-500');
                    btn.classList.add('bg-green-600', 'hover:bg-green-700');
                    btn.disabled = false;
                }, 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                btn.innerHTML = '<i class="fas fa-times mr-2"></i> Error!';
                btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                btn.classList.add('bg-red-500');
                
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Question';
                    btn.classList.remove('bg-red-500');
                    btn.classList.add('bg-green-600', 'hover:bg-green-700');
                    btn.disabled = false;
                }, 2000);
            });
        }
        
        function updateQuestionText(questionId, text) {
            const formData = new FormData();
            formData.append('action', 'update_question');
            formData.append('question_id', questionId);
            formData.append('question_text', text);
            formData.append('question_type', document.querySelector(`[data-question-id="${questionId}"] .question-type-select`).value);
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error saving question');
                }
            });
        }
        
        function updateQuestionType(questionId, type) {
            const formData = new FormData();
            formData.append('action', 'update_question');
            formData.append('question_id', questionId);
            formData.append('question_text', document.querySelector(`[data-question-id="${questionId}"] .question-text`).value);
            formData.append('question_type', type);
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update input types and container data attribute
                    const container = document.querySelector(`.answers-container[data-question-id="${questionId}"]`);
                    container.dataset.type = type;
                    
                    const checkboxes = container.querySelectorAll('.correct-checkbox');
                    checkboxes.forEach(cb => {
                        cb.type = type === 'single' ? 'radio' : 'checkbox';
                    });
                    
                    // If switching to single, keep only first correct answer
                    if (type === 'single') {
                        const checked = container.querySelectorAll('.correct-checkbox:checked');
                        if (checked.length > 1) {
                            for (let i = 1; i < checked.length; i++) {
                                checked[i].checked = false;
                            }
                            // Update correct answers
                            const correctAnswers = [parseInt(checked[0].closest('.answer-row').dataset.answerId)];
                            saveCorrectAnswers(questionId, correctAnswers);
                        }
                    }
                } else {
                    alert('Error updating question type');
                }
            });
        }
        
        function deleteQuestion(questionId) {
            if (!confirm('Are you sure you want to delete this question?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_question');
            formData.append('question_id', questionId);
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting question');
                }
            });
        }
        
        function updateAnswerText(answerId, text) {
            const formData = new FormData();
            formData.append('action', 'update_answer');
            formData.append('answer_id', answerId);
            formData.append('answer_text', text);
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error saving answer');
                }
            });
        }
        
        function toggleCorrectAnswer(questionId, answerId, element) {
            const container = element.closest('.answers-container');
            const type = container.dataset.type;
            let correctAnswers = [];
            
            if (type === 'single') {
                // For single answer, only this one is correct
                correctAnswers = [answerId];
                
                // Update UI
                container.querySelectorAll('.answer-row').forEach(row => {
                    row.classList.remove('correct-answer');
                    row.classList.add('bg-gray-50', 'border-gray-200');
                });
                element.closest('.answer-row').classList.remove('bg-gray-50', 'border-gray-200');
                element.closest('.answer-row').classList.add('correct-answer');
            } else {
                // For multiple answers, collect all checked
                container.querySelectorAll('.correct-checkbox:checked').forEach(cb => {
                    correctAnswers.push(parseInt(cb.closest('.answer-row').dataset.answerId));
                });
                
                // Update UI
                container.querySelectorAll('.answer-row').forEach(row => {
                    const cb = row.querySelector('.correct-checkbox');
                    if (cb.checked) {
                        row.classList.remove('bg-gray-50', 'border-gray-200');
                        row.classList.add('correct-answer');
                    } else {
                        row.classList.remove('correct-answer');
                        row.classList.add('bg-gray-50', 'border-gray-200');
                    }
                });
            }
            
            saveCorrectAnswers(questionId, correctAnswers);
        }
        
        function saveCorrectAnswers(questionId, correctAnswers) {
            const formData = new FormData();
            formData.append('action', 'set_correct');
            formData.append('question_id', questionId);
            formData.append('correct_answers', JSON.stringify(correctAnswers));
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error saving correct answers');
                }
            });
        }
        
        function addAnswer(questionId) {
            const formData = new FormData();
            formData.append('action', 'add_answer');
            formData.append('question_id', questionId);
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Error adding answer');
                }
            });
        }
        
        function deleteAnswer(questionId, answerId) {
            const formData = new FormData();
            formData.append('action', 'delete_answer');
            formData.append('answer_id', answerId);
            formData.append('question_id', questionId);
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Error deleting answer');
                }
            });
        }
        
        function uploadQuestionImage(questionId, input) {
            if (!input.files || !input.files[0]) return;
            
            const formData = new FormData();
            formData.append('action', 'upload_question_image');
            formData.append('question_id', questionId);
            formData.append('image', input.files[0]);
            
            // Show loading state if desired (optional)
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.image_id && data.image_path) {
                    // Find the container relative to the input
                    const container = input.closest('.images-container');
                    // Find the specific add button (the last element that triggers file click)
                    const addBtn = Array.from(container.querySelectorAll('button')).find(b => b.innerHTML.includes('fa-plus'));
                    
                    const imageDiv = document.createElement('div');
                    imageDiv.className = 'relative inline-block';
                    imageDiv.setAttribute('data-image-id', data.image_id);
                    imageDiv.innerHTML = `
                        <img src="../${data.image_path}" class="h-14 w-14 object-cover rounded border shadow-sm" alt="Preview">
                        <button type="button" onclick="removeQuestionImage(${data.image_id})" 
                                class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center hover:bg-red-600 shadow-sm border border-white">
                            <i class="fas fa-times" style="font-size: 8px;"></i>
                        </button>
                    `;
                    
                    if (addBtn) {
                        container.insertBefore(imageDiv, addBtn);
                    } else {
                        container.appendChild(imageDiv);
                    }
                    
                    input.value = ''; // Clear file input for next upload
                } else {
                    alert(data.error || 'Error uploading image');
                }
            })
            .catch(err => {
                console.error('Upload error:', err);
                alert('Connection error while uploading image');
            });
        }
        
        function removeQuestionImage(imageId) {
            if (!confirm('Remove this image?')) return;
            
            const formData = new FormData();
            formData.append('action', 'remove_question_image');
            formData.append('image_id', imageId);
            
            fetch(`questions.php?exam_id=${examId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the image element from DOM
                    const imageEl = document.querySelector(`[data-image-id="${imageId}"]`);
                    if (imageEl) {
                        imageEl.remove();
                    }
                } else {
                    alert('Error removing image');
                }
            });
        }
    </script>
</body>
</html>
