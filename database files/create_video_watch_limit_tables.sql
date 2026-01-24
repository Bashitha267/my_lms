-- Add watch_limit column to recordings table
-- Default limit is 3 times per student per video
ALTER TABLE `recordings` 
ADD COLUMN `watch_limit` INT(11) NOT NULL DEFAULT 3 COMMENT 'Maximum number of times a student can watch this video (0 = unlimited)' 
AFTER `free_video`;

-- Create video_watch_log table
-- Tracks how many times each student has watched each video
CREATE TABLE IF NOT EXISTS `video_watch_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `recording_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id',
  `student_id` VARCHAR(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = student)',
  `watched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when video was watched',
  `watch_duration` INT(11) DEFAULT NULL COMMENT 'Duration watched in seconds (optional, for future use)',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_video_watch_log_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_video_watch_log_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_recording_id` (`recording_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_recording_student` (`recording_id`, `student_id`),
  INDEX `idx_watched_at` (`watched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks video watch history for students';

