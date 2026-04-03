
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

DROP TRIGGER IF EXISTS `after_enrollment_payment_update`;

DELIMITER $$

CREATE TRIGGER `after_enrollment_payment_update`
AFTER UPDATE ON `enrollment_payments`
FOR EACH ROW
BEGIN
    DECLARE v_teacher_id VARCHAR(20) COLLATE utf8mb4_unicode_ci;
    DECLARE v_student_id VARCHAR(20) COLLATE utf8mb4_unicode_ci;
    DECLARE v_teacher_rate DECIMAL(5,2);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        SELECT ta.teacher_id, se.student_id, ta.commission_rate
        INTO v_teacher_id, v_student_id, v_teacher_rate
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
            AND se.academic_year COLLATE utf8mb4_unicode_ci = ta.academic_year COLLATE utf8mb4_unicode_ci
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            SET v_teacher_points = NEW.amount * (v_teacher_rate / 100);
            SET v_institute_points = NEW.amount * ((100 - v_teacher_rate) / 100);
            
            INSERT IGNORE INTO teacher_wallet (teacher_id, total_points, total_earned)
            VALUES (v_teacher_id, 0.00, 0.00);
            
            UPDATE teacher_wallet
            SET total_points = total_points + v_teacher_points,
                total_earned = total_earned + v_teacher_points
            WHERE teacher_id COLLATE utf8mb4_unicode_ci = v_teacher_id COLLATE utf8mb4_unicode_ci;
            
            UPDATE institute_wallet
            SET total_points = total_points + v_institute_points,
                total_earned = total_earned + v_institute_points
            WHERE id = 1;
            
            INSERT INTO payment_transactions 
            (payment_type, payment_id, teacher_id, student_id, total_amount, teacher_points, institute_points, commission_rate_teacher, commission_rate_institute, transaction_status)
            VALUES 
            ('enrollment', NEW.id, v_teacher_id, v_student_id, NEW.amount, v_teacher_points, v_institute_points, v_teacher_rate, (100 - v_teacher_rate), 'completed');
        END IF;
    END IF;
END$$

DELIMITER ;

DROP TRIGGER IF EXISTS `after_monthly_payment_update`;

DELIMITER $$

CREATE TRIGGER `after_monthly_payment_update`
AFTER UPDATE ON `monthly_payments`
FOR EACH ROW
BEGIN
    DECLARE v_teacher_id VARCHAR(20) COLLATE utf8mb4_unicode_ci;
    DECLARE v_student_id VARCHAR(20) COLLATE utf8mb4_unicode_ci;
    DECLARE v_teacher_rate DECIMAL(5,2);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        SELECT ta.teacher_id, se.student_id, ta.commission_rate
        INTO v_teacher_id, v_student_id, v_teacher_rate
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
            AND se.academic_year COLLATE utf8mb4_unicode_ci = ta.academic_year COLLATE utf8mb4_unicode_ci
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            SET v_teacher_points = NEW.amount * (v_teacher_rate / 100);
            SET v_institute_points = NEW.amount * ((100 - v_teacher_rate) / 100);
            
            INSERT IGNORE INTO teacher_wallet (teacher_id, total_points, total_earned)
            VALUES (v_teacher_id, 0.00, 0.00);
            
            UPDATE teacher_wallet
            SET total_points = total_points + v_teacher_points,
                total_earned = total_earned + v_teacher_points
            WHERE teacher_id COLLATE utf8mb4_unicode_ci = v_teacher_id COLLATE utf8mb4_unicode_ci;
            
            UPDATE institute_wallet
            SET total_points = total_points + v_institute_points,
                total_earned = total_earned + v_institute_points
            WHERE id = 1;
            
            INSERT INTO payment_transactions 
            (payment_type, payment_id, teacher_id, student_id, total_amount, teacher_points, institute_points, commission_rate_teacher, commission_rate_institute, transaction_status)
            VALUES 
            ('monthly', NEW.id, v_teacher_id, v_student_id, NEW.amount, v_teacher_points, v_institute_points, v_teacher_rate, (100 - v_teacher_rate), 'completed');
        END IF;
    END IF;
END$$

DELIMITER ;
