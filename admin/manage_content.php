<?php
require_once '../check_session.php';
require_once '../config.php';

// Ensure user is admin
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle Actions (Add, Edit, Enable, Disable, Delete Stream & Subject, Unassign Teacher)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id = intval($_POST['id'] ?? 0);

        if ($action === 'add_stream') {
            $stream_name = trim($_POST['stream_name'] ?? '');
            if (!empty($stream_name)) {
                $chk = $conn->prepare("SELECT id FROM streams WHERE LOWER(name) = LOWER(?)");
                $chk->bind_param("s", $stream_name);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $error_message = "Stream '$stream_name' already exists.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO streams (name, status) VALUES (?, 1)");
                    $stmt->bind_param("s", $stream_name);
                    if ($stmt->execute()) {
                        $success_message = "Stream '$stream_name' created successfully.";
                    } else {
                        $error_message = "Failed to create stream.";
                    }
                    $stmt->close();
                }
                $chk->close();
            } else {
                $error_message = "Stream name is required.";
            }

        } elseif ($action === 'add_subject') {
            $stream_id = intval($_POST['stream_id'] ?? 0);
            $subject_name = trim($_POST['subject_name'] ?? '');
            $subject_code = trim($_POST['subject_code'] ?? '');

            if ($stream_id > 0 && !empty($subject_name)) {
                // Find existing subject (case-insensitive) or create new
                $chk_sub = $conn->prepare("SELECT id, name FROM subjects WHERE LOWER(name) = LOWER(?)");
                $chk_sub->bind_param("s", $subject_name);
                $chk_sub->execute();
                $res_sub = $chk_sub->get_result();

                if ($sub_row = $res_sub->fetch_assoc()) {
                    $subject_id = $sub_row['id'];
                    $actual_name = $sub_row['name'];
                } else {
                    $ins_sub = $conn->prepare("INSERT INTO subjects (name, code, status) VALUES (?, ?, 1)");
                    $ins_sub->bind_param("ss", $subject_name, $subject_code);
                    $ins_sub->execute();
                    $subject_id = $ins_sub->insert_id;
                    $actual_name = $subject_name;
                    $ins_sub->close();
                }
                $chk_sub->close();

                // Link to stream_subjects
                $chk_map = $conn->prepare("SELECT id FROM stream_subjects WHERE stream_id = ? AND subject_id = ?");
                $chk_map->bind_param("ii", $stream_id, $subject_id);
                $chk_map->execute();
                $res_map = $chk_map->get_result();

                if ($res_map->num_rows == 0) {
                    $ins_map = $conn->prepare("INSERT INTO stream_subjects (stream_id, subject_id, status) VALUES (?, ?, 1)");
                    $ins_map->bind_param("ii", $stream_id, $subject_id);
                    $ins_map->execute();
                    $ins_map->close();
                    $success_message = "Subject '$actual_name' linked to stream successfully.";
                } else {
                    $error_message = "Subject '$actual_name' is already assigned to this stream.";
                }
                $chk_map->close();
            } else {
                $error_message = "Please select a valid stream and enter a subject name.";
            }

        } elseif ($action === 'delete_stream') {
            $stmt = $conn->prepare("UPDATE streams SET status = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Stream disabled successfully.";
            } else {
                $error_message = "Failed to disable stream.";
            }
            $stmt->close();

        } elseif ($action === 'enable_stream') {
            $stmt = $conn->prepare("UPDATE streams SET status = 1 WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Stream enabled successfully.";
            } else {
                $error_message = "Failed to enable stream.";
            }
            $stmt->close();
        
        } elseif ($action === 'delete_subject') {
            $stmt = $conn->prepare("UPDATE subjects SET status = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Subject disabled successfully.";
            } else {
                $error_message = "Failed to disable subject.";
            }
            $stmt->close();

        } elseif ($action === 'enable_subject') {
            $stmt = $conn->prepare("UPDATE subjects SET status = 1 WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Subject enabled successfully.";
            } else {
                $error_message = "Failed to enable subject.";
            }
            $stmt->close();

        } elseif ($action === 'remove_teacher_assignment') {
            $stmt = $conn->prepare("DELETE FROM teacher_assignments WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Teacher unassigned successfully.";
            } else {
                $error_message = "Failed to unassign teacher.";
            }
            $stmt->close();

        } elseif ($action === 'update_subject_name') {
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                $stmt = $conn->prepare("UPDATE subjects SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $name, $id);
                if ($stmt->execute()) {
                    $success_message = "Subject name updated successfully.";
                } else {
                    $error_message = "Failed to update subject name.";
                }
                $stmt->close();
            }

        } elseif ($action === 'permanently_delete_stream') {
            $conn->query("DELETE FROM stream_subjects WHERE stream_id = $id");
            $stmt = $conn->prepare("DELETE FROM streams WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Stream permanently deleted.";
            } else {
                $error_message = "Failed to delete stream. It may have associated data.";
            }
            $stmt->close();
            
        } elseif ($action === 'permanently_delete_subject') {
            $conn->query("DELETE FROM stream_subjects WHERE subject_id = $id");
            $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Subject permanently deleted.";
            } else {
                $error_message = "Failed to delete subject. It may have associated data.";
            }
            $stmt->close();
            
        } elseif ($action === 'update_stream_name') {
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                $stmt = $conn->prepare("UPDATE streams SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $name, $id);
                if ($stmt->execute()) {
                    $success_message = "Stream name updated successfully.";
                } else {
                    $error_message = "Failed to update stream name.";
                }
                $stmt->close();
            }
        }
    }
}

// Fetch Streams
$streams = [];
$stream_query = "SELECT * FROM streams ORDER BY id ASC";
$result = $conn->query($stream_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $streams[] = $row;
    }
}

// Fetch All Existing Master Subjects for Auto-Suggest
$all_master_subjects = [];
$ams_res = $conn->query("SELECT id, name, code FROM subjects WHERE status = 1 ORDER BY name ASC");
if ($ams_res) {
    while ($r = $ams_res->fetch_assoc()) {
        $all_master_subjects[] = $r;
    }
}

// Structure Streams -> Subjects -> Teachers
$structure = [];
foreach ($streams as $stream) {
    $s_id = $stream['id'];
    $structure[$s_id] = [
        'info' => $stream,
        'subjects' => []
    ];
}

$subj_query = "
    SELECT ss.id as stream_subject_id, ss.stream_id, s.id as subject_id, s.name as subject_name, s.code, s.status as subject_status
    FROM stream_subjects ss
    JOIN subjects s ON ss.subject_id = s.id
    WHERE ss.status = 1 
    ORDER BY s.name ASC
";

$subj_result = $conn->query($subj_query);
if ($subj_result) {
    while ($row = $subj_result->fetch_assoc()) {
        if (isset($structure[$row['stream_id']])) {
            $structure[$row['stream_id']]['subjects'][$row['stream_subject_id']] = [
                'info' => $row,
                'teachers' => []
            ];
        }
    }
}

$teacher_query = "
    SELECT ta.id as assignment_id, ta.stream_subject_id, u.user_id, u.first_name, u.second_name, u.profile_picture
    FROM teacher_assignments ta
    JOIN users u ON ta.teacher_id = u.user_id
    WHERE ta.status = 'active'
";
$teacher_result = $conn->query($teacher_query);
if ($teacher_result) {
    while ($row = $teacher_result->fetch_assoc()) {
        foreach ($structure as $s_id => &$stream_data) {
            if (isset($stream_data['subjects'][$row['stream_subject_id']])) {
                $stream_data['subjects'][$row['stream_subject_id']]['teachers'][] = $row;
            }
        }
    }
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
    <title>Manage Subjects - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [data-tooltip] { position: relative; }
        [data-tooltip]::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 500;
            white-space: nowrap;
            padding: 4px 10px;
            border-radius: 6px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease;
            z-index: 50;
        }
        [data-tooltip]:hover::after { opacity: 1; }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            transition: all 0.15s;
            border: none;
            cursor: pointer;
        }
        .action-btn:hover { opacity: 0.85; transform: translateY(-1px); }
        .btn-disable  { background: #fee2e2; color: #b91c1c; }
        .btn-enable   { background: #dcfce7; color: #15803d; }
        .btn-edit     { background: #dbeafe; color: #1d4ed8; }
        .btn-delete   { background: #f1f5f9; color: #dc2626; }
        .btn-unassign { background: #fef3c7; color: #b45309; }
        .subject-row { transition: background 0.15s; }
        .subject-row:hover { background: #f8fafc; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        
        <!-- Header area with Add Stream button -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Manage Subjects</h1>
                <p class="mt-2 text-sm text-gray-600">Manage Streams, Standardized Subjects, and Teacher Assignments.</p>
            </div>
            <div>
                <button onclick="openAddStreamModal()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-lg shadow-md flex items-center gap-2 transition cursor-pointer">
                    <i class="fas fa-plus"></i> Add New Stream
                </button>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="mb-4 p-4 rounded-md bg-green-50 border border-green-200 text-green-700 font-medium">
                <i class="fas fa-check-circle mr-1"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-4 p-4 rounded-md bg-red-50 border border-red-200 text-red-700 font-medium">
                <i class="fas fa-exclamation-circle mr-1"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter & Search Bar -->
        <div class="mb-6 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200 flex-1 max-w-md">
                <i class="fas fa-search text-gray-400"></i>
                <input type="text" 
                       id="streamSearchInput" 
                       oninput="filterAdminStreams()" 
                       placeholder="Search stream or subject name..." 
                       class="w-full text-sm bg-transparent border-none focus:outline-none text-gray-800 placeholder-gray-400">
            </div>
            
            <div class="flex items-center gap-2 shrink-0">
                <label for="streamFilterSelect" class="text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Filter Stream:</label>
                <select id="streamFilterSelect" 
                        onchange="filterAdminStreams()" 
                        class="px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm font-bold text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                    <option value="all">All Streams (<?php echo count($structure); ?>)</option>
                    <?php foreach ($structure as $s_id => $s_data): ?>
                        <option value="<?php echo $s_id; ?>"><?php echo htmlspecialchars($s_data['info']['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <?php foreach ($structure as $stream_id => $data): ?>
                    <?php 
                    $stream = $data['info']; 
                    $is_active = $stream['status'] == 1; 
                    $sub_names_str = implode(' ', array_map(function($s) { return strtolower($s['info']['subject_name']); }, $data['subjects']));
                    ?>
                    <div class="stream-card bg-white rounded-xl shadow border <?php echo $is_active ? 'border-red-300' : 'border-gray-300 opacity-75'; ?> overflow-hidden transition-all duration-200 hover:shadow-lg flex flex-col justify-between"
                         data-stream-id="<?php echo $stream['id']; ?>"
                         data-stream-name="<?php echo htmlspecialchars(strtolower($stream['name'])); ?>"
                         data-subjects="<?php echo htmlspecialchars($sub_names_str); ?>">

                        <div>
                            <!-- Stream Header -->
                            <div class="px-5 py-4 <?php echo $is_active ? 'bg-gradient-to-r from-red-50 to-pink-50' : 'bg-gray-100'; ?> border-b <?php echo $is_active ? 'border-red-200' : 'border-gray-200'; ?>">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 <?php echo $is_active ? 'bg-green-500' : 'bg-gray-400'; ?>"></div>
                                        <h3 class="text-base font-bold text-gray-900 leading-tight truncate">
                                            <?php echo htmlspecialchars($stream['name']); ?>
                                        </h3>
                                        <?php if (!$is_active): ?>
                                            <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Disabled</span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Stream Action Buttons -->
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <!-- Enable/Disable -->
                                        <form method="POST" class="inline" onsubmit="return confirm('<?php echo $is_active ? 'Disable' : 'Enable'; ?> this stream?');">
                                            <input type="hidden" name="id" value="<?php echo $stream['id']; ?>">
                                            <?php if ($is_active): ?>
                                                <input type="hidden" name="action" value="delete_stream">
                                                <button type="submit" class="action-btn btn-disable" data-tooltip="Disable this stream (hides from students)">
                                                    <i class="fas fa-ban text-xs"></i> Disable
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="enable_stream">
                                                <button type="submit" class="action-btn btn-enable" data-tooltip="Re-enable this stream">
                                                    <i class="fas fa-check text-xs"></i> Enable
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        <!-- Rename -->
                                        <button onclick="openEditModal('stream', <?php echo $stream['id']; ?>, '<?php echo addslashes($stream['name']); ?>')"
                                                class="action-btn btn-edit" data-tooltip="Edit stream name">
                                            <i class="fas fa-pencil-alt text-xs"></i> Edit
                                        </button>
                                        <!-- Delete Permanently -->
                                        <form method="POST" class="inline" onsubmit="return confirm('WARNING: Permanently delete this stream and all its data? This cannot be undone.');">
                                            <input type="hidden" name="action" value="permanently_delete_stream">
                                            <input type="hidden" name="id" value="<?php echo $stream['id']; ?>">
                                            <button type="submit" class="action-btn btn-delete" data-tooltip="Permanently delete stream">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Subjects Header & Add Subject Button -->
                            <div>
                                <div class="bg-gray-50 px-5 py-2.5 text-xs font-bold text-gray-600 uppercase tracking-wider border-b border-gray-100 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fas fa-book text-gray-400"></i>
                                        <span>Subjects</span>
                                        <span class="text-gray-400 font-normal ml-0.5">(<?php echo count($data['subjects']); ?>)</span>
                                    </div>
                                    <button onclick="openAddSubjectModal(<?php echo $stream['id']; ?>, '<?php echo addslashes($stream['name']); ?>')" 
                                            class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-[11px] font-bold flex items-center gap-1 shadow-xs transition cursor-pointer">
                                        <i class="fas fa-plus text-[9px]"></i> Add Subject
                                    </button>
                                </div>

                                <?php if (empty($data['subjects'])): ?>
                                    <div class="px-5 py-6 text-sm text-gray-400 italic text-center">
                                        <i class="fas fa-folder-open text-gray-300 text-2xl mb-1 block"></i>
                                        No subjects assigned.
                                    </div>
                                <?php else: ?>
                                    <ul class="divide-y divide-gray-100">
                                        <?php foreach ($data['subjects'] as $ss_id => $subj_data): ?>
                                            <?php $subj_active = $subj_data['info']['subject_status'] == 1; ?>
                                            <li>
                                                <!-- Subject Row -->
                                                <div class="subject-row px-5 py-3 cursor-pointer" onclick="toggleTeachers('teachers-<?php echo $ss_id; ?>')">
                                                    <div class="flex justify-between items-center gap-2">
                                                        <!-- Subject Name -->
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 <?php echo $subj_active ? 'bg-blue-500' : 'bg-gray-300'; ?>"></div>
                                                            <span class="font-semibold <?php echo $subj_active ? 'text-gray-800' : 'line-through text-gray-400'; ?> truncate text-sm">
                                                                <?php echo htmlspecialchars($subj_data['info']['subject_name']); ?>
                                                            </span>
                                                            <?php if ($subj_data['info']['code']): ?>
                                                                <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded font-mono flex-shrink-0">
                                                                    <?php echo htmlspecialchars($subj_data['info']['code']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Subject Action Buttons -->
                                                        <div class="flex items-center gap-1 flex-shrink-0" onclick="event.stopPropagation()">
                                                            <span class="text-xs text-gray-400 mr-1 font-medium">
                                                                <i class="fas fa-chalkboard-teacher"></i> <?php echo count($subj_data['teachers']); ?>
                                                            </span>
                                                            <!-- Edit name -->
                                                            <button onclick="openEditModal('subject', <?php echo $subj_data['info']['subject_id']; ?>, '<?php echo addslashes($subj_data['info']['subject_name']); ?>')"
                                                                    class="action-btn btn-edit" data-tooltip="Rename this subject">
                                                                <i class="fas fa-pencil-alt text-xs"></i>
                                                            </button>
                                                            <!-- Enable/Disable subject -->
                                                            <form method="POST" class="inline" onsubmit="return confirm('<?php echo $subj_active ? 'Disable' : 'Enable'; ?> this subject?');">
                                                                <input type="hidden" name="id" value="<?php echo $subj_data['info']['subject_id']; ?>">
                                                                <?php if ($subj_active): ?>
                                                                    <input type="hidden" name="action" value="delete_subject">
                                                                    <button type="submit" class="action-btn btn-disable" data-tooltip="Disable subject (hides from students)">
                                                                        <i class="fas fa-ban text-xs"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <input type="hidden" name="action" value="enable_subject">
                                                                    <button type="submit" class="action-btn btn-enable" data-tooltip="Re-enable this subject">
                                                                        <i class="fas fa-check text-xs"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </form>
                                                            <!-- Permanently delete subject -->
                                                            <form method="POST" class="inline" onsubmit="return confirm('WARNING: Permanently delete this subject and all its data?');">
                                                                <input type="hidden" name="action" value="permanently_delete_subject">
                                                                <input type="hidden" name="id" value="<?php echo $subj_data['info']['subject_id']; ?>">
                                                                <button type="submit" class="action-btn btn-delete" data-tooltip="Permanently delete subject">
                                                                    <i class="fas fa-trash-alt text-xs"></i>
                                                                </button>
                                                            </form>
                                                            <!-- Expand arrow -->
                                                            <span class="ml-1 text-gray-400 text-xs transition-transform duration-200" id="arrow-teachers-<?php echo $ss_id; ?>">
                                                                <i class="fas fa-chevron-down"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Teachers List -->
                                                <div id="teachers-<?php echo $ss_id; ?>" class="hidden bg-gray-50 border-t border-gray-100">
                                                    <div class="px-5 py-2 text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1">
                                                        <i class="fas fa-user-tie"></i> Assigned Teachers
                                                    </div>
                                                    <div class="px-4 pb-3 space-y-2">
                                                        <?php if (empty($subj_data['teachers'])): ?>
                                                            <div class="text-xs text-gray-400 italic py-2 text-center">No teachers assigned to this subject.</div>
                                                        <?php else: ?>
                                                            <?php foreach ($subj_data['teachers'] as $teacher): ?>
                                                                <div class="flex justify-between items-center bg-white rounded-lg px-3 py-2 border border-gray-200 shadow-sm">
                                                                    <div class="flex items-center gap-2">
                                                                        <?php if ($teacher['profile_picture']): ?>
                                                                            <img src="../<?php echo htmlspecialchars($teacher['profile_picture']); ?>" class="w-7 h-7 rounded-full object-cover border border-gray-200">
                                                                        <?php else: ?>
                                                                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center text-xs text-white font-bold">
                                                                                <?php echo strtoupper(substr($teacher['first_name'], 0, 1)); ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <span class="text-sm font-medium text-gray-800">
                                                                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['second_name']); ?>
                                                                        </span>
                                                                    </div>
                                                                    <form method="POST" onsubmit="return confirm('Remove this teacher from the subject?');">
                                                                        <input type="hidden" name="action" value="remove_teacher_assignment">
                                                                        <input type="hidden" name="id" value="<?php echo $teacher['assignment_id']; ?>">
                                                                        <button type="submit" class="action-btn btn-unassign" data-tooltip="Remove teacher from this subject">
                                                                            <i class="fas fa-user-minus text-xs"></i> Unassign
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- Master Subjects Datalist for Auto-Suggest -->
    <datalist id="existing_subjects_list">
        <?php foreach ($all_master_subjects as $ms): ?>
            <option value="<?php echo htmlspecialchars($ms['name']); ?>">
                <?php echo htmlspecialchars($ms['code'] ? $ms['name'] . ' (' . $ms['code'] . ')' : $ms['name']); ?>
            </option>
        <?php endforeach; ?>
    </datalist>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-bold text-gray-900" id="modalTitle">Edit Name</h3>
                <form id="editForm" method="POST" class="mt-2 px-4 py-3">
                    <input type="hidden" name="action" id="editAction">
                    <input type="hidden" name="id" id="editId">
                    <input type="text" name="name" id="editName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <div class="items-center px-0 py-3 mt-2 flex gap-2">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-md w-full shadow-sm hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md w-full shadow-sm hover:bg-blue-700">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Stream Modal -->
    <div id="addStreamModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-2">
                <h3 class="text-lg leading-6 font-bold text-gray-900 text-center mb-3">Add New Stream</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_stream">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Stream Name *</label>
                        <input type="text" name="stream_name" placeholder="e.g. A/L Commerce" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" required>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" onclick="closeAddStreamModal()" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-md w-full hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md w-full hover:bg-blue-700">
                            Save Stream
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Subject Modal (with Auto-Suggest) -->
    <div id="addSubjectModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-2">
                <h3 class="text-lg leading-6 font-bold text-gray-900 text-center mb-3" id="addSubjectModalTitle">Add Subject</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_subject">
                    <input type="hidden" name="stream_id" id="addSubjectStreamId">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Target Stream *</label>
                        <select id="addSubjectStreamSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100 font-semibold" disabled>
                            <?php foreach ($streams as $str): ?>
                                <option value="<?php echo $str['id']; ?>"><?php echo htmlspecialchars($str['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Subject Name *</label>
                        <input type="text" 
                               name="subject_name" 
                               id="addSubjectNameInput"
                               list="existing_subjects_list" 
                               placeholder="Type or select subject (e.g. ICT, Accounting)..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm font-medium" 
                               autocomplete="off" 
                               required>
                        <p class="text-[11px] text-blue-600 mt-1 font-medium">💡 Auto-suggests existing subjects (e.g. ICT) to link them without creating duplicates!</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Subject Code (Optional)</label>
                        <input type="text" name="subject_code" placeholder="e.g. ICT101" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" onclick="closeAddSubjectModal()" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-md w-full hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md w-full hover:bg-blue-700">
                            Add / Link Subject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleTeachers(id) {
            const element = document.getElementById(id);
            const arrowEl = document.getElementById('arrow-' + id);
            const isHidden = element.classList.contains('hidden');

            if (isHidden) {
                element.classList.remove('hidden');
                if (arrowEl) arrowEl.style.transform = 'rotate(180deg)';
            } else {
                element.classList.add('hidden');
                if (arrowEl) arrowEl.style.transform = 'rotate(0deg)';
            }
        }

        function openEditModal(type, id, currentName) {
            const modal = document.getElementById('editModal');
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('editAction');
            const idInput = document.getElementById('editId');
            const nameInput = document.getElementById('editName');

            modal.classList.remove('hidden');
            nameInput.value = currentName;
            idInput.value = id;

            if (type === 'stream') {
                title.textContent = 'Edit Stream Name';
                actionInput.value = 'update_stream_name';
            } else {
                title.textContent = 'Edit Subject Name';
                actionInput.value = 'update_subject_name';
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function openAddStreamModal() {
            document.getElementById('addStreamModal').classList.remove('hidden');
        }

        function closeAddStreamModal() {
            document.getElementById('addStreamModal').classList.add('hidden');
        }

        function openAddSubjectModal(streamId, streamName) {
            document.getElementById('addSubjectStreamId').value = streamId;
            document.getElementById('addSubjectStreamSelect').value = streamId;
            document.getElementById('addSubjectModalTitle').textContent = 'Add Subject to ' + streamName;
            document.getElementById('addSubjectNameInput').value = '';
            document.getElementById('addSubjectModal').classList.remove('hidden');
        }

        function closeAddSubjectModal() {
            document.getElementById('addSubjectModal').classList.add('hidden');
        }

        function filterAdminStreams() {
            const selectedStream = document.getElementById('streamFilterSelect').value;
            const query = document.getElementById('streamSearchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.stream-card');

            cards.forEach(card => {
                const streamId = card.getAttribute('data-stream-id');
                const streamName = card.getAttribute('data-stream-name');
                const subjects = card.getAttribute('data-subjects');

                const matchesStream = (selectedStream === 'all' || selectedStream === streamId);
                const matchesQuery = (query === '' || streamName.includes(query) || subjects.includes(query));

                if (matchesStream && matchesQuery) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const addStreamModal = document.getElementById('addStreamModal');
            const addSubjectModal = document.getElementById('addSubjectModal');

            if (event.target == editModal) closeEditModal();
            if (event.target == addStreamModal) closeAddStreamModal();
            if (event.target == addSubjectModal) closeAddSubjectModal();
        }
    </script>
</body>
</html>
