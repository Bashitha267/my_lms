<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$course_id = intval($_GET['id'] ?? 0);
$success_msg = '';
$error_msg = '';

// Get Course Details
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$c_res = $stmt->get_result();
if ($c_res->num_rows === 0) {
    echo "Course not found.";
    exit();
}
$course = $c_res->fetch_assoc();
$stmt->close();

// Permissions Check
$is_teacher = ($role === 'teacher' && $course['teacher_id'] === $user_id);
$is_student = false;
$access_level = 'none'; // none, limited (free only), full

if ($is_teacher) {
    $access_level = 'full';
} else if ($role === 'student') {
    $stmt = $conn->prepare("SELECT status, payment_status FROM course_enrollments WHERE course_id = ? AND student_id = ?");
    $stmt->bind_param("is", $course_id, $user_id);
    $stmt->execute();
    $e_res = $stmt->get_result();
    if ($e_res->num_rows > 0) {
        $enrollment = $e_res->fetch_assoc();
        $is_student = true;
        if ($enrollment['payment_status'] === 'paid' || $enrollment['payment_status'] === 'free') {
            $access_level = 'full';
        } else {
            $access_level = 'limited';
        }
    } else {
        // Not enrolled
        header("Location: online_courses.php");
        exit();
    }
    $stmt->close();
} else {
    // Other roles? Admin?
    if ($role === 'admin') $access_level = 'full';
}

// Helper to get YT ID
function getYoutubeId($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return isset($match[1]) ? $match[1] : null;
}

// Handle Delete Recording (Teacher)
if ($is_teacher && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    // Verify ownership via course
    $check_stmt = $conn->prepare("SELECT id FROM course_recordings WHERE id = ? AND course_id = ?");
    $check_stmt->bind_param("ii", $del_id, $course_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $del_stmt = $conn->prepare("DELETE FROM course_recordings WHERE id = ?");
        $del_stmt->bind_param("i", $del_id);
        if ($del_stmt->execute()) {
            $success_msg = "Recording deleted successfully.";
        } else {
            $error_msg = "Error deleting recording.";
        }
        $del_stmt->close();
    }
    $check_stmt->close();
}

// Handle Add Recording (Teacher)
if ($is_teacher && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_recording'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    
    // Video URL input
    $video_url = trim($_POST['video_url']);
    
    if (!empty($video_url)) {
        // Generate Thumbnail
        $thumb_url = null;
        $yt_id = getYoutubeId($video_url);
        if ($yt_id) {
            $thumb_url = "https://img.youtube.com/vi/$yt_id/mqdefault.jpg";
        }

        // Save URL
        $stmt = $conn->prepare("INSERT INTO course_recordings (course_id, title, description, video_path, thumbnail_url, is_free) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssi", $course_id, $title, $desc, $video_url, $thumb_url, $is_free);
        if ($stmt->execute()) {
            $success_msg = "Video added successfully.";
        } else {
            $error_msg = "Error adding video: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error_msg = "Video URL is required.";
    }
}

// Get Recordings
$recordings = [];
$stmt = $conn->prepare("SELECT * FROM course_recordings WHERE course_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $recordings[] = $row;
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($course['title']); ?> - Content</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { red: { 600: '#dc2626', 700: '#b91c1c' } } }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-10 px-4">
        <!-- Breadcrumb -->
        <nav class="text-sm font-medium text-gray-500 mb-4">
            <a href="online_courses.php" class="hover:text-red-600">Courses</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900"><?php echo htmlspecialchars($course['title']); ?></span>
        </nav>
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8 flex flex-col md:flex-row justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($course['title']); ?></h1>
                <p class="mt-2 text-gray-600 max-w-2xl"><?php echo htmlspecialchars($course['description']); ?></p>
                <?php if($access_level === 'limited'): ?>
                    <p class="mt-2 text-yellow-600 font-semibold text-sm bg-yellow-50 inline-block px-2 py-1 rounded">
                        <i class="fas fa-lock mr-1"></i> Payment Pending - Access Limited to Free Previews
                    </p>
                <?php endif; ?>
            </div>
            <?php if($is_teacher): ?>
                <button onclick="openAddModal()" class="mt-4 md:mt-0 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 shadow flex items-center">
                    <i class="fas fa-video mr-2"></i> Add Video
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Videos Grid -->
        <?php if(empty($recordings)): ?>
            <div class="text-center py-12 bg-white rounded-lg border border-dashed border-gray-300">
                <i class="fas fa-video text-gray-300 text-4xl mb-3"></i>
                <p class="text-gray-500">No content uploaded yet.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach($recordings as $rec): 
                    $locked = ($access_level === 'limited' && !$rec['is_free'] && !$is_teacher);
                ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-all group flex flex-col h-full">
                        <!-- Thumbnail -->
                        <div class="aspect-video bg-gray-100 relative overflow-hidden group-hover:opacity-95 transition-opacity">
                            <?php if(!empty($rec['thumbnail_url'])): ?>
                                <img src="<?php echo htmlspecialchars($rec['thumbnail_url']); ?>" alt="Thumbnail" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <i class="fas <?php echo $locked ? 'fa-lock' : 'fa-play'; ?> text-3xl opacity-50"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Overlay Gradient -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-60"></div>

                            <?php if($rec['is_free']): ?>
                                <span class="absolute top-2 right-2 bg-green-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold shadow-sm uppercase tracking-wider">Free</span>
                            <?php endif; ?>
                            
                            <!-- Play Button Overlay -->
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <div class="w-10 h-10 bg-red-600 rounded-full flex items-center justify-center text-white shadow-lg transform scale-0 group-hover:scale-100 transition-transform">
                                    <i class="fas <?php echo $locked ? 'fa-lock' : 'fa-play'; ?> text-sm"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-4 flex flex-col flex-1">
                            <h3 class="font-bold text-gray-900 line-clamp-2 mb-1 group-hover:text-red-600 transition-colors" title="<?php echo htmlspecialchars($rec['title']); ?>">
                                <?php echo htmlspecialchars($rec['title']); ?>
                            </h3>
                            
                            <div class="flex items-center text-xs text-gray-500 mb-4">
                                <i class="far fa-clock mr-1.5"></i> <?php echo date('M d, Y', strtotime($rec['created_at'])); ?>
                            </div>
                            
                            <div class="mt-auto flex items-center justify-between gap-2 pt-3 border-t border-gray-100">
                                <?php if($locked): ?>
                                    <button disabled class="flex-1 py-2 bg-gray-100 text-gray-400 rounded-lg text-sm font-medium cursor-not-allowed">
                                        <i class="fas fa-lock mr-1"></i> Locked
                                    </button>
                                <?php else: ?>
                                    <a href="course_player.php?id=<?php echo $rec['id']; ?>" class="flex-1 text-center py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-600 hover:text-white transition font-medium text-sm">
                                        Watch Now
                                    </a>
                                <?php endif; ?>

                                <?php if($is_teacher): ?>
                                    <form method="POST" onsubmit="return confirm('Delete this video?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $rec['id']; ?>">
                                        <button type="submit" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add Video Modal -->
    <div id="addModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-bold mb-4">Add Course Video</h3>
            <form method="POST" class="space-y-4">
                <input type="text" name="title" placeholder="Video Title" required class="w-full border p-2 rounded">
                <textarea name="description" placeholder="Description" rows="3" class="w-full border p-2 rounded"></textarea>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">YouTube Video URL</label>
                    <input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=..." required class="w-full border p-2 rounded">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="is_free" id="is_free" class="mr-2">
                    <label for="is_free">Allow as Free Preview?</label>
                </div>
                <div class="flex justify-end pt-4">
                    <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="mr-2 px-4 py-2 bg-gray-200 rounded">Cancel</button>
                    <button type="submit" name="add_recording" class="px-4 py-2 bg-red-600 text-white rounded">Upload</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); }
    </script>
</body>
</html>
