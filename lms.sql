-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 11, 2026 at 05:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lms`
--

-- --------------------------------------------------------

--
-- Table structure for table `al_exam_submissions`
--

CREATE TABLE `al_exam_submissions` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `district_rank` int(11) DEFAULT NULL,
  `island_rank` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores A/L exam details submitted by students';

--
-- Dumping data for table `al_exam_submissions`
--

INSERT INTO `al_exam_submissions` (`id`, `student_id`, `subject_1`, `result_1`, `subject_2`, `result_2`, `subject_3`, `result_3`, `index_number`, `district`, `stream`, `photo_path`, `agreed_to_publish`, `results_submitted_at`, `created_at`, `updated_at`, `district_rank`, `island_rank`) VALUES
(1, 'stu_1000', 'Combined Mathematics', 'A', 'Physics', 'A', 'Chemistry', 'A', '', 'Gampaha', NULL, 'uploads/al_photos/stu_1000_al_1772720811.jpg', 1, '2026-04-11 14:24:41', '2026-03-05 14:26:51', '2026-04-11 14:24:41', 25, 620);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `physical_class_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `attended_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_expenses`
--

CREATE TABLE `budget_expenses` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_fixed` tinyint(1) NOT NULL DEFAULT 0,
  `month` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_expenses`
--

INSERT INTO `budget_expenses` (`id`, `name`, `amount`, `is_fixed`, `month`, `year`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Whatsapp API', 1000.00, 1, NULL, NULL, '', '2026-03-19 14:55:36', '2026-03-19 14:55:36');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `recording_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id - Chat context (video being watched)',
  `sender_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id - Message sender (student or teacher)',
  `sender_role` enum('student','teacher') NOT NULL COMMENT 'Role of the sender (for quick access)',
  `message` text NOT NULL COMMENT 'Message content',
  `video_timestamp` int(11) DEFAULT NULL COMMENT 'Seconds into the video when message was sent',
  `status` enum('sent','delivered','read') NOT NULL DEFAULT 'sent' COMMENT 'Message status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Message creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Message update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chat messages between students and teachers for recordings';

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `recording_id`, `sender_id`, `sender_role`, `message`, `video_timestamp`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'tea_1000', 'teacher', 'hiiii', NULL, 'sent', '2026-03-05 14:04:40', '2026-03-05 14:04:40'),
(2, 1, 'stu_1000', 'student', 'hey', NULL, 'sent', '2026-03-05 14:05:22', '2026-03-05 14:05:22'),
(3, 1, 'tea_1000', 'teacher', 'helooo', NULL, 'sent', '2026-03-05 14:05:34', '2026-03-05 14:05:34'),
(4, 2, 'stu_1000', 'student', 'hello', NULL, 'sent', '2026-03-05 14:17:09', '2026-03-05 14:17:09');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `duration` varchar(100) DEFAULT NULL COMMENT 'e.g. 8 weeks, 20 hours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_chats`
--

CREATE TABLE `course_chats` (
  `id` int(11) NOT NULL,
  `course_recording_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `enrolled_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `payment_status` enum('paid','pending','free') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_payments`
--

CREATE TABLE `course_payments` (
  `id` int(11) NOT NULL,
  `course_enrollment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `receipt_path` varchar(255) DEFAULT NULL,
  `receipt_type` varchar(20) DEFAULT NULL,
  `card_number` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_recordings`
--

CREATE TABLE `course_recordings` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(255) NOT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `is_free` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_uploads`
--

CREATE TABLE `course_uploads` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_fees`
--

CREATE TABLE `enrollment_fees` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `teacher_assignment_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to teacher_assignments.id',
  `enrollment_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'One-time enrollment fee',
  `monthly_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'Monthly subscription fee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores enrollment and monthly fee settings per teacher assignment';

--
-- Dumping data for table `enrollment_fees`
--

INSERT INTO `enrollment_fees` (`id`, `teacher_assignment_id`, `enrollment_fee`, `monthly_fee`, `created_at`, `updated_at`) VALUES
(1, 1, 1000.00, 2000.00, '2026-03-05 13:56:52', '2026-03-05 13:56:52'),
(2, 2, 500.00, 1000.00, '2026-03-05 17:49:59', '2026-03-05 17:49:59'),
(3, 3, 1000.00, 1200.00, '2026-03-05 17:51:05', '2026-03-05 17:51:05'),
(4, 4, 0.00, 2500.00, '2026-03-28 10:25:18', '2026-03-28 10:25:18');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_payments`
--

CREATE TABLE `enrollment_payments` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores enrollment payment transactions';

--
-- Dumping data for table `enrollment_payments`
--

INSERT INTO `enrollment_payments` (`id`, `student_enrollment_id`, `amount`, `payment_method`, `payment_status`, `payment_date`, `card_number`, `receipt_path`, `receipt_type`, `verified_by`, `verified_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1000.00, 'bank_transfer', 'paid', NULL, NULL, 'uploads/payments/payment_stu_1000_1772719078.png', 'image', NULL, NULL, NULL, '2026-03-05 13:57:58', '2026-03-05 14:10:37'),
(2, 3, 0.00, '', 'paid', '2026-03-06', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-06 07:25:36', '2026-03-06 07:25:36');

--
-- Triggers `enrollment_payments`
--
DELIMITER $$
CREATE TRIGGER `after_enrollment_payment_update` AFTER UPDATE ON `enrollment_payments` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `teacher_id` varchar(20) NOT NULL COMMENT 'FK to users.user_id',
  `subject_id` int(11) NOT NULL COMMENT 'FK to subjects.id',
  `title` varchar(255) NOT NULL COMMENT 'Exam title',
  `duration_minutes` int(11) NOT NULL DEFAULT 60 COMMENT 'Duration in minutes',
  `deadline` datetime NOT NULL COMMENT 'Exam deadline',
  `is_published` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=draft, 1=published',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Exam status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores exam information';

-- --------------------------------------------------------

--
-- Table structure for table `exam_attempts`
--

CREATE TABLE `exam_attempts` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `exam_id` int(11) NOT NULL COMMENT 'FK to exams.id',
  `student_id` varchar(20) NOT NULL COMMENT 'FK to users.user_id',
  `start_time` datetime NOT NULL COMMENT 'When student started the exam',
  `end_time` datetime DEFAULT NULL COMMENT 'When student submitted or time expired',
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Score percentage',
  `correct_count` int(11) DEFAULT 0 COMMENT 'Number of correct answers',
  `total_questions` int(11) DEFAULT 0 COMMENT 'Total questions in exam',
  `status` enum('in_progress','completed','expired') NOT NULL DEFAULT 'in_progress' COMMENT 'Attempt status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores student exam attempts';

-- --------------------------------------------------------

--
-- Table structure for table `exam_questions`
--

CREATE TABLE `exam_questions` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `exam_id` int(11) NOT NULL COMMENT 'FK to exams.id',
  `question_text` text NOT NULL COMMENT 'Question text content',
  `question_image` varchar(255) DEFAULT NULL COMMENT 'Optional question image path',
  `question_type` enum('single','multiple') NOT NULL DEFAULT 'single' COMMENT 'single=one correct answer, multiple=multiple correct answers',
  `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'Question display order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores exam questions';

-- --------------------------------------------------------

--
-- Table structure for table `institute_settings`
--

CREATE TABLE `institute_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `institute_settings`
--

INSERT INTO `institute_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('commission_rate', '10', '2026-03-19 14:43:32');

-- --------------------------------------------------------

--
-- Table structure for table `institute_wallet`
--

CREATE TABLE `institute_wallet` (
  `id` int(11) NOT NULL,
  `total_points` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_earned` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `institute_wallet`
--

INSERT INTO `institute_wallet` (`id`, `total_points`, `total_earned`, `created_at`, `updated_at`) VALUES
(1, 1750.00, 1750.00, '2026-03-05 09:04:54', '2026-04-03 11:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_payments`
--

CREATE TABLE `instructor_payments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `instructor_id` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `receipt_path` varchar(255) NOT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  `verified_by` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructor_payments`
--

INSERT INTO `instructor_payments` (`id`, `request_id`, `student_id`, `instructor_id`, `amount`, `receipt_path`, `status`, `created_at`, `verified_at`, `verified_by`) VALUES
(1, 1, 'stu_1000', 'ins_1000', 0.00, 'uploads/instructor_payments/PAY_1_1774777476.png', 'verified', '2026-03-29 09:44:36', '2026-04-11 20:36:23', 'adm_1000'),
(2, 5, 'stu_1000', 'ins_1001', 1500.00, '', '', '2026-04-05 10:37:08', NULL, NULL),
(3, 9, 'stu_1000', 'ins_1001', 1500.00, 'uploads/instructor_payments/PAY_9_1775920160.png', 'verified', '2026-04-11 15:09:20', '2026-04-11 21:09:35', 'adm_1000'),
(4, 10, 'stu_1000', 'ins_1000', 0.00, 'uploads/instructor_payments/PAY_10_1775921957.png', 'verified', '2026-04-11 15:39:17', '2026-04-11 21:09:40', 'adm_1000');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_ratings`
--

CREATE TABLE `instructor_ratings` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `instructor_id` varchar(20) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `rating` int(1) NOT NULL,
  `review` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instructor_ratings`
--

INSERT INTO `instructor_ratings` (`id`, `request_id`, `instructor_id`, `student_id`, `rating`, `review`, `comment`, `created_at`) VALUES
(1, 10, 'ins_1000', 'stu_1000', 4, '', NULL, '2026-04-11 15:49:02');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_requests`
--

CREATE TABLE `instructor_requests` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL COMMENT 'Link to users.user_id',
  `subject_id` int(11) NOT NULL COMMENT 'Link to subjects.id',
  `session_date` date DEFAULT NULL,
  `status` enum('pending','accepted','payment_pending','paid','rejected','completed') NOT NULL DEFAULT 'pending',
  `payment_id` int(11) DEFAULT NULL,
  `accepted_by` varchar(20) DEFAULT NULL COMMENT 'Instructor who accepted the request',
  `accepted_at` datetime DEFAULT NULL,
  `request_note` text DEFAULT NULL COMMENT 'Optional note from student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `zoom_link` varchar(500) DEFAULT NULL,
  `zoom_meeting_id` varchar(100) DEFAULT NULL,
  `zoom_password` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructor_requests`
--

INSERT INTO `instructor_requests` (`id`, `student_id`, `subject_id`, `session_date`, `status`, `payment_id`, `accepted_by`, `accepted_at`, `request_note`, `created_at`, `updated_at`, `zoom_link`, `zoom_meeting_id`, `zoom_password`) VALUES
(1, 'stu_1000', 2, '2026-03-04', 'paid', NULL, 'ins_1000', '2026-03-29 15:12:02', 'heeee', '2026-03-29 09:41:53', '2026-04-11 15:01:17', NULL, NULL, NULL),
(2, 'stu_1000', 2, '2026-04-04', 'accepted', NULL, 'ins_1000', '2026-04-03 16:15:38', 'hiii', '2026-04-03 10:43:06', '2026-04-03 10:45:38', NULL, NULL, NULL),
(3, 'stu_1000', 2, '2026-04-09', 'accepted', NULL, 'ins_1001', '2026-04-03 16:24:55', 'helllo this is testng', '2026-04-03 10:54:45', '2026-04-03 10:54:55', NULL, NULL, NULL),
(4, 'stu_1000', 2, '2026-04-09', 'accepted', NULL, 'ins_1000', '2026-04-03 16:35:24', 'lllll', '2026-04-03 11:05:08', '2026-04-03 11:05:24', NULL, NULL, NULL),
(5, 'stu_1000', 2, '2026-04-06', 'paid', NULL, 'ins_1001', '2026-04-05 16:05:49', 'jooo', '2026-04-05 10:35:36', '2026-04-05 10:37:08', NULL, NULL, NULL),
(6, 'stu_1000', 2, '2026-04-15', 'accepted', NULL, 'ins_1000', '2026-04-05 16:09:41', '24555', '2026-04-05 10:39:31', '2026-04-05 10:39:41', NULL, NULL, NULL),
(7, 'stu_1000', 2, '2026-04-08', 'accepted', NULL, NULL, NULL, '445555', '2026-04-05 10:44:34', '2026-04-05 10:49:54', NULL, NULL, NULL),
(8, 'stu_1000', 2, '2026-04-05', 'accepted', NULL, 'ins_1001', '2026-04-05 16:51:05', 'last', '2026-04-05 11:20:26', '2026-04-05 11:21:05', NULL, NULL, NULL),
(9, 'stu_1000', 2, '2026-04-13', 'paid', 3, 'ins_1001', NULL, 'dddd', '2026-04-11 14:51:59', '2026-04-11 15:39:35', 'https://us05web.zoom.us/j/82902389662?pwd=vzVb0YfaYpikHzBfmPhIUwOWYIaD3a.1', '82902389662', 'vzVb0YfaYpikHzBfmPhIUwOWYIaD3a.1'),
(10, 'stu_1000', 2, '2026-04-30', 'completed', 4, 'ins_1000', NULL, 'ghjjjjjjjjjjkkkkkkkkkkkk', '2026-04-11 15:38:45', '2026-04-11 15:43:44', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `instructor_request_acceptances`
--

CREATE TABLE `instructor_request_acceptances` (
  `id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `instructor_id` varchar(50) DEFAULT NULL,
  `accepted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructor_request_acceptances`
--

INSERT INTO `instructor_request_acceptances` (`id`, `request_id`, `instructor_id`, `accepted_at`) VALUES
(1, 7, 'ins_1000', '2026-04-05 16:19:54'),
(2, 7, 'ins_1001', '2026-04-05 16:20:12'),
(3, 9, 'ins_1001', '2026-04-11 20:22:13'),
(5, 9, 'ins_1000', '2026-04-11 20:22:40'),
(6, 10, 'ins_1000', '2026-04-11 21:08:57');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_sessions`
--

CREATE TABLE `instructor_sessions` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `zoom_link` varchar(500) DEFAULT NULL,
  `zoom_meeting_id` varchar(100) DEFAULT NULL,
  `zoom_password` varchar(100) DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructor_sessions`
--

INSERT INTO `instructor_sessions` (`id`, `request_id`, `zoom_link`, `zoom_meeting_id`, `zoom_password`, `status`, `started_at`, `ended_at`, `created_at`, `updated_at`) VALUES
(1, 9, 'https://us05web.zoom.us/j/82902389662?pwd=vzVb0YfaYpikHzBfmPhIUwOWYIaD3a.1', '82902389662', 'vzVb0YfaYpikHzBfmPhIUwOWYIaD3a.1', 'completed', NULL, '2026-04-11 21:00:00', '2026-04-11 15:17:36', '2026-04-11 15:30:00'),
(4, 10, 'https://us05web.zoom.us/j/82902389662?pwd=vzVb0YfaYpikHzBfmPhIUwOWYIaD3a.1', 'gghhh', 'ffggg', 'completed', NULL, '2026-04-11 21:13:44', '2026-04-11 15:40:01', '2026-04-11 15:43:44');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_subjects`
--

CREATE TABLE `instructor_subjects` (
  `id` int(11) NOT NULL,
  `instructor_id` varchar(20) NOT NULL COMMENT 'Link to users.user_id',
  `subject_id` int(11) NOT NULL COMMENT 'Link to subjects.id',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructor_subjects`
--

INSERT INTO `instructor_subjects` (`id`, `instructor_id`, `subject_id`, `assigned_at`) VALUES
(1, 'ins_1000', 2, '2026-03-28 10:39:43'),
(3, 'ins_1001', 2, '2026-04-03 10:50:49');

-- --------------------------------------------------------

--
-- Table structure for table `live_classes`
--

CREATE TABLE `live_classes` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `instructor_id` varchar(20) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `zoom_link` varchar(500) NOT NULL,
  `status` enum('scheduled','ongoing','ended','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `live_class_participants`
--

CREATE TABLE `live_class_participants` (
  `id` int(11) NOT NULL,
  `recording_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id (live class)',
  `student_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (student)',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When student joined the live class',
  `left_at` timestamp NULL DEFAULT NULL COMMENT 'When student left the live class (NULL = still online)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `live_class_participants`
--

INSERT INTO `live_class_participants` (`id`, `recording_id`, `student_id`, `joined_at`, `left_at`) VALUES
(1, 1, 'stu_1000', '2026-03-05 14:07:23', '2026-03-05 14:09:04'),
(2, 3, 'stu_1000', '2026-04-03 06:33:57', '2026-04-03 07:35:04');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_payments`
--

CREATE TABLE `monthly_payments` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores monthly payment transactions';

--
-- Dumping data for table `monthly_payments`
--

INSERT INTO `monthly_payments` (`id`, `student_enrollment_id`, `month`, `year`, `amount`, `payment_method`, `payment_status`, `payment_date`, `card_number`, `receipt_path`, `receipt_type`, `verified_by`, `verified_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 3, 2026, 2000.00, 'bank_transfer', 'paid', NULL, NULL, 'uploads/payments/payment_stu_1000_1772720024.jpg', 'image', NULL, NULL, NULL, '2026-03-05 14:13:44', '2026-03-05 14:15:13'),
(2, 1, 4, 2026, 2000.00, 'bank_transfer', 'paid', NULL, '2222', 'uploads/payments/payment_stu_1000_1775214818.jpeg', 'image', NULL, NULL, 'Card: ****2222, Holder: lllllll', '2026-04-03 11:13:38', '2026-04-03 11:24:14');

--
-- Triggers `monthly_payments`
--
DELIMITER $$
CREATE TRIGGER `after_monthly_payment_update` AFTER UPDATE ON `monthly_payments` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_transactions`
--

INSERT INTO `payment_transactions` (`id`, `payment_type`, `payment_id`, `teacher_id`, `student_id`, `total_amount`, `teacher_points`, `institute_points`, `commission_rate_teacher`, `commission_rate_institute`, `transaction_status`, `created_at`, `updated_at`) VALUES
(1, 'enrollment', 1, 'tea_1000', 'stu_1000', 1000.00, 750.00, 250.00, 75.00, 25.00, 'completed', '2026-03-05 14:10:37', '2026-03-05 14:10:37'),
(2, 'monthly', 1, 'tea_1000', 'stu_1000', 2000.00, 1500.00, 500.00, 75.00, 25.00, 'completed', '2026-03-05 14:15:13', '2026-03-05 14:15:13'),
(3, 'monthly', 2, 'tea_1000', 'stu_1000', 2000.00, 1000.00, 1000.00, 50.00, 50.00, 'completed', '2026-04-03 11:24:14', '2026-04-03 11:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `physical_classes`
--

CREATE TABLE `physical_classes` (
  `id` int(11) NOT NULL,
  `teacher_assignment_id` int(11) NOT NULL,
  `teacher_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `class_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','ongoing','ended','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `publications`
--

CREATE TABLE `publications` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `publications`
--

INSERT INTO `publications` (`id`, `category_id`, `title`, `description`, `price`, `discount`, `image_path`, `created_at`) VALUES
(1, 1, '2000-2025 History Pastpapers O/L', '', 1800.00, 100.00, 'uploads/publications/pub_69aa8236061a0.jpg', '2026-03-06 07:28:54');

-- --------------------------------------------------------

--
-- Table structure for table `publication_categories`
--

CREATE TABLE `publication_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `publication_categories`
--

INSERT INTO `publication_categories` (`id`, `name`, `created_at`) VALUES
(1, 'O/L Pastpaper', '2026-03-06 07:28:04');

-- --------------------------------------------------------

--
-- Table structure for table `publication_orders`
--

CREATE TABLE `publication_orders` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `contact_number` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `district` varchar(100) NOT NULL,
  `payment_method` enum('card','bank_transfer') NOT NULL,
  `bank_receipt_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','preparing','hand_order_to_delivery','canceled','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `publication_orders`
--

INSERT INTO `publication_orders` (`id`, `user_id`, `name`, `contact_number`, `address`, `district`, `payment_method`, `bank_receipt_path`, `status`, `created_at`) VALUES
(1, 'stu_1000', 'Nipuna Diyalagoda', '0762962048', '2225/2\r\nhenpitagedara marandahamula', 'gampaha', 'bank_transfer', 'uploads/receipts/publications/receipt_pub_69aa8289628b2.jpg', 'pending', '2026-03-06 07:30:17'),
(2, NULL, 'thejani traders', '0770080391', 'Chilaw', 'gampaha', 'card', NULL, 'pending', '2026-04-11 14:41:43');

-- --------------------------------------------------------

--
-- Table structure for table `publication_order_items`
--

CREATE TABLE `publication_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `publication_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price_at_order` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `publication_order_items`
--

INSERT INTO `publication_order_items` (`id`, `order_id`, `publication_id`, `quantity`, `price_at_order`) VALUES
(1, 1, 1, 1, 1700.00),
(2, 2, 1, 1, 1700.00);

-- --------------------------------------------------------

--
-- Table structure for table `question_answers`
--

CREATE TABLE `question_answers` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `question_id` int(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `answer_text` text NOT NULL COMMENT 'Answer text content',
  `answer_image` varchar(255) DEFAULT NULL COMMENT 'Optional answer image path',
  `is_correct` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=wrong, 1=correct',
  `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'Answer display order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores question answer options';

-- --------------------------------------------------------

--
-- Table structure for table `question_images`
--

CREATE TABLE `question_images` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `question_id` int(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `image_path` varchar(255) NOT NULL COMMENT 'Image file path',
  `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'Image display order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores multiple images per question';

-- --------------------------------------------------------

--
-- Table structure for table `recordings`
--

CREATE TABLE `recordings` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores YouTube video recordings linked to teacher assignments';

--
-- Dumping data for table `recordings`
--

INSERT INTO `recordings` (`id`, `teacher_assignment_id`, `is_live`, `title`, `description`, `youtube_video_id`, `youtube_url`, `duration`, `thumbnail_url`, `view_count`, `free_video`, `watch_limit`, `status`, `scheduled_start_time`, `actual_start_time`, `end_time`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Basic Algebra', 'Introduction to basic algebra', 'HffngrDGIxE', 'https://www.youtube.com/live/HffngrDGIxE?si=iHFfYJYQdJHzbj_t', NULL, 'https://img.youtube.com/vi/HffngrDGIxE/maxresdefault.jpg', 0, 0, 3, 'ended', '2026-03-05 20:00:00', '2026-03-05 19:33:46', '2026-03-05 19:39:19', '2026-03-05 14:02:19', '2026-03-05 14:09:19'),
(2, 1, 0, 'Test Recording', 'dsdsdsdsds', 'b24oG7qCwp4', 'https://youtu.be/b24oG7qCwp4?si=WvvMyoXI-89Zjy5X', NULL, 'https://img.youtube.com/vi/b24oG7qCwp4/maxresdefault.jpg', 0, 0, 3, 'active', NULL, NULL, NULL, '2026-03-04 18:30:00', '2026-03-05 14:13:05'),
(3, 1, 1, 'testing live 1', 'this is tesging live class description', 'xRdy4buEEY8', 'https://www.youtube.com/live/xRdy4buEEY8?si=0tM6QblIK61yAmzk', NULL, 'https://img.youtube.com/vi/xRdy4buEEY8/maxresdefault.jpg', 0, 1, 3, 'ended', '2026-04-03 11:51:00', '2026-04-03 11:56:15', '2026-04-03 13:05:21', '2026-04-03 06:20:11', '2026-04-03 07:35:21');

-- --------------------------------------------------------

--
-- Table structure for table `recording_files`
--

CREATE TABLE `recording_files` (
  `id` int(11) NOT NULL,
  `recording_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id',
  `uploaded_by` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (who uploaded the file)',
  `file_name` varchar(255) NOT NULL COMMENT 'Original file name',
  `file_path` varchar(500) NOT NULL COMMENT 'Path to stored file',
  `file_size` bigint(20) NOT NULL COMMENT 'File size in bytes',
  `file_type` varchar(100) DEFAULT NULL COMMENT 'MIME type of the file',
  `file_extension` varchar(10) DEFAULT NULL COMMENT 'File extension',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When file was uploaded',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=deleted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores file uploads for recordings';

--
-- Dumping data for table `recording_files`
--

INSERT INTO `recording_files` (`id`, `recording_id`, `uploaded_by`, `file_name`, `file_path`, `file_size`, `file_type`, `file_extension`, `upload_date`, `status`) VALUES
(1, 1, 'stu_1000', 'RL91JiCm4Bpx5QjUIW_z0Yej73YWLbJGys9l6qkE7QqtGH7YxpWnRXF4AVPwPdPcb4qKP6bhfnoT1IG0sqibO2oJ5XkyKajufBFr10zQ0aU5mmSMnxPfd2LU04Oeb_9uGgOgj36HJ6tzWCcd0dYg6nTwel6bNAxILMYoR3eC1evz5AfX6rfla7iuCJPgC8Z-ucYwxWKzNgWcBx.png', 'uploads/recordings/1/stu_1000_1772719558_69a98dc63db5e.png', 26514, 'image/png', 'png', '2026-03-05 14:05:58', 1),
(2, 1, 'stu_1000', '89ef5768ada38b95bdd077e38b74af0f_xeankj.jpg', 'uploads/recordings/1/stu_1000_1772719587_69a98de3aeb84.jpg', 97938, 'image/jpeg', 'jpg', '2026-03-05 14:06:27', 1),
(3, 1, 'tea_1000', 'Untitled design (9).jpg', 'uploads/recordings/1/tea_1000_1772719615_69a98dffb26eb.jpg', 91062, 'image/jpeg', 'jpg', '2026-03-05 14:06:55', 1),
(4, 2, 'tea_1000', 'amex.png', 'uploads/recordings/2/tea_1000_1774497052_69c4ad1cc659c.png', 6188, 'image/png', 'png', '2026-03-26 03:50:52', 1);

-- --------------------------------------------------------

--
-- Table structure for table `streams`
--

CREATE TABLE `streams` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `name` varchar(100) NOT NULL COMMENT 'Stream name (e.g., "Grade 6", "Grade 7", "A/L Science")',
  `description` text DEFAULT NULL COMMENT 'Optional description of the stream',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the grades or categories';

--
-- Dumping data for table `streams`
--

INSERT INTO `streams` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'A/L 2021 Sinhala Medium', NULL, 1, '2026-03-05 13:33:55', '2026-03-05 13:33:55'),
(2, 'Grade 6', NULL, 1, '2026-03-05 17:49:59', '2026-03-05 17:49:59'),
(3, 'Grade 07', NULL, 1, '2026-03-05 17:51:05', '2026-03-05 17:51:05');

-- --------------------------------------------------------

--
-- Table structure for table `stream_subjects`
--

CREATE TABLE `stream_subjects` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `stream_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to streams.id',
  `subject_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to subjects.id',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines which subjects exist in which grades (The Offering)';

--
-- Dumping data for table `stream_subjects`
--

INSERT INTO `stream_subjects` (`id`, `stream_id`, `subject_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-03-05 13:34:13', '2026-03-05 13:34:13'),
(2, 2, 2, 1, '2026-03-05 17:49:59', '2026-03-05 17:49:59'),
(3, 3, 2, 1, '2026-03-05 17:51:05', '2026-03-05 17:51:05');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `attempt_id` int(11) NOT NULL COMMENT 'FK to exam_attempts.id',
  `question_id` int(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `answer_id` int(11) NOT NULL COMMENT 'FK to question_answers.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores student selected answers';

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollment`
--

CREATE TABLE `student_enrollment` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links students to specific stream-subject enrollments';

--
-- Dumping data for table `student_enrollment`
--

INSERT INTO `student_enrollment` (`id`, `student_id`, `stream_subject_id`, `academic_year`, `batch_name`, `enrolled_date`, `status`, `payment_status`, `payment_method`, `payment_date`, `payment_amount`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'stu_1000', 1, 2026, NULL, '2026-03-05', 'active', 'pending', NULL, NULL, NULL, NULL, '2026-03-05 13:37:44', '2026-03-05 13:37:44'),
(2, 'stu_1000', 2, 2026, NULL, '2026-03-06', 'active', 'pending', NULL, NULL, NULL, NULL, '2026-03-06 06:46:17', '2026-03-06 06:46:17'),
(3, 'stu_1000', 3, 2027, NULL, '2026-03-06', 'active', 'paid', NULL, NULL, NULL, NULL, '2026-03-06 07:25:36', '2026-03-06 07:25:36');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `name` varchar(100) NOT NULL COMMENT 'Subject name (e.g., "Science", "Mathematics", "English")',
  `code` varchar(20) DEFAULT NULL COMMENT 'Optional subject code (e.g., "SCI", "MATH")',
  `description` text DEFAULT NULL COMMENT 'Optional description of the subject',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status: 1=active, 0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the subject names';

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `code`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Combined Maths', 'cm2021', NULL, 1, '2026-03-05 13:34:13', '2026-03-05 13:34:13'),
(2, 'Science', '', NULL, 1, '2026-03-05 17:49:59', '2026-03-05 17:49:59');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','image','number','boolean','json') DEFAULT 'text',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_assignments`
--

CREATE TABLE `teacher_assignments` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
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
  `commission_rate` decimal(5,2) DEFAULT 75.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links teachers to specific stream-subject offerings with academic year support';

--
-- Dumping data for table `teacher_assignments`
--

INSERT INTO `teacher_assignments` (`id`, `teacher_id`, `stream_subject_id`, `academic_year`, `batch_name`, `status`, `assigned_date`, `start_date`, `end_date`, `notes`, `created_at`, `updated_at`, `cover_image`, `commission_rate`) VALUES
(1, 'tea_1000', 1, 2026, NULL, 'active', '2026-03-05', NULL, NULL, NULL, '2026-03-05 13:34:17', '2026-04-03 11:21:15', NULL, 50.00),
(2, 'tea_1000', 2, 2026, NULL, 'active', '2026-03-05', NULL, NULL, NULL, '2026-03-05 17:49:59', '2026-03-05 17:49:59', 'uploads/subject_covers/69a9c24703245.jpeg', 75.00),
(3, 'tea_1000', 3, 2026, NULL, 'active', '2026-03-05', NULL, NULL, NULL, '2026-03-05 17:51:05', '2026-03-05 17:51:05', 'uploads/subject_covers/69a9c2894bbec.jpeg', 75.00),
(4, 'tea_1000', 3, 2027, NULL, 'active', NULL, NULL, NULL, NULL, '2026-03-06 07:25:36', '2026-03-06 07:25:36', NULL, 75.00);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_education`
--

CREATE TABLE `teacher_education` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `teacher_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = teacher)',
  `qualification` varchar(200) NOT NULL COMMENT 'Qualification name (e.g., "B.Sc. in Mathematics", "M.Ed.", "Ph.D. in Physics")',
  `institution` varchar(200) DEFAULT NULL COMMENT 'Institution name where qualification was obtained',
  `year_obtained` int(4) DEFAULT NULL COMMENT 'Year when qualification was obtained',
  `field_of_study` varchar(200) DEFAULT NULL COMMENT 'Field of study or specialization',
  `grade_or_class` varchar(50) DEFAULT NULL COMMENT 'Grade/Class obtained (e.g., "First Class", "Distinction", "A+")',
  `certificate_path` varchar(255) DEFAULT NULL COMMENT 'Path to certificate document if uploaded',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores education details for teachers';

--
-- Dumping data for table `teacher_education`
--

INSERT INTO `teacher_education` (`id`, `teacher_id`, `qualification`, `institution`, `year_obtained`, `field_of_study`, `grade_or_class`, `certificate_path`, `created_at`, `updated_at`) VALUES
(1, 'tea_1000', 'BSc (Engineering – Electrical & Electronic)', 'University of Moratuwa, Sri Lanka', 2025, 'Mathematics', 'A', NULL, '2026-03-05 13:34:17', '2026-03-05 13:34:17');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_payment_requests`
--

CREATE TABLE `teacher_payment_requests` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_payment_requests`
--

INSERT INTO `teacher_payment_requests` (`id`, `teacher_id`, `amount`, `status`, `request_date`, `processed_at`, `notes`) VALUES
(1, 'tea_1000', 2000.00, 'pending', '2026-04-03 11:36:16', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_wallet`
--

CREATE TABLE `teacher_wallet` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(20) NOT NULL,
  `total_points` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_earned` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_wallet`
--

INSERT INTO `teacher_wallet` (`id`, `teacher_id`, `total_points`, `total_earned`, `total_withdrawn`, `created_at`, `updated_at`) VALUES
(1, 'tea_1000', 3250.00, 3250.00, 0.00, '2026-03-05 14:10:37', '2026-04-03 11:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(20) NOT NULL COMMENT 'Unique user ID (e.g., stu_0001, tea_1000, adm_1000)',
  `email` varchar(255) DEFAULT NULL,
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
  `al_details_requested` tinyint(1) DEFAULT 0,
  `verification_method` varchar(20) DEFAULT 'none' COMMENT 'Verification method: nic, mobile, none',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  `session_token` varchar(64) DEFAULT NULL COMMENT 'Session token for single login',
  `session_created_at` datetime DEFAULT NULL COMMENT 'Session creation timestamp',
  `commission_rate` decimal(5,2) DEFAULT 75.00,
  `requested_commission_rate` decimal(5,2) DEFAULT 75.00,
  `rating` decimal(3,2) DEFAULT 0.00,
  `hourly_rate` decimal(10,2) DEFAULT 0.00,
  `rating_count` int(11) DEFAULT 0,
  `total_rating` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users table for LMS system';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `first_name`, `second_name`, `dob`, `school_name`, `exam_year`, `closest_town`, `district`, `address`, `nic_no`, `mobile_number`, `whatsapp_number`, `gender`, `profile_picture`, `registering_date`, `status`, `approved`, `al_details_requested`, `verification_method`, `created_at`, `updated_at`, `session_token`, `session_created_at`, `commission_rate`, `requested_commission_rate`, `rating`, `hourly_rate`, `rating_count`, `total_rating`) VALUES
('adm_1000', 'admin@lms.com', '$2y$12$vvcpuoPPEE/dyEnn7WI4GuRAWiMyCb77Sbsg.j.h3LOgxGAruiSnO', 'admin', 'Admin', 'User', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0768368202', NULL, NULL, NULL, '2026-03-05', 1, 1, 0, 'none', '2026-03-05 13:19:28', '2026-04-11 14:55:12', '3b53df3e7a935e343e11fc0d1df0c5d0536276a20954441ae9d9002e0b26aa40', '2026-04-11 20:25:12', 75.00, 75.00, 0.00, 0.00, 0, 0.00),
('ins_1000', 'ds@gmail.com', '$2y$10$x3K7GeqL7gKMmsGi.FwkA.T7gX0dnEJ/4xP3mRSQYJsbq4U1s9.NO', 'instructor', 'Instructor', 'test', NULL, NULL, NULL, NULL, NULL, 'Chilaw', '200214702375', '0766302421', '0766302421', 'male', NULL, '2026-03-28', 1, 1, 0, 'none', '2026-03-28 10:34:03', '2026-04-11 15:49:02', '667f710c3c0c716a9b6e0c6f0844199a7c4bef304a106ab3bfcab4808c23fa29', '2026-04-11 21:02:58', 75.00, 75.00, 4.00, 0.00, 0, 0.00),
('ins_1001', NULL, '$2y$10$u1hbgnxCS9BR.6PQjxxUKOxTasdtVmX1aqc8qCgEEitOQnsJKhlA6', 'instructor', 'ins', '123', NULL, NULL, NULL, NULL, NULL, 'gampaha', '14789655', '123456', '123456', 'male', NULL, '2026-04-03', 1, 1, 0, 'none', '2026-04-03 10:49:04', '2026-04-11 15:32:43', NULL, NULL, 75.00, 75.00, NULL, 1500.00, 0, 0.00),
('stu_1000', 'nipuna@gmail.com', '$2y$10$7OTFo0auCydJhY3Nwd3dM.dWraEL1HJJHWFyu0CEd2ctAIUqduobK', 'student', 'Nipuna', 'Diyalagoda', '2015-12-29', NULL, NULL, NULL, NULL, 'Veyangoda', NULL, '0762962048', '076 296 2048', 'male', 'uploads/profiles/stu_1000_1772717864.jpg', '2026-03-05', 1, 1, 0, 'mobile', '2026-03-05 13:37:44', '2026-04-11 15:50:25', NULL, NULL, 75.00, 75.00, 0.00, 0.00, 0, 0.00),
('tea_1000', 'kamal@gmail.com', '$2y$10$4lsTvZczFYtUNWuTP6meheKGJxA/bdg9Jk0ToR37/z/UJbnqvcfge', 'teacher', 'Kamal', 'Perera', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0763255307', '0763255307', NULL, 'uploads/profiles/tea_1000_1772717657.jpg', '2026-03-05', 1, 1, 0, 'none', '2026-03-05 13:34:17', '2026-04-11 15:50:28', 'a075cb0919476d4a170a3d13b78da84bc6c319ec39fb0ba62d6eb912c7426ca5', '2026-04-11 21:20:28', 75.00, 75.00, 0.00, 0.00, 0, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `video_watch_log`
--

CREATE TABLE `video_watch_log` (
  `id` int(11) NOT NULL COMMENT 'Primary Key',
  `recording_id` int(11) NOT NULL COMMENT 'Foreign Key: Links to recordings.id',
  `student_id` varchar(20) NOT NULL COMMENT 'Foreign Key: Links to users.user_id (where role = student)',
  `watched_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp when video was watched',
  `watch_duration` int(11) DEFAULT NULL COMMENT 'Duration watched in seconds (optional, for future use)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks video watch history for students';

--
-- Dumping data for table `video_watch_log`
--

INSERT INTO `video_watch_log` (`id`, `recording_id`, `student_id`, `watched_at`, `watch_duration`) VALUES
(1, 2, 'stu_1000', '2026-03-05 14:16:48', NULL),
(2, 2, 'stu_1000', '2026-03-05 14:40:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `zoom_chat_messages`
--

CREATE TABLE `zoom_chat_messages` (
  `id` int(11) NOT NULL,
  `zoom_class_id` int(11) NOT NULL,
  `sender_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `zoom_chat_messages`
--

INSERT INTO `zoom_chat_messages` (`id`, `zoom_class_id`, `sender_id`, `message`, `sent_at`) VALUES
(1, 1, 'stu_1000', 'hello', '2026-04-11 13:22:19');

-- --------------------------------------------------------

--
-- Table structure for table `zoom_classes`
--

CREATE TABLE `zoom_classes` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `zoom_classes`
--

INSERT INTO `zoom_classes` (`id`, `teacher_assignment_id`, `title`, `description`, `zoom_meeting_link`, `zoom_meeting_id`, `zoom_passcode`, `scheduled_start_time`, `actual_start_time`, `end_time`, `status`, `free_class`, `created_at`, `updated_at`) VALUES
(1, 1, 'Zoom Class test', 'this is testing zoom class', 'https://us05web.zoom.us/j/84874657675?pwd=Jx22h3B0T1gb4uHtyHPhL8g8q07MvX.1', '', '', '2026-04-11 18:45:00', '2026-04-11 18:45:34', '2026-04-11 18:52:47', 'ended', 0, '2026-04-11 13:15:28', '2026-04-11 13:22:47'),
(2, 4, 'hhhhhh', 'kkkkkkkkkkkkkkkkkk', 'https://us05web.zoom.us/j/87882606683?pwd=FmrPgr2yIVrFqaJAXVpzQSnYQhFGsK.1', '', '', '2026-04-11 21:21:00', NULL, NULL, 'scheduled', 0, '2026-04-11 15:51:16', '2026-04-11 15:51:16'),
(3, 4, 'hhhhhh', 'kkkkkkkkkkkkkkkkkk', 'https://us05web.zoom.us/j/87882606683?pwd=FmrPgr2yIVrFqaJAXVpzQSnYQhFGsK.1', '', '', '2026-04-11 21:21:00', NULL, NULL, 'scheduled', 0, '2026-04-11 15:51:16', '2026-04-11 15:51:16');

-- --------------------------------------------------------

--
-- Table structure for table `zoom_class_files`
--

CREATE TABLE `zoom_class_files` (
  `id` int(11) NOT NULL,
  `zoom_class_id` int(11) NOT NULL,
  `uploader_id` varchar(50) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `zoom_class_files`
--

INSERT INTO `zoom_class_files` (`id`, `zoom_class_id`, `uploader_id`, `file_name`, `file_path`, `file_size`, `file_type`, `uploaded_at`) VALUES
(1, 1, 'tea_1000', 'Screenshot 2026-04-06 103133.png', '69da4b1681579_1775913750.png', 268193, 'image/png', '2026-04-11 13:22:30');

-- --------------------------------------------------------

--
-- Table structure for table `zoom_participants`
--

CREATE TABLE `zoom_participants` (
  `id` int(11) NOT NULL,
  `zoom_class_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `join_time` datetime NOT NULL,
  `leave_time` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `zoom_participants`
--

INSERT INTO `zoom_participants` (`id`, `zoom_class_id`, `user_id`, `join_time`, `leave_time`, `duration_minutes`, `created_at`) VALUES
(1, 1, 'tea_1000', '2026-04-11 18:45:34', '2026-04-11 18:52:47', 7, '2026-04-11 13:15:34'),
(2, 1, 'stu_1000', '2026-04-11 18:46:56', '2026-04-11 18:52:47', 5, '2026-04-11 13:16:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `al_exam_submissions`
--
ALTER TABLE `al_exam_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_submission` (`student_id`),
  ADD KEY `idx_district` (`district`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `physical_class_id` (`physical_class_id`,`student_id`);

--
-- Indexes for table `budget_expenses`
--
ALTER TABLE `budget_expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recording_id` (`recording_id`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `course_chats`
--
ALTER TABLE `course_chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_recording_id` (`course_recording_id`);

--
-- Indexes for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`course_id`,`student_id`);

--
-- Indexes for table `course_payments`
--
ALTER TABLE `course_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cp_enrollment` (`course_enrollment_id`),
  ADD KEY `idx_cp_status` (`payment_status`);

--
-- Indexes for table `course_recordings`
--
ALTER TABLE `course_recordings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `course_uploads`
--
ALTER TABLE `course_uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `enrollment_fees`
--
ALTER TABLE `enrollment_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_assignment_fee` (`teacher_assignment_id`),
  ADD KEY `idx_teacher_assignment_id` (`teacher_assignment_id`);

--
-- Indexes for table `enrollment_payments`
--
ALTER TABLE `enrollment_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_enrollment_payment_verified_by` (`verified_by`),
  ADD KEY `idx_student_enrollment_id` (`student_enrollment_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_id` (`teacher_id`),
  ADD KEY `idx_subject_id` (`subject_id`),
  ADD KEY `idx_is_published` (`is_published`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_exam` (`exam_id`,`student_id`),
  ADD KEY `idx_exam_id` (`exam_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exam_id` (`exam_id`),
  ADD KEY `idx_order_index` (`order_index`);

--
-- Indexes for table `institute_settings`
--
ALTER TABLE `institute_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `institute_wallet`
--
ALTER TABLE `institute_wallet`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `instructor_payments`
--
ALTER TABLE `instructor_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `instructor_ratings`
--
ALTER TABLE `instructor_ratings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `instructor_requests`
--
ALTER TABLE `instructor_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_subject_id` (`subject_id`),
  ADD KEY `idx_accepted_by` (`accepted_by`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `instructor_request_acceptances`
--
ALTER TABLE `instructor_request_acceptances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_id` (`request_id`,`instructor_id`);

--
-- Indexes for table `instructor_sessions`
--
ALTER TABLE `instructor_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`request_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `instructor_subjects`
--
ALTER TABLE `instructor_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_instructor_subject` (`instructor_id`,`subject_id`),
  ADD KEY `idx_instructor_id` (`instructor_id`),
  ADD KEY `idx_subject_id` (`subject_id`);

--
-- Indexes for table `live_classes`
--
ALTER TABLE `live_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `live_class_participants`
--
ALTER TABLE `live_class_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participant` (`recording_id`,`student_id`),
  ADD KEY `idx_recording_status` (`recording_id`,`left_at`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment_month_year` (`student_enrollment_id`,`month`,`year`),
  ADD KEY `fk_monthly_payment_verified_by` (`verified_by`),
  ADD KEY `idx_student_enrollment_id` (`student_enrollment_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_month_year` (`month`,`year`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_tracking` (`payment_type`,`payment_id`),
  ADD KEY `idx_teacher_transactions` (`teacher_id`,`created_at`),
  ADD KEY `idx_transaction_status` (`transaction_status`);

--
-- Indexes for table `physical_classes`
--
ALTER TABLE `physical_classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `publications`
--
ALTER TABLE `publications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `publication_categories`
--
ALTER TABLE `publication_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `publication_orders`
--
ALTER TABLE `publication_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `publication_order_items`
--
ALTER TABLE `publication_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `publication_id` (`publication_id`);

--
-- Indexes for table `question_answers`
--
ALTER TABLE `question_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_is_correct` (`is_correct`),
  ADD KEY `idx_order_index` (`order_index`);

--
-- Indexes for table `question_images`
--
ALTER TABLE `question_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_order_index` (`order_index`);

--
-- Indexes for table `recordings`
--
ALTER TABLE `recordings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_assignment_id` (`teacher_assignment_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_youtube_video_id` (`youtube_video_id`),
  ADD KEY `idx_is_live` (`is_live`),
  ADD KEY `idx_scheduled` (`scheduled_start_time`);

--
-- Indexes for table `recording_files`
--
ALTER TABLE `recording_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recording_id` (`recording_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `streams`
--
ALTER TABLE `streams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `stream_subjects`
--
ALTER TABLE `stream_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stream_subject` (`stream_id`,`subject_id`),
  ADD KEY `idx_stream_id` (`stream_id`),
  ADD KEY `idx_subject_id` (`subject_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_student_answers_answer` (`answer_id`),
  ADD KEY `idx_attempt_id` (`attempt_id`),
  ADD KEY `idx_question_id` (`question_id`);

--
-- Indexes for table `student_enrollment`
--
ALTER TABLE `student_enrollment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_stream_subject_year` (`student_id`,`stream_subject_id`,`academic_year`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_stream_subject_id` (`stream_subject_id`),
  ADD KEY `idx_academic_year` (`academic_year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD UNIQUE KEY `unique_setting_key` (`setting_key`);

--
-- Indexes for table `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_stream_subject_year` (`teacher_id`,`stream_subject_id`,`academic_year`),
  ADD KEY `idx_teacher_id` (`teacher_id`),
  ADD KEY `idx_stream_subject_id` (`stream_subject_id`),
  ADD KEY `idx_academic_year` (`academic_year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_teacher_year` (`teacher_id`,`academic_year`);

--
-- Indexes for table `teacher_education`
--
ALTER TABLE `teacher_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_id` (`teacher_id`);

--
-- Indexes for table `teacher_payment_requests`
--
ALTER TABLE `teacher_payment_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teacher_wallet`
--
ALTER TABLE `teacher_wallet`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_teacher_points` (`teacher_id`,`total_points`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `mobile_number` (`mobile_number`),
  ADD UNIQUE KEY `whatsapp_number` (`whatsapp_number`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_approved` (`approved`),
  ADD KEY `idx_nic_no` (`nic_no`),
  ADD KEY `idx_mobile_number` (`mobile_number`),
  ADD KEY `idx_session_token` (`session_token`);

--
-- Indexes for table `video_watch_log`
--
ALTER TABLE `video_watch_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recording_id` (`recording_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_recording_student` (`recording_id`,`student_id`),
  ADD KEY `idx_watched_at` (`watched_at`);

--
-- Indexes for table `zoom_chat_messages`
--
ALTER TABLE `zoom_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_zoom_class` (`zoom_class_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `zoom_classes`
--
ALTER TABLE `zoom_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_assignment` (`teacher_assignment_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_scheduled_time` (`scheduled_start_time`);

--
-- Indexes for table `zoom_class_files`
--
ALTER TABLE `zoom_class_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_zoom_class` (`zoom_class_id`),
  ADD KEY `idx_uploader` (`uploader_id`);

--
-- Indexes for table `zoom_participants`
--
ALTER TABLE `zoom_participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_zoom_class` (`zoom_class_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `al_exam_submissions`
--
ALTER TABLE `al_exam_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_expenses`
--
ALTER TABLE `budget_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_chats`
--
ALTER TABLE `course_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_payments`
--
ALTER TABLE `course_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_recordings`
--
ALTER TABLE `course_recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_uploads`
--
ALTER TABLE `course_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment_fees`
--
ALTER TABLE `enrollment_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `enrollment_payments`
--
ALTER TABLE `enrollment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key';

--
-- AUTO_INCREMENT for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key';

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key';

--
-- AUTO_INCREMENT for table `institute_wallet`
--
ALTER TABLE `institute_wallet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `instructor_payments`
--
ALTER TABLE `instructor_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `instructor_ratings`
--
ALTER TABLE `instructor_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `instructor_requests`
--
ALTER TABLE `instructor_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `instructor_request_acceptances`
--
ALTER TABLE `instructor_request_acceptances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `instructor_sessions`
--
ALTER TABLE `instructor_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `instructor_subjects`
--
ALTER TABLE `instructor_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `live_classes`
--
ALTER TABLE `live_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `live_class_participants`
--
ALTER TABLE `live_class_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `physical_classes`
--
ALTER TABLE `physical_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `publications`
--
ALTER TABLE `publications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `publication_categories`
--
ALTER TABLE `publication_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `publication_orders`
--
ALTER TABLE `publication_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `publication_order_items`
--
ALTER TABLE `publication_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `question_answers`
--
ALTER TABLE `question_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key';

--
-- AUTO_INCREMENT for table `question_images`
--
ALTER TABLE `question_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key';

--
-- AUTO_INCREMENT for table `recordings`
--
ALTER TABLE `recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `recording_files`
--
ALTER TABLE `recording_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `streams`
--
ALTER TABLE `streams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stream_subjects`
--
ALTER TABLE `stream_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key';

--
-- AUTO_INCREMENT for table `student_enrollment`
--
ALTER TABLE `student_enrollment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teacher_education`
--
ALTER TABLE `teacher_education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_payment_requests`
--
ALTER TABLE `teacher_payment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_wallet`
--
ALTER TABLE `teacher_wallet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `video_watch_log`
--
ALTER TABLE `video_watch_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `zoom_chat_messages`
--
ALTER TABLE `zoom_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `zoom_classes`
--
ALTER TABLE `zoom_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `zoom_class_files`
--
ALTER TABLE `zoom_class_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `zoom_participants`
--
ALTER TABLE `zoom_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `al_exam_submissions`
--
ALTER TABLE `al_exam_submissions`
  ADD CONSTRAINT `fk_al_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_messages_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `course_chats`
--
ALTER TABLE `course_chats`
  ADD CONSTRAINT `course_chats_ibfk_1` FOREIGN KEY (`course_recording_id`) REFERENCES `course_recordings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_payments`
--
ALTER TABLE `course_payments`
  ADD CONSTRAINT `course_payments_ibfk_1` FOREIGN KEY (`course_enrollment_id`) REFERENCES `course_enrollments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_recordings`
--
ALTER TABLE `course_recordings`
  ADD CONSTRAINT `course_recordings_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_uploads`
--
ALTER TABLE `course_uploads`
  ADD CONSTRAINT `course_uploads_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollment_fees`
--
ALTER TABLE `enrollment_fees`
  ADD CONSTRAINT `fk_enrollment_fee_assignment` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `enrollment_payments`
--
ALTER TABLE `enrollment_payments`
  ADD CONSTRAINT `fk_enrollment_payment_enrollment` FOREIGN KEY (`student_enrollment_id`) REFERENCES `student_enrollment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrollment_payment_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `fk_exams_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exams_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD CONSTRAINT `fk_attempts_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `live_classes`
--
ALTER TABLE `live_classes`
  ADD CONSTRAINT `live_classes_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `instructor_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `live_class_participants`
--
ALTER TABLE `live_class_participants`
  ADD CONSTRAINT `fk_participants_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_participants_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  ADD CONSTRAINT `fk_monthly_payment_enrollment` FOREIGN KEY (`student_enrollment_id`) REFERENCES `student_enrollment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_monthly_payment_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `publications`
--
ALTER TABLE `publications`
  ADD CONSTRAINT `publications_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `publication_categories` (`id`);

--
-- Constraints for table `publication_orders`
--
ALTER TABLE `publication_orders`
  ADD CONSTRAINT `publication_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `publication_order_items`
--
ALTER TABLE `publication_order_items`
  ADD CONSTRAINT `publication_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `publication_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `publication_order_items_ibfk_2` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`);

--
-- Constraints for table `question_answers`
--
ALTER TABLE `question_answers`
  ADD CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `question_images`
--
ALTER TABLE `question_images`
  ADD CONSTRAINT `fk_images_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `recordings`
--
ALTER TABLE `recordings`
  ADD CONSTRAINT `fk_recordings_teacher_assignment` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `recording_files`
--
ALTER TABLE `recording_files`
  ADD CONSTRAINT `fk_recording_files_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recording_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stream_subjects`
--
ALTER TABLE `stream_subjects`
  ADD CONSTRAINT `fk_stream_subjects_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stream_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `fk_student_answers_answer` FOREIGN KEY (`answer_id`) REFERENCES `question_answers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_enrollment`
--
ALTER TABLE `student_enrollment`
  ADD CONSTRAINT `fk_student_enrollment_stream_subject` FOREIGN KEY (`stream_subject_id`) REFERENCES `stream_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  ADD CONSTRAINT `fk_teacher_assignments_stream_subject` FOREIGN KEY (`stream_subject_id`) REFERENCES `stream_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_teacher_assignments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teacher_education`
--
ALTER TABLE `teacher_education`
  ADD CONSTRAINT `fk_teacher_education_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `video_watch_log`
--
ALTER TABLE `video_watch_log`
  ADD CONSTRAINT `fk_video_watch_log_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_video_watch_log_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `zoom_chat_messages`
--
ALTER TABLE `zoom_chat_messages`
  ADD CONSTRAINT `zoom_chat_messages_ibfk_1` FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `zoom_chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `zoom_classes`
--
ALTER TABLE `zoom_classes`
  ADD CONSTRAINT `zoom_classes_ibfk_1` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `zoom_class_files`
--
ALTER TABLE `zoom_class_files`
  ADD CONSTRAINT `zoom_class_files_ibfk_1` FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `zoom_class_files_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `zoom_participants`
--
ALTER TABLE `zoom_participants`
  ADD CONSTRAINT `zoom_participants_ibfk_1` FOREIGN KEY (`zoom_class_id`) REFERENCES `zoom_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `zoom_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
