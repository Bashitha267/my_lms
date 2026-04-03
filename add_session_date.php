<?php
require_once 'config.php';
$sql = "ALTER TABLE instructor_requests ADD COLUMN IF NOT EXISTS session_date DATE AFTER subject_id";
if ($conn->query($sql)) {
    echo "Success: session_date added.";
} else {
    echo "Error: " . $conn->error;
}
?>
