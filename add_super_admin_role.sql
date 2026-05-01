ALTER TABLE users MODIFY COLUMN role ENUM('student', 'teacher', 'instructor', 'admin', 'super_admin') NOT NULL DEFAULT 'student';
