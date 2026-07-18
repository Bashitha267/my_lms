<?php
// Redirect legacy register.php to student_registration.php preserving query parameters
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: student_registration.php" . $queryString);
exit();
