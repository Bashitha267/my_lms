<?php
require_once 'config.php';
$cols = $conn->query("SHOW COLUMNS FROM instructor_requests");
while($col = $cols->fetch_assoc()) {
    echo $col['Field'] . "\n";
}
?>
