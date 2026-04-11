<?php
$c = new mysqli('localhost', 'root', '', 'lms');
$c->query("CREATE TABLE IF NOT EXISTS `instructor_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `instructor_id` varchar(20) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `rating` int(1) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "Ratings table created!";
?>
