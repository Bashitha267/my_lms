-- Create recordings table
-- Stores YouTube video recordings linked to teacher assignments
-- Each recording is linked to a specific teacher assignment (e.g., "Mr. Sunil teaches Grade 6 Science in 2025")

CREATE TABLE IF NOT EXISTS `recordings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `teacher_assignment_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to teacher_assignments.id',
  `title` VARCHAR(255) NOT NULL COMMENT 'Video title',
  `description` TEXT DEFAULT NULL COMMENT 'Video description',
  `youtube_video_id` VARCHAR(20) NOT NULL COMMENT 'YouTube video ID extracted from URL',
  `youtube_url` VARCHAR(500) DEFAULT NULL COMMENT 'Original YouTube URL',
  `duration` VARCHAR(20) DEFAULT NULL COMMENT 'Video duration (e.g., "10:30")',
  `thumbnail_url` VARCHAR(500) DEFAULT NULL COMMENT 'YouTube thumbnail URL',
  `view_count` INT(11) DEFAULT 0 COMMENT 'View count',
  `free_video` TINYINT(1) DEFAULT 0 COMMENT 'Whether this video is free to watch (1 = free, 0 = requires payment)',
  `status` ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active' COMMENT 'Recording status',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraint
  CONSTRAINT `fk_recordings_teacher_assignment` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_teacher_assignment_id` (`teacher_assignment_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_youtube_video_id` (`youtube_video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores YouTube video recordings linked to teacher assignments';

