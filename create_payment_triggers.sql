-- ============================================================
-- Payment Triggers for Commission Split
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

SELECT 'Triggers created successfully!' AS Status;
