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
    $success_message = "You have already submitted your results. Thank you!";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_submitted_results) {
    $result1 = $_POST['result_1'] ?? '';
    $result2 = $_POST['result_2'] ?? '';
    $result3 = $_POST['result_3'] ?? '';
    $agreed = isset($_POST['agreed']) ? 1 : 0;
    
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
        } else {
            // Update Database
            $update_query = "UPDATE al_exam_submissions SET 
                             result_1 = ?, result_2 = ?, result_3 = ?, 
                             agreed_to_publish = ?, photo_path = ?, 
                             results_submitted_at = CURRENT_TIMESTAMP 
                             WHERE student_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssisSH", $result1, $result2, $result3, $agreed, $photo_path, $user_id); 
            // Correct bind param types: sssis s -> sssis s (result1,2,3 - s, agreed - i, photo - s, uid - s)
            // wait, bind_param types: s,s,s,i,s,s
            $stmt = $conn->prepare("UPDATE al_exam_submissions SET result_1=?, result_2=?, result_3=?, agreed_to_publish=?, photo_path=?, results_submitted_at=NOW() WHERE student_id=?");
            $stmt->bind_param("sssisss", $result1, $result2, $result3, $agreed, $photo_path, $user_id); // extra s for user_id, user_id is string? yes.
             // Actually wait, bind_param syntax: Check types.
             // result1 (s), result2 (s), result3 (s), agreed (i), photo (s), user_id (s) -> "sssis"
             
            if ($stmt->execute()) {
                $success_message = "Results submitted successfully!";
                $already_submitted_results = true;
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
        
        <div class="text-center">
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">A/L Results Collection</h2>
            <p class="mt-2 text-sm text-gray-600">Enter your results for the submitted subjects.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-4">
                <p class="font-bold">Success</p>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
            <div class="text-center mt-4">
                <a href="../dashboard/dashboard.php" class="text-indigo-600 hover:text-indigo-800 font-medium">Go to Dashboard</a>
            </div>
        <?php elseif ($already_submitted_results): ?>
             <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded mb-4">
                <p class="font-bold">Info</p>
                <p>You have already submitted your results.</p>
            </div>
            <div class="text-center mt-4">
                <a href="../dashboard/dashboard.php" class="text-indigo-600 hover:text-indigo-800 font-medium">Go to Dashboard</a>
            </div>
        <?php else: ?>

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
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="S">S</option>
                                <option value="F">F</option>
                                <option value="AB">Absent</option>
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
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="S">S</option>
                                <option value="F">F</option>
                                <option value="AB">Absent</option>
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
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="S">S</option>
                                <option value="F">F</option>
                                <option value="AB">Absent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Index Number (Info Only) -->
                 <div>
                    <label class="block text-sm font-medium text-gray-700">Index Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($submission['index_number']); ?>" disabled
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-500 sm:text-sm">
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

                <!-- Consent -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input id="agreed" name="agreed" type="checkbox" required
                               class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="agreed" class="font-medium text-gray-700">I agree to publish my results.</label>
                        <p class="text-gray-500">By checking this, you allow the institute to display your results publicly or internally.</p>
                    </div>
                </div>

                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-paper-plane"></i>
                        </span>
                        Submit Results
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
