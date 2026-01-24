-- Create live_class_chat table for LMS system
-- Stores chat messages between students and teachers during live classes

CREATE TABLE IF NOT EXISTS `live_class_chat` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `live_class_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to live_classes.id - Chat context (live class being watched)',
  `sender_id` VARCHAR(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id - Message sender (student or teacher)',
  `sender_role` ENUM('student', 'teacher') NOT NULL COMMENT 'Role of the sender (for quick access)',
  `message` TEXT NOT NULL COMMENT 'Message content',
  `status` ENUM('sent', 'delivered', 'read') NOT NULL DEFAULT 'sent' COMMENT 'Message status',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Message creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Message update timestamp',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_live_class_chat_live_class` FOREIGN KEY (`live_class_id`) REFERENCES `live_classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_live_class_chat_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_live_class_id` (`live_class_id`),
  INDEX `idx_sender_id` (`sender_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chat messages between students and teachers for live classes';

