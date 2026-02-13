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
 * WhatsApp functions moved to whatsapp_config.php
 */


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

                // Send login notification via WhatsApp (non-blocking)
                if (WHATSAPP_ENABLED && !empty($user['whatsapp_number'])) {
                    try {
                        $current_time = date('Y-m-d h:i A');
                        $login_message = "🔔 *New Login Notification / නව පිවිසීම් දැනුම්දීම*\n\n" .
                                        "👤 *User ID / පරිශීලක හැඳුනුම්පත:* {$user_id}\n" .
                                        "⏰ *Time / වේලාව:* {$current_time}\n\n" .
                                        "Successful login to your LMS account.";

                        sendWhatsAppMessage($user['whatsapp_number'], $login_message);
                    } catch (Exception $e) {
                        error_log("WhatsApp login message failed: " . $e->getMessage());
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
