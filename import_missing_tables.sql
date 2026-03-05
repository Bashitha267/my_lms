-- AL Exam Submissions Table
-- Stores student A/L subject selections, details, and results.

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
  `district` VARCHAR(50) NOT NULL,
  `stream` VARCHAR(50) DEFAULT NULL COMMENT 'Arts, Commerce, Science, Tech',
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `agreed_to_publish` TINYINT(1) DEFAULT 0,
  `results_submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  CONSTRAINT `fk_al_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  UNIQUE KEY `unique_student_submission` (`student_id`),
  INDEX `idx_district` (`district`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores A/L exam details submitted by students';

-- Table to link Instructors to Subjects (Many-to-Many)
CREATE TABLE IF NOT EXISTS `instructor_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instructor_id` varchar(20) NOT NULL COMMENT 'Link to users.user_id',
  `subject_id` int(11) NOT NULL COMMENT 'Link to subjects.id',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_instructor_subject` (`instructor_id`,`subject_id`),
  KEY `idx_instructor_id` (`instructor_id`),
  KEY `idx_subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to track Student Requests for Instructors
CREATE TABLE IF NOT EXISTS `instructor_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL COMMENT 'Link to users.user_id',
  `subject_id` int(11) NOT NULL COMMENT 'Link to subjects.id',
  `status` enum('pending','accepted','completed','cancelled') NOT NULL DEFAULT 'pending',
  `accepted_by` varchar(20) DEFAULT NULL COMMENT 'Instructor who accepted the request',
  `accepted_at` datetime DEFAULT NULL,
  `request_note` text DEFAULT NULL COMMENT 'Optional note from student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_accepted_by` (`accepted_by`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
