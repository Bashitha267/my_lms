<?php
require_once 'config.php';
$cols = ['is_live', 'scheduled_start_time', 'actual_start_time', 'start_time', 'class_date'];
foreach ($cols as $c) {
    $res = $conn->query("SHOW COLUMNS FROM recordings LIKE '$c'");
    echo "$c: " . ($res->num_rows > 0 ? "YES" : "NO") . "\n";
}
?>
