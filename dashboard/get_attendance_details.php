<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($class_id <= 0 || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$response = [
    'success' => true,
    'class_title' => '',
    'attendees' => []
];

try {
    if ($type === 'Zoom') {
        $stmt = $conn->prepare("SELECT title FROM zoom_classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class = $stmt->get_result()->fetch_assoc();
        $response['class_title'] = $class['title'] ?? 'Unknown Zoom Class';
        $stmt->close();

        $attendee_query = "SELECT u.first_name, u.second_name, u.user_id, zp.join_time 
                          FROM zoom_participants zp
                          JOIN users u ON zp.user_id = u.user_id
                          WHERE zp.zoom_class_id = ?
                          ORDER BY zp.join_time ASC";
        $stmt = $conn->prepare($attendee_query);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $response['attendees'][] = [
                'name' => $row['first_name'] . ' ' . $row['second_name'],
                'id' => $row['user_id'],
                'time' => date('H:i:s', strtotime($row['join_time']))
            ];
        }
        $stmt->close();

    } elseif ($type === 'Physical') {
        $stmt = $conn->prepare("SELECT title FROM physical_classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class = $stmt->get_result()->fetch_assoc();
        $response['class_title'] = $class['title'] ?? 'Unknown Physical Class';
        $stmt->close();

        $attendee_query = "SELECT u.first_name, u.second_name, u.user_id, a.attended_at 
                          FROM attendance a
                          JOIN users u ON a.student_id = u.user_id
                          WHERE a.physical_class_id = ?
                          ORDER BY a.attended_at ASC";
        $stmt = $conn->prepare($attendee_query);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $response['attendees'][] = [
                'name' => $row['first_name'] . ' ' . $row['second_name'],
                'id' => $row['user_id'],
                'time' => date('H:i:s', strtotime($row['attended_at']))
            ];
        }
        $stmt->close();

    } elseif ($type === 'Live') {
        $stmt = $conn->prepare("SELECT title FROM recordings WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class = $stmt->get_result()->fetch_assoc();
        $response['class_title'] = $class['title'] ?? 'Unknown Live Class';
        $stmt->close();

        $attendee_query = "SELECT u.first_name, u.second_name, u.user_id, MAX(vl.watched_at) as watch_time 
                          FROM video_watch_log vl
                          JOIN users u ON vl.student_id = u.user_id
                          WHERE vl.recording_id = ?
                          GROUP BY vl.student_id
                          ORDER BY watch_time ASC";
        $stmt = $conn->prepare($attendee_query);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $response['attendees'][] = [
                'name' => $row['first_name'] . ' ' . $row['second_name'],
                'id' => $row['user_id'],
                'time' => date('H:i:s', strtotime($row['watch_time']))
            ];
        }
        $stmt->close();
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
