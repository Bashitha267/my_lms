<?php
require_once 'config.php';

// 1. Update instructor_requests status enum to include 'paid'
$conn->query("ALTER TABLE instructor_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'paid', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");

// 2. Create instructor_request_acceptances if not exists (multiple instructors can accept but student picks one)
// Wait, the user said "if one instructor accepted the thing others cannot accept fix it"
// This means we should probably change the logic in dashboard/instructors.php to check if status is still 'pending'.
// If one accepts, status becomes 'accepted', and others see it as "already taken".

// 3. Create live_classes table
$conn->query("CREATE TABLE IF NOT EXISTS `live_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `instructor_id` varchar(20) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `zoom_link` varchar(500) NOT NULL,
  `status` enum('scheduled','ongoing','ended','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`request_id`) REFERENCES `instructor_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 4. Update users table for better rating system
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'rating_count'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN rating_count int(11) DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN total_rating decimal(12,2) DEFAULT 0.00");
}

// 5. Create instructor_payments table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `instructor_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `instructor_id` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'card',
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`request_id`) REFERENCES `instructor_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

echo "Database updated successfully!";
?>
