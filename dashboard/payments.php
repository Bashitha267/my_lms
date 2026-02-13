<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');
$current_month = date('n');

// Handle teacher fee setting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_fees']) && $role === 'teacher') {
    $teacher_assignment_id = intval($_POST['teacher_assignment_id'] ?? 0);
    $enrollment_fee = floatval($_POST['enrollment_fee'] ?? 0);
    $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
    
    if ($teacher_assignment_id > 0) {
        // Check if teacher owns this assignment
        $check_query = "SELECT id FROM teacher_assignments WHERE id = ? AND teacher_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("is", $teacher_assignment_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Insert or update fees
            $upsert_query = "INSERT INTO enrollment_fees (teacher_assignment_id, enrollment_fee, monthly_fee) 
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE enrollment_fee = ?, monthly_fee = ?";
            $upsert_stmt = $conn->prepare($upsert_query);
            $upsert_stmt->bind_param("idddd", $teacher_assignment_id, $enrollment_fee, $monthly_fee, $enrollment_fee, $monthly_fee);
            
            if ($upsert_stmt->execute()) {
                $success_message = 'Fees updated successfully!';
            } else {
                $error_message = 'Error updating fees: ' . $conn->error;
            }
            $upsert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get data based on role
$enrollments = [];
$teacher_assignments = [];
$payment_history = [];
$class_payments = [];
$course_payments = [];
$fee_settings = [];

if ($role === 'student') {
    // Get student enrollments with payment status
    $query = "SELECT se.*, s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                     ta.id as teacher_assignment_id,
                     (SELECT COUNT(*) FROM enrollment_payments ep WHERE ep.student_enrollment_id = se.id AND ep.payment_status = 'paid') as enrollment_paid,
                     (SELECT COUNT(*) FROM monthly_payments mp WHERE mp.student_enrollment_id = se.id AND mp.payment_status = 'paid') as monthly_paid_count
              FROM student_enrollment se
              INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id 
                AND ta.academic_year = se.academic_year 
                AND ta.status = 'active'
              WHERE se.student_id = ? AND se.status = 'active'
              ORDER BY se.academic_year DESC, s.name, sub.name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get fee info
        $fee_info = null;
        if ($row['teacher_assignment_id']) {
            $fee_query = "SELECT enrollment_fee, monthly_fee FROM enrollment_fees WHERE teacher_assignment_id = ?";
            $fee_stmt = $conn->prepare($fee_query);
            $fee_stmt->bind_param("i", $row['teacher_assignment_id']);
            $fee_stmt->execute();
            $fee_result = $fee_stmt->get_result();
            if ($fee_result->num_rows > 0) {
                $fee_info = $fee_result->fetch_assoc();
            }
            $fee_stmt->close();
        }
        $row['fee_info'] = $fee_info;
        
        // Check enrollment payment status (pending or paid)
        $enroll_payment_query = "SELECT payment_status FROM enrollment_payments 
                                WHERE student_enrollment_id = ? 
                                ORDER BY created_at DESC LIMIT 1";
        $enroll_payment_stmt = $conn->prepare($enroll_payment_query);
        $enroll_payment_stmt->bind_param("i", $row['id']);
        $enroll_payment_stmt->execute();
        $enroll_payment_result = $enroll_payment_stmt->get_result();
        $enroll_payment_status = null;
        if ($enroll_payment_result->num_rows > 0) {
            $enroll_payment_row = $enroll_payment_result->fetch_assoc();
            $enroll_payment_status = $enroll_payment_row['payment_status'];
        }
        $enroll_payment_stmt->close();
        $row['enroll_payment_status'] = $enroll_payment_status;
        
        // Check monthly payment status for current month
        $monthly_payment_query = "SELECT payment_status FROM monthly_payments 
                                 WHERE student_enrollment_id = ? 
                                   AND month = ? AND year = ?
                                 ORDER BY created_at DESC LIMIT 1";
        $monthly_payment_stmt = $conn->prepare($monthly_payment_query);
        $monthly_payment_stmt->bind_param("iii", $row['id'], $current_month, $current_year);
        $monthly_payment_stmt->execute();
        $monthly_payment_result = $monthly_payment_stmt->get_result();
        $monthly_payment_status = null;
        if ($monthly_payment_result->num_rows > 0) {
            $monthly_payment_row = $monthly_payment_result->fetch_assoc();
            $monthly_payment_status = $monthly_payment_row['payment_status'];
        }
        $monthly_payment_stmt->close();
        $row['monthly_payment_status'] = $monthly_payment_status;
        
        $enrollments[] = $row;
    }
    $stmt->close();
    
    // Get Class Payments (Enrollment + Monthly)
    $class_payments = [];
    $class_query = "SELECT 'enrollment' as type, id, amount, payment_method, payment_status, payment_date, receipt_path, receipt_type, created_at, NULL as period
                      FROM enrollment_payments
                      WHERE student_enrollment_id IN (SELECT id FROM student_enrollment WHERE student_id = ?)
                      UNION ALL
                      SELECT 'monthly' as type, id, amount, payment_method, payment_status, payment_date, receipt_path, receipt_type, created_at,
                             CONCAT(month, '/', year) as period
                      FROM monthly_payments
                      WHERE student_enrollment_id IN (SELECT id FROM student_enrollment WHERE student_id = ?)
                      ORDER BY created_at DESC LIMIT 50";
    
    $c_stmt = $conn->prepare($class_query);
    if ($c_stmt) {
        $c_stmt->bind_param("ss", $user_id, $user_id);
        $c_stmt->execute();
        $c_res = $c_stmt->get_result();
        while ($row = $c_res->fetch_assoc()) {
            $class_payments[] = $row;
        }
        $c_stmt->close();
    } else {
        // Fallback or error logging if needed
        // $error_message = "Error fetching class payments: " . $conn->error;
    }

    // Get Course Payments
    $course_payments = [];
    $cp_query = "SELECT 'course' as type, cp.id, cp.amount, cp.payment_method, cp.payment_status, cp.verified_at as payment_date, cp.receipt_path, cp.receipt_type, cp.created_at, c.title as period
                      FROM course_payments cp
                      JOIN course_enrollments ce ON cp.course_enrollment_id = ce.id
                      JOIN courses c ON ce.course_id = c.id
                      WHERE ce.student_id = ?
                      ORDER BY cp.created_at DESC LIMIT 50";

    $cp_stmt = $conn->prepare($cp_query);
    if ($cp_stmt) {
        $cp_stmt->bind_param("s", $user_id);
        $cp_stmt->execute();
        $cp_res = $cp_stmt->get_result();
        while ($row = $cp_res->fetch_assoc()) {
            $course_payments[] = $row;
        }
        $cp_stmt->close();
    } else {
        // $error_message = "Error fetching course payments: " . $conn->error;
    }
    
} elseif ($role === 'teacher') {
    // Get teacher assignments
    $query = "SELECT ta.*, s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                     (SELECT COUNT(*) FROM student_enrollment se WHERE se.stream_subject_id = ta.stream_subject_id AND se.academic_year = ta.academic_year AND se.status = 'active') as student_count
              FROM teacher_assignments ta
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              WHERE ta.teacher_id = ? AND ta.status = 'active'
              ORDER BY ta.academic_year DESC, s.name, sub.name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get fee settings
        $fee_query = "SELECT enrollment_fee, monthly_fee FROM enrollment_fees WHERE teacher_assignment_id = ?";
        $fee_stmt = $conn->prepare($fee_query);
        $fee_stmt->bind_param("i", $row['id']);
        $fee_stmt->execute();
        $fee_result = $fee_stmt->get_result();
        $fee_info = null;
        if ($fee_result->num_rows > 0) {
            $fee_info = $fee_result->fetch_assoc();
        }
        $fee_stmt->close();
        $row['fee_info'] = $fee_info;
        $teacher_assignments[] = $row;
    }
    $stmt->close();

    // Get teacher courses
    $teacher_courses = [];
    $stmt = $conn->prepare("SELECT * FROM courses WHERE teacher_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) {
        $teacher_courses[] = $row;
    }
    $stmt->close();

    // Get teacher wallet balance
    $teacher_points = 0;
    $teacher_earnings = 0;
    $wallet_query = "SELECT total_points, total_earned FROM teacher_wallet WHERE teacher_id = ?";
    $wallet_stmt = $conn->prepare($wallet_query);
    if ($wallet_stmt) {
        $wallet_stmt->bind_param("s", $user_id);
        $wallet_stmt->execute();
        $wallet_result = $wallet_stmt->get_result();
        if ($wallet_result->num_rows > 0) {
            $wallet_row = $wallet_result->fetch_assoc();
            $teacher_points = $wallet_row['total_points'];
            $teacher_earnings = $wallet_row['total_earned'];
        }
        $wallet_stmt->close();
    }

    // Get recent transactions
    $transactions = [];
    $trans_query = "SELECT pt.*, u.first_name, u.second_name 
                    FROM payment_transactions pt
                    JOIN users u ON pt.student_id = u.user_id
                    WHERE pt.teacher_id = ?
                    ORDER BY pt.created_at DESC
                    LIMIT 50";
    $trans_stmt = $conn->prepare($trans_query);
    if ($trans_stmt) {
        $trans_stmt->bind_param("s", $user_id);
        $trans_stmt->execute();
        $trans_result = $trans_stmt->get_result();
        while ($row = $trans_result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $trans_stmt->close();
    }


    // Handle Assignment Selection (Teacher View)
    $selected_assignment = null;
    $enrolled_students = [];
    $filter_year = $_GET['year'] ?? date('Y');
    $filter_month = $_GET['month'] ?? date('n');
    $filter_status = $_GET['status'] ?? 'all';

    if (isset($_GET['assignment_id'])) {
        $assignment_id = intval($_GET['assignment_id']);
        foreach ($teacher_assignments as $ta) {
            if ($ta['id'] === $assignment_id) {
                $selected_assignment = $ta;
                break;
            }
        }

        if ($selected_assignment) {
            // Fetch enrolled students and their payment status
            $students_query = "
                SELECT 
                    se.id as enrollment_id,
                    u.user_id,
                    u.first_name,
                    u.second_name,
                    mp.payment_status as monthly_status,
                    mp.id as payment_id
                FROM student_enrollment se
                JOIN users u ON se.student_id = u.user_id
                LEFT JOIN monthly_payments mp ON mp.student_enrollment_id = se.id 
                    AND mp.month = ? AND mp.year = ?
                WHERE se.stream_subject_id = ? 
                AND se.academic_year = ?
                AND se.status = 'active'
            ";

            if ($filter_status === 'paid') {
                $students_query .= " AND mp.payment_status = 'paid'";
            } elseif ($filter_status === 'not_paid') {
                $students_query .= " AND (mp.payment_status IS NULL OR mp.payment_status != 'paid')";
            }

            $students_query .= " ORDER BY u.first_name, u.user_id";

            $stmt = $conn->prepare($students_query);
            $stmt->bind_param("iiis", $filter_month, $filter_year, $selected_assignment['stream_subject_id'], $selected_assignment['academic_year']);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) {
                $enrolled_students[] = $row;
            }
            $stmt->close();
        }
    }

    // Handle Course Selection
    $selected_course = null;
    $course_students = [];
    
    if (isset($_GET['course_id']) && !isset($_GET['assignment_id'])) {
        $course_id = intval($_GET['course_id']);
        foreach ($teacher_courses as $c) {
            if ($c['id'] === $course_id) {
                $selected_course = $c;
                break;
            }
        }
        
        if ($selected_course) {
            $c_query = "SELECT ce.*, cp.verified_at as payment_date
                        FROM course_enrollments ce
                        LEFT JOIN course_payments cp ON cp.course_enrollment_id = ce.id AND cp.payment_status = 'paid'
                        WHERE ce.course_id = ?";
            
            if ($filter_status === 'paid') {
                $c_query .= " AND ce.payment_status = 'paid'";
            } elseif ($filter_status === 'not_paid') {
                $c_query .= " AND ce.payment_status != 'paid'";
            }
            
            // Removed ORDER BY u.first_name since we are not joining users
            
            $stmt = $conn->prepare($c_query);
            $stmt->bind_param("i", $selected_course['id']);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $temp_students = [];
            while($row = $res->fetch_assoc()) {
                $temp_students[] = $row;
            }
            $stmt->close();

            // Fetch user info individually
            foreach ($temp_students as $row) {
                $u_stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
                $u_stmt->bind_param("s", $row['student_id']);
                $u_stmt->execute();
                $u_res = $u_stmt->get_result();
                if ($u = $u_res->fetch_assoc()) {
                    $row['first_name'] = $u['first_name'];
                    $row['second_name'] = $u['second_name'];
                } else {
                    $row['first_name'] = 'Unknown';
                    $row['second_name'] = '';
                }
                $u_stmt->close();
                
                $row['user_id'] = $row['student_id'];
                $course_students[] = $row;
            }

            // Sort by name
            usort($course_students, function($a, $b) {
                return strcasecmp($a['first_name'] . $a['second_name'], $b['first_name'] . $b['second_name']);
            });
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .premium-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .premium-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="premium-card rounded-3xl p-8 mb-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-red-50 rounded-full -mr-32 -mt-32 opacity-50"></div>
                <div class="relative z-10">
                    <h1 class="text-3xl font-black text-gray-900 mb-2">Payments & Fees</h1>
                    <p class="text-gray-500 font-medium">Manage your active enrollments and transaction history</p>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message) && !empty($success_message)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
                    <p class="text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message) && !empty($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
                    <p class="text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($role === 'student'): ?>
                <!-- Student View -->
                <div class="space-y-6">
                    <!-- Enrollments with Payment Status -->
                    <div class="mb-10">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-black text-gray-900 flex items-center gap-3">
                                <span class="w-1.5 h-8 bg-red-600 rounded-full"></span>
                                My Enrollments
                            </h2>
                        </div>
                        
                        <?php if (empty($enrollments)): ?>
                            <div class="premium-card rounded-2xl p-12 text-center text-gray-400">
                                <i class="fas fa-folder-open text-4xl mb-4 text-gray-200"></i>
                                <p class="font-medium text-lg">No active enrollments found.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <div class="premium-card rounded-2xl overflow-hidden border-t-4 border-red-600 flex flex-col">
                                        <div class="p-6 flex-1">
                                                <div class="flex items-start justify-between mb-4">
                                                    <div>
                                                        <h3 class="text-xl font-bold text-gray-900 group-hover:text-red-600 transition-colors"><?php echo htmlspecialchars($enrollment['subject_name']); ?></h3>
                                                        <div class="flex items-center mt-1 space-x-2">
                                                            <span class="text-xs font-semibold px-2 py-0.5 bg-gray-100 text-gray-600 rounded"><?php echo htmlspecialchars($enrollment['stream_name']); ?></span>
                                                            <span class="text-xs font-semibold px-2 py-0.5 bg-red-50 text-red-600 rounded"><?php echo htmlspecialchars($enrollment['academic_year']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="p-2 bg-red-50 rounded-lg">
                                                        <i class="fas fa-book-open text-red-600"></i>
                                                    </div>
                                                </div>

                                            <div class="flex flex-col gap-3 mb-6">
                                                <!-- Enrollment Status -->
                                                <?php $enroll_status = $enrollment['enroll_payment_status'] ?? null; ?>
                                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <div class="w-2 h-2 rounded-full mr-3 <?php echo $enroll_status === 'paid' ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                                                        <span class="text-[11px] text-gray-400 uppercase font-black tracking-widest">Enrollment Fee</span>
                                                    </div>
                                                    <?php if ($enroll_status === 'paid'): ?>
                                                        <span class="text-xs font-black text-green-600 px-3 py-1 bg-green-50 rounded-lg">PAID</span>
                                                    <?php elseif ($enroll_status === 'pending'): ?>
                                                        <span class="text-xs font-black text-yellow-600 px-3 py-1 bg-yellow-50 rounded-lg">PENDING</span>
                                                    <?php else: ?>
                                                        <span class="text-xs font-black text-red-600 px-3 py-1 bg-red-50 rounded-lg">UNPAID</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Monthly Status -->
                                                <?php $monthly_status = $enrollment['monthly_payment_status'] ?? null; ?>
                                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <div class="w-2 h-2 rounded-full mr-3 <?php echo $monthly_status === 'paid' ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                                                        <span class="text-[11px] text-gray-400 uppercase font-black tracking-widest"><?php echo strtoupper(date('F')); ?> FEE</span>
                                                    </div>
                                                    <?php if ($monthly_status === 'paid'): ?>
                                                        <span class="text-xs font-black text-green-600 px-3 py-1 bg-green-50 rounded-lg">PAID</span>
                                                    <?php elseif ($monthly_status === 'pending'): ?>
                                                        <span class="text-xs font-black text-yellow-600 px-3 py-1 bg-yellow-50 rounded-lg">PENDING</span>
                                                    <?php else: ?>
                                                        <span class="text-xs font-black text-red-600 px-3 py-1 bg-red-50 rounded-lg">UNPAID</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                                <div class="flex flex-col space-y-2">
                                                    <?php if (($enroll_status === null || $enroll_status === 'failed') && ($enrollment['fee_info']['enrollment_fee'] ?? 0) > 0): ?>
                                                        <a href="payments_form.php?enrollment_id=<?php echo $enrollment['id']; ?>&type=enrollment" 
                                                           class="w-full py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 transition font-bold text-center text-sm">
                                                            Pay Enrollment (Rs. <?php echo number_format($enrollment['fee_info']['enrollment_fee'], 0); ?>)
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (($monthly_status === null || $monthly_status === 'failed') && ($enrollment['fee_info']['monthly_fee'] ?? 0) > 0): ?>
                                                        <a href="payments_form.php?enrollment_id=<?php echo $enrollment['id']; ?>&type=monthly&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" 
                                                           class="w-full py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-bold text-center text-sm">
                                                            Pay Monthly (Rs. <?php echo number_format($enrollment['fee_info']['monthly_fee'], 0); ?>)
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (($enroll_status === 'paid' || $enroll_status === 'pending') && ($monthly_status === 'paid' || $monthly_status === 'pending')): ?>
                                                        <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-center gap-2 text-green-600 font-black text-[10px] tracking-widest uppercase">
                                                            <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div>
                                                            Status Up to Date
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Pay for Specific Month Form -->
                                                    <?php if (($enrollment['fee_info']['monthly_fee'] ?? 0) > 0): ?>
                                                        <form action="payments_form.php" method="GET" class="mt-4 pt-4 border-t border-gray-100">
                                                            <input type="hidden" name="type" value="monthly">
                                                            <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                                            
                                                            <div class="flex items-center justify-between mb-2">
                                                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pay for Other Month</label>
                                                            </div>
                                                            
                                                            <div class="flex gap-2">
                                                                <select name="month" class="flex-1 text-xs font-medium text-gray-700 bg-gray-50 border-gray-200 rounded-lg focus:ring-red-500 focus:border-red-500">
                                                                    <?php for($m=1; $m<=12; $m++): ?>
                                                                        <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                                        </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                                
                                                                <select name="year" class="w-20 text-xs font-medium text-gray-700 bg-gray-50 border-gray-200 rounded-lg focus:ring-red-500 focus:border-red-500">
                                                                    <?php 
                                                                    $curr_year = date('Y');
                                                                    for($y=$curr_year-1; $y<=$curr_year+1; $y++): ?>
                                                                        <option value="<?php echo $y; ?>" <?php echo $y == $curr_year ? 'selected' : ''; ?>>
                                                                            <?php echo $y; ?>
                                                                        </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                                
                                                                <button type="submit" class="bg-gray-800 text-white text-xs font-bold px-3 py-2 rounded-lg hover:bg-gray-900 transition shadow-sm">
                                                                    PAY
                                                                </button>
                                                            </div>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Class Payments History -->
                    <?php if (!empty($class_payments)): ?>
                    <div class="mb-10">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-black text-gray-900 flex items-center gap-3">
                                <span class="w-1.5 h-8 bg-blue-600 rounded-full"></span>
                                Transaction History
                            </h2>
                        </div>
                        
                        <div class="premium-card rounded-2xl overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead>
                                        <tr class="bg-gray-50/50">
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Type</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Period</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Amount</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Method</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Date</th>
                                        </tr>
                                    </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($class_payments as $payment): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo ucfirst($payment['type']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo $payment['period'] ?: '-'; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo number_format($payment['amount'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php 
                                                            echo $payment['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                                                ($payment['payment_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                        ?>">
                                                            <?php echo ucfirst($payment['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php 
                                                        $display_date = $payment['payment_date'] ?? $payment['created_at'];
                                                        echo $display_date ? date('M d, Y', strtotime($display_date)) : 'N/A';
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Course Payments History -->
                    <?php if (!empty($course_payments)): ?>
                    <div class="mb-10">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-black text-gray-900 flex items-center gap-3">
                                <span class="w-1.5 h-8 bg-purple-600 rounded-full"></span>
                                Course Subscriptions
                            </h2>
                        </div>
                        
                        <div class="premium-card rounded-2xl overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead>
                                        <tr class="bg-gray-50/50">
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Course</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Amount</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Method</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Date</th>
                                        </tr>
                                    </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($course_payments as $payment): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($payment['period']); // Accessing period alias for Course Title ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo number_format($payment['amount'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php 
                                                            echo $payment['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                                                ($payment['payment_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                        ?>">
                                                            <?php echo ucfirst($payment['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php 
                                                        $display_date = $payment['payment_date'] ?? $payment['created_at'];
                                                        echo $display_date ? date('M d, Y', strtotime($display_date)) : 'N/A';
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($role === 'teacher'): ?>
                <!-- Teacher View -->
                <div class="space-y-6">
                    <!-- Active Points Header (Teacher Only) -->
                    <div class="premium-card rounded-3xl p-8 mb-8 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-green-600 uppercase tracking-wider mb-1">Your Wallet</p>
                                <h2 class="text-4xl font-black text-gray-900 mb-2">
                                    <?php echo number_format($teacher_points, 2); ?> <span class="text-2xl text-gray-500">Points</span>
                                </h2>
                                <p class="text-sm text-gray-600">
                                    Total Earned: <span class="font-bold text-green-700">Rs. <?php echo number_format($teacher_earnings, 2); ?></span>
                                    <span class="text-xs text-gray-400 ml-2">(1 Point = 1 Rs)</span>
                                </p>
                            </div>
                            <div class="p-6 bg-white rounded-2xl shadow-lg">
                                <i class="fas fa-wallet text-5xl text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History Section -->
                    <?php if (!empty($transactions)): ?>
                    <div class="mb-10">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-black text-gray-900 flex items-center gap-3">
                                <span class="w-1.5 h-8 bg-green-600 rounded-full"></span>
                                Point Transactions
                            </h2>
                        </div>
                        
                        <div class="premium-card rounded-2xl overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead>
                                        <tr class="bg-gray-50/50">
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Date</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Student</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Type</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Amount</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Your Share (75%)</th>
                                            <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($transactions as $trans): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo date('M d, Y', strtotime($trans['created_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars(trim(($trans['first_name'] ?? '') . ' ' . ($trans['second_name'] ?? ''))); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $trans['payment_type'] === 'enrollment' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                        <?php echo ucfirst($trans['payment_type']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                    Rs. <?php echo number_format($trans['total_amount'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">
                                                    +<?php echo number_format($trans['teacher_points'], 2); ?> pts
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php 
                                                        echo $trans['transaction_status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                            ($trans['transaction_status'] === 'reversed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                                                    ?>">
                                                        <?php echo ucfirst($trans['transaction_status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($selected_assignment): ?>
                        <!-- Subject Detail View -->
                        <div class="bg-white rounded-lg shadow border border-red-500 overflow-hidden">
                            <div class="p-6 border-b border-red-100 bg-red-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <div class="flex items-center space-x-2 text-sm text-gray-500 mb-1">
                                        <a href="payments.php" class="hover:text-red-600 transition-colors">Subjects</a>
                                        <span>/</span>
                                        <span><?php echo htmlspecialchars($selected_assignment['academic_year']); ?></span>
                                    </div>
                                    <h2 class="text-2xl font-bold text-gray-900">
                                        <?php echo htmlspecialchars($selected_assignment['subject_name']); ?>
                                        <span class="text-gray-500 font-normal">- <?php echo htmlspecialchars($selected_assignment['stream_name']); ?></span>
                                    </h2>
                                </div>
                                <a href="payments.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <svg class="h-5 w-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    Back to Subjects
                                </a>
                            </div>

                            <div class="p-6">
                                <!-- Filters -->
                                <div class="bg-gray-50 rounded-lg p-4 mb-8">
                                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($selected_assignment['id']); ?>">
                                        
                                        <div>
                                            <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                            <select name="year" id="year" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                                                <?php 
                                                $current_y = date('Y');
                                                for($y = $current_y - 1; $y <= $current_y + 1; $y++): ?>
                                                    <option value="<?php echo $y; ?>" <?php echo $filter_year == $y ? 'selected' : ''; ?>>
                                                        <?php echo $y; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                            <select name="month" id="month" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                                                <?php for($m = 1; $m <= 12; $m++): ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $filter_month == $m ? 'selected' : ''; ?>>
                                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                                            <select name="status" id="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Students</option>
                                                <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                                                <option value="not_paid" <?php echo $filter_status == 'not_paid' ? 'selected' : ''; ?>>Not Paid Only</option>
                                            </select>
                                        </div>

                                        <div class="flex items-end">
                                            <button type="submit" class="w-full bg-red-600 border border-transparent rounded-md shadow-sm py-2 px-4 inline-flex justify-center items-center text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                                </svg>
                                                Filter
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Students Grid -->
                                <div class="mb-8">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center justify-between">
                                        <span>Enrolled Students</span>
                                        <span class="text-sm font-normal text-gray-500"><?php echo count($enrolled_students); ?> students</span>
                                    </h3>
                                    
                                    <?php if (empty($enrolled_students)): ?>
                                        <div class="text-center py-12 bg-white rounded-lg border-2 border-dashed border-gray-300">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900">No students found</h3>
                                            <p class="mt-1 text-sm text-gray-500">Try adjusting your filters.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex flex-col max-w-xl gap-4">
                                            <?php foreach ($enrolled_students as $student): 
                                                $is_paid = $student['monthly_status'] === 'paid';
                                            ?>
                                                <div class="relative rounded-lg border p-2 shadow-sm flex items-center space-x-4 <?php echo $is_paid ? 'bg-green-100 border-green-200' : 'bg-red-100 border-red-200'; ?>">
                                                    <div class="flex-shrink-0">
                                                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full <?php echo $is_paid ? 'bg-green-200 text-green-700' : 'bg-red-200 text-red-700'; ?>">
                                                            <span class="text-sm font-bold leading-none">
                                                                <?php echo strtoupper(substr($student['first_name'] ?: ($student['user_id'] ?: '?'), 0, 1)); ?>
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            <?php 
                                                            $name = trim(($student['first_name'] ?? '') . ' ' . ($student['second_name'] ?? ''));
                                                            echo htmlspecialchars($name ?: $student['user_id']); 
                                                            ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 truncate">
                                                            <?php echo htmlspecialchars($student['user_id']); ?>
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <?php if ($is_paid): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                Paid
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                Not Paid
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Fee Settings for this Subject -->
                                <div class="border-t border-gray-200 pt-8">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Fee Configuration</h3>
                                    <div class="bg-blue-50 rounded-lg p-6 border border-blue-100">
                                        <form method="POST" action="" class="flex flex-col md:flex-row gap-4 items-end">
                                            <input type="hidden" name="teacher_assignment_id" value="<?php echo $selected_assignment['id']; ?>">
                                            
                                            <div class="w-full md:w-1/3">
                                                <label for="enrollment_fee" class="block text-sm font-medium text-gray-700 mb-1">Enrollment Fee (LKR)</label>
                                                <div class="relative rounded-md shadow-sm">
                                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm">Rs.</span>
                                                    </div>
                                                    <input type="number" name="enrollment_fee" id="enrollment_fee" step="0.01" min="0"
                                                           value="<?php echo $selected_assignment['fee_info'] ? $selected_assignment['fee_info']['enrollment_fee'] : '0.00'; ?>"
                                                           class="focus:ring-red-500 focus:border-red-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md p-2">
                                                </div>
                                            </div>

                                            <div class="w-full md:w-1/3">
                                                <label for="monthly_fee" class="block text-sm font-medium text-gray-700 mb-1">Monthly Fee (LKR)</label>
                                                <div class="relative rounded-md shadow-sm">
                                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm">Rs.</span>
                                                    </div>
                                                    <input type="number" name="monthly_fee" id="monthly_fee" step="0.01" min="0"
                                                           value="<?php echo $selected_assignment['fee_info'] ? $selected_assignment['fee_info']['monthly_fee'] : '0.00'; ?>"
                                                           class="focus:ring-red-500 focus:border-red-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md p-2">
                                                </div>
                                            </div>

                                            <div class="w-full md:w-auto">
                                                <button type="submit" name="set_fees" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm font-medium">
                                                    Update Fees
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        </div>

                    <?php elseif ($selected_course): ?>
                        <!-- Course Detail View -->
                        <div class="bg-white rounded-lg shadow border border-red-500 overflow-hidden">
                            <div class="p-6 border-b border-red-100 bg-red-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <div class="flex items-center space-x-2 text-sm text-gray-500 mb-1">
                                        <a href="payments.php" class="hover:text-red-600 transition-colors">Courses</a>
                                        <span>/</span>
                                        <span>Online Course</span>
                                    </div>
                                    <h2 class="text-2xl font-bold text-gray-900">
                                        <?php echo htmlspecialchars($selected_course['title']); ?>
                                    </h2>
                                </div>
                                <a href="payments.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="h-5 w-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    Back to Dashboard
                                </a>
                            </div>

                            <div class="p-6">
                                <!-- Filters -->
                                <div class="bg-gray-50 rounded-lg p-4 mb-8">
                                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($selected_course['id']); ?>">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                                            <select name="status" onchange="this.form.submit()" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Students</option>
                                                <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                                                <option value="not_paid" <?php echo $filter_status == 'not_paid' ? 'selected' : ''; ?>>Not Paid Only</option>
                                            </select>
                                        </div>
                                    </form>
                                </div>

                                <!-- Students Grid -->
                                <div class="mb-8">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center justify-between">
                                        <span>Enrolled Students</span>
                                        <span class="text-sm font-normal text-gray-500"><?php echo count($course_students); ?> students</span>
                                    </h3>
                                    
                                    <?php if (empty($course_students)): ?>
                                        <div class="text-center py-12 bg-white rounded-lg border-2 border-dashed border-gray-300">
                                            <p class="text-sm text-gray-500">No students found.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex flex-col max-w-xl gap-4">
                                            <?php foreach ($course_students as $student): 
                                                $is_paid = $student['payment_status'] === 'paid';
                                            ?>
                                                <div class="relative rounded-lg border p-2 shadow-sm flex items-center space-x-4 <?php echo $is_paid ? 'bg-green-100 border-green-200' : 'bg-red-100 border-red-200'; ?>">
                                                    <div class="flex-shrink-0">
                                                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full <?php echo $is_paid ? 'bg-green-200 text-green-700' : 'bg-red-200 text-red-700'; ?>">
                                                            <span class="text-sm font-bold leading-none">
                                                                <?php echo strtoupper(substr($student['first_name'] ?: ($student['user_id'] ?: '?'), 0, 1)); ?>
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            <?php echo htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['second_name'] ?? '')) ?: $student['user_id']); ?>
                                                        </p>
                                                        <?php if ($is_paid && !empty($student['payment_date'])): ?>
                                                            <p class="text-xs text-gray-500 mt-1">
                                                                Paid on: <?php echo date('M d, Y', strtotime($student['payment_date'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $is_paid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo ucfirst($student['payment_status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Subject Cards List -->
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-2xl font-bold text-gray-900">My Subjects</h2>
                                <p class="text-gray-600 mt-1">Select a subject to view payments and student status.</p>
                            </div>
                            <div class="p-6">
                                <?php if (empty($teacher_assignments)): ?>
                                    <div class="text-center py-12">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No subjects assigned</h3>
                                        <p class="mt-1 text-sm text-gray-500">Contact admin to get assigned to updated streams.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <?php foreach ($teacher_assignments as $assignment): ?>
                                            <a href="?assignment_id=<?php echo $assignment['id']; ?>" class="group">
                                                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 h-full flex flex-col relative overflow-hidden group-hover:-translate-y-1">
                                                    <div class="h-1.5 bg-red-600 w-full"></div>
                                                    <div class="p-6 flex-1">
                                                        <div class="flex items-center justify-between mb-4">
                                                            <div class="p-2 bg-red-50 rounded-lg">
                                                                <i class="fas fa-chalkboard-teacher text-red-600"></i>
                                                            </div>
                                                            <span class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full bg-gray-100 text-gray-600">
                                                                <?php echo htmlspecialchars($assignment['academic_year']); ?>
                                                            </span>
                                                        </div>
                                                        <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-red-600 transition-colors">
                                                            <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                                        </h3>
                                                        <div class="flex items-center text-sm text-gray-500 mb-4">
                                                            <i class="fas fa-layer-group mr-2 text-gray-400"></i>
                                                            <span><?php echo htmlspecialchars($assignment['stream_name']); ?></span>
                                                        </div>
                                                        <div class="flex items-center justify-between pt-4 border-t border-gray-50">
                                                            <div class="flex items-center text-xs font-semibold text-gray-500">
                                                                <i class="fas fa-users mr-1.5 text-red-400"></i>
                                                                <?php echo $assignment['student_count']; ?> Students
                                                            </div>
                                                            <div class="text-xs font-bold text-gray-900">
                                                                <?php 
                                                                if ($assignment['fee_info'] && $assignment['fee_info']['monthly_fee'] > 0) {
                                                                    echo 'Rs. ' . number_format($assignment['fee_info']['monthly_fee'], 0);
                                                                } else {
                                                                    echo 'Free';
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Course Cards List -->
                        <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-2xl font-bold text-gray-900">My Courses</h2>
                                <p class="text-gray-600 mt-1">Select a course to view enrollments.</p>
                            </div>
                            <div class="p-6">
                                <?php if (empty($teacher_courses)): ?>
                                    <div class="text-center py-8">
                                        <p class="text-gray-500">No active courses found.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <?php foreach ($teacher_courses as $course): ?>
                                            <a href="?course_id=<?php echo $course['id']; ?>" class="group">
                                                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 h-full flex flex-col relative overflow-hidden group-hover:-translate-y-1">
                                                    <div class="h-1.5 bg-blue-600 w-full"></div>
                                                    <div class="p-6 flex-1">
                                                        <div class="flex items-center justify-between mb-4">
                                                            <div class="p-2 bg-blue-50 rounded-lg">
                                                                <i class="fas fa-video text-blue-600"></i>
                                                            </div>
                                                            <span class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full bg-blue-50 text-blue-600">
                                                                Online Course
                                                            </span>
                                                        </div>
                                                        <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-blue-600 transition-colors">
                                                            <?php echo htmlspecialchars($course['title']); ?>
                                                        </h3>
                                                        <div class="flex items-center text-sm font-bold text-blue-600 mb-4">
                                                            Rs. <?php echo number_format($course['price'], 0); ?>
                                                        </div>
                                                        <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed">
                                                            <?php echo htmlspecialchars($course['description']); ?>
                                                        </p>
                                                    </div>
                                                    <div class="px-6 py-3 bg-gray-50 flex items-center justify-between">
                                                        <span class="text-[10px] font-bold text-gray-400 uppercase">View Details</span>
                                                        <i class="fas fa-arrow-right text-gray-300 group-hover:text-blue-600 group-hover:translate-x-1 transition-all"></i>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>