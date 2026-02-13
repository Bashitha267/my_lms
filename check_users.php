<?php
require_once 'config.php';
$res = $conn->query("SELECT user_id, first_name, role, whatsapp_number, mobile_number FROM users LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo "User: {$row['user_id']} ({$row['role']}) | WA: {$row['whatsapp_number']} | Mob: {$row['mobile_number']}\n";
}
?>
