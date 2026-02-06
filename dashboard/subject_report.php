<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
$current_month = date('n');
$current_year = date('Y');
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

if ($assignment_id <= 0) {
    header('Location: reports.php');
    exit;
}

// Fetch assignment details
$assign_query = "SELECT ta.*, s.name as stream_name, sub.name as subject_name, sub.code as subject_code
                FROM teacher_assignments ta
                INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                INNER JOIN streams s ON ss.stream_id = s.id
                INNER JOIN subjects sub ON ss.subject_id = sub.id
                WHERE ta.id = ? AND ta.teacher_id = ?";
$stmt = $conn->prepare($assign_query);
$teacher_id = $_SESSION['user_id'];
$stmt->bind_param("is", $assignment_id, $teacher_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    header('Location: reports.php');
    exit;
}

// Helper to get classes for the selected month/year
$classes = [];

// 1. Zoom Classes
$zoom_query = "SELECT id, title, scheduled_start_time as start_time, 'Zoom' as type, status 
               FROM zoom_classes 
               WHERE teacher_assignment_id = ? 
               AND MONTH(scheduled_start_time) = ? 
               AND YEAR(scheduled_start_time) = ?
               ORDER BY scheduled_start_time DESC";
$stmt = $conn->prepare($zoom_query);
$stmt->bind_param("iii", $assignment_id, $selected_month, $selected_year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    // Count attendees
    $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as total FROM zoom_participants WHERE zoom_class_id = ?");
    $count_stmt->bind_param("i", $row['id']);
    $count_stmt->execute();
    $row['attendees_count'] = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    $classes[] = $row;
}
$stmt->close();

// 2. Physical Classes
$physical_query = "SELECT id, title, CONCAT(class_date, ' ', start_time) as start_time, 'Physical' as type, status 
                   FROM physical_classes 
                   WHERE teacher_assignment_id = ? 
                   AND MONTH(class_date) = ? 
                   AND YEAR(class_date) = ?
                   ORDER BY class_date DESC, start_time DESC";
$stmt = $conn->prepare($physical_query);
$stmt->bind_param("iii", $assignment_id, $selected_month, $selected_year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    // Count attendees
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE physical_class_id = ?");
    $count_stmt->bind_param("i", $row['id']);
    $count_stmt->execute();
    $row['attendees_count'] = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    $classes[] = $row;
}
$stmt->close();

// 3. Live Classes (Recordings where is_live=1)
$live_query = "SELECT id, title, scheduled_start_time as start_time, 'Live' as type, status 
               FROM recordings 
               WHERE teacher_assignment_id = ? AND is_live = 1
               AND MONTH(scheduled_start_time) = ? 
               AND YEAR(scheduled_start_time) = ?
               ORDER BY scheduled_start_time DESC";
$stmt = $conn->prepare($live_query);
$stmt->bind_param("iii", $assignment_id, $selected_month, $selected_year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    // Count attendees (from video_watch_log)
    $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as total FROM video_watch_log WHERE recording_id = ?");
    $count_stmt->bind_param("i", $row['id']);
    $count_stmt->execute();
    $row['attendees_count'] = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    $classes[] = $row;
}
$stmt->close();

// Sort unified classes by start time
usort($classes, function($a, $b) {
    return strtotime($b['start_time']) - strtotime($a['start_time']);
});

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($assignment['subject_name']); ?> Report - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm text-gray-500">
                <li class="inline-flex items-center">
                    <a href="reports.php" class="hover:text-red-600 transition-colors">Reports</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 text-xs mx-1"></i>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="glass-header rounded-2xl p-6 mb-8 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="flex items-center">
                    <div class="w-16 h-16 bg-red-600 rounded-2xl flex items-center justify-center text-white mr-5 shadow-lg shadow-red-200">
                        <i class="fas fa-chart-bar text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($assignment['subject_name']); ?></h1>
                        <p class="text-sm text-gray-500 uppercase tracking-wider font-semibold">
                            <?php echo $assignment['subject_code']; ?> | <?php echo htmlspecialchars($assignment['stream_name']); ?>
                        </p>
                    </div>
                </div>

                <form action="" method="GET" class="flex flex-wrap items-center gap-4">
                    <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                    <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                    
                    <div class="flex items-center gap-2">
                        <label for="month" class="text-sm font-medium text-gray-700">Month:</label>
                        <select name="month" id="month" onchange="this.form.submit()" 
                                class="pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-xl shadow-sm bg-white">
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($selected_month == $num) ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="bg-gray-100 px-4 py-2 rounded-xl text-sm font-bold text-gray-700">
                        Year: <?php echo $selected_year; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php
            $stats = ['Zoom' => 0, 'Physical' => 0, 'Live' => 0];
            foreach ($classes as $c) $stats[$c['type']]++;
            ?>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-video"></i>
                </div>
                <div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo $stats['Zoom']; ?></span>
                    <span class="text-sm text-gray-500 font-medium">Zoom Classes</span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center">
                <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo $stats['Physical']; ?></span>
                    <span class="text-sm text-gray-500 font-medium">Physical Classes</span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-broadcast-tower"></i>
                </div>
                <div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo $stats['Live']; ?></span>
                    <span class="text-sm text-gray-500 font-medium">Live Classes</span>
                </div>
            </div>
        </div>

        <!-- Attendance List -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Monthly Attendance List</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Class Type</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Attendees</th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 bg-gray-50 font-medium italic">
                                    No classes held in <?php echo $months[$selected_month]; ?> <?php echo $selected_year; ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-700 font-medium">
                                        <?php echo date('M d, Y | H:i', strtotime($class['start_time'])); ?>
                                    </td>
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <?php if ($class['type'] === 'Zoom'): ?>
                                            <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full uppercase tracking-tighter shadow-sm"><?php echo $class['type']; ?></span>
                                        <?php elseif ($class['type'] === 'Physical'): ?>
                                            <span class="px-3 py-1 bg-orange-100 text-orange-700 text-xs font-bold rounded-full uppercase tracking-tighter shadow-sm"><?php echo $class['type']; ?></span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full uppercase tracking-tighter shadow-sm"><?php echo $class['type']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="text-sm font-bold text-gray-900 group whitespace-normal line-clamp-1 truncate max-w-xs">
                                            <?php echo htmlspecialchars($class['title']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 whitespace-nowrap text-center">
                                        <div class="inline-flex items-center text-sm font-extrabold text-gray-900 bg-gray-100 px-3 py-1 rounded-lg">
                                            <i class="fas fa-users mr-2 text-gray-400"></i> <?php echo $class['attendees_count']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <button onclick="viewAttendance(<?php echo $class['id']; ?>, '<?php echo $class['type']; ?>')" 
                                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-md shadow-indigo-100">
                                                <i class="fas fa-eye mr-2"></i> View
                                            </button>
                                            <button onclick="downloadAttendance(<?php echo $class['id']; ?>, '<?php echo $class['type']; ?>')" 
                                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-md shadow-emerald-100">
                                                <i class="fas fa-file-pdf mr-2"></i> PDF
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Attendance Details Modal -->
    <div id="attendanceModal" class="hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-[2000] flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Attendance Details</h3>
                        <p id="modalClassInfo" class="text-sm text-gray-500 font-medium"></p>
                    </div>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-white rounded-xl transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="attendeeList" class="p-6 overflow-y-auto flex-1 bg-white">
                <div class="flex justify-center py-10">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-indigo-600"></i>
                </div>
            </div>
            <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                <button onclick="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-300 transition">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewAttendance(classId, type) {
            const modal = document.getElementById('attendanceModal');
            const list = document.getElementById('attendeeList');
            const info = document.getElementById('modalClassInfo');
            
            modal.classList.remove('hidden');
            info.textContent = `Loading information for ${type} class...`;
            list.innerHTML = `<div class="flex flex-col items-center justify-center py-20">
                <i class="fas fa-circle-notch fa-spin text-4xl text-indigo-600 mb-4"></i>
                <p class="text-gray-500 font-medium animate-pulse">Fetching attendee list...</p>
            </div>`;

            fetch(`get_attendance_details.php?class_id=${classId}&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        info.textContent = `${type} Class: ${data.class_title}`;
                        if (data.attendees.length === 0) {
                            list.innerHTML = `<div class="text-center py-20">
                                <i class="fas fa-user-slash text-gray-300 text-5xl mb-4"></i>
                                <p class="text-gray-500 font-bold">No attendees found for this class.</p>
                            </div>`;
                        } else {
                            let html = `<div class="space-y-3">`;
                            data.attendees.forEach(student => {
                                html += `
                                    <div class="flex items-center justify-between p-4 rounded-2xl bg-gray-50 border border-gray-100 hover:border-indigo-200 transition-colors">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold mr-4 shadow-md">
                                                ${student.name.charAt(0)}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900">${student.name}</h4>
                                                <p class="text-xs text-gray-500 font-semibold tracking-wide uppercase">${student.id}</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Attended at</span>
                                            <span class="text-sm font-extrabold text-indigo-700">${student.time}</span>
                                        </div>
                                    </div>
                                `;
                            });
                            html += `</div>`;
                            list.innerHTML = html;
                        }
                    } else {
                        list.innerHTML = `<div class="text-center py-20 text-red-600">
                            <i class="fas fa-exclamation-triangle text-5xl mb-4"></i>
                            <p class="font-bold">Error: ${data.message}</p>
                        </div>`;
                    }
                })
                .catch(err => {
                    list.innerHTML = `<div class="text-center py-20 text-red-600">
                        <i class="fas fa-wifi text-5xl mb-4"></i>
                        <p class="font-bold">Connection error occurred.</p>
                    </div>`;
                });
        }

        function closeModal() {
            document.getElementById('attendanceModal').classList.add('hidden');
        }

        function downloadAttendance(classId, type) {
            window.open(`download_attendance_pdf.php?class_id=${classId}&type=${type}`, '_blank');
        }

        // Close on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
