<?php
require_once '../config.php';

// Create course_payments table
$sql = "CREATE TABLE IF NOT EXISTS course_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_enrollment_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL, -- 'card', 'bank_transfer'
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    receipt_path VARCHAR(255) DEFAULT NULL,
    receipt_type VARCHAR(20) DEFAULT NULL, -- 'image', 'pdf'
    card_number VARCHAR(20) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (course_enrollment_id) REFERENCES course_enrollments(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table course_payments created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Add index for faster lookups
$conn->query("CREATE INDEX idx_cp_enrollment ON course_payments(course_enrollment_id)");
$conn->query("CREATE INDEX idx_cp_status ON course_payments(payment_status)");

echo "Setup completed.";
?>
