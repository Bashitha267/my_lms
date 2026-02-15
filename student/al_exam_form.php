<?php
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if already submitted
$check_query = "SELECT id FROM al_exam_submissions WHERE student_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Already submitted, redirect to dashboard or show success message
    // For now, let's redirect to dashboard if not forced
    // If this page is accessed directly, show a "You have already submitted" message
    $already_submitted = true;
} else {
    $already_submitted = false;
}
$stmt->close();

$success_message = '';
$error_message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_submitted) {
    $subject1 = trim($_POST['subject_1']);
    $subject2 = trim($_POST['subject_2']);
    $subject3 = trim($_POST['subject_3']);
    $district = trim($_POST['district']);
    $index_number = trim($_POST['index_number'] ?? '');
    
    // Validate
    if (empty($subject1) || empty($subject2) || empty($subject3) || empty($district)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Handle Photo Upload
        $photo_path = null;
        if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/al_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['student_photo']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = $user_id . '_al_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['student_photo']['tmp_name'], $target_path)) {
                    $photo_path = 'uploads/al_photos/' . $new_filename; // Store relative path for DB
                } else {
                    $error_message = "Failed to upload photo.";
                }
            } else {
                $error_message = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
            }
        }
        
        if (empty($error_message)) {
            // Insert into Database
            $insert_query = "INSERT INTO al_exam_submissions (student_id, subject_1, subject_2, subject_3, index_number, district, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("sssssss", $user_id, $subject1, $subject2, $subject3, $index_number, $district, $photo_path);
            
            if ($stmt->execute()) {
                $success_message = "Details submitted successfully!";
                $already_submitted = true;
                $_SESSION['al_submitted'] = true;
                // Redirect after 2 seconds
                header("refresh:2;url=../dashboard/dashboard.php");
            } else {
                $error_message = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Sri Lankan A/L Subjects List
$al_subjects = [
    "Biology", "Combined Mathematics", "Physics", "Chemistry", 
    "Agricultural Science", "Information & Communication Technology (ICT)",
    "Accounting", "Business Studies", "Economics",
    "Sinhala", "Tamil", "English", "French", "German", "Japanese", "Hindi", "Chinese",
    "History", "Political Science", "Geography", "Logic & Scientific Method",
    "Buddhist Civilization", "Christian Civilization", "Hindu Civilization", "Islamic Civilization",
    "Greek & Roman Civilization", "Art", "Dancing", "Music (Oriental)", "Music (Western)", "Music (Carnatic)",
    "Drama & Theatre", "Home Economics", "Communication & Media Studies", "Civil Technology",
    "Mechanical Technology", "Electrical, Electronic & Information Technology", "Food Technology",
    "Agro Technology", "Bio-Resource Technology", "Engineering Technology", "Science for Technology"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A/L Exam Details Form - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 150px;
            overflow-y: auto;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4;
        }
        .autocomplete-items div:hover {
            background-color: #f3f4f6;
        }
        .autocomplete-active {
            background-color: #fee2e2 !important;
            color: #b91c1c;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-10 px-4">

    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-red-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white mb-2">A/L Exam Details Collection</h1>
            <p class="text-red-100 text-sm">Please fill in your correct details to proceed.</p>
        </div>

        <div class="p-8">
            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Success!</p>
                    <p><?php echo $success_message; ?></p>
                    <p class="text-sm mt-1">Redirecting to dashboard...</p>
                </div>
            <?php elseif ($already_submitted): ?>
                 <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 text-center" role="alert">
                    <p class="font-bold mb-2">You have already submitted your details.</p>
                    <a href="../dashboard/dashboard.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Go to Dashboard
                    </a>
                </div>
            <?php else: ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <!-- Subjects Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">A/L Subjects</h3>
                    
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject 1 *</label>
                        <input type="text" name="subject_1" id="subject_1" required autocomplete="off"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                               placeholder="Start typing subject name...">
                    </div>
                    
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject 2 *</label>
                        <input type="text" name="subject_2" id="subject_2" required autocomplete="off"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                               placeholder="Start typing subject name...">
                    </div>
                    
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject 3 *</label>
                        <input type="text" name="subject_3" id="subject_3" required autocomplete="off"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                               placeholder="Start typing subject name...">
                    </div>
                </div>

                <!-- Personal Details -->
                <div class="space-y-4 pt-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Exam Details</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Index Number (Optional)</label>
                        <input type="text" name="index_number" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                               placeholder="Your A/L Index Number">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">District *</label>
                        <select name="district" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors">
                            <option value="">Select District</option>
                            <option value="Ampara">Ampara</option>
                            <option value="Anuradhapura">Anuradhapura</option>
                            <option value="Badulla">Badulla</option>
                            <option value="Batticaloa">Batticaloa</option>
                            <option value="Colombo">Colombo</option>
                            <option value="Galle">Galle</option>
                            <option value="Gampaha">Gampaha</option>
                            <option value="Hambantota">Hambantota</option>
                            <option value="Jaffna">Jaffna</option>
                            <option value="Kalutara">Kalutara</option>
                            <option value="Kandy">Kandy</option>
                            <option value="Kegalle">Kegalle</option>
                            <option value="Kilinochchi">Kilinochchi</option>
                            <option value="Kurunegala">Kurunegala</option>
                            <option value="Mannar">Mannar</option>
                            <option value="Matale">Matale</option>
                            <option value="Matara">Matara</option>
                            <option value="Monaragala">Monaragala</option>
                            <option value="Mullaitivu">Mullaitivu</option>
                            <option value="Nuwara Eliya">Nuwara Eliya</option>
                            <option value="Polonnaruwa">Polonnaruwa</option>
                            <option value="Puttalam">Puttalam</option>
                            <option value="Ratnapura">Ratnapura</option>
                            <option value="Trincomalee">Trincomalee</option>
                            <option value="Vavuniya">Vavuniya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Your Photo (Optional)</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-red-400 transition-colors cursor-pointer relative bg-gray-50" onclick="document.getElementById('photo-upload').click()">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600 justify-center">
                                    <span class="relative cursor-pointer bg-white rounded-md font-medium text-red-600 hover:text-red-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-red-500">
                                        <span>Upload a file</span>
                                        <input id="photo-upload" name="student_photo" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG up to 5MB</p>
                                <p id="file-name" class="text-xs text-gray-700 font-bold mt-2"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all font-bold tracking-wide uppercase">
                        Submit Details
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- JS for Autocomplete -->
    <script>
        const subjects = <?php echo json_encode($al_subjects); ?>;

        function autocomplete(inp, arr) {
            let currentFocus;
            inp.addEventListener("input", function(e) {
                let a, b, i, val = this.value;
                closeAllLists();
                if (!val) { return false;}
                currentFocus = -1;
                a = document.createElement("DIV");
                a.setAttribute("id", this.id + "autocomplete-list");
                a.setAttribute("class", "autocomplete-items");
                this.parentNode.appendChild(a);
                for (i = 0; i < arr.length; i++) {
                    if (arr[i].toUpperCase().indexOf(val.toUpperCase()) > -1) { // Contains match logic
                        b = document.createElement("DIV");
                        const matchIndex = arr[i].toUpperCase().indexOf(val.toUpperCase());
                        // Highlight matching part
                        b.innerHTML = arr[i].substr(0, matchIndex);
                        b.innerHTML += "<strong>" + arr[i].substr(matchIndex, val.length) + "</strong>";
                        b.innerHTML += arr[i].substr(matchIndex + val.length);
                        b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
                        b.addEventListener("click", function(e) {
                            inp.value = this.getElementsByTagName("input")[0].value;
                            closeAllLists();
                        });
                        a.appendChild(b);
                    }
                }
            });
            inp.addEventListener("keydown", function(e) {
                let x = document.getElementById(this.id + "autocomplete-list");
                if (x) x = x.getElementsByTagName("div");
                if (e.keyCode == 40) { // DOWN
                    currentFocus++;
                    addActive(x);
                } else if (e.keyCode == 38) { // UP
                    currentFocus--;
                    addActive(x);
                } else if (e.keyCode == 13) { // ENTER
                    e.preventDefault();
                    if (currentFocus > -1) {
                        if (x) x[currentFocus].click();
                    }
                }
            });
            function addActive(x) {
                if (!x) return false;
                removeActive(x);
                if (currentFocus >= x.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = (x.length - 1);
                x[currentFocus].classList.add("autocomplete-active");
            }
            function removeActive(x) {
                for (let i = 0; i < x.length; i++) {
                    x[i].classList.remove("autocomplete-active");
                }
            }
            function closeAllLists(elmnt) {
                let x = document.getElementsByClassName("autocomplete-items");
                for (let i = 0; i < x.length; i++) {
                    if (elmnt != x[i] && elmnt != inp) {
                        x[i].parentNode.removeChild(x[i]);
                    }
                }
            }
            document.addEventListener("click", function (e) {
                closeAllLists(e.target);
            });
        }

        autocomplete(document.getElementById("subject_1"), subjects);
        autocomplete(document.getElementById("subject_2"), subjects);
        autocomplete(document.getElementById("subject_3"), subjects);

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('file-name').textContent = input.files[0].name;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

</body>
</html>
