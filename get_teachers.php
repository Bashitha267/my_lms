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
    $query = "SELECT DISTINCT u.user_id as teacher_id, u.email, u.first_name, u.second_name, 
                     u.mobile_number, u.whatsapp_number, u.profile_picture, u.role, ta.academic_year
              FROM users u
              INNER JOIN teacher_assignments ta ON u.user_id = ta.teacher_id
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
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
        
        // Get education details for this teacher
        $edu_query = "SELECT qualification, institution, year_obtained, field_of_study, grade_or_class 
                      FROM teacher_education 
                      WHERE teacher_id = ? 
                      ORDER BY year_obtained DESC, id ASC";
        $edu_stmt = $conn->prepare($edu_query);
        if ($edu_stmt) {
            $edu_stmt->bind_param("s", $teacher_id);
            $edu_stmt->execute();
            $edu_result = $edu_stmt->get_result();
            
            $education = [];
            while ($edu_row = $edu_result->fetch_assoc()) {
                $education[] = [
                    'qualification' => $edu_row['qualification'],
                    'institution' => $edu_row['institution'],
                    'year_obtained' => $edu_row['year_obtained'],
                    'field_of_study' => $edu_row['field_of_study'],
                    'grade_or_class' => $edu_row['grade_or_class']
                ];
            }
            $edu_stmt->close();
        }
        
        $teachers[] = [
            'teacher_id' => $teacher_id,
            'email' => $row['email'],
            'first_name' => $row['first_name'],
            'second_name' => $row['second_name'],
            'mobile_number' => $row['mobile_number'],
            'whatsapp_number' => $row['whatsapp_number'],
            'profile_picture' => $row['profile_picture'],
            'academic_year' => $row['academic_year'],
            'education' => $education ?? []
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'teachers' => $teachers
    ]);
    
} catch (Exception $e) {
    error_log('get_teachers.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading teachers: ' . $e->getMessage(),
        'teachers' => []
    ]);
}
?>















