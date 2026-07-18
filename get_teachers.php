<?php
header('Content-Type: application/json');

try {
    require_once 'config.php';
    
    $stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
    $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
    $academic_year = isset($_GET['academic_year']) ? intval($_GET['academic_year']) : date('Y');
    
    if ($stream_id <= 0 || $subject_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid stream or subject ID']);
        exit;
    }
    
    // Get teachers assigned to this stream-subject combination for the current academic year
    // Also pull enrollment_fee and monthly_fee from the enrollment_fees table
    $query = "SELECT DISTINCT u.user_id as teacher_id, u.first_name, u.second_name, 
                     u.mobile_number, u.whatsapp_number, u.profile_picture, u.role, 
                     ta.academic_year, ta.id as assignment_id,
                     ef.enrollment_fee, ef.monthly_fee
              FROM users u
              INNER JOIN teacher_assignments ta ON u.user_id = ta.teacher_id
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              LEFT JOIN enrollment_fees ef ON ef.teacher_assignment_id = ta.id
              WHERE ss.stream_id = ? 
                AND ss.subject_id = ? 
                AND ta.academic_year = ?
                AND ta.status = 'active'
                AND u.role = 'teacher'
                AND u.status = 1
                AND u.approved = 1
              ORDER BY u.first_name, u.second_name";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("iii", $stream_id, $subject_id, $academic_year);
    
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teacher_id = $row['teacher_id'];
        
        // Get highest education entry for this teacher
        $edu_query = "SELECT qualification, institution, field_of_study
                      FROM teacher_education 
                      WHERE teacher_id = ? 
                      ORDER BY year_obtained DESC, id DESC
                      LIMIT 1";
        $edu_stmt = $conn->prepare($edu_query);
        $top_degree = '';
        $top_institution = '';
        if ($edu_stmt) {
            $edu_stmt->bind_param("s", $teacher_id);
            $edu_stmt->execute();
            $edu_result = $edu_stmt->get_result();
            if ($edu_row = $edu_result->fetch_assoc()) {
                $top_degree = $edu_row['qualification'] ?? '';
                if (!empty($edu_row['field_of_study'])) {
                    $top_degree .= ' (' . $edu_row['field_of_study'] . ')';
                }
                $top_institution = $edu_row['institution'] ?? '';
            }
            $edu_stmt->close();
        }
        
        $teachers[] = [
            'teacher_id'      => $teacher_id,
            'first_name'      => $row['first_name'],
            'second_name'     => $row['second_name'],
            'mobile_number'   => $row['mobile_number'],
            'whatsapp_number' => $row['whatsapp_number'],
            'profile_picture' => $row['profile_picture'],
            'academic_year'   => $row['academic_year'],
            'enrollment_fee'  => $row['enrollment_fee'] ?? 0,
            'monthly_fee'     => $row['monthly_fee'] ?? 0,
            'degree'          => $top_degree,
            'university'      => $top_institution,
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success'  => true,
        'teachers' => $teachers
    ]);
    
} catch (Exception $e) {
    error_log('get_teachers.php error: ' . $e->getMessage());
    echo json_encode([
        'success'  => false,
        'message'  => 'Error loading teachers: ' . $e->getMessage(),
        'teachers' => []
    ]);
}
?>
