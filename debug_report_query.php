<?php
require_once 'config.php';

$current_month = date('Y-m');
$report_query = "
    SELECT 
        u.user_id, 
        CONCAT(u.first_name, ' ', u.second_name) as name,
        COUNT(ir.id) as total_accepted
    FROM users u
    LEFT JOIN instructor_requests ir ON u.user_id = ir.accepted_by 
        AND DATE_FORMAT(ir.accepted_at, '%Y-%m') = ? 
        AND ir.status = 'accepted'
    WHERE u.role = 'instructor' AND u.status = 1
    GROUP BY u.user_id
    ORDER BY total_accepted DESC
";

$stmt = $conn->prepare($report_query);
if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
} else {
    echo "Prepare success!";
}
?>
