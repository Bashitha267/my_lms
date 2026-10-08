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
    $field = $_POST['field'] ?? '';
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
$active_tab = 'manage_homepage';

// Handle Homepage Video Uploads & Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_homepage_video'])) {
    $active_tab = 'manage_homepage';
    $video_type = $_POST['video_type'] ?? ''; // 'desktop' or 'mobile'
    
    if (in_array($video_type, ['desktop', 'mobile'])) {
        if (isset($_POST['remove_video']) && $_POST['remove_video'] === '1') {
            $stmt = $conn->prepare("SELECT video_path FROM homepage_videos WHERE video_type = ? LIMIT 1");
            $stmt->bind_param("s", $video_type);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $old = $res->fetch_assoc()['video_path'];
                if ($old && strpos($old, 'uploads/videos/') !== false && file_exists('../' . $old)) {
                    @unlink('../' . $old);
                }
            }
            $stmt->close();

            $del_stmt = $conn->prepare("DELETE FROM homepage_videos WHERE video_type = ?");
            $del_stmt->bind_param("s", $video_type);
            $del_stmt->execute();
            $del_stmt->close();

            $success_message = ucfirst($video_type) . ' background video removed successfully!';
        } elseif (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $u_dir = '../uploads/videos/';
            if (!file_exists($u_dir)) {
                mkdir($u_dir, 0777, true);
            }
            
            $file = $_FILES['video_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['mp4', 'webm', 'ogg', 'mov'];
            
            if (!in_array($ext, $allowed_exts)) {
                $error_message = 'Invalid video file type. Only MP4, WEBM, OGG, and MOV are allowed.';
            } elseif ($file['size'] > 100 * 1024 * 1024) { // 100MB limit
                $error_message = 'Video file size too large. Maximum size is 100MB.';
            } else {
                // Delete old video if exists
                $stmt = $conn->prepare("SELECT video_path FROM homepage_videos WHERE video_type = ? LIMIT 1");
                $stmt->bind_param("s", $video_type);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $old = $res->fetch_assoc()['video_path'];
                    if ($old && strpos($old, 'uploads/videos/') !== false && file_exists('../' . $old)) {
                        @unlink('../' . $old);
                    }
                }
                $stmt->close();

                $new_filename = $video_type . '_bg_' . time() . '.' . $ext;
                $upload_path = $u_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $db_path = 'uploads/videos/' . $new_filename;
                    $stmt = $conn->prepare("INSERT INTO homepage_videos (video_type, video_path) VALUES (?, ?) ON DUPLICATE KEY UPDATE video_path = ?");
                    $stmt->bind_param("sss", $video_type, $db_path, $db_path);
                    if ($stmt->execute()) {
                        $success_message = ucfirst($video_type) . ' background video updated successfully!';
                    } else {
                        $error_message = 'Database error saving video path.';
                    }
                    $stmt->close();
                } else {
                    $error_message = 'Failed to save uploaded video file.';
                }
            }
        } else {
            $error_message = 'Please select a valid video file to upload.';
        }
    }
}

// Handle Result Poster Uploads & Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_result_poster'])) {
    $active_tab = 'manage_homepage';
    $poster_type = $_POST['poster_type'] ?? ''; // 'desktop' or 'mobile'
    $setting_key = ($poster_type === 'mobile') ? 'result_poster_mobile' : 'result_poster_desktop';
    
    if (in_array($poster_type, ['desktop', 'mobile'])) {
        if (isset($_POST['remove_poster']) && $_POST['remove_poster'] === '1') {
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->bind_param("s", $setting_key);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $old = $res->fetch_assoc()['setting_value'];
                if ($old && strpos($old, 'uploads/posters/') !== false && file_exists('../' . $old)) {
                    @unlink('../' . $old);
                }
            }
            $stmt->close();

            $desc = ucfirst($poster_type) . ' Result Poster Image';
            $del_stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, NULL, 'image', ?) ON DUPLICATE KEY UPDATE setting_value = NULL");
            $del_stmt->bind_param("ss", $setting_key, $desc);
            $del_stmt->execute();
            $del_stmt->close();

            $success_message = ucfirst($poster_type) . ' result poster removed successfully!';
        } elseif (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
            $u_dir = '../uploads/posters/';
            if (!file_exists($u_dir)) {
                mkdir($u_dir, 0777, true);
            }
            
            $file = $_FILES['poster_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($ext, $allowed_exts)) {
                $error_message = 'Invalid image file type. Only JPG, PNG, GIF, and WEBP are allowed.';
            } elseif ($file['size'] > 15 * 1024 * 1024) { // 15MB limit
                $error_message = 'Image file size too large. Maximum size is 15MB.';
            } else {
                // Delete old poster if exists
                $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
                $stmt->bind_param("s", $setting_key);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $old = $res->fetch_assoc()['setting_value'];
                    if ($old && strpos($old, 'uploads/posters/') !== false && file_exists('../' . $old)) {
                        @unlink('../' . $old);
                    }
                }
                $stmt->close();

                $new_filename = 'result_poster_' . $poster_type . '_' . time() . '.' . $ext;
                $upload_path = $u_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $db_path = 'uploads/posters/' . $new_filename;
                    $desc = ucfirst($poster_type) . ' Result Poster Image';
                    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, 'image', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->bind_param("ssss", $setting_key, $db_path, $desc, $db_path);
                    if ($stmt->execute()) {
                        $success_message = ucfirst($poster_type) . ' result poster updated successfully!';
                    } else {
                        $error_message = 'Database error saving poster path.';
                    }
                    $stmt->close();
                } else {
                    $error_message = 'Failed to save uploaded image file.';
                }
            }
        } else {
            $error_message = 'Please select a valid image file to upload.';
        }
    }
}

// Handle Student Showcase Images Uploads & Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_showcase_image'])) {
    $active_tab = 'manage_homepage';
    $slot = $_POST['image_slot'] ?? ''; // 'img1_desktop', 'img1_mobile', 'img2_desktop', 'img2_mobile'
    
    $valid_slots = [
        'img1_desktop' => ['key' => 'showcase_img1_desktop', 'label' => 'Showcase Image 1 (Desktop)'],
        'img1_mobile'  => ['key' => 'showcase_img1_mobile',  'label' => 'Showcase Image 1 (Mobile)'],
        'img2_desktop' => ['key' => 'showcase_img2_desktop', 'label' => 'Showcase Image 2 (Desktop)'],
        'img2_mobile'  => ['key' => 'showcase_img2_mobile',  'label' => 'Showcase Image 2 (Mobile)'],
    ];
    
    if (isset($valid_slots[$slot])) {
        $setting_key = $valid_slots[$slot]['key'];
        $setting_label = $valid_slots[$slot]['label'];
        
        if (isset($_POST['remove_showcase_image']) && $_POST['remove_showcase_image'] === '1') {
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $setting_key);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $old = $res->fetch_assoc()['setting_value'];
                    if ($old && strpos($old, 'uploads/showcase/') !== false && file_exists('../' . $old)) {
                        @unlink('../' . $old);
                    }
                }
                $stmt->close();
            }

            $del_stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, NULL, 'image', ?) ON DUPLICATE KEY UPDATE setting_value = NULL");
            if ($del_stmt) {
                $del_stmt->bind_param("ss", $setting_key, $setting_label);
                $del_stmt->execute();
                $del_stmt->close();
            }

            $success_message = $setting_label . ' removed successfully!';
        } elseif (isset($_FILES['showcase_file']) && $_FILES['showcase_file']['error'] === UPLOAD_ERR_OK) {
            $u_dir = '../uploads/showcase/';
            if (!file_exists($u_dir)) {
                mkdir($u_dir, 0777, true);
            }
            
            $file = $_FILES['showcase_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($ext, $allowed_exts)) {
                $error_message = 'Invalid image file type. Only JPG, PNG, GIF, and WEBP are allowed.';
            } elseif ($file['size'] > 15 * 1024 * 1024) { // 15MB limit
                $error_message = 'Image file size too large. Maximum size is 15MB.';
            } else {
                // Delete old file if exists
                $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $setting_key);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        $old = $res->fetch_assoc()['setting_value'];
                        if ($old && strpos($old, 'uploads/showcase/') !== false && file_exists('../' . $old)) {
                            @unlink('../' . $old);
                        }
                    }
                    $stmt->close();
                }

                $new_filename = 'showcase_' . $slot . '_' . time() . '.' . $ext;
                $upload_path = $u_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $db_path = 'uploads/showcase/' . $new_filename;
                    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, 'image', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    if ($stmt) {
                        $stmt->bind_param("ssss", $setting_key, $db_path, $setting_label, $db_path);
                        if ($stmt->execute()) {
                            $success_message = $setting_label . ' updated successfully!';
                        } else {
                            $error_message = 'Database error saving image path.';
                        }
                        $stmt->close();
                    } else {
                        $error_message = 'Database prepare error.';
                    }
                } else {
                    $error_message = 'Failed to save uploaded image file.';
                }
            }
        } else {
            $error_message = 'Please select a valid image file to upload.';
        }
    }
}

// Handle Theme Colors Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_colors'])) {
    $active_tab = 'dashboard_colors';
    $colors_updated = true;
    foreach (['al_results', 'classes', 'extra_courses'] as $section_key) {
        $bg_color = trim($_POST[$section_key . '_bg_color'] ?? '');
        $card_colors = trim($_POST[$section_key . '_card_colors'] ?? '');
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

// Handle Marketing Posts Gallery
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
            
            if (in_array($file_ext, $allowed_extensions)) {
                $new_filename = 'post_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/posts/' . $new_filename;
                    $title = trim($_POST['post_title'] ?? '');
                    
                    $stmt = $conn->prepare("INSERT INTO home_posts (image_path, title, created_by) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $image_path, $title, $user_id);
                    if ($stmt->execute()) {
                        $success_message = 'Marketing post added successfully!';
                    } else {
                        $error_message = 'Failed to add post to database.';
                    }
                    $stmt->close();
                } else {
                    $error_message = 'Failed to upload image.';
                }
            } else {
                $error_message = 'Invalid image type.';
            }
        }
    }

    if (isset($_POST['delete_home_post'])) {
        $post_id = intval($_POST['post_id']);
        $stmt = $conn->prepare("SELECT image_path FROM home_posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (file_exists('../' . $row['image_path'])) {
                unlink('../' . $row['image_path']);
            }
            $del_stmt = $conn->prepare("DELETE FROM home_posts WHERE id = ?");
            $del_stmt->bind_param("i", $post_id);
            $del_stmt->execute();
            $del_stmt->close();
            $success_message = 'Marketing post deleted successfully!';
        }
        $stmt->close();
    }
}

// Fetch Homepage Videos
$homepage_videos = ['desktop' => null, 'mobile' => null];
$v_res = $conn->query("SELECT video_type, video_path FROM homepage_videos");
if ($v_res) {
    while ($row = $v_res->fetch_assoc()) {
        $homepage_videos[$row['video_type']] = $row['video_path'];
    }
}

// Fetch Marketing Posts
$home_posts = [];
$res_posts = $conn->query("SELECT * FROM home_posts ORDER BY created_at DESC");
if ($res_posts) {
    while ($row = $res_posts->fetch_assoc()) {
        $home_posts[] = $row;
    }
}

// Fetch Dashboard Theme Colors
$dashboard_colors = [];
$result_colors = $conn->query("SELECT * FROM dashboard_colors");
if ($result_colors) {
    while ($row = $result_colors->fetch_assoc()) {
        $dashboard_colors[$row['section_key']] = $row;
    }
}

// Fetch Result Posters
$result_posters = ['desktop' => null, 'mobile' => null];
$p_res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('result_poster_desktop', 'result_poster_mobile')");
if ($p_res) {
    while ($row = $p_res->fetch_assoc()) {
        if ($row['setting_key'] === 'result_poster_desktop') {
            $result_posters['desktop'] = $row['setting_value'];
        } elseif ($row['setting_key'] === 'result_poster_mobile') {
            $result_posters['mobile'] = $row['setting_value'];
        }
    }
}

// Fetch Student Showcase Images
$showcase_images = [
    'img1_desktop' => null,
    'img1_mobile'  => null,
    'img2_desktop' => null,
    'img2_mobile'  => null
];
$sc_res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('showcase_img1_desktop', 'showcase_img1_mobile', 'showcase_img2_desktop', 'showcase_img2_mobile')");
if ($sc_res) {
    while ($row = $sc_res->fetch_assoc()) {
        if ($row['setting_key'] === 'showcase_img1_desktop') $showcase_images['img1_desktop'] = $row['setting_value'];
        if ($row['setting_key'] === 'showcase_img1_mobile')  $showcase_images['img1_mobile']  = $row['setting_value'];
        if ($row['setting_key'] === 'showcase_img2_desktop') $showcase_images['img2_desktop'] = $row['setting_value'];
        if ($row['setting_key'] === 'showcase_img2_mobile')  $showcase_images['img2_mobile']  = $row['setting_value'];
    }
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
            color: #2563eb;
            border-color: #2563eb;
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

                    <!-- Tab Navigation Header -->
                    <div class="mb-6 pb-3 border-b border-gray-200">
                        <h3 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-video text-blue-600"></i>
                            <span>Manage Home Page Background Videos</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">Upload custom desktop and mobile background videos to display on the home landing page. Uploaded files are saved in <code class="bg-slate-100 text-blue-600 px-1.5 py-0.5 rounded font-mono">uploads/videos/</code>.</p>
                    </div>

                    <!-- Manage Home Page Content -->
                    <div id="tab-manage_homepage" class="tab-content">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Desktop Video Card -->
                            <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                                            <i class="fas fa-desktop text-blue-600"></i>
                                            <span>Desktop Background Video</span>
                                        </h4>
                                        <span class="text-[10px] font-extrabold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full border border-blue-200">Desktop View</span>
                                    </div>

                                    <!-- Preview Player -->
                                    <div class="mb-4">
                                        <?php if (!empty($homepage_videos['desktop'])): ?>
                                            <div class="relative rounded-2xl overflow-hidden border-2 border-slate-300 shadow-md bg-black w-full">
                                                <video controls class="w-full h-64 sm:h-80 object-contain">
                                                    <source src="../<?php echo htmlspecialchars($homepage_videos['desktop']); ?>" type="video/mp4">
                                                    Your browser does not support video playback.
                                                </video>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-2 font-mono truncate">
                                                <strong>Current File:</strong> <?php echo htmlspecialchars(basename($homepage_videos['desktop'])); ?>
                                            </p>
                                        <?php else: ?>
                                            <div class="w-full h-64 bg-slate-200/70 rounded-2xl border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 p-4 text-center">
                                                <i class="fas fa-video-slash text-4xl mb-2"></i>
                                                <p class="text-sm font-bold text-slate-600">No Custom Desktop Video Uploaded</p>
                                                <p class="text-xs text-slate-400">Using default Cloudinary desktop hero video</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Upload Form -->
                                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                        <input type="hidden" name="action_homepage_video" value="1">
                                        <input type="hidden" name="video_type" value="desktop">
                                        
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Desktop Video (MP4 / WEBM)</label>
                                            <input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg,video/quicktime" required
                                                class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                                            <p class="text-[11px] text-slate-400 mt-1">Recommended: MP4 format, 16:9 ratio, max 100MB.</p>
                                        </div>

                                        <div class="flex items-center gap-3 pt-2">
                                            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                                                <i class="fas fa-upload"></i> Save Desktop Video
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <?php if (!empty($homepage_videos['desktop'])): ?>
                                    <form method="POST" action="" class="mt-4 pt-4 border-t border-slate-200">
                                        <input type="hidden" name="action_homepage_video" value="1">
                                        <input type="hidden" name="video_type" value="desktop">
                                        <input type="hidden" name="remove_video" value="1">
                                        <button type="submit" onclick="return confirm('Remove custom desktop background video?')" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors flex items-center gap-1.5">
                                            <i class="fas fa-trash-alt"></i> Remove Custom Desktop Video
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Mobile Video Card -->
                            <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                                            <i class="fas fa-mobile-alt text-emerald-600"></i>
                                            <span>Mobile Background Video</span>
                                        </h4>
                                        <span class="text-[10px] font-extrabold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">Mobile View</span>
                                    </div>

                                    <!-- Preview Player (Portrait Frame) -->
                                    <div class="mb-4 flex flex-col items-center">
                                        <?php if (!empty($homepage_videos['mobile'])): ?>
                                            <div class="relative w-60 h-[420px] rounded-3xl overflow-hidden border-4 border-slate-800 shadow-xl bg-black flex items-center justify-center">
                                                <video controls class="w-full h-full object-cover">
                                                    <source src="../<?php echo htmlspecialchars($homepage_videos['mobile']); ?>" type="video/mp4">
                                                    Your browser does not support video playback.
                                                </video>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-2 font-mono truncate max-w-xs text-center">
                                                <strong>Current File:</strong> <?php echo htmlspecialchars(basename($homepage_videos['mobile'])); ?>
                                            </p>
                                        <?php else: ?>
                                            <div class="w-60 h-[420px] bg-slate-200/70 rounded-3xl border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 p-4 text-center">
                                                <i class="fas fa-video-slash text-4xl mb-2"></i>
                                                <p class="text-sm font-bold text-slate-600">No Custom Mobile Video Uploaded</p>
                                                <p class="text-xs text-slate-400">Using default Cloudinary mobile hero video</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Upload Form -->
                                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                        <input type="hidden" name="action_homepage_video" value="1">
                                        <input type="hidden" name="video_type" value="mobile">
                                        
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Mobile Video (MP4 / WEBM)</label>
                                            <input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg,video/quicktime" required
                                                class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                                            <p class="text-[11px] text-slate-400 mt-1">Recommended: MP4 format, 9:16 portrait ratio, max 100MB.</p>
                                        </div>

                                        <div class="flex items-center gap-3 pt-2">
                                            <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                                                <i class="fas fa-upload"></i> Save Mobile Video
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <?php if (!empty($homepage_videos['mobile'])): ?>
                                    <form method="POST" action="" class="mt-4 pt-4 border-t border-slate-200">
                                        <input type="hidden" name="action_homepage_video" value="1">
                                        <input type="hidden" name="video_type" value="mobile">
                                        <input type="hidden" name="remove_video" value="1">
                                        <button type="submit" onclick="return confirm('Remove custom mobile background video?')" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors flex items-center gap-1.5">
                                            <i class="fas fa-trash-alt"></i> Remove Custom Mobile Video
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>

                        <!-- Result Posters Section Header -->
                        <div class="mt-12 mb-6 pb-3 border-b border-gray-200">
                            <h3 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                                <i class="fas fa-image text-amber-500"></i>
                                <span>Manage Home Page Result Posters</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">Upload custom desktop and mobile result posters to display on the home landing page instead of the default student feedback section. Uploaded files are saved in <code class="bg-slate-100 text-amber-600 px-1.5 py-0.5 rounded font-mono">uploads/posters/</code>.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <!-- Desktop Result Poster Card -->
                            <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                                            <i class="fas fa-desktop text-amber-500"></i>
                                            <span>Desktop Result Poster</span>
                                        </h4>
                                        <span class="text-[10px] font-extrabold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-full border border-amber-200">Desktop View</span>
                                    </div>

                                    <!-- Preview Image -->
                                    <div class="mb-4">
                                        <?php if (!empty($result_posters['desktop']) && file_exists('../' . $result_posters['desktop'])): ?>
                                            <div class="relative rounded-2xl overflow-hidden border-2 border-slate-300 shadow-md bg-white w-full p-2 flex justify-center">
                                                <img src="../<?php echo htmlspecialchars($result_posters['desktop']); ?>" alt="Desktop Result Poster" class="w-full max-h-72 object-contain rounded-xl">
                                            </div>
                                            <p class="text-xs text-slate-500 mt-2 font-mono truncate">
                                                <strong>Current File:</strong> <?php echo htmlspecialchars(basename($result_posters['desktop'])); ?>
                                            </p>
                                        <?php else: ?>
                                            <div class="w-full h-64 bg-slate-200/70 rounded-2xl border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 p-4 text-center">
                                                <i class="fas fa-image text-4xl mb-2 text-slate-300"></i>
                                                <p class="text-sm font-bold text-slate-600">No Desktop Result Poster Uploaded</p>
                                                <p class="text-xs text-slate-400">If empty, the result poster section on the home page will be skipped.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Upload Form -->
                                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                        <input type="hidden" name="action_result_poster" value="1">
                                        <input type="hidden" name="poster_type" value="desktop">
                                        
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Desktop Poster (JPG / PNG / WEBP)</label>
                                            <input type="file" name="poster_file" accept="image/jpeg,image/png,image/gif,image/webp" required
                                                class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-amber-600 file:text-white hover:file:bg-amber-700 cursor-pointer">
                                            <p class="text-[11px] text-slate-400 mt-1">Recommended resolution: 1400x500px or wide landscape banner, max 15MB.</p>
                                        </div>

                                        <div class="flex items-center gap-3 pt-2">
                                            <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                                                <i class="fas fa-upload"></i> Save Desktop Poster
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <?php if (!empty($result_posters['desktop'])): ?>
                                    <form method="POST" action="" class="mt-4 pt-4 border-t border-slate-200">
                                        <input type="hidden" name="action_result_poster" value="1">
                                        <input type="hidden" name="poster_type" value="desktop">
                                        <input type="hidden" name="remove_poster" value="1">
                                        <button type="submit" onclick="return confirm('Remove custom desktop result poster?')" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors flex items-center gap-1.5">
                                            <i class="fas fa-trash-alt"></i> Remove Desktop Poster
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Mobile Result Poster Card -->
                            <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                                            <i class="fas fa-mobile-alt text-orange-500"></i>
                                            <span>Mobile Result Poster</span>
                                        </h4>
                                        <span class="text-[10px] font-extrabold text-orange-600 bg-orange-50 px-2.5 py-1 rounded-full border border-orange-200">Mobile View</span>
                                    </div>

                                    <!-- Preview Image -->
                                    <div class="mb-4 flex flex-col items-center">
                                        <?php if (!empty($result_posters['mobile']) && file_exists('../' . $result_posters['mobile'])): ?>
                                            <div class="relative w-64 max-h-80 rounded-2xl overflow-hidden border-2 border-slate-300 shadow-md bg-white p-2 flex justify-center">
                                                <img src="../<?php echo htmlspecialchars($result_posters['mobile']); ?>" alt="Mobile Result Poster" class="w-full max-h-72 object-contain rounded-xl">
                                            </div>
                                            <p class="text-xs text-slate-500 mt-2 font-mono truncate max-w-xs text-center">
                                                <strong>Current File:</strong> <?php echo htmlspecialchars(basename($result_posters['mobile'])); ?>
                                            </p>
                                        <?php else: ?>
                                            <div class="w-60 h-64 bg-slate-200/70 rounded-2xl border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 p-4 text-center">
                                                <i class="fas fa-mobile-alt text-4xl mb-2 text-slate-300"></i>
                                                <p class="text-sm font-bold text-slate-600">No Mobile Result Poster Uploaded</p>
                                                <p class="text-xs text-slate-400">If empty, mobile view will fall back to desktop poster or skip if none.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Upload Form -->
                                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                        <input type="hidden" name="action_result_poster" value="1">
                                        <input type="hidden" name="poster_type" value="mobile">
                                        
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Mobile Poster (JPG / PNG / WEBP)</label>
                                            <input type="file" name="poster_file" accept="image/jpeg,image/png,image/gif,image/webp" required
                                                class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-600 file:text-white hover:file:bg-orange-700 cursor-pointer">
                                            <p class="text-[11px] text-slate-400 mt-1">Recommended: Vertical or square mobile ratio, max 15MB.</p>
                                        </div>

                                        <div class="flex items-center gap-3 pt-2">
                                            <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                                                <i class="fas fa-upload"></i> Save Mobile Poster
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                 <?php if (!empty($result_posters['mobile'])): ?>
                                    <form method="POST" action="" class="mt-4 pt-4 border-t border-slate-200">
                                        <input type="hidden" name="action_result_poster" value="1">
                                        <input type="hidden" name="poster_type" value="mobile">
                                        <input type="hidden" name="remove_poster" value="1">
                                        <button type="submit" onclick="return confirm('Remove custom mobile result poster?')" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors flex items-center gap-1.5">
                                            <i class="fas fa-trash-alt"></i> Remove Mobile Poster
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Student Showcase Images Section Header -->
                        <div class="mt-12 mb-6 pb-3 border-b border-gray-200">
                            <h3 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                                <i class="fas fa-images text-emerald-600"></i>
                                <span>Manage Student Showcase Images (4 Image Slots)</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">Upload Facebook post size images for the 2 student showcase slots on the home landing page. Each slot supports dedicated <strong>Desktop</strong> and <strong>Mobile</strong> images. Uploaded images are stored in <code class="bg-slate-100 text-emerald-600 px-1.5 py-0.5 rounded font-mono">uploads/showcase/</code>.</p>
                        </div>

                        <!-- Showcase Image 1 (Desktop & Mobile) -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-red-100 text-red-600 font-black text-xs flex items-center justify-center">1</span>
                                <h4 class="text-base font-extrabold text-slate-800">Showcase Slot 1 (Left Image)</h4>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Image 1 Desktop -->
                                <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-4">
                                            <h5 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                                                <i class="fas fa-desktop text-red-500"></i>
                                                <span>Image 1 (Desktop View)</span>
                                            </h5>
                                            <span class="text-[10px] font-extrabold text-red-600 bg-red-50 px-2.5 py-1 rounded-full border border-red-200">Desktop</span>
                                        </div>

                                        <!-- Preview -->
                                        <div class="mb-4">
                                            <?php $cur_img1_d = !empty($showcase_images['img1_desktop']) ? $showcase_images['img1_desktop'] : null; ?>
                                            <div class="relative rounded-2xl overflow-hidden border-2 border-slate-300 shadow-md bg-white w-full p-2 flex justify-center min-h-[220px] items-center">
                                                <img id="preview_img1_desktop" src="<?php echo $cur_img1_d ? '../' . htmlspecialchars($cur_img1_d) : '../assests/smiling_student.png'; ?>" 
                                                     alt="Showcase 1 Desktop" class="w-full max-h-64 object-contain rounded-xl">
                                            </div>
                                            <p class="text-xs text-slate-500 mt-2 font-mono truncate">
                                                <strong>Current File:</strong> <?php echo $cur_img1_d ? htmlspecialchars(basename($cur_img1_d)) : 'Default (assests/smiling_student.png)'; ?>
                                            </p>
                                        </div>

                                        <!-- Form -->
                                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                            <input type="hidden" name="action_showcase_image" value="1">
                                            <input type="hidden" name="image_slot" value="img1_desktop">
                                            
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Desktop Image (JPG / PNG / WEBP)</label>
                                                <input type="file" name="showcase_file" accept="image/jpeg,image/png,image/gif,image/webp" required
                                                    onchange="previewShowcaseImage(event, 'preview_img1_desktop')"
                                                    class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-red-600 file:text-white hover:file:bg-red-700 cursor-pointer">
                                                <p class="text-[11px] text-slate-400 mt-1">Recommended: Facebook square (1080x1080) or landscape (1200x630), max 15MB.</p>
                                            </div>

                                            <div class="flex items-center gap-3 pt-2">
                                                <button type="submit" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                                                    <i class="fas fa-upload"></i> Save Image 1 Desktop
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <?php if (!empty($showcase_images['img1_desktop'])): ?>
                                        <form method="POST" action="" class="mt-4 pt-4 border-t border-slate-200">
                                            <input type="hidden" name="action_showcase_image" value="1">
                                            <input type="hidden" name="image_slot" value="img1_desktop">
                                            <input type="hidden" name="remove_showcase_image" value="1">
                                            <button type="submit" onclick="return confirm('Reset to default image?')" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors flex items-center gap-1.5">
                                                <i class="fas fa-trash-alt"></i> Reset to Default
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <!-- Image 1 Mobile -->
                                <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-4">
                                            <h5 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                                                <i class="fas fa-mobile-alt text-rose-500"></i>
                                                <span>Image 1 (Mobile View)</span>
                                            </h5>
                                            <span class="text-[10px] font-extrabold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200">Mobile</span>
                                        </div>

                                        <!-- Preview -->
                                        <div class="mb-4 flex flex-col items-center">
                                            <?php $cur_img1_m = !empty($showcase_images['img1_mobile']) ? $showcase_images['img1_mobile'] : null; ?>
                                            <div class="relative w-64 max-h-72 rounded-2xl overflow-hidden border-2 border-slate-300 shadow-md bg-white p-2 flex justify-center min-h-[220px] items-center">
                                                <img id="preview_img1_mobile" src="<?php echo $cur_img1_m ? '../' . htmlspecialchars($cur_img1_m) : '../assests/smiling_student.png'; ?>" 
                                                     alt="Showcase 1 Mobile" class="w-full max-h-64 object-contain rounded-xl">
                                            </div>
                                            <p class="text-xs text-slate-500 mt-2 font-mono truncate max-w-xs text-center">
                                                <strong>Current File:</strong> <?php echo $cur_img1_m ? htmlspecialchars(basename($cur_img1_m)) : 'Default (assests/smiling_student.png)'; ?>
                                            </p>
                                        </div>

                                        <!-- Form -->
                                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                            <input type="hidden" name="action_showcase_image" value="1">
                                            <input type="hidden" name="image_slot" value="img1_mobile">
                                            
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Mobile Image (JPG / PNG / WEBP)</label>
                                                <input type="file" name="showcase_file" accept="image/jpeg,image/png,image/gif,image/webp" required
                                                    onchange="previewShowcaseImage(event, 'preview_img1_mobile')"
                                                    class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-rose-600 file:text-white hover:file:bg-rose-700 cursor-pointer">
                                                <p class="text-[11px] text-slate-400 mt-1">Recommended: Facebook square (1:1) or portrait (4:5), max 15MB.</p>
                                            </div>

                                            <div class="flex items-center gap-3 pt-2">
                                                <button type="submit" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                                                    <i class="fas fa-upload"></i> Save Image 1 Mobile
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <?php if (!empty($showcase_images['img1_mobile'])): ?>
                                        <form method="POST" action="" class="mt-4 pt-4 border-t border-slate-200">
                                            <input type="hidden" name="action_showcase_image" value="1">
                                            <input type="hidden" name="image_slot" value="img1_mobile">
                                            <input type="hidden" name="remove_showcase_image" value="1">
                                            <button type="submit" onclick="return confirm('Reset to default image?')" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors flex items-center gap-1.5">
                                                <i class="fas fa-trash-alt"></i> Reset to Default
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Showcase Image 2 (Desktop & Mobile) -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 font-black text-xs flex items-center justify-center">2</span>
                                <h4 class="text-base font-extrabold text-slate-800">Showcase Slot 2 (Right Image)</h4>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Image 2 Desktop -->
                                <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-4">
                                            <h5 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                                                <i class="fas fa-desktop text-emerald-600"></i>
                                                <span>Image 2 (Desktop View)</span>
                                            </h5>
                                            <span class="text-[10px] font-extrabold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">Desktop</span>
                                        </div>

                                        <!-- Preview -->
                                        <div class="mb-4">
                                            <?php $cur_img2_d = !empty($showcase_images['img2_desktop']) ? $showcase_images['img2_desktop'] : null; ?>
                                            <div class="relative rounded-2xl overflow-hidden border-2 border-slate-300 shadow-md bg-white w-full p-2 flex justify-center min-h-[220px] items-center">
                                                <img id="preview_img2_desktop" src="<?php echo $cur_img2_d ? '../' . htmlspecialchars($cur_img2_d) : '../assests/student.png'; ?>" 
                                                     alt="Showcase 2 Desktop" class="w-full max-h-64 object-contain rounded-xl">
                                            </div>
                                            <p class="text-xs text-slate-500 mt-2 font-mono truncate">
                                                <strong>Current File:</strong> <?php echo $cur_img2_d ? htmlspecialchars(basename($cur_img2_d)) : 'Default (assests/student.png)'; ?>
                                            </p>
                                        </div>

                                        <!-- Form -->
                                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                            <input type="hidden" name="action_showcase_image" value="1">
                                            <input type="hidden" name="image_slot" value="img2_desktop">
                                            
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Desktop Image (JPG / PNG / WEBP)</label>
                                                <input type="file" name="showcase_file" accept="image/jpeg,image/png,image/gif,image/webp" required
                                                    onchange="previewShowcaseImage(event, 'preview_img2_desktop')"
                                                    class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                                                <p class="text-[11px] text-slate-400 mt-1">Recommended: Facebook square (1080x1080) or landscape (1200x630), max 15MB.</p>
                                            </div>

                                            <div class="flex items-center gap-3 pt-2">
                                                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                                                    <i class="fas fa-upload"></i> Save Image 2 Desktop
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <?php if (!empty($showcase_images['img2_desktop'])): ?>
                                        <form method="POST" action="" class="mt-4 pt-4 border-t border-slate-200">
                                            <input type="hidden" name="action_showcase_image" value="1">
                                            <input type="hidden" name="image_slot" value="img2_desktop">
                                            <input type="hidden" name="remove_showcase_image" value="1">
                                            <button type="submit" onclick="return confirm('Reset to default image?')" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors flex items-center gap-1.5">
                                                <i class="fas fa-trash-alt"></i> Reset to Default
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <!-- Image 2 Mobile -->
                                <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-4">
                                            <h5 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                                                <i class="fas fa-mobile-alt text-teal-500"></i>
                                                <span>Image 2 (Mobile View)</span>
                                            </h5>
                                            <span class="text-[10px] font-extrabold text-teal-600 bg-teal-50 px-2.5 py-1 rounded-full border border-teal-200">Mobile</span>
                                        </div>

                                        <!-- Preview -->
                                        <div class="mb-4 flex flex-col items-center">
                                            <?php $cur_img2_m = !empty($showcase_images['img2_mobile']) ? $showcase_images['img2_mobile'] : null; ?>
                                            <div class="relative w-64 max-h-72 rounded-2xl overflow-hidden border-2 border-slate-300 shadow-md bg-white p-2 flex justify-center min-h-[220px] items-center">
                                                <img id="preview_img2_mobile" src="<?php echo $cur_img2_m ? '../' . htmlspecialchars($cur_img2_m) : '../assests/student.png'; ?>" 
                                                     alt="Showcase 2 Mobile" class="w-full max-h-64 object-contain rounded-xl">
                                            </div>
                                            <p class="text-xs text-slate-500 mt-2 font-mono truncate max-w-xs text-center">
                                                <strong>Current File:</strong> <?php echo $cur_img2_m ? htmlspecialchars(basename($cur_img2_m)) : 'Default (assests/student.png)'; ?>
                                            </p>
                                        </div>

                                        <!-- Form -->
                                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                            <input type="hidden" name="action_showcase_image" value="1">
                                            <input type="hidden" name="image_slot" value="img2_mobile">
                                            
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Mobile Image (JPG / PNG / WEBP)</label>
                                                <input type="file" name="showcase_file" accept="image/jpeg,image/png,image/gif,image/webp" required
                                                    onchange="previewShowcaseImage(event, 'preview_img2_mobile')"
                                                    class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-teal-600 file:text-white hover:file:bg-teal-700 cursor-pointer">
                                                <p class="text-[11px] text-slate-400 mt-1">Recommended: Facebook square (1:1) or portrait (4:5), max 15MB.</p>
                                            </div>

                                            <div class="flex items-center gap-3 pt-2">
                                                <button type="submit" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                                                    <i class="fas fa-upload"></i> Save Image 2 Mobile
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <?php if (!empty($showcase_images['img2_mobile'])): ?>
                                        <form method="POST" action="" class="mt-4 pt-4 border-t border-slate-200">
                                            <input type="hidden" name="action_showcase_image" value="1">
                                            <input type="hidden" name="image_slot" value="img2_mobile">
                                            <input type="hidden" name="remove_showcase_image" value="1">
                                            <button type="submit" onclick="return confirm('Reset to default image?')" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors flex items-center gap-1.5">
                                                <i class="fas fa-trash-alt"></i> Reset to Default
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        function previewShowcaseImage(event, previewId) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewEl = document.getElementById(previewId);
                    if (previewEl) {
                        previewEl.src = e.target.result;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Automatically upload images & videos as soon as a file is selected (no button click needed)
        document.addEventListener('DOMContentLoaded', () => {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', function(e) {
                    if (this.files && this.files.length > 0) {
                        const form = this.closest('form');
                        if (form) {
                            // Find and update submit button inside form
                            const submitBtn = form.querySelector('button[type="submit"]');
                            if (submitBtn) {
                                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> Uploading...';
                                submitBtn.disabled = true;
                                submitBtn.classList.add('opacity-75', 'cursor-wait');
                            }
                            
                            // Show floating upload notification
                            let uploadToast = document.getElementById('auto-upload-toast');
                            if (!uploadToast) {
                                uploadToast = document.createElement('div');
                                uploadToast.id = 'auto-upload-toast';
                                uploadToast.className = 'fixed bottom-6 right-6 bg-slate-900/95 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 z-50 text-sm font-bold border border-slate-700 backdrop-blur-md animate-pulse';
                                uploadToast.innerHTML = '<i class="fas fa-cloud-upload-alt text-blue-400 text-xl animate-bounce"></i><span>Uploading file automatically, please wait...</span>';
                                document.body.appendChild(uploadToast);
                            }

                            // Submit form immediately
                            form.submit();
                        }
                    }
                });
            });

            const activeTab = <?php echo json_encode($active_tab); ?>;
            if (activeTab && document.getElementById('tab-' + activeTab)) {
                switchTab(activeTab);
            }
        });

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            const selectedTabContent = document.getElementById('tab-' + tabName);
            if (selectedTabContent) {
                selectedTabContent.classList.remove('hidden');
            }
            const selectedButton = document.querySelector(`[data-tab="${tabName}"]`);
            if (selectedButton) {
                selectedButton.classList.add('active');
            }
        }

        function updateHiddenInput(sectionKey) {
            const container = document.getElementById(sectionKey + '_chips_container');
            const hiddenInput = document.getElementById(sectionKey + '_card_colors');
            if (!container || !hiddenInput) return;
            const chips = container.querySelectorAll('span[data-color]');
            const colors = Array.from(chips).map(chip => chip.getAttribute('data-color'));
            hiddenInput.value = colors.join(',');
        }

        function removeColor(sectionKey, colorValue) {
            const container = document.getElementById(sectionKey + '_chips_container');
            if (!container) return;
            const selector = `span[data-color="${CSS.escape(colorValue)}"]`;
            const chip = container.querySelector(selector);
            if (chip) {
                chip.remove();
                updateHiddenInput(sectionKey);
                const hiddenInput = document.getElementById(sectionKey + '_card_colors');
                saveColorSetting(sectionKey, 'card_colors', hiddenInput.value);
            }
        }

        function addColor(sectionKey) {
            const input = document.getElementById(sectionKey + '_new_color_input');
            if (!input) return;
            let color = input.value.trim();
            if (!color) return;

            if (/^[a-fA-F0-9]{6}$/.test(color) || /^[a-fA-F0-9]{3}$/.test(color)) {
                color = '#' + color;
            }
            if (!/^#[a-fA-F0-9]{3}([a-fA-F0-9]{3})?$/.test(color)) {
                alert('Please enter a valid hex color code.');
                return;
            }

            const container = document.getElementById(sectionKey + '_chips_container');
            const existing = container.querySelector(`span[data-color="${CSS.escape(color)}"]`);
            if (existing) {
                alert('This color is already in the list.');
                return;
            }

            const chip = document.createElement('span');
            chip.setAttribute('data-color', color);
            chip.className = 'inline-flex items-center px-2.5 py-1 text-xs font-semibold border rounded-full gap-2 shadow-sm bg-white hover:bg-gray-50 transition-colors';
            
            const colorCircle = document.createElement('span');
            colorCircle.className = 'w-3.5 h-3.5 rounded-full border shadow-inner flex-shrink-0';
            colorCircle.style.backgroundColor = color;

            const textSpan = document.createElement('span');
            textSpan.className = 'text-gray-700 font-mono text-[11px]';
            textSpan.textContent = color;

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'text-gray-400 hover:text-red-500 font-bold ml-1 transition-colors text-sm focus:outline-none';
            deleteBtn.innerHTML = '&times;';
            deleteBtn.onclick = function() { removeColor(sectionKey, color); };

            chip.appendChild(colorCircle);
            chip.appendChild(textSpan);
            chip.appendChild(deleteBtn);

            container.appendChild(chip);
            updateHiddenInput(sectionKey);

            const hiddenInput = document.getElementById(sectionKey + '_card_colors');
            saveColorSetting(sectionKey, 'card_colors', hiddenInput.value);
        }

        function saveColorSetting(sectionKey, field, value) {
            const formData = new FormData();
            formData.append('action', 'update_section_color_ajax');
            formData.append('section_key', sectionKey);
            formData.append('field', field);
            formData.append('value', value);

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
                        setTimeout(() => { statusIndicator.classList.add('hidden'); }, 2000);
                    } else {
                        statusIndicator.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i> Save failed';
                    }
                }
            })
            .catch(() => {
                if (statusIndicator) {
                    statusIndicator.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i> Error';
                }
            });
        }

        function saveBgColor(sectionKey, colorValue) {
            colorValue = colorValue.trim();
            const picker = document.getElementById(sectionKey + '_bg_color_picker');
            if (picker && /^#[a-fA-F0-9]{3,6}$/.test(colorValue)) {
                picker.value = colorValue;
            }
            saveColorSetting(sectionKey, 'bg_color', colorValue);
        }
    </script>
</body>
</html>
