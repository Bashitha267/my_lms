<?php
require_once 'config.php';
$res = $conn->query("SHOW COLUMNS FROM users");
$columns = [];
while($row = $res->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo "Columns: " . implode(", ", $columns) . "\n\n";

$res = $conn->query("SELECT user_id, whatsapp_number, mobile_number FROM users LIMIT 3");
while($row = $res->fetch_assoc()) {
    echo "User: " . $row['user_id'] . " | WhatsApp: " . ($row['whatsapp_number'] ?? 'NULL') . " | Mobile: " . ($row['mobile_number'] ?? 'NULL') . "\n";
}
?>
