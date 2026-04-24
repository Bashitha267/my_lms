<?php
$c = new mysqli('localhost', 'root', '', 'lms');
$res = $c->query("SELECT user_id, hourly_rate FROM users WHERE user_id = 'ins_1000'");
print_r($res->fetch_assoc());
$c->close();
?>
