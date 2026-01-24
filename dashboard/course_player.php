<?php
session_start();
require_once '../config.php';

// disable error reporting for API responses
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax'])) { http_response_code(401); exit; }
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$recording_id = intval($_GET['id'] ?? 0);

function getYoutubeId($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return isset($match[1]) ? $match[1] : null;
}

// --- AJAX HANDLERS ---

// 1. Get Chat
// 1. Get Chat
if (isset($_GET['ajax']) && $_GET['action'] === 'get_chat') {
    header('Content-Type: application/json');
    $last_id = intval($_GET['last_id'] ?? 0);
    
    $query = "SELECT c.id, c.user_id, c.message, c.created_at
              FROM course_chats c 
              WHERE c.course_recording_id = ? AND c.id > ? 
              ORDER BY c.id ASC LIMIT 50";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $recording_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Fetch User Info Separately
        $u_stmt = $conn->prepare("SELECT first_name, second_name, role, profile_picture FROM users WHERE user_id = ?");
        $u_stmt->bind_param("s", $row['user_id']);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        
        $first_name = 'Unknown';
        $second_name = '';
        $u_role = 'student';
        $profile_picture = null;
        
        if ($u_info = $u_res->fetch_assoc()) {
            $first_name = $u_info['first_name'];
            $second_name = $u_info['second_name'];
            $u_role = $u_info['role'];
            $profile_picture = $u_info['profile_picture'];
        }
        $u_stmt->close();

        $messages[] = [
            'id' => $row['id'],
            'user' => $first_name . ' ' . substr($second_name, 0, 1),
            'avatar' => $profile_picture ? '../'.$profile_picture : null,
            'message' => $row['message'],
            'is_me' => ($row['user_id'] === $user_id),
            'role' => $u_role,
            'time' => date('H:i', strtotime($row['created_at']))
        ];
    }
    echo json_encode(['messages' => $messages]);
    exit;
}

// 2. Send Chat
if (isset($_POST['ajax']) && $_POST['action'] === 'send_chat') {
    header('Content-Type: application/json');
    $message = trim($_POST['message'] ?? '');
    if (!empty($message) && $recording_id > 0) {
        $stmt = $conn->prepare("INSERT INTO course_chats (course_recording_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $recording_id, $user_id, $message);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }
    exit;
}

// --- MAIN PAGE LOGIC ---

// Get Recording & Course Info
$stmt = $conn->prepare("SELECT r.*, c.teacher_id, c.title as course_title, c.id as course_id 
                        FROM course_recordings r 
                        JOIN courses c ON r.course_id = c.id 
                        WHERE r.id = ?");
$stmt->bind_param("i", $recording_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { die("Recording not found."); }
$recording = $res->fetch_assoc();
$stmt->close();
$course_id = $recording['course_id'];

// Check Access
$access = false;
if ($role === 'teacher' && $recording['teacher_id'] === $user_id) $access = true;
else {
    // Check Enrollment
    $stmt = $conn->prepare("SELECT payment_status FROM course_enrollments WHERE course_id = ? AND student_id = ?");
    $stmt->bind_param("is", $course_id, $user_id);
    $stmt->execute();
    $e_res = $stmt->get_result();
    if ($e_res->num_rows > 0) {
        $enrollment = $e_res->fetch_assoc();
        if ($enrollment['payment_status'] === 'paid' || $enrollment['payment_status'] === 'free' || $recording['is_free']) {
            $access = true;
        }
    } else {
         // Not enrolled, check if free
         // For now, assume simplified: must enroll to see even free videos if not teacher? 
         // Logic in course_content allowed checking details.
         // Let's allow if IS_FREE regardless of enrollment? No, strict enrollment first usually.
         // I'll stick to: Must be enrolled OR Teacher.
    }
    $stmt->close();
}

if (!$access) { echo "Access Denied. Check enrollment or payment."; exit; }

// Increment Views
if ($role === 'student') {
    $conn->query("UPDATE course_recordings SET views = views + 1 WHERE id = $recording_id");
}

// Handle File Upload (Teacher)
if ($role === 'teacher' && isset($_POST['upload_resource'])) {
    if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
        $title = $_POST['resource_title'];
        $upload_dir = '../uploads/courses/resources/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = uniqid('res_') . '_' . $_FILES['resource_file']['name'];
        if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $upload_dir . $filename)) {
            $path = 'uploads/courses/resources/' . $filename;
            $stmt = $conn->prepare("INSERT INTO course_uploads (course_id, title, file_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $course_id, $title, $path);
            $stmt->execute();
        }
    }
}

// Get Resources
$resources = [];
$r_res = $conn->query("SELECT * FROM course_uploads WHERE course_id = $course_id ORDER BY uploaded_at DESC");
while($row = $r_res->fetch_assoc()) $resources[] = $row;

?>
<?php
// Get Other Videos (Sidebar)
$other_recordings = [];
$o_sql = "SELECT id, title, thumbnail_url, created_at, is_free, views FROM course_recordings WHERE course_id = ? AND id != ? ORDER BY created_at DESC";
$o_stmt = $conn->prepare($o_sql);
$o_stmt->bind_param("ii", $course_id, $recording_id);
$o_stmt->execute();
$o_res = $o_stmt->get_result();
while($row = $o_res->fetch_assoc()) $other_recordings[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recording['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://www.youtube.com/iframe_api"></script>
    <style>
        /* Custom Scrollbar for Dark Theme */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #1a1a1a; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #444; }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Range Input Styling */
        input[type="range"] {
            -webkit-appearance: none;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
            height: 4px;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 12px;
            height: 12px;
            background: #dc2626;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.1s;
        }
        input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.2); }
    </style>
</head>
<body class="bg-black text-white h-screen overflow-hidden flex font-sans">

    <!-- Left Sidebar: Other Videos -->
    <aside class="w-80 bg-[#121212] border-r border-[#222] hidden lg:flex flex-col z-20">
        <div class="p-4 border-b border-[#222]">
            <h2 class="font-bold text-gray-200">Course Content</h2>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-3">
            <?php foreach($other_recordings as $vid): ?>
                <a href="course_player.php?id=<?php echo $vid['id']; ?>" class="block group">
                    <div class="flex gap-3 p-2 rounded-lg hover:bg-[#1f1f1f] transition">
                        <div class="w-24 h-14 bg-[#2a2a2a] rounded overflow-hidden flex-shrink-0 relative">
                            <?php if(!empty($vid['thumbnail_url'])): ?>
                                <img src="<?php echo htmlspecialchars($vid['thumbnail_url']); ?>" class="w-full h-full object-cover group-hover:opacity-80 transition">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-600">
                                    <i class="fas fa-play"></i>
                                </div>
                            <?php endif; ?>
                            <?php if($vid['is_free']): ?>
                                <span class="absolute bottom-0.5 right-0.5 bg-green-600 text-[9px] px-1 rounded text-white py-px">FREE</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-medium text-gray-300 group-hover:text-white line-clamp-2 transition mb-1">
                                <?php echo htmlspecialchars($vid['title']); ?>
                            </h4>
                            <div class="text-xs text-gray-500">
                                <i class="far fa-clock mr-1"></i> <?php echo date('M d', strtotime($vid['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
            <?php if(empty($other_recordings)): ?>
                <div class="text-center py-8 text-gray-500 text-sm">No other videos.</div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 relative">
        <!-- Top Bar (Back Button & Title) -->
        <div class="h-14 bg-[#121212] border-b border-[#222] flex items-center px-4 justify-between shrink-0">
            <div class="flex items-center gap-4 min-w-0">
                <a href="online_courses.php" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="font-semibold text-lg truncate"><?php echo htmlspecialchars($recording['title']); ?></h1>
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-400">
                <span><i class="fas fa-eye mr-1.5"></i> <?php echo $recording['views']; ?></span>
            </div>
        </div>

        <!-- Video Player Wrapper -->
        <div class="bg-black relative group w-full" style="aspect-ratio: 16/9; max-height: calc(100vh - 3.5rem - 150px);">
            <?php $yt_id = getYoutubeId($recording['video_path']); ?>
            
            <?php if($yt_id): ?>
                <!-- YouTube Player Container -->
                <div id="player" class="w-full h-full"></div>
                
                <!-- Transparent Overlay to Block Direct Interaction -->
                <div id="overlay" class="absolute inset-0 z-10 w-full h-full bg-transparent cursor-pointer" onclick="togglePlay()"></div>
                
                <!-- Custom Controls Layer -->
                <div id="controls" class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/90 via-black/50 to-transparent z-20 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col gap-2">
                    <!-- Progress Bar -->
                    <div class="relative h-1 bg-gray-600 rounded cursor-pointer group/progress" onclick="seek(event)">
                        <div id="progressBar" class="absolute h-full bg-red-600 rounded w-0"></div>
                        <div class="absolute h-3 w-3 bg-red-600 rounded-full top-1/2 -mt-1.5 -ml-1.5 hidden group-hover/progress:block shadow" style="left: 0%" id="progressThumb"></div>
                    </div>
                    
                    <!-- Control Buttons -->
                    <div class="flex items-center justify-between mt-1">
                        <div class="flex items-center gap-4">
                            <button onclick="togglePlay()" class="text-white hover:text-red-500 w-8 text-left">
                                <i id="playIcon" class="fas fa-play text-lg"></i>
                            </button>
                            <!-- Volume -->
                            <div class="flex items-center gap-2 group/vol">
                                <button onclick="toggleMute()" class="text-gray-300 hover:text-white w-6">
                                    <i id="volIcon" class="fas fa-volume-up"></i>
                                </button>
                                <input type="range" min="0" max="100" value="100" class="w-20 hidden group-hover/vol:block accent-red-600" oninput="setVolume(this.value)">
                            </div>
                            <span id="timeDisplay" class="text-xs text-gray-400 font-mono">0:00 / 0:00</span>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <button onclick="toggleFullscreen()" class="text-gray-300 hover:text-white">
                                <i class="fas fa-expand"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Fallback for Non-YT -->
                <video controls class="w-full h-full" controlsList="nodownload">
                    <source src="../<?php echo htmlspecialchars($recording['video_path']); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php endif; ?>
        </div>

        <!-- Scrollable Content Below Video -->
        <div class="flex-1 overflow-y-auto bg-[#0f0f0f]">
            <div class="p-6 max-w-4xl mx-auto w-full">
                <!-- Description -->
                <div class="bg-[#1a1a1a] rounded-lg p-5 border border-[#333] mb-6">
                    <h3 class="font-bold text-gray-200 mb-2">Description</h3>
                    <p class="text-gray-400 text-sm leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($recording['description']); ?></p>
                </div>

                <!-- Resources -->
                <div class="bg-[#1a1a1a] rounded-lg p-5 border border-[#333]">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-gray-200">Resources</h3>
                         <?php if($role === 'teacher'): ?>
                            <button onclick="document.getElementById('uploadResModal').classList.remove('hidden')" class="text-xs bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 transition">
                                <i class="fas fa-plus mr-1"></i> Add Resource
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if(empty($resources)): ?>
                        <p class="text-gray-500 text-sm">No resources available.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach($resources as $res): ?>
                                <div class="flex items-center justify-between p-3 bg-[#222] rounded border border-[#333] hover:border-red-900 transition group">
                                    <div class="flex items-center gap-3 overflow-hidden">
                                        <div class="w-8 h-8 rounded bg-[#333] flex items-center justify-center text-red-500">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <span class="text-sm text-gray-300 truncate font-medium"><?php echo htmlspecialchars($res['title']); ?></span>
                                    </div>
                                    <a href="../<?php echo $res['file_path']; ?>" download class="text-gray-500 hover:text-white transition">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Right Sidebar: Chat -->
    <aside class="w-80 bg-[#121212] border-l border-[#222] flex flex-col z-20 hidden md:flex">
        <div class="p-4 border-b border-[#222] bg-[#1a1a1a]">
            <h2 class="font-bold text-gray-200 flex items-center gap-2">
                <i class="far fa-comments text-red-500"></i> Course Chat
            </h2>
        </div>
        <div id="chatBox" class="flex-1 overflow-y-auto p-4 space-y-3 scroll-smooth">
            <!-- Messages -->
        </div>
        <div class="p-4 bg-[#1a1a1a] border-t border-[#222]">
            <form id="chatForm" class="flex gap-2">
                <input type="text" id="chatInput" class="flex-1 bg-[#222] border border-[#333] rounded px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition" placeholder="Type message...">
                <button type="submit" class="bg-red-600 text-white rounded px-3 hover:bg-red-700 transition">
                    <i class="fas fa-paper-plane text-sm"></i>
                </button>
            </form>
        </div>
    </aside>

    <!-- Mobile Navigation Bottom (Simple toggle for chat/videos?) -->
    <!-- Keeping it simple for now, standard responsive hides sidebars -->

    <!-- Upload Resource Modal -->
    <div id="uploadResModal" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center z-50 backdrop-blur-sm">
        <div class="bg-[#1a1a1a] border border-[#333] p-6 rounded-lg shadow-2xl w-96">
            <h3 class="font-bold text-lg mb-4 text-white">Upload Resource</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="resource_title" placeholder="File Title" required class="w-full bg-[#222] border border-[#333] text-white p-2 mb-3 rounded focus:border-red-600 focus:outline-none">
                <input type="file" name="resource_file" required class="w-full bg-[#222] border border-[#333] text-gray-400 p-1 mb-4 rounded text-sm file:mr-4 file:py-1 file:px-2 file:rounded file:border-0 file:bg-[#333] file:text-white hover:file:bg-[#444]">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('uploadResModal').classList.add('hidden')" class="px-3 py-1 bg-[#333] text-gray-300 rounded hover:bg-[#444]">Cancel</button>
                    <button type="submit" name="upload_resource" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- YouTube & Chat Logic -->
    <script>
        // Chat Logic
        const recordingId = <?php echo $recording_id; ?>;
        const chatBox = document.getElementById('chatBox');
        let lastId = 0;

        async function fetchChat() {
            try {
                const res = await fetch(`?ajax=1&action=get_chat&id=${recordingId}&last_id=${lastId}`);
                const data = await res.json();
                if (data.messages && data.messages.length > 0) {
                    const wasAtBottom = chatBox.scrollHeight - chatBox.scrollTop === chatBox.clientHeight;
                    data.messages.forEach(msg => {
                        lastId = msg.id;
                        appendMessage(msg);
                    });
                    if(wasAtBottom || lastId === data.messages[0].id) {
                         chatBox.scrollTop = chatBox.scrollHeight;
                    }
                }
            } catch(e) { console.error("Chat error", e); }
        }

        function appendMessage(msg) {
            const div = document.createElement('div');
            const isMe = msg.is_me;
            div.className = `flex ${isMe ? 'justify-end' : 'justify-start'}`;
            // Dark theme message bubbles
            const bgClass = isMe ? 'bg-red-600 text-white' : 'bg-[#2a2a2a] text-gray-200 border border-[#333]';
            div.innerHTML = `
                <div class="max-w-[85%] ${bgClass} rounded-lg px-3 py-2 text-sm shadow-sm">
                    <div class="flex items-center gap-2 mb-0.5 opacity-80">
                        <span class="text-xs font-bold">${msg.user}</span>
                        <span class="text-[10px]">${msg.time}</span>
                    </div>
                    <p class="break-words leading-relaxed">${msg.message}</p>
                </div>
            `;
            chatBox.appendChild(div);
        }

        document.getElementById('chatForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('chatInput');
            const text = input.value.trim();
            if(!text) return;
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'send_chat');
            formData.append('message', text);
            try {
                await fetch('', { method: 'POST', body: formData });
                input.value = '';
                fetchChat();
            } catch(e) { alert('Error sending message'); }
        });
        setInterval(fetchChat, 2000);
        fetchChat();

        // YouTube Player Logic
        <?php if($yt_id): ?>
        var player;
        var progressInterval;
        const progressBar = document.getElementById('progressBar');
        const progressThumb = document.getElementById('progressThumb');
        const playIcon = document.getElementById('playIcon');
        const timeDisplay = document.getElementById('timeDisplay');
        const volIcon = document.getElementById('volIcon');

        function onYouTubeIframeAPIReady() {
            player = new YT.Player('player', {
                videoId: '<?php echo $yt_id; ?>',
                playerVars: { 
                    'autoplay': 0, 
                    'controls': 0, // Disable YT controls
                    'rel': 0, 
                    'modestbranding': 1,
                    'iv_load_policy': 3,
                    'disablekb': 1 
                },
                events: {
                    'onReady': onPlayerReady,
                    'onStateChange': onPlayerStateChange
                }
            });
        }

        function onPlayerReady(event) {
            updateProgress();
            progressInterval = setInterval(updateProgress, 500);
        }

        function onPlayerStateChange(event) {
            if (event.data == YT.PlayerState.PLAYING) {
                playIcon.className = 'fas fa-pause text-lg';
            } else {
                playIcon.className = 'fas fa-play text-lg';
            }
        }

        function togglePlay() {
            if (player.getPlayerState() == YT.PlayerState.PLAYING) {
                player.pauseVideo();
            } else {
                player.playVideo();
            }
        }

        function seek(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const pos = (event.clientX - rect.left) / rect.width;
            const duration = player.getDuration();
            player.seekTo(pos * duration);
        }

        function updateProgress() {
            if (!player || !player.getDuration) return;
            const current = player.getCurrentTime();
            const duration = player.getDuration();
            if (duration) {
                const percent = (current / duration) * 100;
                progressBar.style.width = percent + '%';
                progressThumb.style.left = percent + '%';
                timeDisplay.innerText = formatTime(current) + ' / ' + formatTime(duration);
            }
        }

        function setVolume(val) {
            player.setVolume(val);
            if(val == 0) volIcon.className = 'fas fa-volume-mute';
            else if(val < 50) volIcon.className = 'fas fa-volume-down';
            else volIcon.className = 'fas fa-volume-up';
        }

        function toggleMute() {
            if(player.isMuted()) {
                player.unMute();
                volIcon.className = 'fas fa-volume-up';
            } else {
                player.mute();
                volIcon.className = 'fas fa-volume-mute';
            }
        }

        function toggleFullscreen() {
            const elem = document.querySelector('.bg-black.relative.group'); // Wrapper
            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => {});
            } else {
                document.exitFullscreen();
            }
        }

        function formatTime(s) {
            const m = Math.floor(s / 60);
            const sec = Math.floor(s % 60);
            return m + ':' + (sec < 10 ? '0' : '') + sec;
        }
        <?php endif; ?>
    </script>
</body>
</html>
