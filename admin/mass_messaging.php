<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

// Only admins can access this page
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_mass_msg']) || isset($_POST['send_individual_msg']) || isset($_POST['send_marketing_msg'])) {
        $media_url = '';
        $media_file = $_FILES['media_file'] ?? null;
        
        if ($media_file && $media_file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/messaging/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
                @file_put_contents($upload_dir . 'index.html', ''); // Privacy
            }
            
            $file_ext = strtolower(pathinfo($media_file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_ext, $allowed)) {
                $file_name = 'img_' . time() . '_' . rand(100, 999) . '.' . $file_ext;
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($media_file['tmp_name'], $target_path)) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                    $media_url = $protocol . $_SERVER['HTTP_HOST'] . str_replace('/admin', '', dirname($_SERVER['REQUEST_URI'])) . '/uploads/messaging/' . $file_name;
                } else {
                    $error_message = "Failed to save uploaded image. Check folder permissions.";
                }
            } else {
                $error_message = "Invalid file type. Only JPG, PNG, and WEBP are allowed.";
            }
        }

        if (isset($_POST['send_mass_msg']) && !$error_message) {
            $roles = $_POST['roles'] ?? [];
            $message_text = trim($_POST['message'] ?? '');

            if (empty($roles)) {
                $error_message = "Please select at least one group.";
            } elseif (empty($message_text)) {
                $error_message = "Please enter a message.";
            } else {
                $role_placeholders = implode(',', array_fill(0, count($roles), '?'));
                $query = "SELECT whatsapp_number, mobile_number FROM users WHERE role IN ($role_placeholders) AND status = 1";
                $stmt = $conn->prepare($query);
                $stmt->bind_param(str_repeat('s', count($roles)), ...$roles);
                $stmt->execute();
                $result = $stmt->get_result();

                $sent_count = 0;
                $failed_count = 0;
                while ($row = $result->fetch_assoc()) {
                    $target_number = !empty($row['whatsapp_number']) ? $row['whatsapp_number'] : $row['mobile_number'];
                    if (!empty($target_number)) {
                        $res = $media_url ? sendWhatsAppMedia($target_number, $message_text, $media_url) : sendWhatsAppMessage($target_number, $message_text);
                        if ($res['success']) $sent_count++; else $failed_count++;
                    }
                }
                $success_message = "Broadcast complete. Sent: $sent_count, Failed: $failed_count";
            }
        } elseif (isset($_POST['send_individual_msg']) && !$error_message) {
            $target_number = $_POST['target_number'] ?? '';
            $message_text = trim($_POST['message'] ?? '');

            if (empty($target_number)) {
                $error_message = "Please select a user first.";
            } elseif (empty($message_text)) {
                $error_message = "Please enter a message.";
            } else {
                $response = $media_url ? sendWhatsAppMedia($target_number, $message_text, $media_url) : sendWhatsAppMessage($target_number, $message_text);
                if ($response['success']) {
                    $success_message = "Message sent successfully to " . htmlspecialchars($target_number);
                } else {
                    $error_message = "Failed to send: " . ($response['message'] ?? 'Unknown error');
                }
            }
        } elseif (isset($_POST['send_marketing_msg']) && !$error_message) {
            $message_text = trim($_POST['message'] ?? '');
            $numbers_file = $_FILES['numbers_file'] ?? null;

            if (!$numbers_file || $numbers_file['error'] !== UPLOAD_ERR_OK) {
                $error_message = "Please upload a valid .txt file with mobile numbers.";
            } else {
                $content = file_get_contents($numbers_file['tmp_name']);
                $numbers = preg_split('/\r\n|\r|\n/', $content);
                $numbers = array_filter(array_map('trim', $numbers));

                if (empty($numbers)) {
                    $error_message = "The uploaded file is empty.";
                }
                
                // Add a warning if localhost is detected
                if (strpos($media_url, 'localhost') !== false || strpos($media_url, '127.0.0.1') !== false) {
                    $error_message = "Warning: Media URL is local (" . htmlspecialchars($media_url) . "). Remote WhatsApp API will not be able to download this image. Please use a public URL.";
                }

                $sent_count = 0;
                $failed_count = 0;
                $last_error_raw = '';

                foreach ($numbers as $number) {
                    if (!empty($number)) {
                        $res = $media_url ? sendWhatsAppMedia($number, $message_text, $media_url) : sendWhatsAppMessage($number, $message_text);
                        if ($res['success']) {
                            $sent_count++;
                        } else {
                            $failed_count++;
                            $last_error_raw = $res['raw'] ?? '';
                        }
                    }
                }
                $success_message = "Marketing complete. Sent: $sent_count, Failed: $failed_count";
                if ($failed_count > 0 && $last_error_raw) {
                    echo "<script>console.error('WhatsApp API Error: ', " . json_encode($last_error_raw) . ");</script>";
                }
            }
        }
    }
}

// Handle AJAX Search
if (isset($_GET['search_query'])) {
    header('Content-Type: application/json');
    $q = "%" . $conn->real_escape_string($_GET['search_query']) . "%";
    $search_res = $conn->query("SELECT user_id, first_name, second_name, whatsapp_number, mobile_number, role FROM users WHERE (first_name LIKE '$q' OR second_name LIKE '$q' OR user_id LIKE '$q' OR mobile_number LIKE '$q' OR whatsapp_number LIKE '$q') AND status = 1 LIMIT 5");
    $users = [];
    while ($row = $search_res->fetch_assoc()) {
        $users[] = [
            'name' => $row['first_name'] . ' ' . $row['second_name'],
            'number' => !empty($row['whatsapp_number']) ? $row['whatsapp_number'] : $row['mobile_number'],
            'role' => ucfirst($row['role']),
            'id' => $row['user_id']
        ];
    }
    echo json_encode($users);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messaging | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50">
    <?php include 'header.php'; ?>

    <div class="max-w-3xl mx-auto px-4 py-6">
        <div class="glass-card p-6 md:p-10">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-200">
                    <i class="fas fa-paper-plane text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-black text-slate-800 tracking-tight">Messaging Portal</h1>
                    <p class="text-slate-500 text-[10px] font-medium">Broadcast WhatsApp messages efficiently.</p>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-emerald-50 border border-emerald-100 p-3 rounded-xl text-emerald-700 text-xs font-bold mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-rose-50 border border-rose-100 p-3 rounded-xl text-rose-700 text-xs font-bold mb-6 flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Mobile Friendly Tab System -->
            <div class="mb-8 -mx-6 md:mx-0 px-6 md:px-0 overflow-x-auto no-scrollbar scroll-smooth">
                <div class="flex border-b border-slate-100 min-w-max md:min-w-0">
                    <button onclick="switchTab('mass')" id="tab-mass" 
                            class="flex items-center gap-2 px-6 py-4 font-black text-[10px] uppercase tracking-widest border-b-2 border-blue-600 text-blue-600 transition-all whitespace-nowrap active:bg-slate-50">
                        <i class="fas fa-users text-xs"></i> Mass Broadcast
                    </button>
                    <button onclick="switchTab('individual')" id="tab-individual" 
                            class="flex items-center gap-2 px-6 py-4 font-black text-[10px] uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition-all whitespace-nowrap active:bg-slate-50">
                        <i class="fas fa-user text-xs"></i> Individual Message
                    </button>
                    <button onclick="switchTab('marketing')" id="tab-marketing" 
                            class="flex items-center gap-2 px-6 py-4 font-black text-[10px] uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition-all whitespace-nowrap active:bg-slate-50">
                        <i class="fas fa-bullhorn text-xs"></i> Marketing Message
                    </button>
                </div>
            </div>

            <!-- Mass Broadcast Form -->
            <form method="POST" id="form-mass" class="space-y-8" enctype="multipart/form-data">
                <div class="space-y-4">
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Select Target Groups</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="relative flex items-center p-3 rounded-xl border-2 border-slate-100 hover:border-blue-500 transition-all cursor-pointer group">
                            <input type="checkbox" name="roles[]" value="student" class="hidden peer">
                            <div class="w-5 h-5 border-2 border-slate-300 rounded-full flex items-center justify-center peer-checked:bg-blue-600 peer-checked:border-blue-600 transition-all">
                                <i class="fas fa-check text-[10px] text-white"></i>
                            </div>
                            <div class="ml-3">
                                <span class="block text-xs font-bold text-slate-700">Students</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Active Students</span>
                            </div>
                        </label>

                        <label class="relative flex items-center p-4 rounded-2xl border-2 border-slate-100 hover:border-blue-500 transition-all cursor-pointer group">
                            <input type="checkbox" name="roles[]" value="teacher" class="hidden peer">
                            <div class="w-5 h-5 border-2 border-slate-300 rounded-full flex items-center justify-center peer-checked:bg-blue-600 peer-checked:border-blue-600 transition-all">
                                <i class="fas fa-check text-[10px] text-white"></i>
                            </div>
                            <div class="ml-3">
                                <span class="block text-xs font-bold text-slate-700">Teachers</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Active Teachers</span>
                            </div>
                        </label>

                        <label class="relative flex items-center p-4 rounded-2xl border-2 border-slate-100 hover:border-blue-500 transition-all cursor-pointer group">
                            <input type="checkbox" name="roles[]" value="instructor" class="hidden peer">
                            <div class="w-5 h-5 border-2 border-slate-300 rounded-full flex items-center justify-center peer-checked:bg-blue-600 peer-checked:border-blue-600 transition-all">
                                <i class="fas fa-check text-[10px] text-white"></i>
                            </div>
                            <div class="ml-3">
                                <span class="block text-xs font-bold text-slate-700">Instructors</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Active Instructors</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Message Content</label>
                        <div class="relative">
                            <textarea name="message" rows="4" required 
                                      placeholder="Type your announcement here..."
                                      class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl text-slate-700 text-sm font-medium focus:border-blue-500 focus:bg-white outline-none transition-all resize-none"></textarea>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Optional Image</label>
                        <div class="relative group">
                            <input type="file" name="media_file" accept="image/*" id="mass-media-input" class="hidden" onchange="previewImage(this, 'mass-preview')">
                            <label for="mass-media-input" class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50 hover:bg-white hover:border-blue-400 transition-all cursor-pointer overflow-hidden">
                                <div id="mass-preview" class="hidden absolute inset-0 bg-white">
                                    <img src="" class="w-full h-full object-contain">
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <span class="text-white text-[10px] font-bold uppercase tracking-widest">Change Image</span>
                                    </div>
                                </div>
                                <i class="fas fa-image text-slate-400 mb-2"></i>
                                <span class="text-xs font-bold text-slate-600" id="mass-media-label">Select image</span>
                                <span class="text-[9px] text-slate-400 mt-1 uppercase font-black">JPEG, PNG allowed</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="send_mass_msg" onclick="return confirm('Are you sure?')"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-3xl font-black text-xs uppercase tracking-widest transition-all shadow-xl shadow-blue-200 active:scale-[0.98] flex items-center justify-center gap-3">
                        Broadcast Now <i class="fas fa-paper-plane text-[10px] opacity-70"></i>
                    </button>
                </div>
            </form>

            <!-- Individual Message Form -->
            <form method="POST" id="form-individual" class="hidden space-y-8" enctype="multipart/form-data">
                <div class="space-y-4">
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Search & Select User</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-6 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="user-search" placeholder="Search by name, ID or mobile..." 
                               class="w-full pl-12 pr-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl text-slate-700 text-sm font-medium focus:border-blue-500 focus:bg-white outline-none transition-all">
                        
                        <!-- Search Results Dropdown -->
                        <div id="search-results" class="hidden absolute top-full left-0 w-full mt-2 bg-white border border-slate-100 rounded-3xl shadow-2xl z-50 overflow-hidden">
                        </div>
                    </div>

                    <!-- Selected User Info -->
                    <div id="selected-user-box" class="hidden p-4 bg-blue-50 border-2 border-blue-100 rounded-2xl flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold" id="user-initial">A</div>
                            <div>
                                <p class="text-sm font-black text-slate-800" id="user-display-name">User Name</p>
                                <p class="text-[10px] font-bold text-blue-600 uppercase tracking-tight" id="user-display-number">94771234567</p>
                            </div>
                        </div>
                        <button type="button" onclick="clearSelectedUser()" class="text-slate-400 hover:text-rose-500 transition-colors">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                    <input type="hidden" name="target_number" id="target_number">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Message</label>
                        <div class="relative">
                            <textarea name="message" rows="4" required 
                                      placeholder="Type your message..."
                                      class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl text-slate-700 text-sm font-medium focus:border-blue-500 focus:bg-white outline-none transition-all resize-none"></textarea>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Optional Image</label>
                        <div class="relative group">
                            <input type="file" name="media_file" accept="image/*" id="indiv-media-input" class="hidden" onchange="previewImage(this, 'indiv-preview')">
                            <label for="indiv-media-input" class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50 hover:bg-white hover:border-blue-400 transition-all cursor-pointer overflow-hidden">
                                <div id="indiv-preview" class="hidden absolute inset-0 bg-white">
                                    <img src="" class="w-full h-full object-contain">
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <span class="text-white text-[10px] font-bold uppercase tracking-widest">Change Image</span>
                                    </div>
                                </div>
                                <i class="fas fa-image text-slate-400 mb-2"></i>
                                <span class="text-xs font-bold text-slate-600" id="indiv-media-label">Select image</span>
                                <span class="text-[9px] text-slate-400 mt-1 uppercase font-black">JPEG, PNG allowed</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="send_individual_msg"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-3xl font-black text-xs uppercase tracking-widest transition-all shadow-xl shadow-blue-200 active:scale-[0.98] flex items-center justify-center gap-3">
                        Send Message <i class="fas fa-paper-plane text-[10px] opacity-70"></i>
                    </button>
                </div>
            </form>

            <!-- Marketing Message Form -->
            <form method="POST" id="form-marketing" class="hidden space-y-8" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Upload Numbers (.txt)</label>
                        <div class="relative group">
                            <input type="file" name="numbers_file" accept=".txt" required id="numbers-file-input" class="hidden">
                            <label for="numbers-file-input" class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50 hover:bg-white hover:border-blue-400 transition-all cursor-pointer">
                                <i class="fas fa-file-alt text-slate-400 mb-2"></i>
                                <span class="text-xs font-bold text-slate-600" id="file-label">Select .txt file</span>
                                <span class="text-[9px] text-slate-400 mt-1 uppercase font-black">One number per line</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Optional Image</label>
                        <div class="relative group">
                            <input type="file" name="media_file" accept="image/*" id="media-file-input" class="hidden" onchange="previewImage(this, 'mkt-preview')">
                            <label for="media-file-input" class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50 hover:bg-white hover:border-blue-400 transition-all cursor-pointer overflow-hidden">
                                <div id="mkt-preview" class="hidden absolute inset-0 bg-white">
                                    <img src="" class="w-full h-full object-contain">
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <span class="text-white text-[10px] font-bold uppercase tracking-widest">Change Image</span>
                                    </div>
                                </div>
                                <i class="fas fa-image text-slate-400 mb-2"></i>
                                <span class="text-xs font-bold text-slate-600" id="media-label">Select image</span>
                                <span class="text-[9px] text-slate-400 mt-1 uppercase font-black">JPEG, PNG allowed</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Campaign Message</label>
                    <div class="relative">
                        <textarea name="message" rows="4" required 
                                  placeholder="Type your marketing campaign message here..."
                                  class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl text-slate-700 text-sm font-medium focus:border-blue-500 focus:bg-white outline-none transition-all resize-none"></textarea>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="send_marketing_msg" onclick="return confirm('Start marketing broadcast? This may take some time.')"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-3xl font-black text-xs uppercase tracking-widest transition-all shadow-xl shadow-blue-200 active:scale-[0.98] flex items-center justify-center gap-3">
                        Start Marketing Campaign <i class="fas fa-rocket text-[10px] opacity-70"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <div class="mt-8 text-center">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Communicate safely. Avoid spamming.</p>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const forms = ['mass', 'individual', 'marketing'];
            forms.forEach(f => {
                document.getElementById(`form-${f}`).classList.add('hidden');
                document.getElementById(`tab-${f}`).classList.remove('border-blue-600', 'text-blue-600');
                document.getElementById(`tab-${f}`).classList.add('border-transparent', 'text-slate-400');
            });

            document.getElementById(`form-${tab}`).classList.remove('hidden');
            document.getElementById(`tab-${tab}`).classList.add('border-blue-600', 'text-blue-600');
            document.getElementById(`tab-${tab}`).classList.remove('border-transparent', 'text-slate-400');
        }
        const searchInput = document.getElementById('user-search');
        const searchResults = document.getElementById('search-results');
        const selectedUserBox = document.getElementById('selected-user-box');
        const targetNumberInput = document.getElementById('target_number');

        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`?search_query=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(users => {
                        if (users.length > 0) {
                            searchResults.innerHTML = users.map(user => `
                                <div onclick="selectUser('${user.name}', '${user.number}')" class="p-4 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0 flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm">${user.name}</p>
                                        <p class="text-[10px] text-slate-400">${user.role} • ${user.number}</p>
                                    </div>
                                    <i class="fas fa-plus text-blue-600 text-xs"></i>
                                </div>
                            `).join('');
                            searchResults.classList.remove('hidden');
                        } else {
                            searchResults.innerHTML = '<div class="p-4 text-slate-400 text-sm text-center">No active users found</div>';
                            searchResults.classList.remove('hidden');
                        }
                    });
            }, 300);
        });

        function selectUser(name, number) {
            document.getElementById('user-display-name').textContent = name;
            document.getElementById('user-display-number').textContent = number;
            document.getElementById('user-initial').textContent = name.charAt(0).toUpperCase();
            targetNumberInput.value = number;
            
            selectedUserBox.classList.remove('hidden');
            searchResults.classList.add('hidden');
            searchInput.value = '';
        }

        function clearSelectedUser() {
            selectedUserBox.classList.add('hidden');
            targetNumberInput.value = '';
        }

        // Close search results on click outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });

        function previewImage(input, previewId) {
            const previewContainer = document.getElementById(previewId);
            const previewImg = previewContainer.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                previewContainer.classList.add('hidden');
            }
        }

        // File input label handlers
        document.getElementById('numbers-file-input')?.addEventListener('change', function() {
            const label = document.getElementById('file-label');
            if (this.files[0]) label.textContent = this.files[0].name;
        });
        document.getElementById('media-file-input')?.addEventListener('change', function() {
            const label = document.getElementById('media-label');
            if (this.files[0]) label.textContent = this.files[0].name;
        });
        document.getElementById('mass-media-input')?.addEventListener('change', function() {
            const label = document.getElementById('mass-media-label');
            if (this.files[0]) label.textContent = this.files[0].name;
        });
        document.getElementById('indiv-media-input')?.addEventListener('change', function() {
            const label = document.getElementById('indiv-media-label');
            if (this.files[0]) label.textContent = this.files[0].name;
        });
    </script>

    <style>
        input:checked + div + div span { color: #2563eb; }
        input:checked + div { border-color: #2563eb; }
        label:has(input:checked) { border-color: #2563eb; background: #f8fafc; }
    </style>
</body>
</html>
