<?php
require_once 'config.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS `instructor_subjects` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `instructor_id` varchar(20) NOT NULL,
      `subject_id` int(11) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_assignment` (`instructor_id`,`subject_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `instructor_requests` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` varchar(20) NOT NULL,
      `subject_id` int(11) NOT NULL,
      `request_note` text DEFAULT NULL,
      `status` enum('pending','accepted','completed','cancelled') NOT NULL DEFAULT 'pending',
      `accepted_by` varchar(20) DEFAULT NULL,
      `accepted_at` datetime DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table created successfully\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
}
?>
