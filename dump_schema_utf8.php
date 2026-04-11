<?php
$conn = new mysqli('localhost', 'root', '', 'lms');
$data = [];
$res = $conn->query("DESCRIBE instructor_requests");
while($row = $res->fetch_assoc()) $data['requests'][] = $row;
$res = $conn->query("DESCRIBE instructor_request_acceptances");
if($res) while($row = $res->fetch_assoc()) $data['acceptances'][] = $row;
file_put_contents('schema_utf8.json', json_encode($data, JSON_PRETTY_PRINT));
?>
