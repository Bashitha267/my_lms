-- Live Class Participants Table
-- Tracks which students joined live classes and their attendance

CREATE TABLE IF NOT EXISTS `live_class_participants` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `recording_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id (live class)',
  `student_id` VARCHAR(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (student)',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When student joined the live class',
  `left_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When student left the live class (NULL = still online)',
  
  CONSTRAINT `fk_participants_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_participants_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Unique constraint: one record per student per live class
  -- Allows re-joining by updating left_at to NULL
  UNIQUE KEY `unique_participant` (`recording_id`, `student_id`),
  
  -- Index for faster queries
  INDEX `idx_recording_status` (`recording_id`, `left_at`),
  INDEX `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

