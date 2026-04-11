<?php
$c = new mysqli('localhost', 'root', '', 'lms');
$ts = ['instructor_requests', 'instructor_subjects', 'users', 'subjects', 'zoom_classes', 'instructor_payments', 'instructor_request_acceptances'];
foreach($ts as $t) {
    $r = $c->query("SHOW TABLES LIKE '$t'");
    echo "$t: " . ($r->num_rows > 0 ? "found" : "not found") . "\n";
}
?>
