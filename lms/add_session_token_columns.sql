-- Add session token columns to users table for single login functionality
-- Run this SQL to add the required columns if they don't exist

ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `session_token` VARCHAR(64) DEFAULT NULL COMMENT 'Session token for single login',
ADD COLUMN IF NOT EXISTS `session_created_at` DATETIME DEFAULT NULL COMMENT 'Session creation timestamp';

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS `idx_session_token` ON `users` (`session_token`);

