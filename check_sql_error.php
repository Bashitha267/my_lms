<?php
require 'config.php';
$sql = "
    SELECT 
        ir.id as request_id,
        ir.session_date,
        ir.created_at,
        s.name as subject_name,
        u.first_name, u.second_name, u.profile_picture,
        isess.status as session_status,
        isess.ended_at,
        rat.rating, rat.review
    FROM instructor_requests ir
    JOIN subjects s ON ir.subject_id = s.id
    JOIN users u ON ir.student_id = u.user_id
    LEFT JOIN instructor_sessions isess ON isess.request_id = ir.id
    LEFT JOIN instructor_ratings rat ON rat.request_id = ir.id
    WHERE ir.accepted_by = 'test'
      AND ir.status IN ('completed', 'paid')
    ORDER BY COALESCE(isess.ended_at, ir.created_at) DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "SQL ERROR: " . $conn->error . "\n";
} else {
    echo "SQL OK\n";
}
?>
