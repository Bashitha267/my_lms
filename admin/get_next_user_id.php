<?php
require_once '../check_session.php';
require_once '../config.php';

// Only admins can access
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$role = $_GET['role'] ?? '';

// Validate role
$valid_roles = ['student', 'teacher', 'instructor', 'admin', 'super_admin'];
if (!in_array($role, $valid_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Role prefixes
$role_prefix = [
    'student' => 'stu',
    'teacher' => 'T',
    'instructor' => 'ins',
    'admin' => 'adm',
    'super_admin' => 'sad'
];

$prefix = $role_prefix[$role];
$start_num = ($role === 'teacher') ? 1 : 1000;
$substring_offset = strlen($prefix) + 2;

// Get next number for this role
$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY CAST(SUBSTRING(user_id, {$substring_offset}) AS UNSIGNED) DESC LIMIT 1");
$pattern = $prefix . '_%';
$stmt->bind_param("s", $pattern);
$stmt->execute();
$result = $stmt->get_result();

$next_num = $start_num;
if ($result->num_rows > 0) {
    $last_user = $result->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
    $next_num = max($last_num + 1, $start_num);
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
