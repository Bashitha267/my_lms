-- Create system_settings table for storing application-wide settings
-- Run this SQL to add the settings table

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `setting_type` ENUM('text', 'image', 'number', 'boolean', 'json') DEFAULT 'text',
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(20) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default dashboard background setting
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) 
VALUES ('dashboard_background', NULL, 'image', 'Background image for student dashboard')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
