<?php
require_once '../check_session.php';
require_once '../config.php';

// Ensure user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle Actions (Remove/Disable Stream, Subject, Teacher Assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id = intval($_POST['id']);

        if ($action === 'delete_stream') {
            // Delete stream (soft delete or hard delete depending on preference, using status=0 for disable is safer)
            /*
            Requirments: remove or disable.
            Let's implement "Disable" -> status = 0
            */
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
            // Unassign teacher from a subject (delete from teacher_assignments or set status='inactive')
            // Using DELETE for unassigning feels more appropriate if it's "remove".
            // Or updating status to 'inactive' to keep history. Let's use status='inactive' based on previous patterns.
            // Actually user said "remove teacher like unassign", so let's delete or status inactive.
            // Let's use DELETE to actually remove the link, or status='inactive' if we want to keep record.
            // Given "remove", DELETE is cleaner for "unassigning". 
            // BUT, wait, we have `teacher_assignments` table.
            
            $stmt = $conn->prepare("DELETE FROM teacher_assignments WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Teacher unassigned successfully.";
            } else {
                $error_message = "Failed to unassign teacher.";
            }
            $stmt->close();
        } elseif ($action === 'update_subject_name') {
            $name = trim($_POST['name']);
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
            // Check for dependencies first (optional, but good practice)
            // For now, we'll try to delete and if it fails due to FK, we show error.
            // Or we can delete related stream_subjects first.
            
            // Delete related stream_subjects linking
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
            // Delete related stream_subjects linking
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
            $name = trim($_POST['name']);
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
$stream_query = "SELECT * FROM streams ORDER BY name ASC";
$result = $conn->query($stream_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $streams[] = $row;
    }
}

// Fetch Subjects (grouped by Stream if possible, OR just fetch all active subjects and we filter in UI? 
// User wants: Show Streams -> Click Stream -> Show Subjects -> Click Subject -> Show Teachers
// So we need to know which subjects belong to which stream.
// Table `stream_subjects` links streams and subjects.

// Let's fetch all data needed or fetch on demand? 
// For better UX with "Cards", let's preload meaningful structure.
// WE need: Streams -> Subjects (via stream_subjects) -> Teachers (via teacher_assignments)

$structure = [];

// 1. Get all streams
foreach ($streams as $stream) {
    $s_id = $stream['id'];
    $structure[$s_id] = [
        'info' => $stream,
        'subjects' => []
    ];
}

// 2. Get subjects for each stream
// Join stream_subjects and subjects
$subj_query = "
    SELECT ss.id as stream_subject_id, ss.stream_id, s.id as subject_id, s.name as subject_name, s.code, s.status as subject_status
    FROM stream_subjects ss
    JOIN subjects s ON ss.subject_id = s.id
    WHERE ss.status = 1 
    ORDER BY s.name ASC
";
// Note: We might want even disabled subjects if we want to manage them? 
// "admin can be able to remove,disable streams or subjects". So yes, show all?
// The prompt implies managing active content mostly, but to enable/disable we should see them.
// Let's rely on `subjects` table status. `stream_subjects` is the link.

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

// 3. Get teachers for each stream_subject
$teacher_query = "
    SELECT ta.id as assignment_id, ta.stream_subject_id, u.user_id, u.first_name, u.second_name, u.profile_picture
    FROM teacher_assignments ta
    JOIN users u ON ta.teacher_id = u.user_id
    WHERE ta.status = 'active'
";
$teacher_result = $conn->query($teacher_query);
if ($teacher_result) {
    while ($row = $teacher_result->fetch_assoc()) {
        // Iterate through structure to find the matching stream_subject_id
        // This is O(N^2) roughly but N is small.
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Content - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Manage Content</h1>
            <p class="mt-2 text-sm text-gray-600">Manage Streams, Subjects, and Teacher Assignments.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="mb-4 p-4 rounded-md bg-green-50 border border-green-200 text-green-700">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-4 p-4 rounded-md bg-red-50 border border-red-200 text-red-700">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <?php foreach ($structure as $stream_id => $data): ?>
                    <?php $stream = $data['info']; $is_active = $stream['status'] == 1; ?>
                    <div class="bg-white rounded-xl shadow border <?php echo $is_active ? 'border-red-400' : 'border-gray-300 opacity-75'; ?> overflow-hidden transition-all duration-200 hover:shadow-lg">

                        <!-- Stream Header -->
                        <div class="px-5 py-4 <?php echo $is_active ? 'bg-gradient-to-r from-red-50 to-pink-50' : 'bg-gray-100'; ?> border-b <?php echo $is_active ? 'border-red-200' : 'border-gray-200'; ?>">
                            <div class="flex justify-between items-start gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-2 h-2 rounded-full flex-shrink-0 <?php echo $is_active ? 'bg-green-500' : 'bg-gray-400'; ?>"></div>
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

                        <!-- Subjects list -->
                        <div>
                            <div class="bg-gray-50 px-5 py-2 text-xs font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100 flex items-center gap-2">
                                <i class="fas fa-book text-gray-400"></i> Subjects
                                <span class="ml-auto text-gray-400 font-normal"><?php echo count($data['subjects']); ?> total</span>
                            </div>

                            <?php if (empty($data['subjects'])): ?>
                                <div class="px-5 py-5 text-sm text-gray-400 italic text-center">
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
                                                        <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 <?php echo $subj_active ? 'bg-blue-400' : 'bg-gray-300'; ?>"></div>
                                                        <span class="font-medium <?php echo $subj_active ? 'text-gray-800' : 'line-through text-gray-400'; ?> truncate text-sm">
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
                                                        <span class="text-xs text-gray-400 mr-1">
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

                                            <!-- Teachers List (Hidden by default) -->
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
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Edit Name</h3>
                <form id="editForm" method="POST" class="mt-2 px-7 py-3">
                    <input type="hidden" name="action" id="editAction">
                    <input type="hidden" name="id" id="editId">
                    <input type="text" name="name" id="editName" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-red-500" required>
                    <div class="items-center px-4 py-3">
                        <button id="ok-btn" type="submit" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Update
                        </button>
                        <button type="button" onclick="closeEditModal()" class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                            Cancel
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
