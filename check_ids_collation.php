<?php
$conn = new mysqli("localhost", "root", "", "lms");
$tables = ['users', 'student_enrollment', 'enrollment_payments', 'monthly_payments', 'course_payments', 'instructor_payments', 'instructor_requests'];
foreach ($tables as $table) {
    echo "\nTABLE: $table\n";
    $res = $conn->query("SHOW FULL COLUMNS FROM $table");
    while ($row = $res->fetch_assoc()) {
        if ($row['Field'] == 'user_id' || $row['Field'] == 'student_id' || $row['Field'] == 'teacher_id') {
            printf("%-15s %-20s\n", $row['Field'], $row['Collation']);
        }
    }
}
$conn->close();
