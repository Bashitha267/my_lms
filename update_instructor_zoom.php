<?php
require_once 'config.php';
$sql = "ALTER TABLE instructor_requests 
        ADD COLUMN IF NOT EXISTS zoom_link VARCHAR(500) DEFAULT NULL, 
        ADD COLUMN IF NOT EXISTS zoom_meeting_id VARCHAR(100) DEFAULT NULL, 
        ADD COLUMN IF NOT EXISTS zoom_password VARCHAR(100) DEFAULT NULL";
if ($conn->query($sql)) {
    echo "Columns added successfully!\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
