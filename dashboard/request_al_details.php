<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Only Admin and Teachers can access
if ($role !== 'admin' && $role !== 'teacher') {
    header('Location: ../dashboard/dashboard.php');
    exit;
}

$page_title = "Request A/L Details";
$success_message = '';
$error_message = '';

// Handle WhatsApp Request (Initial Details)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $subject_id = intval($_POST['subject_id']);
    $subject_name = $_POST['subject_name'];
    
    // Find enrolled students for this subject
    // We need to join stream_subjects to get the correct enrollment
    $query = "SELECT DISTINCT u.whatsapp_number, u.first_name, u.user_id 
              FROM users u
              INNER JOIN student_enrollment se ON u.user_id = se.student_id
              INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
              WHERE ss.subject_id = ? AND se.status = 'active' AND u.status = 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    $link = "https://" . $_SERVER['HTTP_HOST'] . "/lms/student/al_exam_form.php"; 
    
    while ($student = $result->fetch_assoc()) {
        if (!empty($student['whatsapp_number'])) {
            $msg = "📢 *Action Required / අනිවාර්යයෙන් පුරවන්න*\n\n" .
                   "🎓 *A/L Exam Details Collection*\n" .
                   "📌 *Subject / විෂය:* $subject_name\n\n" .
                   "Dear Student,\n" .
                   "Please update your A/L exam details (Subjects, Index Number, District) immediately using the link below.\n\n" .
                   "ඔබගේ උසස් පෙළ විභාග තොරතුරු (විෂයන්, විභාග අංකය, දිස්ත්‍රික්කය) පහත සබැඳිය භාවිතා කර වහාම යාවත්කාලීන කරන්න.\n\n" .
                   "🔗 *Link:* $link\n\n" .
                   "⚠️ *Note:* You will not be able to access the LMS until you complete this form.\n" .
                   "මිමෙම පෝරමය පුරවන තෙක් ඔබට LMS වෙත පිවිසිය නොහැක.";
            
            sendWhatsAppMessage($student['whatsapp_number'], $msg);
            $count++;
        }
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

// Fetch Subjects (Grouped by Stream/Teacher if possible, or just unique subjects)
// For simplicty and as per request: Subject Cards with Teacher Name
$subjects = [];

if ($role === 'teacher') {
    // Show only subjects assigned to this teacher
    $query = "SELECT DISTINCT s.id, s.name, s.code, u.first_name, u.second_name
              FROM subjects s
              INNER JOIN stream_subjects ss ON s.id = ss.subject_id
              INNER JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              WHERE ta.teacher_id = ? AND ta.status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Admin sees all subjects and their teachers
    $query = "SELECT DISTINCT s.id, s.name, s.code, u.first_name, u.second_name
              FROM subjects s
              INNER JOIN stream_subjects ss ON s.id = ss.subject_id
              INNER JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              WHERE ta.status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
}

while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request A/L Details - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <?php include 'navbar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-grow container mx-auto px-4 py-8 max-w-7xl">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">A/L Exam Details Collection</h1>
                    <p class="text-gray-500 mt-1">Request exam details from students and view responses.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($subjects as $subject): ?>
                <?php 
                    $teacher_name = trim($subject['first_name'] . ' ' . $subject['second_name']);
                ?>
                <div class="glass-card rounded-2xl p-6 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 bg-white">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 bg-red-50 rounded-xl">
                            <i class="fas fa-book text-red-600 text-xl"></i>
                        </div>
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full">
                            <?php echo htmlspecialchars($subject['code']); ?>
                        </span>
                    </div>
                    
                    <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($subject['name']); ?></h3>
                    <div class="flex items-center text-gray-500 text-sm mb-6">
                        <i class="fas fa-chalkboard-teacher mr-2"></i>
                        <?php echo htmlspecialchars($teacher_name); ?>
                    </div>
                    
                    <div class="flex gap-3">
                        <button onclick="sendRequest(<?php echo $subject['id']; ?>, '<?php echo addslashes($subject['name']); ?>')" 
                                class="flex-1 bg-red-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                            <i class="fab fa-whatsapp text-lg"></i>
                            Request Details
                        </button>
                        
                        <a href="view_al_responses.php?subject_id=<?php echo $subject['id']; ?>" 
                           class="flex-none bg-gray-100 text-gray-700 px-4 py-2.5 rounded-xl hover:bg-gray-200 transition-colors"
                           title="View Responses">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($subjects)): ?>
                <div class="col-span-full py-12 text-center text-gray-400">
                    <i class="fas fa-folder-open text-4xl mb-3"></i>
                    <p>No subjects found assigned to you.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full mx-4 text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600 mx-auto mb-4"></div>
            <h3 class="text-lg font-semibold text-gray-900">Sending Notifications...</h3>
            <p class="text-gray-500 text-sm mt-2">Please wait while we send WhatsApp messages to all enrolled students.</p>
        </div>
    </div>

    <script>
        function sendRequest(subjectId, subjectName) {
            if (!confirm(`Send WhatsApp request to all students enrolled in ${subjectName}?`)) return;
            
            document.getElementById('loadingModal').classList.remove('hidden');
            
            const formData = new FormData();
            formData.append('send_request', '1');
            formData.append('subject_id', subjectId);
            formData.append('subject_name', subjectName);
            
            fetch('request_al_details.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('loadingModal').classList.add('hidden');
                if (data.success) {
                    alert(`Successfully sent messages to ${data.count} students!`);
                } else {
                    alert('Error sending messages.');
                }
            })
            .catch(err => {
                document.getElementById('loadingModal').classList.add('hidden');
                console.error(err);
                alert('An error occurred.');
            });
        }
    </script>
</body>
</html>
