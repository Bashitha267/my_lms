<?php
require_once '../check_session.php';
require_once '../config.php';
require_once '../whatsapp_config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Only Admin, Super Admin, Teachers, and Instructors can access
if (!in_array($role, ['admin', 'super_admin', 'teacher', 'instructor'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = "A/L Details & Results Management";
$success_message = '';
$error_message = '';

// Dynamic protocol-aware base URLs (defined at top to avoid undefined variable notices)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$base_path_val = defined('BASE_PATH') ? BASE_PATH : '/';
$base_url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($base_path_val, '/');
$subjects_link = $base_url . "/student/al_results_form";
$results_link = $base_url . "/student/al_exam_form";

// Self-heal: ensure required columns exist (safe for older database imports)
$chk_al_req = $conn->query("SHOW COLUMNS FROM users LIKE 'al_details_requested'");
if ($chk_al_req && $chk_al_req->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN al_details_requested TINYINT(1) DEFAULT 0");
}
$chk_al_stream = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'stream'");
if ($chk_al_stream && $chk_al_stream->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN stream VARCHAR(50) DEFAULT NULL");
}
$chk_al_r1 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'result_1'");
if ($chk_al_r1 && $chk_al_r1->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN result_1 VARCHAR(5) DEFAULT NULL");
}
$chk_al_r2 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'result_2'");
if ($chk_al_r2 && $chk_al_r2->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN result_2 VARCHAR(5) DEFAULT NULL");
}
$chk_al_r3 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'result_3'");
if ($chk_al_r3 && $chk_al_r3->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN result_3 VARCHAR(5) DEFAULT NULL");
}
$chk_al_teacher = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'teacher_id'");
if ($chk_al_teacher && $chk_al_teacher->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN teacher_id VARCHAR(255) DEFAULT NULL AFTER student_id");
}
$chk_al_dr = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'district_rank'");
if ($chk_al_dr && $chk_al_dr->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN district_rank INT(11) DEFAULT NULL");
}
$chk_al_ir = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'island_rank'");
if ($chk_al_ir && $chk_al_ir->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN island_rank INT(11) DEFAULT NULL");
}
$chk_al_ey = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'exam_year'");
if ($chk_al_ey && $chk_al_ey->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN exam_year INT(11) DEFAULT NULL");
}
$chk_al_zs = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'z_score'");
if ($chk_al_zs && $chk_al_zs->num_rows == 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN z_score DECIMAL(6,4) DEFAULT NULL");
}

// Flash Toast Messages
$toast_data = null;
if (isset($_SESSION['toast'])) {
    $toast_data = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

// Handle Result Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_result') {
    $submission_id = intval($_POST['submission_id'] ?? 0);
    $stream = trim($_POST['stream'] ?? '');
    $subject_1 = trim($_POST['subject_1'] ?? '');
    $result_1 = trim($_POST['result_1'] ?? '');
    $subject_2 = trim($_POST['subject_2'] ?? '');
    $result_2 = trim($_POST['result_2'] ?? '');
    $subject_3 = trim($_POST['subject_3'] ?? '');
    $result_3 = trim($_POST['result_3'] ?? '');
    $index_number = trim($_POST['index_number'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $district_rank = (isset($_POST['district_rank']) && $_POST['district_rank'] !== '') ? intval($_POST['district_rank']) : null;
    $island_rank = (isset($_POST['island_rank']) && $_POST['island_rank'] !== '') ? intval($_POST['island_rank']) : null;
    $exam_year = (isset($_POST['exam_year']) && $_POST['exam_year'] !== '') ? intval($_POST['exam_year']) : null;
    $z_score = (isset($_POST['z_score']) && $_POST['z_score'] !== '') ? floatval($_POST['z_score']) : null;
    $agreed_to_publish = isset($_POST['agreed_to_publish']) ? 1 : 0;

    if ($submission_id > 0 && in_array($role, ['admin', 'super_admin', 'teacher', 'instructor'])) {
        $upd_stmt = $conn->prepare("UPDATE al_exam_submissions SET 
            stream = ?, 
            index_number = ?,
            subject_1 = ?, result_1 = ?, 
            subject_2 = ?, result_2 = ?, 
            subject_3 = ?, result_3 = ?, 
            district = ?, 
            district_rank = ?, island_rank = ?, 
            exam_year = ?, z_score = ?, 
            agreed_to_publish = ?
            WHERE id = ?");
        $upd_stmt->bind_param("sssssssssiisdii", 
            $stream, 
            $index_number,
            $subject_1, $result_1, 
            $subject_2, $result_2, 
            $subject_3, $result_3, 
            $district, 
            $district_rank, $island_rank, 
            $exam_year, $z_score, 
            $agreed_to_publish, 
            $submission_id
        );
        if ($upd_stmt->execute()) {
            $_SESSION['toast'] = ['message' => 'Student A/L results updated successfully.', 'type' => 'success'];
        } else {
            $_SESSION['toast'] = ['message' => 'Error updating results: ' . $conn->error, 'type' => 'error'];
        }
        $upd_stmt->close();
        header("Location: request_al_details.php?tab=edit_results");
        exit;
    }
}

// Handle Result Deletion (Only deletes submission, never the user account)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_result') {
    $submission_id = intval($_POST['submission_id'] ?? 0);
    if ($submission_id > 0 && in_array($role, ['admin', 'super_admin', 'teacher', 'instructor'])) {
        $del_stmt = $conn->prepare("DELETE FROM al_exam_submissions WHERE id = ?");
        $del_stmt->bind_param("i", $submission_id);
        if ($del_stmt->execute()) {
            $_SESSION['toast'] = ['message' => 'Result record deleted successfully. The student user account was preserved.', 'type' => 'success'];
        } else {
            $_SESSION['toast'] = ['message' => 'Error deleting result record.', 'type' => 'error'];
        }
        $del_stmt->close();
        header("Location: request_al_details.php?tab=edit_results");
        exit;
    }
}

// Handle WhatsApp Request API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    header('Content-Type: application/json');
    $request_type = $_POST['request_type'] ?? ''; // 'stream' or 'class'
    $request_mode = $_POST['request_mode'] ?? 'subjects'; // 'subjects' or 'results'
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID specified.']);
        exit;
    }

    $query = "";
    $params = [];
    $types = "";

    if ($request_type === 'stream' && in_array($role, ['admin', 'super_admin'])) {
        // Enrolled students for this STREAM
        $query = "SELECT DISTINCT u.whatsapp_number, u.mobile_number, u.first_name, u.user_id 
                  FROM users u
                  INNER JOIN student_enrollment se ON u.user_id = se.student_id
                  INNER JOIN stream_subjects ss ON se.stream_subject_id = ss.id
                  WHERE ss.stream_id = ? AND se.status = 'active' AND u.status = 1";
        $types = "i";
        $params[] = $id;

    } elseif ($request_type === 'class') {
        if (in_array($role, ['admin', 'super_admin'])) {
            // Admin can dispatch to any class
            $query = "SELECT DISTINCT u.whatsapp_number, u.mobile_number, u.first_name, u.user_id 
                      FROM users u
                      INNER JOIN student_enrollment se ON u.user_id = se.student_id
                      WHERE se.stream_subject_id = ? AND se.status = 'active' AND u.status = 1";
            $types = "i";
            $params[] = $id;
        } elseif (in_array($role, ['teacher', 'instructor'])) {
            // Teacher/Instructor can dispatch to their assigned class
            $query = "SELECT DISTINCT u.whatsapp_number, u.mobile_number, u.first_name, u.user_id 
                      FROM users u
                      INNER JOIN student_enrollment se ON u.user_id = se.student_id
                      INNER JOIN teacher_assignments ta ON se.stream_subject_id = ta.stream_subject_id
                      WHERE se.stream_subject_id = ? AND ta.teacher_id = ? AND se.status = 'active' AND u.status = 1";
            $types = "is";
            $params[] = $id;
            $params[] = $user_id;
        } else {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request type or permission denied.']);
        exit;
    }
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    $context = ($request_type === 'stream') ? "Stream: $name" : "Class: $name";
    
    while ($student = $result->fetch_assoc()) {
        $target_number = !empty($student['whatsapp_number']) ? $student['whatsapp_number'] : $student['mobile_number'];
        if (!empty($target_number)) {
            $first_name = !empty($student['first_name']) ? $student['first_name'] : 'Student';
            
            if ($request_mode === 'results') {
                $msg = "📢 *A/L Results Collection / උසස් පෙළ ප්‍රතිඵල ලබාගැනීම*\n\n" .
                       "Hello {$first_name},\n" .
                       "📌 *$context*\n\n" .
                       "ඔබ තවමත් අප වෙත ඔබගේ ප්‍රතිඵල දැනුම් දී නොමැති නම් හෝ සම්පූර්ණ තොරතුරු ලබා දී නොමැති නම්, ඔබගේ උසස් පෙළ විභාග ප්‍රතිඵල පහත සබැඳියෙන් (Link) ගොස් තොරතුරු ඇතුළත් කරන්න.\n" .
                       "If you have not yet notified us of your results or have not provided complete information, please enter your A/L exam results by going to the link below.\n\n" .
                       "🔗 $results_link\n\n" .
                       "---------------------------------------\n\n" .
                       "| Lernerr.LK 🇱🇰\n" .
                       "| Best Place for Your Online Learning";
            } else {
                $msg = "📢 *A/L Subjects Registration / උසස් පෙළ විෂය තොරතුරු ලබාගැනීම*\n\n" .
                       "Hello *{$first_name}*,\n" .
                       "📌 *$context*\n\n" .
                       "Please submit your A/L examination subjects and details using the link below:\n\n" .
                       "🔗 $subjects_link\n\n" .
                       "------------------------------------------\n" .
                       "ඔබගේ උසස් පෙළ විෂයන් පිළිබඳ තොරතුරු ඉහත සබැඳියෙන් ඇතුළත් කරන්න.\n" .
                       "⚠️ *Note:* Submission is required to unlock full access to your learning portal.\n\n" .
                       "Lernerr.LK Team\n\n" .
                       "| Lernerr.LK 🇱🇰\n" .
                       "| Best Place for Your Online Learning";
            }
            
            $clean_phone = preg_replace('/\D/', '', $target_number);
            if (!empty($clean_phone)) {
                sendWhatsAppMessage($clean_phone, $msg);
            }
            
            // Set flag in database that details have been requested
            $update_flag = $conn->prepare("UPDATE users SET al_details_requested = 1 WHERE user_id = ?");
            $update_flag->bind_param("s", $student['user_id']);
            $update_flag->execute();
            $update_flag->close();

            $count++;
        }
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

// Fetch Items to Display (Streams for Admin, Classes for Teacher) with Live Enrolled and Submission Counts
$items = [];
$card_type = ''; // 'stream' or 'class'

if ($role === 'teacher') {
    $card_type = 'class';
    $query = "SELECT ss.id, 
                     CONCAT(s.name, ' - ', sub.name) as name,
                     sub.code,
                     s.name as stream_name,
                     sub.name as subject_name,
                     COUNT(DISTINCT se.student_id) as enrolled_count,
                     COUNT(DISTINCT CASE WHEN als.id IS NOT NULL THEN se.student_id END) as subjects_count,
                     COUNT(DISTINCT CASE WHEN als.results_submitted_at IS NOT NULL THEN se.student_id END) as results_count
              FROM teacher_assignments ta
              INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
              INNER JOIN streams s ON ss.stream_id = s.id
              INNER JOIN subjects sub ON ss.subject_id = sub.id
              LEFT JOIN student_enrollment se ON ss.id = se.stream_subject_id AND se.status = 'active'
              LEFT JOIN users u ON se.student_id = u.user_id AND u.status = 1
              LEFT JOIN al_exam_submissions als ON se.student_id = als.student_id
              WHERE ta.teacher_id = ? AND ta.status = 'active'
              GROUP BY ss.id
              ORDER BY s.name ASC, sub.name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

} else {
    $card_type = 'stream';
    $query = "SELECT s.id, 
                     s.name, 
                     s.description,
                     COUNT(DISTINCT se.student_id) as enrolled_count,
                     COUNT(DISTINCT CASE WHEN als.id IS NOT NULL THEN se.student_id END) as subjects_count,
                     COUNT(DISTINCT CASE WHEN als.results_submitted_at IS NOT NULL THEN se.student_id END) as results_count
              FROM streams s
              LEFT JOIN stream_subjects ss ON s.id = ss.stream_id
              LEFT JOIN student_enrollment se ON ss.id = se.stream_subject_id AND se.status = 'active'
              LEFT JOIN users u ON se.student_id = u.user_id AND u.status = 1
              LEFT JOIN al_exam_submissions als ON se.student_id = als.student_id
              WHERE s.status = 1
              GROUP BY s.id
              ORDER BY s.name ASC";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
}

// Active Tab
$active_tab = $_GET['tab'] ?? 'collection';
if (!in_array($active_tab, ['collection', 'edit_results'])) {
    $active_tab = 'collection';
}

// Calculate Global Metrics for Collection Tab
$total_items = count($items);
$total_enrolled = 0;
$total_subjects_submitted = 0;
$total_results_submitted = 0;

foreach ($items as $it) {
    $total_enrolled += intval($it['enrolled_count'] ?? 0);
    $total_subjects_submitted += intval($it['subjects_count'] ?? 0);
    $total_results_submitted += intval($it['results_count'] ?? 0);
}

$overall_subjects_rate = $total_enrolled > 0 ? round(($total_subjects_submitted / $total_enrolled) * 100, 1) : 0;
$overall_results_rate = $total_enrolled > 0 ? round(($total_results_submitted / $total_enrolled) * 100, 1) : 0;

// Fetch Submitted Results for Edit Results Tab
$submitted_results = [];
$available_exam_years = [];
$available_streams = [];

$results_query = "SELECT als.*, 
                         u.first_name, u.second_name, u.whatsapp_number, u.mobile_number, u.email, u.profile_picture
                  FROM al_exam_submissions als
                  INNER JOIN users u ON als.student_id = u.user_id
                  WHERE (als.results_submitted_at IS NOT NULL OR (als.result_1 IS NOT NULL AND als.result_1 != ''))
                  ORDER BY (als.district_rank IS NULL OR als.district_rank = 0) ASC, als.district_rank ASC, als.results_submitted_at DESC, als.id DESC";

$res_res = $conn->query($results_query);
if ($res_res) {
    while ($r_row = $res_res->fetch_assoc()) {
        $submitted_results[] = $r_row;
        if (!empty($r_row['exam_year']) && !in_array($r_row['exam_year'], $available_exam_years)) {
            $available_exam_years[] = (int)$r_row['exam_year'];
        }
        if (!empty($r_row['stream']) && !in_array($r_row['stream'], $available_streams)) {
            $available_streams[] = $r_row['stream'];
        }
    }
}
rsort($available_exam_years);
sort($available_streams);

// Helper for Grade Badge
function render_grade_badge($grade) {
    $grade = trim((string)$grade);
    if ($grade === '') return '<span class="text-gray-300">-</span>';
    
    $badge_classes = [
        'A' => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-extrabold',
        'B' => 'bg-blue-100 text-blue-800 border-blue-300 font-extrabold',
        'C' => 'bg-amber-100 text-amber-800 border-amber-300 font-extrabold',
        'S' => 'bg-purple-100 text-purple-800 border-purple-300 font-extrabold',
        'F' => 'bg-rose-100 text-rose-800 border-rose-300 font-extrabold'
    ];
    
    $cls = $badge_classes[strtoupper($grade)] ?? 'bg-gray-100 text-gray-800 border-gray-300 font-bold';
    return '<span class="inline-flex items-center justify-center w-6 h-6 rounded-md text-xs border ' . $cls . '">' . htmlspecialchars(strtoupper($grade)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assests/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assests/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assests/favicon-16x16.png">
    <link rel="manifest" href="../assests/site.webmanifest">
    <link rel="shortcut icon" href="../assests/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; }
        .table-sticky-header th { position: sticky; top: 0; background: #f8fafc; z-index: 10; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <?php 
    if (in_array($role, ['admin', 'super_admin'])) {
        $admin_header_prefix = '../admin/';
        include '../admin/header.php';
    } else {
        include 'navbar.php'; 
    }
    ?>

    <div class="w-full max-w-[98%] 2xl:max-w-[1750px] mx-auto py-6 px-3 sm:px-6 lg:px-8">
        
        <!-- Page Title & Top Actions Bar -->
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold <?php echo in_array($role, ['admin', 'super_admin']) ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200'; ?>">
                        <i class="fas fa-user-shield text-[10px]"></i> <?php echo in_array($role, ['admin', 'super_admin']) ? 'Admin Console' : 'Teacher Portal'; ?>
                    </span>
                </div>
                <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <i class="fas fa-graduation-cap text-blue-600"></i> A/L Details & Results Management
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Manage student A/L subject requests, inspect submissions, and edit/update exam results and ranks.
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?php echo $subjects_link; ?>" target="_blank" class="px-3 py-1.5 bg-amber-50 border border-amber-200 hover:bg-amber-100 text-amber-900 text-xs font-semibold rounded-lg shadow-xs flex items-center gap-1.5 transition-colors" title="Open A/L Subjects Form (student/al_results_form.php)">
                    <i class="fas fa-book-open text-amber-600 text-xs"></i>
                    <span>Subjects Form</span>
                </a>
                <a href="<?php echo $results_link; ?>" target="_blank" class="px-3 py-1.5 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-900 text-xs font-semibold rounded-lg shadow-xs flex items-center gap-1.5 transition-colors" title="Open A/L Results Form (student/al_exam_form.php)">
                    <i class="fas fa-award text-emerald-600 text-xs"></i>
                    <span>Results Form</span>
                </a>
                <a href="ALDetails.php" target="_blank" class="px-3 py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg shadow-xs flex items-center gap-1.5 transition-colors" title="View Public Results Showcase">
                    <i class="fas fa-external-link-alt text-blue-600 text-xs"></i>
                    <span>Showcase</span>
                </a>
                <button type="button" onclick="openPreviewModal('subjects')" class="px-3 py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg shadow-xs flex items-center gap-1.5 transition-colors">
                    <i class="fab fa-whatsapp text-emerald-600"></i>
                    <span>Preview WhatsApp</span>
                </button>
            </div>
        </div>

        <!-- Form Shareable Links Info Bar -->
        <div class="mb-5 bg-gradient-to-r from-slate-50 to-blue-50/50 p-3 rounded-xl border border-slate-200 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                    <i class="fas fa-link text-xs"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-800">Student Form Links</h4>
                    <p class="text-[11px] text-slate-500">Links sent to students via WhatsApp or for manual sharing.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <div class="flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-lg border border-slate-200 text-xs shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span class="font-bold text-slate-700">Subjects:</span>
                    <a href="<?php echo $subjects_link; ?>" target="_blank" class="text-blue-600 hover:underline font-mono text-[11px] truncate max-w-[150px] sm:max-w-[220px]" title="<?php echo $subjects_link; ?>"><?php echo $subjects_link; ?></a>
                    <button type="button" onclick="copyToClipboard('<?php echo $subjects_link; ?>', 'Subjects Form Link')" class="text-slate-400 hover:text-slate-700 ml-1 p-0.5" title="Copy Subjects Link">
                        <i class="fas fa-copy text-xs"></i>
                    </button>
                </div>
                <div class="flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-lg border border-slate-200 text-xs shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="font-bold text-slate-700">Results:</span>
                    <a href="<?php echo $results_link; ?>" target="_blank" class="text-blue-600 hover:underline font-mono text-[11px] truncate max-w-[150px] sm:max-w-[220px]" title="<?php echo $results_link; ?>"><?php echo $results_link; ?></a>
                    <button type="button" onclick="copyToClipboard('<?php echo $results_link; ?>', 'Results Form Link')" class="text-slate-400 hover:text-slate-700 ml-1 p-0.5" title="Copy Results Link">
                        <i class="fas fa-copy text-xs"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Submenu Tabs -->
        <div class="mb-6 flex border-b border-gray-200">
            <a href="?tab=collection" class="px-4 py-2.5 text-xs font-semibold border-b-2 flex items-center gap-2 transition-all <?php echo $active_tab === 'collection' ? 'border-blue-600 text-blue-600 bg-white rounded-t-lg shadow-xs' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                <i class="fas fa-paper-plane <?php echo $active_tab === 'collection' ? 'text-blue-600' : 'text-gray-400'; ?>"></i>
                <span>Request & Collection</span>
                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $active_tab === 'collection' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'; ?>">
                    <?php echo count($items); ?>
                </span>
            </a>
            <a href="?tab=edit_results" class="px-4 py-2.5 text-xs font-semibold border-b-2 flex items-center gap-2 transition-all <?php echo $active_tab === 'edit_results' ? 'border-blue-600 text-blue-600 bg-white rounded-t-lg shadow-xs' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                <i class="fas fa-award <?php echo $active_tab === 'edit_results' ? 'text-blue-600' : 'text-gray-400'; ?>"></i>
                <span>Edit Results</span>
                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $active_tab === 'edit_results' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'; ?>">
                    <?php echo count($submitted_results); ?>
                </span>
            </a>
        </div>

        <?php if ($active_tab === 'collection'): ?>
            <!-- ========================================== -->
            <!-- TAB 1: REQUEST & COLLECTION (ORIGINAL VIEW)-->
            <!-- ========================================== -->

            <!-- 4-Card Summary Metrics Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-6">
                <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-<?php echo $card_type === 'stream' ? 'layer-group' : 'chalkboard-teacher'; ?> text-sm"></i>
                    </div>
                    <div>
                        <div class="text-base font-bold text-gray-900 leading-tight"><?php echo number_format($total_items); ?></div>
                        <div class="text-[11px] text-gray-500 font-medium">Total <?php echo $card_type === 'stream' ? 'Streams' : 'Classes'; ?></div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-users text-sm"></i>
                    </div>
                    <div>
                        <div class="text-base font-bold text-indigo-600 leading-tight"><?php echo number_format($total_enrolled); ?></div>
                        <div class="text-[11px] text-gray-500 font-medium">Enrolled Students</div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-book-bookmark text-sm"></i>
                    </div>
                    <div>
                        <div class="text-base font-bold text-gray-900 leading-tight">
                            <span class="text-amber-600"><?php echo number_format($total_subjects_submitted); ?></span>
                            <span class="text-xs font-normal text-gray-400">(<?php echo $overall_subjects_rate; ?>%)</span>
                        </div>
                        <div class="text-[11px] text-gray-500 font-medium">Subjects Submitted</div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-award text-sm"></i>
                    </div>
                    <div>
                        <div class="text-base font-bold text-gray-900 leading-tight">
                            <span class="text-emerald-600"><?php echo number_format($total_results_submitted); ?></span>
                            <span class="text-xs font-normal text-gray-400">(<?php echo $overall_results_rate; ?>%)</span>
                        </div>
                        <div class="text-[11px] text-gray-500 font-medium">Results Submitted</div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter Controls -->
            <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs mb-6 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <div class="relative flex-1 sm:w-72">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" id="streamSearchInput" onkeyup="filterStreamCards()" placeholder="Search stream or class name..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500">
                    </div>
                    <select id="streamStatusFilter" onchange="filterStreamCards()" class="px-2.5 py-1.5 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-600">
                        <option value="">All Progress Statuses</option>
                        <option value="pending">Has Pending Submissions</option>
                        <option value="completed">100% Completed</option>
                        <option value="empty">No Students Enrolled</option>
                    </select>
                </div>
                <div class="text-[11px] text-gray-500 font-medium whitespace-nowrap">
                    Showing <span id="visibleCount" class="font-bold text-gray-800"><?php echo count($items); ?></span> <?php echo $card_type === 'stream' ? 'streams' : 'classes'; ?>
                </div>
            </div>

            <!-- Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="cardsContainer">
                <?php foreach ($items as $item): ?>
                <?php 
                    $enrolled = intval($item['enrolled_count'] ?? 0);
                    $subs = intval($item['subjects_count'] ?? 0);
                    $results = intval($item['results_count'] ?? 0);
                    $sub_pct = $enrolled > 0 ? min(100, round(($subs / $enrolled) * 100)) : 0;
                    $res_pct = $enrolled > 0 ? min(100, round(($results / $enrolled) * 100)) : 0;
                    
                    $status_category = 'pending';
                    if ($enrolled === 0) $status_category = 'empty';
                    elseif ($sub_pct === 100 && $res_pct === 100) $status_category = 'completed';
                ?>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-xs hover:shadow-md hover:border-blue-200 transition-all flex flex-col justify-between stream-card" 
                     data-name="<?php echo htmlspecialchars(strtolower($item['name'])); ?>" 
                     data-status="<?php echo $status_category; ?>">
                    
                    <div>
                        <!-- Card Header -->
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                    <i class="fas fa-<?php echo $card_type === 'stream' ? 'graduation-cap' : 'book-open'; ?> text-xs"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-xs font-bold text-gray-900 leading-snug truncate" title="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </h3>
                                    <p class="text-[11px] text-gray-400 mt-0.5">
                                        <?php echo $card_type === 'stream' ? 'Stream Offering' : htmlspecialchars($item['stream_name'] ?? 'Class'); ?>
                                    </p>
                                </div>
                            </div>

                            <?php if (isset($item['code']) && !empty($item['code'])): ?>
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 text-[10px] font-semibold rounded shrink-0 border border-slate-200">
                                    <?php echo htmlspecialchars($item['code']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Progress Statistics Bar -->
                        <div class="bg-slate-50 rounded-lg p-2.5 border border-slate-100 mb-4 space-y-2">
                            <div class="grid grid-cols-3 gap-1 text-center divide-x divide-slate-200">
                                <div>
                                    <div class="text-xs font-bold text-gray-800"><?php echo $enrolled; ?></div>
                                    <div class="text-[10px] text-gray-400 font-medium">Enrolled</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-amber-600"><?php echo $subs; ?></div>
                                    <div class="text-[10px] text-gray-400 font-medium">Subjects (<?php echo $sub_pct; ?>%)</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-emerald-600"><?php echo $results; ?></div>
                                    <div class="text-[10px] text-gray-400 font-medium">Results (<?php echo $res_pct; ?>%)</div>
                                </div>
                            </div>

                            <!-- Mini Visual Dual-Stage Bar -->
                            <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden flex">
                                <div class="bg-amber-500 h-1.5 transition-all duration-300" style="width: <?php echo $sub_pct; ?>%" title="Subjects: <?php echo $sub_pct; ?>%"></div>
                                <div class="bg-emerald-500 h-1.5 transition-all duration-300" style="width: <?php echo $res_pct; ?>%" title="Results: <?php echo $res_pct; ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-2 pt-2 border-t border-gray-100">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" 
                                    onclick="confirmAndSend('<?php echo $card_type; ?>', <?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $enrolled; ?>, 'subjects')" 
                                    class="px-2 py-1.5 bg-amber-600 hover:bg-amber-700 active:scale-95 text-white text-[11px] font-bold rounded-lg shadow-xs flex items-center justify-center gap-1 transition-all <?php echo $enrolled === 0 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>"
                                    <?php echo $enrolled === 0 ? 'disabled' : ''; ?> title="Request A/L Subjects registration (student/al_results_form.php)">
                                <i class="fab fa-whatsapp text-xs"></i>
                                <span>Get Subjects</span>
                            </button>
                            
                            <button type="button" 
                                    onclick="confirmAndSend('<?php echo $card_type; ?>', <?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $enrolled; ?>, 'results')" 
                                    class="px-2 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-[11px] font-bold rounded-lg shadow-xs flex items-center justify-center gap-1 transition-all <?php echo $enrolled === 0 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>"
                                    <?php echo $enrolled === 0 ? 'disabled' : ''; ?> title="Request A/L Results submission (student/al_exam_form.php)">
                                <i class="fab fa-whatsapp text-xs"></i>
                                <span>Get Results</span>
                            </button>
                        </div>
                        
                        <a href="view_al_responses.php?type=<?php echo $card_type; ?>&id=<?php echo $item['id']; ?>" 
                           class="w-full text-center px-3 py-1.5 bg-white border border-gray-200 hover:bg-slate-50 hover:text-blue-600 text-gray-700 text-xs font-medium rounded-lg shadow-xs flex items-center justify-center gap-1 transition-colors shrink-0"
                           title="View Student Submissions">
                            <i class="fas fa-table-list text-[11px] text-gray-400"></i>
                            <span>View Responses</span>
                            <?php if ($subs > 0): ?>
                                <span class="ml-1 bg-blue-100 text-blue-700 text-[10px] font-bold px-1.5 py-0.2 rounded-full">
                                    <?php echo $subs; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($items)): ?>
                <div class="col-span-full py-12 text-center bg-white rounded-xl border border-gray-200 shadow-xs">
                    <i class="fas fa-folder-open text-3xl text-gray-300 mb-2 block"></i>
                    <h3 class="text-sm font-semibold text-gray-700">No <?php echo $card_type === 'stream' ? 'streams' : 'classes'; ?> found</h3>
                    <p class="text-xs text-gray-400 mt-1">There are currently no active streams or class assignments configured in the LMS.</p>
                </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- ========================================== -->
            <!-- TAB 2: EDIT RESULTS (NEW MANAGEMENT TAB)   -->
            <!-- ========================================== -->

            <!-- Summary Bar for Results Tab -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-6">
                <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-file-circle-check text-sm"></i>
                    </div>
                    <div>
                        <div class="text-base font-bold text-emerald-600 leading-tight"><?php echo count($submitted_results); ?></div>
                        <div class="text-[11px] text-gray-500 font-medium">Submitted Results</div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-medal text-sm"></i>
                    </div>
                    <div>
                        <?php 
                        $ranked_count = 0;
                        foreach ($submitted_results as $sr) {
                            if (!empty($sr['district_rank']) || !empty($sr['island_rank'])) $ranked_count++;
                        }
                        ?>
                        <div class="text-base font-bold text-blue-600 leading-tight"><?php echo $ranked_count; ?></div>
                        <div class="text-[11px] text-gray-500 font-medium">With Island / District Rank</div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-calendar-check text-sm"></i>
                    </div>
                    <div>
                        <div class="text-base font-bold text-purple-600 leading-tight"><?php echo count($available_exam_years); ?></div>
                        <div class="text-[11px] text-gray-500 font-medium">Exam Batches Recorded</div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-share-nodes text-sm"></i>
                    </div>
                    <div>
                        <?php 
                        $published_count = 0;
                        foreach ($submitted_results as $sr) {
                            if (!empty($sr['agreed_to_publish'])) $published_count++;
                        }
                        ?>
                        <div class="text-base font-bold text-amber-600 leading-tight"><?php echo $published_count; ?></div>
                        <div class="text-[11px] text-gray-500 font-medium">Published on Wall</div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter Controls for Results Table -->
            <div class="bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs mb-6 flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 flex-wrap w-full md:w-auto">
                    <!-- Search Input -->
                    <div class="relative flex-1 sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" id="resultsTableSearch" onkeyup="filterResultsTable()" placeholder="Search name, ID, index number..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500">
                    </div>

                    <!-- Year Filter -->
                    <select id="resultsYearFilter" onchange="filterResultsTable()" class="px-2.5 py-1.5 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-700">
                        <option value="">All Exam Years</option>
                        <?php foreach ($available_exam_years as $yr): ?>
                            <option value="<?php echo $yr; ?>"><?php echo $yr; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Stream Filter -->
                    <select id="resultsStreamFilter" onchange="filterResultsTable()" class="px-2.5 py-1.5 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-700">
                        <option value="">All Streams</option>
                        <?php foreach ($available_streams as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="text-[11px] text-gray-500 font-medium whitespace-nowrap">
                    Showing <span id="resultsVisibleCount" class="font-bold text-gray-900"><?php echo count($submitted_results); ?></span> submitted results
                </div>
            </div>

            <!-- Results Data Table -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs table-sticky-header" id="resultsDataTable">
                        <thead>
                            <tr class="border-b border-gray-200 text-[11px] font-bold text-gray-600 uppercase tracking-wider bg-slate-50">
                                <th class="py-3 px-4">Student</th>
                                <th class="py-3 px-3">Stream</th>
                                <th class="py-3 px-3 text-center">Index Number</th>
                                <th class="py-3 px-4">3 Subjects & Results</th>
                                <th class="py-3 px-3 text-center">District Rank</th>
                                <th class="py-3 px-3 text-center">Island Rank</th>
                                <th class="py-3 px-3 text-center">Z-Score</th>
                                <th class="py-3 px-3 text-center">Exam Year</th>
                                <th class="py-3 px-4">Submitted Date</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($submitted_results as $row): ?>
                            <?php 
                                $fullname = trim(($row['first_name'] ?? '') . ' ' . ($row['second_name'] ?? ''));
                                if (empty($fullname)) $fullname = 'Student';
                                
                                $initials = '';
                                foreach (explode(' ', $fullname) as $w) {
                                    if (!empty($w)) $initials .= mb_substr($w, 0, 1, 'UTF-8');
                                }
                                $initials = strtoupper(mb_substr($initials, 0, 2, 'UTF-8'));
                                
                                $submitted_date_formatted = !empty($row['results_submitted_at']) ? date('M d, Y h:i A', strtotime($row['results_submitted_at'])) : (!empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '-');
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors result-table-row"
                                data-search="<?php echo htmlspecialchars(strtolower($fullname . ' ' . $row['student_id'] . ' ' . ($row['index_number'] ?? '') . ' ' . ($row['district'] ?? '') . ' ' . ($row['stream'] ?? ''))); ?>"
                                data-year="<?php echo htmlspecialchars($row['exam_year'] ?? ''); ?>"
                                data-stream="<?php echo htmlspecialchars($row['stream'] ?? ''); ?>">
                                
                                <!-- Student Column -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($row['photo_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($row['photo_path']); ?>" alt="<?php echo htmlspecialchars($fullname); ?>" class="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0">
                                        <?php elseif (!empty($row['profile_picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($row['profile_picture']); ?>" alt="<?php echo htmlspecialchars($fullname); ?>" class="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0">
                                        <?php else: ?>
                                            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                                <?php echo $initials ?: 'ST'; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <div class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($fullname); ?></div>
                                            <div class="text-[11px] text-gray-500 font-mono">ID: <?php echo htmlspecialchars($row['student_id']); ?></div>
                                            <?php if (!empty($row['whatsapp_number']) || !empty($row['mobile_number'])): ?>
                                                <div class="text-[10px] text-gray-400 mt-0.5 flex items-center gap-1">
                                                    <i class="fab fa-whatsapp text-emerald-500 text-[10px]"></i>
                                                    <span><?php echo htmlspecialchars($row['whatsapp_number'] ?: $row['mobile_number']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Stream Column -->
                                <td class="py-3 px-3">
                                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['stream'] ?: 'General'); ?></div>
                                    <?php if (!empty($row['district'])): ?>
                                        <div class="text-[10px] text-gray-400 mt-0.5">
                                            <i class="fas fa-location-dot text-slate-400 mr-0.5"></i> <?php echo htmlspecialchars($row['district']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Index Number Column (Separate Column) -->
                                <td class="py-3 px-3 text-center font-mono">
                                    <?php if (!empty($row['index_number'])): ?>
                                        <span class="font-bold text-slate-800 bg-slate-100 px-2.5 py-1 rounded text-xs"><?php echo htmlspecialchars($row['index_number']); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-300 font-normal">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 3 Subjects & Results -->
                                <td class="py-3 px-4">
                                    <div class="space-y-1.5 min-w-[200px]">
                                        <!-- Subject 1 -->
                                        <div class="flex items-center justify-between gap-2 bg-slate-50 px-2 py-1 rounded border border-slate-100">
                                            <span class="text-gray-700 truncate font-medium"><?php echo htmlspecialchars($row['subject_1'] ?: 'Subject 1'); ?></span>
                                            <?php echo render_grade_badge($row['result_1']); ?>
                                        </div>
                                        <!-- Subject 2 -->
                                        <div class="flex items-center justify-between gap-2 bg-slate-50 px-2 py-1 rounded border border-slate-100">
                                            <span class="text-gray-700 truncate font-medium"><?php echo htmlspecialchars($row['subject_2'] ?: 'Subject 2'); ?></span>
                                            <?php echo render_grade_badge($row['result_2']); ?>
                                        </div>
                                        <!-- Subject 3 -->
                                        <div class="flex items-center justify-between gap-2 bg-slate-50 px-2 py-1 rounded border border-slate-100">
                                            <span class="text-gray-700 truncate font-medium"><?php echo htmlspecialchars($row['subject_3'] ?: 'Subject 3'); ?></span>
                                            <?php echo render_grade_badge($row['result_3']); ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- District Rank -->
                                <td class="py-3 px-3 text-center">
                                    <?php if (!empty($row['district_rank'])): ?>
                                        <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                            #<?php echo intval($row['district_rank']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Island Rank (No emojis) -->
                                <td class="py-3 px-3 text-center">
                                    <?php if (!empty($row['island_rank'])): ?>
                                        <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            #<?php echo intval($row['island_rank']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Z-Score -->
                                <td class="py-3 px-3 text-center font-mono">
                                    <?php if (isset($row['z_score']) && $row['z_score'] !== null && $row['z_score'] !== ''): ?>
                                        <span class="font-bold text-gray-800 bg-slate-100 px-2 py-0.5 rounded text-xs">
                                            <?php echo number_format((float)$row['z_score'], 4); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Exam Year -->
                                <td class="py-3 px-3 text-center font-semibold text-gray-700">
                                    <?php echo !empty($row['exam_year']) ? htmlspecialchars($row['exam_year']) : '<span class="text-gray-300">-</span>'; ?>
                                </td>

                                <!-- Submitted Date -->
                                <td class="py-3 px-4 text-gray-500 text-[11px] whitespace-nowrap">
                                    <div><?php echo $submitted_date_formatted; ?></div>
                                    <?php if (!empty($row['agreed_to_publish'])): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] text-emerald-600 font-semibold mt-0.5">
                                            <i class="fas fa-check-circle text-[9px]"></i> Public
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 font-medium mt-0.5">
                                            <i class="fas fa-lock text-[9px]"></i> Private
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Edit Button -->
                                        <button type="button" 
                                                onclick='openEditResultModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold rounded-lg text-xs transition-colors flex items-center gap-1 shadow-2xs"
                                                title="Edit Results & Ranks">
                                            <i class="fas fa-pen-to-square text-[11px]"></i>
                                            <span>Edit</span>
                                        </button>

                                        <!-- Delete Button (Only deletes submission, NOT user) -->
                                        <button type="button" 
                                                onclick='openDeleteResultModal(<?php echo $row["id"]; ?>, "<?php echo addslashes($fullname); ?>", "<?php echo addslashes($row["student_id"]); ?>")'
                                                class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold rounded-lg text-xs transition-colors flex items-center gap-1 shadow-2xs"
                                                title="Delete this Result record">
                                            <i class="fas fa-trash-can text-[11px]"></i>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($submitted_results)): ?>
                            <tr>
                                <td colspan="10" class="py-12 text-center text-gray-400">
                                    <i class="fas fa-file-excel text-3xl text-gray-300 mb-2 block"></i>
                                    <p class="font-semibold text-gray-600">No submitted results found</p>
                                    <p class="text-xs text-gray-400 mt-1">Students will appear here as soon as they submit their exam results and grades.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- ===================== EDIT RESULT MODAL ===================== -->
    <div id="editResultModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" onclick="closeEditResultModal()"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 bg-slate-50 border-b border-gray-200">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-award text-blue-600"></i> Edit Student A/L Results & Ranks
                        </h3>
                        <p class="text-[11px] text-gray-500 mt-0.5" id="editModalSubtitle">Update grades, island rank, district rank, and z-score</p>
                    </div>
                    <button type="button" onclick="closeEditResultModal()" class="w-7 h-7 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 flex items-center justify-center transition-colors">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>

                <!-- Modal Form -->
                <form action="request_al_details.php?tab=edit_results" method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="update_result">
                    <input type="hidden" name="submission_id" id="editSubmissionId" value="">

                    <!-- Student Info Banner -->
                    <div class="p-3 bg-blue-50/70 border border-blue-100 rounded-xl flex items-center justify-between gap-4">
                        <div>
                            <div class="font-bold text-blue-950 text-xs" id="editStudentName">Student Name</div>
                            <div class="text-[11px] text-blue-700 font-mono mt-0.5" id="editStudentID">ID: -</div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">
                                Results Editor
                            </span>
                        </div>
                    </div>

                    <!-- Stream & Exam Year & District & Index -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">A/L Stream</label>
                            <input type="text" name="stream" id="editStream" required class="w-full px-3 py-2 bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-800">
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Exam Year</label>
                            <input type="number" name="exam_year" id="editExamYear" placeholder="e.g. 2024" class="w-full px-3 py-2 bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-800">
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">
                                Exam Index / Admission Number
                            </label>
                            <input type="text" name="index_number" id="editIndexNumber" placeholder="e.g. 1234567" class="w-full px-3 py-2 bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-800 font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">District</label>
                            <input type="text" name="district" id="editDistrict" class="w-full px-3 py-2 bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-800">
                        </div>
                    </div>

                    <!-- 3 Subjects & Grades Section -->
                    <div class="border-t border-gray-100 pt-3">
                        <label class="block font-bold text-gray-800 text-xs mb-2 flex items-center gap-1.5">
                            <i class="fas fa-book-open text-blue-600"></i> Subjects & Results (Grades)
                        </label>
                        <div class="space-y-2.5">
                            <!-- Subject 1 -->
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-2">
                                    <input type="text" name="subject_1" id="editSubject1" placeholder="Subject 1 Name" required class="w-full px-3 py-2 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500">
                                </div>
                                <div>
                                    <select name="result_1" id="editResult1" required class="w-full px-3 py-2 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 font-bold">
                                        <option value="">Result</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="S">S</option>
                                        <option value="F">F</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Subject 2 -->
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-2">
                                    <input type="text" name="subject_2" id="editSubject2" placeholder="Subject 2 Name" required class="w-full px-3 py-2 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500">
                                </div>
                                <div>
                                    <select name="result_2" id="editResult2" required class="w-full px-3 py-2 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 font-bold">
                                        <option value="">Result</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="S">S</option>
                                        <option value="F">F</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Subject 3 -->
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-2">
                                    <input type="text" name="subject_3" id="editSubject3" placeholder="Subject 3 Name" required class="w-full px-3 py-2 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500">
                                </div>
                                <div>
                                    <select name="result_3" id="editResult3" required class="w-full px-3 py-2 text-xs bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 font-bold">
                                        <option value="">Result</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="S">S</option>
                                        <option value="F">F</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ranks & Z-Score Section -->
                    <div class="border-t border-gray-100 pt-3">
                        <label class="block font-bold text-gray-800 text-xs mb-2 flex items-center gap-1.5">
                            <i class="fas fa-medal text-amber-500"></i> Ranks & Z-Score
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">District Rank</label>
                                <input type="number" name="district_rank" id="editDistrictRank" placeholder="e.g. 15" class="w-full px-3 py-2 bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-800 font-bold">
                            </div>
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Island Rank</label>
                                <input type="number" name="island_rank" id="editIslandRank" placeholder="e.g. 45" class="w-full px-3 py-2 bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-800 font-bold">
                            </div>
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Z-Score</label>
                                <input type="number" step="0.0001" name="z_score" id="editZScore" placeholder="e.g. 1.8745" class="w-full px-3 py-2 bg-slate-50 border border-gray-200 rounded-lg focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 text-gray-800 font-bold font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- Publish Consent Option -->
                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="agreed_to_publish" id="editAgreedToPublish" value="1" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                            <span class="text-xs font-semibold text-gray-700">Display this student's result on the public results wall (AL Details Showcase)</span>
                        </label>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 flex items-center justify-end gap-2 border-t border-gray-100">
                        <button type="button" onclick="closeEditResultModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-xs transition-all flex items-center gap-1.5 active:scale-95">
                            <i class="fas fa-save"></i>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================== DELETE CONFIRMATION MODAL ===================== -->
    <div id="deleteResultModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" onclick="closeDeleteResultModal()"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl border border-gray-200 p-6">
                <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 border border-rose-100">
                    <i class="fas fa-trash-can text-lg"></i>
                </div>

                <div class="text-center">
                    <h3 class="text-base font-bold text-gray-900">Delete A/L Result Record?</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Are you sure you want to delete the result submission for:
                    </p>
                    <div class="my-3 p-2.5 bg-rose-50/70 border border-rose-100 rounded-lg">
                        <div class="font-bold text-rose-950 text-xs" id="deleteStudentName">-</div>
                        <div class="text-[11px] text-rose-700 font-mono mt-0.5" id="deleteStudentID">ID: -</div>
                    </div>
                    <div class="p-2.5 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-[11px] text-left leading-relaxed">
                        <i class="fas fa-circle-info text-amber-600 mr-1"></i>
                        <strong>Important:</strong> Only the exam results submission will be deleted. The student's LMS user account and class enrollments will <strong>NOT</strong> be deleted.
                    </div>
                </div>

                <form action="request_al_details.php?tab=edit_results" method="POST" class="mt-5 flex items-center justify-end gap-2">
                    <input type="hidden" name="action" value="delete_result">
                    <input type="hidden" name="submission_id" id="deleteSubmissionId" value="">
                    
                    <button type="button" onclick="closeDeleteResultModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white rounded-lg shadow-xs transition-all flex items-center gap-1.5 active:scale-95">
                        <i class="fas fa-trash-can"></i>
                        <span>Confirm Delete</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================== WHATSAPP CONFIRMATION & PREVIEW MODAL ===================== -->
    <div id="confirmModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" onclick="closeConfirmModal()"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative bg-white w-full max-w-md rounded-xl shadow-xl border border-gray-200 p-5 sm:p-6">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2" id="modalHeaderTitle">
                        <i class="fab fa-whatsapp text-emerald-600 text-base"></i> Confirm WhatsApp Dispatch
                    </h3>
                    <button type="button" onclick="closeConfirmModal()" class="w-6 h-6 rounded text-gray-400 hover:text-gray-600 flex items-center justify-center">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <p class="text-gray-600" id="modalSubDescription">
                        You are about to send A/L collection reminder messages to all enrolled students in:
                    </p>
                    <div class="p-2.5 bg-blue-50/70 border border-blue-100 rounded-lg">
                        <div class="font-bold text-blue-900" id="modalTargetName"></div>
                        <div class="text-[11px] text-blue-700 mt-0.5" id="modalRecipientCount"></div>
                    </div>

                    <div>
                        <label class="font-semibold text-gray-700 block mb-1">Message Preview:</label>
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg text-[11px] text-gray-600 max-h-48 overflow-y-auto font-mono whitespace-pre-wrap leading-relaxed" id="modalMessagePreview"></div>
                    </div>
                </div>

                <div class="pt-4 mt-4 flex items-center justify-end gap-2 border-t border-gray-100">
                    <button type="button" onclick="closeConfirmModal()" class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="button" id="modalConfirmBtn" onclick="executeSendRequest()" class="px-4 py-1.5 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-xs transition-colors flex items-center gap-1.5">
                        <i class="fas fa-paper-plane text-[11px]"></i>
                        <span>Confirm & Send</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== LOADING DISPATCH MODAL ===================== -->
    <div id="loadingModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl p-5 max-w-xs w-full text-center shadow-xl border border-gray-200">
            <div class="animate-spin rounded-full h-9 w-9 border-2 border-emerald-600 border-t-transparent mx-auto mb-3"></div>
            <h4 class="text-xs font-bold text-gray-900">Sending WhatsApp Messages...</h4>
            <p class="text-[11px] text-gray-500 mt-1">Please wait while notifications are dispatched to enrolled students.</p>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="statusToast" class="fixed bottom-6 right-6 z-50 transform translate-y-16 opacity-0 transition-all duration-200 bg-gray-900 text-white shadow-lg rounded-lg px-4 py-2.5 flex items-center gap-2.5 text-xs font-medium">
        <i id="toastIcon" class="fas fa-check text-emerald-400 text-sm"></i>
        <span id="toastMessage">Notifications dispatched successfully</span>
    </div>

    <script>
        let currentRequestPayload = null;
        const subjectsLink = <?php echo json_encode($subjects_link); ?>;
        const resultsLink = <?php echo json_encode($results_link); ?>;

        function getMessageTemplate(name, mode) {
            if (mode === 'results') {
                return `📢 *A/L Results Collection / උසස් පෙළ ප්‍රතිඵල ලබාගැනීම*\n\nHello [Student Name],\n📌 Stream: ${name}\n\nඔබ තවමත් අප වෙත ඔබගේ ප්‍රතිඵල දැනුම් දී නොමැති නම් හෝ සම්පූර්ණ තොරතුරු ලබා දී නොමැති නම්, ඔබගේ උසස් පෙළ විභාග ප්‍රතිඵල පහත සබැඳියෙන් (Link) ගොස් තොරතුරු ඇතුළත් කරන්න.\nIf you have not yet notified us of your results or have not provided complete information, please enter your A/L exam results by going to the link below.\n\n🔗 ${resultsLink}\n\n---------------------------------------\n\n| Lernerr.LK 🇱🇰\n| Best Place for Your Online Learning`;
            } else {
                return `📢 *A/L Subjects Registration / උසස් පෙළ විෂය තොරතුරු ලබාගැනීම*\n\nHello [Student Name],\n📌 ${name}\n\nPlease submit your A/L examination subjects and details using the link below:\n\n🔗 ${subjectsLink}\n\n------------------------------------------\nඔබගේ උසස් පෙළ විෂයන් පිළිබඳ තොරතුරු ඉහත සබැඳියෙන් ඇතුළත් කරන්න.\n⚠️ *Note:* Submission is required to unlock full access to your learning portal.\n\nLernerr.LK Team\n\n| Lernerr.LK 🇱🇰\n| Best Place for Your Online Learning`;
            }
        }

        // Open WhatsApp Confirmation Modal
        function confirmAndSend(type, id, name, count, mode = 'subjects') {
            currentRequestPayload = { type, id, name, count, mode };
            document.getElementById('modalTargetName').textContent = (mode === 'results' ? '📊 [Get Results] ' : '📚 [Get Subjects] ') + name;
            document.getElementById('modalRecipientCount').textContent = `Target: ${count} actively enrolled student(s)`;
            document.getElementById('modalMessagePreview').textContent = getMessageTemplate(name, mode);
            document.getElementById('modalConfirmBtn').classList.remove('hidden');
            document.getElementById('confirmModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            currentRequestPayload = null;
        }

        function openPreviewModal(mode = 'subjects') {
            document.getElementById('confirmModal').classList.remove('hidden');
            document.getElementById('modalTargetName').textContent = mode === 'results' ? 'Sample: A/L Results Request (student/al_exam_form.php)' : 'Sample: A/L Subjects Request (student/al_results_form.php)';
            document.getElementById('modalRecipientCount').textContent = 'Sample preview message format';
            document.getElementById('modalMessagePreview').textContent = getMessageTemplate('[Stream / Class Name]', mode);
            document.getElementById('modalConfirmBtn').classList.add('hidden');
            document.body.style.overflow = 'hidden';
        }

        // Execute Sending WhatsApp Messages
        function executeSendRequest() {
            if (!currentRequestPayload) return;
            const { type, id, name, mode } = currentRequestPayload;
            closeConfirmModal();

            document.getElementById('loadingModal').classList.remove('hidden');
            
            const formData = new FormData();
            formData.append('send_request', '1');
            formData.append('request_type', type);
            formData.append('request_mode', mode || 'subjects');
            formData.append('id', id);
            formData.append('name', name);
            
            fetch('request_al_details.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('loadingModal').classList.add('hidden');
                if (data.success) {
                    showToast(`Successfully sent WhatsApp requests to ${data.count} student(s)!`, 'success');
                } else {
                    showToast(data.message || 'Error occurred while sending messages.', 'error');
                }
            })
            .catch(err => {
                document.getElementById('loadingModal').classList.add('hidden');
                console.error(err);
                showToast('Network error while dispatching messages.', 'error');
            });
        }

        // Live Filter for Stream Cards (Collection Tab)
        function filterStreamCards() {
            const query = (document.getElementById('streamSearchInput')?.value || '').toLowerCase();
            const statusFilter = document.getElementById('streamStatusFilter')?.value || '';
            const cards = document.querySelectorAll('#cardsContainer .stream-card');
            let visible = 0;

            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const status = card.getAttribute('data-status') || '';
                const matchesText = name.includes(query);
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesText && matchesStatus) {
                    card.style.display = '';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            const countSpan = document.getElementById('visibleCount');
            if (countSpan) countSpan.textContent = visible;
        }

        // Live Filter for Results Table (Edit Results Tab)
        function filterResultsTable() {
            const query = (document.getElementById('resultsTableSearch')?.value || '').toLowerCase();
            const yearFilter = document.getElementById('resultsYearFilter')?.value || '';
            const streamFilter = (document.getElementById('resultsStreamFilter')?.value || '').toLowerCase();
            const rows = document.querySelectorAll('#resultsDataTable .result-table-row');
            let visible = 0;

            rows.forEach(row => {
                const searchText = row.getAttribute('data-search') || '';
                const year = row.getAttribute('data-year') || '';
                const stream = (row.getAttribute('data-stream') || '').toLowerCase();

                const matchesQuery = !query || searchText.includes(query);
                const matchesYear = !yearFilter || year === yearFilter;
                const matchesStream = !streamFilter || stream === streamFilter;

                if (matchesQuery && matchesYear && matchesStream) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            const countSpan = document.getElementById('resultsVisibleCount');
            if (countSpan) countSpan.textContent = visible;
        }

        // Edit Modal Handlers
        function openEditResultModal(data) {
            if (!data) return;
            document.getElementById('editSubmissionId').value = data.id || '';
            const fullname = ((data.first_name || '') + ' ' + (data.second_name || '')).trim() || 'Student';
            document.getElementById('editStudentName').textContent = fullname;
            document.getElementById('editStudentID').textContent = 'ID: ' + (data.student_id || '-');

            document.getElementById('editStream').value = data.stream || '';
            document.getElementById('editExamYear').value = data.exam_year || '';
            document.getElementById('editIndexNumber').value = data.index_number || '';
            document.getElementById('editDistrict').value = data.district || '';

            document.getElementById('editSubject1').value = data.subject_1 || '';
            document.getElementById('editResult1').value = (data.result_1 || '').toUpperCase();
            document.getElementById('editSubject2').value = data.subject_2 || '';
            document.getElementById('editResult2').value = (data.result_2 || '').toUpperCase();
            document.getElementById('editSubject3').value = data.subject_3 || '';
            document.getElementById('editResult3').value = (data.result_3 || '').toUpperCase();

            document.getElementById('editDistrictRank').value = data.district_rank || '';
            document.getElementById('editIslandRank').value = data.island_rank || '';
            document.getElementById('editZScore').value = data.z_score || '';

            document.getElementById('editAgreedToPublish').checked = data.agreed_to_publish == 1;

            document.getElementById('editResultModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeEditResultModal() {
            document.getElementById('editResultModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Delete Modal Handlers
        function openDeleteResultModal(submissionId, name, studentId) {
            document.getElementById('deleteSubmissionId').value = submissionId;
            document.getElementById('deleteStudentName').textContent = name;
            document.getElementById('deleteStudentID').textContent = 'ID: ' + studentId;
            document.getElementById('deleteResultModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteResultModal() {
            document.getElementById('deleteResultModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Copy link to clipboard
        function copyToClipboard(text, label) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast(label + ' copied to clipboard!', 'success');
                }).catch(() => {
                    fallbackCopy(text, label);
                });
            } else {
                fallbackCopy(text, label);
            }
        }

        function fallbackCopy(text, label) {
            const input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            showToast(label + ' copied to clipboard!', 'success');
        }

        // Toast Feedback
        function showToast(message, type = 'success') {
            const toast = document.getElementById('statusToast');
            const toastMsg = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            
            toastMsg.textContent = message;
            if (type === 'success') {
                toastIcon.className = 'fas fa-check text-emerald-400 text-sm';
            } else {
                toastIcon.className = 'fas fa-triangle-exclamation text-rose-400 text-sm';
            }

            toast.classList.remove('translate-y-16', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-16', 'opacity-0');
            }, 3500);
        }

        // Flash message from PHP redirect
        <?php if (!empty($toast_data)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?php echo json_encode($toast_data['message']); ?>, <?php echo json_encode($toast_data['type'] ?? 'success'); ?>);
            });
        <?php endif; ?>
    </script>
</body>
</html>
