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
        
        // Use flags in session to avoid DB query on every page load if possible.
        // We need both subject submission and results submission states.
        if (!isset($_SESSION['al_subjects_submitted']) || !isset($_SESSION['al_results_submitted']) || !isset($_SESSION['al_requested'])) {
            require_once __DIR__ . '/config.php';
            
            $uid = $_SESSION['user_id'];
            $chk = $conn->prepare("SELECT 
                (SELECT COUNT(*) FROM al_exam_submissions WHERE student_id = u.user_id) as subjects_submitted,
                (SELECT results_submitted_at FROM al_exam_submissions WHERE student_id = u.user_id) as results_submitted_at,
                al_details_requested 
                FROM users u WHERE user_id = ?");
            $chk->bind_param("s", $uid);
            $chk->execute();
            $result = $chk->get_result()->fetch_assoc();
            $chk->close();
            
            $_SESSION['al_subjects_submitted'] = (intval($result['subjects_submitted'] ?? 0) > 0);
            $_SESSION['al_results_submitted'] = (!empty($result['results_submitted_at']));
            $_SESSION['al_requested'] = (intval($result['al_details_requested'] ?? 0) === 1);
        }
        
        // Two-step enforcement when requested:
        // 1) If subjects not submitted => force subjects form
        // 2) If subjects submitted but results not => force results/publish form
        if (!empty($_SESSION['al_requested'])) {
            if (empty($_SESSION['al_subjects_submitted'])) {
                header("Location: /lms/student/al_exam_form.php");
                exit();
            }
            if (empty($_SESSION['al_results_submitted'])) {
                header("Location: /lms/student/al_results_form.php");
                exit();
            }
        }
    }
}
?>
