<?php
session_start();
require_once '../config.php';

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$already_submitted_results = false;

// Ensure optional rank columns exist (safe, one-time schema self-heal)
$rank_columns = [
    'district_rank' => "ALTER TABLE al_exam_submissions ADD COLUMN district_rank INT(11) DEFAULT NULL",
    'island_rank' => "ALTER TABLE al_exam_submissions ADD COLUMN island_rank INT(11) DEFAULT NULL",
    'exam_year' => "ALTER TABLE al_exam_submissions ADD COLUMN exam_year INT(11) DEFAULT NULL"
];
foreach ($rank_columns as $col => $ddl) {
    $check_col = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE '{$col}'");
    if ($check_col && $check_col->num_rows === 0) {
        $conn->query($ddl);
    }
}

// Fetch Existing Submission (Subjects)
$stmt = $conn->prepare("SELECT * FROM al_exam_submissions WHERE student_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$submission) {
    // If no initial submission found, redirect to initial form
    header("Location: al_exam_form.php");
    exit();
}

// Check if results already submitted
if (!empty($submission['results_submitted_at'])) {
    $already_submitted_results = true;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result1 = $_POST['result_1'] ?? '';
    $result2 = $_POST['result_2'] ?? '';
    $result3 = $_POST['result_3'] ?? '';
    $exam_index_number = trim($_POST['exam_index_number'] ?? '');
    $exam_year = isset($_POST['exam_year']) && $_POST['exam_year'] !== '' ? intval($_POST['exam_year']) : null;
    $al_stream = trim($_POST['al_stream'] ?? '');
    $agreed = isset($_POST['agreed']) ? 1 : 0;
    $district_rank = isset($_POST['district_rank']) && $_POST['district_rank'] !== '' ? intval($_POST['district_rank']) : null;
    $island_rank = isset($_POST['island_rank']) && $_POST['island_rank'] !== '' ? intval($_POST['island_rank']) : null;
    
    // Additional Profile Photo Upload (Optional)
    $photo_path = $submission['photo_path']; // Keep existing by default
    
    if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/al_photos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['student_photo']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = $user_id . '_results_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['student_photo']['tmp_name'], $destination)) {
                $photo_path = 'uploads/al_photos/' . $new_filename;
            } else {
                $error_message = "Failed to upload photo.";
            }
        } else {
            $error_message = "Invalid file type. Only JPG, PNG, WEBP allowed.";
        }
    }

    if (empty($error_message)) {
        if (empty($result1) || empty($result2) || empty($result3)) {
            $error_message = "Please select results for all subjects.";
        } elseif (empty($exam_index_number)) {
            $error_message = "Exam Index Number is required.";
        } elseif (empty($exam_year)) {
            $error_message = "Exam Year is required.";
        } elseif (empty($al_stream)) {
            $error_message = "Please select your A/L stream.";
        } else {
            // Update Database
            // district_rank / island_rank columns may not exist in older schemas; detect once per request
            $has_district_rank = false;
            $has_island_rank = false;
            $has_exam_year = false;
            $c1 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'district_rank'");
            if ($c1 && $c1->num_rows > 0) $has_district_rank = true;
            $c2 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'island_rank'");
            if ($c2 && $c2->num_rows > 0) $has_island_rank = true;
            $c3 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'exam_year'");
            if ($c3 && $c3->num_rows > 0) $has_exam_year = true;

            if ($has_district_rank && $has_island_rank && $has_exam_year) {
                $stmt = $conn->prepare("UPDATE al_exam_submissions SET result_1=?, result_2=?, result_3=?, index_number=?, stream=?, exam_year=?, agreed_to_publish=?, photo_path=?, district_rank=?, island_rank=?, results_submitted_at=NOW() WHERE student_id=?");
                $stmt->bind_param("sssssisiiis", $result1, $result2, $result3, $exam_index_number, $al_stream, $exam_year, $agreed, $photo_path, $district_rank, $island_rank, $user_id);
            } elseif ($has_district_rank && $has_island_rank) {
                $stmt = $conn->prepare("UPDATE al_exam_submissions SET result_1=?, result_2=?, result_3=?, index_number=?, stream=?, agreed_to_publish=?, photo_path=?, district_rank=?, island_rank=?, results_submitted_at=NOW() WHERE student_id=?");
                $stmt->bind_param("sssssisiis", $result1, $result2, $result3, $exam_index_number, $al_stream, $agreed, $photo_path, $district_rank, $island_rank, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE al_exam_submissions SET result_1=?, result_2=?, result_3=?, index_number=?, stream=?, agreed_to_publish=?, photo_path=?, results_submitted_at=NOW() WHERE student_id=?");
                $stmt->bind_param("sssssiss", $result1, $result2, $result3, $exam_index_number, $al_stream, $agreed, $photo_path, $user_id);
            }
             
            if ($stmt->execute()) {
                $success_message = $already_submitted_results ? "Results updated successfully!" : "Results submitted successfully!";
                $already_submitted_results = true;

                // Clear requested flag now (request is fully satisfied)
                $clear_request = $conn->prepare("UPDATE users SET al_details_requested = 0 WHERE user_id = ?");
                $clear_request->bind_param("s", $user_id);
                $clear_request->execute();
                $clear_request->close();

                $_SESSION['al_results_submitted'] = true;
                $_SESSION['al_requested'] = false;

                // Keep in-memory submission updated for prefilled fields before refresh
                $submission['result_1'] = $result1;
                $submission['result_2'] = $result2;
                $submission['result_3'] = $result3;
                $submission['index_number'] = $exam_index_number;
                $submission['stream'] = $al_stream;
                $submission['exam_year'] = $exam_year;
                $submission['agreed_to_publish'] = $agreed;
                $submission['district_rank'] = $district_rank;
                $submission['island_rank'] = $island_rank;
                $submission['photo_path'] = $photo_path;

                // Refresh to show success state
                header("refresh:2");
            } else {
                $error_message = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit A/L Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-gray-800">

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg border border-gray-100">

        <div class="flex justify-between items-center">
            <a href="../dashboard/dashboard.php" class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i> Skip for now
            </a>
        </div>
        
        <div class="text-center">
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">A/L Results Collection</h2>
            <p class="mt-2 text-sm text-gray-600">Enter your results for the submitted subjects.</p>
        </div>

        <datalist id="al-stream-list">
            <option value="Physical Science"></option>
            <option value="Biological Science"></option>
            <option value="Commerce"></option>
            <option value="Arts"></option>
            <option value="Technology"></option>
            <option value="Engineering Technology"></option>
            <option value="Bio Systems Technology"></option>
        </datalist>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-4">
                <p class="font-bold">Success</p>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($already_submitted_results): ?>
             <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded mb-4">
                <p class="font-bold">Info</p>
                <p>You have already submitted your results. You can edit and update them below.</p>
                <div class="mt-3 text-sm text-blue-800 space-y-1">
                    <div><span class="font-semibold">Subject 1:</span> <?php echo htmlspecialchars($submission['subject_1']); ?> — <span class="font-bold"><?php echo htmlspecialchars($submission['result_1']); ?></span></div>
                    <div><span class="font-semibold">Subject 2:</span> <?php echo htmlspecialchars($submission['subject_2']); ?> — <span class="font-bold"><?php echo htmlspecialchars($submission['result_2']); ?></span></div>
                    <div><span class="font-semibold">Subject 3:</span> <?php echo htmlspecialchars($submission['subject_3']); ?> — <span class="font-bold"><?php echo htmlspecialchars($submission['result_3']); ?></span></div>
                    <div><span class="font-semibold">Publish:</span> <?php echo !empty($submission['agreed_to_publish']) ? 'Yes' : 'No'; ?></div>
                </div>
            </div>

        <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-4">
                    <p class="font-bold">Error</p>
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form class="mt-8 space-y-6" action="" method="POST" enctype="multipart/form-data">
                
                <!-- Display Subjects (Read-only) & Result Inputs -->
                <div class="space-y-4">
                    
                    <!-- Subject 1 -->
                    <div class="grid grid-cols-2 gap-4 items-center">
                        <div class="col-span-1">
                            <label class="block text-sm font-medium text-gray-700">Subject 1</label>
                            <input type="text" value="<?php echo htmlspecialchars($submission['subject_1']); ?>" disabled
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-600 font-medium text-sm">
                        </div>
                        <div class="col-span-1">
                            <label for="result_1" class="block text-sm font-medium text-gray-700 text-right pr-1">Result</label>
                            <select id="result_1" name="result_1" required
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Select Result</option>
                                <option value="A" <?php echo (($submission['result_1'] ?? '') === 'A') ? 'selected' : ''; ?>>A</option>
                                <option value="B" <?php echo (($submission['result_1'] ?? '') === 'B') ? 'selected' : ''; ?>>B</option>
                                <option value="C" <?php echo (($submission['result_1'] ?? '') === 'C') ? 'selected' : ''; ?>>C</option>
                                <option value="S" <?php echo (($submission['result_1'] ?? '') === 'S') ? 'selected' : ''; ?>>S</option>
                                <option value="F" <?php echo (($submission['result_1'] ?? '') === 'F') ? 'selected' : ''; ?>>F</option>
                                <option value="AB" <?php echo (($submission['result_1'] ?? '') === 'AB') ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </div>
                    </div>

                    <!-- Subject 2 -->
                    <div class="grid grid-cols-2 gap-4 items-center">
                        <div class="col-span-1">
                            <label class="block text-sm font-medium text-gray-700">Subject 2</label>
                            <input type="text" value="<?php echo htmlspecialchars($submission['subject_2']); ?>" disabled
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-600 font-medium text-sm">
                        </div>
                        <div class="col-span-1">
                            <label for="result_2" class="block text-sm font-medium text-gray-700 text-right pr-1">Result</label>
                            <select id="result_2" name="result_2" required
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Select Result</option>
                                <option value="A" <?php echo (($submission['result_2'] ?? '') === 'A') ? 'selected' : ''; ?>>A</option>
                                <option value="B" <?php echo (($submission['result_2'] ?? '') === 'B') ? 'selected' : ''; ?>>B</option>
                                <option value="C" <?php echo (($submission['result_2'] ?? '') === 'C') ? 'selected' : ''; ?>>C</option>
                                <option value="S" <?php echo (($submission['result_2'] ?? '') === 'S') ? 'selected' : ''; ?>>S</option>
                                <option value="F" <?php echo (($submission['result_2'] ?? '') === 'F') ? 'selected' : ''; ?>>F</option>
                                <option value="AB" <?php echo (($submission['result_2'] ?? '') === 'AB') ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </div>
                    </div>

                    <!-- Subject 3 -->
                    <div class="grid grid-cols-2 gap-4 items-center">
                        <div class="col-span-1">
                            <label class="block text-sm font-medium text-gray-700">Subject 3</label>
                            <input type="text" value="<?php echo htmlspecialchars($submission['subject_3']); ?>" disabled
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-600 font-medium text-sm">
                        </div>
                        <div class="col-span-1">
                            <label for="result_3" class="block text-sm font-medium text-gray-700 text-right pr-1">Result</label>
                            <select id="result_3" name="result_3" required
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Select Result</option>
                                <option value="A" <?php echo (($submission['result_3'] ?? '') === 'A') ? 'selected' : ''; ?>>A</option>
                                <option value="B" <?php echo (($submission['result_3'] ?? '') === 'B') ? 'selected' : ''; ?>>B</option>
                                <option value="C" <?php echo (($submission['result_3'] ?? '') === 'C') ? 'selected' : ''; ?>>C</option>
                                <option value="S" <?php echo (($submission['result_3'] ?? '') === 'S') ? 'selected' : ''; ?>>S</option>
                                <option value="F" <?php echo (($submission['result_3'] ?? '') === 'F') ? 'selected' : ''; ?>>F</option>
                                <option value="AB" <?php echo (($submission['result_3'] ?? '') === 'AB') ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Exam Meta -->
                 <div>
                    <label class="block text-sm font-medium text-gray-700">Exam Index Number *</label>
                    <input type="text" name="exam_index_number" value="<?php echo htmlspecialchars($submission['index_number'] ?? ''); ?>" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm"
                           placeholder="Enter your official A/L exam index number">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Exam Year *</label>
                        <input type="number" name="exam_year" min="2000" max="2100" required
                               value="<?php echo htmlspecialchars($submission['exam_year'] ?? ''); ?>"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm"
                               placeholder="e.g., 2025">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">A/L Stream *</label>
                        <input type="text" name="al_stream" list="al-stream-list" required
                               value="<?php echo htmlspecialchars($submission['stream'] ?? ''); ?>"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm"
                               placeholder="Type stream name">
                    </div>
                </div>

                <!-- Photo Upload (Optional Update) -->
                <div>
                     <label class="block text-sm font-medium text-gray-700">Update Photo (Optional)</label>
                    <div class="mt-1 flex items-center">
                        <?php if (!empty($submission['photo_path'])): ?>
                            <img class="inline-block h-12 w-12 rounded-full ring-2 ring-white mr-4 object-cover" src="../<?php echo htmlspecialchars($submission['photo_path']); ?>" alt="Current Photo">
                        <?php else: ?>
                            <span class="inline-block h-12 w-12 rounded-full overflow-hidden bg-gray-100 mr-4">
                                <svg class="h-full w-full text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </span>
                        <?php endif; ?>
                        <input type="file" name="student_photo" accept="image/*"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Currently stored photo shown on left.</p>
                </div>

                <!-- Ranks (Optional) -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">District Rank (Optional)</label>
                        <input type="number" name="district_rank" min="1" inputmode="numeric"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm"
                               value="<?php echo htmlspecialchars($submission['district_rank'] ?? ''); ?>"
                               placeholder="e.g., 25">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Island Rank (Optional)</label>
                        <input type="number" name="island_rank" min="1" inputmode="numeric"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm"
                               value="<?php echo htmlspecialchars($submission['island_rank'] ?? ''); ?>"
                               placeholder="e.g., 320">
                    </div>
                </div>

                <!-- Consent -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input id="agreed" name="agreed" type="checkbox"
                               <?php echo !empty($submission['agreed_to_publish']) ? 'checked' : ''; ?>
                               class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="agreed" class="font-medium text-gray-700">Publish my results publicly (Optional)</label>
                        <p class="text-gray-500">Tick this only if you agree to display your A/L results publicly on the website.</p>
                    </div>
                </div>

                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-paper-plane"></i>
                        </span>
                        <?php echo $already_submitted_results ? 'Update Results' : 'Submit Results'; ?>
                    </button>
                </div>
            </form>
    </div>
</div>

</body>
</html>
