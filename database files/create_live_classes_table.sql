-- Create live_classes table
-- Stores live class sessions created by teachers

CREATE TABLE IF NOT EXISTS `live_classes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `teacher_assignment_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to teacher_assignments.id',
  `title` VARCHAR(255) NOT NULL COMMENT 'Live class title',
  `description` TEXT DEFAULT NULL COMMENT 'Live class description',
  `youtube_video_id` VARCHAR(20) DEFAULT NULL COMMENT 'YouTube video ID (if using YouTube Live)',
  `youtube_url` VARCHAR(500) DEFAULT NULL COMMENT 'YouTube Live URL',
  `stream_url` VARCHAR(500) DEFAULT NULL COMMENT 'Stream URL (for custom streaming)',
  `status` ENUM('scheduled', 'ongoing', 'ended', 'cancelled') NOT NULL DEFAULT 'scheduled' COMMENT 'Live class status',
  `scheduled_start_time` DATETIME DEFAULT NULL COMMENT 'Scheduled start time',
  `actual_start_time` DATETIME DEFAULT NULL COMMENT 'Actual start time (when teacher starts)',
  `end_time` DATETIME DEFAULT NULL COMMENT 'End time (when teacher ends)',
  `recording_id` INT(11) DEFAULT NULL COMMENT 'Foreign Key: Links to recordings.id (if saved as recording)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_live_classes_teacher_assignment` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_live_classes_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  
  INDEX `idx_teacher_assignment_id` (`teacher_assignment_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_scheduled_start_time` (`scheduled_start_time`),
  INDEX `idx_actual_start_time` (`actual_start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores live class sessions';

-- Create live_class_participants table
-- Tracks participants (students) in live classes

CREATE TABLE IF NOT EXISTS `live_class_participants` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `live_class_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to live_classes.id',
  `student_id` VARCHAR(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = student)',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When student joined',
  `left_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When student left (NULL if still in class)',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_live_class_participants_live_class` FOREIGN KEY (`live_class_id`) REFERENCES `live_classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_live_class_participants_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Unique constraint: A student can only have one active participation per live class
  UNIQUE KEY `unique_live_class_student` (`live_class_id`, `student_id`),
  
  INDEX `idx_live_class_id` (`live_class_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_joined_at` (`joined_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks participants in live classes';

