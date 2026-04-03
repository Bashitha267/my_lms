-- mentor_system_migration.sql
-- Run this on your XAMPP MySQL database

-- 1. Update Users Table for Mentor Role and Rate
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_mentor TINYINT(1) DEFAULT 0 COMMENT 'If a teacher also behaves as an instructor';
ALTER TABLE users ADD COLUMN IF NOT EXISTS rating DECIMAL(3,2) DEFAULT 4.50;
-- (hourly_rate already added in previous turns)

-- 2. Instructor Requests Table
-- Enhanced to include date and description
CREATE TABLE IF NOT EXISTS `instructor_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `session_date` date DEFAULT NULL,
  `request_note` text DEFAULT NULL,
  `status` enum('pending','accepted','payment_pending','verified_payment','rejected','completed') DEFAULT 'pending',
  `accepted_by` varchar(20) DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Instructor Payments Table
CREATE TABLE IF NOT EXISTS `instructor_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `instructor_id` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `receipt_path` varchar(255) NOT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  `verified_by` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Update existing assignments
-- Linking instructors/teachers to subjects for mentorship availability
CREATE TABLE IF NOT EXISTS `instructor_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instructor_id` varchar(20) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`instructor_id`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
