-- Create payment tables for enrollment and monthly payments

-- Table for enrollment payments (one-time payment per enrollment)
CREATE TABLE IF NOT EXISTS `enrollment_payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `student_enrollment_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to student_enrollment.id',
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Payment amount',
  `payment_method` ENUM('card', 'bank_transfer', 'cash', 'mobile_payment') NOT NULL COMMENT 'Payment method',
  `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending' COMMENT 'Payment status',
  `payment_date` DATE DEFAULT NULL COMMENT 'Date when payment was made',
  `card_number` VARCHAR(50) DEFAULT NULL COMMENT 'Last 4 digits of card (for card payments)',
  `receipt_path` VARCHAR(255) DEFAULT NULL COMMENT 'Path to uploaded receipt (for bank transfers)',
  `receipt_type` ENUM('image', 'pdf') DEFAULT NULL COMMENT 'Type of receipt file',
  `verified_by` VARCHAR(20) DEFAULT NULL COMMENT 'Admin user_id who verified the payment',
  `verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When payment was verified',
  `notes` TEXT DEFAULT NULL COMMENT 'Additional notes',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_enrollment_payment_enrollment` FOREIGN KEY (`student_enrollment_id`) REFERENCES `student_enrollment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enrollment_payment_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  
  INDEX `idx_student_enrollment_id` (`student_enrollment_id`),
  INDEX `idx_payment_status` (`payment_status`),
  INDEX `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores enrollment payment transactions';

-- Table for monthly payments (recurring monthly payments)
CREATE TABLE IF NOT EXISTS `monthly_payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `student_enrollment_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to student_enrollment.id',
  `month` INT(2) NOT NULL COMMENT 'Month (1-12)',
  `year` INT(4) NOT NULL COMMENT 'Year (e.g., 2025)',
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Payment amount',
  `payment_method` ENUM('card', 'bank_transfer', 'cash', 'mobile_payment') NOT NULL COMMENT 'Payment method',
  `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending' COMMENT 'Payment status',
  `payment_date` DATE DEFAULT NULL COMMENT 'Date when payment was made',
  `card_number` VARCHAR(50) DEFAULT NULL COMMENT 'Last 4 digits of card (for card payments)',
  `receipt_path` VARCHAR(255) DEFAULT NULL COMMENT 'Path to uploaded receipt (for bank transfers)',
  `receipt_type` ENUM('image', 'pdf') DEFAULT NULL COMMENT 'Type of receipt file',
  `verified_by` VARCHAR(20) DEFAULT NULL COMMENT 'Admin user_id who verified the payment',
  `verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When payment was verified',
  `notes` TEXT DEFAULT NULL COMMENT 'Additional notes',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_monthly_payment_enrollment` FOREIGN KEY (`student_enrollment_id`) REFERENCES `student_enrollment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_monthly_payment_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  
  -- Unique constraint: One payment per enrollment per month/year
  UNIQUE KEY `unique_enrollment_month_year` (`student_enrollment_id`, `month`, `year`),
  
  INDEX `idx_student_enrollment_id` (`student_enrollment_id`),
  INDEX `idx_payment_status` (`payment_status`),
  INDEX `idx_month_year` (`month`, `year`),
  INDEX `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores monthly payment transactions';

-- Table for enrollment fee settings (set by teachers)
CREATE TABLE IF NOT EXISTS `enrollment_fees` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `teacher_assignment_id` INT(11) NOT NULL COMMENT 'Foreign Key: Links to teacher_assignments.id',
  `enrollment_fee` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'One-time enrollment fee',
  `monthly_fee` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Monthly subscription fee',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  -- Foreign Key Constraints
  CONSTRAINT `fk_enrollment_fee_assignment` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  UNIQUE KEY `unique_teacher_assignment_fee` (`teacher_assignment_id`),
  
  INDEX `idx_teacher_assignment_id` (`teacher_assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores enrollment and monthly fee settings per teacher assignment';

