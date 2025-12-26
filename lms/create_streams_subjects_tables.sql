-- Create tables for Streams, Subjects, Stream-Subject relationships, and Teacher Assignments
-- This structure allows flexible assignment of teachers to specific grade-subject combinations

-- Table: streams
-- Stores the grades or categories (e.g., "Grade 6", "Grade 7", "A/L Science", "A/L Arts")
CREATE TABLE IF NOT EXISTS `streams` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Stream name (e.g., "Grade 6", "Grade 7", "A/L Science")',
  `description` TEXT DEFAULT NULL COMMENT 'Optional description of the stream',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  INDEX `idx_name` (`name`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the grades or categories';

-- Table: subjects
-- Stores the subject names (e.g., "Science", "Mathematics", "English")
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Subject name (e.g., "Science", "Mathematics", "English")',
  `code` VARCHAR(20) DEFAULT NULL COMMENT 'Optional subject code (e.g., "SCI", "MATH")',
  `description` TEXT DEFAULT NULL COMMENT 'Optional description of the subject',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  INDEX `idx_name` (`name`),
  INDEX `idx_code` (`code`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the subject names';

-- Table: stream_subjects
-- Junction table that defines which subjects are offered in which streams (The "Offering")
-- This links streams to subjects, creating unique grade-subject combinations
CREATE TABLE IF NOT EXISTS `stream_subjects` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `stream_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to streams.id',
  `subject_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to subjects.id',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_stream_subjects_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stream_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Unique constraint: A subject can only be offered once per stream
  UNIQUE KEY `unique_stream_subject` (`stream_id`, `subject_id`),
  
  INDEX `idx_stream_id` (`stream_id`),
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines which subjects exist in which grades (The Offering)';

-- Table: teacher_assignments
-- Links teachers to specific stream-subject offerings (Enrollment Table)
-- This allows assigning a teacher to teach a specific subject in a specific grade
-- Supports multiple academic years/batches (e.g., same teacher can teach Grade 6 Science in 2025 and again in 2026 for new batch)
CREATE TABLE IF NOT EXISTS `teacher_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `teacher_id` VARCHAR(20) NOT NULL COMMENT 'Foreign Key: Links to users.id (where role = teacher)',
  `stream_subject_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to stream_subjects.id',
  `academic_year` INT(4) NOT NULL COMMENT 'Academic year (e.g., 2025, 2026) - allows same teacher to teach same subject for different batches',
  `batch_name` VARCHAR(50) DEFAULT NULL COMMENT 'Optional batch identifier (e.g., "Batch A", "Morning Batch", "2025-2026")',
  `status` ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active' COMMENT 'Assignment status',
  `assigned_date` DATE DEFAULT NULL COMMENT 'Date when teacher was assigned',
  `start_date` DATE DEFAULT NULL COMMENT 'Start date of the assignment',
  `end_date` DATE DEFAULT NULL COMMENT 'End date of the assignment',
  `notes` TEXT DEFAULT NULL COMMENT 'Optional notes about the assignment',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_teacher_assignments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_assignments_stream_subject` FOREIGN KEY (`stream_subject_id`) REFERENCES `stream_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Unique constraint: A teacher can be assigned to the same stream-subject in different academic years
  -- This allows: Mr. Sunil teaches Grade 6 Science in 2025, and again in 2026 for new batch
  UNIQUE KEY `unique_teacher_stream_subject_year` (`teacher_id`, `stream_subject_id`, `academic_year`),
  
  INDEX `idx_teacher_id` (`teacher_id`),
  INDEX `idx_stream_subject_id` (`stream_subject_id`),
  INDEX `idx_academic_year` (`academic_year`),
  INDEX `idx_status` (`status`),
  INDEX `idx_teacher_year` (`teacher_id`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links teachers to specific stream-subject offerings with academic year support';

-- Sample data for streams
INSERT INTO `streams` (`name`, `description`, `status`) VALUES
('Grade 6', 'Grade 6 students', 1),
('Grade 7', 'Grade 7 students', 1),
('Grade 8', 'Grade 8 students', 1),
('Grade 9', 'Grade 9 students', 1),
('Grade 10', 'Grade 10 students', 1),
('Grade 11', 'Grade 11 students', 1),
('A/L Science', 'Advanced Level Science stream', 1),
('A/L Arts', 'Advanced Level Arts stream', 1),
('A/L Commerce', 'Advanced Level Commerce stream', 1)
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Sample data for subjects
INSERT INTO `subjects` (`name`, `code`, `description`, `status`) VALUES
('Mathematics', 'MATH', 'Mathematics subject', 1),
('Science', 'SCI', 'Science subject', 1),
('English', 'ENG', 'English Language', 1),
('Sinhala', 'SIN', 'Sinhala Language', 1),
('History', 'HIS', 'History subject', 1),
('Geography', 'GEO', 'Geography subject', 1),
('Buddhism', 'BUD', 'Buddhism subject', 1),
('ICT', 'ICT', 'Information and Communication Technology', 1),
('Physics', 'PHY', 'Physics for A/L', 1),
('Chemistry', 'CHE', 'Chemistry for A/L', 1),
('Biology', 'BIO', 'Biology for A/L', 1),
('Commerce', 'COM', 'Commerce for A/L', 1),
('Accounting', 'ACC', 'Accounting for A/L', 1)
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Sample data for stream_subjects (Example: Grade 6 offers Mathematics, Science, English, Sinhala)
INSERT INTO `stream_subjects` (`stream_id`, `subject_id`, `status`) 
SELECT s.id, sub.id, 1
FROM streams s
CROSS JOIN subjects sub
WHERE s.name = 'Grade 6' AND sub.name IN ('Mathematics', 'Science', 'English', 'Sinhala', 'History', 'Geography', 'Buddhism', 'ICT')
ON DUPLICATE KEY UPDATE `status` = 1;

-- Sample data for stream_subjects (Example: Grade 7 offers same subjects as Grade 6)
INSERT INTO `stream_subjects` (`stream_id`, `subject_id`, `status`) 
SELECT s.id, sub.id, 1
FROM streams s
CROSS JOIN subjects sub
WHERE s.name = 'Grade 7' AND sub.name IN ('Mathematics', 'Science', 'English', 'Sinhala', 'History', 'Geography', 'Buddhism', 'ICT')
ON DUPLICATE KEY UPDATE `status` = 1;

-- Sample data for stream_subjects (Example: A/L Science offers Physics, Chemistry, Biology, Mathematics)
INSERT INTO `stream_subjects` (`stream_id`, `subject_id`, `status`) 
SELECT s.id, sub.id, 1
FROM streams s
CROSS JOIN subjects sub
WHERE s.name = 'A/L Science' AND sub.name IN ('Physics', 'Chemistry', 'Biology', 'Mathematics', 'English')
ON DUPLICATE KEY UPDATE `status` = 1;

-- Sample data for stream_subjects (Example: A/L Commerce offers Commerce, Accounting, Mathematics, English)
INSERT INTO `stream_subjects` (`stream_id`, `subject_id`, `status`) 
SELECT s.id, sub.id, 1
FROM streams s
CROSS JOIN subjects sub
WHERE s.name = 'A/L Commerce' AND sub.name IN ('Commerce', 'Accounting', 'Mathematics', 'English')
ON DUPLICATE KEY UPDATE `status` = 1;

