<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

header('Content-Type: application/json');

if ($role !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_physical') {
        $class_id = intval($_POST['class_id'] ?? 0);
        
        if ($class_id > 0) {
            // Verify ownership
            $check_query = "SELECT id FROM physical_classes WHERE id = ? AND teacher_id = ? LIMIT 1";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param("is", $class_id, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                // Delete attendance first
                $conn->query("DELETE FROM attendance WHERE physical_class_id = $class_id");
                
                // Delete class
                $delete_query = "DELETE FROM physical_classes WHERE id = ?";
                $del_stmt = $conn->prepare($delete_query);
                $del_stmt->bind_param("i", $class_id);
                if ($del_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Physical class deleted successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting class.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized or class not found.']);
            }
        }
    } elseif ($action === 'start_physical') {
        $class_id = intval($_POST['class_id'] ?? 0);
        $update_query = "UPDATE physical_classes SET status = 'ongoing' WHERE id = ? AND teacher_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("is", $class_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Class started successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error starting class.']);
        }
    } elseif ($action === 'end_physical') {
        $class_id = intval($_POST['class_id'] ?? 0);
        $update_query = "UPDATE physical_classes SET status = 'ended' WHERE id = ? AND teacher_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("is", $class_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Class ended successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error ending class.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
