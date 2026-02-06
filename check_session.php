<?php
// check_session.php - Common session check for dashboard and admin pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Determine path back to login
    $path = '/lms/login.php';
    header("Location: $path");
    exit();
}
?>
