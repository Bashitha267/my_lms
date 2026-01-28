-- Create Zoom Live Class Tables

-- Zoom Classes Table
CREATE TABLE IF NOT EXISTS `zoom_classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_assignment_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `zoom_meeting_link` VARCHAR(500) NOT NULL,
  `zoom_meeting_id` VARCHAR(255),
  `zoom_passcode` VARCHAR(100),
  `scheduled_start_time` DATETIME NOT NULL,
  `actual_start_time` DATETIME,
  `end_time` DATETIME,
  `status` ENUM('scheduled', 'ongoing', 'ended', 'cancelled') DEFAULT 'scheduled',
  `free_class` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments`(`id`) ON DELETE CASCADE,
  INDEX `idx_teacher_assignment` (`teacher_assignment_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_scheduled_time` (`scheduled_start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zoom Participants Table
CREATE TABLE IF NOT EXISTS `zoom_participants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `zoom_class_id` INT NOT NULL,
  `user_id` VARCHAR(50) NOT NULL,
  `join_time` DATETIME NOT NULL,
  `leave_time` DATETIME,
  `duration_minutes` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_zoom_class` (`zoom_class_id`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zoom Chat Messages Table
CREATE TABLE IF NOT EXISTS `zoom_chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `zoom_class_id` INT NOT NULL,
  `sender_id` VARCHAR(50) NOT NULL,
  `message` TEXT NOT NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_zoom_class` (`zoom_class_id`),
  INDEX `idx_sender` (`sender_id`),
  INDEX `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zoom Class Files Table
CREATE TABLE IF NOT EXISTS `zoom_class_files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `zoom_class_id` INT NOT NULL,
  `uploader_id` VARCHAR(50) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` BIGINT NOT NULL,
  `file_type` VARCHAR(100),
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploader_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_zoom_class` (`zoom_class_id`),
  INDEX `idx_uploader` (`uploader_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
