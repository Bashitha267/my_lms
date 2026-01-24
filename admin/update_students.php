<?php
require_once '../check_session.php';
require_once '../config.php';

// Ensure admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle Enrollment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_students'])) {
    $selected_students = $_POST['student_ids'] ?? [];
    $target_stream_id = intval($_POST['target_stream_id']);
    $target_subject_id = intval($_POST['target_subject_id'] ?? 0); // If selecting existing
    $new_subject_name = trim($_POST['new_subject_name'] ?? '');
    $new_subject_code = trim($_POST['new_subject_code'] ?? '');
    $target_year = intval($_POST['target_year']);
    
    // Validate inputs
    if (empty($selected_students)) {
        $error_msg = "No students selected.";
    } elseif ($target_stream_id <= 0) {
        $error_msg = "Please select a target stream.";
    } elseif ($target_year <= 2000 || $target_year > 2100) {
        $error_msg = "Invalid academic year.";
    } else {
        // Handle Subject (Existing vs New)
        if (!empty($new_subject_name)) {
            // Create new subject
            $stmt = $conn->prepare("INSERT INTO subjects (name, code, status) VALUES (?, ?, 1)");
            $stmt->bind_param("ss", $new_subject_name, $new_subject_code);
            if ($stmt->execute()) {
                $target_subject_id = $conn->insert_id;
            } else {
                $error_msg = "Error creating subject: " . $conn->error;
            }
            $stmt->close();
        } elseif ($target_subject_id <= 0) {
            $error_msg = "Please select a subject or create a new one.";
        }

        if (empty($error_msg)) {
            // Check/Create stream_subject combination
            $ss_id = 0;
            $check_ss = $conn->prepare("SELECT id FROM stream_subjects WHERE stream_id = ? AND subject_id = ?");
            $check_ss->bind_param("ii", $target_stream_id, $target_subject_id);
            $check_ss->execute();
            $res = $check_ss->get_result();
            if ($row = $res->fetch_assoc()) {
                $ss_id = $row['id'];
            } else {
                // Create new link
                $ins_ss = $conn->prepare("INSERT INTO stream_subjects (stream_id, subject_id, status) VALUES (?, ?, 1)");
                $ins_ss->bind_param("ii", $target_stream_id, $target_subject_id);
                if ($ins_ss->execute()) {
                    $ss_id = $conn->insert_id;
                } else {
                    $error_msg = "Error linking stream and subject.";
                }
                $ins_ss->close();
            }
            $check_ss->close();

        if ($ss_id > 0) {
                // Enrollment Logic
                $success_count = 0;
                $enroll_stmt = $conn->prepare("INSERT IGNORE INTO student_enrollment (student_id, stream_subject_id, academic_year, enrolled_date, status, payment_status) VALUES (?, ?, ?, CURDATE(), 'active', 'paid')");
                $pay_stmt = $conn->prepare("INSERT INTO enrollment_payments (student_enrollment_id, amount, payment_method, payment_status, payment_date) VALUES (?, 0, 'system_transfer', 'paid', NOW())");
                
                foreach ($selected_students as $std_id) {
                    $enroll_stmt->bind_param("sis", $std_id, $ss_id, $target_year);
                    if ($enroll_stmt->execute() && $enroll_stmt->affected_rows > 0) {
                        $new_enrollment_id = $conn->insert_id;
                        $pay_stmt->bind_param("i", $new_enrollment_id);
                        $pay_stmt->execute();
                        $success_count++;
                    }
                }
                $enroll_stmt->close();
                $pay_stmt->close();
                
                // Assign User's Teacher to this new class
                $teacher_id = $_POST['teacher_id'] ?? '';
                if (!empty($teacher_id)) {
                    // Check if assignment exists
                    $check_ta = $conn->prepare("SELECT id FROM teacher_assignments WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ?");
                    $check_ta->bind_param("sii", $teacher_id, $ss_id, $target_year);
                    $check_ta->execute();
                    if ($check_ta->get_result()->num_rows == 0) {
                        $ins_ta = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, status) VALUES (?, ?, ?, 'active')");
                        $ins_ta->bind_param("sii", $teacher_id, $ss_id, $target_year);
                        $ins_ta->execute();
                        $ins_ta->close();
                    }
                    $check_ta->close();
                }

                $success_msg = "Successfully enrolled $success_count students and assigned teacher to the new class.";
            }
        }
    }
}

// Data Fetching Helpers
$teachers = [];
if (!isset($_GET['teacher_id'])) {
    $res = $conn->query("SELECT user_id, first_name, second_name, profile_picture FROM users WHERE role = 'teacher' AND status = 1 ORDER BY first_name");
    while ($row = $res->fetch_assoc()) $teachers[] = $row;
}

$classes = [];
$teacher_details = null;
if (isset($_GET['teacher_id'])) {
    // Get Teacher Name
    $tid = $_GET['teacher_id'];
    $stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $tid);
    $stmt->execute();
    $teacher_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get Classes filtered by Year
    $filter_year = $_GET['year'] ?? date('Y');
    $stmt = $conn->prepare("
        SELECT ta.id, ta.academic_year, ta.stream_subject_id, s.name as stream_name, sub.name as subject_name, sub.code as subject_code 
        FROM teacher_assignments ta
        JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
        JOIN streams s ON ss.stream_id = s.id
        JOIN subjects sub ON ss.subject_id = sub.id
        WHERE ta.teacher_id = ? AND ta.academic_year = ? AND ta.status = 'active'
    ");
    $stmt->bind_param("si", $tid, $filter_year);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $classes[] = $row;
    $stmt->close();
}

$students = [];
$current_class = null;
if (isset($_GET['stream_subject_id'])) {
    // Get Class Details
    $class_id = $_GET['stream_subject_id'] ?? 0; // Using stream_subject_id passed via GET
    // Actually GET stores stream_subject_id from the class selection
    // Let's ensure proper ID usage. In the class list loop, we'll link stream_subject_id.
    
    $ssid = intval($_GET['stream_subject_id']);
    $year = intval($_GET['year']);
    
    // Fetch students
    // Check for payment in Last Month OR Current Month
    $last_month = date('n', strtotime('-1 month'));
    $last_month_year = date('Y', strtotime('-1 month'));
    
    $curr_month = date('n');
    $curr_year = date('Y');
    
    $stmt = $conn->prepare("
        SELECT se.student_id, u.first_name, u.second_name, u.profile_picture,
        (SELECT payment_status FROM monthly_payments mp 
         WHERE mp.student_enrollment_id = se.id 
         AND mp.payment_status = 'paid'
         AND ( (mp.month = ? AND mp.year = ?) OR (mp.month = ? AND mp.year = ?) )
         LIMIT 1
        ) as recent_payment_status
        FROM student_enrollment se
        JOIN users u ON se.student_id = u.user_id
        WHERE se.stream_subject_id = ? AND se.academic_year = ? AND se.status = 'active'
        ORDER BY u.first_name
    ");
    // Params: last_m, last_y, curr_m, curr_y, ssid, year
    $stmt->bind_param("iiiiis", $last_month, $last_month_year, $curr_month, $curr_year, $ssid, $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
    
    // Get current class name for display
    $stmt = $conn->prepare("SELECT s.name as sname, sub.name as subname FROM stream_subjects ss JOIN streams s ON ss.stream_id = s.id JOIN subjects sub ON ss.subject_id = sub.id WHERE ss.id = ?");
    $stmt->bind_param("i", $ssid);
    $stmt->execute();
    $current_class = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch Streams for dropdown
$streams_list = $conn->query("SELECT id, name FROM streams WHERE status = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
// Fetch Subjects for dropdown
$subjects_list = $conn->query("SELECT id, name FROM subjects WHERE status = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Students | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumbs / Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Update Students Workflow</h1>
            <nav class="flex mt-2 text-sm text-gray-500 space-x-2">
                <a href="update_students.php" class="hover:text-red-600">Teachers</a>
                <?php if(isset($_GET['teacher_id'])): ?>
                    <span>/</span>
                    <a href="?teacher_id=<?php echo $_GET['teacher_id']; ?>" class="hover:text-red-600"><?php echo htmlspecialchars($teacher_details['first_name'] . ' ' . $teacher_details['second_name']); ?></a>
                <?php endif; ?>
                <?php if(isset($_GET['stream_subject_id']) && $current_class): ?>
                    <span>/</span>
                    <span class="text-red-600"><?php echo htmlspecialchars($current_class['sname'] . ' - ' . $current_class['subname']); ?></span>
                <?php endif; ?>
            </nav>
        </div>

        <?php if($success_msg): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- STEP 1: Select Teacher -->
        <?php if(!isset($_GET['teacher_id'])): ?>
            <div class="bg-white rounded-xl shadow-sm border border-red-500 overflow-hidden">
                <div class="bg-red-50 px-6 py-4 border-b border-red-100">
                    <h2 class="text-xl font-semibold text-gray-800">Select a Teacher</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php foreach($teachers as $t): ?>
                            <a href="?teacher_id=<?php echo $t['user_id']; ?>" class="group block bg-white border border-gray-200 rounded-lg p-4 hover:border-red-500 hover:shadow-lg transition-all text-center">
                                <div class="w-24 h-24 mx-auto mb-3 rounded-full overflow-hidden bg-gray-100">
                                    <?php if($t['profile_picture']): ?>
                                        <img src="../<?php echo $t['profile_picture']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400">No Img</div>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-red-600"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['second_name']); ?></h3>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- STEP 2: Select Class -->
        <?php if(isset($_GET['teacher_id']) && !isset($_GET['stream_subject_id'])): ?>
            <div class="bg-white rounded-xl shadow-sm border border-red-500 overflow-hidden">
                <div class="bg-red-50 px-6 py-4 border-b border-red-100 flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800">Select Class</h2>
                    <form method="GET" class="flex items-center space-x-2">
                        <input type="hidden" name="teacher_id" value="<?php echo $_GET['teacher_id']; ?>">
                        <label class="text-sm font-medium text-gray-600">Academic Year:</label>
                        <select name="year" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 text-sm">
                            <?php 
                            $curr = date('Y');
                            $sel_year = $_GET['year'] ?? $curr;
                            for($y=$curr-2; $y<=$curr+1; $y++) {
                                echo "<option value='$y' " . ($y == $sel_year ? 'selected' : '') . ">$y</option>";
                            }
                            ?>
                        </select>
                    </form>
                </div>

                <div class="p-6">
                    <?php if(empty($classes)): ?>
                        <p class="text-gray-500 text-center py-10">No classes found for this year.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <?php foreach($classes as $c): ?>
                                <a href="?teacher_id=<?php echo $_GET['teacher_id']; ?>&year=<?php echo $sel_year; ?>&stream_subject_id=<?php echo $c['stream_subject_id']; ?>" class="block bg-white border border-gray-200 rounded-lg p-6 hover:border-red-500 hover:shadow-lg transition-all relative overflow-hidden group">
                                    <div class="absolute top-0 left-0 w-1 h-full bg-red-500"></div>
                                    <h3 class="text-xl font-bold text-gray-900 group-hover:text-red-600"><?php echo htmlspecialchars($c['subject_name']); ?></h3>
                                    <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($c['stream_name']); ?></p>
                                    <span class="inline-block mt-3 px-2 py-1 bg-gray-100 text-xs font-semibold rounded text-gray-600"><?php echo $c['academic_year']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- STEP 3: Manage Students -->
        <?php if(isset($_GET['stream_subject_id'])): ?>
            <form method="POST" action="">
                <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($_GET['teacher_id']); ?>">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Student List -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-red-500 flex flex-col overflow-hidden">
                        <div class="bg-red-50 p-6 border-b border-red-100 flex justify-between items-center">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Students List</h2>
                                <p class="text-sm text-gray-500">Select students to enroll in the new class.</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <label class="flex items-center space-x-2 text-sm text-gray-600 cursor-pointer select-none">
                                    <input type="checkbox" onclick="toggleAll(this)" class="rounded text-red-600 focus:ring-red-500">
                                    <span>Select All</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto max-h-[600px] p-2">
                            <?php if(empty($students)): ?>
                                <p class="text-center text-gray-500 py-10">No students enrolled in this class.</p>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <?php foreach($students as $s): 
                                        $paid = ($s['recent_payment_status'] === 'paid');
                                    ?>
                                        <label class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 rounded-lg border cursor-pointer transition-colors <?php echo $paid ? 'bg-green-50 border-green-200 hover:bg-green-100' : 'bg-white border-gray-200 hover:bg-gray-50'; ?>">
                                            <div class="flex items-center space-x-4 mb-2 sm:mb-0 w-full sm:w-auto">
                                                <input type="checkbox" name="student_ids[]" value="<?php echo $s['student_id']; ?>" class="rounded text-red-600 focus:ring-red-500 w-5 h-5 student-checkbox flex-shrink-0">
                                                <div class="w-10 h-10 rounded-full bg-gray-200 overflow-hidden flex-shrink-0">
                                                    <?php if($s['profile_picture']): ?>
                                                        <img src="../<?php echo $s['profile_picture']; ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <div class="flex items-center justify-center h-full w-full text-xs text-gray-500"><?php echo substr($s['first_name'], 0, 1); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0 flex-1 sm:flex-initial">
                                                    <h4 class="font-medium text-gray-900 truncate pr-2"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['second_name']); ?></h4>
                                                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($s['student_id']); ?></p>
                                                </div>
                                            </div>
                                            <div class="text-left sm:text-right pl-9 sm:pl-0">
                                                <?php if($paid): ?>
                                                    <span class="inline-block px-2 py-1 bg-green-200 text-green-800 text-xs rounded-full font-bold">Paid Recent</span>
                                                <?php else: ?>
                                                    <span class="inline-block px-2 py-1 bg-gray-100 text-gray-500 text-xs rounded-full">Not Paid</span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Enrollment Actions -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-xl shadow-sm border border-red-500 sticky top-6 overflow-hidden">
                            <div class="bg-red-50 p-6 border-b border-red-100">
                                <h3 class="text-lg font-bold text-gray-900">Enroll to New Class</h3>
                            </div>
                            
                            <div class="p-6 space-y-4">
                                <!-- Target Year -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                    <input type="number" name="target_year" value="<?php echo date('Y') + 1; ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500">
                                </div>

                                <!-- Target Stream -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Target Stream (Grade)</label>
                                    <select name="target_stream_id" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500">
                                        <option value="">Select Stream</option>
                                        <?php foreach($streams_list as $sl): ?>
                                            <option value="<?php echo $sl['id']; ?>"><?php echo htmlspecialchars($sl['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Target Subject -->
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="block text-sm font-medium text-gray-700">Target Subject</label>
                                        <button type="button" onclick="toggleNewSubject()" class="text-xs text-red-600 hover:text-red-800 font-semibold underline">Create New</button>
                                    </div>
                                    
                                    <div id="existingSubject">
                                        <select name="target_subject_id" id="subjectSelect" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500">
                                            <option value="">Select Subject</option>
                                            <?php foreach($subjects_list as $sub): ?>
                                                <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div id="newSubject" class="hidden space-y-2 bg-gray-50 p-3 rounded-md border border-gray-200">
                                        <input type="text" name="new_subject_name" placeholder="Subject Name" class="w-full border-gray-300 rounded-md text-sm text-gray-800">
                                        <input type="text" name="new_subject_code" placeholder="Subject Code (Optional)" class="w-full border-gray-300 rounded-md text-sm text-gray-800">
                                        <button type="button" onclick="toggleNewSubject()" class="text-xs text-gray-500 underline">Cancel</button>
                                    </div>
                                </div>

                                <button type="submit" name="enroll_students" class="w-full bg-red-600 text-white py-3 rounded-lg font-bold hover:bg-red-700 shadow-md transition-colors mt-6">
                                    Enroll Selected Students
                                </button>
                                
                                <p class="text-xs text-gray-400 mt-2 text-center">This will enroll the selected students into the new class structure.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>

    </div>

    <script>
        function toggleAll(source) {
            checkboxes = document.querySelectorAll('.student-checkbox');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        function toggleNewSubject() {
            const existing = document.getElementById('existingSubject');
            const newSub = document.getElementById('newSubject');
            const select = document.getElementById('subjectSelect');

            if (existing.classList.contains('hidden')) {
                existing.classList.remove('hidden');
                newSub.classList.add('hidden');
                select.value = ""; // Reset
            } else {
                existing.classList.add('hidden');
                newSub.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>
