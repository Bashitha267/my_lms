-- Create recording_files table for storing file uploads related to recordings
CREATE TABLE IF NOT EXISTS `recording_files` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `recording_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id',
  `uploaded_by` VARCHAR(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (who uploaded the file)',
  `file_name` VARCHAR(255) NOT NULL COMMENT 'Original file name',
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Path to stored file',
  `file_size` BIGINT(20) NOT NULL COMMENT 'File size in bytes',
  `file_type` VARCHAR(100) DEFAULT NULL COMMENT 'MIME type of the file',
  `file_extension` VARCHAR(10) DEFAULT NULL COMMENT 'File extension',
  `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When file was uploaded',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=deleted',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_recording_files_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_recording_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_recording_id` (`recording_id`),
  INDEX `idx_uploaded_by` (`uploaded_by`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores file uploads for recordings';

