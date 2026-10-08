-- Migration: Add is_free and pdf_path to publications table

ALTER TABLE `publications`
  ADD COLUMN IF NOT EXISTS `is_free` TINYINT(1) NOT NULL DEFAULT 0 AFTER `discount`,
  ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(255) DEFAULT NULL AFTER `image_path`;
