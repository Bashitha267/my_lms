<?php
require_once 'config.php';

function checkTable($conn, $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if (!$res) {
        echo "Error: " . $conn->error . "\n";
        return;
    }
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n";
}

checkTable($conn, 'recordings');
checkTable($conn, 'zoom_classes');
checkTable($conn, 'teacher_assignments');
checkTable($conn, 'stream_subjects');
checkTable($conn, 'subjects');
checkTable($conn, 'users');
?>
