<?php
require_once 'config.php';
$sql1 = "ALTER TABLE instructor_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'payment_pending', 'completed', 'cancelled') DEFAULT 'pending'";
$sql2 = "ALTER TABLE instructor_requests ADD COLUMN IF NOT EXISTS payment_id INT AFTER status";

if ($conn->query($sql1) && $conn->query($sql2)) {
    echo "Success: instructor_requests updated.";
} else {
    echo "Error: " . $conn->error;
}
?>
