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
        // Check both submission status and if specifically requested
        if (!isset($_SESSION['al_submitted']) || !isset($_SESSION['al_requested'])) {
            require_once __DIR__ . '/config.php';
            
            $uid = $_SESSION['user_id'];
            $chk = $conn->prepare("SELECT 
                (SELECT COUNT(*) FROM al_exam_submissions WHERE student_id = u.user_id) as submitted,
                al_details_requested 
                FROM users u WHERE user_id = ?");
            $chk->bind_param("s", $uid);
            $chk->execute();
            $result = $chk->get_result()->fetch_assoc();
            $chk->close();
            
            $_SESSION['al_submitted'] = ($result['submitted'] > 0);
            $_SESSION['al_requested'] = ($result['al_details_requested'] == 1);
        }
        
        // ONLY force redirect if NOT submitted AND it HAS been requested
        if (!$_SESSION['al_submitted'] && $_SESSION['al_requested']) {
            header("Location: /lms/student/al_exam_form.php");
            exit();
        }
    }
}
?>
