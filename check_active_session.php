<?php
// check_active_session.php - Lightweight heartbeat endpoint for live session checking
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['logged_in' => false, 'reason' => 'not_logged_in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$current_token = $_SESSION['session_token'] ?? '';

$stmt = $conn->prepare("SELECT session_token FROM users WHERE user_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $db_token = $row['session_token'] ?? null;
        if (!empty($db_token) && !empty($current_token) && hash_equals($db_token, $current_token)) {
            $stmt->close();
            echo json_encode(['logged_in' => true]);
            exit();
        }
    }
    $stmt->close();
}

// Session invalidated - clear current session
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();

$expired_msg = "You have been logged out because this account was logged in from another device/browser. / වෙනත් device එකක් හරහා ඔබගේ ගිණුමට ඇතුළු වූ බැවින් ඔබව Logout වී ඇත. කරුණාකර නැවත Log වන්න.";
echo json_encode([
    'logged_in' => false, 
    'reason' => 'token_mismatch',
    'redirect_url' => BASE_PATH . '?error=' . urlencode($expired_msg)
]);
exit();
