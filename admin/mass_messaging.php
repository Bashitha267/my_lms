<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

// Only admins can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_mass_msg'])) {
        $target_roles = $_POST['roles'] ?? [];
        $message_text = trim($_POST['message'] ?? '');

        if (empty($target_roles)) {
            $error_message = "Please select at least one target group.";
        } elseif (empty($message_text)) {
            $error_message = "Please enter a message.";
        } else {
            $roles_list = "'" . implode("','", array_map([$conn, 'real_escape_string'], $target_roles)) . "'";
            $query = "SELECT whatsapp_number, mobile_number, first_name FROM users WHERE role IN ($roles_list) AND status = 1";
            $result = $conn->query($query);

            $sent_count = 0;
            $failed_count = 0;

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $number = !empty($row['whatsapp_number']) ? $row['whatsapp_number'] : $row['mobile_number'];
                    if (!empty($number)) {
                        $response = sendWhatsAppMessage($number, $message_text);
                        if ($response['success']) $sent_count++; else $failed_count++;
                    }
                }
                $success_message = "Mass messaging complete. Sent: $sent_count, Failed: $failed_count";
            } else {
                $error_message = "No active users found in the selected groups.";
            }
        }
    } elseif (isset($_POST['send_individual_msg'])) {
        $target_number = trim($_POST['target_number'] ?? '');
        $message_text = trim($_POST['message'] ?? '');

        if (empty($target_number)) {
            $error_message = "Please select a user first.";
        } elseif (empty($message_text)) {
            $error_message = "Please enter a message.";
        } else {
            $response = sendWhatsAppMessage($target_number, $message_text);
            if ($response['success']) {
                $success_message = "Message sent successfully to " . htmlspecialchars($target_number);
            } else {
                $error_message = "Failed to send message: " . ($response['error'] ?? 'Unknown error');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Messaging | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-slate-50">
    <?php include 'header.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-12">
        <div class="glass-card p-8 md:p-12">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-blue-200">
                    <i class="fas fa-paper-plane text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Mass Messaging Portal</h1>
                    <p class="text-slate-500 text-sm font-medium">Broadcast WhatsApp messages to students, teachers, and instructors.</p>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-xl text-emerald-700 text-sm font-bold mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-rose-50 border border-rose-100 p-4 rounded-xl text-rose-700 text-sm font-bold mb-6 flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="mb-10 flex border-b border-slate-100">
                <button onclick="switchTab('mass')" id="tab-mass" class="px-6 py-3 font-bold text-sm border-b-2 border-blue-600 text-blue-600 transition-all">Mass Broadcast</button>
                <button onclick="switchTab('individual')" id="tab-individual" class="px-6 py-3 font-bold text-sm border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition-all">Individual Message</button>
            </div>

            <!-- Mass Broadcast Form -->
            <form method="POST" id="form-mass" class="space-y-8">
                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Select Target Groups</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <label class="relative flex items-center p-4 rounded-2xl border-2 border-slate-100 hover:border-blue-500 transition-all cursor-pointer group">
                            <input type="checkbox" name="roles[]" value="student" class="hidden peer">
                            <div class="w-5 h-5 border-2 border-slate-300 rounded-full flex items-center justify-center peer-checked:bg-blue-600 peer-checked:border-blue-600 transition-all">
                                <i class="fas fa-check text-[10px] text-white"></i>
                            </div>
                            <div class="ml-3">
                                <span class="block text-sm font-bold text-slate-700">Students</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">All active students</span>
                            </div>
                        </label>

                        <label class="relative flex items-center p-4 rounded-2xl border-2 border-slate-100 hover:border-blue-500 transition-all cursor-pointer group">
                            <input type="checkbox" name="roles[]" value="teacher" class="hidden peer">
                            <div class="w-5 h-5 border-2 border-slate-300 rounded-full flex items-center justify-center peer-checked:bg-blue-600 peer-checked:border-blue-600 transition-all">
                                <i class="fas fa-check text-[10px] text-white"></i>
                            </div>
                            <div class="ml-3">
                                <span class="block text-sm font-bold text-slate-700">Teachers</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">All active teachers</span>
                            </div>
                        </label>

                        <label class="relative flex items-center p-4 rounded-2xl border-2 border-slate-100 hover:border-blue-500 transition-all cursor-pointer group">
                            <input type="checkbox" name="roles[]" value="instructor" class="hidden peer">
                            <div class="w-5 h-5 border-2 border-slate-300 rounded-full flex items-center justify-center peer-checked:bg-blue-600 peer-checked:border-blue-600 transition-all">
                                <i class="fas fa-check text-[10px] text-white"></i>
                            </div>
                            <div class="ml-3">
                                <span class="block text-sm font-bold text-slate-700">Instructors</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">All active instructors</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Message Content</label>
                    <div class="relative">
                        <textarea name="message" rows="6" required 
                                  placeholder="Type your announcement here..."
                                  class="w-full px-6 py-5 bg-slate-50 border-2 border-slate-100 rounded-3xl text-slate-700 font-medium focus:border-blue-500 focus:bg-white outline-none transition-all resize-none"></textarea>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="send_mass_msg" onclick="return confirm('Are you sure?')"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-5 rounded-3xl font-black text-sm uppercase tracking-widest transition-all shadow-xl shadow-blue-200 active:scale-[0.98] flex items-center justify-center gap-3">
                        Broadcast Message <i class="fas fa-paper-plane text-xs opacity-70"></i>
                    </button>
                </div>
            </form>

            <!-- Individual Message Form -->
            <form method="POST" id="form-individual" class="hidden space-y-8">
                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Search & Select User</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-6 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="user-search" placeholder="Search by name, ID or mobile..." 
                               class="w-full pl-14 pr-6 py-5 bg-slate-50 border-2 border-slate-100 rounded-3xl text-slate-700 font-medium focus:border-blue-500 focus:bg-white outline-none transition-all">
                        
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

                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Message Content</label>
                    <div class="relative">
                        <textarea name="message" rows="6" required 
                                  placeholder="Type your private message here..."
                                  class="w-full px-6 py-5 bg-slate-50 border-2 border-slate-100 rounded-3xl text-slate-700 font-medium focus:border-blue-500 focus:bg-white outline-none transition-all resize-none"></textarea>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="send_individual_msg"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-5 rounded-3xl font-black text-sm uppercase tracking-widest transition-all shadow-xl shadow-blue-200 active:scale-[0.98] flex items-center justify-center gap-3">
                        Send Message <i class="fas fa-paper-plane text-xs opacity-70"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <div class="mt-8 text-center">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Broadcast safely. Avoid spamming.</p>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const massForm = document.getElementById('form-mass');
            const individualForm = document.getElementById('form-individual');
            const massTab = document.getElementById('tab-mass');
            const individualTab = document.getElementById('tab-individual');

            if (tab === 'mass') {
                massForm.classList.remove('hidden');
                individualForm.classList.add('hidden');
                massTab.classList.add('border-blue-600', 'text-blue-600');
                massTab.classList.remove('border-transparent', 'text-slate-400');
                individualTab.classList.remove('border-blue-600', 'text-blue-600');
                individualTab.classList.add('border-transparent', 'text-slate-400');
            } else {
                massForm.classList.add('hidden');
                individualForm.classList.remove('hidden');
                individualTab.classList.add('border-blue-600', 'text-blue-600');
                individualTab.classList.remove('border-transparent', 'text-slate-400');
                massTab.classList.remove('border-blue-600', 'text-blue-600');
                massTab.classList.add('border-transparent', 'text-slate-400');
            }
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
    </script>

    <style>
        input:checked + div + div span { color: #2563eb; }
        input:checked + div { border-color: #2563eb; }
        label:has(input:checked) { border-color: #2563eb; background: #f8fafc; }
    </style>
</body>
</html>
