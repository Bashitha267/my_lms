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
 * Performance helper: flush the HTTP response to the browser immediately,
 * then continue executing PHP in the background (works on Nginx + PHP-FPM).
 * This means the user is redirected instantly while WhatsApp API runs after.
 */
function flush_response_to_browser() {
    // Close the session so it's written before we flush
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    // Ignore user abort so background work continues after browser disconnects
    ignore_user_abort(true);
    // Set content length so the browser knows it got the full response
    if (function_exists('header_remove')) {
        header_remove('Content-Encoding');
    }
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    // On Nginx + PHP-FPM this is the key call — closes the FastCGI connection
    // to the browser while PHP keeps running
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

/**
 * WhatsApp functions moved to whatsapp_config.php
 */


if (isset($_POST['login'])) {

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    // Use absolute path for redirects
    $login_path = BASE_PATH . 'login.php';

    if (empty($identifier) || empty($password)) {
        header("Location: " . $login_path . "?error=" . urlencode("Fields cannot be empty"));
        exit();
    }

    // 1. Fetch User (using user_id or mobile_number)
    // Included mobile_number in SELECT for WhatsApp fallback
    $stmt = $conn->prepare("SELECT user_id, password, role, approved, status, mobile_number, whatsapp_number, first_name, second_name FROM users WHERE user_id = ? OR mobile_number = ? LIMIT 1");
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
                // Note: session_regenerate_id is called BEFORE we flush so the
                // new session ID is set in the cookie header sent to the browser.
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

                // ── REDIRECT FIRST ──────────────────────────────────────────
                // Determine redirect URL before we flush so the header is sent.
                $redirect_role = $user['role'];

                // Fallback for super_admin if role is empty but ID starts with sad_
                if (empty($redirect_role) && strpos($user_id, 'sad_') === 0) {
                    $redirect_role = 'super_admin';
                    $_SESSION['role'] = 'super_admin';
                }

                switch ($redirect_role) {
                    case 'admin':
                        $redirect_url = 'admin/dashboard.php';
                        break;
                    case 'super_admin':
                        $redirect_url = 'admin/teacher_payments.php';
                        break;
                    case 'teacher':
                        $redirect_url = 'dashboard/profile.php';
                        break;
                    case 'student':
                        $redirect_url = 'dashboard/profile.php';
                        break;
                    case 'instructor':
                        $redirect_url = BASE_PATH . 'instructor/dashboard.php';
                        break;
                    default:
                        $redirect_url = $login_path;
                }

                // Send the redirect header to the browser
                header("Location: " . $redirect_url);

                // ── FLUSH RESPONSE TO BROWSER ────────────────────────────────
                // This closes the FastCGI connection on Nginx+PHP-FPM so the
                // browser gets the redirect instantly. PHP keeps running below.
                flush_response_to_browser();

                // ── BACKGROUND: SEND WHATSAPP NOTIFICATION ──────────────────
                // This now runs AFTER the browser has already been redirected.
                // Even if it takes 10-30s, the user never notices.
                $whatsapp_target = !empty($user['whatsapp_number']) ? $user['whatsapp_number'] : ($user['mobile_number'] ?? '');

                if (WHATSAPP_ENABLED && !empty($whatsapp_target)) {
                    try {
                        $current_time = date('Y-m-d h:i A');
                        $u_name = !empty($user['first_name']) ? $user['first_name'] : $user_id;
                        $login_message = "👋 *Welcome back, {$u_name}! / සාදරයෙන් පිළිගනිමු!*\n\n" .
                                         "You have successfully logged into your Learner.LK account.\n" .
                                         "ඔබ සාර්ථකව Learner.LK ගිණුමට ප්‍රවිෂ්ට විය.\n\n" .
                                         "⏰ *Time:* {$current_time}";

                        sendWhatsAppMessage($whatsapp_target, $login_message);
                    } catch (Exception $e) {
                        error_log("WhatsApp login message failed: " . $e->getMessage());
                    }
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
    header("Location: " . BASE_PATH . "login.php?success=" . urlencode("Logged out successfully"));
    exit();
}
?>
