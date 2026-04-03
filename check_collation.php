<?php
$mysqli = new mysqli("localhost", "root", "", "lms");
if ($mysqli->connect_error) {
    die("Connect Error: " . $mysqli->connect_error);
}

$tables = ['users', 'student_enrollment', 'enrollment_payments', 'monthly_payments', 'course_payments', 'instructor_payments', 'instructor_requests'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $res = $mysqli->query("SHOW FULL COLUMNS FROM $table");
    while ($row = $res->fetch_assoc()) {
        if ($row['Collation']) {
            echo "{$row['Field']}: {$row['Collation']}\n";
        }
    }
}
$mysqli->close();
