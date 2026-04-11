<?php
$c = new mysqli('localhost', 'root', '', 'lms');
if ($c->connect_error) {
    die("Connection failed: " . $c->connect_error);
}

function describe_table($c, $t) {
    echo "\nTable: $t\n";
    $r = $c->query("DESCRIBE $t");
    if($r) {
        while($row = $r->fetch_assoc()) {
            echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
        }
    } else {
        echo "  Error: " . $c->error . "\n";
    }
}

$ts = ['instructor_requests', 'instructor_request_acceptances', 'users', 'instructor_subjects', 'live_classes'];
foreach($ts as $t) {
    describe_table($c, $t);
}

echo "\nSample Data from instructor_requests:\n";
$r = $c->query("SELECT * FROM instructor_requests LIMIT 5");
if ($r) {
    while($row = $r->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "Error fetching instructor_requests: " . $c->error . "\n";
}
?>
