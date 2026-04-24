<?php
$c = new mysqli('localhost', 'root', '', 'lms');
$res = $c->query("SELECT * FROM instructor_wallet");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$c->close();
?>
