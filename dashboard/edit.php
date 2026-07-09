<?php
// edit.php - Edit user profile details (excluding email, including profile picture)
require_once __DIR__ . '/../config.php';

// Start session safely if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if (empty($user_id)) {
    header("Location: ../login.php");
    exit();
}

if ($role === 'student') {
    require_once __DIR__ . '/../check_al_redirection.php';
}

// Fetch current user details
$stmt = $conn->prepare("SELECT profile_picture, first_name, second_name, dob, school_name, exam_year, closest_town, district, address, nic_no, mobile_number, whatsapp_number, gender FROM users WHERE user_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    error_log("Prepare failed in edit.php: " . $conn->error);
}

if (!$user_data) {
    echo "User data not found.";
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $dob = !empty($_POST['dob']) ? trim($_POST['dob']) : null;
    $school_name = trim($_POST['school_name'] ?? '');
    $exam_year = !empty($_POST['exam_year']) ? intval($_POST['exam_year']) : null;
    $closest_town = trim($_POST['closest_town'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $nic_no = trim($_POST['nic_no'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    
    // File upload logic for profile picture
    $profile_picture = $user_data['profile_picture']; // Default to old picture
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_size = $_FILES['profile_picture']['size'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            if ($file_size <= 5 * 1024 * 1024) { // 5MB limit
                $new_filename = $user_id . '_' . time() . '.' . $ext;
                $upload_dir = __DIR__ . '/../uploads/profiles/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $target_file = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $target_file)) {
                    // Update photo path
                    $profile_picture = 'uploads/profiles/' . $new_filename;
                    
                    // Remove old image if exist and is not default
                    if (!empty($user_data['profile_picture']) && file_exists(__DIR__ . '/../' . $user_data['profile_picture'])) {
                        @unlink(__DIR__ . '/../' . $user_data['profile_picture']);
                    }
                } else {
                    $error_msg = "Error moving uploaded file.";
                }
            } else {
                $error_msg = "Image size exceeds 5MB limit.";
            }
        } else {
            $error_msg = "Unsupported image format. Allowed formats: JPG, JPEG, PNG, WEBP.";
        }
    }
    
    // Save to Database if no errors
    if (empty($error_msg)) {
        if (empty($first_name)) {
            $error_msg = "First Name cannot be empty.";
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET first_name = ?, second_name = ?, dob = ?, school_name = ?, exam_year = ?, closest_town = ?, district = ?, address = ?, nic_no = ?, mobile_number = ?, whatsapp_number = ?, gender = ?, profile_picture = ? WHERE user_id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("ssssisssssssss", $first_name, $second_name, $dob, $school_name, $exam_year, $closest_town, $district, $address, $nic_no, $mobile_number, $whatsapp_number, $gender, $profile_picture, $user_id);
                if ($update_stmt->execute()) {
                    $success_msg = "Your profile has been updated successfully!";
                    
                    // Update session credentials for instant header rendering
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['profile_picture'] = $profile_picture;
                    
                    // Update visual state variables
                    $user_data['first_name'] = $first_name;
                    $user_data['second_name'] = $second_name;
                    $user_data['dob'] = $dob;
                    $user_data['school_name'] = $school_name;
                    $user_data['exam_year'] = $exam_year;
                    $user_data['closest_town'] = $closest_town;
                    $user_data['district'] = $district;
                    $user_data['address'] = $address;
                    $user_data['nic_no'] = $nic_no;
                    $user_data['mobile_number'] = $mobile_number;
                    $user_data['whatsapp_number'] = $whatsapp_number;
                    $user_data['gender'] = $gender;
                    $user_data['profile_picture'] = $profile_picture;
                } else {
                    $error_msg = "Database update failure: " . $conn->error;
                }
                $update_stmt->close();
            } else {
                $error_msg = "Database preparation error.";
            }
        }
    }
}

// Get dashboard background
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

$districts = ["Ampara", "Anuradhapura", "Badulla", "Batticaloa", "Colombo", "Galle", "Gampaha", "Hambantota", "Jaffna", "Kalutara", "Kandy", "Kegalle", "Kilinochchi", "Kurunegala", "Mannar", "Matale", "Matara", "Moneragala", "Mullaitivu", "Nuwara Eliya", "Polonnaruwa", "Puttalam", "Ratnapura", "Trincomalee", "Vavuniya"];
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
    <title>Edit Profile - Learner.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            <?php if ($dashboard_background): ?>
            background-image: url('../<?php echo htmlspecialchars($dashboard_background); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            <?php endif; ?>
        }
        .content-overlay {
            <?php if ($dashboard_background): ?>
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.7);
            <?php else: ?>
            background: #f8fafc;
            <?php endif; ?>
            min-height: 100vh;
            padding-top: 6rem;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08);
        }
        .form-input {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-input:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php include 'navbar.php'; ?>

    <div class="content-overlay pb-16 px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Breadcrumbs -->
            <nav class="flex mb-6 text-sm font-medium text-slate-500 max-w-4xl mx-auto">
                <a href="profile.php" class="hover:text-red-600 transition-colors">Profile</a>
                <span class="mx-2 text-slate-300">/</span>
                <span class="text-slate-900 font-semibold">Edit Profile</span>
            </nav>

            <div class="glass-card rounded-[2rem] p-6 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-red-50 rounded-full -mr-32 -mt-32 opacity-40 blur-2xl"></div>
                
                <div class="relative z-10">
                    <div class="mb-8 border-b border-slate-100 pb-6">
                        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Edit Profile Details</h1>
                        <p class="text-slate-500 font-medium mt-1">Keep your credentials up to date to ensure seamless access.</p>
                    </div>

                    <?php if (!empty($success_msg)): ?>
                        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl flex items-center gap-3 animate-fade-in shadow-sm">
                            <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                            <span class="font-semibold text-sm"><?php echo htmlspecialchars($success_msg); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_msg)): ?>
                        <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl flex items-center gap-3 animate-fade-in shadow-sm">
                            <i class="fas fa-exclamation-circle text-rose-500 text-xl"></i>
                            <span class="font-semibold text-sm"><?php echo htmlspecialchars($error_msg); ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="edit.php" method="POST" enctype="multipart/form-data" class="space-y-8">
                        
                        <!-- Profile Image Section -->
                        <div class="flex flex-col sm:flex-row items-center gap-6 bg-slate-50/50 p-6 rounded-3xl border border-slate-100/55">
                            <div class="relative group">
                                <div class="w-28 h-28 rounded-full bg-red-600 flex items-center justify-center text-white text-4xl font-extrabold overflow-hidden border-4 border-white shadow-lg shadow-slate-200">
                                    <?php if (!empty($user_data['profile_picture'])): ?>
                                        <img id="avatar-preview" src="../<?php echo htmlspecialchars($user_data['profile_picture']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <span id="avatar-placeholder"><?php echo strtoupper(substr($user_data['first_name'] ?? 'U', 0, 1)); ?></span>
                                        <img id="avatar-preview" class="w-full h-full object-cover hidden">
                                    <?php endif; ?>
                                </div>
                                <label for="profile_picture" class="absolute bottom-0 right-0 bg-red-600 hover:bg-red-700 text-white w-9 h-9 rounded-full flex items-center justify-center cursor-pointer border-2 border-white shadow-md transition-all active:scale-95">
                                    <i class="fas fa-camera text-xs"></i>
                                </label>
                                <input type="file" name="profile_picture" id="profile_picture" accept="image/*" class="hidden" onchange="previewImage(event)">
                            </div>
                            <div class="text-center sm:text-left">
                                <h3 class="text-base font-bold text-slate-800">Profile Picture</h3>
                                <p class="text-xs text-slate-500 mt-1">Recommended: Square JPG, PNG, or WEBP (Max 5MB)</p>
                            </div>
                        </div>

                        <!-- Form Fields Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- First Name -->
                            <div>
                                <label for="first_name" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">First Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="first_name" id="first_name" required value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input">
                            </div>

                            <!-- Second Name -->
                            <div>
                                <label for="second_name" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Last Name</label>
                                <input type="text" name="second_name" id="second_name" value="<?php echo htmlspecialchars($user_data['second_name'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input">
                            </div>

                            <!-- Gender -->
                            <div>
                                <label for="gender" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Gender</label>
                                <select name="gender" id="gender" 
                                        class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none form-input">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($user_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>

                            <!-- Date of Birth -->
                            <div>
                                <label for="dob" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Date of Birth</label>
                                <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($user_data['dob'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none form-input">
                            </div>

                            <!-- Mobile Number -->
                            <div>
                                <label for="mobile_number" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Mobile Number</label>
                                <input type="text" name="mobile_number" id="mobile_number" value="<?php echo htmlspecialchars($user_data['mobile_number'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input">
                            </div>

                            <!-- WhatsApp Number -->
                            <div>
                                <label for="whatsapp_number" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">WhatsApp Number</label>
                                <input type="text" name="whatsapp_number" id="whatsapp_number" value="<?php echo htmlspecialchars($user_data['whatsapp_number'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input">
                            </div>

                            <!-- NIC No -->
                            <div>
                                <label for="nic_no" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">NIC Number</label>
                                <input type="text" name="nic_no" id="nic_no" value="<?php echo htmlspecialchars($user_data['nic_no'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input">
                            </div>

                            <!-- District -->
                            <div>
                                <label for="district" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">District</label>
                                <select name="district" id="district" 
                                        class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none form-input">
                                    <option value="">Select District</option>
                                    <?php foreach ($districts as $d): ?>
                                        <option value="<?php echo $d; ?>" <?php echo ($user_data['district'] ?? '') === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Closest Town -->
                            <div>
                                <label for="closest_town" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Closest Town</label>
                                <input type="text" name="closest_town" id="closest_town" value="<?php echo htmlspecialchars($user_data['closest_town'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input">
                            </div>

                            <!-- School Name (Only for students) -->
                            <?php if ($role === 'student'): ?>
                            <div>
                                <label for="school_name" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">School Name</label>
                                <input type="text" name="school_name" id="school_name" value="<?php echo htmlspecialchars($user_data['school_name'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input">
                            </div>

                            <!-- Exam Year (Only for students) -->
                            <div>
                                <label for="exam_year" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Exam Year</label>
                                <input type="number" name="exam_year" id="exam_year" min="2020" max="2035" value="<?php echo htmlspecialchars($user_data['exam_year'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input">
                            </div>
                            <?php endif; ?>

                        </div>

                        <!-- Address Field (Takes full width) -->
                        <div>
                            <label for="address" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Full Address</label>
                            <textarea name="address" id="address" rows="3" 
                                      class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none form-input"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end gap-4 border-t border-slate-100 pt-6">
                            <a href="profile.php" class="px-6 py-3.5 bg-slate-100 text-slate-600 font-bold rounded-2xl hover:bg-slate-200 transition-all text-sm active:scale-95 shadow-sm">Cancel</a>
                            <button type="submit" class="px-8 py-3.5 bg-red-600 text-white font-black rounded-2xl hover:bg-red-700 transition-all text-sm active:scale-95 shadow-md shadow-red-600/20">Save Changes</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatar-preview');
                    const placeholder = document.getElementById('avatar-placeholder');
                    
                    if (preview) {
                        preview.src = e.target.result;
                        preview.classList.remove('hidden');
                    }
                    if (placeholder) {
                        placeholder.classList.add('hidden');
                    }
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
