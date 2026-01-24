<?php
header('Content-Type: application/json');

try {
    require_once 'config.php';
    
    $stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
    
    if ($stream_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid stream ID', 'subjects' => []]);
        exit;
    }
    
    // Get subjects for the selected stream
    $query = "SELECT s.id, s.name, s.code 
              FROM subjects s
              INNER JOIN stream_subjects ss ON s.id = ss.subject_id
              WHERE ss.stream_id = ? AND ss.status = 1 AND s.status = 1
              ORDER BY s.name";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $stream_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'subjects' => $subjects
    ]);
    
} catch (Exception $e) {
    error_log('get_subjects.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading subjects: ' . $e->getMessage(),
        'subjects' => []
    ]);
}
?>
