<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

// Verify user is instructor
if ($_SESSION['role'] !== 'instructor') {
    header("Location: ../dashboard/dashboard.php");
    exit();
}

$page_title = "Instructor Requests";
$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['first_name'] . ' ' . $_SESSION['second_name'];
$instructor_wa = $_SESSION['whatsapp_number'];

// Handle Acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_request'])) {
    $request_id = intval($_POST['request_id']);
    
    // Atomically accept the request to prevent double booking
    $stmt = $conn->prepare("UPDATE instructor_requests SET status = 'accepted', accepted_by = ?, accepted_at = NOW() WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("si", $instructor_id, $request_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_message = "You have successfully accepted the request!";
        
        // Notify Student via WhatsApp
        // Fetch Student Details
        $query = "
            SELECT u.whatsapp_number, u.first_name, s.name as subject_name
            FROM instructor_requests ir
            JOIN users u ON ir.student_id = u.user_id
            JOIN subjects s ON ir.subject_id = s.id
            WHERE ir.id = ?
        ";
        $stmt_info = $conn->prepare($query);
        $stmt_info->bind_param("i", $request_id);
        $stmt_info->execute();
        $req_info = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();
        
        if ($req_info && !empty($req_info['whatsapp_number'])) {
            $msg = "✅ *Instructor Request Accepted*\n\n" .
                   "Hello {$req_info['first_name']},\n" .
                   "Review for *{$req_info['subject_name']}* has been accepted by instructor.\n\n" .
                   "👤 *Instructor:* $instructor_name\n" .
                   "📞 *WhatsApp:* $instructor_wa\n\n" .
                   "They will contact you shortly.";
            sendWhatsAppMessage($req_info['whatsapp_number'], $msg);
        }
        
    } else {
        $error_message = "Failed to accept. It might have been taken by another instructor.";
    }
    $stmt->close();
}

// Fetch Pending Requests (Broadcasted to ANY subject I am assigned to)
// 1. Get my subjects
$my_subjects_query = "SELECT subject_id FROM instructor_subjects WHERE instructor_id = '" . $conn->real_escape_string($instructor_id) . "'";
// 2. Main Query
$pending_query = "
    SELECT 
        ir.id, ir.created_at, ir.request_note,
        s.name as subject_name,
        u.first_name as student_name
    FROM instructor_requests ir
    JOIN subjects s ON ir.subject_id = s.id
    JOIN users u ON ir.student_id = u.user_id
    WHERE ir.status = 'pending' 
    AND ir.subject_id IN ($my_subjects_query)
    ORDER BY ir.created_at DESC
";
$pending_requests = $conn->query($pending_query)->fetch_all(MYSQLI_ASSOC);

// Fetch My Accepted Requests
$accepted_query = "
    SELECT 
        ir.id, ir.accepted_at, ir.request_note,
        s.name as subject_name,
        u.first_name, u.second_name, u.whatsapp_number, u.mobile_number, u.user_id as student_id
    FROM instructor_requests ir
    JOIN subjects s ON ir.subject_id = s.id
    JOIN users u ON ir.student_id = u.user_id
    WHERE ir.accepted_by = ? AND ir.status = 'accepted'
    ORDER BY ir.accepted_at DESC
";
$stmt = $conn->prepare($accepted_query);
$stmt->bind_param("s", $instructor_id);
$stmt->execute();
$accepted_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    
    <?php include '../dashboard/navbar.php'; ?>

    <div class="max-w-7xl mx-auto py-8 px-4">
        
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <div class="bg-purple-100 text-purple-800 px-4 py-2 rounded-lg font-bold">
                Instructor Mode
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Pending Requests Column -->
            <div>
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                    New Requests
                </h2>
                
                <div class="space-y-4">
                    <?php if (empty($pending_requests)): ?>
                        <div class="bg-white p-8 rounded-xl text-center text-gray-400 border border-gray-200 border-dashed">
                            No pending requests at the moment.
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $req): ?>
                        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-purple-500 hover:shadow-lg transition">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($req['subject_name']); ?></h3>
                                <span class="text-xs text-gray-500"><?php echo time_elapsed_string($req['created_at']); ?></span>
                            </div>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($req['request_note'] ?: 'No details provided.'); ?></p>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <button type="submit" name="accept_request" class="w-full bg-purple-600 text-white py-2 rounded-lg font-bold hover:bg-purple-700 transition">
                                    Accept Request
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Accepted Requests Column -->
            <div>
                <h2 class="text-xl font-bold text-gray-800 mb-4">My Students</h2>
                
                <div class="space-y-4">
                    <?php if (empty($accepted_requests)): ?>
                        <div class="bg-white p-8 rounded-xl text-center text-gray-400 border border-gray-200">
                            You haven't accepted any requests yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($accepted_requests as $req): ?>
                        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['second_name']); ?></h3>
                                    <p class="text-sm text-gray-500 font-medium"><?php echo htmlspecialchars($req['subject_name']); ?></p>
                                </div>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold uppercase">Active</span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-xs text-gray-500 uppercase">WhatsApp</p>
                                    <p class="font-mono font-bold text-gray-800"><?php echo htmlspecialchars($req['whatsapp_number']); ?></p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-xs text-gray-500 uppercase">Mobile</p>
                                    <p class="font-mono font-bold text-gray-800"><?php echo htmlspecialchars($req['mobile_number']); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex gap-2">
                                <a href="https://wa.me/<?php echo str_replace(['+', ' '], '', $req['whatsapp_number']); ?>" target="_blank" class="flex-1 bg-green-500 text-white py-2 rounded-lg text-center font-bold hover:bg-green-600 transition flex items-center justify-center gap-2">
                                    <i class="fab fa-whatsapp"></i> Chat
                                </a>
                                <a href="tel:<?php echo $req['mobile_number']; ?>" class="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-center font-bold hover:bg-gray-200 transition">
                                    <i class="fas fa-phone"></i> Call
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>

<?php
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
