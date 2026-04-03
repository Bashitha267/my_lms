<?php
include 'config.php';
$tables = ['instructor_requests', 'instructor_subjects', 'users', 'subjects'];
foreach ($tables as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    if ($res->num_rows > 0) {
        echo "Table '$table' exists.\n";
        $columns = $conn->query("DESCRIBE $table");
        while ($col = $columns->fetch_assoc()) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    } else {
        echo "Table '$table' DOES NOT EXIST.\n";
    }
}
?>
