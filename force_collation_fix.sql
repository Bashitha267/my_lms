
SET FOREIGN_KEY_CHECKS = 0;

-- Set Database-wide collation
ALTER DATABASE lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Force convert every single table to the correct charset and collation
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `student_enrollment` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `enrollment_payments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `monthly_payments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `course_enrollments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `course_payments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `teacher_assignments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `instructor_payments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `instructor_requests` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `instructor_request_acceptances` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `teacher_wallet` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `institute_wallet` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `payment_transactions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `subjects` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `streams` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `stream_subjects` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Now re-create triggers with full explicit collation
DROP TRIGGER IF EXISTS `after_enrollment_payment_update`;
DELIMITER $$
CREATE TRIGGER `after_enrollment_payment_update`
AFTER UPDATE ON `enrollment_payments` FOR EACH ROW
BEGIN
    DECLARE v_teacher_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_student_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_teacher_rate DECIMAL(5,2);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        SELECT ta.teacher_id, se.student_id, ta.commission_rate
        INTO v_teacher_id, v_student_id, v_teacher_rate
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
             AND se.academic_year = ta.academic_year
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            SET v_teacher_points = NEW.amount * (v_teacher_rate / 100);
            SET v_institute_points = NEW.amount * ((100 - v_teacher_rate) / 100);
            INSERT IGNORE INTO teacher_wallet (teacher_id, total_points, total_earned) VALUES (v_teacher_id, 0.00, 0.00);
            UPDATE teacher_wallet SET total_points = total_points + v_teacher_points, total_earned = total_earned + v_teacher_points WHERE teacher_id = v_teacher_id;
            UPDATE institute_wallet SET total_points = total_points + v_institute_points, total_earned = total_earned + v_institute_points WHERE id = 1;
            INSERT INTO payment_transactions (payment_type, payment_id, teacher_id, student_id, total_amount, teacher_points, institute_points, commission_rate_teacher, commission_rate_institute, transaction_status)
            VALUES ('enrollment', NEW.id, v_teacher_id, v_student_id, NEW.amount, v_teacher_points, v_institute_points, v_teacher_rate, (100 - v_teacher_rate), 'completed');
        END IF;
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `after_monthly_payment_update`;
DELIMITER $$
CREATE TRIGGER `after_monthly_payment_update`
AFTER UPDATE ON `monthly_payments` FOR EACH ROW
BEGIN
    DECLARE v_teacher_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_student_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_teacher_rate DECIMAL(5,2);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        SELECT ta.teacher_id, se.student_id, ta.commission_rate
        INTO v_teacher_id, v_student_id, v_teacher_rate
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
             AND se.academic_year = ta.academic_year
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            SET v_teacher_points = NEW.amount * (v_teacher_rate / 100);
            SET v_institute_points = NEW.amount * ((100 - v_teacher_rate) / 100);
            INSERT IGNORE INTO teacher_wallet (teacher_id, total_points, total_earned) VALUES (v_teacher_id, 0.00, 0.00);
            UPDATE teacher_wallet SET total_points = total_points + v_teacher_points, total_earned = total_earned + v_teacher_points WHERE teacher_id = v_teacher_id;
            UPDATE institute_wallet SET total_points = total_points + v_institute_points, total_earned = total_earned + v_institute_points WHERE id = 1;
            INSERT INTO payment_transactions (payment_type, payment_id, teacher_id, student_id, total_amount, teacher_points, institute_points, commission_rate_teacher, commission_rate_institute, transaction_status)
            VALUES ('monthly', NEW.id, v_teacher_id, v_student_id, NEW.amount, v_teacher_points, v_institute_points, v_teacher_rate, (100 - v_teacher_rate), 'completed');
        END IF;
    END IF;
END$$
DELIMITER ;
