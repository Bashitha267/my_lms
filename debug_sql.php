<?php
require_once 'config.php';

$query = "
    SELECT DISTINCT s.id, s.name, s.code
    FROM subjects s
    JOIN instructor_subjects is_sub ON s.id = is_sub.subject_id
    WHERE 1=1
    ORDER BY s.name
";

echo "Testing Query:\n$query\n\n";

$result = $conn->query($query);

if ($result === false) {
    echo "Query Failed!\n";
    echo "Error: " . $conn->error . "\n";
    echo "Errno: " . $conn->errno . "\n";
} else {
    echo "Query Successful!\n";
    echo "Rows: " . $result->num_rows . "\n";
}
?>
