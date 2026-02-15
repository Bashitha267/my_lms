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
    if (strpos($current_script, 'al_exam_form.php') === false && strpos($current_script, 'logout.php') === false) {
        
        // Use a flag in session to avoid DB query on every page load if possible, 
        // but for robustness we check DB or set session flag after first check.
        if (!isset($_SESSION['al_submitted'])) {
            require_once __DIR__ . '/config.php';
            
            $uid = $_SESSION['user_id'];
            $chk = $conn->prepare("SELECT id FROM al_exam_submissions WHERE student_id = ?");
            $chk->bind_param("s", $uid);
            $chk->execute();
            $has_submitted = $chk->get_result()->num_rows > 0;
            $chk->close();
            
            $_SESSION['al_submitted'] = $has_submitted;
        }
        
        if (!$_SESSION['al_submitted']) {
            header("Location: /lms/student/al_exam_form.php");
            exit();
        }
    }
}
?>
