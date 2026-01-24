<?php
require_once 'config.php';

// Drop columns if they exist
$sql = "SHOW COLUMNS FROM teacher_assignments LIKE 'enrollment_fee'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $conn->query("ALTER TABLE teacher_assignments DROP COLUMN enrollment_fee");
    echo "Dropped enrollment_fee column.\n";
}

$sql = "SHOW COLUMNS FROM teacher_assignments LIKE 'monthly_fee'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $conn->query("ALTER TABLE teacher_assignments DROP COLUMN monthly_fee");
    echo "Dropped monthly_fee column.\n";
}

echo "Database schema cleanup successful.";
?>
