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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $recording_id = intval($_POST['recording_id'] ?? 0);
    
    if ($recording_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid recording ID']);
        exit;
    }
    
    // Verify teacher owns this live class
    $verify_query = "SELECT r.id, r.status, r.is_live, ta.teacher_id 
                    FROM recordings r
                    INNER JOIN teacher_assignments ta ON r.teacher_assignment_id = ta.id
                    WHERE r.id = ? AND r.is_live = 1 AND ta.teacher_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("is", $recording_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Live class not found or unauthorized']);
        $verify_stmt->close();
        exit;
    }
    
    $live_class = $verify_result->fetch_assoc();
    $verify_stmt->close();
    
    if ($action === 'start') {
        // Start live class - change status to 'ongoing' and set actual_start_time
        $update_query = "UPDATE recordings SET status = 'ongoing', actual_start_time = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $recording_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Live class started successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error starting live class: ' . $conn->error]);
        }
        $update_stmt->close();
        
    } elseif ($action === 'end') {
        $save_to_recordings = isset($_POST['save_to_recordings']) && $_POST['save_to_recordings'] === 'yes';
        
        if ($save_to_recordings) {
            // End and save to recordings - change status to 'ended'
            $update_query = "UPDATE recordings SET status = 'ended', end_time = NOW() WHERE id = ?";
        } else {
            // Cancel - change status to 'cancelled'
            $update_query = "UPDATE recordings SET status = 'cancelled', end_time = NOW() WHERE id = ?";
        }
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $recording_id);
        
        if ($update_stmt->execute()) {
            $message = $save_to_recordings ? 'Live class ended and saved to recordings' : 'Live class cancelled';
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error ending live class: ' . $conn->error]);
        }
        $update_stmt->close();
        
    } elseif ($action === 'delete') {
        // Delete live class - soft delete by setting status to 'cancelled' or 'inactive'
        // Only allow deletion if status is 'scheduled' or 'cancelled'
        if ($live_class['status'] === 'scheduled' || $live_class['status'] === 'cancelled') {
            $update_query = "UPDATE recordings SET status = 'inactive' WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $recording_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Live class deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting live class: ' . $conn->error]);
            }
            $update_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot delete live class that is ongoing or ended']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

