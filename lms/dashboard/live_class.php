<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Get live classes
$live_classes = [];
$teacher_assignments = [];
$unique_subjects = [];
$unique_years = [];

if ($role === 'teacher') {
    // Get teacher's live classes
    $live_query = "SELECT lc.id, lc.title, lc.description, lc.status, lc.youtube_url, lc.actual_start_time, lc.end_time,
                          lc.created_at, lc.scheduled_start_time,
                          ta.stream_subject_id, ta.academic_year,
                          s.name as stream_name, sub.name as subject_name, sub.code as subject_code
                   FROM live_classes lc
                   INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
                   INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                   INNER JOIN streams s ON ss.stream_id = s.id
                   INNER JOIN subjects sub ON ss.subject_id = sub.id
                   WHERE ta.teacher_id = ?
                   ORDER BY lc.status DESC, lc.created_at DESC";
    
    $live_stmt = $conn->prepare($live_query);
    $live_stmt->bind_param("s", $user_id);
    $live_stmt->execute();
    $live_result = $live_stmt->get_result();
    
    while ($row = $live_result->fetch_assoc()) {
        $live_classes[] = $row;
    }
    $live_stmt->close();
    
    // Get teacher assignments (similar to recordings.php)
    $assign_query = "SELECT ta.id, ta.stream_subject_id, ta.academic_year, ta.batch_name, ta.status, 
                            ta.assigned_date, ta.start_date, ta.end_date, ta.notes,
                            s.name as stream_name, sub.name as subject_name, sub.code as subject_code
                     FROM teacher_assignments ta
                     INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                     INNER JOIN streams s ON ss.stream_id = s.id
                     INNER JOIN subjects sub ON ss.subject_id = sub.id
                     WHERE ta.teacher_id = ? AND ta.status = 'active'
                     ORDER BY ta.academic_year DESC, s.name, sub.name";
    
    $assign_stmt = $conn->prepare($assign_query);
    $assign_stmt->bind_param("s", $user_id);
    $assign_stmt->execute();
    $assign_result = $assign_stmt->get_result();
    
    while ($row = $assign_result->fetch_assoc()) {
        $teacher_assignments[] = $row;
        if (!in_array($row['subject_name'], $unique_subjects)) {
            $unique_subjects[] = $row['subject_name'];
        }
        if (!in_array($row['academic_year'], $unique_years)) {
            $unique_years[] = $row['academic_year'];
        }
    }
    if (!empty($unique_subjects)) sort($unique_subjects);
    if (!empty($unique_years)) rsort($unique_years);
    $assign_stmt->close();
    
} elseif ($role === 'student') {
    // Get ongoing live classes for student's enrollments
    $live_query = "SELECT lc.id, lc.title, lc.description, lc.status, lc.youtube_url, lc.actual_start_time,
                          ta.stream_subject_id, ta.academic_year,
                          s.name as stream_name, sub.name as subject_name, sub.code as subject_code,
                          u.first_name, u.second_name, u.profile_picture
                   FROM live_classes lc
                   INNER JOIN teacher_assignments ta ON lc.teacher_assignment_id = ta.id
                   INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                   INNER JOIN streams s ON ss.stream_id = s.id
                   INNER JOIN subjects sub ON ss.subject_id = sub.id
                   INNER JOIN users u ON ta.teacher_id = u.user_id
                   INNER JOIN student_enrollment se ON se.stream_subject_id = ta.stream_subject_id 
                       AND se.academic_year = ta.academic_year
                   WHERE se.student_id = ? AND se.status = 'active' AND lc.status = 'ongoing'
                   ORDER BY lc.actual_start_time DESC";
    
    $live_stmt = $conn->prepare($live_query);
    $live_stmt->bind_param("s", $user_id);
    $live_stmt->execute();
    $live_result = $live_stmt->get_result();
    
    while ($row = $live_result->fetch_assoc()) {
        $live_classes[] = $row;
    }
    $live_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Classes - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Welcome Section -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Live Classes</h1>
                <p class="text-gray-600">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!
                </p>
            </div>

            <?php if ($role === 'teacher'): ?>
                <!-- Ongoing Live Classes -->
                <?php 
                $ongoing_classes = array_filter($live_classes, function($lc) { return $lc['status'] === 'ongoing'; });
                $scheduled_classes = array_filter($live_classes, function($lc) { return $lc['status'] === 'scheduled'; });
                ?>
                
                <?php if (!empty($ongoing_classes)): ?>
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Ongoing Live Classes</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($ongoing_classes as $live_class): ?>
                                <div class="bg-white rounded-lg shadow-md border-l-4 border-red-600 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($live_class['title']); ?></h3>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($live_class['subject_name']); ?> - <?php echo htmlspecialchars($live_class['stream_name']); ?>
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            <i class="fas fa-circle text-red-600 mr-1"></i>
                                            LIVE
                                        </span>
                                    </div>
                                    
                                    <?php if ($live_class['description']): ?>
                                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($live_class['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="flex gap-2">
                                        <a href="../player/live_player.php?id=<?php echo $live_class['id']; ?>" 
                                           class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-center text-sm font-medium">
                                            <i class="fas fa-play mr-2"></i>Join
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Scheduled Live Classes -->
                <?php if (!empty($scheduled_classes)): ?>
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Scheduled Live Classes</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($scheduled_classes as $live_class): ?>
                                <div class="bg-white rounded-lg shadow-md border-l-4 border-yellow-500 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($live_class['title']); ?></h3>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($live_class['subject_name']); ?> - <?php echo htmlspecialchars($live_class['stream_name']); ?>
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                            Scheduled
                                        </span>
                                    </div>
                                    
                                    <?php if ($live_class['description']): ?>
                                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($live_class['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($live_class['scheduled_start_time']): ?>
                                        <p class="text-gray-600 text-sm mb-4">
                                            <i class="far fa-clock mr-2"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($live_class['scheduled_start_time'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="flex gap-2">
                                        <button onclick="startLiveClass(<?php echo $live_class['id']; ?>)" 
                                                class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium">
                                            <i class="fas fa-play mr-2"></i>Start
                                        </button>
                                        <button onclick="deleteLiveClass(<?php echo $live_class['id']; ?>)" 
                                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- My Subjects / Create New Live Class -->
                <div class="mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">My Subjects</h2>
                        <?php if (!empty($teacher_assignments)): ?>
                            <button onclick="openCreateModal()" class="w-full sm:w-auto px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Create New Live Class
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($teacher_assignments)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No active teaching subjects found.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($teacher_assignments as $assignment): ?>
                                <div class="bg-white rounded-lg shadow-md border-l-4 border-red-500 p-6">
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
                                        <div class="flex items-center text-gray-600 text-sm">
                                            <i class="fas fa-graduation-cap mr-2 text-red-600"></i>
                                            <span class="font-medium">Stream:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($assignment['stream_name']); ?></span>
                                        </div>
                                        
                                        <div class="flex items-center text-gray-600 text-sm">
                                            <i class="far fa-calendar mr-2 text-red-600"></i>
                                            <span class="font-medium">Year:</span>
                                            <span class="ml-2"><?php echo htmlspecialchars($assignment['academic_year']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <button onclick="openCreateModal(<?php echo $assignment['id']; ?>)" 
                                            class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                                        <i class="fas fa-video mr-2"></i>Create Live Class
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($role === 'student'): ?>
                <!-- Student Ongoing Live Classes -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Ongoing Live Classes</h2>
                    
                    <?php if (empty($live_classes)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg">No ongoing live classes at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($live_classes as $live_class): ?>
                                <div class="bg-white rounded-lg shadow-md border-l-4 border-red-600 p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($live_class['title']); ?></h3>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($live_class['subject_name']); ?> - <?php echo htmlspecialchars($live_class['stream_name']); ?>
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            <i class="fas fa-circle text-red-600 mr-1"></i>
                                            LIVE
                                        </span>
                                    </div>
                                    
                                    <!-- Teacher Info -->
                                    <?php if (isset($live_class['first_name'])): ?>
                                        <div class="flex items-center mb-4">
                                            <?php if ($live_class['profile_picture']): ?>
                                                <img src="../<?php echo htmlspecialchars($live_class['profile_picture']); ?>" 
                                                     alt="Teacher" 
                                                     class="w-10 h-10 rounded-full object-cover mr-2"
                                                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode(trim(($live_class['first_name'] ?? '') . ' ' . ($live_class['second_name'] ?? ''))); ?>&background=dc2626&color=fff&size=128'">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-2">
                                                    <i class="fas fa-user text-red-600"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars(trim(($live_class['first_name'] ?? '') . ' ' . ($live_class['second_name'] ?? ''))); ?>
                                                </p>
                                                <p class="text-xs text-gray-500">Teacher</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($live_class['description']): ?>
                                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($live_class['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <a href="../player/live_player.php?id=<?php echo $live_class['id']; ?>" 
                                       class="block w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-center text-sm font-medium">
                                        <i class="fas fa-play mr-2"></i>Join Live Class
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Live Class Modal (Teachers Only) -->
    <?php if ($role === 'teacher'): ?>
        <div id="createModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Create New Live Class</h3>
                    <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="createLiveClassForm" class="space-y-4">
                    <input type="hidden" id="teacher_assignment_id" name="teacher_assignment_id" value="">
                    
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" id="title" name="title" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
                    </div>
                    
                    <div>
                        <label for="youtube_url" class="block text-sm font-medium text-gray-700 mb-1">YouTube URL *</label>
                        <input type="url" id="youtube_url" name="youtube_url" required
                               placeholder="https://www.youtube.com/watch?v=..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                        <p class="text-xs text-gray-500 mt-1">Enter the YouTube Live URL</p>
                    </div>
                    
                    <div>
                        <label for="scheduled_start_time" class="block text-sm font-medium text-gray-700 mb-1">Scheduled Start Time (Optional)</label>
                        <input type="datetime-local" id="scheduled_start_time" name="scheduled_start_time"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="closeCreateModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Create Live Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Create Live Class Modal
        function openCreateModal(assignmentId = null) {
            if (assignmentId) {
                document.getElementById('teacher_assignment_id').value = assignmentId;
            }
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
            document.getElementById('createLiveClassForm').reset();
        }

        document.getElementById('createLiveClassForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('create_live_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Live class created successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create live class'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating live class');
            });
        });

        // Start Live Class
        function startLiveClass(liveClassId) {
            if (!confirm('Are you sure you want to start this live class? Students will be able to join.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('live_class_id', liveClassId);
            
            fetch('start_live_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Live class started successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to start live class'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error starting live class');
            });
        }

        // Delete Live Class
        function deleteLiveClass(liveClassId) {
            if (!confirm('Are you sure you want to delete this live class? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('live_class_id', liveClassId);
            
            fetch('delete_live_class.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Live class deleted successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete live class'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting live class');
            });
        }

        // Close modal on outside click
        document.getElementById('createModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });
    </script>
</body>
</html>

