<?php
session_start();
require_once '../config.php';
require_once '../whatsapp_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['second_name'];
$user_wa = $_SESSION['whatsapp_number'] ?? '';

$page_title = "Instructor Dashboard";

// =========================================================================
// INSTRUCTOR LOGIC
// =========================================================================
if ($user_role === 'instructor') {
    
    // Handle Acceptance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_request'])) {
        $request_id = intval($_POST['request_id']);
        
        // Atomically accept
        $stmt = $conn->prepare("UPDATE instructor_requests SET status = 'accepted', accepted_by = ?, accepted_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("si", $user_id, $request_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "Request accepted successfully!";
            
            // Notify Student
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
                       "👤 *Instructor:* $user_name\n" .
                       "📞 *WhatsApp:* $user_wa\n\n" .
                       "They will contact you shortly.";
                sendWhatsAppMessage($req_info['whatsapp_number'], $msg);
            }
        } else {
            $error_message = "Failed to accept. Request may have been taken or cancelled.";
        }
        $stmt->close();
    }

    // Fetch Pending Requests
    $my_subjects_query = "SELECT subject_id FROM instructor_subjects WHERE instructor_id = '" . $conn->real_escape_string($user_id) . "'";
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

    // Fetch Accepted Requests
    $accepted_query = "
        SELECT 
            ir.id, ir.accepted_at, ir.request_note,
            s.name as subject_name,
            u.first_name, u.second_name, u.whatsapp_number, u.mobile_number
        FROM instructor_requests ir
        JOIN subjects s ON ir.subject_id = s.id
        JOIN users u ON ir.student_id = u.user_id
        WHERE ir.accepted_by = ? AND ir.status = 'accepted'
        ORDER BY ir.accepted_at DESC
    ";
    $stmt = $conn->prepare($accepted_query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $accepted_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// =========================================================================
// STUDENT LOGIC
// =========================================================================
if ($user_role === 'student') {
    
    // Handle Request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_instructor'])) {
        $subject_id = intval($_POST['subject_id']);
        $note = trim($_POST['note'] ?? '');
        
        if ($subject_id > 0) {
            $stmt = $conn->prepare("INSERT INTO instructor_requests (student_id, subject_id, request_note, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("sis", $user_id, $subject_id, $note);
            
            if ($stmt->execute()) {
                $success_message = "Request sent to instructors!";
                
                // Notify Instructors
                $sub_res = $conn->query("SELECT name FROM subjects WHERE id = $subject_id")->fetch_assoc();
                $subject_name = $sub_res['name'];
                
                $query = "
                    SELECT u.whatsapp_number 
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
                        $msg = "📢 *New Request*\nSubject: *$subject_name*\nStudent is waiting.";
                        sendWhatsAppMessage($inst['whatsapp_number'], $msg);
                    }
                }
                $stmt_notif->close();
            } else {
                $error_message = "Error submitting request.";
            }
            $stmt->close();
        }
    }

    // Fetch Subjects
    $subjects_query = "
        SELECT DISTINCT s.id, s.name, s.code
        FROM subjects s
        JOIN instructor_subjects is_sub ON s.id = is_sub.subject_id
        WHERE 1=1
        ORDER BY s.name
    ";
    $subjects = $conn->query($subjects_query)->fetch_all(MYSQLI_ASSOC);

    // Fetch My Requests
    $my_requests_query = "
        SELECT 
            ir.id, ir.status, ir.created_at, ir.request_note,
            s.name as subject_name,
            u.first_name, u.second_name, u.whatsapp_number
        FROM instructor_requests ir
        JOIN subjects s ON ir.subject_id = s.id
        LEFT JOIN users u ON ir.accepted_by = u.user_id
        WHERE ir.student_id = ?
        ORDER BY ir.created_at DESC
    ";
    $stmt = $conn->prepare($my_requests_query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $my_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        function filterSubjects() {
            let input = document.getElementById('searchSubject');
            let filter = input.value.toUpperCase();
            let cards = document.getElementsByClassName('subject-card');
            
            for (let i = 0; i < cards.length; i++) {
                let txtValue = cards[i].getAttribute('data-name');
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    cards[i].style.display = "";
                } else {
                    cards[i].style.display = "none";
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        
        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- ================= INSTRUCTOR VIEW ================= -->
        <?php if ($user_role === 'instructor'): ?>
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Instructor Dashboard</h1>
                <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-bold">Logged in as Instructor</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Pending Requests -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-bell text-yellow-500"></i> New Requests
                    </h2>
                    <div class="space-y-4 max-h-[600px] overflow-y-auto">
                        <?php if (empty($pending_requests)): ?>
                            <p class="text-gray-400 text-center py-8">No new requests.</p>
                        <?php else: ?>
                            <?php foreach ($pending_requests as $req): ?>
                                <div class="border border-gray-100 rounded-lg p-4 hover:shadow-md transition">
                                    <div class="flex justify-between mb-2">
                                        <span class="font-bold text-lg text-purple-700"><?php echo htmlspecialchars($req['subject_name']); ?></span>
                                        <span class="text-xs text-gray-400"><?php echo time_elapsed_string($req['created_at']); ?></span>
                                    </div>
                                    <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($req['request_note'] ?: "No note provided."); ?></p>
                                    <form method="POST">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="accept_request" class="w-full bg-blue-600 text-white py-2 rounded-lg font-bold hover:bg-blue-700 transition">
                                            Accept Request
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Accepted Students -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-user-graduate text-green-500"></i> My Students
                    </h2>
                    <div class="space-y-4 max-h-[600px] overflow-y-auto">
                        <?php if (empty($accepted_requests)): ?>
                            <p class="text-gray-400 text-center py-8">No accepted requests yet.</p>
                        <?php else: ?>
                            <?php foreach ($accepted_requests as $req): ?>
                                <div class="border border-green-100 bg-green-50/30 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['second_name']); ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($req['subject_name']); ?></p>
                                        </div>
                                        <div class="flex gap-2">
                                            <a href="https://wa.me/<?php echo str_replace(['+', ' '], '', $req['whatsapp_number']); ?>" target="_blank" class="text-green-600 hover:text-green-800">
                                                <i class="fab fa-whatsapp text-xl"></i>
                                            </a>
                                            <a href="tel:<?php echo $req['mobile_number']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-phone text-xl"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-400 text-right mt-2">Accepted <?php echo time_elapsed_string($req['accepted_at']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <!-- ================= STUDENT VIEW ================= -->
        <?php elseif ($user_role === 'student'): ?>
            
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Find an Instructor</h1>
                    <p class="text-gray-500">Select a subject to request a personal session.</p>
                </div>
                
                <!-- Search Bar -->
                <div class="relative w-full md:w-96">
                    <input type="text" id="searchSubject" onkeyup="filterSubjects()" placeholder="Search subjects..." 
                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 outline-none shadow-sm transition">
                    <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Subject List -->
                <div class="lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($subjects as $sub): ?>
                            <div class="subject-card bg-white p-5 rounded-xl shadow-sm hover:shadow-md transition border border-gray-100 flex justify-between items-center group" data-name="<?php echo htmlspecialchars($sub['name'] . ' ' . $sub['code']); ?>">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800 group-hover:text-purple-600 transition"><?php echo htmlspecialchars($sub['name']); ?></h3>
                                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded"><?php echo htmlspecialchars($sub['code']); ?></span>
                                </div>
                                <button onclick="openRequestModal(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['name']); ?>')" 
                                        class="bg-purple-100 text-purple-700 px-4 py-2 rounded-lg font-bold hover:bg-purple-600 hover:text-white transition">
                                    Request
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sidebar: My Requests -->
                <div class="lg:col-span-1">
                    <div class="bg-white p-6 rounded-xl shadow-lg sticky top-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">My Requests</h2>
                        
                        <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                            <?php if (empty($my_requests)): ?>
                                <p class="text-gray-400 text-center text-sm">You haven't made any requests yet.</p>
                            <?php else: ?>
                                <?php foreach ($my_requests as $req): ?>
                                    <div class="p-3 rounded-lg border <?php echo ($req['status'] === 'accepted') ? 'border-green-200 bg-green-50' : 'border-gray-100 bg-gray-50'; ?>">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="font-bold text-sm text-gray-700"><?php echo htmlspecialchars($req['subject_name']); ?></span>
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full">Pending</span>
                                            <?php elseif ($req['status'] === 'accepted'): ?>
                                                <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded-full">Accepted</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($req['status'] === 'accepted'): ?>
                                            <div class="mt-2 pt-2 border-t border-green-200">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-xs text-purple-700">
                                                        <i class="fas fa-chalkboard-teacher"></i>
                                                    </div>
                                                    <span class="text-xs font-bold text-gray-800"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['second_name']); ?></span>
                                                </div>
                                                <a href="https://wa.me/<?php echo str_replace(['+', ' '], '', $req['whatsapp_number']); ?>" target="_blank" class="block w-full text-center bg-green-500 text-white text-xs py-1.5 rounded hover:bg-green-600 transition">
                                                    <i class="fab fa-whatsapp"></i> Chat Now
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-xs text-gray-400 mt-1">Waiting for instructor...</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Request Modal -->
            <div id="requestModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl transform transition-all scale-100">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Request Instructor</h3>
                    <p class="text-gray-500 text-sm mb-4">Subject: <span id="modalSubjectName" class="font-bold text-purple-600"></span></p>
                    
                    <form method="POST">
                        <input type="hidden" name="subject_id" id="modalSubjectId">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Add a Note (Optional)</label>
                            <textarea name="note" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Explain what you need help with..."></textarea>
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="button" onclick="closeRequestModal()" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-bold transition">Cancel</button>
                            <button type="submit" name="request_instructor" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-bold transition">Send Request</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function openRequestModal(id, name) {
                    document.getElementById('modalSubjectId').value = id;
                    document.getElementById('modalSubjectName').innerText = name;
                    document.getElementById('requestModal').classList.remove('hidden');
                    document.getElementById('requestModal').classList.add('flex');
                }

                function closeRequestModal() {
                    document.getElementById('requestModal').classList.add('hidden');
                    document.getElementById('requestModal').classList.remove('flex');
                }
                
                // Close modal on click outside
                document.getElementById('requestModal').addEventListener('click', function(e) {
                    if (e.target === this) closeRequestModal();
                });
            </script>

        <?php else: ?>
            <!-- Fallback for other roles (Admin etc) -->
            <div class="text-center py-20">
                <h2 class="text-2xl font-bold text-gray-600">Access Restricted</h2>
                <p class="text-gray-500 mt-2">Please login as a Student or Instructor.</p>
                <a href="dashboard.php" class="inline-block mt-4 text-blue-600 hover:underline">Go to Dashboard</a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
