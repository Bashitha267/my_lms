<?php
require_once '../check_session.php';
require_once '../config.php';

// Only admins can access
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$role = $_GET['role'] ?? '';

// Validate role
$valid_roles = ['student', 'teacher', 'instructor', 'admin'];
if (!in_array($role, $valid_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Role prefixes
$role_prefix = [
    'student' => 'stu',
    'teacher' => 'tea',
    'instructor' => 'ins',
    'admin' => 'adm'
];

$prefix = $role_prefix[$role];

// Get next number for this role
$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
$pattern = $prefix . '_%';
$stmt->bind_param("s", $pattern);
$stmt->execute();
$result = $stmt->get_result();

$next_num = 1000; // Start from 1000
if ($result->num_rows > 0) {
    $last_user = $result->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
    $next_num = max($last_num + 1, 1000);
}
$stmt->close();

$user_id = $prefix . '_' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

echo json_encode([
    'success' => true,
    'user_id' => $user_id,
    'role' => $role,
    'prefix' => $prefix
]);
?>
