<?php
require 'config.php';
$query = "SELECT al.*, u.first_name, u.second_name 
          FROM al_exam_submissions al 
          JOIN users u ON al.student_id = u.user_id 
          WHERE al.agreed_to_publish = 1 
          ORDER BY al.island_rank ASC 
          LIMIT 8";
$result = $conn->query($query);
$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}
echo json_encode($data);
