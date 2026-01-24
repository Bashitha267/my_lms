<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $page_type = $_POST['page_type'] ?? 'dashboard';
    $upload_background = isset($_POST['upload_background']) && $_POST['upload_background'] === '1';
    $remove_background = isset($_POST['remove_background']) && $_POST['remove_background'] === '1';
    
    // Determine setting key based on page type
    $setting_key_map = [
        'dashboard' => 'dashboard_background',
        'recordings' => 'recordings_background',
        'live_classes' => 'live_classes_background',
        'online_courses' => 'online_courses_background'
    ];
    
    $setting_key = $setting_key_map[$page_type] ?? 'dashboard_background';
    $page_description_map = [
        'dashboard' => 'Background image for student dashboard',
        'recordings' => 'Background image for recordings page',
        'live_classes' => 'Background image for live classes page',
        'online_courses' => 'Background image for online courses page'
    ];
    $description = $page_description_map[$page_type] ?? 'Background image';
    
    if ($remove_background) {
        // Remove existing background
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->bind_param("s", $setting_key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $old_image = $row['setting_value'];
            
            // Delete old file if exists
            if ($old_image && file_exists('../' . $old_image)) {
                unlink('../' . $old_image);
            }
        }
        $stmt->close();
        
        // Update database
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = NULL, updated_by = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $user_id, $setting_key);
        
        if ($stmt->execute()) {
            $success_message = 'Background image removed successfully!';
        } else {
            $error_message = 'Failed to remove background image.';
        }
        $stmt->close();
        
    } elseif ($upload_background && isset($_FILES['background_image']) && $_FILES['background_image']['error'] === UPLOAD_ERR_OK) {
        // Process upload
        $upload_dir = '../uploads/backgrounds/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file = $_FILES['background_image'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Validate file type
        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = 'Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.';
        } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $error_message = 'File size too large. Maximum size is 10MB.';
        } else {
            // Get old image to delete
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->bind_param("s", $setting_key);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $old_image = null;
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $old_image = $row['setting_value'];
            }
            $stmt->close();
            
            // Generate unique filename
            $new_filename = $page_type . '_bg_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $background_path = 'uploads/backgrounds/' . $new_filename;
                
                // Update database
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, updated_by) 
                                       VALUES (?, ?, 'image', ?, ?)
                                       ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
                $stmt->bind_param("ssssss", $setting_key, $background_path, $description, $user_id, $background_path, $user_id);
                
                if ($stmt->execute()) {
                    // Delete old image if exists
                    if ($old_image && file_exists('../' . $old_image)) {
                        unlink('../' . $old_image);
                    }
                    
                    $success_message = 'Background image updated successfully!';
                } else {
                    $error_message = 'Failed to update background image in database.';
                    // Delete uploaded file if database update fails
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
                $stmt->close();
            } else {
                $error_message = 'Failed to upload background image.';
            }
        }
    } else {
        $error_message = 'Please select an image file to upload.';
    }
}

// Get current background images for all pages
$backgrounds = [
    'dashboard' => null,
    'recordings' => null,
    'live_classes' => null,
    'online_courses' => null
];

$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('dashboard_background', 'recordings_background', 'live_classes_background', 'online_courses_background')");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $key = str_replace('_background', '', $row['setting_key']);
    $backgrounds[$key] = $row['setting_value'];
}
$stmt->close();

// For backward compatibility
$current_background = $backgrounds['dashboard'];

// Function to render background section
function renderBackgroundSection($page_type, $page_title, $current_background) {
    ob_start();
    ?>
    <!-- Current Background Preview -->
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Current Background for <?php echo htmlspecialchars($page_title); ?></label>
        <?php if ($current_background): ?>
            <div class="relative inline-block">
                <img src="../<?php echo htmlspecialchars($current_background); ?>" 
                     alt="Current Background" 
                     class="w-full max-w-2xl h-64 object-cover rounded-lg border-2 border-gray-300 shadow-md">
                <div class="mt-2 text-sm text-gray-600">
                    <strong>File:</strong> <?php echo htmlspecialchars(basename($current_background)); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="w-full max-w-2xl h-64 bg-gray-200 rounded-lg border-2 border-dashed border-gray-400 flex items-center justify-center">
                <div class="text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-gray-500">No background image set</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload New Background -->
    <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="page_type" value="<?php echo htmlspecialchars($page_type); ?>">
        
        <div>
            <label for="background_image_<?php echo $page_type; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                Upload New Background Image for <?php echo htmlspecialchars($page_title); ?>
            </label>
            <div class="flex items-center space-x-4">
                <input type="file" 
                       name="background_image" 
                       id="background_image_<?php echo $page_type; ?>" 
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                       class="block w-full text-sm text-gray-500
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-md file:border-0
                              file:text-sm file:font-semibold
                              file:bg-red-50 file:text-red-700
                              hover:file:bg-red-100
                              cursor-pointer"
                       onchange="previewImage(this, '<?php echo $page_type; ?>')">
            </div>
            <p class="mt-2 text-sm text-gray-500">
                Accepted formats: JPG, JPEG, PNG, GIF, WEBP (Max size: 10MB)
            </p>
            <p class="mt-1 text-sm text-gray-500">
                Recommended dimensions: 1920x1080 pixels or higher for best quality
            </p>
        </div>

        <!-- Image Preview -->
        <div id="imagePreview_<?php echo $page_type; ?>" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
            <img id="previewImg_<?php echo $page_type; ?>" src="" alt="Preview" class="w-full max-w-2xl h-64 object-cover rounded-lg border-2 border-gray-300 shadow-md">
        </div>

        <div class="flex space-x-4">
            <input type="hidden" name="upload_background" value="1">
            <button type="submit" 
                    name="update_settings"
                    class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors font-medium shadow-md">
                Upload Background
            </button>
            
            <?php if ($current_background): ?>
                <button type="submit" 
                        name="update_settings"
                        onclick="return confirm('Are you sure you want to remove the current background image?')"
                        class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors font-medium shadow-md">
                    Remove Background
                </button>
                <input type="hidden" name="remove_background" value="1">
            <?php endif; ?>
        </div>
    </form>
    <?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-button {
            border-color: transparent;
            color: #6b7280;
        }
        .tab-button:hover {
            color: #374151;
            border-color: #d1d5db;
        }
        .tab-button.active {
            color: #dc2626;
            border-color: #dc2626;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">System Settings</h2>
                    <a href="dashboard.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                        Back to Dashboard
                    </a>
                </div>

                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (!empty($error_message)): ?>
                    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Background Settings -->
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">Page Background Images</h3>
                    
                    <!-- Tab Navigation -->
                    <div class="mb-6 border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button type="button" onclick="switchTab('dashboard')" 
                                    class="tab-button active whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                    data-tab="dashboard">
                                Dashboard
                            </button>
                            <button type="button" onclick="switchTab('recordings')" 
                                    class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                    data-tab="recordings">
                                Recordings Page
                            </button>
                            <button type="button" onclick="switchTab('live_classes')" 
                                    class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                    data-tab="live_classes">
                                Live Classes Page
                            </button>
                            <button type="button" onclick="switchTab('online_courses')" 
                                    class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                    data-tab="online_courses">
                                Online Courses Page
                            </button>
                        </nav>
                    </div>

                    <!-- Dashboard Tab -->
                    <div id="tab-dashboard" class="tab-content">
                        <?php echo renderBackgroundSection('dashboard', 'Dashboard', $backgrounds['dashboard']); ?>
                    </div>

                    <!-- Recordings Tab -->
                    <div id="tab-recordings" class="tab-content hidden">
                        <?php echo renderBackgroundSection('recordings', 'Recordings Page', $backgrounds['recordings']); ?>
                    </div>

                    <!-- Live Classes Tab -->
                    <div id="tab-live_classes" class="tab-content hidden">
                        <?php echo renderBackgroundSection('live_classes', 'Live Classes Page', $backgrounds['live_classes']); ?>
                    </div>
                    
                    <!-- Online Courses Tab -->
                    <div id="tab-online_courses" class="tab-content hidden">
                        <?php echo renderBackgroundSection('online_courses', 'Online Courses Page', $backgrounds['online_courses']); ?>
                    </div>
                </div>

                
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            
            // Add active class to selected button
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        }
        
        function previewImage(input, pageType) {
            const preview = document.getElementById('imagePreview_' + pageType);
            const previewImg = document.getElementById('previewImg_' + pageType);
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file size
                if (file.size > 10 * 1024 * 1024) {
                    alert('File size too large. Maximum size is 10MB.');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
