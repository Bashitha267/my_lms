<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE users");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n--- Sample Data ---\n";
$res = $conn->query("SELECT user_id, whatsapp_number, mobile_number FROM users LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
