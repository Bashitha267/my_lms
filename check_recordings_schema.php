<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE recordings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}
?>
