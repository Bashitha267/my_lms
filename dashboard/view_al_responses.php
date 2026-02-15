<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'admin' && $role !== 'teacher') {
    header('Location: ../dashboard/dashboard.php');
    exit;
}

$subject_id = intval($_GET['subject_id'] ?? 0);
if ($subject_id <= 0) {
    header('Location: request_al_details.php');
    exit;
}

// Get Subject Info
$stmt = $conn->prepare("SELECT name, code FROM subjects WHERE id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    die("Subject not found.");
}

// Get all enrolled students and their submission status
$query = "SELECT u.user_id, u.first_name, u.second_name, u.whatsapp_number,
                 als.id as submission_id, als.subject_1, als.subject_2, als.subject_3, 
                 als.index_number, als.district, als.photo_path, als.created_at,
                 als.result_1, als.result_2, als.result_3, als.results_submitted_at
          FROM users u
          INNER JOIN student_enrollment se ON u.user_id = se.student_id
          INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
          LEFT JOIN al_exam_submissions als ON u.user_id = als.student_id
          WHERE ss.subject_id = ? AND se.status = 'active' AND u.status = 1
          ORDER BY als.id DESC, u.first_name ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
$responded_count = 0;
$results_submitted_count = 0;
$total_count = 0;
$grade_counts = ['A' => 0, 'B' => 0, 'C' => 0, 'S' => 0, 'F' => 0, 'AB' => 0];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
    $total_count++;
    if (!empty($row['submission_id'])) {
        $responded_count++;
    }
    if (!empty($row['results_submitted_at'])) {
        $results_submitted_count++;
        // Count grades for this specific subject matches
        // Note: Students submit result_1, result_2, but we don't know which corresponds to current $subject_id
        // We need to check if subject_1 == current subject name
        // But here we only have names. A better approach would be to check if the subject name in submission matches the current subject name
        
        // Let's assume for simplicity we count all 'A's across all students for this subject if they took it.
        // Actually, the submission has subject_1, subject_2. We need to find which one is THIS subject.
        
        $subj_name = strtolower(trim($subject['name']));
        $grade = null;
        
        if (strtolower(trim($row['subject_1'])) == $subj_name) $grade = $row['result_1'];
        elseif (strtolower(trim($row['subject_2'])) == $subj_name) $grade = $row['result_2'];
        elseif (strtolower(trim($row['subject_3'])) == $subj_name) $grade = $row['result_3'];
        
        if ($grade && isset($grade_counts[$grade])) {
            $grade_counts[$grade]++;
        }
        
        // Attach the specific grade for this subject to the student array for display
        $students[count($students)-1]['subject_grade'] = $grade;
    }
}
$stmt->close();

$not_responded_count = $total_count - $responded_count;
$response_rate = $total_count > 0 ? round(($responded_count / $total_count) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Responses - <?php echo htmlspecialchars($subject['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-gray-800">

    <div class="min-h-screen container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <a href="request_al_details.php" class="text-gray-500 hover:text-red-600 mb-2 inline-block">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Subjects
                </a>
                <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($subject['name']); ?></h1>
                <p class="text-gray-500">Student Response Report</p>
            </div>
            <div class="flex gap-4">
                <div class="bg-white px-4 py-2 rounded-xl shadow-sm border border-gray-100 text-center">
                    <span class="block text-2xl font-bold text-green-600"><?php echo $responded_count; ?></span>
                    <span class="text-xs text-gray-500 font-semibold uppercase">Responded</span>
                </div>
                <div class="bg-white px-4 py-2 rounded-xl shadow-sm border border-gray-100 text-center">
                    <span class="block text-2xl font-bold text-red-600"><?php echo $not_responded_count; ?></span>
                    <span class="text-xs text-gray-500 font-semibold uppercase">Pending</span>
                </div>
                <div class="bg-white px-4 py-2 rounded-xl shadow-sm border border-gray-100 text-center">
                    <span class="block text-2xl font-bold text-blue-600"><?php echo $response_rate; ?>%</span>
                    <span class="text-xs text-gray-500 font-semibold uppercase">Rate</span>
                </div>
            </div>
        </div>

        <!-- Grade Stats -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            <?php foreach ($grade_counts as $grade => $count): ?>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 text-center">
                <span class="block text-xl font-bold text-gray-800"><?php echo $count; ?></span>
                <span class="text-xs text-gray-500 font-bold uppercase">Average <?php echo $grade; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="mb-6 flex justify-end">
            <select id="gradeFilter" onchange="filterTable()" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all">All Grades</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="S">S</option>
                <option value="F">F</option>
                <option value="AB">Absent</option>
                <option value="pending">Results Pending</option>
            </select>
        </div>

        <!-- Student List -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="studentsTable">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase font-semibold text-gray-500">
                            <th class="px-6 py-4">Student Name</th>
                            <th class="px-6 py-4">User ID</th>
                            <th class="px-6 py-4">Response Status</th>
                            <th class="px-6 py-4">Result</th>
                            <th class="px-6 py-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($students as $student): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors student-row" data-grade="<?php echo $student['subject_grade'] ?? 'pending'; ?>">
                            <td class="px-6 py-4 font-medium text-gray-900">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['second_name']); ?>
                                <?php if (!empty($student['whatsapp_number'])): ?>
                                    <br><span class="text-xs text-gray-400"><i class="fab fa-whatsapp text-green-500"></i> <?php echo htmlspecialchars($student['whatsapp_number']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($student['user_id']); ?></td>
                            <td class="px-6 py-4">
                                <?php if (!empty($student['submission_id'])): ?>
                                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold bg-green-100 text-green-600 border border-green-200 shadow-sm">Responded</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold bg-red-50 text-red-600 border border-red-100 shadow-sm">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-700">
                                <?php 
                                    if (isset($student['subject_grade'])) {
                                        $badges = [
                                            'A' => 'bg-green-100 text-green-700',
                                            'B' => 'bg-blue-100 text-blue-700',
                                            'C' => 'bg-yellow-100 text-yellow-700',
                                            'S' => 'bg-gray-100 text-gray-700',
                                            'F' => 'bg-red-100 text-red-700',
                                            'AB' => 'bg-gray-200 text-gray-500'
                                        ];
                                        $cls = $badges[$student['subject_grade']] ?? 'bg-gray-100';
                                        echo "<span class='px-3 py-1 rounded-full text-xs $cls'>{$student['subject_grade']}</span>";
                                    } else {
                                        echo '<span class="text-gray-400 text-xs italic">Waiting</span>';
                                    }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!empty($student['submission_id'])): ?>
                                    <button onclick='viewDetails(<?php echo json_encode($student); ?>)' 
                                            class="text-gray-400 hover:text-blue-600 transition-colors p-2 rounded-full hover:bg-blue-50">
                                        <i class="fas fa-eye text-lg"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-200">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                <i class="fas fa-users-slash text-4xl mb-3"></i>
                                <p>No students found for this subject.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all scale-100">
            <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 flex justify-between items-center text-white">
                <h3 class="font-bold text-lg">Student Result Card</h3>
                <button onclick="closeModal()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="p-6">
                <div class="flex items-center gap-4 mb-6">
                    <img id="modalPhoto" src="" alt="Student Photo" class="w-20 h-20 rounded-xl object-cover border-2 border-gray-100 shadow-sm bg-gray-50">
                    <div>
                        <h4 id="modalName" class="font-bold text-gray-900 text-lg">Student Name</h4>
                        <p id="modalId" class="text-gray-500 text-sm">User ID</p>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                            <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider block mb-1">Index Number</span>
                            <p id="modalIndex" class="font-medium text-gray-900">Waiting...</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                            <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider block mb-1">District</span>
                            <p id="modalDistrict" class="font-medium text-gray-900">Waiting...</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider block mb-1">Results</span>
                        <table class="w-full text-sm">
                            <tr><td id="modalSub1" class="py-1">Sub 1</td><td id="modalRes1" class="font-bold text-right">?</td></tr>
                            <tr><td id="modalSub2" class="py-1">Sub 2</td><td id="modalRes2" class="font-bold text-right">?</td></tr>
                            <tr><td id="modalSub3" class="py-1">Sub 3</td><td id="modalRes3" class="font-bold text-right">?</td></tr>
                        </table>
                    </div>
                </div>

                <div class="mt-6 text-right">
                    <button onclick="closeModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-colors">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewDetails(data) {
            document.getElementById('modalName').textContent = data.first_name + ' ' + data.second_name;
            document.getElementById('modalId').textContent = data.user_id;
            
            document.getElementById('modalIndex').textContent = data.index_number || 'Not provided';
            document.getElementById('modalDistrict').textContent = data.district;
            
            document.getElementById('modalSub1').textContent = data.subject_1;
            document.getElementById('modalSub2').textContent = data.subject_2;
            document.getElementById('modalSub3').textContent = data.subject_3;
            
            document.getElementById('modalRes1').textContent = data.result_1 || '-';
            document.getElementById('modalRes2').textContent = data.result_2 || '-';
            document.getElementById('modalRes3').textContent = data.result_3 || '-';
            
            const photoEl = document.getElementById('modalPhoto');
            if (data.photo_path) {
                photoEl.src = '../' + data.photo_path;
            } else {
                photoEl.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.first_name) + '&background=random';
            }
            
            document.getElementById('detailsModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
        
        // Close on outside click
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        function filterTable() {
            var input, filter, table, tr, td, i;
            input = document.getElementById("gradeFilter");
            filter = input.value;
            table = document.getElementById("studentsTable");
            tr = table.getElementsByTagName("tr");
            
            for (i = 1; i < tr.length; i++) {
                var grade = tr[i].getAttribute('data-grade');
                if (filter === "all") {
                    tr[i].style.display = "";
                } else if (filter === "pending") {
                    if (grade === "pending" || grade === "") {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                } else {
                    if (grade === filter) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    </script>

</body>
</html>
