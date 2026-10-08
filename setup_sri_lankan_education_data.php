<?php
// setup_sri_lankan_education_data.php
// Production-ready database populator for Sri Lankan education streams & standardized subjects (Grade 6, 7, 8, 9, 10, O/L, A/L separately)

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Sri Lankan LMS Education Streams & Subjects Seeder</h2>";

if ($conn->connect_error) {
    die("<p style='color:red;'>Database Connection Failed: " . htmlspecialchars($conn->connect_error) . "</p>");
}

$conn->set_charset("utf8mb4");

// 1. Remove / disable old grouped streams if they exist
$old_streams = ['Junior Secondary (Grade 6 - Grade 9)', 'G.C.E. Ordinary Level (O/L - Grade 10 & 11)'];
foreach ($old_streams as $old_name) {
    $conn->query("DELETE FROM streams WHERE name = '" . $conn->real_escape_string($old_name) . "' AND id NOT IN (SELECT DISTINCT stream_id FROM stream_subjects ss JOIN teacher_assignments ta ON ss.id = ta.stream_subject_id)");
    // If it has active assignments, disable it instead of hard deleting
    $conn->query("UPDATE streams SET status = 0 WHERE name = '" . $conn->real_escape_string($old_name) . "'");
}

// 2. Define Grade-by-Grade Streams and Subjects
$grade_6_9_subjects = [
    'Sinhala',
    'Tamil',
    'English',
    'Science',
    'Mathematics',
    'History',
    'Geography',
    'Civics',
    'ICT',
    'Health & Physical Education',
    'Practical & Technical Skills (PTS)',
    'Art',
    'Eastern Music',
    'Western Music',
    'Dancing',
    'Drama & Theatre',
    'Buddhism',
    'Hinduism',
    'Roman Catholicism',
    'Islam'
];

$grade_10_ol_subjects = [
    'Sinhala',
    'Tamil',
    'English',
    'Science',
    'Mathematics',
    'History',
    'Buddhism',
    'Hinduism',
    'Roman Catholicism',
    'Islam',
    'Business & Accounting Studies',
    'ICT',
    'Entrepreneurship Studies',
    'Geography',
    'Civic Education',
    'Agriculture & Food Technology',
    'Design & Mechanical Technology',
    'Home Economics',
    'Health & Physical Education',
    'Sinhala Literature',
    'Tamil Literature',
    'English Literature',
    'Art',
    'Eastern Music',
    'Western Music',
    'Dancing',
    'Drama & Theatre',
    'French',
    'German',
    'Japanese',
    'Chinese'
];

$streams_data = [
    [
        'name' => 'Grade 5 Scholarship',
        'code' => 'SCHOLARSHIP',
        'subjects' => [
            'Scholarship General Paper',
            'Scholarship Mathematics & Language'
        ]
    ],
    [
        'name' => 'Grade 6',
        'code' => 'GRADE_6',
        'subjects' => $grade_6_9_subjects
    ],
    [
        'name' => 'Grade 7',
        'code' => 'GRADE_7',
        'subjects' => $grade_6_9_subjects
    ],
    [
        'name' => 'Grade 8',
        'code' => 'GRADE_8',
        'subjects' => $grade_6_9_subjects
    ],
    [
        'name' => 'Grade 9',
        'code' => 'GRADE_9',
        'subjects' => $grade_6_9_subjects
    ],
    [
        'name' => 'Grade 10',
        'code' => 'GRADE_10',
        'subjects' => $grade_10_ol_subjects
    ],
    [
        'name' => 'G.C.E. Ordinary Level (O/L)',
        'code' => 'OL',
        'subjects' => $grade_10_ol_subjects
    ],
    [
        'name' => 'G.C.E. A/L Physical Science',
        'code' => 'AL_MATHS',
        'subjects' => [
            'Combined Mathematics',
            'Physics',
            'Chemistry',
            'ICT',
            'Higher Mathematics'
        ]
    ],
    [
        'name' => 'G.C.E. A/L Biological Science',
        'code' => 'AL_BIO',
        'subjects' => [
            'Biology',
            'Chemistry',
            'Physics',
            'Agricultural Science',
            'ICT'
        ]
    ],
    [
        'name' => 'G.C.E. A/L Commerce',
        'code' => 'AL_COMMERCE',
        'subjects' => [
            'Accounting',
            'Business Studies',
            'Economics',
            'Business Statistics',
            'ICT'
        ]
    ],
    [
        'name' => 'G.C.E. A/L Technology',
        'code' => 'AL_TECH',
        'subjects' => [
            'Engineering Technology (ET)',
            'Bio Systems Technology (BST)',
            'Science for Technology (SFT)',
            'ICT',
            'Agricultural Science'
        ]
    ],
    [
        'name' => 'G.C.E. A/L Arts',
        'code' => 'AL_ARTS',
        'subjects' => [
            'Sinhala',
            'Tamil',
            'English Literature',
            'Political Science',
            'Logic & Scientific Method',
            'Geography',
            'History',
            'Buddhist Culture',
            'Christian Culture',
            'Hindu Culture',
            'Islamic Culture',
            'Media & Communication Studies',
            'Drama & Theatre',
            'Music',
            'Dancing',
            'Art',
            'ICT',
            'Home Economics',
            'Agricultural Science'
        ]
    ],
    [
        'name' => 'General / Professional',
        'code' => 'GENERAL',
        'subjects' => [
            'General English',
            'Common General Test'
        ]
    ]
];

$inserted_streams = 0;
$inserted_subjects = 0;
$inserted_links = 0;

foreach ($streams_data as $s_info) {
    $stream_name = $s_info['name'];
    $stream_code = $s_info['code'];
    
    // 1. Insert/Get Stream
    $stmt = $conn->prepare("SELECT id FROM streams WHERE LOWER(name) = LOWER(?)");
    $stmt->bind_param("s", $stream_name);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $stream_id = $row['id'];
        // Ensure status is active
        $conn->query("UPDATE streams SET status = 1 WHERE id = $stream_id");
    } else {
        $ins = $conn->prepare("INSERT INTO streams (name, status) VALUES (?, 1)");
        $ins->bind_param("s", $stream_name);
        $ins->execute();
        $stream_id = $ins->insert_id;
        $inserted_streams++;
        $ins->close();
    }
    $stmt->close();
    
    // 2. Loop Subjects
    foreach ($s_info['subjects'] as $sub_name) {
        $sub_name = trim($sub_name);
        // Insert/Get Subject (case-insensitive lookup to avoid duplicates)
        $sub_stmt = $conn->prepare("SELECT id FROM subjects WHERE LOWER(name) = LOWER(?)");
        $sub_stmt->bind_param("s", $sub_name);
        $sub_stmt->execute();
        $sub_res = $sub_stmt->get_result();
        
        if ($sub_row = $sub_res->fetch_assoc()) {
            $subject_id = $sub_row['id'];
        } else {
            $sub_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sub_name), 0, 10));
            $ins_sub = $conn->prepare("INSERT INTO subjects (name, code, status) VALUES (?, ?, 1)");
            $ins_sub->bind_param("ss", $sub_name, $sub_code);
            $ins_sub->execute();
            $subject_id = $ins_sub->insert_id;
            $inserted_subjects++;
            $ins_sub->close();
        }
        $sub_stmt->close();
        
        // Link stream_subject
        $link_stmt = $conn->prepare("SELECT id FROM stream_subjects WHERE stream_id = ? AND subject_id = ?");
        $link_stmt->bind_param("ii", $stream_id, $subject_id);
        $link_stmt->execute();
        $link_res = $link_stmt->get_result();
        
        if ($link_res->num_rows == 0) {
            $ins_link = $conn->prepare("INSERT INTO stream_subjects (stream_id, subject_id, status) VALUES (?, ?, 1)");
            $ins_link->bind_param("ii", $stream_id, $subject_id);
            $ins_link->execute();
            $inserted_links++;
            $ins_link->close();
        } else {
            // Ensure status = 1
            $conn->query("UPDATE stream_subjects SET status = 1 WHERE stream_id = $stream_id AND subject_id = $subject_id");
        }
        $link_stmt->close();
    }
}

echo "<div style='font-family:sans-serif; padding:15px; background:#e6fffa; border:1px solid #319795; border-radius:8px; color:#234e52;'>";
echo "<h3>✅ Setup Completed Successfully!</h3>";
echo "<ul>";
echo "<li>Created/Activated <strong>Grade 6, Grade 7, Grade 8, Grade 9, Grade 10</strong> separately.</li>";
echo "<li>Added <strong>$inserted_streams</strong> new streams.</li>";
echo "<li>Added <strong>$inserted_subjects</strong> new master subjects.</li>";
echo "<li>Created <strong>$inserted_links</strong> stream-subject mappings.</li>";
echo "</ul>";
echo "<p>You can run this page anytime to seed or verify Sri Lankan curriculum streams & subjects.</p>";
echo "</div>";
?>
