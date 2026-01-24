-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2026 at 08:56 PM
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
(18, 16, 'stu_1000', 'student', 'hello madam', NULL, 'sent', '2026-01-15 17:01:17', '2026-01-15 17:01:17'),
(19, 16, 'stu_1001', 'student', 'hii', NULL, 'sent', '2026-01-15 19:44:21', '2026-01-15 19:44:21');

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
(1, 'tea_1000', 'Python', 'jjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj', 10000.00, 'uploads/courses/course_6969252b35dbc.jpg', 1, '2026-01-15 23:04:35'),
(2, 'tea_1000', 'Java', 'kkkkkkkkkkkkkkkkkkkk', 15000.00, 'uploads/courses/course_696932054b9fb.webp', 1, '2026-01-15 23:59:25');

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
(2, 3, 'stu_1000', 'hellp', '2026-01-16 00:12:53'),
(3, 3, 'tea_1000', 'hello', '2026-01-16 00:44:20');

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
(1, 1, 'stu_1000', '2026-01-15 23:47:58', 'active', 'pending'),
(3, 2, 'stu_1000', '2026-01-16 00:00:32', 'active', 'paid'),
(4, 1, 'stu_1001', '2026-01-16 01:12:08', 'active', 'pending'),
(5, 2, 'stu_1001', '2026-01-16 01:12:48', 'active', 'pending');

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
(1, 3, 15000.00, 'bank_transfer', 'paid', 'uploads/payments/course_pay_stu_1000_1768501903.jpg', 'image', NULL, NULL, '2026-01-15 18:31:43', '2026-01-15 18:42:21'),
(2, 4, 10000.00, 'bank_transfer', 'pending', 'uploads/payments/course_pay_stu_1001_1768506150.jpg', 'image', NULL, NULL, '2026-01-15 19:42:30', NULL),
(3, 5, 15000.00, 'bank_transfer', 'pending', 'uploads/payments/course_pay_stu_1001_1768506175.jpg', 'image', NULL, NULL, '2026-01-15 19:42:55', NULL);

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
(3, 2, 'Java 1', 'dsdsad', 'https://youtu.be/TDkH3EbWTYc?si=479qN6UWUhOzW1Du', 'https://img.youtube.com/vi/TDkH3EbWTYc/mqdefault.jpg', 0, 2, '2026-01-16 00:02:35');

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

--
-- Dumping data for table `course_uploads`
--

INSERT INTO `course_uploads` (`id`, `course_id`, `title`, `file_path`, `uploaded_at`) VALUES
(1, 1, 'testing', 'uploads/courses/resources/res_69692d15adca7_Happy Pongal wishes greetings South Indian harvest festival celebration background with Pongal pot.jpeg', '2026-01-15 23:38:21'),
(2, 1, 'testing', 'uploads/courses/resources/res_69692e22af0b7_Happy Pongal wishes greetings South Indian harvest festival celebration background with Pongal pot.jpeg', '2026-01-15 23:42:50'),
(3, 1, 'testing', 'uploads/courses/resources/res_69692f188dee5_Happy Pongal wishes greetings South Indian harvest festival celebration background with Pongal pot.jpeg', '2026-01-15 23:46:56');

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
(4, 7, 1500.00, 2000.00, '2026-01-15 16:52:50', '2026-01-15 16:52:50');

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
(4, 9, 1500.00, 'bank_transfer', 'paid', NULL, NULL, 'uploads/payments/payment_stu_1000_1768496090.jpg', 'image', NULL, NULL, NULL, '2026-01-15 16:54:50', '2026-01-15 16:56:07');

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
(4, 9, 1, 2026, 2000.00, 'bank_transfer', 'paid', NULL, NULL, 'uploads/payments/payment_stu_1000_1768496501.jpg', 'image', NULL, NULL, NULL, '2026-01-15 17:01:41', '2026-01-15 17:08:34');

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
(15, 7, 0, 'Chapter 1', 'sadadddddddddddddffffffffffffffffffffffffffffffff', 'DkPQRSVeHG8', 'https://youtu.be/DkPQRSVeHG8?si=gW7NWLS2KZtIc5DK', NULL, 'https://img.youtube.com/vi/DkPQRSVeHG8/maxresdefault.jpg', 0, 0, 3, 'active', NULL, NULL, NULL, '2026-01-14 18:30:00', '2026-01-15 16:59:41'),
(16, 7, 0, 'Chapter 2', 'sssssssssssssssssssssssssssssssssssaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'daF9R6dadH8', 'https://youtu.be/daF9R6dadH8?si=52rsURsIpG7ucuDI', NULL, 'https://img.youtube.com/vi/daF9R6dadH8/maxresdefault.jpg', 0, 1, 3, 'active', NULL, NULL, NULL, '2026-01-14 18:30:00', '2026-01-15 17:00:28');

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
(3, 16, 'stu_1000', 'pongal post.jpg', 'uploads/recordings/16/stu_1000_1768496465_69691d5139891.jpg', 220965, 'image/jpeg', 'jpg', '2026-01-15 17:01:05', 1),
(4, 16, 'stu_1001', 'pongal post.jpg', 'uploads/recordings/16/stu_1001_1768506291_696943b3ef09f.jpg', 220965, 'image/jpeg', 'jpg', '2026-01-15 19:44:51', 1);

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
(11, 'A/L Commerce 2026 A/L', NULL, 1, '2026-01-15 16:32:44', '2026-01-15 16:32:44'),
(12, 'Combined Mathematics 2026 A/L', NULL, 1, '2026-01-15 16:47:13', '2026-01-15 16:47:13');

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
(38, 12, 16, 1, '2026-01-15 16:49:03', '2026-01-15 16:49:03');

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
(9, 'stu_1000', 38, 2026, NULL, '2026-01-15', 'active', 'pending', NULL, NULL, NULL, NULL, '2026-01-15 16:54:05', '2026-01-15 16:54:05'),
(10, 'stu_1001', 38, 2026, NULL, '2026-01-16', 'active', 'pending', NULL, NULL, NULL, NULL, '2026-01-15 19:40:21', '2026-01-15 19:40:21');

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
(16, 'Physics', '', NULL, 1, '2026-01-15 16:49:03', '2026-01-15 16:49:03');

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
(3, 'recordings_background', 'uploads/backgrounds/recordings_bg_1767089340.jpeg', 'image', 'Background image for recordings page', '2025-12-30 10:09:00', 'adm_0001');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links teachers to specific stream-subject offerings with academic year support';

--
-- Dumping data for table `teacher_assignments`
--

INSERT INTO `teacher_assignments` (`id`, `teacher_id`, `stream_subject_id`, `academic_year`, `batch_name`, `status`, `assigned_date`, `start_date`, `end_date`, `notes`, `created_at`, `updated_at`) VALUES
(7, 'tea_1000', 38, 2026, NULL, 'active', '2026-01-15', NULL, NULL, NULL, '2026-01-15 16:49:36', '2026-01-15 16:49:36');

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
(3, 'tea_1000', 'B.Sc in Maths', 'University of Colombo', 2022, '', '', NULL, '2026-01-15 16:49:36', '2026-01-15 16:49:36');

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
('adm_1000', 'admin@example.com', '$2y$10$RTf99yYEtlRzGRLjlZQJaO8yRJPyCwiF3SFFEIMErqz7zkzCzE0fS', 'admin', 'System', 'Admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1234', '1234', NULL, NULL, '2026-01-15', 1, 1, 'none', '2026-01-15 15:27:22', '2026-01-15 19:48:56', NULL, NULL),
('stu_1000', 'sunil@gmail.com', '$2y$10$uau3I3FmKeZiCxl0B7aPjufQxZiBJ6rapfT0Ay1H.SRx0IaXka.Zy', 'student', 'Sunil', 'Perera', '2002-05-26', 'dsdsd', NULL, 'Gampaha', 'Galle', '68/17, Isuru Mw, Yakkala', '200214702390', '123456', '123456', 'male', 'uploads/profiles/stu_1000_1768496045.jpg', '2026-01-15', 1, 1, 'nic', '2026-01-15 16:54:05', '2026-01-15 17:12:46', 'ea817d643d9d13e699ec84193d3095ccc6bbb4489f584e2b1fc9c22dd6d3539b', '2026-01-15 22:42:46'),
('stu_1001', 'sara@gmail.com', '$2y$10$qPWGZ0fvcMjrlYM.57ixH.4ev4TP06hj1Aqp/kJ7Knxki7QAVmLTm', 'student', 'Sara', 'Perera', '2002-05-26', 'ssadsdasd', NULL, 'Badulla', 'Badulla', 'sdsdsdsdsd', '200214702390', '1234567', '1234567', 'male', 'uploads/profiles/stu_1001_1768506021.jpg', '2026-01-16', 1, 1, 'nic', '2026-01-15 19:40:21', '2026-01-15 19:40:31', '5de83341bde230c383f78359c3c7f25c52e4e2be0aec979e5136f74f5ae8aaa5', '2026-01-16 01:10:31'),
('tea_1000', 'Kamali@gmail.com', '$2y$10$1ExLSvGCpXWyEQBtQbXxHuwM65U0zVdYCGduQkioiu.FvZOtAbPPy', 'teacher', 'Kamali', 'Perera', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12345', '12345', NULL, 'uploads/profiles/tea_1000_1768495776.jpg', '2026-01-15', 1, 1, 'none', '2026-01-15 16:49:36', '2026-01-15 19:50:11', NULL, NULL);

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
(4, 16, 'stu_1000', '2026-01-15 17:00:43', NULL),
(5, 16, 'stu_1001', '2026-01-15 19:44:08', NULL);

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `course_chats`
--
ALTER TABLE `course_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `course_payments`
--
ALTER TABLE `course_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_recordings`
--
ALTER TABLE `course_recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_uploads`
--
ALTER TABLE `course_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `enrollment_fees`
--
ALTER TABLE `enrollment_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `enrollment_payments`
--
ALTER TABLE `enrollment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `live_class_participants`
--
ALTER TABLE `live_class_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `recordings`
--
ALTER TABLE `recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `recording_files`
--
ALTER TABLE `recording_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `streams`
--
ALTER TABLE `streams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `stream_subjects`
--
ALTER TABLE `stream_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `student_enrollment`
--
ALTER TABLE `student_enrollment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `teacher_education`
--
ALTER TABLE `teacher_education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `video_watch_log`
--
ALTER TABLE `video_watch_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', AUTO_INCREMENT=6;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
