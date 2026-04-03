<?php
include 'config.php';
$my_subjects_query = "SELECT subject_id FROM instructor_subjects WHERE instructor_id = 'test'";
$pending_query = "
    SELECT 
        ir.id, ir.created_at, ir.request_note, ir.session_date,
        s.name as subject_name,
        u.first_name as student_name
    FROM instructor_requests ir
    JOIN subjects s ON ir.subject_id = s.id
    JOIN users u ON ir.student_id = u.user_id
    WHERE ir.status = 'pending' 
    AND ir.subject_id IN ($my_subjects_query)
    ORDER BY ir.created_at DESC
";
$res = $conn->query($pending_query);
if (!$res) {
    echo "SQL ERROR: " . $conn->error . "\n";
} else {
    echo "Query OK.\n";
}
?>
