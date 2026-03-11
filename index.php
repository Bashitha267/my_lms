<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student' || $_SESSION['role'] === 'teacher') {
        header("Location: dashboard/profile.php");
        exit;
    }
}

header("Location: dashboard/dashboard.php");
exit;
?>
