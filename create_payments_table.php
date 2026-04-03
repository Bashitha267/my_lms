<?php
require_once 'config.php';
$sql = "CREATE TABLE IF NOT EXISTS instructor_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    instructor_id VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    receipt_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "Success: instructor_payments table created.";
} else {
    echo "Error: " . $conn->error;
}
?>
