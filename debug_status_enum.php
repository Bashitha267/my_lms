<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE instructor_requests status");
if ($res) {
    $row = $res->fetch_assoc();
    echo $row['Type'];
}
?>
