<?php
$conn = new mysqli('localhost', 'root', '', 'lms');
if ($conn->connect_error) die($conn->connect_error);
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) echo $row[0]."\n";
echo "--- instructor_requests schema ---\n";
$res = $conn->query("DESCRIBE instructor_requests");
while($row = $res->fetch_assoc()) print_r($row);
echo "--- instructor_request_acceptances schema ---\n";
$res = $conn->query("DESCRIBE instructor_request_acceptances");
if($res) while($row = $res->fetch_assoc()) print_r($row);
else echo "instructor_request_acceptances does not exist\n";
?>
