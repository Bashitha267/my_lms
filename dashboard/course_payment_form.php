<?php
require_once '../check_session.php';
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enrollment_id = intval($_POST['enrollment_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? '';
    
    // Validate
    if ($enrollment_id <= 0 || $amount <= 0 || empty($payment_method)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Verify student owns this enrollment
        $verify_query = "SELECT id FROM course_enrollments WHERE id = ? AND student_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param("is", $enrollment_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            $error_message = 'Invalid enrollment.';
        } else {
            if ($payment_method === 'card') {
                // Card payment
                $card_number = substr($_POST['card_number'] ?? '', -4);
                $card_holder = $_POST['card_holder'] ?? '';
                $expiry_month = $_POST['expiry_month'] ?? '';
                $expiry_year = $_POST['expiry_year'] ?? '';
                $cvv = $_POST['cvv'] ?? '';
                
                if (empty($card_number) || empty($card_holder) || empty($expiry_month) || empty($expiry_year) || empty($cvv)) {
                    $error_message = 'Please fill in all card details.';
                } else {
                    $insert_query = "INSERT INTO course_payments (course_enrollment_id, amount, payment_method, payment_status, card_number, notes) 
                                    VALUES (?, ?, 'card', 'pending', ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $notes = "Card: ****" . $card_number . ", Holder: " . $card_holder;
                    $insert_stmt->bind_param("idss", $enrollment_id, $amount, $card_number, $notes);
                    
                    if ($insert_stmt->execute()) {
                        $success_message = 'Payment submitted successfully! It will be processed shortly.';
                        // Update enrollment status
                        $conn->query("UPDATE course_enrollments SET payment_status = 'pending' WHERE id = $enrollment_id AND payment_status != 'paid'");
                    } else {
                        $error_message = 'Error submitting payment: ' . $conn->error;
                    }
                }
            } elseif ($payment_method === 'bank_transfer') {
                // Bank transfer
                if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
                    $error_message = 'Please upload a payment receipt.';
                } else {
                    $file = $_FILES['receipt'];
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($file['type'], $allowed_types)) {
                        $error_message = 'Invalid file type. Please upload an image or PDF.';
                    } elseif ($file['size'] > $max_size) {
                        $error_message = 'File size too large. Maximum size is 5MB.';
                    } else {
                        $upload_dir = '../uploads/payments/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        
                        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $file_type = strpos($file['type'], 'pdf') !== false ? 'pdf' : 'image';
                        $filename = 'course_pay_' . $user_id . '_' . time() . '.' . $file_ext;
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            $receipt_path = 'uploads/payments/' . $filename;
                            
                            $insert_query = "INSERT INTO course_payments (course_enrollment_id, amount, payment_method, payment_status, receipt_path, receipt_type) 
                                            VALUES (?, ?, 'bank_transfer', 'pending', ?, ?)";
                            $insert_stmt = $conn->prepare($insert_query);
                            $insert_stmt->bind_param("idss", $enrollment_id, $amount, $receipt_path, $file_type);
                            
                            if ($insert_stmt->execute()) {
                                $success_message = 'Payment receipt uploaded successfully! It will be verified by admin shortly.';
                                // Update enrollment status
                                $conn->query("UPDATE course_enrollments SET payment_status = 'pending' WHERE id = $enrollment_id AND payment_status != 'paid'");
                            } else {
                                $error_message = 'Error submitting payment: ' . $conn->error;
                                @unlink($filepath);
                            }
                        } else {
                            $error_message = 'Error uploading file.';
                        }
                    }
                }
            } else {
                $error_message = 'Invalid payment method.';
            }
        }
    }
}

// Get Data for Display
$enrollment_id = isset($_GET['enrollment_id']) ? intval($_GET['enrollment_id']) : 0;
$enrollment = null;

if ($enrollment_id > 0) {
    // Fetch enrollment + course info
    // Fetch enrollment + course info (Join users removed to avoid collation error)
    $query = "SELECT ce.*, c.title as course_title, c.price, c.description, c.teacher_id
              FROM course_enrollments ce
              JOIN courses c ON ce.course_id = c.id
              WHERE ce.id = ? AND ce.student_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("is", $enrollment_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res->num_rows > 0) {
        $enrollment = $res->fetch_assoc();
        
        // Fetch teacher name separately
        $enrollment['teacher_first'] = 'Unknown';
        $enrollment['teacher_last'] = '';
        
        if (!empty($enrollment['teacher_id'])) {
            $t_stmt = $conn->prepare("SELECT first_name, second_name FROM users WHERE user_id = ?");
            if ($t_stmt) {
                $t_stmt->bind_param("s", $enrollment['teacher_id']);
                $t_stmt->execute();
                $t_res = $t_stmt->get_result();
                if ($t_row = $t_res->fetch_assoc()) {
                    $enrollment['teacher_first'] = $t_row['first_name'];
                    $enrollment['teacher_last'] = $t_row['second_name'];
                }
                $t_stmt->close();
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
    <title>Course Payment - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Complete Your Purchase</h1>
                <a href="online_courses.php" class="text-red-600 hover:text-red-700 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Courses
                </a>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
                    <p class="font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                    <p class="text-sm mt-2"><a href="online_courses.php" class="underline">Go to My Courses</a></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
                    <p class="font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($enrollment && empty($success_message)): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Order Summary -->
                    <div class="md:col-span-1">
                        <div class="bg-white rounded-lg shadow p-6 sticky top-6">
                            <h2 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Order Summary</h2>
                            <div class="mb-4">
                                <p class="text-sm text-gray-500">Course</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($enrollment['course_title']); ?></p>
                            </div>
                            <div class="mb-4">
                                <p class="text-sm text-gray-500">Instructor</p>
                                <p class="text-gray-800"><?php echo htmlspecialchars($enrollment['teacher_first'] . ' ' . $enrollment['teacher_last']); ?></p>
                            </div>
                            <div class="flex justify-between items-center border-t pt-4 mt-4">
                                <span class="font-bold text-gray-900">Total</span>
                                <span class="text-2xl font-bold text-red-600">Rs<?php echo number_format($enrollment['price'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Form -->
                    <div class="md:col-span-2">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6">Payment Method</h2>
                            
                            <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="enrollment_id" value="<?php echo $enrollment_id; ?>">
                                <input type="hidden" name="amount" value="<?php echo $enrollment['price']; ?>">
                                
                                <div class="space-y-4">
                                    <div class="flex items-center space-x-4 border p-4 rounded cursor-pointer hover:border-red-500 transition" onclick="document.getElementById('pm_card').click()">
                                        <input type="radio" id="pm_card" name="payment_method" value="card" class="h-4 w-4 text-red-600 focus:ring-red-500" onchange="togglePaymentFields()">
                                        <div class="flex-1">
                                            <label for="pm_card" class="font-medium text-gray-900 cursor-pointer block">Credit/Debit Card</label>
                                            <p class="text-sm text-gray-500">Pay securely with your bank card</p>
                                        </div>
                                        <i class="fas fa-credit-card text-gray-400 text-xl"></i>
                                    </div>

                                    <div class="flex items-center space-x-4 border p-4 rounded cursor-pointer hover:border-red-500 transition" onclick="document.getElementById('pm_bank').click()">
                                        <input type="radio" id="pm_bank" name="payment_method" value="bank_transfer" class="h-4 w-4 text-red-600 focus:ring-red-500" onchange="togglePaymentFields()">
                                        <div class="flex-1">
                                            <label for="pm_bank" class="font-medium text-gray-900 cursor-pointer block">Bank Transfer</label>
                                            <p class="text-sm text-gray-500">Upload your transfer receipt slip</p>
                                        </div>
                                        <i class="fas fa-university text-gray-400 text-xl"></i>
                                    </div>
                                </div>
                                
                                <!-- Card Fields -->
                                <div id="cardFields" class="hidden space-y-4 pt-4 border-t">
                                    <div class="grid grid-cols-1 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Card Number</label>
                                            <input type="text" id="card_number" name="card_number" maxlength="19" placeholder="0000 0000 0000 0000" class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Card Holder Name</label>
                                            <input type="text" id="card_holder" name="card_holder" class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500">
                                        </div>
                                        <div class="grid grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                                <input type="text" id="expiry_month" name="expiry_month" placeholder="MM" maxlength="2" class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                                <input type="text" id="expiry_year" name="expiry_year" placeholder="YY" maxlength="2" class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                                                <input type="password" id="cvv" name="cvv" maxlength="3" class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bank Fields -->
                                <div id="bankFields" class="hidden space-y-4 pt-4 border-t">
                                    <div class="bg-blue-50 p-4 rounded border border-blue-200 mb-4">
                                        <h4 class="font-bold text-blue-800 text-sm">Bank Details</h4>
                                        <p class="text-sm text-blue-700">Bank: LMS Global Bank</p>
                                        <p class="text-sm text-blue-700">Acc No: 1234 5678 9000</p>
                                        <p class="text-sm text-blue-700">Ref: <?php echo 'COURSE-' . $enrollment_id; ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload Receipt</label>
                                        <input type="file" id="receipt" name="receipt" class="w-full border rounded p-2 text-sm text-gray-600">
                                    </div>
                                </div>

                                <button type="submit" class="w-full py-3 bg-red-600 text-white rounded font-bold hover:bg-red-700 transition shadow-lg mt-6">
                                    Pay Rs<?php echo number_format($enrollment['price'], 2); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php elseif(empty($success_message)): ?>
                <div class="bg-white rounded-lg p-8 text-center text-gray-500">
                    <p>Invalid Enrollment ID.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePaymentFields() {
            const isCard = document.getElementById('pm_card').checked;
            const isBank = document.getElementById('pm_bank').checked;
            const cardFields = document.getElementById('cardFields');
            const bankFields = document.getElementById('bankFields');

            if(isCard) {
                cardFields.classList.remove('hidden');
                bankFields.classList.add('hidden');
            } else if(isBank) {
                cardFields.classList.add('hidden');
                bankFields.classList.remove('hidden');
            } else {
                cardFields.classList.add('hidden');
                bankFields.classList.add('hidden');
            }
        }
        
        // Card formatting
        document.getElementById('card_number')?.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let matches = v.match(/\d{4,16}/g);
            let match = matches && matches[0] || '';
            let parts = [];
            for (let i=0, len=match.length; i<len; i+=4) {
               parts.push(match.substring(i, i+4));
            }
            if (parts.length) {
                e.target.value = parts.join(' ');
            } else {
                e.target.value = v;
            }
        });
    </script>
</body>
</html>
