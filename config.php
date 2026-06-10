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
define('DB_NAME', 'lms_new');

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

} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}
?>