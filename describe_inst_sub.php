<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE instructor_subjects");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Failed to DESCRIBE instructor_subjects: " . $conn->error;
}
?>
