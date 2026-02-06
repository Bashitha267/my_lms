-- ============================================================
-- Teacher Payment Points System - Database Setup
-- Commission Split: 75% Teacher / 25% Institute
-- ============================================================

-- Create teacher_wallet table
CREATE TABLE IF NOT EXISTS teacher_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(20) NOT NULL UNIQUE,
    total_points DECIMAL(12,2) DEFAULT 0.00 NOT NULL,
    total_earned DECIMAL(12,2) DEFAULT 0.00 NOT NULL,
    total_withdrawn DECIMAL(12,2) DEFAULT 0.00 NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE,
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

-- Create payment_transactions table
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
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_payment_tracking (payment_type, payment_id),
    INDEX idx_teacher_transactions (teacher_id, created_at),
    INDEX idx_transaction_status (transaction_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Trigger for Enrollment Payments
-- ============================================================

DROP TRIGGER IF EXISTS after_enrollment_payment_update;

DELIMITER $$

CREATE TRIGGER after_enrollment_payment_update
AFTER UPDATE ON enrollment_payments
FOR EACH ROW
BEGIN
    DECLARE v_teacher_id VARCHAR(20);
    DECLARE v_student_id VARCHAR(20);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    -- Only process when status changes to 'paid' from non-paid
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        
        -- Get teacher and student info
        SELECT ta.teacher_id, se.student_id 
        INTO v_teacher_id, v_student_id
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
            AND se.academic_year = ta.academic_year
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            -- Calculate split (75% teacher, 25% institute)
            SET v_teacher_points = NEW.amount * 0.75;
            SET v_institute_points = NEW.amount * 0.25;
            
            -- Create wallet if doesn't exist
            INSERT IGNORE INTO teacher_wallet (teacher_id, total_points, total_earned)
            VALUES (v_teacher_id, 0.00, 0.00);
            
            -- Update teacher wallet
            UPDATE teacher_wallet
            SET total_points = total_points + v_teacher_points,
                total_earned = total_earned + v_teacher_points
            WHERE teacher_id = v_teacher_id;
            
            -- Update institute wallet
            UPDATE institute_wallet
            SET total_points = total_points + v_institute_points,
                total_earned = total_earned + v_institute_points
            WHERE id = 1;
            
            -- Log transaction
            INSERT INTO payment_transactions (
                payment_type, payment_id, teacher_id, student_id,
                total_amount, teacher_points, institute_points, transaction_status
            ) VALUES (
                'enrollment', NEW.id, v_teacher_id, v_student_id,
                NEW.amount, v_teacher_points, v_institute_points, 'completed'
            );
        END IF;
        
    -- Handle refunds (reverse points)
    ELSEIF NEW.payment_status = 'refunded' AND OLD.payment_status = 'paid' THEN
        
        -- Find the original transaction
        SELECT teacher_id, student_id, teacher_points, institute_points
        INTO v_teacher_id, v_student_id, v_teacher_points, v_institute_points
        FROM payment_transactions
        WHERE payment_type = 'enrollment' AND payment_id = NEW.id AND transaction_status = 'completed'
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            -- Reverse teacher wallet
            UPDATE teacher_wallet
            SET total_points = total_points - v_teacher_points
            WHERE teacher_id = v_teacher_id;
            
            -- Reverse institute wallet
            UPDATE institute_wallet
            SET total_points = total_points - v_institute_points
            WHERE id = 1;
            
            -- Mark transaction as reversed
            UPDATE payment_transactions
            SET transaction_status = 'reversed'
            WHERE payment_type = 'enrollment' AND payment_id = NEW.id;
        END IF;
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- Trigger for Monthly Payments
-- ============================================================

DROP TRIGGER IF EXISTS after_monthly_payment_update;

DELIMITER $$

CREATE TRIGGER after_monthly_payment_update
AFTER UPDATE ON monthly_payments
FOR EACH ROW
BEGIN
    DECLARE v_teacher_id VARCHAR(20);
    DECLARE v_student_id VARCHAR(20);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    -- Only process when status changes to 'paid' from non-paid
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        
        -- Get teacher and student info
        SELECT ta.teacher_id, se.student_id 
        INTO v_teacher_id, v_student_id
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
            AND se.academic_year = ta.academic_year
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            -- Calculate split (75% teacher, 25% institute)
            SET v_teacher_points = NEW.amount * 0.75;
            SET v_institute_points = NEW.amount * 0.25;
            
            -- Create wallet if doesn't exist
            INSERT IGNORE INTO teacher_wallet (teacher_id, total_points, total_earned)
            VALUES (v_teacher_id, 0.00, 0.00);
            
            -- Update teacher wallet
            UPDATE teacher_wallet
            SET total_points = total_points + v_teacher_points,
                total_earned = total_earned + v_teacher_points
            WHERE teacher_id = v_teacher_id;
            
            -- Update institute wallet
            UPDATE institute_wallet
            SET total_points = total_points + v_institute_points,
                total_earned = total_earned + v_institute_points
            WHERE id = 1;
            
            -- Log transaction
            INSERT INTO payment_transactions (
                payment_type, payment_id, teacher_id, student_id,
                total_amount, teacher_points, institute_points, transaction_status
            ) VALUES (
                'monthly', NEW.id, v_teacher_id, v_student_id,
                NEW.amount, v_teacher_points, v_institute_points, 'completed'
            );
        END IF;
        
    -- Handle refunds (reverse points)
    ELSEIF NEW.payment_status = 'refunded' AND OLD.payment_status = 'paid' THEN
        
        -- Find the original transaction
        SELECT teacher_id, student_id, teacher_points, institute_points
        INTO v_teacher_id, v_student_id, v_teacher_points, v_institute_points
        FROM payment_transactions
        WHERE payment_type = 'monthly' AND payment_id = NEW.id AND transaction_status = 'completed'
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            -- Reverse teacher wallet
            UPDATE teacher_wallet
            SET total_points = total_points - v_teacher_points
            WHERE teacher_id = v_teacher_id;
            
            -- Reverse institute wallet
            UPDATE institute_wallet
            SET total_points = total_points - v_institute_points
            WHERE id = 1;
            
            -- Mark transaction as reversed
            UPDATE payment_transactions
            SET transaction_status = 'reversed'
            WHERE payment_type = 'monthly' AND payment_id = NEW.id;
        END IF;
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- Setup Complete
-- ============================================================

SELECT 'Teacher Payment Points System Setup Complete!' AS Status;
