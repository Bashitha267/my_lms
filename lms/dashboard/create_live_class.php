<?php
require_once '../check_session.php';
require_once '../config.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$teacher_assignment_id = isset($_POST['teacher_assignment_id']) ? intval($_POST['teacher_assignment_id']) : 0;
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$youtube_url = trim($_POST['youtube_url'] ?? '');
$scheduled_start_time = trim($_POST['scheduled_start_time'] ?? '');

if ($teacher_assignment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid teacher assignment ID']);
    exit;
}

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}

// Verify teacher owns this assignment
$verify_query = "SELECT id FROM teacher_assignments WHERE id = ? AND teacher_id = ? AND status = 'active'";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("is", $teacher_assignment_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $verify_stmt->close();
    echo json_encode(['success' => false, 'message' => 'Invalid teacher assignment']);
    exit;
}
$verify_stmt->close();

// Extract YouTube video ID from URL if provided
$youtube_video_id = null;
if (!empty($youtube_url)) {
    // Extract video ID from various YouTube URL formats
    // Handles: 
    // - youtube.com/watch?v=VIDEO_ID
    // - youtube.com/live/VIDEO_ID
    // - youtube.com/embed/VIDEO_ID
    // - youtu.be/VIDEO_ID
    // - youtube.com/v/VIDEO_ID
    
    // Try /live/ format first (most specific)
    if (preg_match('/youtube\.com\/live\/([a-zA-Z0-9_-]{11})/', $youtube_url, $live_matches)) {
        $youtube_video_id = $live_matches[1];
    }
    // Try /watch?v= format
    elseif (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $watch_matches)) {
        $youtube_video_id = $watch_matches[1];
    }
    // Try /embed/ or /v/ format
    elseif (preg_match('/youtube\.com\/(?:embed|v)\/([a-zA-Z0-9_-]{11})/', $youtube_url, $embed_matches)) {
        $youtube_video_id = $embed_matches[1];
    }
    
    // Validate that we got a video ID
    if (empty($youtube_video_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid YouTube URL. Please provide a valid YouTube Live URL (e.g., https://www.youtube.com/live/VIDEO_ID).']);
        exit;
    }
}

// Convert scheduled start time to datetime format if provided
$scheduled_datetime = null;
if (!empty($scheduled_start_time)) {
    $scheduled_datetime = date('Y-m-d H:i:s', strtotime($scheduled_start_time));
}

// Insert live class
$insert_query = "INSERT INTO live_classes (teacher_assignment_id, title, description, youtube_video_id, youtube_url, status, scheduled_start_time, created_at)
                VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NOW())";

$insert_stmt = $conn->prepare($insert_query);
$insert_stmt->bind_param("isssss", 
    $teacher_assignment_id,
    $title,
    $description,
    $youtube_video_id,
    $youtube_url,
    $scheduled_datetime
);

if ($insert_stmt->execute()) {
    $live_class_id = $conn->insert_id;
    echo json_encode([
        'success' => true,
        'message' => 'Live class created successfully',
        'live_class_id' => $live_class_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating live class: ' . $conn->error]);
}

$insert_stmt->close();

