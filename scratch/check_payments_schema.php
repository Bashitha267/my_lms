<?php
$c = new mysqli('localhost', 'root', '', 'lms');
$res = $c->query("DESCRIBE instructor_payments");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$c->close();
?>
