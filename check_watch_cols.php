<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE video_watch_log");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
