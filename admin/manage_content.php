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
    <style>
        body { font-family: 'Inter', sans-serif; }
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
            
            <!-- Streams Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Add New Stream Card (Optional, or just list existing) -->
                
                <?php foreach ($structure as $stream_id => $data): ?>
                    <?php $stream = $data['info']; ?>
                    <div class="bg-white rounded-lg shadow-sm border border-red-500 overflow-hidden hover:shadow-md transition-shadow duration-200">
                        <div class="p-5 border-b border-red-100 flex justify-between items-center bg-red-50">
                            <h3 class="text-lg font-semibold text-gray-800">
                                <?php echo htmlspecialchars($stream['name']); ?>
                            </h3>
                            <div class="flex space-x-2">
                                <!-- Enable/Disable Stream -->
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to change this stream status?');">
                                    <input type="hidden" name="id" value="<?php echo $stream['id']; ?>">
                                    <?php if ($stream['status'] == 1): ?>
                                        <input type="hidden" name="action" value="delete_stream">
                                        <button type="submit" class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200">Disable</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="enable_stream">
                                        <button type="submit" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200">Enable</button>
                                    <?php endif; ?>
                                </form>
                                <button onclick="openEditModal('stream', <?php echo $stream['id']; ?>, '<?php echo addslashes($stream['name']); ?>')" class="text-xs text-blue-600 hover:text-blue-800">Edit</button>
                                <form method="POST" class="inline" onsubmit="return confirm('WARNING: This will permanently delete the stream and all associated data. This action cannot be undone. Are you sure?');">
                                    <input type="hidden" name="action" value="permanently_delete_stream">
                                    <input type="hidden" name="id" value="<?php echo $stream['id']; ?>">
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-900 ml-1" title="Delete Permanently">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Subjects List inside Stream Card -->
                        <div class="p-0">
                            <div class="bg-gray-50 px-5 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                                Subjects
                            </div>
                            <?php if (empty($data['subjects'])): ?>
                                <div class="px-5 py-4 text-sm text-gray-500 italic">No subjects assigned.</div>
                            <?php else: ?>
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach ($data['subjects'] as $ss_id => $subj_data): ?>
                                        <li class="group">
                                            <!-- Subject Row -->
                                            <div class="px-5 py-3 hover:bg-gray-50 transition-colors cursor-pointer" onclick="toggleTeachers('<?php echo 'teachers-' . $ss_id; ?>')">
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <span class="font-medium text-gray-700 <?php echo $subj_data['info']['subject_status'] == 0 ? 'line-through text-gray-400' : ''; ?>">
                                                            <?php echo htmlspecialchars($subj_data['info']['subject_name']); ?>
                                                        </span>
                                                        <?php if ($subj_data['info']['code']): ?>
                                                            <span class="ml-2 text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                                                <?php echo htmlspecialchars($subj_data['info']['code']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex items-center space-x-2">
                                                        <span class="text-xs text-gray-400 mr-2">
                                                            <?php echo count($subj_data['teachers']); ?> Teachers
                                                        </span>
                                                        <!-- Subject Actions -->
                                                         <button onclick="event.stopPropagation(); openEditModal('subject', <?php echo $subj_data['info']['subject_id']; ?>, '<?php echo addslashes($subj_data['info']['subject_name']); ?>')" class="text-gray-400 hover:text-blue-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        </button>
                                                        <form method="POST" class="inline" onsubmit="event.stopPropagation(); return confirm('Toggle subject status?');">
                                                            <input type="hidden" name="id" value="<?php echo $subj_data['info']['subject_id']; ?>">
                                                            <?php if ($subj_data['info']['subject_status'] == 1): ?>
                                                                <input type="hidden" name="action" value="delete_subject">
                                                                <button type="submit" class="text-gray-400 hover:text-red-600">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                                                </button>
                                                            <?php else: ?>
                                                                <input type="hidden" name="action" value="enable_subject">
                                                                <button type="submit" class="text-gray-400 hover:text-green-600">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>
                                                        <form method="POST" class="inline" onsubmit="event.stopPropagation(); return confirm('WARNING: This will permanently delete the subject and all associated data. This action cannot be undone. Are you sure?');">
                                                            <input type="hidden" name="action" value="permanently_delete_subject">
                                                            <input type="hidden" name="id" value="<?php echo $subj_data['info']['subject_id']; ?>">
                                                            <button type="submit" class="text-gray-400 hover:text-red-800 ml-1" title="Delete Permanently">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                        </form>
                                                        <svg class="w-4 h-4 text-gray-400 transform transition-transform duration-200" id="arrow-<?php echo 'teachers-' . $ss_id; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Teachers List (Hidden by default) -->
                                            <div id="<?php echo 'teachers-' . $ss_id; ?>" class="hidden bg-gray-50 border-t border-gray-100 px-5 py-3 space-y-2">
                                                <?php if (empty($subj_data['teachers'])): ?>
                                                    <div class="text-xs text-gray-500">No teachers assigned.</div>
                                                <?php else: ?>
                                                    <?php foreach ($subj_data['teachers'] as $teacher): ?>
                                                        <div class="flex justify-between items-center text-sm bg-white p-2 rounded border border-gray-200">
                                                            <div class="flex items-center space-x-2">
                                                                <?php if ($teacher['profile_picture']): ?>
                                                                    <img src="../<?php echo htmlspecialchars($teacher['profile_picture']); ?>" class="w-6 h-6 rounded-full object-cover">
                                                                <?php else: ?>
                                                                    <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs text-gray-500">
                                                                        <?php echo strtoupper(substr($teacher['first_name'], 0, 1)); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <span class="text-gray-700"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['second_name']); ?></span>
                                                            </div>
                                                            <form method="POST" onsubmit="return confirm('Unassign this teacher?');">
                                                                <input type="hidden" name="action" value="remove_teacher_assignment">
                                                                <input type="hidden" name="id" value="<?php echo $teacher['assignment_id']; ?>">
                                                                <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline">Unassign</button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
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
            const arrow = document.getElementById('arrow-' + id);
            
            if (element.classList.contains('hidden')) {
                element.classList.remove('hidden');
                if(arrow) arrow.style.transform = 'rotate(180deg)';
            } else {
                element.classList.add('hidden');
                if(arrow) arrow.style.transform = 'rotate(0deg)';
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
