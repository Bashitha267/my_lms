<?php
require_once 'config.php';
$sql = "ALTER TABLE instructor_payments 
        ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL, 
        ADD COLUMN IF NOT EXISTS verified_by VARCHAR(20) DEFAULT NULL";
if ($conn->query($sql)) {
    echo "Columns added successfully to instructor_payments!\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
