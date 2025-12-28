<?php
require_once '../check_session.php';
require_once '../config.php';

// Get session variables
$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$second_name = $_SESSION['second_name'] ?? '';
$session_token = $_SESSION['session_token'] ?? '';

$stream_subject_id = isset($_GET['stream_subject_id']) ? intval($_GET['stream_subject_id']) : 0;
$academic_year = isset($_GET['academic_year']) ? intval($_GET['academic_year']) : date('Y');

$success_message = '';
$error_message = '';

// Get teacher assignment ID for current user (if teacher) or find assignments for this stream-subject-year
$teacher_assignment_id = null;
$teacher_assignment_ids = [];

if ($stream_subject_id > 0) {
    if ($role === 'teacher') {
        // Find the teacher's specific assignment using session user_id
        $query = "SELECT id FROM teacher_assignments 
                  WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active' 
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $teacher_assignment_id = $row['id'];
            $teacher_assignment_ids = [$teacher_assignment_id];
        }
        $stmt->close();
    } else {
        // For students: get all active teacher assignments for this stream-subject-year
        $query = "SELECT id FROM teacher_assignments 
                  WHERE stream_subject_id = ? AND academic_year = ? AND status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $stream_subject_id, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $teacher_assignment_ids[] = $row['id'];
        }
        $stmt->close();
    }
}

// Handle form submission for adding new recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_recording']) && $role === 'teacher') {
    // Verify session is still valid
    if (empty($user_id) || empty($session_token)) {
        $error_message = 'Session expired. Please login again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        
        // Get teacher_assignment_id from session user_id (not from POST for security)
        // Use the stream_subject_id and academic_year from GET parameters
        $teacher_assignment_id_for_recording = null;
        if ($stream_subject_id > 0) {
            $query = "SELECT id FROM teacher_assignments 
                      WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active' 
                      LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $teacher_assignment_id_for_recording = $row['id'];
            } else {
                $error_message = 'You do not have an active assignment for this subject and academic year.';
            }
            $stmt->close();
        }
        
        if (empty($title) || empty($youtube_url)) {
            $error_message = 'Title and YouTube URL are required.';
        } elseif (!$teacher_assignment_id_for_recording) {
            // Error message already set above
        } else {
        // Extract YouTube video ID from various URL formats
        $youtube_video_id = '';
        
        // Pattern 1: https://www.youtube.com/watch?v=VIDEO_ID
        // Pattern 2: https://youtu.be/VIDEO_ID
        // Pattern 3: https://www.youtube.com/embed/VIDEO_ID
        // Pattern 4: https://youtube.com/watch?v=VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $matches)) {
            $youtube_video_id = $matches[1];
        } else {
            $error_message = 'Invalid YouTube URL. Please provide a valid YouTube video link.';
        }
        
            if (empty($error_message) && !empty($youtube_video_id)) {
                // Generate thumbnail URL
                $thumbnail_url = "https://img.youtube.com/vi/{$youtube_video_id}/maxresdefault.jpg";
                
                // Insert recording using teacher_assignment_id from session user_id
                $stmt = $conn->prepare("INSERT INTO recordings (teacher_assignment_id, title, description, youtube_video_id, youtube_url, thumbnail_url, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("isssss", $teacher_assignment_id_for_recording, $title, $description, $youtube_video_id, $youtube_url, $thumbnail_url);
                
                if ($stmt->execute()) {
                    $success_message = 'Recording added successfully!';
                    // Clear form
                    $_POST = array();
                    // Refresh teacher assignment ID
                    if ($stream_subject_id > 0) {
                        $query = "SELECT id FROM teacher_assignments 
                                  WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ? AND status = 'active' 
                                  LIMIT 1";
                        $stmt2 = $conn->prepare($query);
                        $stmt2->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
                        $stmt2->execute();
                        $result2 = $stmt2->get_result();
                        if ($result2->num_rows > 0) {
                            $row2 = $result2->fetch_assoc();
                            $teacher_assignment_id = $row2['id'];
                            $teacher_assignment_ids = [$teacher_assignment_id];
                        }
                        $stmt2->close();
                    }
                } else {
                    $error_message = 'Error adding recording: ' . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

// Get subject and stream information
$subject_info = null;
if ($stream_subject_id > 0) {
    $query = "SELECT s.name as stream_name, sub.name as subject_name, sub.code as subject_code
              FROM stream_subjects ss
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              WHERE ss.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $stream_subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $subject_info = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get recordings for this teacher assignment(s)
$recordings = [];
if (!empty($teacher_assignment_ids)) {
    // Create placeholders for IN clause
    $placeholders = str_repeat('?,', count($teacher_assignment_ids) - 1) . '?';
    $query = "SELECT r.id, r.title, r.description, r.youtube_video_id, r.youtube_url, r.thumbnail_url, 
                     r.view_count, r.status, r.created_at,
                     u.first_name, u.second_name
              FROM recordings r
              INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
              INNER JOIN users u ON ta.teacher_id = u.user_id
              WHERE r.teacher_assignment_id IN ($placeholders) AND r.status = 'active'
              ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($query);
    $types = str_repeat('i', count($teacher_assignment_ids));
    $stmt->bind_param($types, ...$teacher_assignment_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recordings[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordings - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div class="mb-4 sm:mb-0">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Recordings</h1>
                        <?php if ($subject_info): ?>
                            <p class="text-gray-600">
                                <span class="font-semibold"><?php echo htmlspecialchars($subject_info['subject_name']); ?></span>
                                <?php if ($subject_info['subject_code']): ?>
                                    <span class="text-gray-500">(<?php echo htmlspecialchars($subject_info['subject_code']); ?>)</span>
                                <?php endif; ?>
                                - <?php echo htmlspecialchars($subject_info['stream_name']); ?>
                            </p>
                            <p class="text-gray-500 text-sm mt-1">Academic Year: <?php echo htmlspecialchars($academic_year); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-3">
                        <a href="recordings.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back
                        </a>
                        <?php if ($role === 'teacher' && $teacher_assignment_id): ?>
                            <button onclick="openAddModal()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add New Recording
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6" role="alert">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6" role="alert">
                    <div class="flex">
                        <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recordings Grid -->
            <?php if ($role === 'teacher' && !$teacher_assignment_id): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded mb-6">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm text-yellow-700">You don't have an active assignment for this subject and academic year.</p>
                    </div>
                </div>
            <?php elseif (empty($recordings)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                    <p class="text-gray-500 text-lg">No recordings available for this subject.</p>
                    <?php if ($role === 'teacher' && $teacher_assignment_id): ?>
                        <p class="text-gray-400 text-sm mt-2">Click "Add New Recording" to upload your first video.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($recordings as $recording): ?>
                        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow overflow-hidden cursor-pointer" onclick="playVideo('<?php echo htmlspecialchars($recording['youtube_video_id']); ?>')">
                            <!-- Thumbnail -->
                            <div class="relative aspect-video bg-gray-200">
                                <img src="<?php echo htmlspecialchars($recording['thumbnail_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($recording['title']); ?>"
                                     class="w-full h-full object-cover"
                                     onerror="this.src='https://img.youtube.com/vi/<?php echo htmlspecialchars($recording['youtube_video_id']); ?>/hqdefault.jpg'">
                                <!-- Play Button Overlay -->
                                <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30 hover:bg-opacity-40 transition-opacity">
                                    <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </div>
                            </div>
                            <!-- Video Info -->
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2" title="<?php echo htmlspecialchars($recording['title']); ?>">
                                    <?php echo htmlspecialchars($recording['title']); ?>
                                </h3>
                                <?php if ($recording['description']): ?>
                                    <p class="text-sm text-gray-600 mb-2 line-clamp-2">
                                        <?php echo htmlspecialchars(substr($recording['description'], 0, 100)); ?>
                                        <?php echo strlen($recording['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span><?php echo date('M d, Y', strtotime($recording['created_at'])); ?></span>
                                    <?php if ($recording['view_count'] > 0): ?>
                                        <span><?php echo number_format($recording['view_count']); ?> views</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($recording['first_name'] || $recording['second_name']): ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        By: <?php echo htmlspecialchars(trim(($recording['first_name'] ?? '') . ' ' . ($recording['second_name'] ?? ''))); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Recording Modal (Teachers Only) -->
    <?php if ($role === 'teacher' && $teacher_assignment_id): ?>
        <div id="addModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Add New Recording</h3>
                    <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="" class="space-y-4">
                    <!-- Note: teacher_assignment_id is retrieved from session user_id, not from form for security -->
                    
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" id="title" name="title" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="youtube_url" class="block text-sm font-medium text-gray-700 mb-1">YouTube URL *</label>
                        <input type="url" id="youtube_url" name="youtube_url" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               placeholder="https://www.youtube.com/watch?v=..." 
                               value="<?php echo htmlspecialchars($_POST['youtube_url'] ?? ''); ?>">
                        <p class="text-xs text-gray-500 mt-1">Supports: youtube.com/watch?v=, youtu.be/, youtube.com/embed/</p>
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="closeAddModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" name="add_recording"
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Add Recording
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Video Player Modal -->
    <div id="videoModal" class="hidden fixed inset-0 bg-black bg-opacity-90 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 w-full max-w-4xl">
            <div class="flex justify-end mb-4">
                <button onclick="closeVideoModal()" class="text-white hover:text-gray-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="aspect-video bg-black">
                <iframe id="videoPlayer" 
                        class="w-full h-full" 
                        src="" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function playVideo(videoId) {
            const modal = document.getElementById('videoModal');
            const player = document.getElementById('videoPlayer');
            player.src = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
            modal.classList.remove('hidden');
            
            // Update view count (optional - you can add an AJAX call here)
        }

        function closeVideoModal() {
            const modal = document.getElementById('videoModal');
            const player = document.getElementById('videoPlayer');
            player.src = '';
            modal.classList.add('hidden');
        }

        // Close modals on outside click
        document.getElementById('addModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        document.getElementById('videoModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeVideoModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeVideoModal();
            }
        });
    </script>
</body>
</html>

