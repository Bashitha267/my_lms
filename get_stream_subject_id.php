<?php
header('Content-Type: application/json');

try {
    require_once 'config.php';
    
    $stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
    $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
    
    if ($stream_id <= 0 || $subject_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid stream or subject ID']);
        exit;
    }
    
    // Get stream_subject_id for the given stream-subject combination
    $query = "SELECT id as stream_subject_id FROM stream_subjects WHERE stream_id = ? AND subject_id = ? AND status = 1 LIMIT 1";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $stream_id, $subject_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'stream_subject_id' => $row['stream_subject_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Stream-subject combination not found'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log('get_stream_subject_id.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>















