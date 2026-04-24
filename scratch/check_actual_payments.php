<?php
$c = new mysqli('localhost', 'root', '', 'lms');
$res = $c->query("SELECT * FROM instructor_payments ORDER BY id DESC LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$c->close();
?>
