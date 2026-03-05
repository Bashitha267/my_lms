<?php
require_once 'config.php';
require_once 'whatsapp_config.php';

$page_title = "Instructor Registration";
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_instructor'])) {
    
    $first_name = trim($_POST['first_name'] ?? '');
    $second_name = trim($_POST['second_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nic_number = trim($_POST['nic_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = $_POST['gender'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($second_name) || empty($email) || empty($password)) {
        $error_message = 'All required fields must be filled.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        // Check for existing user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR mobile_number = ?");
        $stmt->bind_param("ss", $email, $mobile_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = ' Email or Mobile Number already registered.';
        }
        $stmt->close();
        
        if (empty($error_message)) {
            // Generate User ID (ins_XXXX)
            $prefix = 'ins';
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
            $pattern = $prefix . '_%';
            $stmt->bind_param("s", $pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $next_num = 1000;
            if ($result->num_rows > 0) {
                $last_user = $result->fetch_assoc();
                $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
                $next_num = max($last_num + 1, 1000);
            }
            $stmt->close();
            
            $user_id = $prefix . '_' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'instructor';
            $approved = 0; // Requires Admin Approval
            
            // Insert User
            $stmt = $conn->prepare("INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, nic_no, address, gender, approved, registering_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1)");
            $stmt->bind_param("sssssssssssi", $user_id, $email, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number, $nic_number, $address, $gender, $approved);
            
            if ($stmt->execute()) {
                $success_message = "Registration successful! Your Instructor ID is $user_id. Please wait for Admin approval.";
                
                // Clear form
                $_POST = array();
                
                 // Send WhatsApp Notification (Welcome)
                 if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && !empty($whatsapp_number)) {
                    $msg = "👋 *Welcome to LMS - Instructor Portal*\n\n" .
                           "Hello $first_name,\n" .
                           "Your registration as an Instructor is successful.\n" .
                           "🆔 *ID:* $user_id\n" .
                           "⚠️ *Status:* Pending Admin Approval\n\n" .
                           "You will be notified once your account is approved.";
                    sendWhatsAppMessage($whatsapp_number, $msg);
                 }

            } else {
                $error_message = "Registration failed: " . $conn->error;
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
    <title>Instructor Registration - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)),
                        url('https://images.unsplash.com/photo-1524178232363-1fb2b075b655?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen py-10 px-4">

    <div class="bg-white/95 backdrop-blur-sm w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row">
        
        <!-- Left Banner -->
        <div class="md:w-1/3 bg-purple-900 text-white p-8 flex flex-col justify-between hidden md:flex">
            <div>
                <h1 class="text-3xl font-bold mb-2">Join as Instructor</h1>
                <p class="text-purple-200">Share your knowledge and mentor students.</p>
            </div>
            
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-purple-800 flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div>
                        <h4 class="font-bold">Mentorship</h4>
                        <p class="text-xs text-purple-200">Guide students personally</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-purple-800 flex items-center justify-center">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <h4 class="font-bold">Flexible Schedule</h4>
                        <p class="text-xs text-purple-200">Accept requests on your time</p>
                    </div>
                </div>
            </div>
            
            <div class="text-sm text-purple-300">
                &copy; <?php echo date('Y'); ?> LMS Platform
            </div>
        </div>

        <!-- Right Form -->
        <div class="md:w-2/3 p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <span class="text-purple-600"><i class="fas fa-user-plus"></i></span>
                Instructor Registration
            </h2>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?php echo $success_message; ?></p>
                </div>
                <div class="text-center mt-8">
                    <a href="login.php" class="inline-block bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">Go to Login</a>
                </div>
            <?php else: ?>

                <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo $error_message; ?></p>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-5">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="John" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="second_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="Doe" value="<?php echo htmlspecialchars($_POST['second_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="instructor@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                         <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                            <input type="tel" name="mobile_number" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="07XXXXXXXX" value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                            <input type="tel" name="whatsapp_number" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="07XXXXXXXX" value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">NIC Number</label>
                            <input type="text" name="nic_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="NIC / ID Number" value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="City, District" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="******">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input type="password" name="confirm_password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" placeholder="******">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" name="register_instructor" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg shadow-lg hover:shadow-xl transition transform hover:-translate-y-1">
                            Register Now
                        </button>
                    </div>
                    
                    <p class="text-center text-sm text-gray-600 mt-4">
                        Already have an account? <a href="login.php" class="text-purple-600 font-bold hover:underline">Login here</a>
                    </p>

                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
