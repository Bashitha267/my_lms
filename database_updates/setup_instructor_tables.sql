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
