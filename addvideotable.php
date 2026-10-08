<?php
// create_homepage_videos_table.php - Migration script to create homepage_videos table
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die("Error: config.php not found.");
}

$u_dir = __DIR__ . '/uploads/videos/';
if (!file_exists($u_dir)) {
    mkdir($u_dir, 0777, true);
}

$sql = "
CREATE TABLE IF NOT EXISTS `homepage_videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `video_type` VARCHAR(50) NOT NULL UNIQUE,
  `video_path` VARCHAR(255) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "<div style='font-family:sans-serif;padding:30px;background:#1e293b;color:#f8fafc;border-radius:16px;max-width:600px;margin:50px auto;box-shadow:0 20px 25px -5px rgba(0,0,0,0.5);'>";
    echo "<h2 style='color:#10b981;margin-top:0;'>✅ Success!</h2>";
    echo "<p>Table <strong>homepage_videos</strong> has been created or updated successfully.</p>";
    echo "<p>Upload directory <strong>uploads/videos/</strong> is ready.</p>";
    echo "<a href='admin/settings' style='display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-top:15px;'>Go to Admin Settings &rarr;</a>";
    echo "</div>";
} else {
    echo "<div style='font-family:sans-serif;padding:30px;background:#1e293b;color:#f8fafc;border-radius:16px;max-width:600px;margin:50px auto;'>";
    echo "<h2 style='color:#ef4444;margin-top:0;'>❌ Error</h2>";
    echo "<p>" . htmlspecialchars($conn->error) . "</p>";
    echo "</div>";
}
?>
