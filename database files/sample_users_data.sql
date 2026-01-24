-- Sample user data for LMS system
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
    'stu',
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
    'teacher1',
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

