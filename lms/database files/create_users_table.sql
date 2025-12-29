-- Create users table for LMS system
-- This table stores all user information including students, instructors, and admins

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` VARCHAR(20) NOT NULL PRIMARY KEY COMMENT 'Unique user ID (e.g., stu_0001)',
  `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Username for login',
  `email` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Email address',
  `password` VARCHAR(255) NOT NULL COMMENT 'Hashed password',
  `role` ENUM('student', 'teacher', 'instructor', 'admin') NOT NULL DEFAULT 'student' COMMENT 'User role: student, teacher, instructor, admin',
  `first_name` VARCHAR(100) DEFAULT NULL COMMENT 'First name',
  `second_name` VARCHAR(100) DEFAULT NULL COMMENT 'Last name',
  `dob` DATE DEFAULT NULL COMMENT 'Date of birth',
  `school_name` VARCHAR(200) DEFAULT NULL COMMENT 'School name',
  `exam_year` INT(4) DEFAULT NULL COMMENT 'Exam year (e.g., 2024)',
  `closest_town` VARCHAR(100) DEFAULT NULL COMMENT 'Closest town',
  `district` VARCHAR(100) DEFAULT NULL COMMENT 'District',
  `address` TEXT DEFAULT NULL COMMENT 'Full address',
  `nic_no` VARCHAR(20) DEFAULT NULL COMMENT 'NIC number',
  `mobile_number` VARCHAR(20) DEFAULT NULL COMMENT 'Mobile phone number',
  `whatsapp_number` VARCHAR(20) DEFAULT NULL COMMENT 'WhatsApp number',
  `gender` ENUM('male', 'female') DEFAULT NULL COMMENT 'Gender',
  `profile_picture` VARCHAR(255) DEFAULT NULL COMMENT 'Path to profile picture',
  `registering_date` DATE NOT NULL COMMENT 'Date of registration',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Account status: 1=active, 0=inactive',
  `approved` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Approval status: 1=approved, 0=not approved',
  `verification_method` VARCHAR(20) DEFAULT 'none' COMMENT 'Verification method: nic, mobile, none',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`),
  INDEX `idx_status` (`status`),
  INDEX `idx_approved` (`approved`),
  INDEX `idx_nic_no` (`nic_no`),
  INDEX `idx_mobile_number` (`mobile_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users table for LMS system';

-- Sample user data
-- Password for all users: 1234
-- Note: These are pre-hashed passwords using PHP password_hash() function

-- Admin User
INSERT INTO `users` (
    `user_id`, `username`, `email`, `password`, `role`, 
    `first_name`, `second_name`, `dob`, 
    `mobile_number`, `whatsapp_number`, `gender`,
    `registering_date`, `status`, `approved`, `verification_method`
) VALUES (
    'adm_0001',
    'admin',
    'admin@lms.com',
    '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', -- 1234
    'admin',
    'John',
    'Administrator',
    '1985-05-15',
    '0771234567',
    '0771234567',
    'male',
    CURDATE(),
    1,
    1,
    'none'
);

-- Student User
INSERT INTO `users` (
    `user_id`, `username`, `email`, `password`, `role`,
    `first_name`, `second_name`, `dob`,
    `school_name`, `exam_year`, `closest_town`, `district`, `address`,
    `nic_no`, `mobile_number`, `whatsapp_number`, `gender`,
    `registering_date`, `status`, `approved`, `verification_method`
) VALUES (
    'stu_0001',
    'student01',
    'student01@lms.com',
    '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', -- 1234
    'student',
    'Sarah',
    'Perera',
    '2005-08-20',
    'Royal College',
    2024,
    'Colombo',
    'Colombo',
    '123 Main Street, Colombo 05',
    '200512345678',
    '0772345678',
    '0772345678',
    'female',
    CURDATE(),
    1,
    1,
    'nic'
);

-- Teacher User
INSERT INTO `users` (
    `user_id`, `username`, `email`, `password`, `role`,
    `first_name`, `second_name`, `dob`,
    `school_name`, `closest_town`, `district`, `address`,
    `nic_no`, `mobile_number`, `whatsapp_number`, `gender`,
    `registering_date`, `status`, `approved`, `verification_method`
) VALUES (
    'tea_0001',
    'teacher01',
    'teacher01@lms.com',
    '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', -- 1234
    'teacher',
    'Kamal',
    'Fernando',
    '1988-03-10',
    'Royal College',
    'Colombo',
    'Colombo',
    '456 Teacher Lane, Colombo 07',
    '198812345678',
    '0773456789',
    '0773456789',
    'male',
    CURDATE(),
    1,
    1,
    'nic'
);
