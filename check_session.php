<?php
// check_session.php
// Include this file in protected pages to verify session token
// This ensures single login functionality - if user logs in elsewhere, this session becomes invalid

// Use absolute path to config.php to ensure it's found regardless of where this file is included from
require_once __DIR__ . '/config.php';

// Use absolute path from document root to ensure correct redirect regardless of subdirectory
// This works whether check_session.php is called from lms/, lms/dashboard/, lms/admin/, etc.
$login_path = '/lms/login.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    header("Location: " . $login_path . "?error=" . urlencode("Please login to access this page."));
    exit();
}

$user_id = $_SESSION['user_id'];
$session_token = $_SESSION['session_token'];

// Verify session token matches database
$stmt = $conn->prepare("SELECT user_id, role, session_token, first_name, second_name FROM users WHERE user_id = ? AND session_token = ? LIMIT 1");
$stmt->bind_param("ss", $user_id, $session_token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Session token doesn't match - user logged in elsewhere
    session_destroy();
    header("Location: " . $login_path . "?error=" . urlencode("Your session has expired or you logged in from another device. Please login again."));
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Update session with current user data
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['username'] = $user['user_id']; // Use user_id as username for backward compatibility
$_SESSION['role'] = $user['role'];
$_SESSION['session_token'] = $user['session_token'];
$_SESSION['first_name'] = $user['first_name'] ?? '';
$_SESSION['second_name'] = $user['second_name'] ?? '';
?>
