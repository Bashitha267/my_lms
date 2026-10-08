<?php
// Database configuration for LMS system
date_default_timezone_set('Asia/Colombo');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base path for the application relative to domain root
// This handles both local subdirectory (e.g. /lms/) and production root (e.g. /)
if (!defined('BASE_PATH')) {
    $doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $dir = rtrim(str_replace('\\', '/', __DIR__), '/');
    $base_path = '/';
    if (!empty($doc_root) && stripos($dir, $doc_root) === 0) {
        $base_path = substr($dir, strlen($doc_root));
    }
    $base_path = '/' . ltrim($base_path, '/');
    if ($base_path !== '/') {
        $base_path = rtrim($base_path, '/') . '/';
    }
    define('BASE_PATH', $base_path);
}

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lms');

// Create database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8mb4 for proper character encoding
    $conn->set_charset("utf8mb4");

    // Set MySQL timezone to Sri Lanka (+05:30)
    $conn->query("SET time_zone = '+05:30'");

    // ── Single Active Session Check ──────────────────────────────────────
    // If user is logged in, verify their session_token matches the database.
    // If another device logged in with the same account, session_token in DB
    // changes, so this session is invalidated and redirected to login.
    if (!empty($_SESSION['user_id'])) {
        $sess_uid = $_SESSION['user_id'];
        $current_sess_token = $_SESSION['session_token'] ?? '';
        
        $chk_sess_stmt = $conn->prepare("SELECT session_token FROM users WHERE user_id = ? LIMIT 1");
        if ($chk_sess_stmt) {
            $chk_sess_stmt->bind_param("s", $sess_uid);
            $chk_sess_stmt->execute();
            $chk_sess_res = $chk_sess_stmt->get_result();
            if ($chk_row = $chk_sess_res->fetch_assoc()) {
                $db_sess_token = $chk_row['session_token'] ?? null;
                // If database has a token and it does not match current session's token (or session lacks token)
                if (!empty($db_sess_token) && (empty($current_sess_token) || !hash_equals($db_sess_token, $current_sess_token))) {
                    $chk_sess_stmt->close();
                    
                    // Clear session data
                    $_SESSION = array();
                    if (isset($_COOKIE[session_name()])) {
                        setcookie(session_name(), '', time() - 3600, '/');
                    }
                    session_destroy();
                    
                    // Redirect to login with error notice
                    $expired_msg = urlencode("You have been logged out because this account was logged in from another device/browser. / වෙනත් device එකක් හරහා ඔබගේ ගිණුමට ඇතුළු වූ බැවින් ඔබව Logout වී ඇත. කරුණාකර නැවත Log වන්න.");
                    header("Location: " . BASE_PATH . "?error=" . $expired_msg);
                    exit();
                }
            }
            $chk_sess_stmt->close();
        }
    }

} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}
?>