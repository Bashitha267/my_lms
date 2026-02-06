<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE recordings");
$fields = [];
while ($row = $res->fetch_assoc()) {
    $fields[] = $row['Field'];
}
echo implode("\n", $fields);
?>
