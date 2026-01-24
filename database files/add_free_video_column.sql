-- Add free_video column to recordings table
ALTER TABLE `recordings` 
ADD COLUMN `free_video` TINYINT(1) DEFAULT 0 COMMENT 'Whether this video is free to watch (1 = free, 0 = requires payment)' 
AFTER `view_count`;

