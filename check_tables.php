<?php
require_once 'config.php';
$res = $conn->query("SHOW TABLES LIKE 'instructor_requests'");
if ($res->num_rows > 0) {
    echo "Table 'instructor_requests' EXISTS.\n";
} else {
    echo "Table 'instructor_requests' DOES NOT EXIST.\n";
}
?>
