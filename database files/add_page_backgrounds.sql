-- Add recordings and live classes background settings
-- Run this SQL to add the new settings for recordings and live classes pages

-- Insert recordings background setting
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) 
VALUES ('recordings_background', NULL, 'image', 'Background image for recordings page')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- Insert live classes background setting
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) 
VALUES ('live_classes_background', NULL, 'image', 'Background image for live classes page')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
