<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$class_id = intval($_GET['id'] ?? 0);

if ($role !== 'teacher' || $class_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Fetch class details
$class_query = "SELECT pc.*, s.name as subject_name 
                FROM physical_classes pc 
                JOIN teacher_assignments ta ON pc.teacher_assignment_id = ta.id
                JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                JOIN subjects s ON ss.subject_id = s.id
                WHERE pc.id = ? AND pc.teacher_id = ? LIMIT 1";
$stmt = $conn->prepare($class_query);
$stmt->bind_param("is", $class_id, $user_id);
$stmt->execute();
$class_result = $stmt->get_result();

if ($class_result->num_rows === 0) {
    header('Location: live_classes.php');
    exit;
}

$class_data = $class_result->fetch_assoc();

// Handle Attendance Submission (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    header('Content-Type: application/json');
    $student_id = trim($_POST['student_id'] ?? '');
    
    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Empty Student ID.']);
        exit;
    }

    // Check if student exists
    $user_check = "SELECT first_name, second_name FROM users WHERE user_id = ? LIMIT 1";
    $u_stmt = $conn->prepare($user_check);
    $u_stmt->bind_param("s", $student_id);
    $u_stmt->execute();
    $user_res = $u_stmt->get_result();
    
    if ($user_res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found in database.']);
        exit;
    }
    
    $student_data = $user_res->fetch_assoc();
    $student_name = trim($student_data['first_name'] . ' ' . $student_data['second_name']);

    // Mark attendance
    $insert_query = "INSERT IGNORE INTO attendance (physical_class_id, student_id) VALUES (?, ?)";
    $i_stmt = $conn->prepare($insert_query);
    $i_stmt->bind_param("is", $class_id, $student_id);
    
    if ($i_stmt->execute()) {
        if ($conn->affected_rows > 0) {
            // WhatsApp Notifications
            sendPhysicalJoinNotifications($conn, $class_id, $student_id);
            
            echo json_encode(['success' => true, 'message' => 'Attendance marked for ' . $student_name, 'student_name' => $student_name, 'student_id' => $student_id, 'attended_at' => date('H:i:s')]);
        } else {
            echo json_encode(['success' => false, 'message' => $student_name . ' has already been marked present.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

/**
 * Helper function to send WhatsApp notifications for Physical Class Attendance
 */
function sendPhysicalJoinNotifications($conn, $class_id, $student_id) {
    if (!file_exists('../whatsapp_config.php')) return;
    require_once '../whatsapp_config.php';
    if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED) return;

    // Fetch Details
    $query = "SELECT pc.title, s.name as subject_name, pc.location,
                     stu.first_name as student_name, stu.whatsapp_number as student_wa, stu.mobile_number as student_mob,
                     tchr.first_name as teacher_first, tchr.second_name as teacher_second, tchr.whatsapp_number as teacher_wa, tchr.mobile_number as teacher_mob
              FROM physical_classes pc
              JOIN teacher_assignments ta ON pc.teacher_assignment_id = ta.id
              JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              JOIN subjects s ON ss.subject_id = s.id
              JOIN users tchr ON pc.teacher_id = tchr.user_id
              JOIN users stu ON stu.user_id = ?
              WHERE pc.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $student_id, $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $subj = $row['subject_name'];
        $title = $row['title'];
        $loc = $row['location'];
        $s_name = $row['student_name'];
        $s_wa = !empty($row['student_wa']) ? $row['student_wa'] : $row['student_mob'];
        $t_name = trim($row['teacher_first'] . ' ' . $row['teacher_second']);
        $t_wa = !empty($row['teacher_wa']) ? $row['teacher_wa'] : $row['teacher_mob'];

        $now = date('h:i A');

        // 1. Notify Teacher
        if (!empty($t_wa)) {
            $t_msg = "🏛️ *Student Attended Physical Class*\n\n" .
                   "Student: *{$s_name}* ({$student_id})\n" .
                   "Subject: *{$subj}*\n" .
                   "Class: *{$title}*\n" .
                   "Time: *{$now}*";
            sendWhatsAppMessage($t_wa, $t_msg);
        }

        // 2. Notify Student
        if (!empty($s_wa)) {
            $s_msg = "🏛️ *Attendance Marked - Physical Class*\n\n" .
                   "Hello {$s_name},\n" .
                   "Your attendance for *{$subj}* by *{$t_name}* at *{$loc}* has been marked successfully.\n\n" .
                   "--------------------------\n\n" .
                   "ඔබේ *{$subj}* ({$t_name}) භෞතික පන්තිය සඳහා පැමිණීම සාර්ථකව සටහන් කර ගන්නා ලදී.\n\n" .
                   "Thank you! - LearnerX Team";
            sendWhatsAppMessage($s_wa, $s_msg);
        }
    }
    $stmt->close();
}


// Fetch currently attended students
$attendance_query = "SELECT a.student_id, a.attended_at, u.first_name, u.second_name 
                     FROM attendance a 
                     JOIN users u ON a.student_id = u.user_id 
                     WHERE a.physical_class_id = ? 
                     ORDER BY a.attended_at DESC";
$a_stmt = $conn->prepare($attendance_query);
$a_stmt->bind_param("i", $class_id);
$a_stmt->execute();
$attendance_list = $a_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance - <?php echo htmlspecialchars($class_data['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .scanner-container {
            border: 4px solid #ef4444;
            border-radius: 1.5rem;
            overflow: hidden;
            background: #000;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left Side: Scanner -->
            <div class="w-full lg:w-1/2">
                <div class="bg-white rounded-3xl shadow-xl overflow-hidden p-6">
                    <div class="mb-6">
                        <h1 class="text-2xl font-black text-gray-900 mb-1">Attendance Scanner</h1>
                        <p class="text-red-500 font-bold"><?php echo htmlspecialchars($class_data['subject_name']); ?>: <?php echo htmlspecialchars($class_data['title']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">Point the camera at the student's ID QR code</p>
                    </div>

                    <div class="scanner-container aspect-square mb-6 shadow-inner" id="reader"></div>

                    <div class="space-y-4">
                        <div id="status-message" class="hidden p-4 rounded-xl text-center font-bold text-sm transition-all duration-300"></div>
                        
                        <div class="flex items-center space-x-3">
                            <input type="text" id="manual_id" placeholder="Enter Student ID Manually" 
                                   class="flex-1 px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-500 outline-none">
                            <button onclick="markManualAttendance()" 
                                    class="bg-gray-900 text-white px-6 py-3 rounded-xl font-bold hover:bg-black transition-colors">
                                Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Attendance List -->
            <div class="w-full lg:w-1/2">
                <div class="bg-white rounded-3xl shadow-xl overflow-hidden h-full flex flex-col">
                    <div class="p-6 bg-gray-900 text-white flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold">Attendees</h2>
                            <p class="text-gray-400 text-xs tracking-widest uppercase">Verified Students</p>
                        </div>
                        <div class="bg-red-600 px-4 py-2 rounded-full font-black text-lg shadow-lg" id="attendee-count">
                            <?php echo $attendance_list->num_rows; ?>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto max-h-[600px] p-6">
                        <div class="space-y-4" id="attendance-list">
                            <?php while($row = $attendance_list->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100 hover:shadow-md transition-shadow">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 bg-red-100 text-red-600 rounded-full flex items-center justify-center font-black">
                                            <?php echo substr($row['first_name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['second_name']); ?></h4>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['student_id']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">Attended At</p>
                                        <p class="text-sm font-black text-gray-900"><?php echo date('H:i:s', strtotime($row['attended_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            
                            <?php if ($attendance_list->num_rows === 0): ?>
                                <div id="no-attendees" class="text-center py-20">
                                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-users text-gray-300 text-3xl"></i>
                                    </div>
                                    <p class="text-gray-400 font-medium">No students scanned yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let count = <?php echo $attendance_list->num_rows; ?>;
        const html5QrCode = new Html5Qrcode("reader");
        let isScanned = false;

        function markAttendance(studentId) {
            if (isScanned) return;
            isScanned = true;

            const formData = new FormData();
            formData.append('mark_attendance', '1');
            formData.append('student_id', studentId);

            fetch('add_attendance.php?id=<?php echo $class_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageEl = document.getElementById('status-message');
                messageEl.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700', 'bg-yellow-100', 'text-yellow-700');
                
                if (data.success) {
                    messageEl.textContent = data.message;
                    messageEl.classList.add('bg-green-100', 'text-green-700');
                    addAttendeeToList(data);
                    
                    // Add some feedback for scanning
                    document.querySelector('.scanner-container').classList.add('border-green-500');
                    setTimeout(() => {
                        document.querySelector('.scanner-container').classList.remove('border-green-500');
                    }, 1000);
                } else {
                    messageEl.textContent = data.message;
                    messageEl.classList.add('bg-yellow-100', 'text-yellow-700');
                }
                
                messageEl.classList.remove('hidden');
                
                // Reset scanned status after short delay
                setTimeout(() => {
                    isScanned = false;
                    messageEl.classList.add('hidden');
                }, 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                isScanned = false;
            });
        }

        function markManualAttendance() {
            const sid = document.getElementById('manual_id').value;
            if (sid) {
                markAttendance(sid);
                document.getElementById('manual_id').value = '';
            }
        }

        function addAttendeeToList(data) {
            const list = document.getElementById('attendance-list');
            const noAttendees = document.getElementById('no-attendees');
            if (noAttendees) noAttendees.remove();

            const item = document.createElement('div');
            item.className = "flex items-center justify-between p-4 bg-green-50 rounded-2xl border border-green-100 hover:shadow-md transition-all transform translate-y-4 opacity-0";
            item.innerHTML = `
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-red-100 text-red-600 rounded-full flex items-center justify-center font-black">
                        ${data.student_name.charAt(0)}
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900">${data.student_name}</h4>
                        <p class="text-xs text-gray-500">${data.student_id}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">Attended At</p>
                    <p class="text-sm font-black text-gray-900">${data.attended_at}</p>
                </div>
            `;
            
            list.insertBefore(item, list.firstChild);
            
            // Animation
            setTimeout(() => {
                item.classList.remove('translate-y-4', 'opacity-0');
            }, 50);

            count++;
            document.getElementById('attendee-count').textContent = count;
        }

        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        html5QrCode.start({ facingMode: "environment" }, config, (decodedText) => {
            markAttendance(decodedText);
        });
    </script>
</body>
</html>
