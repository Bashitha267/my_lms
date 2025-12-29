-- Create student_enrollment table
-- Links students to stream-subject combinations (enrollments)

CREATE TABLE IF NOT EXISTS `student_enrollment` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `student_id` VARCHAR(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = student)',
  `stream_subject_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to stream_subjects.id',
  `academic_year` INT(4) NOT NULL COMMENT 'Academic year (e.g., 2025, 2026)',
  `batch_name` VARCHAR(50) DEFAULT NULL COMMENT 'Optional batch identifier',
  `enrolled_date` DATE NOT NULL COMMENT 'Date when student enrolled',
  `status` ENUM('active', 'inactive', 'completed', 'dropped') NOT NULL DEFAULT 'active' COMMENT 'Enrollment status',
  `payment_status` ENUM('pending', 'paid', 'partial', 'refunded') NOT NULL DEFAULT 'pending' COMMENT 'Payment status',
  `payment_method` VARCHAR(50) DEFAULT NULL COMMENT 'Payment method used (e.g., bank_transfer, card, cash, mobile_payment)',
  `payment_date` DATE DEFAULT NULL COMMENT 'Date of payment',
  `payment_amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Amount paid',
  `notes` TEXT DEFAULT NULL COMMENT 'Optional notes about the enrollment',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_student_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_enrollment_stream_subject` FOREIGN KEY (`stream_subject_id`) REFERENCES `stream_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Unique constraint: A student can enroll in the same stream-subject only once per academic year
  UNIQUE KEY `unique_student_stream_subject_year` (`student_id`, `stream_subject_id`, `academic_year`),
  
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_stream_subject_id` (`stream_subject_id`),
  INDEX `idx_academic_year` (`academic_year`),
  INDEX `idx_status` (`status`),
  INDEX `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links students to specific stream-subject enrollments';

