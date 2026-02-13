<?php
require_once 'config.php';
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
?>
