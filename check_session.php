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

// Function to check if AL details submitted
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    // Avoid infinite loop if already on the form page or handling the submission
    $current_script = $_SERVER['SCRIPT_NAME'];
    if (
        strpos($current_script, 'al_exam_form.php') === false &&
        strpos($current_script, 'al_results_form.php') === false &&
        strpos($current_script, 'logout.php') === false
    ) {
        require_once __DIR__ . '/check_al_redirection.php';
    }
}
?>
