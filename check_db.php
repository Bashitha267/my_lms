<?php
require_once 'config.php';

echo "Checking tables...\n";
$tables = ['instructor_subjects', 'users', 'subjects'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "Table '$table' exists.\n";
        $columns = $conn->query("DESCRIBE $table");
        while ($row = $columns->fetch_assoc()) {
            echo " - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Table '$table' DOES NOT EXIST.\n";
    }
    echo "\n";
}
?>
