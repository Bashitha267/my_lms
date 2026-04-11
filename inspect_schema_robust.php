<?php
$c = new mysqli('localhost', 'root', '', 'lms');
$ts = ['instructor_requests', 'instructor_subjects', 'users', 'subjects', 'zoom_classes', 'instructor_request_acceptances'];
foreach($ts as $t) {
    echo "\nTable: $t\n";
    $r = $c->query("DESCRIBE $t");
    if($r) {
        while($row = $r->fetch_assoc()) {
            echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
        }
    } else {
        echo "  Error describing $t: " . $c->error . "\n";
    }
}
?>
