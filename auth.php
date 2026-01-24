<?php
// auth.php
require_once 'config.php'; // Session starts in config.php

// Load WhatsApp config if file exists (optional)
if (file_exists(__DIR__ . '/whatsapp_config.php')) {
    require_once 'whatsapp_config.php';
} else {
    // Define default values if config file doesn't exist
    if (!defined('WHATSAPP_API_URL')) {
        define('WHATSAPP_API_URL', '');
    }
    if (!defined('WHATSAPP_API_EMAIL')) {
        define('WHATSAPP_API_EMAIL', '');
    }
    if (!defined('WHATSAPP_API_KEY')) {
        define('WHATSAPP_API_KEY', '');
    }
    if (!defined('WHATSAPP_ENABLED')) {
        define('WHATSAPP_ENABLED', false);
    }
}

/**
 * Format mobile number to WhatsApp format (e.g., 0771234567 -> 94771234567@c.us)
 */
function formatWhatsAppNumber($mobile)
{
    // Remove all non-numeric characters
    $mobile = preg_replace('/\D/', '', $mobile);

    // Remove leading 0 if present
    if (substr($mobile, 0, 1) === '0') {
        $mobile = substr($mobile, 1);
    }

    // Add country code 94 if not present
    if (substr($mobile, 0, 2) !== '94') {
        $mobile = '94' . $mobile;
    }

    // Add WhatsApp suffix
    return $mobile . '@c.us';
}

/**
 * Send WhatsApp message via API
 */
function sendWhatsAppMessage($mobile, $message)
{
    // Get configuration from whatsapp_config.php
    $whatsapp_api_url = WHATSAPP_API_URL;
    $email = WHATSAPP_API_EMAIL;
    $api_key = WHATSAPP_API_KEY;

    // Format mobile number for WhatsApp
    $chatId = formatWhatsAppNumber($mobile);

    // Prepare JSON data
    $data = [
        'email' => $email,
        'api_key' => $api_key,
        'chatId' => $chatId,
        'text' => $message
    ];

    // Initialize cURL
    $ch = curl_init($whatsapp_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Check for cURL errors
    if ($curl_error) {
        return ['success' => false, 'message' => 'Failed to connect to WhatsApp API: ' . $curl_error];
    }

    // Check HTTP status code
    if ($http_code !== 200) {
        return ['success' => false, 'message' => 'WhatsApp API returned error code: ' . $http_code];
    }

    // Try to decode response
    $response_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Invalid response from WhatsApp API'];
    }

    // Check response data - API returns status field
    if (isset($response_data['status']) && $response_data['status'] === 'success') {
        $message = isset($response_data['message']) ? $response_data['message'] : 'Message sent successfully';
        return ['success' => true, 'message' => $message];
    } else {
        return ['success' => false, 'message' => 'Unable to send the message'];
    }
}

if (isset($_POST['login'])) {

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    // Use absolute path for redirects
    $login_path = '/lms/login.php';

    if (empty($identifier) || empty($password)) {
        header("Location: " . $login_path . "?error=" . urlencode("Fields cannot be empty"));
        exit();
    }

    // 1. Fetch User (using user_id or mobile_number)
    // Removed 'username' from SELECT as the column no longer exists
    $stmt = $conn->prepare("SELECT user_id, password, role, approved, status, whatsapp_number, first_name, second_name FROM users WHERE user_id = ? OR mobile_number = ? LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Handle Case sensitivity of column name in your array
        $password_hash = $user['password'] ?? $user['PASSWORD'] ?? '';
        $password_hash = trim($password_hash);

        // Debug: Log password verification attempt
        // error_log("Login attempt - Identifier: $identifier");

        // 2. Verify Password
        if (!empty($password_hash) && password_verify($password, $password_hash)) {

            // Check if account is active
            if (isset($user['status']) && $user['status'] == 0) {
                header("Location: " . $login_path . "?error=" . urlencode("Your account has been deactivated. Please contact administrator."));
                exit();
            }

            // Check approval
            if ($user['approved'] == 0) {
                header("Location: " . $login_path . "?error=" . urlencode("Account not approved yet."));
                exit();
            }

            // 3. GENERATE NEW TOKEN
            $new_session_token = bin2hex(random_bytes(32));
            $user_id = $user['user_id'];

            // 4. UPDATE DATABASE (Kick out previous user)
            $update_stmt = $conn->prepare("UPDATE users SET session_token = ?, session_created_at = NOW() WHERE user_id = ?");
            if (!$update_stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $update_stmt->bind_param("ss", $new_session_token, $user_id);

            if ($update_stmt->execute()) {
                // Database updated successfully

                // 5. SET SESSION VARIABLES
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user_id;
                // Since username column is gone, we use user_id as the username in session for compatibility
                $_SESSION['username'] = $user_id; 
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'] ?? '';
                $_SESSION['second_name'] = $user['second_name'] ?? '';
                // Store the SAME token in the browser session
                $_SESSION['session_token'] = $new_session_token;

                $update_stmt->close();

                // Send welcome message via WhatsApp (non-blocking - don't fail login if this fails)
                if (WHATSAPP_ENABLED && !empty($user['whatsapp_number']) && !empty(WHATSAPP_API_URL)) {
                    try {
                        $user_name = !empty($user['first_name']) ? $user['first_name'] : $user_id;
                        $welcome_message = "Welcome back, {$user_name}! You have successfully logged into the LMS system. Your User ID: {$user_id}";

                        // Send message asynchronously (don't wait for response)
                        sendWhatsAppMessage($user['whatsapp_number'], $welcome_message);
                    } catch (Exception $e) {
                        // Silently fail - don't interrupt login process
                        error_log("WhatsApp message failed: " . $e->getMessage());
                    }
                }

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'teacher':
                        header("Location: dashboard/recordings.php");
                        break;
                    case 'student':
                        header("Location: dashboard/recordings.php");
                        break;
                    case 'instructor':
                        header("Location: /lms/instructor/dashboard.php");
                        break;
                    default:
                        header("Location: " . $login_path);
                }
                exit();

            } else {
                // Update failed
                header("Location: " . $login_path . "?error=" . urlencode("Login failed during session creation."));
                exit();
            }

        } else {
            header("Location: " . $login_path . "?error=" . urlencode("Invalid Password"));
            exit();
        }
    } else {
        header("Location: " . $login_path . "?error=" . urlencode("Invalid Username"));
        exit();
    }
}

// Logout Logic
if (isset($_GET['logout'])) {
    // Ensure session is started (should be from config.php, but double-check)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear session token from database if user is logged in
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $clear_stmt = $conn->prepare("UPDATE users SET session_token = NULL, session_created_at = NULL WHERE user_id = ?");
        if ($clear_stmt) {
            $clear_stmt->bind_param("s", $uid);
            $clear_stmt->execute();
            $clear_stmt->close();
        }
    }

    // Clear all session data
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy the session
    session_destroy();

    // Redirect to login
    header("Location: /lms/login.php?success=" . urlencode("Logged out successfully"));
    exit();
}
?>
