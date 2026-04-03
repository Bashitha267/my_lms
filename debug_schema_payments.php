<?php
require_once 'config.php';
$res = $conn->query("SHOW TABLES LIKE 'instructor_payments'");
if ($res->num_rows == 0) {
    echo "Table instructor_payments DOES NOT EXIST.";
} else {
    $cols = $conn->query("SHOW COLUMNS FROM instructor_payments");
    while($col = $cols->fetch_assoc()) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
}
?>
