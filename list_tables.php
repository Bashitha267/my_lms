<?php
$c = new mysqli('localhost', 'root', '', 'lms');
if ($c->connect_error) {
    die("Connection failed: " . $c->connect_error);
}
$r = $c->query("SHOW TABLES");
if ($r) {
    while($row = $r->fetch_array()) {
        echo $row[0] . "\n";
    }
} else {
    echo "Error: " . $c->error;
}
?>
