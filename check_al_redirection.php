<?php
// check_al_redirection.php - Shared logic to enforce AL details submission
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    // Avoid infinite loop
    $current_script = $_SERVER['SCRIPT_NAME'];
    if (
        strpos($current_script, 'al_exam_form.php') === false &&
        strpos($current_script, 'al_results_form.php') === false &&
        strpos($current_script, 'logout.php') === false
    ) {
        // Use flags in session to avoid DB query on every page load
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
        
        // Enforcement:
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
