<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit();
}

// Handle AJAX updates for theme settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_section_color_ajax') {
    header('Content-Type: application/json');
    $section_key = $_POST['section_key'] ?? '';
    $field = $_POST['field'] ?? ''; // 'bg_color' or 'card_colors'
    $value = trim($_POST['value'] ?? '');

    if (!in_array($section_key, ['al_results', 'classes', 'extra_courses'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid section key']);
        exit();
    }

    if (!in_array($field, ['bg_color', 'card_colors'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid field']);
        exit();
    }

    if ($field === 'card_colors') {
        // Clean card colors list
        $value = implode(',', array_filter(array_map('trim', explode(',', $value))));
    }

    $stmt = $conn->prepare("UPDATE dashboard_colors SET $field = ? WHERE section_key = ?");
    $stmt->bind_param("ss", $value, $section_key);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $success, 'message' => $success ? 'Updated successfully' : 'Database error']);
    exit();
}

$success_message = '';
$error_message = '';
$user_id = $_SESSION['user_id'];
$active_tab = 'dashboard';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_colors'])) {
    $active_tab = 'dashboard_colors';
    $colors_updated = true;
    foreach (['al_results', 'classes', 'extra_courses'] as $section_key) {
        $bg_color = trim($_POST[$section_key . '_bg_color'] ?? '');
        $card_colors = trim($_POST[$section_key . '_card_colors'] ?? '');
        
        // Clean card colors list
        $card_colors = implode(',', array_filter(array_map('trim', explode(',', $card_colors))));
        
        $stmt = $conn->prepare("UPDATE dashboard_colors SET bg_color = ?, card_colors = ? WHERE section_key = ?");
        $stmt->bind_param("sss", $bg_color, $card_colors, $section_key);
        if (!$stmt->execute()) {
            $colors_updated = false;
        }
        $stmt->close();
    }
    
    if ($colors_updated) {
        $success_message = 'Dashboard theme colors updated successfully!';
    } else {
        $error_message = 'Failed to update some dashboard theme colors.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $page_type = $_POST['page_type'] ?? 'dashboard';
    $active_tab = $page_type;
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

// Handle Home Posts (Gallery)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_home_post']) || isset($_POST['delete_home_post'])) {
        $active_tab = 'home_posts';
    }
    if (isset($_POST['upload_home_post'])) {
        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/posts/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['post_image'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                $error_message = 'Invalid file type for post.';
            } else {
                $new_filename = 'post_' . time() . '_' . rand(100, 999) . '.' . $file_ext;
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
                    $image_path = 'uploads/posts/' . $new_filename;
                    $title = $_POST['post_title'] ?? '';
                    $stmt = $conn->prepare("INSERT INTO home_posts (image_path, title) VALUES (?, ?)");
                    $stmt->bind_param("ss", $image_path, $title);
                    if ($stmt->execute()) {
                        $success_message = 'Post uploaded successfully!';
                    } else {
                        $error_message = 'Database error: ' . $conn->error;
                    }
                    $stmt->close();
                }
            }
        }
    } elseif (isset($_POST['delete_home_post'])) {
        $post_id = $_POST['post_id'];
        $stmt = $conn->prepare("SELECT image_path FROM home_posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (file_exists('../' . $row['image_path'])) {
                unlink('../' . $row['image_path']);
            }
            $stmt = $conn->prepare("DELETE FROM home_posts WHERE id = ?");
            $stmt->bind_param("i", $post_id);
            $stmt->execute();
            $success_message = 'Post deleted successfully!';
        }
        $stmt->close();
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

// Get home posts
$home_posts = [];
$result = $conn->query("SELECT * FROM home_posts ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $home_posts[] = $row;
    }
}

// Get dashboard theme colors
$dashboard_colors = [];
$result_colors = $conn->query("SELECT * FROM dashboard_colors");
if ($result_colors) {
    while ($row = $result_colors->fetch_assoc()) {
        $dashboard_colors[$row['section_key']] = $row;
    }
}

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                            <button type="button" onclick="switchTab('home_posts')" 
                                    class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                    data-tab="home_posts">
                                Marketing Posts
                            </button>
                            <button type="button" onclick="switchTab('dashboard_colors')" 
                                    class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                    data-tab="dashboard_colors">
                                Dashboard Colors
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

                    <!-- Home Posts Tab -->
                    <div id="tab-home_posts" class="tab-content hidden">
                        <div class="bg-gray-50 p-6 rounded-lg border mb-8">
                            <h4 class="text-lg font-bold mb-4">Add New Post</h4>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Image</label>
                                    <input type="file" name="post_image" accept="image/*" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Title (Optional)</label>
                                    <input type="text" name="post_title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm p-2 border">
                                </div>
                                <button type="submit" name="upload_home_post" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Upload Post</button>
                            </form>
                        </div>

                        <h4 class="text-lg font-bold mb-4">Current Posts</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($home_posts as $post): ?>
                                <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden relative group">
                                    <div class="h-64 w-full bg-gray-100 relative">
                                        <img src="../<?php echo htmlspecialchars($post['image_path']); ?>" 
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                             alt="<?php echo htmlspecialchars($post['title'] ?? 'Post'); ?>">
                                        
                                        <!-- Removal Icon Overlay -->
                                        <form method="POST" onsubmit="return confirm('Delete this marketing post?')" class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-[-10px] group-hover:translate-y-0">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" name="delete_home_post" class="w-10 h-10 bg-white/90 backdrop-blur-sm text-red-600 rounded-full flex items-center justify-center hover:bg-red-600 hover:text-white shadow-xl transition-all duration-200 border border-red-100" title="Remove Post">
                                                <i class="fas fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="p-3 bg-white border-t">
                                        <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($post['title'] ?: 'Untitled Post'); ?></p>
                                        <p class="text-[10px] text-gray-400 mt-1 flex items-center gap-1">
                                            <i class="far fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($home_posts)): ?>
                                <p class="col-span-full text-center text-gray-500 py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                                    <i class="fas fa-images text-4xl mb-3 block text-gray-300"></i>
                                    No posts uploaded yet.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dashboard Colors Tab -->
                    <div id="tab-dashboard_colors" class="tab-content hidden">
                        <div class="bg-gray-50 p-6 rounded-lg border mb-8">
                            <h4 class="text-lg font-bold mb-2">Dashboard Section & Card Colors</h4>
                            <p class="text-xs text-gray-500 mb-6">Specify HTML color values (e.g. <code>#e0f2fe</code> or <code>e0f2fe</code> or <code>rgb(224, 242, 254)</code>. Card colors should be separated by commas. We will randomly select a color from the card colors list for each item card.</p>
                            
                            <form method="POST" action="" class="space-y-8">
                                <input type="hidden" name="update_colors" value="1">
                                
                                <?php foreach (['al_results' => 'A/L Results Section', 'classes' => 'Available Classes Section', 'extra_courses' => 'Extra Courses Section'] as $key => $title): 
                                    $info = $dashboard_colors[$key] ?? ['bg_color' => '', 'card_colors' => ''];
                                ?>
                                    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
                                        <h5 class="text-md font-bold text-gray-800 border-b pb-2 mb-4 flex justify-between items-center">
                                            <span><?php echo htmlspecialchars($title); ?></span>
                                            <span id="save_status_<?php echo $key; ?>" class="text-xs font-normal text-gray-500 hidden flex items-center gap-1"></span>
                                        </h5>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Section Background Color</label>
                                                <div class="flex gap-2 items-center">
                                                    <?php 
                                                    $bg_color_disp = trim($info['bg_color']);
                                                    if (preg_match('/^[a-fA-F0-9]{3,8}$/', $bg_color_disp)) $bg_color_disp = '#' . $bg_color_disp;
                                                    // Default hex color value for picker
                                                    $picker_val = (preg_match('/^#[a-fA-F0-9]{3,6}$/', $bg_color_disp)) ? $bg_color_disp : '#ffffff';
                                                    ?>
                                                    <div class="relative flex-1">
                                                        <input type="text" id="<?php echo $key; ?>_bg_color" name="<?php echo $key; ?>_bg_color" 
                                                               value="<?php echo htmlspecialchars($info['bg_color']); ?>" 
                                                               class="w-full border border-gray-300 rounded-md shadow-sm p-2 pr-10 text-sm focus:ring-red-500 focus:border-red-500" 
                                                               placeholder="#ffffff"
                                                               onchange="saveBgColor('<?php echo $key; ?>', this.value)">
                                                        <!-- Color picker for background color -->
                                                        <div class="absolute inset-y-0 right-0 pr-2 flex items-center">
                                                            <input type="color" id="<?php echo $key; ?>_bg_color_picker" 
                                                                   value="<?php echo htmlspecialchars($picker_val); ?>"
                                                                   class="w-6 h-6 border border-gray-300 rounded cursor-pointer p-0 bg-transparent"
                                                                   onchange="document.getElementById('<?php echo $key; ?>_bg_color').value = this.value; saveBgColor('<?php echo $key; ?>', this.value);">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Card Colors (Add/Remove interactively)</label>
                                                
                                                <!-- Hidden input to hold the comma-separated string for form submit -->
                                                <input type="hidden" name="<?php echo $key; ?>_card_colors" id="<?php echo $key; ?>_card_colors" value="<?php echo htmlspecialchars($info['card_colors']); ?>">
                                                
                                                <!-- Container for dynamic color chips -->
                                                <div id="<?php echo $key; ?>_chips_container" class="flex flex-wrap gap-2 p-3 bg-gray-50 border border-gray-200 rounded-lg min-h-[50px] mb-3">
                                                    <?php 
                                                    $cards = explode(',', $info['card_colors']);
                                                    $has_chips = false;
                                                    foreach ($cards as $color):
                                                        $color = trim($color);
                                                        if (empty($color)) continue;
                                                        $has_chips = true;
                                                        $preview_color = (preg_match('/^[a-fA-F0-9]{3,8}$/', $color)) ? '#' . $color : $color;
                                                    ?>
                                                        <span data-color="<?php echo htmlspecialchars($color); ?>" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold border rounded-full gap-2 shadow-sm bg-white hover:bg-gray-50 transition-colors" style="border-color: rgba(0,0,0,0.08);">
                                                            <span class="w-3.5 h-3.5 rounded-full border shadow-inner flex-shrink-0" style="background-color: <?php echo htmlspecialchars($preview_color); ?>;"></span>
                                                            <span class="text-gray-700 font-mono text-[11px]"><?php echo htmlspecialchars($color); ?></span>
                                                            <button type="button" onclick="removeColor('<?php echo $key; ?>', '<?php echo htmlspecialchars(addslashes($color)); ?>')" class="text-gray-400 hover:text-red-500 font-bold ml-1 transition-colors text-sm focus:outline-none" title="Remove Color">
                                                                &times;
                                                            </button>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (!$has_chips): ?>
                                                        <span class="no-chips-placeholder text-xs text-gray-400 italic">No colors added yet.</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Controls to add a new color -->
                                                <div class="flex items-center gap-2">
                                                    <div class="relative flex-1">
                                                        <input type="text" id="<?php echo $key; ?>_new_color_input" 
                                                               class="w-full border border-gray-300 rounded-md shadow-sm p-2 pr-10 text-sm focus:ring-red-500 focus:border-red-500" 
                                                               placeholder="Enter hex (e.g. #f3e8ff) or Tailwind bg class"
                                                               onkeypress="if(event.key === 'Enter') { event.preventDefault(); addColor('<?php echo $key; ?>'); }">
                                                        <!-- Direct quick color picker -->
                                                        <div class="absolute inset-y-0 right-0 pr-2 flex items-center">
                                                            <input type="color" id="<?php echo $key; ?>_new_color_picker" 
                                                                   value="#e0f2fe"
                                                                   class="w-6 h-6 border border-gray-300 rounded cursor-pointer p-0 bg-transparent"
                                                                   onchange="document.getElementById('<?php echo $key; ?>_new_color_input').value = this.value;">
                                                        </div>
                                                    </div>
                                                    <button type="button" onclick="addColor('<?php echo $key; ?>')" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-semibold transition shadow-sm">
                                                        Add
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="flex justify-end items-center gap-4 pt-4">
                                     <span class="text-xs text-gray-500 flex items-center gap-1.5"><i class="fas fa-info-circle"></i> Settings auto-save in real-time</span>
                                    <button type="submit" class="px-6 py-2.5 bg-red-600 text-white rounded-md hover:bg-red-700 font-semibold text-sm transition shadow-md shadow-red-100">
                                        Save Theme Colors
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                
            </div>
        </div>
    </div>

    <script>
        // Auto-switch to active tab from PHP on load
        window.addEventListener('DOMContentLoaded', () => {
            const activeTab = <?php echo json_encode($active_tab); ?>;
            if (activeTab && document.getElementById('tab-' + activeTab)) {
                switchTab(activeTab);
            }
        });

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

        // Color Chips Management JavaScript
        function updateHiddenInput(sectionKey) {
            const container = document.getElementById(sectionKey + '_chips_container');
            const hiddenInput = document.getElementById(sectionKey + '_card_colors');
            
            const chips = container.querySelectorAll('span[data-color]');
            const colors = Array.from(chips).map(chip => chip.getAttribute('data-color'));
            
            hiddenInput.value = colors.join(',');
            
            // Show placeholder if empty
            const placeholder = container.querySelector('.no-chips-placeholder');
            if (colors.length === 0) {
                if (!placeholder) {
                    const span = document.createElement('span');
                    span.className = 'no-chips-placeholder text-xs text-gray-400 italic';
                    span.textContent = 'No colors added yet.';
                    container.appendChild(span);
                }
            } else if (placeholder) {
                placeholder.remove();
            }
        }
        
        function removeColor(sectionKey, colorValue) {
            const container = document.getElementById(sectionKey + '_chips_container');
            // Escape any special characters for the querySelector
            const selector = `span[data-color="${CSS.escape(colorValue)}"]`;
            const chip = container.querySelector(selector);
            if (chip) {
                chip.remove();
                updateHiddenInput(sectionKey);
                
                // Auto-save cards color update
                const hiddenInput = document.getElementById(sectionKey + '_card_colors');
                saveColorSetting(sectionKey, 'card_colors', hiddenInput.value);
            }
        }
        
        function addColor(sectionKey) {
            const input = document.getElementById(sectionKey + '_new_color_input');
            let color = input.value.trim();
            if (!color) return;
            
            const container = document.getElementById(sectionKey + '_chips_container');
            
            // Check if color already exists
            const existing = container.querySelector(`span[data-color="${CSS.escape(color)}"]`);
            if (existing) {
                alert('This color is already in the list.');
                input.value = '';
                return;
            }
            
            // Format preview color
            let previewColor = color;
            if (/^[a-fA-F0-9]{3,8}$/.test(color)) {
                previewColor = '#' + color;
            }
            
            // Create chip element
            const chip = document.createElement('span');
            chip.setAttribute('data-color', color);
            chip.className = 'inline-flex items-center px-2.5 py-1 text-xs font-semibold border rounded-full gap-2 shadow-sm bg-white hover:bg-gray-50 transition-colors';
            chip.style.borderColor = 'rgba(0,0,0,0.08)';
            
            // Inner color circle
            const colorCircle = document.createElement('span');
            colorCircle.className = 'w-3.5 h-3.5 rounded-full border shadow-inner flex-shrink-0';
            colorCircle.style.backgroundColor = previewColor;
            
            // Text value
            const textSpan = document.createElement('span');
            textSpan.className = 'text-gray-700 font-mono text-[11px]';
            textSpan.textContent = color;
            
            // Delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'text-gray-400 hover:text-red-500 font-bold ml-1 transition-colors text-sm focus:outline-none';
            deleteBtn.innerHTML = '&times;';
            deleteBtn.onclick = function() {
                removeColor(sectionKey, color);
            };
            
            chip.appendChild(colorCircle);
            chip.appendChild(textSpan);
            chip.appendChild(deleteBtn);
            
            // Remove placeholder if present
            const placeholder = container.querySelector('.no-chips-placeholder');
            if (placeholder) {
                placeholder.remove();
            }
            
            container.appendChild(chip);
            updateHiddenInput(sectionKey);
            
            // Auto-save cards color update
            const hiddenInput = document.getElementById(sectionKey + '_card_colors');
            saveColorSetting(sectionKey, 'card_colors', hiddenInput.value);
            
            // Clear input
            input.value = '';
        }

        // Auto-save settings via AJAX
        function saveColorSetting(sectionKey, field, value) {
            const formData = new FormData();
            formData.append('action', 'update_section_color_ajax');
            formData.append('section_key', sectionKey);
            formData.append('field', field);
            formData.append('value', value);

            // Visual feedback - show saving indicator
            const statusIndicator = document.getElementById('save_status_' + sectionKey);
            if (statusIndicator) {
                statusIndicator.innerHTML = '<i class="fas fa-spinner fa-spin text-blue-500 mr-1"></i> Saving...';
                statusIndicator.classList.remove('hidden');
            }

            fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (statusIndicator) {
                    if (data.success) {
                        statusIndicator.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i> Saved';
                        setTimeout(() => {
                            statusIndicator.classList.add('hidden');
                        }, 2000);
                    } else {
                        statusIndicator.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i> Save failed';
                    }
                }
            })
            .catch(error => {
                console.error('Error saving setting:', error);
                if (statusIndicator) {
                    statusIndicator.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i> Error';
                }
            });
        }

        function saveBgColor(sectionKey, colorValue) {
            colorValue = colorValue.trim();
            // Update visual color picker input value if valid hex
            const picker = document.getElementById(sectionKey + '_bg_color_picker');
            if (picker && /^#[a-fA-F0-9]{3,6}$/.test(colorValue)) {
                picker.value = colorValue;
            }
            saveColorSetting(sectionKey, 'bg_color', colorValue);
        }
    </script>
</body>
</html>
