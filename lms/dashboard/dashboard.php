<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');

// Initialize arrays
$teacher_assignments = [];
$student_enrollments = [];

if ($role === 'teacher') {
    // Get teacher assignments
    $query = "SELECT ta.id, ta.stream_subject_id, ta.academic_year, ta.batch_name, ta.status, 
                     ta.assigned_date, ta.start_date, ta.end_date, ta.notes,
                     s.name as stream_name, sub.name as subject_name, sub.code as subject_code
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
        $teacher_assignments[] = $row;
    }
    $stmt->close();
    
} elseif ($role === 'student') {
    // Get student enrollments with teacher information
    $query = "SELECT se.id, se.stream_subject_id, se.academic_year, se.batch_name, se.enrolled_date,
                     se.status, se.payment_status, se.payment_method, se.payment_date, 
                     se.payment_amount, se.notes,
                     s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                     t.user_id as teacher_id, t.first_name as teacher_first_name, 
                     t.second_name as teacher_second_name, t.profile_picture as teacher_profile_picture,
                     t.email as teacher_email, t.whatsapp_number as teacher_whatsapp
              FROM student_enrollment se
              INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              LEFT JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id 
                AND ta.academic_year = se.academic_year 
                AND ta.status = 'active'
              LEFT JOIN users t ON ta.teacher_id = t.user_id AND t.role = 'teacher' AND t.status = 1
              WHERE se.student_id = ? AND se.status = 'active'
              ORDER BY se.academic_year DESC, s.name, sub.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $student_enrollments[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Welcome Section -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Welcome to Dashboard</h1>
                <p class="text-gray-600">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!
                </p>
                <p class="text-gray-600 mt-2">
                    Your role: <span class="font-semibold"><?php echo htmlspecialchars($role ?? 'N/A'); ?></span>
                </p>
            </div>

            <?php if ($role === 'teacher'): ?>
                <!-- Teacher Assignments -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">My Subjects</h2>
                    <?php if (empty($teacher_assignments)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No active teaching Subjects found.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($teacher_assignments as $assignment): ?>
                                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow border-l-4 border-red-500 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($assignment['subject_name']); ?></h3>
                                            <?php if ($assignment['subject_code']): ?>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($assignment['subject_code']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 mb-4">
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                            </svg>
                                            <span class="font-medium">Stream:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($assignment['stream_name']); ?></span>
                                        </div>
                                        
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span class="font-medium">Academic Year:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($assignment['academic_year']); ?></span>
                                        </div>
                                        
                                        <?php if ($assignment['batch_name']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zm-7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                                <span class="font-medium">Batch:</span>
                                                <span class="ml-2"><?php echo htmlspecialchars($assignment['batch_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($assignment['assigned_date']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                <span class="font-medium">Assigned:</span>
                                                <span class="ml-2"><?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($assignment['notes']): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">Notes:</span>
                                                <?php echo htmlspecialchars($assignment['notes']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($role === 'student'): ?>
                <!-- Student Enrollments -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">My Enrollments</h2>
                    <?php if (empty($student_enrollments)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No active enrollments found.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($student_enrollments as $enrollment): ?>
                                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow border-l-4 border-blue-500 p-6 flex flex-col">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($enrollment['subject_name']); ?></h3>
                                            <?php if ($enrollment['subject_code']): ?>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($enrollment['subject_code']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php 
                                            echo $enrollment['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                                        ?>">
                                            <?php echo ucfirst($enrollment['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 mb-4 flex-1">
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                            </svg>
                                            <span class="font-medium">Stream:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($enrollment['stream_name']); ?></span>
                                        </div>
                                        
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span class="font-medium">Academic Year:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($enrollment['academic_year']); ?></span>
                                        </div>
                                        
                                        <?php if ($enrollment['batch_name']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zm-7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                                <span class="font-medium">Batch:</span>
                                                <span class="ml-2"><?php echo htmlspecialchars($enrollment['batch_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($enrollment['enrolled_date']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                <span class="font-medium">Enrolled:</span>
                                                <span class="ml-2"><?php echo date('M d, Y', strtotime($enrollment['enrolled_date'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Teacher Information -->
                                        <?php if ($enrollment['teacher_id']): ?>
                                            <div class="mt-4 pt-4 border-t border-gray-200">
                                                <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                    Teacher
                                                </h4>
                                                <div class="flex items-center space-x-3">
                                                    <?php if ($enrollment['teacher_profile_picture']): ?>
                                                        <img src="../<?php echo htmlspecialchars($enrollment['teacher_profile_picture']); ?>" 
                                                             alt="Teacher" 
                                                             class="w-10 h-10 rounded-full object-cover border-2 border-blue-200">
                                                    <?php else: ?>
                                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center border-2 border-blue-200">
                                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                            </svg>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex-1">
                                                        <p class="font-medium text-gray-900">
                                                            <?php echo htmlspecialchars(trim(($enrollment['teacher_first_name'] ?? '') . ' ' . ($enrollment['teacher_second_name'] ?? ''))); ?>
                                                        </p>
                                                        <?php if ($enrollment['teacher_email']): ?>
                                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($enrollment['teacher_email']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if ($enrollment['teacher_whatsapp']): ?>
                                                            <p class="text-xs text-gray-500 flex items-center mt-1">
                                                                <svg class="w-3 h-3 mr-1 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                                </svg>
                                                                <?php echo htmlspecialchars($enrollment['teacher_whatsapp']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-4 pt-4 border-t border-gray-200">
                                                <p class="text-sm text-gray-500 italic">No teacher assigned yet</p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Payment Information -->
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-gray-700">Payment Status:</span>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?php 
                                                    echo $enrollment['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                                        ($enrollment['payment_status'] === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                ?>">
                                                    <?php echo ucfirst($enrollment['payment_status']); ?>
                                                </span>
                                            </div>
                                            <?php if ($enrollment['payment_amount']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <span class="font-medium">Amount:</span> 
                                                    <?php echo number_format($enrollment['payment_amount'], 2); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($enrollment['payment_date']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <span class="font-medium">Paid on:</span> 
                                                    <?php echo date('M d, Y', strtotime($enrollment['payment_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($enrollment['payment_method']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <span class="font-medium">Method:</span> 
                                                    <?php echo ucfirst(str_replace('_', ' ', $enrollment['payment_method'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($enrollment['notes']): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">Notes:</span>
                                                <?php echo htmlspecialchars($enrollment['notes']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

