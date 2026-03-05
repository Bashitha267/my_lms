<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

// Verify user is student
if ($_SESSION['role'] !== 'student') {
    header("Location: ../dashboard/dashboard.php");
    exit();
}

$page_title = "Request Instructor";
$success_message = '';
$error_message = '';
$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['first_name'] . ' ' . $_SESSION['second_name'];

// Handle Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_instructor'])) {
    
    $subject_id = intval($_POST['subject_id']);
    $note = trim($_POST['note'] ?? '');
    
    if ($subject_id > 0) {
        // Insert Request
        $stmt = $conn->prepare("INSERT INTO instructor_requests (student_id, subject_id, request_note, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("sis", $student_id, $subject_id, $note);
        
        if ($stmt->execute()) {
            $success_message = "Request submitted successfully! Instructors have been notified.";
            
            // Notify Assigned Instructors via WhatsApp
            // 1. Get Subject Name
            $sub_res = $conn->query("SELECT name FROM subjects WHERE id = $subject_id")->fetch_assoc();
            $subject_name = $sub_res['name'];
            
            // 2. Find Instructors assigned to this subject
            $query = "
                SELECT u.whatsapp_number, u.first_name 
                FROM instructor_subjects is_sub
                JOIN users u ON is_sub.instructor_id = u.user_id
                WHERE is_sub.subject_id = ? AND u.status = 1 AND u.approved = 1
            ";
            $stmt_notif = $conn->prepare($query);
            $stmt_notif->bind_param("i", $subject_id);
            $stmt_notif->execute();
            $result = $stmt_notif->get_result();
            
            while ($inst = $result->fetch_assoc()) {
                if (!empty($inst['whatsapp_number'])) {
                    $msg = "📢 *New Instructor Request*\n\n" .
                           "Subject: *$subject_name*\n" .
                           "Student is waiting for help.\n\n" .
                           "Login to LMS to accept the request.";
                    // We don't send student details yet.
                     sendWhatsAppMessage($inst['whatsapp_number'], $msg);
                }
            }
            $stmt_notif->close();
            
        } else {
            $error_message = "Error submitting request.";
        }
        $stmt->close();
    } else {
        $error_message = "Please select a subject.";
    }
}

// Fetch Subjects available for requests (subjects with at least one instructor assigned)
$subjects_query = "
    SELECT DISTINCT s.id, s.name, s.code
    FROM subjects s
    JOIN instructor_subjects is_sub ON s.id = is_sub.subject_id
    WHERE 1=1
    ORDER BY s.name
";
$subjects = $conn->query($subjects_query)->fetch_all(MYSQLI_ASSOC);

// Fetch My Recent Requests
$my_requests_query = "
    SELECT 
        ir.id, ir.status, ir.created_at, ir.request_note,
        s.name as subject_name,
        u.first_name, u.second_name, u.whatsapp_number
    FROM instructor_requests ir
    JOIN subjects s ON ir.subject_id = s.id
    LEFT JOIN users u ON ir.accepted_by = u.user_id
    WHERE ir.student_id = ?
    ORDER BY ir.created_at DESC LIMIT 5
";
$stmt = $conn->prepare($my_requests_query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$my_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Instructor - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <?php include '../dashboard/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Request an Instructor</h1>
        <p class="text-gray-600 mb-8">Need extra help? Request a personal instructor session.</p>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Request Form -->
            <div class="md:col-span-2">
                <div class="glass-card bg-white p-6 rounded-2xl shadow-lg">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="block text-gray-700 font-bold mb-2">Select Subject</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($subjects as $sub): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="subject_id" value="<?php echo $sub['id']; ?>" class="peer sr-only" required>
                                        <div class="p-4 rounded-xl border-2 border-gray-200 peer-checked:border-purple-600 peer-checked:bg-purple-50 transition hover:bg-gray-50">
                                            <div class="font-bold text-gray-800"><?php echo htmlspecialchars($sub['name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($sub['code']); ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 font-bold mb-2">Note (Optional)</label>
                            <textarea name="note" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="e.g., I need help with Thermodynamics..."></textarea>
                        </div>

                        <button type="submit" name="request_instructor" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-xl shadow-lg transition transform hover:-translate-y-1">
                            Send Request
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent History -->
            <div class="md:col-span-1">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Your Recent Requests</h3>
                <div class="space-y-4">
                    <?php foreach ($my_requests as $req): ?>
                        <div class="bg-white p-4 rounded-xl shadow border border-gray-100">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-bold text-gray-800"><?php echo htmlspecialchars($req['subject_name']); ?></span>
                                <?php
                                    $status_colors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'accepted' => 'bg-green-100 text-green-800',
                                        'completed' => 'bg-blue-100 text-blue-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $cls = $status_colors[$req['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 rounded-md text-xs font-bold uppercase <?php echo $cls; ?>"><?php echo $req['status']; ?></span>
                            </div>
                            
                            <?php if ($req['status'] === 'accepted'): ?>
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-xs text-gray-500">Instructor:</p>
                                    <p class="font-bold text-purple-700"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['second_name']); ?></p>
                                    <a href="https://wa.me/<?php echo str_replace(['+', ' '], '', $req['whatsapp_number']); ?>" target="_blank" class="text-green-600 text-sm hover:underline mt-1 inline-block">
                                        <i class="fab fa-whatsapp"></i> Chat Now
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 mt-2">
                                    <i class="far fa-clock"></i> <?php echo date('M d, h:i A', strtotime($req['created_at'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($my_requests)): ?>
                        <div class="text-center text-gray-400 py-8">No requests yet.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
