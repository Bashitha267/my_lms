-- Exam System Tables
-- Tables for managing exams, questions, and answers

-- Table: exams
-- Stores exam information created by teachers
CREATE TABLE IF NOT EXISTS `exams` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `teacher_id` VARCHAR(20) NOT NULL COMMENT 'FK to users.user_id',
  `subject_id` INT(11) NOT NULL COMMENT 'FK to subjects.id',
  `title` VARCHAR(255) NOT NULL COMMENT 'Exam title',
  `duration_minutes` INT NOT NULL DEFAULT 60 COMMENT 'Duration in minutes',
  `deadline` DATETIME NOT NULL COMMENT 'Exam deadline',
  `is_published` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=draft, 1=published',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT 'Exam status',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  CONSTRAINT `fk_exams_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_exams_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_teacher_id` (`teacher_id`),
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_is_published` (`is_published`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores exam information';

-- Table: exam_questions
-- Stores questions for each exam
CREATE TABLE IF NOT EXISTS `exam_questions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `exam_id` INT(11) NOT NULL COMMENT 'FK to exams.id',
  `question_text` TEXT NOT NULL COMMENT 'Question text content',
  `question_image` VARCHAR(255) DEFAULT NULL COMMENT 'Optional question image path',
  `question_type` ENUM('single', 'multiple') NOT NULL DEFAULT 'single' COMMENT 'single=one correct answer, multiple=multiple correct answers',
  `order_index` INT NOT NULL DEFAULT 0 COMMENT 'Question display order',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_exam_id` (`exam_id`),
  INDEX `idx_order_index` (`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores exam questions';

-- Table: question_answers
-- Stores answer options for each question
CREATE TABLE IF NOT EXISTS `question_answers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `question_id` INT(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `answer_text` TEXT NOT NULL COMMENT 'Answer text content',
  `answer_image` VARCHAR(255) DEFAULT NULL COMMENT 'Optional answer image path',
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=wrong, 1=correct',
  `order_index` INT NOT NULL DEFAULT 0 COMMENT 'Answer display order',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
  
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_question_id` (`question_id`),
  INDEX `idx_is_correct` (`is_correct`),
  INDEX `idx_order_index` (`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores question answer options';

-- Table: question_images
-- Stores multiple images for each question
CREATE TABLE IF NOT EXISTS `question_images` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `question_id` INT(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `image_path` VARCHAR(255) NOT NULL COMMENT 'Image file path',
  `order_index` INT NOT NULL DEFAULT 0 COMMENT 'Image display order',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  
  CONSTRAINT `fk_images_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_question_id` (`question_id`),
  INDEX `idx_order_index` (`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores multiple images per question';

-- Table: exam_attempts
-- Stores student exam attempts (one per student per exam)
CREATE TABLE IF NOT EXISTS `exam_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `exam_id` INT(11) NOT NULL COMMENT 'FK to exams.id',
  `student_id` VARCHAR(20) NOT NULL COMMENT 'FK to users.user_id',
  `start_time` DATETIME NOT NULL COMMENT 'When student started the exam',
  `end_time` DATETIME DEFAULT NULL COMMENT 'When student submitted or time expired',
  `score` DECIMAL(5,2) DEFAULT NULL COMMENT 'Score percentage',
  `correct_count` INT DEFAULT 0 COMMENT 'Number of correct answers',
  `total_questions` INT DEFAULT 0 COMMENT 'Total questions in exam',
  `status` ENUM('in_progress', 'completed', 'expired') NOT NULL DEFAULT 'in_progress' COMMENT 'Attempt status',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  
  CONSTRAINT `fk_attempts_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  UNIQUE KEY `unique_student_exam` (`exam_id`, `student_id`),
  INDEX `idx_exam_id` (`exam_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores student exam attempts';

-- Table: student_answers
-- Stores answers selected by students
CREATE TABLE IF NOT EXISTS `student_answers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key',
  `attempt_id` INT(11) NOT NULL COMMENT 'FK to exam_attempts.id',
  `question_id` INT(11) NOT NULL COMMENT 'FK to exam_questions.id',
  `answer_id` INT(11) NOT NULL COMMENT 'FK to question_answers.id',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  
  CONSTRAINT `fk_student_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_answers_answer` FOREIGN KEY (`answer_id`) REFERENCES `question_answers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_attempt_id` (`attempt_id`),
  INDEX `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores student selected answers';
