CREATE TABLE IF NOT EXISTS `dashboard_colors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_key` VARCHAR(50) UNIQUE NOT NULL,
  `section_name` VARCHAR(100) NOT NULL,
  `bg_color` VARCHAR(50) NOT NULL DEFAULT '#ffffff',
  `card_colors` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default colors for the three sections
INSERT INTO `dashboard_colors` (`section_key`, `section_name`, `bg_color`, `card_colors`) 
VALUES ('al_results', 'A/L Results Section', '#e0f2fe', '#f3e8ff,#e0f2fe,#dcfce7,#fee2e2,#fef9c3,#ccfbf1')
ON DUPLICATE KEY UPDATE `section_name`=VALUES(`section_name`);

INSERT INTO `dashboard_colors` (`section_key`, `section_name`, `bg_color`, `card_colors`) 
VALUES ('classes', 'Available Classes Section', '#fef3c7', '#dbeafe,#d1fae5,#f3e8ff,#fee2e2,#e0f2fe,#ffedd5')
ON DUPLICATE KEY UPDATE `section_name`=VALUES(`section_name`);

INSERT INTO `dashboard_colors` (`section_key`, `section_name`, `bg_color`, `card_colors`) 
VALUES ('extra_courses', 'Extra Courses Section', '#dcfce7', '#dbeafe,#d1fae5,#f3e8ff,#fee2e2,#e0f2fe,#ffedd5')
ON DUPLICATE KEY UPDATE `section_name`=VALUES(`section_name`);
