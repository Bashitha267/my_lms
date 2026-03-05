-- ============================================================
-- LMS Database Complete Schema
-- Generated: March 5, 2026
-- This file contains all table structures for the LMS system
-- NO DATA INCLUDED - Schema only for database restoration
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================
-- Database: `lms`
-- ============================================================

-- Drop existing tables (in reverse dependency order)
DROP TABLE IF EXISTS `zoom_participants`;
DROP TABLE IF EXISTS `zoom_class_files`;
DROP TABLE IF EXISTS `zoom_chat_messages`;
DROP TABLE IF EXISTS `zoom_classes`;
DROP TABLE IF EXISTS `video_watch_log`;
DROP TABLE IF EXISTS `teacher_education`;
DROP TABLE IF EXISTS `teacher_wallet`;
DROP TABLE IF EXISTS `teacher_assignments`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `student_enrollment`;
DROP TABLE IF EXISTS `student_answers`;
DROP TABLE IF EXISTS `stream_subjects`;
DROP TABLE IF EXISTS `recording_files`;
DROP TABLE IF EXISTS `recordings`;
DROP TABLE IF EXISTS `question_images`;
DROP TABLE IF EXISTS `question_answers`;
DROP TABLE IF EXISTS `physical_classes`;
DROP TABLE IF EXISTS `payment_transactions`;
DROP TABLE IF EXISTS `monthly_payments`;
DROP TABLE IF EXISTS `live_class_participants`;
DROP TABLE IF EXISTS `institute_wallet`;
DROP TABLE IF EXISTS `instructor_requests`;
DROP TABLE IF EXISTS `instructor_subjects`;
DROP TABLE IF EXISTS `exam_questions`;
DROP TABLE IF EXISTS `exam_attempts`;
DROP TABLE IF EXISTS `exams`;
DROP TABLE IF EXISTS `enrollment_payments`;
DROP TABLE IF EXISTS `enrollment_fees`;
DROP TABLE IF EXISTS `course_uploads`;
DROP TABLE IF EXISTS `course_recordings`;
DROP TABLE IF EXISTS `course_payments`;
DROP TABLE IF EXISTS `course_enrollments`;
DROP TABLE IF EXISTS `course_chats`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `al_exam_submissions`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `streams`;
DROP TABLE IF EXISTS `users`;

-- ============================================================
-- Table structure for table `users`
-- Core table - must be created first as many tables reference it
-- ============================================================

CREATE TABLE `users` (
  `user_id` varchar(20) NOT NULL COMMENT 'Unique user ID (e.g., stu_0001, tea_1000, adm_1000)',
  `email` varchar(100) NOT NULL COMMENT 'Email address',
  `password` varchar(255) NOT NULL COMMENT 'Hashed password',
  `role` enum('student','teacher','instructor','admin') NOT NULL DEFAULT 'student' COMMENT 'User role: student, teacher, instructor, admin',
  `first_name` varchar(100) DEFAULT NULL COMMENT 'First name',
  `second_name` varchar(100) DEFAULT NULL COMMENT 'Last name',
  `dob` date DEFAULT NULL COMMENT 'Date of birth',
  `school_name` varchar(200) DEFAULT NULL COMMENT 'School name',
  `exam_year` int(4) DEFAULT NULL COMMENT 'Exam year (e.g., 2024)',
  `closest_town` varchar(100) DEFAULT NULL COMMENT 'Closest town',
  `district` varchar(100) DEFAULT NULL COMMENT 'District',
  `address` text DEFAULT NULL COMMENT 'Full address',
  `nic_no` varchar(20) DEFAULT NULL COMMENT 'NIC number',
  `mobile_number` varchar(20) DEFAULT NULL COMMENT 'Mobile phone number',
  `whatsapp_number` varchar(20) DEFAULT NULL COMMENT 'WhatsApp number',
  `gender` enum('male','female') DEFAULT NULL COMMENT 'Gender',
  `profile_picture` varchar(255) DEFAULT NULL COMMENT 'Path to profile picture',
  `registering_date` date NOT NULL COMMENT 'Date of registration',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Account status: 1=active, 0=inactive',
  `approved` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Approval status: 1=approved, 0=not approved',
  `verification_method` varchar(20) DEFAULT 'none' COMMENT 'Verification method: nic, mobile, none',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  `session_token` varchar(64) DEFAULT NULL COMMENT 'Session token for single login',
  `session_created_at` datetime DEFAULT NULL COMMENT 'Session creation timestamp',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `mobile_number` (`mobile_number`),
  UNIQUE KEY `whatsapp_number` (`whatsapp_number`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_approved` (`approved`),
  KEY `idx_nic_no` (`nic_no`),
  KEY `idx_mobile_number` (`mobile_number`),
  KEY `idx_session_token` (`session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users table for LMS system';

-- ============================================================
-- Table structure for table `streams`
-- Stores grades/categories (e.g., "Grade 6", "A/L Science")
-- ============================================================

CREATE TABLE `streams` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `name` varchar(100) NOT NULL COMMENT 'Stream name (e.g., "Grade 6", "Grade 7", "A/L Science")',
  `description` text DEFAULT NULL COMMENT 'Optional description of the stream',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the grades or categories';

-- ============================================================
-- Table structure for table `subjects`
-- Stores subject names
-- ============================================================

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `name` varchar(100) NOT NULL COMMENT 'Subject name (e.g., "Science", "Mathematics", "English")',
  `code` varchar(20) DEFAULT NULL COMMENT 'Optional subject code (e.g., "SCI", "MATH")',
  `description` text DEFAULT NULL COMMENT 'Optional description of the subject',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the subject names';

-- ============================================================
-- Table structure for table `stream_subjects`
-- Defines which subjects exist in which grades (The Offering)
-- ============================================================

CREATE TABLE `stream_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `stream_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to streams.id',
  `subject_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to subjects.id',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_stream_subject` (`stream_id`,`subject_id`),
  KEY `idx_stream_id` (`stream_id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_stream_subjects_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stream_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines which subjects exist in which grades (The Offering)';

-- ============================================================
-- Table structure for table `teacher_assignments`
-- Links teachers to specific stream-subject offerings
-- ============================================================

CREATE TABLE `teacher_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `teacher_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.id (where role = teacher)',
  `stream_subject_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to stream_subjects.id',
  `academic_year` int(4) NOT NULL COMMENT 'Academic year (e.g., 2025, 2026) - allows same teacher to teach same subject for different batches',
  `batch_name` varchar(50) DEFAULT NULL COMMENT 'Optional batch identifier (e.g., "Batch A", "Morning Batch", "2025-2026")',
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active' COMMENT 'Assignment status',
  `assigned_date` date DEFAULT NULL COMMENT 'Date when teacher was assigned',
  `start_date` date DEFAULT NULL COMMENT 'Start date of the assignment',
  `end_date` date DEFAULT NULL COMMENT 'End date of the assignment',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about the assignment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  `cover_image` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_stream_subject_year` (`teacher_id`,`stream_subject_id`,`academic_year`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_stream_subject_id` (`stream_subject_id`),
  KEY `idx_academic_year` (`academic_year`),
  KEY `idx_status` (`status`),
  KEY `idx_teacher_year` (`teacher_id`,`academic_year`),
  CONSTRAINT `fk_teacher_assignments_stream_subject` FOREIGN KEY (`stream_subject_id`) REFERENCES `stream_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_assignments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links teachers to specific stream-subject offerings with academic year support';

-- ============================================================
-- Table structure for table `teacher_education`
-- Stores education details for teachers
-- ============================================================

CREATE TABLE `teacher_education` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `teacher_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = teacher)',
  `qualification` varchar(200) NOT NULL COMMENT 'Qualification name (e.g., "B.Sc. in Mathematics", "M.Ed.", "Ph.D. in Physics")',
  `institution` varchar(200) DEFAULT NULL COMMENT 'Institution name where qualification was obtained',
  `year_obtained` int(4) DEFAULT NULL COMMENT 'Year when qualification was obtained',
  `field_of_study` varchar(200) DEFAULT NULL COMMENT 'Field of study or specialization',
  `grade_or_class` varchar(50) DEFAULT NULL COMMENT 'Grade/Class obtained (e.g., "First Class", "Distinction", "A+")',
  `certificate_path` varchar(255) DEFAULT NULL COMMENT 'Path to certificate document if uploaded',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_teacher_id` (`teacher_id`),
  CONSTRAINT `fk_teacher_education_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores education details for teachers';

-- ============================================================
-- Table structure for table `student_enrollment`
-- Links students to specific stream-subject enrollments
-- ============================================================

CREATE TABLE `student_enrollment` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `student_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = student)',
  `stream_subject_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to stream_subjects.id',
  `academic_year` int(4) NOT NULL COMMENT 'Academic year (e.g., 2025, 2026)',
  `batch_name` varchar(50) DEFAULT NULL COMMENT 'Optional batch identifier',
  `enrolled_date` date NOT NULL COMMENT 'Date when student enrolled',
  `status` enum('active','inactive','completed','dropped') NOT NULL DEFAULT 'active' COMMENT 'Enrollment status',
  `payment_status` enum('pending','paid','partial','refunded') NOT NULL DEFAULT 'pending' COMMENT 'Payment status',
  `payment_method` varchar(50) DEFAULT NULL COMMENT 'Payment method used (e.g., bank_transfer, card, cash, mobile_payment)',
  `payment_date` date DEFAULT NULL COMMENT 'Date of payment',
  `payment_amount` decimal(10,2) DEFAULT NULL COMMENT 'Amount paid',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about the enrollment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_stream_subject_year` (`student_id`,`stream_subject_id`,`academic_year`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_stream_subject_id` (`stream_subject_id`),
  KEY `idx_academic_year` (`academic_year`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `fk_student_enrollment_stream_subject` FOREIGN KEY (`stream_subject_id`) REFERENCES `stream_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links students to specific stream-subject enrollments';

-- ============================================================
-- Table structure for table `enrollment_fees`
-- Stores enrollment and monthly fee settings per teacher assignment
-- ============================================================

CREATE TABLE `enrollment_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `teacher_assignment_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to teacher_assignments.id',
  `enrollment_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'One-time enrollment fee',
  `monthly_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'Monthly subscription fee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_assignment_fee` (`teacher_assignment_id`),
  KEY `idx_teacher_assignment_id` (`teacher_assignment_id`),
  CONSTRAINT `fk_enrollment_fee_assignment` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores enrollment and monthly fee settings per teacher assignment';

-- ============================================================
-- Table structure for table `enrollment_payments`
-- Stores enrollment payment transactions
-- ============================================================

CREATE TABLE `enrollment_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `student_enrollment_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to student_enrollment.id',
  `amount` decimal(10,2) NOT NULL COMMENT 'Payment amount',
  `payment_method` enum('card','bank_transfer','cash','mobile_payment') NOT NULL COMMENT 'Payment method',
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending' COMMENT 'Payment status',
  `payment_date` date DEFAULT NULL COMMENT 'Date when payment was made',
  `card_number` varchar(50) DEFAULT NULL COMMENT 'Last 4 digits of card (for card payments)',
  `receipt_path` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded receipt (for bank transfers)',
  `receipt_type` enum('image','pdf') DEFAULT NULL COMMENT 'Type of receipt file',
  `verified_by` varchar(20) DEFAULT NULL COMMENT 'Admin user_id who verified the payment',
  `verified_at` timestamp NULL DEFAULT NULL COMMENT 'When payment was verified',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  KEY `fk_enrollment_payment_verified_by` (`verified_by`),
  KEY `idx_student_enrollment_id` (`student_enrollment_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `fk_enrollment_payment_enrollment` FOREIGN KEY (`student_enrollment_id`) REFERENCES `student_enrollment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enrollment_payment_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores enrollment payment transactions';

-- ============================================================
-- Table structure for table `monthly_payments`
-- Stores monthly payment transactions
-- ============================================================

CREATE TABLE `monthly_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `student_enrollment_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to student_enrollment.id',
  `month` int(2) NOT NULL COMMENT 'Month (1-12)',
  `year` int(4) NOT NULL COMMENT 'Year (e.g., 2025)',
  `amount` decimal(10,2) NOT NULL COMMENT 'Payment amount',
  `payment_method` enum('card','bank_transfer','cash','mobile_payment') NOT NULL COMMENT 'Payment method',
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending' COMMENT 'Payment status',
  `payment_date` date DEFAULT NULL COMMENT 'Date when payment was made',
  `card_number` varchar(50) DEFAULT NULL COMMENT 'Last 4 digits of card (for card payments)',
  `receipt_path` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded receipt (for bank transfers)',
  `receipt_type` enum('image','pdf') DEFAULT NULL COMMENT 'Type of receipt file',
  `verified_by` varchar(20) DEFAULT NULL COMMENT 'Admin user_id who verified the payment',
  `verified_at` timestamp NULL DEFAULT NULL COMMENT 'When payment was verified',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment_month_year` (`student_enrollment_id`,`month`,`year`),
  KEY `fk_monthly_payment_verified_by` (`verified_by`),
  KEY `idx_student_enrollment_id` (`student_enrollment_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_month_year` (`month`,`year`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `fk_monthly_payment_enrollment` FOREIGN KEY (`student_enrollment_id`) REFERENCES `student_enrollment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_monthly_payment_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores monthly payment transactions';

-- ============================================================
-- Table structure for table `recordings`
-- Stores YouTube video recordings linked to teacher assignments
-- ============================================================

CREATE TABLE `recordings` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `teacher_assignment_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to teacher_assignments.id',
  `is_live` tinyint(1) DEFAULT 0 COMMENT '0 = Uploaded Video, 1 = Live Stream',
  `title` varchar(255) NOT NULL COMMENT 'Video title',
  `description` text DEFAULT NULL COMMENT 'Video description',
  `youtube_video_id` varchar(20) NOT NULL COMMENT 'YouTube video ID extracted from URL',
  `youtube_url` varchar(500) DEFAULT NULL COMMENT 'Original YouTube URL',
  `duration` varchar(20) DEFAULT NULL COMMENT 'Video duration (e.g., "10:30")',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT 'YouTube thumbnail URL',
  `view_count` int(11) DEFAULT 0 COMMENT 'View count',
  `free_video` tinyint(1) DEFAULT 0 COMMENT 'Whether this video is free to watch (1 = free, 0 = requires payment)',
  `watch_limit` int(11) NOT NULL DEFAULT 3 COMMENT 'Maximum number of times a student can watch this video (0 = unlimited)',
  `status` enum('active','inactive','pending','scheduled','ongoing','ended','cancelled') NOT NULL DEFAULT 'active',
  `scheduled_start_time` datetime DEFAULT NULL,
  `actual_start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_teacher_assignment_id` (`teacher_assignment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_youtube_video_id` (`youtube_video_id`),
  KEY `idx_is_live` (`is_live`),
  KEY `idx_scheduled` (`scheduled_start_time`),
  CONSTRAINT `fk_recordings_teacher_assignment` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores YouTube video recordings linked to teacher assignments';

-- ============================================================
-- Table structure for table `recording_files`
-- Stores file uploads for recordings
-- ============================================================

CREATE TABLE `recording_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recording_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id',
  `uploaded_by` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (who uploaded the file)',
  `file_name` varchar(255) NOT NULL COMMENT 'Original file name',
  `file_path` varchar(500) NOT NULL COMMENT 'Path to stored file',
  `file_size` bigint(20) NOT NULL COMMENT 'File size in bytes',
  `file_type` varchar(100) DEFAULT NULL COMMENT 'MIME type of the file',
  `file_extension` varchar(10) DEFAULT NULL COMMENT 'File extension',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When file was uploaded',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=deleted',
  PRIMARY KEY (`id`),
  KEY `idx_recording_id` (`recording_id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_recording_files_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_recording_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores file uploads for recordings';

-- ============================================================
-- Table structure for table `video_watch_log`
-- Tracks video watch history for students
-- ============================================================

CREATE TABLE `video_watch_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `recording_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id',
  `student_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = student)',
  `watched_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp when video was watched',
  `watch_duration` int(11) DEFAULT NULL COMMENT 'Duration watched in seconds (optional, for future use)',
  PRIMARY KEY (`id`),
  KEY `idx_recording_id` (`recording_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_recording_student` (`recording_id`,`student_id`),
  KEY `idx_watched_at` (`watched_at`),
  CONSTRAINT `fk_video_watch_log_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_video_watch_log_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks video watch history for students';

-- ============================================================
-- Table structure for table `chat_messages`
-- Chat messages between students and teachers for recordings
-- ============================================================

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `recording_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id - Chat context (video being watched)',
  `sender_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id - Message sender (student or teacher)',
  `sender_role` enum('student','teacher') NOT NULL COMMENT 'Role of the sender (for quick access)',
  `message` text NOT NULL COMMENT 'Message content',
  `video_timestamp` int(11) DEFAULT NULL COMMENT 'Seconds into the video when message was sent',
  `status` enum('sent','delivered','read') NOT NULL DEFAULT 'sent' COMMENT 'Message status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Message creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Message update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_recording_id` (`recording_id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_chat_messages_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chat messages between students and teachers for recordings';

-- ============================================================
-- Table structure for table `live_class_participants`
-- Tracks students participating in live classes
-- ============================================================

CREATE TABLE `live_class_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recording_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id (live class)',
  `student_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (student)',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When student joined the live class',
  `left_at` timestamp NULL DEFAULT NULL COMMENT 'When student left the live class (NULL = still online)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participant` (`recording_id`,`student_id`),
  KEY `idx_recording_status` (`recording_id`,`left_at`),
  KEY `idx_student` (`student_id`),
  CONSTRAINT `fk_participants_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_participants_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table structure for table `physical_classes`
-- Stores physical class schedules
-- ============================================================

CREATE TABLE `physical_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_assignment_id` int(11) NOT NULL,
  `teacher_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `class_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','ongoing','ended','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `attendance`
-- Tracks student attendance for physical classes
-- ============================================================

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `physical_class_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `attended_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `physical_class_id` (`physical_class_id`,`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table structure for table `exams`
-- Stores exam information
-- ============================================================

CREATE TABLE `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `teacher_id` varchar(20) NOT NULL COMMENT 'FK to users.user_id',
  `subject_id` int(11) NOT NULL COMMENT 'FK to subjects.id',
  `title` varchar(255) NOT NULL COMMENT 'Exam title',
  `duration_minutes` int(11) NOT NULL DEFAULT 60 COMMENT 'Duration in minutes',
  `deadline` datetime NOT NULL COMMENT 'Exam deadline',
  `is_published` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=draft, 1=published',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Exam status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_exams_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_exams_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores exam information';

-- ============================================================
-- Table structure for table `exam_questions`
-- Stores exam questions
-- ============================================================

CREATE TABLE `exam_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `exam_id` int(11) NOT NULL COMMENT 'FK to exams.id',
  `question_text` text NOT NULL COMMENT 'Question text content',
  `question_image` varchar(255) DEFAULT NULL COMMENT 'Optional question image path',
  `question_type` enum('single','multiple') NOT NULL DEFAULT 'single' COMMENT 'single=one correct answer, multiple=multiple correct answers',
  `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'Question display order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_exam_id` (`exam_id`),
  KEY `idx_order_index` (`order_index`),
  CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores exam questions';

-- ============================================================
-- Table structure for table `question_answers`
-- Stores question answer options
-- ============================================================

CREATE TABLE `question_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `question_id` int(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `answer_text` text NOT NULL COMMENT 'Answer text content',
  `answer_image` varchar(255) DEFAULT NULL COMMENT 'Optional answer image path',
  `is_correct` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=wrong, 1=correct',
  `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'Answer display order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_is_correct` (`is_correct`),
  KEY `idx_order_index` (`order_index`),
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores question answer options';

-- ============================================================
-- Table structure for table `question_images`
-- Stores multiple images per question
-- ============================================================

CREATE TABLE `question_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `question_id` int(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `image_path` varchar(255) NOT NULL COMMENT 'Image file path',
  `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'Image display order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_order_index` (`order_index`),
  CONSTRAINT `fk_images_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores multiple images per question';

-- ============================================================
-- Table structure for table `exam_attempts`
-- Stores student exam attempts
-- ============================================================

CREATE TABLE `exam_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `exam_id` int(11) NOT NULL COMMENT 'FK to exams.id',
  `student_id` varchar(20) NOT NULL COMMENT 'FK to users.user_id',
  `start_time` datetime NOT NULL COMMENT 'When student started the exam',
  `end_time` datetime DEFAULT NULL COMMENT 'When student submitted or time expired',
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Score percentage',
  `correct_count` int(11) DEFAULT 0 COMMENT 'Number of correct answers',
  `total_questions` int(11) DEFAULT 0 COMMENT 'Total questions in exam',
  `status` enum('in_progress','completed','expired') NOT NULL DEFAULT 'in_progress' COMMENT 'Attempt status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_exam` (`exam_id`,`student_id`),
  KEY `idx_exam_id` (`exam_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_attempts_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores student exam attempts';

-- ============================================================
-- Table structure for table `student_answers`
-- Stores student selected answers
-- ============================================================

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `attempt_id` int(11) NOT NULL COMMENT 'FK to exam_attempts.id',
  `question_id` int(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `answer_id` int(11) NOT NULL COMMENT 'FK to question_answers.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  PRIMARY KEY (`id`),
  KEY `fk_student_answers_answer` (`answer_id`),
  KEY `idx_attempt_id` (`attempt_id`),
  KEY `idx_question_id` (`question_id`),
  CONSTRAINT `fk_student_answers_answer` FOREIGN KEY (`answer_id`) REFERENCES `question_answers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores student selected answers';

-- ============================================================
-- Table structure for table `courses`
-- Stores online courses (separate from stream-based subjects)
-- ============================================================

CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `course_enrollments`
-- Stores student enrollments in courses
-- ============================================================

CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `enrolled_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `payment_status` enum('paid','pending','free') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`course_id`,`student_id`),
  CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `course_recordings`
-- Stores video recordings for courses
-- ============================================================

CREATE TABLE `course_recordings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(255) NOT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `is_free` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `course_recordings_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `course_chats`
-- Stores chat messages for course recordings
-- ============================================================

CREATE TABLE `course_chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_recording_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_recording_id` (`course_recording_id`),
  CONSTRAINT `course_chats_ibfk_1` FOREIGN KEY (`course_recording_id`) REFERENCES `course_recordings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `course_uploads`
-- Stores uploaded files for courses
-- ============================================================

CREATE TABLE `course_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `course_uploads_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `course_payments`
-- Stores payment records for course enrollments
-- ============================================================

CREATE TABLE `course_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_enrollment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `receipt_path` varchar(255) DEFAULT NULL,
  `receipt_type` varchar(20) DEFAULT NULL,
  `card_number` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cp_enrollment` (`course_enrollment_id`),
  KEY `idx_cp_status` (`payment_status`),
  CONSTRAINT `course_payments_ibfk_1` FOREIGN KEY (`course_enrollment_id`) REFERENCES `course_enrollments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `zoom_classes`
-- Stores Zoom class information
-- ============================================================

CREATE TABLE `zoom_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_assignment_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `zoom_meeting_link` varchar(500) NOT NULL,
  `zoom_meeting_id` varchar(255) DEFAULT NULL,
  `zoom_passcode` varchar(100) DEFAULT NULL,
  `scheduled_start_time` datetime NOT NULL,
  `actual_start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` enum('scheduled','ongoing','ended','cancelled') DEFAULT 'scheduled',
  `free_class` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher_assignment` (`teacher_assignment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_time` (`scheduled_start_time`),
  CONSTRAINT `zoom_classes_ibfk_1` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table structure for table `zoom_participants`
-- Tracks participants in Zoom classes
-- ============================================================

CREATE TABLE `zoom_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zoom_class_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `join_time` datetime NOT NULL,
  `leave_time` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_zoom_class` (`zoom_class_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `zoom_participants_ibfk_1` FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zoom_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table structure for table `zoom_chat_messages`
-- Stores chat messages for Zoom classes
-- ============================================================

CREATE TABLE `zoom_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zoom_class_id` int(11) NOT NULL,
  `sender_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_zoom_class` (`zoom_class_id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_sent_at` (`sent_at`),
  CONSTRAINT `zoom_chat_messages_ibfk_1` FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zoom_chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table structure for table `zoom_class_files`
-- Stores files uploaded during Zoom classes
-- ============================================================

CREATE TABLE `zoom_class_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zoom_class_id` int(11) NOT NULL,
  `uploader_id` varchar(50) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_zoom_class` (`zoom_class_id`),
  KEY `idx_uploader` (`uploader_id`),
  CONSTRAINT `zoom_class_files_ibfk_1` FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zoom_class_files_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table structure for table `system_settings`
-- Stores system configuration settings
-- ============================================================

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','image','number','boolean','json') DEFAULT 'text',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table structure for table `teacher_wallet`
-- Stores teacher wallet/payment points
-- ============================================================

CREATE TABLE `teacher_wallet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` varchar(20) NOT NULL,
  `total_points` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_earned` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_id` (`teacher_id`),
  KEY `idx_teacher_points` (`teacher_id`,`total_points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `institute_wallet`
-- Stores institute wallet/payment points
-- ============================================================

CREATE TABLE `institute_wallet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `total_points` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_earned` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Initialize institute wallet with single row
INSERT INTO `institute_wallet` (`total_points`, `total_earned`) 
VALUES (0.00, 0.00);

-- ============================================================
-- Table structure for table `payment_transactions`
-- Logs all payment transactions with commission split
-- ============================================================

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_type` enum('enrollment','monthly') NOT NULL,
  `payment_id` int(11) NOT NULL,
  `teacher_id` varchar(20) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `teacher_points` decimal(10,2) NOT NULL,
  `institute_points` decimal(10,2) NOT NULL,
  `commission_rate_teacher` decimal(5,2) DEFAULT 75.00,
  `commission_rate_institute` decimal(5,2) DEFAULT 25.00,
  `transaction_status` enum('pending','completed','reversed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payment_tracking` (`payment_type`,`payment_id`),
  KEY `idx_teacher_transactions` (`teacher_id`,`created_at`),
  KEY `idx_transaction_status` (`transaction_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table structure for table `al_exam_submissions`
-- Stores A/L exam details submitted by students
-- ============================================================

CREATE TABLE `al_exam_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `student_id` varchar(20) NOT NULL COMMENT 'FK to users.user_id',
  `subject_1` varchar(100) NOT NULL,
  `result_1` varchar(5) DEFAULT NULL,
  `subject_2` varchar(100) NOT NULL,
  `result_2` varchar(5) DEFAULT NULL,
  `subject_3` varchar(100) NOT NULL,
  `result_3` varchar(5) DEFAULT NULL,
  `index_number` varchar(50) DEFAULT NULL,
  `district` varchar(50) NOT NULL,
  `stream` varchar(50) DEFAULT NULL COMMENT 'Arts, Commerce, Science, Tech',
  `photo_path` varchar(255) DEFAULT NULL,
  `agreed_to_publish` tinyint(1) DEFAULT 0,
  `results_submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  UNIQUE KEY `unique_student_submission` (`student_id`),
  INDEX `idx_district` (`district`),
  CONSTRAINT `fk_al_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores A/L exam details submitted by students';

-- ============================================================
-- Table structure for table `instructor_subjects`
-- Links instructors to subjects (Many-to-Many)
-- ============================================================

CREATE TABLE `instructor_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instructor_id` varchar(20) NOT NULL COMMENT 'Link to users.user_id',
  `subject_id` int(11) NOT NULL COMMENT 'Link to subjects.id',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_instructor_subject` (`instructor_id`,`subject_id`),
  KEY `idx_instructor_id` (`instructor_id`),
  KEY `idx_subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table structure for table `instructor_requests`
-- Tracks student requests for instructors
-- ============================================================

CREATE TABLE `instructor_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL COMMENT 'Link to users.user_id',
  `subject_id` int(11) NOT NULL COMMENT 'Link to subjects.id',
  `status` enum('pending','accepted','completed','cancelled') NOT NULL DEFAULT 'pending',
  `accepted_by` varchar(20) DEFAULT NULL COMMENT 'Instructor who accepted the request',
  `accepted_at` datetime DEFAULT NULL,
  `request_note` text DEFAULT NULL COMMENT 'Optional note from student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_accepted_by` (`accepted_by`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TRIGGERS
-- Payment triggers for commission split (75% teacher / 25% institute)
-- ============================================================

DROP TRIGGER IF EXISTS `after_enrollment_payment_update`;

DELIMITER $$

CREATE TRIGGER `after_enrollment_payment_update`
AFTER UPDATE ON `enrollment_payments`
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

DROP TRIGGER IF EXISTS `after_monthly_payment_update`;

DELIMITER $$

CREATE TRIGGER `after_monthly_payment_update`
AFTER UPDATE ON `monthly_payments`
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
-- End of Schema
-- ============================================================

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
