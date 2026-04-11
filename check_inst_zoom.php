<?php
require 'config.php';
$r = $conn->query('SELECT id, zoom_link, zoom_meeting_id, status, accepted_by, student_id FROM instructor_requests WHERE zoom_link IS NOT NULL LIMIT 5');
if ($r) { while($row=$r->fetch_assoc()) print_r($row); } else echo $conn->error;
echo "\n---All paid requests---\n";
$r2 = $conn->query("SELECT id, student_id, accepted_by, status, zoom_link FROM instructor_requests WHERE status IN ('paid','payment_pending') LIMIT 10");
if ($r2) { while($row=$r2->fetch_assoc()) print_r($row); } else echo $conn->error;
?>
