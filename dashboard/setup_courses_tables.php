<?php
require_once 'config.php';

// disable error reporting for clean output
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Setting up Online Courses tables...<br>";

$queries = [
    // Courses Table
    "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) DEFAULT 0.00,
        cover_image VARCHAR(255),
        status TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Course Enrollments
    "CREATE TABLE IF NOT EXISTS course_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive') DEFAULT 'active',
        payment_status ENUM('paid', 'pending', 'free') DEFAULT 'pending',
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE KEY unique_enrollment (course_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Course Recordings (Videos)
    "CREATE TABLE IF NOT EXISTS course_recordings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        video_path VARCHAR(255) NOT NULL,
        is_free TINYINT(1) DEFAULT 0,
        views INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Course Chats
    "CREATE TABLE IF NOT EXISTS course_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_recording_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_recording_id) REFERENCES course_recordings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Course Uploads (Resources)
    "CREATE TABLE IF NOT EXISTS course_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($queries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Query executed successfully.<br>";
    } else {
        echo "Error executing query: " . $conn->error . "<br>";
    }
}

echo "Tables setup complete.";
?>
