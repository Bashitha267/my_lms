-- SUBJECT WISE COMMISSION MIGRATION
-- Run this on your XAMPP MySQL database

-- 1. Add commission_rate to teacher_assignments
ALTER TABLE `teacher_assignments` ADD COLUMN `commission_rate` DECIMAL(5,2) DEFAULT 75.00 COMMENT 'Percentage the teacher receives for this specific class';

-- 2. Migrate existing global rates to assignments
UPDATE `teacher_assignments` ta
JOIN `users` u ON ta.teacher_id = u.user_id
SET ta.commission_rate = IFNULL(u.commission_rate, 75.00);

-- 3. Update the payment verification trigger to use assignment-level commission
DROP TRIGGER IF EXISTS `after_enrollment_payment_update`;

DELIMITER $$

CREATE TRIGGER `after_enrollment_payment_update`
AFTER UPDATE ON `enrollment_payments`
FOR EACH ROW
BEGIN
    DECLARE v_teacher_id VARCHAR(20);
    DECLARE v_student_id VARCHAR(20);
    DECLARE v_teacher_rate DECIMAL(5,2);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    -- Only process when status changes to 'paid' from non-paid
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        
        -- Get teacher, student and assignment-level commission rate
        SELECT ta.teacher_id, se.student_id, ta.commission_rate
        INTO v_teacher_id, v_student_id, v_teacher_rate
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
            AND se.academic_year = ta.academic_year
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            -- Calculate split based on assignment rate
            SET v_teacher_points = NEW.amount * (v_teacher_rate / 100);
            SET v_institute_points = NEW.amount * ((100 - v_teacher_rate) / 100);
            
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
            
            -- Log the transaction
            INSERT INTO payment_transactions 
            (payment_type, payment_id, teacher_id, student_id, total_amount, teacher_points, institute_points, commission_rate_teacher, commission_rate_institute, transaction_status)
            VALUES 
            ('enrollment', NEW.id, v_teacher_id, v_student_id, NEW.amount, v_teacher_points, v_institute_points, v_teacher_rate, (100 - v_teacher_rate), 'completed');
            
        END IF;
    END IF;
END$$

DELIMITER ;

-- 4. Update the monthly payment verification trigger
DROP TRIGGER IF EXISTS `after_monthly_payment_update`;

DELIMITER $$

CREATE TRIGGER `after_monthly_payment_update`
AFTER UPDATE ON `monthly_payments`
FOR EACH ROW
BEGIN
    DECLARE v_teacher_id VARCHAR(20);
    DECLARE v_student_id VARCHAR(20);
    DECLARE v_teacher_rate DECIMAL(5,2);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    -- Only process when status changes to 'paid' from non-paid
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        
        -- Get teacher, student and assignment-level commission rate
        SELECT ta.teacher_id, se.student_id, ta.commission_rate
        INTO v_teacher_id, v_student_id, v_teacher_rate
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
            AND se.academic_year = ta.academic_year
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            -- Calculate split based on assignment rate
            SET v_teacher_points = NEW.amount * (v_teacher_rate / 100);
            SET v_institute_points = NEW.amount * ((100 - v_teacher_rate) / 100);
            
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
            
            -- Log the transaction
            INSERT INTO payment_transactions 
            (payment_type, payment_id, teacher_id, student_id, total_amount, teacher_points, institute_points, commission_rate_teacher, commission_rate_institute, transaction_status)
            VALUES 
            ('monthly', NEW.id, v_teacher_id, v_student_id, NEW.amount, v_teacher_points, v_institute_points, v_teacher_rate, (100 - v_teacher_rate), 'completed');
            
        END IF;
    END IF;
END$$

DELIMITER ;
