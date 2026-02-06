<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Unauthorized access.");
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($class_id <= 0 || empty($type)) {
    die("Invalid request.");
}

// Fetch class info
$class_title = '';
$class_date = '';
$attendees = [];

if ($type === 'Zoom') {
    $stmt = $conn->prepare("SELECT title, scheduled_start_time FROM zoom_classes WHERE id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $class_title = $res['title'];
    $class_date = $res['scheduled_start_time'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT u.first_name, u.second_name, u.user_id, zp.join_time 
                          FROM zoom_participants zp
                          JOIN users u ON zp.user_id = u.user_id
                          WHERE zp.zoom_class_id = ?
                          ORDER BY zp.join_time ASC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $attendees[] = $row;
    $stmt->close();
} elseif ($type === 'Physical') {
    $stmt = $conn->prepare("SELECT title, class_date, start_time FROM physical_classes WHERE id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $class_title = $res['title'];
    $class_date = $res['class_date'] . ' ' . $res['start_time'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT u.first_name, u.second_name, u.user_id, a.attended_at as join_time 
                          FROM attendance a
                          JOIN users u ON a.student_id = u.user_id
                          WHERE a.physical_class_id = ?
                          ORDER BY a.attended_at ASC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $attendees[] = $row;
    $stmt->close();
} elseif ($type === 'Live') {
    $stmt = $conn->prepare("SELECT title, scheduled_start_time FROM recordings WHERE id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $class_title = $res['title'];
    $class_date = $res['scheduled_start_time'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT u.first_name, u.second_name, u.user_id, MAX(vl.watched_at) as join_time 
                          FROM video_watch_log vl
                          JOIN users u ON vl.student_id = u.user_id
                          WHERE vl.recording_id = ?
                          GROUP BY vl.student_id
                          ORDER BY join_time ASC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $attendees[] = $row;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report - <?php echo htmlspecialchars($class_title); ?></title>
    <style>
        body { font-family: sans-serif; padding: 40px; color: #333; }
        .header { border-bottom: 2px solid #dc2626; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #dc2626; font-size: 24px; }
        .header p { margin: 5px 0 0; color: #666; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; font-size: 13px; }
        th { background-color: #f9fafb; font-weight: bold; color: #444; }
        tr:nth-child(even) { background-color: #fcfcfc; }
        .footer { margin-top: 50px; font-size: 11px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; background: #eee; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="background: #fff9c4; padding: 15px; border: 1px solid #ffe082; margin-bottom: 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: bold;">Print Preview: Use your browser's "Save to PDF" feature.</span>
        <button onclick="window.print()" style="padding: 8px 16px; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Print Now</button>
    </div>

    <div class="header">
        <h1>Attendance Report</h1>
        <p><strong>Subject:</strong> <?php echo htmlspecialchars($class_title); ?> (<?php echo $type; ?> Class)</p>
        <p><strong>Date held:</strong> <?php echo date('F d, Y | H:i', strtotime($class_date)); ?></p>
        <p><strong>Total Attendees:</strong> <?php echo count($attendees); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Student Name</th>
                <th>User ID</th>
                <th>Attendance Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($attendees)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px; color: #999;">No attendance records found for this class.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($attendees as $index => $student): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td style="font-weight: bold;"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['second_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['user_id']); ?></td>
                        <td><?php echo date('H:i:s', strtotime($student['join_time'] ?? 'now')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Generated by LMS System on <?php echo date('Y-m-d H:i:s'); ?> | Teacher ID: <?php echo $_SESSION['user_id']; ?>
    </div>
</body>
</html>
