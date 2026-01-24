<?php
require_once '../check_session.php';
require_once '../config.php';

// Verify user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: /lms/login.php?error=" . urlencode("Access denied. Admin only."));
    exit();
}

// Get dashboard background image from system settings
$dashboard_background = null;
$bg_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'dashboard_background' LIMIT 1");
if ($bg_stmt) {
    $bg_stmt->execute();
    $bg_result = $bg_stmt->get_result();
    if ($bg_result->num_rows > 0) {
        $bg_row = $bg_result->fetch_assoc();
        $dashboard_background = $bg_row['setting_value'];
    }
    $bg_stmt->close();
}

// Get all teachers with their education details
$query = "SELECT DISTINCT u.user_id, u.email, u.first_name, u.second_name, 
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

    // Get assignments and enrollment counts
    $assign_query = "SELECT sub.name as subject_name, s.name as stream_name, 
                            (SELECT COUNT(*) 
                             FROM student_enrollment se 
                             WHERE se.stream_subject_id = ta.stream_subject_id 
                               AND se.academic_year = ta.academic_year 
                               AND se.status = 'active') as student_count
                     FROM teacher_assignments ta
                     INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                     INNER JOIN streams s ON ss.stream_id = s.id
                     INNER JOIN subjects sub ON ss.subject_id = sub.id
                     WHERE ta.teacher_id = ? AND ta.status = 'active'
                     ORDER BY sub.name, s.name";
    $assign_stmt = $conn->prepare($assign_query);
    $assign_stmt->bind_param("s", $teacher_id);
    $assign_stmt->execute();
    $assign_result = $assign_stmt->get_result();
    
    $assignments = [];
    while ($assign_row = $assign_result->fetch_assoc()) {
        $assignments[] = $assign_row;
    }
    $assign_stmt->close();
    
    $teachers[] = [
        'user_id' => $row['user_id'],
        'email' => $row['email'],
        'first_name' => $row['first_name'],
        'second_name' => $row['second_name'],
        'mobile_number' => $row['mobile_number'],
        'whatsapp_number' => $row['whatsapp_number'],
        'profile_picture' => $row['profile_picture'],
        'education' => $education,
        'assignments' => $assignments
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            <?php if ($dashboard_background): ?>
            background-image: url('../<?php echo htmlspecialchars($dashboard_background); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            <?php endif; ?>
        }
        
        /* Add semi-transparent overlay for better content readability */
        <?php if ($dashboard_background): ?>
        .content-overlay {
            background-color: rgba(243, 244, 246, 0.85);
            min-height: 100vh;
        }
        
        /* Make content cards more transparent to show background */
        .transparent-card {
            background-color: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(10px);
        }
        
        .transparent-card-light {
            background-color: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
        }
        <?php else: ?>
        .transparent-card {
            background-color: white;
        }
        
        .transparent-card-light {
            background-color: white;
        }
        <?php endif; ?>
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>
    
    <?php if ($dashboard_background): ?>
    <div class="content-overlay">
    <?php endif; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Welcome Section -->
            <div class="transparent-card rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Admin Dashboard</h1>
                <p class="text-gray-600">
                    Welcome, <span class="font-semibold text-red-600"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>!
                </p>
            </div>

            <!-- Teachers Section -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">All Teachers</h2>
                
                <?php if (empty($teachers)): ?>
                    <div class="transparent-card rounded-lg shadow p-8 text-center">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <p class="text-gray-500 text-lg">No teachers available.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($teachers as $teacher): ?>
                            <div class="transparent-card-light border-2 border-red-500 rounded-lg p-6 hover:border-red-600 hover:shadow-xl transition-all duration-200 flex flex-col h-full">
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
                                
                                <!-- Teacher name - left aligned -->
                                <div class="text-left mb-4">
                                    <h4 class="font-bold text-xl text-gray-900 mb-1">
                                        <?php echo htmlspecialchars(trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['second_name'] ?? ''))); ?>
                                    </h4>
                                </div>
                                
                                <!-- WhatsApp number with icon - left aligned -->
                                <?php if ($teacher['whatsapp_number']): ?>
                                    <div class="text-left mb-4 flex items-center">
                                        <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($teacher['whatsapp_number']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Education details - left aligned -->
                                <div class="text-left mb-4 flex-1">
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 uppercase tracking-wide flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        Education Details
                                    </h5>
                                    <?php if (!empty($teacher['education'])): ?>
                                        <ul class="space-y-2">
                                            <?php foreach ($teacher['education'] as $edu): ?>
                                                <li class="text-sm text-gray-600 flex items-start">
                                                    <svg class="w-4 h-4 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <div class="flex-1 flex flex-col">
                                                        <span class="font-medium text-gray-800">
                                                            <?php 
                                                            echo htmlspecialchars($edu['qualification'] ?? ''); 
                                                            if (!empty($edu['year_obtained'])) {
                                                                echo ' (' . htmlspecialchars($edu['year_obtained']) . ')';
                                                            }
                                                            ?>
                                                        </span>
                                                        <?php if (!empty($edu['institution'])): ?>
                                                            <span class="text-xs text-gray-500 font-medium mt-0.5">
                                                                <?php echo htmlspecialchars($edu['institution']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php 
                                                        $extras = [];
                                                        if (!empty($edu['field_of_study'])) $extras[] = $edu['field_of_study'];
                                                        if (!empty($edu['grade_or_class'])) $extras[] = $edu['grade_or_class'];
                                                        if (!empty($extras)):
                                                        ?>
                                                            <span class="text-xs text-gray-400 mt-0.5">
                                                                <?php echo htmlspecialchars(implode(' - ', $extras)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500 italic">No education details available</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Current Classes / Assignments -->
                                <div class="text-left w-full mt-4 pt-4 border-t border-gray-200">
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 uppercase tracking-wide flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                        Current Classes
                                    </h5>
                                    <?php if (!empty($teacher['assignments'])): ?>
                                        <div class="space-y-2">
                                            <?php foreach ($teacher['assignments'] as $assign): ?>
                                                <div class="text-sm text-gray-600 flex justify-between items-center bg-gray-50 p-2 rounded hover:bg-white hover:shadow-sm transition-all border border-transparent hover:border-gray-200">
                                                    <div class="flex flex-col">
                                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($assign['subject_name']); ?></span>
                                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($assign['stream_name']); ?></span>
                                                    </div>
                                                    <div class="flex items-center space-x-1 bg-blue-50 px-2 py-1 rounded-full border border-blue-100">
                                                        <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                        </svg>
                                                        <span class="text-xs font-bold text-blue-700">
                                                            <?php echo $assign['student_count']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500 italic">No active classes</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($dashboard_background): ?>
    </div> <!-- Close content-overlay -->
    <?php endif; ?>
</body>
</html>
