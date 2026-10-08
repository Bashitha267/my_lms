<?php
require_once '../check_session.php';
require_once '../config.php';

if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    exit('Access denied.');
}

$first_name = 'Super';
$second_name = 'Admin';
$email = 'superadmin0704607707@local.test';
$mobile_number = '0704607707';
$whatsapp_number = '0704607707';
$password = 'super123';
$role = 'super_admin';

$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE mobile_number = ? OR email = ? LIMIT 1");
$check_stmt->bind_param('ss', $mobile_number, $email);
$check_stmt->execute();
$existing = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($existing) {
    exit('User already exists: ' . htmlspecialchars($existing['user_id'], ENT_QUOTES, 'UTF-8'));
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
$pattern = 'sad_%';
$stmt->bind_param('s', $pattern);
$stmt->execute();
$result = $stmt->get_result();

$next_num = 1000;
if ($result->num_rows > 0) {
    $last_user = $result->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], 4));
    $next_num = max($last_num + 1, 1000);
}
$stmt->close();

$user_id = 'sad_' . str_pad((string)$next_num, 4, '0', STR_PAD_LEFT);
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$insert_stmt = $conn->prepare("INSERT INTO users (user_id, email, password, role, first_name, second_name, mobile_number, whatsapp_number, profile_picture, approved, registering_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, CURDATE(), 1)");
$insert_stmt->bind_param('ssssssss', $user_id, $email, $password_hash, $role, $first_name, $second_name, $mobile_number, $whatsapp_number);

if ($insert_stmt->execute()) {
    echo 'Super admin created successfully. User ID: ' . htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8');
} else {
    http_response_code(500);
    echo 'Failed to create super admin: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
}

$insert_stmt->close();
?>