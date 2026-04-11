<?php
require_once 'config.php';

// Create instructor_sessions table — completely separate from zoom_classes
$sql = "CREATE TABLE IF NOT EXISTS instructor_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    zoom_link VARCHAR(500) DEFAULT NULL,
    zoom_meeting_id VARCHAR(100) DEFAULT NULL,
    zoom_password VARCHAR(100) DEFAULT NULL,
    status ENUM('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    started_at DATETIME DEFAULT NULL,
    ended_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request (request_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "✅ instructor_sessions table created!\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Migrate existing zoom data from instructor_requests into the new table
$migrate = "INSERT IGNORE INTO instructor_sessions (request_id, zoom_link, zoom_meeting_id, zoom_password, status)
            SELECT id, zoom_link, zoom_meeting_id, zoom_password,
                   CASE WHEN status = 'completed' THEN 'completed' ELSE 'scheduled' END
            FROM instructor_requests
            WHERE zoom_link IS NOT NULL AND zoom_link != ''";
if ($conn->query($migrate)) {
    echo "✅ Migrated " . $conn->affected_rows . " existing session(s) to instructor_sessions!\n";
} else {
    echo "Migration error: " . $conn->error . "\n";
}

echo "Done!\n";
?>
