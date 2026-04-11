<?php
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS instructor_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_id VARCHAR(20) NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    request_id INT NOT NULL,
    rating TINYINT NOT NULL DEFAULT 0,
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_req_student (request_id, student_id),
    KEY idx_instructor (instructor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "instructor_ratings table ready!\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Also ensure instructor_requests has 'completed' in status enum
$conn->query("ALTER TABLE instructor_requests MODIFY COLUMN status ENUM('pending','accepted','payment_pending','paid','rejected','completed') NOT NULL DEFAULT 'pending'");
echo "Status enum updated!\n";
?>
