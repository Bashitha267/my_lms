<?php
// deduplicate_subjects.php
// Scans for duplicate subject names in `subjects` table, merges references in `stream_subjects`, and removes duplicate subject entries.

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Deduplicate & Clean LMS Subjects Data</h2>";

if ($conn->connect_error) {
    die("<p style='color:red;'>Database Connection Failed: " . htmlspecialchars($conn->connect_error) . "</p>");
}

$conn->set_charset("utf8mb4");

// 1. Find duplicate subject names
$duplicates_query = "
    SELECT TRIM(name) as clean_name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id ASC) as ids
    FROM subjects
    GROUP BY TRIM(name)
    HAVING cnt > 1
";

$res = $conn->query($duplicates_query);
$consolidated_count = 0;
$removed_subjects_count = 0;

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $clean_name = $row['clean_name'];
        $ids = explode(',', $row['ids']);
        $master_id = intval($ids[0]); // Keep the lowest ID as master
        $duplicate_ids = array_map('intval', array_slice($ids, 1));

        echo "<div style='font-family:sans-serif; margin-bottom:10px; padding:10px; background:#fffaf0; border:1px solid #feebc8; border-radius:6px;'>";
        echo "<strong>Subject:</strong> " . htmlspecialchars($clean_name) . "<br>";
        echo "Master ID: <code>$master_id</code> | Duplicate IDs: <code>" . implode(', ', $duplicate_ids) . "</code><br>";

        foreach ($duplicate_ids as $dup_id) {
            // Update stream_subjects to point to master_id
            $ss_query = "SELECT id, stream_id FROM stream_subjects WHERE subject_id = $dup_id";
            $ss_res = $conn->query($ss_query);
            if ($ss_res) {
                while ($ss_row = $ss_res->fetch_assoc()) {
                    $ss_id = $ss_row['id'];
                    $st_id = $ss_row['stream_id'];

                    // Check if master_id is already linked to this stream
                    $chk_exist = $conn->query("SELECT id FROM stream_subjects WHERE stream_id = $st_id AND subject_id = $master_id");
                    if ($chk_exist && $chk_exist->num_rows > 0) {
                        // Delete duplicate stream_subject link
                        $conn->query("DELETE FROM stream_subjects WHERE id = $ss_id");
                    } else {
                        // Re-point stream_subject to master_id
                        $conn->query("UPDATE stream_subjects SET subject_id = $master_id WHERE id = $ss_id");
                    }
                }
            }

            // Update teacher_assignments if applicable
            $conn->query("UPDATE teacher_assignments ta
                          JOIN stream_subjects ss_old ON ta.stream_subject_id = ss_old.id
                          SET ta.stream_subject_id = (
                              SELECT id FROM stream_subjects WHERE stream_id = ss_old.stream_id AND subject_id = $master_id LIMIT 1
                          )
                          WHERE ss_old.subject_id = $dup_id");

            // Delete duplicate row from subjects table
            $conn->query("DELETE FROM subjects WHERE id = $dup_id");
            $removed_subjects_count++;
        }

        // Update clean name on master record
        $stmt_upd = $conn->prepare("UPDATE subjects SET name = ? WHERE id = ?");
        $stmt_upd->bind_param("si", $clean_name, $master_id);
        $stmt_upd->execute();
        $stmt_upd->close();

        $consolidated_count++;
        echo "<span style='color:green;'>Merged & Cleaned!</span></div>";
    }
} else {
    echo "<p style='color:green; font-weight:bold;'>No duplicate subject names found in database!</p>";
}

echo "<div style='font-family:sans-serif; padding:15px; background:#e6fffa; border:1px solid #319795; border-radius:8px; color:#234e52; margin-top:20px;'>";
echo "<h3>✅ Subject Cleanup Complete!</h3>";
echo "<ul>";
echo "<li>Consolidated <strong>$consolidated_count</strong> subject groups.</li>";
echo "<li>Removed <strong>$removed_subjects_count</strong> duplicate subject records from `subjects`.</li>";
echo "</ul>";
echo "</div>";
?>
