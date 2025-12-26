-- Create teacher_education table
-- Stores education details for teachers (one teacher can have multiple education records)

CREATE TABLE IF NOT EXISTS `teacher_education` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `teacher_id` VARCHAR(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = teacher)',
  `qualification` VARCHAR(200) NOT NULL COMMENT 'Qualification name (e.g., "B.Sc. in Mathematics", "M.Ed.", "Ph.D. in Physics")',
  `institution` VARCHAR(200) DEFAULT NULL COMMENT 'Institution name where qualification was obtained',
  `year_obtained` INT(4) DEFAULT NULL COMMENT 'Year when qualification was obtained',
  `field_of_study` VARCHAR(200) DEFAULT NULL COMMENT 'Field of study or specialization',
  `grade_or_class` VARCHAR(50) DEFAULT NULL COMMENT 'Grade/Class obtained (e.g., "First Class", "Distinction", "A+")',
  `certificate_path` VARCHAR(255) DEFAULT NULL COMMENT 'Path to certificate document if uploaded',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraint
  CONSTRAINT `fk_teacher_education_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores education details for teachers';

