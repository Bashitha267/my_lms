<?php
require_once 'config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$report_query = "
    SELECT 
        u.user_id 
    FROM users u
    LEFT JOIN instructor_requests ir ON u.user_id = ir.accepted_by 
";

echo "Testing JOIN query:\n";
$stmt = $conn->prepare($report_query);
if ($stmt) {
    echo "Prepare 1 OK\n";
} else {
    echo "Prepare 1 FAILED: " . $conn->error . "\n";
}

$full_query = "
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

echo "\nTesting FULL query:\n";
$stmt = $conn->prepare($full_query);
if ($stmt) {
    echo "Prepare 2 OK\n";
} else {
    echo "Prepare 2 FAILED: " . $conn->error . "\n";
}
?>
