-- ============================================================
-- LMS Database Performance Indexes
-- Run once on your VPS via:
--   mysql -u root -p lms < db_indexes.sql
-- Or paste into phpMyAdmin > SQL tab
-- All statements use IF NOT EXISTS so they are safe to re-run.
-- ============================================================

USE lms;

-- ── users table ──────────────────────────────────────────────
-- Login query searches by user_id OR mobile_number
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_mobile (mobile_number);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_role (role);
-- Session token validation
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_session_token (session_token(64));

-- ── student_enrollment ────────────────────────────────────────
-- Dashboard fetches all active enrollments for a student
ALTER TABLE student_enrollment ADD INDEX IF NOT EXISTS idx_enrollment_student_status (student_id, status);
-- Enrollment lookup by subject
ALTER TABLE student_enrollment ADD INDEX IF NOT EXISTS idx_enrollment_stream_subject (stream_subject_id);

-- ── enrollment_payments ───────────────────────────────────────
-- Dashboard checks payment status for enrollment IDs
ALTER TABLE enrollment_payments ADD INDEX IF NOT EXISTS idx_enr_payment_enrollment (student_enrollment_id);
ALTER TABLE enrollment_payments ADD INDEX IF NOT EXISTS idx_enr_payment_status (payment_status);

-- ── monthly_payments ──────────────────────────────────────────
-- Dashboard checks current month payment per enrollment
ALTER TABLE monthly_payments ADD INDEX IF NOT EXISTS idx_monthly_enrollment (student_enrollment_id);
ALTER TABLE monthly_payments ADD INDEX IF NOT EXISTS idx_monthly_month_year (month, year);
-- Composite: most efficient for the exact dashboard query
ALTER TABLE monthly_payments ADD INDEX IF NOT EXISTS idx_monthly_composite (student_enrollment_id, month, year);

-- ── teacher_assignments ───────────────────────────────────────
-- Dashboard home page joins on status = 'active'
ALTER TABLE teacher_assignments ADD INDEX IF NOT EXISTS idx_ta_status (status);
ALTER TABLE teacher_assignments ADD INDEX IF NOT EXISTS idx_ta_stream_subject (stream_subject_id);

-- ── al_exam_submissions ───────────────────────────────────────
-- check_al_redirection.php queries this on every student page load
-- (cached in session after first load, but still needs index for cold hits)
ALTER TABLE al_exam_submissions ADD INDEX IF NOT EXISTS idx_al_student (student_id);

-- ── courses ───────────────────────────────────────────────────
-- Dashboard loads all active courses ordered by created_at
ALTER TABLE courses ADD INDEX IF NOT EXISTS idx_courses_status (status);

SELECT 'All indexes applied successfully.' AS result;
