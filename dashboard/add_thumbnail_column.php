<?php
require_once '../config.php';
// Add thumbnail_url column if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM course_recordings LIKE 'thumbnail_url'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE course_recordings ADD COLUMN thumbnail_url VARCHAR(500) DEFAULT NULL AFTER video_path")) {
        echo "thumbnail_url column added successfully.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column already exists.";
}
?>
