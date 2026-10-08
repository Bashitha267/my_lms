<?php
require_once '../check_session.php';
require_once '../config.php';

// Verify admin
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied");
}

$user_id = $_GET['user_id'] ?? '';
if (empty($user_id)) {
    header("Location: users.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fname = trim($_POST['first_name']);
    $sname = trim($_POST['second_name']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile_number']);
    $whatsapp = trim($_POST['whatsapp_number']);
    $role = $_POST['role'];
    $commission_rate = floatval($_POST['commission_rate'] ?? 0);
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
    $password = $_POST['password'] ?? '';
    
    // Update basic info
    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET first_name=?, second_name=?, email=?, mobile_number=?, whatsapp_number=?, role=?, commission_rate=?, hourly_rate=?, password=? WHERE user_id=?");
        $stmt->bind_param("ssssssddss", $fname, $sname, $email, $mobile, $whatsapp, $role, $commission_rate, $hourly_rate, $password_hash, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name=?, second_name=?, email=?, mobile_number=?, whatsapp_number=?, role=?, commission_rate=?, hourly_rate=? WHERE user_id=?");
        $stmt->bind_param("ssssssdds", $fname, $sname, $email, $mobile, $whatsapp, $role, $commission_rate, $hourly_rate, $user_id);
    }
    
    if ($stmt->execute()) {
        $success_msg = "User profile updated successfully.";
        
        // Handle Profile Picture Upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK && !empty($_FILES['profile_picture']['name'])) {
            $upload_dir = '../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['profile_picture'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                $error_msg = "Profile updated, but invalid image type.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error_msg = "Profile updated, but image too large (Max 5MB).";
            } else {
                $new_filename = $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $db_path = 'uploads/profiles/' . $new_filename;
                    
                    // Update DB with new image path
                    $stmt_img = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                    $stmt_img->bind_param("ss", $db_path, $user_id);
                    $stmt_img->execute();
                    $stmt_img->close();
                } else {
                    $error_msg = "Profile updated, but failed to upload image.";
                }
            }
        }
    } else {
        $error_msg = "Error updating profile: " . $conn->error;
    }
    $stmt->close();
}

// Handle Assignment Addition / Removal (Teacher Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    $stream_subject_id = intval($_POST['stream_subject_id'] ?? 0);
    $academic_year = intval($_POST['academic_year'] ?? date('Y'));
    
    if ($stream_subject_id > 0 && $academic_year > 2000) {
        $chk_stmt = $conn->prepare("SELECT id FROM teacher_assignments WHERE teacher_id = ? AND stream_subject_id = ? AND academic_year = ?");
        $chk_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
        $chk_stmt->execute();
        if ($chk_stmt->get_result()->num_rows > 0) {
            $error_msg = "This class is already assigned to this teacher for academic year $academic_year.";
        } else {
            $ins_stmt = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, stream_subject_id, academic_year, status, assigned_date) VALUES (?, ?, ?, 'active', CURDATE())");
            $ins_stmt->bind_param("sii", $user_id, $stream_subject_id, $academic_year);
            if ($ins_stmt->execute()) {
                $success_msg = "New class assignment added successfully!";
            } else {
                $error_msg = "Error assigning class: " . $conn->error;
            }
            $ins_stmt->close();
        }
        $chk_stmt->close();
    } else {
        $error_msg = "Please select a valid subject and academic year.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $stmt = $conn->prepare("DELETE FROM teacher_assignments WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("is", $assignment_id, $user_id);
    if ($stmt->execute()) {
        $success_msg = "Assignment removed.";
    } else {
        $error_msg = "Error removing assignment: " . $conn->error;
    }
    $stmt->close();
}

// Handle Education Operations (Teacher Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_education'])) {
        $qual = trim($_POST['qualification']);
        $inst = trim($_POST['institution']);
        $year = intval($_POST['year_obtained']);
        $field = '';
        
        $stmt = $conn->prepare("INSERT INTO teacher_education (teacher_id, qualification, institution, year_obtained, field_of_study) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $user_id, $qual, $inst, $year, $field);
        if ($stmt->execute()) {
            $success_msg = "Education added successfully.";
        } else {
            $error_msg = "Error adding education: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['update_education'])) {
        $edu_id = intval($_POST['education_id']);
        $qual = trim($_POST['qualification']);
        $inst = trim($_POST['institution']);
        $year = intval($_POST['year_obtained']);
        $field = '';
        
        $stmt = $conn->prepare("UPDATE teacher_education SET qualification=?, institution=?, year_obtained=?, field_of_study=? WHERE id=? AND teacher_id=?");
        $stmt->bind_param("ssisis", $qual, $inst, $year, $field, $edu_id, $user_id);
        if ($stmt->execute()) {
            $success_msg = "Education updated successfully.";
        } else {
            $error_msg = "Error updating education: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['delete_education'])) {
        $edu_id = intval($_POST['education_id']);
        $stmt = $conn->prepare("DELETE FROM teacher_education WHERE id=? AND teacher_id=?");
        $stmt->bind_param("is", $edu_id, $user_id);
        if ($stmt->execute()) {
            $success_msg = "Education removed successfully.";
        } else {
            $error_msg = "Error removing education: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch User Details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

// Fetch Assignments if Teacher
$assignments = [];
$education = [];
if ($user['role'] === 'teacher') {
    // Assignments
    $stmt = $conn->prepare("
        SELECT ta.id, ta.academic_year, s.name as stream_name, sub.name as subject_name 
        FROM teacher_assignments ta
        JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
        JOIN streams s ON ss.stream_id = s.id
        JOIN subjects sub ON ss.subject_id = sub.id
        WHERE ta.teacher_id = ?
        ORDER BY ta.academic_year DESC
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $assignments[] = $row;
    $stmt->close();

    // Education
    $stmt = $conn->prepare("SELECT * FROM teacher_education WHERE teacher_id = ? ORDER BY year_obtained DESC");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $education[] = $row;
    $stmt->close();

    // Fetch all available streams
    $all_streams = [];
    $s_res = $conn->query("SELECT * FROM streams ORDER BY name ASC");
    if ($s_res) {
        while ($row = $s_res->fetch_assoc()) {
            $all_streams[] = $row;
        }
    }

    // Fetch all available stream subjects with stream_id & subject_name
    $all_stream_subjects = [];
    $ss_res = $conn->query("
        SELECT ss.id as stream_subject_id, ss.stream_id, sub.name as subject_name 
        FROM stream_subjects ss
        JOIN subjects sub ON ss.subject_id = sub.id
        WHERE ss.status = 1 AND sub.status = 1
        ORDER BY sub.name ASC
    ");
    if ($ss_res) {
        while ($row = $ss_res->fetch_assoc()) {
            $all_stream_subjects[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-3xl font-bold text-gray-900">Edit User: <span class="text-red-600"><?php echo htmlspecialchars($user['user_id']); ?></span></h1>
            <a href="users.php" class="text-gray-600 hover:text-gray-900">&larr; Back to Users</a>
        </div>

        <?php if($success_msg): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- LEFT: Profile Edit & Education -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Profile Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2">Profile Details</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <!-- Profile Check/Upload -->
                        <div class="mb-6 flex items-center space-x-6">
                            <div class="shrink-0">
                                <?php if (!empty($user['profile_picture'])): ?>
                                    <img class="h-24 w-24 object-cover rounded-full border-2 border-gray-200" src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Current profile photo" />
                                <?php else: ?>
                                    <div class="h-24 w-24 rounded-full bg-gray-200 flex items-center justify-center text-gray-400 text-2xl font-bold">
                                        <?php echo strtoupper(substr($user['first_name'] ?: $user['user_id'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Profile Photo</label>
                                <div class="mt-1 flex items-center">
                                    <input type="file" name="profile_picture" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                                </div>
                                <p class="mt-1 text-xs text-gray-500">JPG, PNG, WEBP up to 5MB</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 p-2 border">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Second Name</label>
                                <input type="text" name="second_name" value="<?php echo htmlspecialchars($user['second_name'] ?? ''); ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 p-2 border">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 p-2 border">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                <select name="role" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 p-2 border">
                                    <option value="student" <?php echo $user['role']=='student'?'selected':''; ?>>Student</option>
                                    <option value="teacher" <?php echo $user['role']=='teacher'?'selected':''; ?>>Teacher</option>
                                    <option value="admin" <?php echo $user['role']=='admin'?'selected':''; ?>>Admin</option>
                                    <option value="instructor" <?php echo $user['role']=='instructor'?'selected':''; ?>>Instructor</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                                <input type="text" name="mobile_number" value="<?php echo htmlspecialchars($user['mobile_number'] ?? ''); ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 p-2 border">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Whatsapp Number</label>
                                <input type="text" name="whatsapp_number" value="<?php echo htmlspecialchars($user['whatsapp_number'] ?? ''); ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 p-2 border">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input type="password" name="password" placeholder="Leave blank to keep current password" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 p-2 border">
                            </div>

                            <?php if ($user['role'] === 'teacher'): ?>
                            <div class="md:col-span-2 bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                                <label class="block text-sm font-bold text-yellow-800 mb-1">Teacher Commission Rate (%)</label>
                                <div class="relative max-w-xs">
                                    <input type="number" name="commission_rate" step="0.01" value="<?php echo htmlspecialchars($user['commission_rate'] ?? '75.00'); ?>" class="w-full border-yellow-200 rounded-md shadow-sm focus:border-yellow-500 focus:ring-yellow-500 p-2 border pr-8 font-bold">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-yellow-400 font-bold">%</span>
                                </div>
                                <p class="text-xs text-yellow-600 mt-1">Percentage the teacher receives from course payments.</p>
                            </div>
                            <?php endif; ?>

                            <?php if ($user['role'] === 'instructor'): ?>
                            <div class="md:col-span-2 bg-purple-50 p-4 rounded-lg border border-purple-100">
                                <label class="block text-sm font-bold text-purple-800 mb-1">Instructor Session Rate (LKR)</label>
                                <div class="relative max-w-xs">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-purple-400 font-bold">LKR</span>
                                    <input type="number" name="hourly_rate" step="0.01" value="<?php echo htmlspecialchars($user['hourly_rate'] ?? '0.00'); ?>" class="w-full border-purple-200 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500 p-2 border pl-12 font-bold">
                                </div>
                                <p class="text-xs text-purple-600 mt-1">Fixed payment amount per assigned mentor session.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700 font-medium transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Education Section (Teachers Only) -->
                <?php if($user['role'] === 'teacher'): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2">Education Details</h2>
                    
                    <!-- Existing Education -->
                    <?php if(!empty($education)): ?>
                        <div class="space-y-4 mb-8">
                            <?php foreach($education as $edu): ?>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <input type="hidden" name="update_education" value="1">
                                        <input type="hidden" name="education_id" value="<?php echo $edu['id']; ?>">
                                        
                                        <div>
                                            <label class="text-xs text-gray-500">Qualification</label>
                                            <input type="text" name="qualification" value="<?php echo htmlspecialchars($edu['qualification']); ?>" class="w-full text-sm border-gray-300 rounded p-1">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">Institution</label>
                                            <input type="text" name="institution" value="<?php echo htmlspecialchars($edu['institution']); ?>" class="w-full text-sm border-gray-300 rounded p-1">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">Year</label>
                                            <input type="number" name="year_obtained" value="<?php echo htmlspecialchars($edu['year_obtained']); ?>" class="w-full text-sm border-gray-300 rounded p-1">
                                        </div>
                                        
                                        <div class="md:col-span-2 flex justify-end space-x-2 mt-2">
                                            <button type="submit" class="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">Update</button>
                                            <button type="submit" formaction="" name="delete_education" value="1" onclick="return confirm('Delete this qualification?');" class="text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700">Delete</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add New -->
                    <div class="border-t pt-4">
                        <h3 class="text-sm font-bold text-gray-700 mb-3">Add New Qualification</h3>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="hidden" name="add_education" value="1">
                            <div>
                                <input type="text" name="qualification" placeholder="Qualification (e.g. BSc)" class="w-full text-sm border-gray-300 rounded p-2" required>
                            </div>
                            <div>
                                <input type="text" name="institution" placeholder="Institution" class="w-full text-sm border-gray-300 rounded p-2">
                            </div>
                            <div class="md:col-span-2">
                                <input type="number" name="year_obtained" placeholder="Year" class="w-full text-sm border-gray-300 rounded p-2">
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-green-700">Add Qualification</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Assignments (If Teacher) -->
            <?php if($user['role'] === 'teacher'): ?>
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Assigned Classes</h2>
                        
                        <?php if(empty($assignments)): ?>
                            <p class="text-gray-500 text-sm mb-4">No classes assigned.</p>
                        <?php else: ?>
                            <div class="space-y-3 mb-6">
                                <?php foreach($assignments as $a): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <div>
                                            <p class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($a['subject_name']); ?></p>
                                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($a['stream_name']); ?> (<?php echo $a['academic_year']; ?>)</p>
                                        </div>
                                        <form method="POST" onsubmit="return confirm('Remove this class assignment?');">
                                            <input type="hidden" name="remove_assignment" value="1">
                                            <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700" title="Remove">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Add New Class Enrollment -->
                        <div class="border-t pt-4">
                            <h3 class="font-bold text-gray-800 text-sm mb-3">+ Assign New Class / Subject</h3>
                            <form method="POST" class="space-y-3">
                                <input type="hidden" name="add_assignment" value="1">
                                <div>
                                    <label class="block text-xs text-gray-500 font-semibold mb-1">1. Select Stream</label>
                                    <select id="stream_select" onchange="filterSubjectsByStream(this.value)" class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                        <option value="">-- Choose Stream --</option>
                                        <?php foreach($all_streams as $st): ?>
                                            <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 font-semibold mb-1">2. Select Subject</label>
                                    <select name="stream_subject_id" id="subject_select" required class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                        <option value="">-- Choose Stream First --</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 font-semibold mb-1">3. Academic Year</label>
                                    <select name="academic_year" required class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                        <?php 
                                        $curr_y = (int)date('Y');
                                        for ($y = $curr_y + 2; $y >= $curr_y - 2; $y--): 
                                        ?>
                                            <option value="<?php echo $y; ?>" <?php echo $y === $curr_y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold text-xs py-2.5 px-4 rounded-lg shadow-sm transition-all flex items-center justify-center gap-2 mt-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    <span>Assign Class Enrollment</span>
                                </button>
                            </form>
                        </div>

                        <script>
                            const allStreamSubjects = <?php echo json_encode($all_stream_subjects); ?>;

                            function filterSubjectsByStream(streamId) {
                                const subjectSelect = document.getElementById('subject_select');
                                subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';
                                
                                if (!streamId) {
                                    subjectSelect.innerHTML = '<option value="">-- Choose Stream First --</option>';
                                    return;
                                }
                                
                                const filtered = allStreamSubjects.filter(item => item.stream_id == streamId);
                                if (filtered.length === 0) {
                                    subjectSelect.innerHTML = '<option value="">No subjects found in stream</option>';
                                    return;
                                }
                                
                                filtered.forEach(item => {
                                    const opt = document.createElement('option');
                                    opt.value = item.stream_subject_id;
                                    opt.textContent = item.subject_name;
                                    subjectSelect.appendChild(opt);
                                });
                            }
                        </script>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
