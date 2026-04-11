<?php
session_start();
require_once 'config.php';
$user_id = $_SESSION['user_id'] ?? '';

// Show all instructor_payments for current student's requests
$res = $conn->query("
    SELECT ip.*, ir.status as req_status, ir.accepted_by
    FROM instructor_payments ip
    JOIN instructor_requests ir ON ip.request_id = ir.id
    WHERE ip.student_id = '$user_id'
    OR ir.student_id = '$user_id'
");
echo "<h2>instructor_payments rows for this student:</h2><pre>";
if ($res) {
    while($r = $res->fetch_assoc()) { print_r($r); }
} else {
    echo "Error: " . $conn->error;
}
echo "</pre>";

// Also show what the join produces
$res2 = $conn->query("
    SELECT ira.request_id, ira.instructor_id, ip.status as pay_status, ip.student_id as pay_student
    FROM instructor_request_acceptances ira
    LEFT JOIN instructor_payments ip ON ira.request_id = ip.request_id AND ira.instructor_id = ip.instructor_id
    JOIN instructor_requests ir ON ira.request_id = ir.id
    WHERE ir.student_id = '$user_id'
");
echo "<h2>Join result (acceptances + payments):</h2><pre>";
if ($res2) {
    while($r = $res2->fetch_assoc()) { print_r($r); }
} else {
    echo "Error: " . $conn->error;
}
echo "</pre>";
?>
