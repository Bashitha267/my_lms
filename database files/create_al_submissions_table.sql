-- AL Exam Submissions Table
-- Stores student A/L subject selections and details

CREATE TABLE IF NOT EXISTS `al_exam_submissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(20) NOT NULL COMMENT 'FK to users.user_id',
  `subject_1` VARCHAR(100) NOT NULL,
  `result_1` VARCHAR(5) DEFAULT NULL,
  `subject_2` VARCHAR(100) NOT NULL,
  `result_2` VARCHAR(5) DEFAULT NULL,
  `subject_3` VARCHAR(100) NOT NULL,
  `result_3` VARCHAR(5) DEFAULT NULL,
  `index_number` VARCHAR(50) DEFAULT NULL,
  `exam_year` INT(11) DEFAULT NULL,
  `district` VARCHAR(50) NOT NULL,
  `stream` VARCHAR(50) DEFAULT NULL COMMENT 'Arts, Commerce, Science, Tech',
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `agreed_to_publish` TINYINT(1) DEFAULT 0,
  `results_submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `district_rank` INT(11) DEFAULT NULL,
  `island_rank` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  CONSTRAINT `fk_al_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  UNIQUE KEY `unique_student_submission` (`student_id`),
  INDEX `idx_district` (`district`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores A/L exam details submitted by students';
