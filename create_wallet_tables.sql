-- ============================================================
-- Teacher Payment Points System - Database Setup (Fixed)
-- Commission Split: 75% Teacher / 25% Institute
-- ============================================================

-- Create teacher_wallet table (without FK constraints)
CREATE TABLE IF NOT EXISTS teacher_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(20) NOT NULL UNIQUE,
    total_points DECIMAL(12,2) DEFAULT 0.00 NOT NULL,
    total_earned DECIMAL(12,2) DEFAULT 0.00 NOT NULL,
    total_withdrawn DECIMAL(12,2) DEFAULT 0.00 NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher_points (teacher_id, total_points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create institute_wallet table
CREATE TABLE IF NOT EXISTS institute_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_points DECIMAL(12,2) DEFAULT 0.00 NOT NULL,
    total_earned DECIMAL(12,2) DEFAULT 0.00 NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialize institute wallet with single row
INSERT INTO institute_wallet (total_points, total_earned) 
VALUES (0.00, 0.00)
ON DUPLICATE KEY UPDATE id=id;

-- Create payment_transactions table (without FK constraints)
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_type ENUM('enrollment', 'monthly') NOT NULL,
    payment_id INT NOT NULL,
    teacher_id VARCHAR(20) NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    teacher_points DECIMAL(10,2) NOT NULL,
    institute_points DECIMAL(10,2) NOT NULL,
    commission_rate_teacher DECIMAL(5,2) DEFAULT 75.00,
    commission_rate_institute DECIMAL(5,2) DEFAULT 25.00,
    transaction_status ENUM('pending', 'completed', 'reversed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_tracking (payment_type, payment_id),
    INDEX idx_teacher_transactions (teacher_id, created_at),
    INDEX idx_transaction_status (transaction_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'Tables created successfully!' AS Status;
