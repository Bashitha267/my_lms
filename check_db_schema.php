<?php
require_once 'config.php';
$res = $conn->query('DESCRIBE instructor_requests');
if (!$res) {
    echo "Error: " . $conn->error;
} else {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}
?>
