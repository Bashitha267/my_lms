<?php
require_once 'config.php';

$query = "
    SELECT DISTINCT s.id, s.name, s.code
    FROM subjects s
    JOIN instructor_subjects is_sub ON s.id = is_sub.subject_id
    WHERE s.status = 1
    ORDER BY s.name
";

$result = $conn->query($query);

if ($result) {
    echo "Query Successful! Rows: " . $result->num_rows . "\n";
} else {
    echo "Query FAILED: " . $conn->error . "\n";
}
?>
