<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$current_year = date('Y');

// Get all teachers with their education details and assignments
$query = "SELECT DISTINCT u.user_id, u.username, u.email, u.first_name, u.second_name, 
                 u.mobile_number, u.whatsapp_number, u.profile_picture, u.role
          FROM users u
          WHERE u.role = 'teacher'
            AND u.status = 1
            AND u.approved = 1
          ORDER BY u.first_name, u.second_name";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$teachers = [];
while ($row = $result->fetch_assoc()) {
    $teacher_id = $row['user_id'];
    
    // Get education details for this teacher
    $edu_query = "SELECT qualification, institution, year_obtained, field_of_study, grade_or_class 
                  FROM teacher_education 
                  WHERE teacher_id = ? 
                  ORDER BY year_obtained DESC, id ASC";
    $edu_stmt = $conn->prepare($edu_query);
    $edu_stmt->bind_param("s", $teacher_id);
    $edu_stmt->execute();
    $edu_result = $edu_stmt->get_result();
    
    $education = [];
    while ($edu_row = $edu_result->fetch_assoc()) {
        $education[] = $edu_row;
    }
    $edu_stmt->close();
    
    // Get distinct teacher assignments (subjects and streams - no duplicates)
    $assign_query = "SELECT DISTINCT s.name as stream_name, sub.name as subject_name
                     FROM teacher_assignments ta
                     INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                     INNER JOIN streams s ON ss.stream_id = s.id
                     INNER JOIN subjects sub ON ss.subject_id = sub.id
                     WHERE ta.teacher_id = ? AND ta.status = 'active'
                     ORDER BY s.name, sub.name";
    $assign_stmt = $conn->prepare($assign_query);
    $assign_stmt->bind_param("s", $teacher_id);
    $assign_stmt->execute();
    $assign_result = $assign_stmt->get_result();
    
    $assignments = [];
    while ($assign_row = $assign_result->fetch_assoc()) {
        $assignments[] = $assign_row;
    }
    $assign_stmt->close();
    
    // Check if student is enrolled with this teacher (for students only)
    $is_enrolled = false;
    if ($role === 'student') {
        $enroll_check = "SELECT COUNT(*) as count 
                        FROM student_enrollment se
                        INNER JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
                            AND se.academic_year = ta.academic_year
                        WHERE se.student_id = ? AND ta.teacher_id = ? AND se.status = 'active' AND ta.status = 'active'";
        $enroll_stmt = $conn->prepare($enroll_check);
        $enroll_stmt->bind_param("ss", $user_id, $teacher_id);
        $enroll_stmt->execute();
        $enroll_result = $enroll_stmt->get_result();
        $enroll_row = $enroll_result->fetch_assoc();
        $is_enrolled = $enroll_row['count'] > 0;
        $enroll_stmt->close();
    }
    
    $teachers[] = [
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'first_name' => $row['first_name'],
        'second_name' => $row['second_name'],
        'mobile_number' => $row['mobile_number'],
        'whatsapp_number' => $row['whatsapp_number'],
        'profile_picture' => $row['profile_picture'],
        'education' => $education,
        'assignments' => $assignments,
        'is_enrolled' => $is_enrolled
    ];
}
$stmt->close();

// Separate teachers into enrolled and others (for students only)
$enrolled_teachers = [];
$other_teachers = [];

if ($role === 'student') {
    foreach ($teachers as $teacher) {
        if ($teacher['is_enrolled']) {
            $enrolled_teachers[] = $teacher;
        } else {
            $other_teachers[] = $teacher;
        }
    }
} else {
    // For non-students, show all teachers
    $other_teachers = $teachers;
}

// Get all available streams and subjects from active teacher assignments
$streams_query = "SELECT DISTINCT s.id, s.name 
                  FROM teacher_assignments ta
                  INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                  INNER JOIN streams s ON ss.stream_id = s.id
                  WHERE ta.status = 'active'
                  ORDER BY s.name";
$streams_stmt = $conn->prepare($streams_query);
$streams_stmt->execute();
$streams_result = $streams_stmt->get_result();

$available_streams = [];
$stream_subjects_map = []; // Map stream_id => [subjects]

while ($stream_row = $streams_result->fetch_assoc()) {
    $stream_id = $stream_row['id'];
    $stream_name = $stream_row['name'];
    $available_streams[] = $stream_row;
    
    // Get subjects for this stream
    $subjects_query = "SELECT DISTINCT sub.id, sub.name 
                       FROM teacher_assignments ta
                       INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                       INNER JOIN subjects sub ON ss.subject_id = sub.id
                       WHERE ss.stream_id = ? AND ta.status = 'active'
                       ORDER BY sub.name";
    $subjects_stmt = $conn->prepare($subjects_query);
    $subjects_stmt->bind_param("i", $stream_id);
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();
    
    $stream_subjects_map[$stream_id] = [];
    while ($subject_row = $subjects_result->fetch_assoc()) {
        $stream_subjects_map[$stream_id][] = $subject_row;
    }
    $subjects_stmt->close();
}
$streams_stmt->close();
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
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Teachers</h1>
                        <p class="text-gray-600">
                            Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!
                        </p>
                    </div>
                    
                    <!-- Filters -->
                    <?php if (!empty($available_streams)): ?>
                        <div class="flex flex-col sm:flex-row gap-3 mt-4 sm:mt-0">
                            <!-- Stream Filter -->
                            <div class="flex-1 sm:flex-initial">
                                <label for="filterStream" class="block text-sm font-medium text-gray-700 mb-1">Filter by Stream</label>
                                <select id="filterStream" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                    <option value="">All Streams</option>
                                    <?php foreach ($available_streams as $stream): ?>
                                        <option value="<?php echo htmlspecialchars($stream['name']); ?>" data-stream-id="<?php echo $stream['id']; ?>">
                                            <?php echo htmlspecialchars($stream['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Subject Filter -->
                            <div class="flex-1 sm:flex-initial">
                                <label for="filterSubject" class="block text-sm font-medium text-gray-700 mb-1">Filter by Subject</label>
                                <select id="filterSubject" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" disabled>
                                    <option value="">Select Stream First</option>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Teachers Grid -->
            <?php if (empty($teachers)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <p class="text-gray-500 text-lg">No teachers available.</p>
                </div>
            <?php else: ?>
                <?php if ($role === 'student' && !empty($enrolled_teachers)): ?>
                    <!-- Enrolled Teachers Section -->
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">My Teachers</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($enrolled_teachers as $teacher): ?>
                        <div class="teacher-card bg-white border-2 border-red-500 rounded-lg p-6 hover:border-red-600 hover:shadow-xl transition-all duration-200 flex flex-col h-full"
                             data-streams="<?php echo htmlspecialchars(implode(',', array_unique(array_column($teacher['assignments'], 'stream_name')))); ?>"
                             data-subjects="<?php echo htmlspecialchars(implode(',', array_unique(array_column($teacher['assignments'], 'subject_name')))); ?>">
                            <!-- Large centered profile picture -->
                            <div class="flex justify-center mb-6">
                                <?php if ($teacher['profile_picture']): ?>
                                    <img src="../<?php echo htmlspecialchars($teacher['profile_picture']); ?>" 
                                         alt="Profile" 
                                         class="w-32 h-32 rounded-full object-cover border-4 border-red-200 shadow-lg">
                                <?php else: ?>
                                    <div class="w-32 h-32 rounded-full bg-red-100 flex items-center justify-center border-4 border-red-200 shadow-lg">
                                        <svg class="w-16 h-16 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Name, WhatsApp, Email - Column layout -->
                            <div class="flex flex-col items-center mb-6 space-y-2">
                                <!-- Name -->
                                <h4 class="font-bold text-xl text-gray-900 text-center">
                                    <?php echo htmlspecialchars(trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['second_name'] ?? ''))); ?>
                                </h4>
                                
                                <!-- WhatsApp number -->
                                <?php if ($teacher['whatsapp_number']): ?>
                                    <p class="text-sm text-gray-700 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        <?php echo htmlspecialchars($teacher['whatsapp_number']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Email -->
                                <?php if ($teacher['email']): ?>
                                    <p class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($teacher['email']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Education details -->
                            <div class="mb-4 flex-1">
                                <h5 class="text-sm font-semibold text-red-600 mb-3 uppercase tracking-wide flex items-center border-b border-red-200 pb-2">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    Education
                                </h5>
                                <?php if (!empty($teacher['education'])): ?>
                                    <ul class="space-y-2">
                                        <?php foreach ($teacher['education'] as $edu): ?>
                                            <?php
                                            $eduText = htmlspecialchars($edu['qualification'] ?? '');
                                            if (!empty($edu['institution'])) {
                                                $eduText .= ' - ' . htmlspecialchars($edu['institution']);
                                            }
                                            if (!empty($edu['year_obtained'])) {
                                                $eduText .= ' (' . htmlspecialchars($edu['year_obtained']);
                                                if (!empty($edu['grade_or_class'])) {
                                                    $eduText .= ' - ' . htmlspecialchars($edu['grade_or_class']);
                                                }
                                                $eduText .= ')';
                                            } elseif (!empty($edu['grade_or_class'])) {
                                                $eduText .= ' - ' . htmlspecialchars($edu['grade_or_class']);
                                            }
                                            if (!empty($edu['field_of_study'])) {
                                                $eduText .= ' - ' . htmlspecialchars($edu['field_of_study']);
                                            }
                                            ?>
                                            <li class="text-sm text-gray-600 flex items-start">
                                                <svg class="w-4 h-4 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="flex-1"><?php echo $eduText; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500 italic">No education details available</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Assignment details (Subjects and Streams) -->
                            <div class="mb-4">
                                <h5 class="text-sm font-semibold text-red-600 mb-3 uppercase tracking-wide flex items-center border-b border-red-200 pb-2">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    Teaching
                                </h5>
                                <?php if (!empty($teacher['assignments'])): ?>
                                    <ul class="space-y-2">
                                        <?php foreach ($teacher['assignments'] as $assignment): ?>
                                            <li class="text-sm text-gray-700 flex items-start">
                                                <svg class="w-4 h-4 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="flex-1">
                                                    <span class="font-medium"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                                                    <span class="text-gray-500"> - <?php echo htmlspecialchars($assignment['stream_name']); ?></span>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500 italic">No active assignments</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Enroll Button -->
                            <?php if ($role === 'student'): ?>
                                <div class="mt-auto pt-4 border-t border-red-200">
                                    <button onclick="showEnrollments('<?php echo htmlspecialchars($teacher['user_id']); ?>')" 
                                            class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors font-medium shadow-md">
                                        Enroll
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($other_teachers)): ?>
                    <!-- Other Teachers Section -->
                    <?php if ($role === 'student' && !empty($enrolled_teachers)): ?>
                        <div class="mb-8">
                            <h2 class="text-2xl font-bold text-gray-900 mb-4">All Teachers</h2>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($other_teachers as $teacher): ?>
                            <div class="teacher-card bg-white border-2 border-red-500 rounded-lg p-6 hover:border-red-600 hover:shadow-xl transition-all duration-200 flex flex-col h-full"
                                 data-streams="<?php echo htmlspecialchars(implode(',', array_unique(array_column($teacher['assignments'], 'stream_name')))); ?>"
                                 data-subjects="<?php echo htmlspecialchars(implode(',', array_unique(array_column($teacher['assignments'], 'subject_name')))); ?>">
                                <!-- Large centered profile picture -->
                                <div class="flex justify-center mb-6">
                                    <?php if ($teacher['profile_picture']): ?>
                                        <img src="../<?php echo htmlspecialchars($teacher['profile_picture']); ?>" 
                                             alt="Profile" 
                                             class="w-32 h-32 rounded-full object-cover border-4 border-red-200 shadow-lg">
                                    <?php else: ?>
                                        <div class="w-32 h-32 rounded-full bg-red-100 flex items-center justify-center border-4 border-red-200 shadow-lg">
                                            <svg class="w-16 h-16 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Name, WhatsApp, Email - Column layout -->
                                <div class="flex flex-col items-center mb-6 space-y-2">
                                    <!-- Name -->
                                    <h4 class="font-bold text-xl text-gray-900 text-center">
                                        <?php echo htmlspecialchars(trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['second_name'] ?? ''))); ?>
                                    </h4>
                                    
                                    <!-- WhatsApp number -->
                                    <?php if ($teacher['whatsapp_number']): ?>
                                        <p class="text-sm text-gray-700 flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                            </svg>
                                            <?php echo htmlspecialchars($teacher['whatsapp_number']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Email -->
                                    <?php if ($teacher['email']): ?>
                                        <p class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($teacher['email']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Education details -->
                                <div class="mb-4 flex-1">
                                    <h5 class="text-sm font-semibold text-red-600 mb-3 uppercase tracking-wide flex items-center border-b border-red-200 pb-2">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        Education
                                    </h5>
                                    <?php if (!empty($teacher['education'])): ?>
                                        <ul class="space-y-2">
                                            <?php foreach ($teacher['education'] as $edu): ?>
                                                <?php
                                                $eduText = htmlspecialchars($edu['qualification'] ?? '');
                                                if (!empty($edu['institution'])) {
                                                    $eduText .= ' - ' . htmlspecialchars($edu['institution']);
                                                }
                                                if (!empty($edu['year_obtained'])) {
                                                    $eduText .= ' (' . htmlspecialchars($edu['year_obtained']);
                                                    if (!empty($edu['grade_or_class'])) {
                                                        $eduText .= ' - ' . htmlspecialchars($edu['grade_or_class']);
                                                    }
                                                    $eduText .= ')';
                                                } elseif (!empty($edu['grade_or_class'])) {
                                                    $eduText .= ' - ' . htmlspecialchars($edu['grade_or_class']);
                                                }
                                                if (!empty($edu['field_of_study'])) {
                                                    $eduText .= ' - ' . htmlspecialchars($edu['field_of_study']);
                                                }
                                                ?>
                                                <li class="text-sm text-gray-600 flex items-start">
                                                    <svg class="w-4 h-4 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span class="flex-1"><?php echo $eduText; ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500 italic">No education details available</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Assignment details (Subjects and Streams) -->
                                <div class="mb-4">
                                    <h5 class="text-sm font-semibold text-red-600 mb-3 uppercase tracking-wide flex items-center border-b border-red-200 pb-2">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        Teaching
                                    </h5>
                                    <?php if (!empty($teacher['assignments'])): ?>
                                        <ul class="space-y-2">
                                            <?php foreach ($teacher['assignments'] as $assignment): ?>
                                                <li class="text-sm text-gray-700 flex items-start">
                                                    <svg class="w-4 h-4 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span class="flex-1">
                                                        <span class="font-medium"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                                                        <span class="text-gray-500"> - <?php echo htmlspecialchars($assignment['stream_name']); ?></span>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500 italic">No active assignments</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Enroll Button -->
                                <?php if ($role === 'student'): ?>
                                    <div class="mt-auto pt-4 border-t border-red-200">
                                        <button onclick="showEnrollments('<?php echo htmlspecialchars($teacher['user_id']); ?>')" 
                                                class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors font-medium shadow-md">
                                            Enroll
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($role === 'student' && !empty($enrolled_teachers)): ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-[60]">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">Confirm Enrollment</h3>
                <button onclick="closeConfirmModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="mb-6">
                <p class="text-gray-700" id="confirmMessage">Are you sure you want to enroll in this subject?</p>
            </div>
            <div class="flex justify-end space-x-3">
                <button onclick="closeConfirmModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button id="confirmButton" onclick="proceedEnrollment()" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Enrollment Modal -->
    <?php if ($role === 'student'): ?>
        <div id="enrollmentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Available Enrollments</h3>
                    <button onclick="closeEnrollmentModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="enrollmentsContainer" class="space-y-4">
                    <!-- Enrollments will be loaded here -->
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Toast notification functions
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const icon = type === 'success' ? 
                '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' :
                '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
            
            toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 min-w-[300px] max-w-md transform transition-all duration-300 ease-in-out translate-x-full opacity-0`;
            toast.innerHTML = `
                ${icon}
                <span class="flex-1">${message}</span>
            `;
            
            container.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 10);
            
            // Auto remove after 3.5 seconds
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    if (container.contains(toast)) {
                        container.removeChild(toast);
                    }
                }, 300);
            }, 3500);
        }

        // Stream-Subject mapping from PHP
        const streamSubjectsMap = <?php echo json_encode($stream_subjects_map); ?>;
        
        // Initialize filters and check for URL messages
        document.addEventListener('DOMContentLoaded', function() {
            // Check for messages in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const successMsg = urlParams.get('success');
            const errorMsg = urlParams.get('error');

            if (successMsg) {
                showToast(decodeURIComponent(successMsg), 'success');
                // Remove parameter to prevent re-showing on refresh
                urlParams.delete('success');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                history.replaceState(null, '', newUrl);
            }
            if (errorMsg) {
                showToast(decodeURIComponent(errorMsg), 'error');
                // Remove parameter to prevent re-showing on refresh
                urlParams.delete('error');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                history.replaceState(null, '', newUrl);
            }
            
            const streamFilter = document.getElementById('filterStream');
            const subjectFilter = document.getElementById('filterSubject');
            
            if (streamFilter) {
                // Handle stream selection
                streamFilter.addEventListener('change', function() {
                    const selectedStream = this.value;
                    const selectedOption = this.options[this.selectedIndex];
                    const streamId = selectedOption ? selectedOption.getAttribute('data-stream-id') : null;
                    
                    // Reset and populate subject filter
                    subjectFilter.innerHTML = '<option value="">All Subjects</option>';
                    
                    if (streamId && streamSubjectsMap[streamId]) {
                        subjectFilter.disabled = false;
                        streamSubjectsMap[streamId].forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject.name;
                            option.textContent = subject.name;
                            subjectFilter.appendChild(option);
                        });
                    } else {
                        subjectFilter.disabled = true;
                        subjectFilter.innerHTML = '<option value="">Select Stream First</option>';
                    }
                    
                    // Apply filters
                    filterTeachers();
                });
                
                // Handle subject selection
                subjectFilter.addEventListener('change', function() {
                    filterTeachers();
                });
            }
        });
        
        function filterTeachers() {
            const streamFilter = document.getElementById('filterStream');
            const subjectFilter = document.getElementById('filterSubject');
            const selectedStream = streamFilter ? streamFilter.value : '';
            const selectedSubject = subjectFilter ? subjectFilter.value : '';
            
            const teacherCards = document.querySelectorAll('.teacher-card');
            let visibleCount = 0;
            
            teacherCards.forEach(card => {
                const cardStreams = card.getAttribute('data-streams') || '';
                const cardSubjects = card.getAttribute('data-subjects') || '';
                
                const streamsArray = cardStreams.split(',').map(s => s.trim()).filter(s => s);
                const subjectsArray = cardSubjects.split(',').map(s => s.trim()).filter(s => s);
                
                // Check if teacher has matching stream
                const streamMatch = !selectedStream || streamsArray.includes(selectedStream);
                
                // Check if teacher has matching subject (only if stream matches)
                const subjectMatch = !selectedSubject || (streamMatch && subjectsArray.includes(selectedSubject));
                
                // Show teacher only if both stream and subject match (or are not selected)
                if (streamMatch && subjectMatch) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show empty message if no teachers visible in each section
            const gridSections = document.querySelectorAll('.grid');
            gridSections.forEach(section => {
                const visibleCards = Array.from(section.querySelectorAll('.teacher-card')).filter(card => 
                    card.style.display !== 'none'
                );
                const totalCards = section.querySelectorAll('.teacher-card').length;
                
                if (visibleCards.length === 0 && totalCards > 0) {
                    let emptyMsg = section.querySelector('.no-results-message');
                    if (!emptyMsg) {
                        emptyMsg = document.createElement('div');
                        emptyMsg.className = 'no-results-message col-span-full bg-white rounded-lg shadow p-8 text-center';
                        emptyMsg.innerHTML = `
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No teachers found for the selected filters.</p>
                        `;
                        section.appendChild(emptyMsg);
                    }
                    emptyMsg.style.display = 'block';
                } else {
                    const emptyMsg = section.querySelector('.no-results-message');
                    if (emptyMsg) {
                        emptyMsg.style.display = 'none';
                    }
                }
            });
        }
        
        function showEnrollments(teacherId) {
            const modal = document.getElementById('enrollmentModal');
            const container = document.getElementById('enrollmentsContainer');
            
            // Show loading
            container.innerHTML = '<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-red-600"></div><p class="mt-2 text-gray-600">Loading enrollments...</p></div>';
            modal.classList.remove('hidden');
            
            // Fetch enrollments
            fetch(`get_teacher_enrollments.php?teacher_id=${teacherId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.enrollments && data.enrollments.length > 0) {
                        let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
                        data.enrollments.forEach(enrollment => {
                            const isEnrolled = enrollment.is_enrolled || false;
                            html += `
                                <div class="border ${isEnrolled ? 'border-green-300 bg-green-50' : 'border-gray-300'} rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex items-start justify-between mb-2">
                                        <h4 class="font-bold text-lg text-gray-900">${enrollment.subject_name || 'Subject'}</h4>
                                        ${isEnrolled ? `<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Enrolled</span>` : ''}
                                    </div>
                                    ${enrollment.subject_code ? `<p class="text-sm text-gray-500 mb-2">${enrollment.subject_code}</p>` : ''}
                                    <div class="space-y-1 text-sm text-gray-600 mb-4">
                                        <p><span class="font-medium">Stream:</span> ${enrollment.stream_name || 'N/A'}</p>
                                        <p><span class="font-medium">Academic Year:</span> ${enrollment.academic_year || 'N/A'}</p>
                                        ${enrollment.batch_name ? `<p><span class="font-medium">Batch:</span> ${enrollment.batch_name}</p>` : ''}
                                    </div>
                                    ${isEnrolled ? 
                                        `<button disabled class="w-full px-4 py-2 bg-gray-400 text-white rounded-md cursor-not-allowed font-medium">
                                            Already Enrolled
                                        </button>` :
                                        `<button onclick="enrollStudent('${enrollment.teacher_assignment_id}', '${enrollment.stream_subject_id}', '${enrollment.academic_year}')" 
                                                class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors font-medium">
                                            Enroll Now
                                        </button>`
                                    }
                                </div>
                            `;
                        });
                        html += '</div>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div class="text-center py-8"><p class="text-gray-500">No available enrollments for this teacher.</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error loading enrollments. Please try again.', 'error');
                    container.innerHTML = '<div class="text-center py-8"><p class="text-red-500">Error loading enrollments. Please try again.</p></div>';
                });
        }

        function closeEnrollmentModal() {
            document.getElementById('enrollmentModal').classList.add('hidden');
        }

        // Store enrollment data for confirmation
        let pendingEnrollment = null;

        function enrollStudent(teacherAssignmentId, streamSubjectId, academicYear) {
            // Store enrollment data
            pendingEnrollment = {
                teacherAssignmentId: teacherAssignmentId,
                streamSubjectId: streamSubjectId,
                academicYear: academicYear
            };
            
            // Show confirmation modal
            document.getElementById('confirmModal').classList.remove('hidden');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
            pendingEnrollment = null;
        }

        function proceedEnrollment() {
            if (!pendingEnrollment) {
                closeConfirmModal();
                return;
            }

            const formData = new FormData();
            formData.append('stream_subject_id', pendingEnrollment.streamSubjectId);
            formData.append('academic_year', pendingEnrollment.academicYear);
            formData.append('enroll', '1');

            // Close confirmation modal
            closeConfirmModal();

            fetch('enroll.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Enrollment successful!', 'success');
                    closeEnrollmentModal();
                    // Reload the page after a short delay to show the toast
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message || 'Enrollment failed. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error enrolling. Please try again.', 'error');
            });
        }

        // Close modal on outside click
        document.getElementById('enrollmentModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEnrollmentModal();
            }
        });

        document.getElementById('confirmModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEnrollmentModal();
                closeConfirmModal();
            }
        });
    </script>
</body>
</html>
