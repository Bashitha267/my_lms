<?php
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS teacher_payment_requests (
  id int(11) NOT NULL AUTO_INCREMENT,
  teacher_id varchar(20) NOT NULL,
  amount decimal(10,2) NOT NULL,
  status enum('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  request_date timestamp NOT NULL DEFAULT current_timestamp(),
  processed_date timestamp NULL DEFAULT NULL,
  processed_by varchar(20) DEFAULT NULL,
  payment_method varchar(50) DEFAULT NULL,
  admin_notes text DEFAULT NULL,
  PRIMARY KEY (id),
  KEY teacher_id (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql) === TRUE) {
    echo "Table teacher_payment_requests created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
