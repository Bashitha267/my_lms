-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 13, 2026 at 10:38 AM
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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `teacher_id`, `title`, `description`, `price`, `cover_image`, `status`, `created_at`) VALUES
(3, 'tea_1001', 'Japan Language', 'Beginner to Pro Japan Language Tutorials', 45000.00, 'uploads/courses/course_696a01491081b.jpg', 1, '2026-01-16 14:43:45');

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

--
-- Dumping data for table `course_chats`
--

INSERT INTO `course_chats` (`id`, `course_recording_id`, `user_id`, `message`, `created_at`) VALUES
(4, 4, 'stu_1002', 'hello madam', '2026-01-16 14:47:01');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_enrollments`
--

INSERT INTO `course_enrollments` (`id`, `course_id`, `student_id`, `enrolled_at`, `status`, `payment_status`) VALUES
(6, 3, 'stu_1002', '2026-01-16 14:44:52', 'active', 'paid'),
(7, 3, 'stu_1001', '2026-01-31 21:09:12', 'active', 'pending');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_payments`
--

INSERT INTO `course_payments` (`id`, `course_enrollment_id`, `amount`, `payment_method`, `payment_status`, `receipt_path`, `receipt_type`, `card_number`, `notes`, `created_at`, `verified_at`) VALUES
(4, 6, 45000.00, 'bank_transfer', 'paid', 'uploads/payments/course_pay_stu_1002_1768554902.pdf', 'pdf', NULL, NULL, '2026-01-16 09:15:02', '2026-01-16 09:16:41');

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

--
-- Dumping data for table `course_recordings`
--

INSERT INTO `course_recordings` (`id`, `course_id`, `title`, `description`, `video_path`, `thumbnail_url`, `is_free`, `views`, `created_at`) VALUES
(4, 3, 'Video 1', 'Introduction to Japan Language', 'https://youtu.be/G_oC7anVuA8?si=GgYhUbRvQ-426hFS', 'https://img.youtube.com/vi/G_oC7anVuA8/mqdefault.jpg', 0, 4, '2026-01-16 14:44:36'),
(5, 3, 'Video 2', 'Japan Language 2', 'https://youtu.be/D7IMsESKTIA?si=cOYhnwEsWdwpZUzO', 'https://img.youtube.com/vi/D7IMsESKTIA/mqdefault.jpg', 0, 0, '2026-01-16 14:47:47');

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
(9, 16, 1500.00, 2500.00, '2026-01-18 16:43:16', '2026-01-18 16:43:16');

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
(10, 21, 1500.00, 'bank_transfer', 'paid', NULL, NULL, 'uploads/payments/payment_stu_1001_1770974303.pdf', 'pdf', NULL, NULL, NULL, '2026-02-13 09:18:23', '2026-02-13 09:19:43');

--
-- Triggers `enrollment_payments`
--
DELIMITER $$
CREATE TRIGGER `after_enrollment_payment_update` AFTER UPDATE ON `enrollment_payments` FOR EACH ROW BEGIN
    DECLARE v_teacher_id VARCHAR(20);
    DECLARE v_student_id VARCHAR(20);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        
        
        SELECT ta.teacher_id, se.student_id 
        INTO v_teacher_id, v_student_id
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
            AND se.academic_year = ta.academic_year
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            
            SET v_teacher_points = NEW.amount * 0.75;
            SET v_institute_points = NEW.amount * 0.25;
            
            
            INSERT IGNORE INTO teacher_wallet (teacher_id, total_points, total_earned)
            VALUES (v_teacher_id, 0.00, 0.00);
            
            
            UPDATE teacher_wallet
            SET total_points = total_points + v_teacher_points,
                total_earned = total_earned + v_teacher_points
            WHERE teacher_id = v_teacher_id;
            
            
            UPDATE institute_wallet
            SET total_points = total_points + v_institute_points,
                total_earned = total_earned + v_institute_points
            WHERE id = 1;
            
            
            INSERT INTO payment_transactions (
                payment_type, payment_id, teacher_id, student_id,
                total_amount, teacher_points, institute_points, transaction_status
            ) VALUES (
                'enrollment', NEW.id, v_teacher_id, v_student_id,
                NEW.amount, v_teacher_points, v_institute_points, 'completed'
            );
        END IF;
        
    
    ELSEIF NEW.payment_status = 'refunded' AND OLD.payment_status = 'paid' THEN
        
        
        SELECT teacher_id, student_id, teacher_points, institute_points
        INTO v_teacher_id, v_student_id, v_teacher_points, v_institute_points
        FROM payment_transactions
        WHERE payment_type = 'enrollment' AND payment_id = NEW.id AND transaction_status = 'completed'
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            
            UPDATE teacher_wallet
            SET total_points = total_points - v_teacher_points
            WHERE teacher_id = v_teacher_id;
            
            
            UPDATE institute_wallet
            SET total_points = total_points - v_institute_points
            WHERE id = 1;
            
            
            UPDATE payment_transactions
            SET transaction_status = 'reversed'
            WHERE payment_type = 'enrollment' AND payment_id = NEW.id;
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

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `teacher_id`, `subject_id`, `title`, `duration_minutes`, `deadline`, `is_published`, `status`, `created_at`, `updated_at`) VALUES
(5, 'tea_1001', 18, 'mid', 60, '2026-01-31 14:10:00', 0, 'active', '2026-01-30 08:40:02', '2026-01-31 15:33:36'),
(6, 'tea_1001', 18, 'title 2', 60, '2026-02-06 21:05:00', 1, 'active', '2026-01-31 15:36:13', '2026-01-31 15:40:19');

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

--
-- Dumping data for table `exam_attempts`
--

INSERT INTO `exam_attempts` (`id`, `exam_id`, `student_id`, `start_time`, `end_time`, `score`, `correct_count`, `total_questions`, `status`, `created_at`) VALUES
(4, 5, 'stu_1001', '2026-01-30 09:41:04', '2026-01-30 09:44:20', 0.00, 0, 2, 'completed', '2026-01-30 08:41:04'),
(5, 6, 'stu_1001', '2026-01-31 16:41:00', '2026-01-31 16:41:30', 0.00, 0, 1, 'completed', '2026-01-31 15:41:00');

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

--
-- Dumping data for table `exam_questions`
--

INSERT INTO `exam_questions` (`id`, `exam_id`, `question_text`, `question_image`, `question_type`, `order_index`, `created_at`, `updated_at`) VALUES
(10, 5, 'New Question 1', NULL, 'single', 1, '2026-01-30 08:40:04', '2026-01-30 08:40:04'),
(11, 5, 'New Question 2', NULL, 'single', 2, '2026-01-30 08:40:22', '2026-01-30 08:40:22'),
(13, 6, 'test question 1', NULL, 'single', 1, '2026-01-31 15:40:23', '2026-01-31 15:40:46');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `institute_wallet`
--

INSERT INTO `institute_wallet` (`id`, `total_points`, `total_earned`, `created_at`, `updated_at`) VALUES
(1, 1000.00, 1000.00, '2026-02-06 09:22:50', '2026-02-13 09:19:43'),
(2, 0.00, 0.00, '2026-02-06 09:33:14', '2026-02-06 09:33:14'),
(3, 0.00, 0.00, '2026-02-06 09:33:21', '2026-02-06 09:33:21');

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
(5, 24, 'stu_1001', '2026-02-13 09:25:30', '2026-02-13 09:26:04');

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
(9, 21, 2, 2026, 2500.00, 'bank_transfer', 'paid', NULL, NULL, 'uploads/payments/payment_stu_1001_1770370260.png', 'image', NULL, NULL, NULL, '2026-02-06 09:31:00', '2026-02-06 09:33:27');

--
-- Triggers `monthly_payments`
--
DELIMITER $$
CREATE TRIGGER `after_monthly_payment_update` AFTER UPDATE ON `monthly_payments` FOR EACH ROW BEGIN
    DECLARE v_teacher_id VARCHAR(20);
    DECLARE v_student_id VARCHAR(20);
    DECLARE v_teacher_points DECIMAL(10,2);
    DECLARE v_institute_points DECIMAL(10,2);
    
    
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        
        
        SELECT ta.teacher_id, se.student_id 
        INTO v_teacher_id, v_student_id
        FROM student_enrollment se
        JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id 
            AND se.academic_year = ta.academic_year
        WHERE se.id = NEW.student_enrollment_id
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            
            SET v_teacher_points = NEW.amount * 0.75;
            SET v_institute_points = NEW.amount * 0.25;
            
            
            INSERT IGNORE INTO teacher_wallet (teacher_id, total_points, total_earned)
            VALUES (v_teacher_id, 0.00, 0.00);
            
            
            UPDATE teacher_wallet
            SET total_points = total_points + v_teacher_points,
                total_earned = total_earned + v_teacher_points
            WHERE teacher_id = v_teacher_id;
            
            
            UPDATE institute_wallet
            SET total_points = total_points + v_institute_points,
                total_earned = total_earned + v_institute_points
            WHERE id = 1;
            
            
            INSERT INTO payment_transactions (
                payment_type, payment_id, teacher_id, student_id,
                total_amount, teacher_points, institute_points, transaction_status
            ) VALUES (
                'monthly', NEW.id, v_teacher_id, v_student_id,
                NEW.amount, v_teacher_points, v_institute_points, 'completed'
            );
        END IF;
        
    
    ELSEIF NEW.payment_status = 'refunded' AND OLD.payment_status = 'paid' THEN
        
        
        SELECT teacher_id, student_id, teacher_points, institute_points
        INTO v_teacher_id, v_student_id, v_teacher_points, v_institute_points
        FROM payment_transactions
        WHERE payment_type = 'monthly' AND payment_id = NEW.id AND transaction_status = 'completed'
        LIMIT 1;
        
        IF v_teacher_id IS NOT NULL THEN
            
            UPDATE teacher_wallet
            SET total_points = total_points - v_teacher_points
            WHERE teacher_id = v_teacher_id;
            
            
            UPDATE institute_wallet
            SET total_points = total_points - v_institute_points
            WHERE id = 1;
            
            
            UPDATE payment_transactions
            SET transaction_status = 'reversed'
            WHERE payment_type = 'monthly' AND payment_id = NEW.id;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_transactions`
--

INSERT INTO `payment_transactions` (`id`, `payment_type`, `payment_id`, `teacher_id`, `student_id`, `total_amount`, `teacher_points`, `institute_points`, `commission_rate_teacher`, `commission_rate_institute`, `transaction_status`, `created_at`, `updated_at`) VALUES
(1, 'monthly', 9, 'tea_1001', 'stu_1001', 2500.00, 1875.00, 625.00, 75.00, 25.00, 'completed', '2026-02-06 09:33:27', '2026-02-06 09:33:27'),
(2, 'enrollment', 10, 'tea_1001', 'stu_1001', 1500.00, 1125.00, 375.00, 75.00, 25.00, 'completed', '2026-02-13 09:19:43', '2026-02-13 09:19:43');

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

--
-- Dumping data for table `physical_classes`
--

INSERT INTO `physical_classes` (`id`, `teacher_assignment_id`, `teacher_id`, `title`, `description`, `class_date`, `start_time`, `location`, `status`, `created_at`) VALUES
(1, 16, 'tea_1001', 'test', 'dsdsdsd', '2026-02-27', '18:46:00', '1111', 'ended', '2026-02-06 08:12:21');

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

--
-- Dumping data for table `question_answers`
--

INSERT INTO `question_answers` (`id`, `question_id`, `answer_text`, `answer_image`, `is_correct`, `order_index`, `created_at`, `updated_at`) VALUES
(37, 10, 'A', NULL, 1, 1, '2026-01-30 08:40:04', '2026-01-30 08:40:17'),
(38, 10, 'B', NULL, 0, 2, '2026-01-30 08:40:04', '2026-01-30 08:40:17'),
(39, 10, 'C', NULL, 0, 3, '2026-01-30 08:40:04', '2026-01-30 08:40:17'),
(40, 10, 'D', NULL, 0, 4, '2026-01-30 08:40:04', '2026-01-30 08:40:17'),
(41, 11, 'A', NULL, 1, 1, '2026-01-30 08:40:22', '2026-01-30 08:40:33'),
(42, 11, 'B', NULL, 0, 2, '2026-01-30 08:40:22', '2026-01-30 08:40:33'),
(43, 11, 'C', NULL, 0, 3, '2026-01-30 08:40:22', '2026-01-30 08:40:33'),
(44, 11, 'D', NULL, 0, 4, '2026-01-30 08:40:22', '2026-01-30 08:40:33'),
(50, 13, 'A', NULL, 0, 1, '2026-01-31 15:40:23', '2026-01-31 15:40:46'),
(51, 13, 'B', NULL, 1, 2, '2026-01-31 15:40:23', '2026-01-31 15:40:47'),
(52, 13, 'C', NULL, 0, 3, '2026-01-31 15:40:23', '2026-01-31 15:40:46'),
(53, 13, 'D', NULL, 0, 4, '2026-01-31 15:40:23', '2026-01-31 15:40:46');

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

--
-- Dumping data for table `question_images`
--

INSERT INTO `question_images` (`id`, `question_id`, `image_path`, `order_index`, `created_at`) VALUES
(12, 10, 'uploads/exam_images/697c6e66cdfa7.png', 1, '2026-01-30 08:40:06'),
(13, 10, 'uploads/exam_images/697c6e68932d2.jpg', 2, '2026-01-30 08:40:08'),
(14, 11, 'uploads/exam_images/697c6e7925c97.jpg', 1, '2026-01-30 08:40:25'),
(16, 13, 'uploads/exam_images/697e226bce1ff.jpg', 1, '2026-01-31 15:40:27');

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
(22, 16, 0, 'Title 1', 'this is test video', 'W-3xZiZj_sw', 'https://youtu.be/W-3xZiZj_sw?si=8M14hk07nA3TACEw', NULL, 'https://img.youtube.com/vi/W-3xZiZj_sw/maxresdefault.jpg', 0, 0, 3, 'active', NULL, NULL, NULL, '2026-01-23 18:30:00', '2026-01-24 09:50:27'),
(23, 16, 1, 'yt', '', 'EPvMbu_0GAA', 'https://www.youtube.com/live/EPvMbu_0GAA?si=FnBWr836v17dTfL6', NULL, 'https://img.youtube.com/vi/EPvMbu_0GAA/maxresdefault.jpg', 0, 0, 3, 'inactive', '2026-01-30 13:20:00', NULL, NULL, '2026-01-30 07:51:00', '2026-01-30 08:23:03'),
(24, 16, 1, 'Testing', 'dsdasdasdasdasd', '4cx-uFxs7uk', 'https://youtu.be/4cx-uFxs7uk?si=fjekr1P2xbZHd5U7', NULL, 'https://img.youtube.com/vi/4cx-uFxs7uk/maxresdefault.jpg', 0, 0, 3, 'ended', '2026-02-14 17:41:00', '2026-02-13 14:54:49', '2026-02-13 14:56:54', '2026-02-13 09:09:47', '2026-02-13 09:26:54');

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
(7, 22, 'tea_1001', 'Merry Christmas Instagram Post.jpg', 'uploads/recordings/22/tea_1001_1769248245_697495f56b317.jpg', 408717, 'image/jpeg', 'jpg', '2026-01-24 09:50:45', 1),
(8, 22, 'tea_1001', 'purple.jpg', 'uploads/recordings/22/tea_1001_1769502512_69787730c0ee7.jpg', 55533, 'image/jpeg', 'jpg', '2026-01-27 08:28:32', 1),
(9, 22, 'stu_1001', 'green 2.jpg', 'uploads/recordings/22/stu_1001_1769502592_6978778068726.jpg', 51122, 'image/jpeg', 'jpg', '2026-01-27 08:29:52', 1),
(10, 22, 'stu_1003', 'green.png', 'uploads/recordings/22/stu_1003_1769502777_69787839314f5.png', 209264, 'image/png', 'png', '2026-01-27 08:32:57', 1);

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
(18, '2027 A/L COMMERCE', NULL, 0, '2026-01-18 16:43:16', '2026-01-31 16:11:52');

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
(46, 18, 18, 1, '2026-01-18 16:43:16', '2026-01-18 16:43:16');

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

--
-- Dumping data for table `student_answers`
--

INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `answer_id`, `created_at`) VALUES
(18, 4, 10, 40, '2026-01-30 08:44:17'),
(19, 4, 11, 43, '2026-01-30 08:44:19'),
(26, 5, 13, 50, '2026-01-31 15:41:24');

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
(21, 'stu_1001', 46, 2026, NULL, '2026-01-22', 'active', 'pending', NULL, NULL, NULL, NULL, '2026-01-22 08:21:47', '2026-01-22 08:21:47'),
(22, 'stu_1003', 46, 2026, NULL, '2026-01-27', 'active', 'pending', NULL, NULL, NULL, NULL, '2026-01-27 08:31:36', '2026-01-27 08:31:36');

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
(16, 'Physics', '', NULL, 1, '2026-01-15 16:49:03', '2026-01-15 16:49:03'),
(17, 'Information Technology Revision', '', NULL, 1, '2026-01-16 07:46:09', '2026-01-16 07:46:09'),
(18, 'Economics', '', NULL, 1, '2026-01-16 07:47:36', '2026-01-16 07:47:36'),
(19, 'Mathematics', '', NULL, 1, '2026-01-16 08:10:14', '2026-01-16 08:10:14'),
(20, 'Business Studies', '', NULL, 1, '2026-01-16 12:12:48', '2026-01-16 12:12:48'),
(21, 'Economics English Medium', '', NULL, 1, '2026-01-17 21:50:09', '2026-01-17 21:50:09');

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

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES
(1, 'dashboard_background', 'uploads/backgrounds/dashboard_bg_1767088657.webp', 'image', 'Background image for student dashboard', '2025-12-30 09:57:37', 'adm_0001'),
(3, 'recordings_background', 'uploads/backgrounds/recordings_bg_1767089340.jpeg', 'image', 'Background image for recordings page', '2025-12-30 10:09:00', 'adm_0001'),
(4, 'online_courses_background', 'uploads/backgrounds/online_courses_bg_1768555238.jpg', 'image', 'Background image for online courses page', '2026-01-16 09:20:38', 'adm_1000'),
(5, 'live_classes_background', 'uploads/backgrounds/live_classes_bg_1768555898.jpeg', 'image', 'Background image for live classes page', '2026-01-16 09:31:38', 'adm_1000');

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
  `cover_image` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links teachers to specific stream-subject offerings with academic year support';

--
-- Dumping data for table `teacher_assignments`
--

INSERT INTO `teacher_assignments` (`id`, `teacher_id`, `stream_subject_id`, `academic_year`, `batch_name`, `status`, `assigned_date`, `start_date`, `end_date`, `notes`, `created_at`, `updated_at`, `cover_image`) VALUES
(16, 'tea_1001', 46, 2026, NULL, 'active', '2026-01-18', NULL, NULL, NULL, '2026-01-18 16:43:16', '2026-01-18 16:43:16', 'uploads/subject_covers/696d0da4a8135.jpg');

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
(4, 'tea_1000', 'BSc (Computer Science)', 'University of Colombo, Sri Lanka', 2022, '', '', NULL, '2026-01-16 07:46:18', '2026-01-16 07:46:18'),
(5, 'tea_1001', 'BSc (Mathematics)', 'University of Peradeniya, Sri Lanka', 2021, '', '', NULL, '2026-01-16 07:47:47', '2026-01-16 07:47:47'),
(7, 'tea_1003', 'BSc (Physics)', 'University of Kelaniya, Sri Lanka', 2024, '', '', NULL, '2026-01-16 08:08:23', '2026-01-16 08:08:23'),
(8, 'tea_1004', 'BSc (Engineering – Electrical & Electronic)', 'University of Moratuwa, Sri Lanka', 2021, '', '', NULL, '2026-01-16 08:14:33', '2026-01-16 08:14:33');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_wallet`
--

INSERT INTO `teacher_wallet` (`id`, `teacher_id`, `total_points`, `total_earned`, `total_withdrawn`, `created_at`, `updated_at`) VALUES
(1, 'tea_1001', 3000.00, 3000.00, 0.00, '2026-02-06 09:33:27', '2026-02-13 09:19:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(20) NOT NULL COMMENT 'Unique user ID (e.g., stu_0001)',
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
  `session_created_at` datetime DEFAULT NULL COMMENT 'Session creation timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users table for LMS system';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `first_name`, `second_name`, `dob`, `school_name`, `exam_year`, `closest_town`, `district`, `address`, `nic_no`, `mobile_number`, `whatsapp_number`, `gender`, `profile_picture`, `registering_date`, `status`, `approved`, `verification_method`, `created_at`, `updated_at`, `session_token`, `session_created_at`) VALUES
('adm_1000', 'admin@example.com', '$2y$10$RTf99yYEtlRzGRLjlZQJaO8yRJPyCwiF3SFFEIMErqz7zkzCzE0fS', 'admin', 'System', 'Admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0768368202', '0768368202', NULL, NULL, '2026-01-15', 1, 1, 'none', '2026-01-15 15:27:22', '2026-02-13 09:19:28', '1dc4ff0a9a5bdffb59b7525ae33e279f94ef92d9e34ba033e8534c5b09904ab6', '2026-02-13 14:49:28'),
('stu_1001', 'dulani.wije@mail.lk', '$2y$10$A16a.cUAReAi82RmyLNdM.L5YX3QN411qJk16GGBkwzZkgFCWIBtm', 'student', 'Dulani', 'Wijesinghe', '2004-10-20', 'Ave Maria Convent', NULL, 'Negombo', 'Gampaha', 'No 45, Temple Road, Kandy', '200479405682', '22', '0766302421', 'female', 'uploads/profiles/stu_1001_1768553098.jpg', '2026-01-16', 1, 1, 'nic', '2026-01-16 08:44:58', '2026-02-13 09:06:54', '3df19b5469d4ce0c573edbfd44bb64778f2345abfdb0e8d8518732a511141d32', '2026-02-13 14:36:54'),
('stu_1002', 'sandu.fer99@webmail.com', '$2y$10$i/x6BIsIBHqEW4zwcq8Fq.2Wdb0k84uyYcOXO95oMFcQ7EOtxqmCm', 'student', 'Sanduni', 'Fernando', '2006-12-31', 'Rathnavali Balika Vidyalaya', NULL, 'Mirigama', 'Gampaha', '88/1, Galle Road, Hikkaduwa, Galle', '200686608910', '44', '44', 'female', 'uploads/profiles/stu_1002_1768553347.jpg', '2026-01-16', 1, 1, 'nic', '2026-01-16 08:49:07', '2026-01-27 08:32:44', NULL, NULL),
('stu_1003', 'Arul@gmail.com', '$2y$10$H2xdPCdGnfTsT4Aqv7xB5etwqcRYpPADc3s2YT2f4mPpg/qAYpHhi', 'student', 'Arul', 'Subramaniam', '2003-03-05', 'Sahira College Colombo', NULL, 'Anuradapura', 'Anuradhapura', 'No 15,Station Road,Anuradapura', '200306502233', '55', '55', 'male', 'uploads/profiles/stu_1003_1768553530.jpg', '2026-01-16', 1, 1, 'nic', '2026-01-16 08:52:10', '2026-01-27 08:33:02', NULL, NULL),
('tea_1000', 'nimal.perera@slacademy.lk', '$2y$10$IHIXsJTqIpedpTHJvdBF4O.DamEKGIy1OxQYuHOrx8LHjVRZGffAy', 'teacher', 'Nimal', 'Perera', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12345', '12345', NULL, 'uploads/profiles/tea_1000_1768549578.jpg', '2026-01-16', 1, 1, 'none', '2026-01-16 07:46:18', '2026-01-16 08:58:08', NULL, NULL),
('tea_1001', 'sanduni.jayasinghe@slacademy.lk', '$2y$10$MMKpGjR6ROIGt4PIBqPAReTIlfvn4g1LAsIim33RlEG3nbJVCId42', 'teacher', 'Sanduni', 'Jayasinghe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1234567', '0763255307', NULL, 'uploads/profiles/tea_1001_1768549667.jpg', '2026-01-16', 1, 1, 'none', '2026-01-16 07:47:47', '2026-02-13 09:27:39', '04ba95611b38cac2171a6b19eb2987cfe8fc1f00f83b8baf007d51c698d2cfaa', '2026-02-13 14:57:39'),
('tea_1003', 'ishara.wickramasinghe@slacademy.lk', '$2y$10$k7e5gNEHmmwTyQJ7nNlpReZSSRd52UhOeAsyccwZ0QYcbKnCJQLh6', 'teacher', 'Ishara', 'Fernando', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '123456789', '123456789', NULL, 'uploads/profiles/tea_1003_1768550903.jpg', '2026-01-16', 1, 1, 'none', '2026-01-16 08:08:23', '2026-01-16 08:08:23', NULL, NULL),
('tea_1004', 'kasun.amarasinghe@slacademy.lk', '$2y$10$g9h.bGilsuyxYwz.2uJN2erErM9.eUJRc4Idenz/bkXodi8g46Ih6', 'teacher', 'Kasun', 'Amarasinghe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1234567890', '1234567890', NULL, 'uploads/profiles/tea_1004_1768551273.jpg', '2026-01-16', 1, 1, 'none', '2026-01-16 08:14:33', '2026-01-16 08:14:33', NULL, NULL),
('tea_1005', 'tharindu.fernando@slacademy.lk', '$2y$10$NTdNtFc5mCUms09m2m/HEuhUpiuVD4QkV5RkPdqb/KpcWvPY0JF4y', 'teacher', 'Tharindu', 'Fernando', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1212', '1212', NULL, 'uploads/profiles/tea_1005_1768551769.jpg', '2026-01-16', 1, 1, 'none', '2026-01-16 08:22:49', '2026-01-16 08:22:49', NULL, NULL);

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
(8, 22, 'stu_1001', '2026-01-27 08:29:38', NULL);

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
(1, 16, 'Zoom Class 1', 'dsdsdsdsd', 'https://us05web.zoom.us/j/88041610928?pwd=QTbATsDefivm1pEEvbFQGN5OzYi2yq.1', '', '', '2026-01-30 13:44:00', '2026-01-30 12:47:33', '2026-01-30 13:05:35', 'ended', 0, '2026-01-30 07:15:06', '2026-01-30 07:35:35'),
(2, 16, 'dsdsd', 'dsadsadasd', 'https://us05web.zoom.us/j/83980760943?pwd=h57qINgFBiOaSknvpA0A4PE3Mm7fIm.1', '', '', '2026-01-30 13:13:00', '2026-01-30 13:13:58', '2026-01-30 13:53:11', 'ended', 0, '2026-01-30 07:43:54', '2026-01-30 08:23:11');

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
(1, 2, 'stu_1001', '4bc76022537f80afd4f3de5b4f7e232a.jpg', '697c68bed26ca_1769760958.jpg', 147381, 'image/jpeg', '2026-01-30 08:15:58');

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
(1, 1, 'tea_1001', '2026-01-30 12:47:33', '2026-01-30 12:56:41', 9, '2026-01-30 07:17:33'),
(2, 1, 'tea_1001', '2026-01-30 12:56:41', '2026-01-30 12:59:09', 2, '2026-01-30 07:26:41'),
(3, 1, 'tea_1001', '2026-01-30 12:59:09', '2026-01-30 13:01:03', 1, '2026-01-30 07:29:09'),
(4, 1, 'tea_1001', '2026-01-30 13:01:03', '2026-01-30 13:01:12', 0, '2026-01-30 07:31:03'),
(5, 1, 'tea_1001', '2026-01-30 13:01:12', '2026-01-30 13:01:39', 0, '2026-01-30 07:31:12'),
(6, 1, 'tea_1001', '2026-01-30 13:01:42', '2026-01-30 13:02:17', 0, '2026-01-30 07:31:42'),
(7, 1, 'tea_1001', '2026-01-30 13:02:17', '2026-01-30 13:02:43', 0, '2026-01-30 07:32:17'),
(8, 1, 'tea_1001', '2026-01-30 13:02:43', '2026-01-30 13:03:00', 0, '2026-01-30 07:32:43'),
(9, 1, 'tea_1001', '2026-01-30 13:03:03', '2026-01-30 13:03:17', 0, '2026-01-30 07:33:03'),
(10, 1, 'tea_1001', '2026-01-30 13:03:17', '2026-01-30 13:03:24', 0, '2026-01-30 07:33:17'),
(11, 1, 'tea_1001', '2026-01-30 13:03:24', '2026-01-30 13:05:35', 2, '2026-01-30 07:33:24'),
(12, 2, 'tea_1001', '2026-01-30 13:13:58', '2026-01-30 13:20:05', 6, '2026-01-30 07:43:58'),
(13, 2, 'tea_1001', '2026-01-30 13:44:26', '2026-01-30 13:47:15', 2, '2026-01-30 08:14:26'),
(14, 2, 'stu_1001', '2026-01-30 13:45:27', '2026-01-30 13:53:11', 7, '2026-01-30 08:15:27'),
(15, 2, 'tea_1001', '2026-01-30 13:47:15', '2026-01-30 13:47:51', 0, '2026-01-30 08:17:15'),
(16, 2, 'tea_1001', '2026-01-30 13:47:52', '2026-01-30 13:48:03', 0, '2026-01-30 08:17:52'),
(17, 2, 'tea_1001', '2026-01-30 13:48:03', '2026-01-30 13:48:38', 0, '2026-01-30 08:18:03'),
(18, 2, 'tea_1001', '2026-01-30 13:48:38', '2026-01-30 13:49:16', 0, '2026-01-30 08:18:38'),
(19, 2, 'tea_1001', '2026-01-30 13:49:16', '2026-01-30 13:49:35', 0, '2026-01-30 08:19:16'),
(20, 2, 'tea_1001', '2026-01-30 13:49:35', '2026-01-30 13:51:50', 2, '2026-01-30 08:19:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `physical_class_id` (`physical_class_id`,`student_id`);

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
-- Indexes for table `institute_wallet`
--
ALTER TABLE `institute_wallet`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_chats`
--
ALTER TABLE `course_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `course_payments`
--
ALTER TABLE `course_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `course_recordings`
--
ALTER TABLE `course_recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `course_uploads`
--
ALTER TABLE `course_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `enrollment_fees`
--
ALTER TABLE `enrollment_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `enrollment_payments`
--
ALTER TABLE `enrollment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `institute_wallet`
--
ALTER TABLE `institute_wallet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `live_class_participants`
--
ALTER TABLE `live_class_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `physical_classes`
--
ALTER TABLE `physical_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `question_answers`
--
ALTER TABLE `question_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `question_images`
--
ALTER TABLE `question_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `recordings`
--
ALTER TABLE `recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `recording_files`
--
ALTER TABLE `recording_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `streams`
--
ALTER TABLE `streams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stream_subjects`
--
ALTER TABLE `stream_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `student_enrollment`
--
ALTER TABLE `student_enrollment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `teacher_education`
--
ALTER TABLE `teacher_education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `teacher_wallet`
--
ALTER TABLE `teacher_wallet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `video_watch_log`
--
ALTER TABLE `video_watch_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `zoom_chat_messages`
--
ALTER TABLE `zoom_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zoom_classes`
--
ALTER TABLE `zoom_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `zoom_class_files`
--
ALTER TABLE `zoom_class_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `zoom_participants`
--
ALTER TABLE `zoom_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

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
