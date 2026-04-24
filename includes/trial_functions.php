<?php
/**
 * Trial Functions for LearnerX LMS
 */

/**
 * Get the count of unique recordings (is_live = 0) a student has watched.
 */
function getRecordingTrialCount($conn, $student_id) {
    $query = "SELECT COUNT(DISTINCT l.recording_id) as trial_count 
              FROM video_watch_log l 
              JOIN recordings r ON l.recording_id = r.id 
              WHERE l.student_id = ? AND (r.is_live = 0 OR r.is_live IS NULL)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc()['trial_count'];
    $stmt->close();
    return intval($result);
}

/**
 * Get the count of unique live classes (YT is_live = 1 OR Zoom) a student has watched.
 */
function getLiveClassTrialCount($conn, $student_id) {
    // Count unique YT live classes
    $yt_query = "SELECT COUNT(DISTINCT l.recording_id) as yt_count 
                 FROM video_watch_log l 
                 JOIN recordings r ON l.recording_id = r.id 
                 WHERE l.student_id = ? AND r.is_live = 1";
    $yt_stmt = $conn->prepare($yt_query);
    $yt_stmt->bind_param("s", $student_id);
    $yt_stmt->execute();
    $yt_count = $yt_stmt->get_result()->fetch_assoc()['yt_count'];
    $yt_stmt->close();

    // Count unique Zoom classes
    $zoom_query = "SELECT COUNT(DISTINCT zoom_class_id) as zoom_count 
                   FROM zoom_watch_log 
                   WHERE student_id = ?";
    $zoom_stmt = $conn->prepare($zoom_query);
    $zoom_stmt->bind_param("s", $student_id);
    $zoom_stmt->execute();
    $zoom_count = $zoom_stmt->get_result()->fetch_assoc()['zoom_count'];
    $zoom_stmt->close();

    return intval($yt_count) + intval($zoom_count);
}

/**
 * Check if a student has watched a specific recording as a trial.
 */
function hasWatchedRecordingAsTrial($conn, $student_id, $recording_id) {
    $query = "SELECT id FROM video_watch_log WHERE student_id = ? AND recording_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $student_id, $recording_id);
    $stmt->execute();
    $result = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $result;
}

/**
 * Check if a student has watched a specific zoom class as a trial.
 */
function hasWatchedZoomAsTrial($conn, $student_id, $zoom_class_id) {
    $query = "SELECT id FROM zoom_watch_log WHERE student_id = ? AND zoom_class_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $student_id, $zoom_class_id);
    $stmt->execute();
    $result = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $result;
}
?>
