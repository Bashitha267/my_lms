<?php
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$current_year = date('Y');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

// Fetch years for filter
$years_query = "SELECT DISTINCT academic_year FROM teacher_assignments WHERE teacher_id = ? ORDER BY academic_year DESC";
$years_stmt = $conn->prepare($years_query);
$years_stmt->bind_param("s", $user_id);
$years_stmt->execute();
$years_result = $years_stmt->get_result();
$available_years = [];
while ($row = $years_result->fetch_assoc()) {
    $available_years[] = $row['academic_year'];
}
if (!in_array($current_year, $available_years)) {
    array_unshift($available_years, $current_year);
}
$years_stmt->close();

// Fetch teacher assignments (subjects) for the selected year
$assignments_query = "SELECT ta.*, s.name as stream_name, sub.name as subject_name, sub.code as subject_code
                     FROM teacher_assignments ta
                     INNER JOIN stream_subjects ss ON ta.stream_subject_id = ss.id
                     INNER JOIN streams s ON ss.stream_id = s.id
                     INNER JOIN subjects sub ON ss.subject_id = sub.id
                     WHERE ta.teacher_id = ? AND ta.academic_year = ? AND ta.status = 'active'";
$assign_stmt = $conn->prepare($assignments_query);
$assign_stmt->bind_param("si", $user_id, $selected_year);
$assign_stmt->execute();
$assignments_result = $assign_stmt->get_result();
$assignments = [];
while ($row = $assignments_result->fetch_assoc()) {
    $assignments[] = $row;
}
$assign_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .premium-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .premium-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Attendance Reports</h1>
                <p class="mt-2 text-sm text-gray-600">Select a subject to view detailed attendance reports.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <form action="" method="GET" class="flex items-center space-x-3">
                    <label for="year" class="text-sm font-medium text-gray-700">Filter by Year:</label>
                    <select name="year" id="year" onchange="this.form.submit()" 
                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md shadow-sm">
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <?php if (empty($assignments)): ?>
            <div class="text-center py-20 bg-white rounded-2xl shadow-sm border border-gray-100">
                <i class="fas fa-folder-open text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900">No subjects found</h3>
                <p class="mt-1 text-gray-500">You don't have any active subject assignments for <?php echo $selected_year; ?>.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($assignments as $subject): ?>
                    <a href="subject_report.php?assignment_id=<?php echo $subject['id']; ?>&year=<?php echo $selected_year; ?>" 
                       class="premium-card rounded-2xl p-6 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                            <i class="fas fa-book text-6xl"></i>
                        </div>
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center text-red-600 mr-4">
                                <i class="fas fa-graduation-cap text-xl"></i>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-red-600 uppercase tracking-wider"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                <h3 class="text-xl font-bold text-gray-900 group-hover:text-red-600 transition-colors">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </h3>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-layer-group w-5 text-gray-400"></i>
                                <span><?php echo htmlspecialchars($subject['stream_name']); ?></span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-calendar-alt w-5 text-gray-400"></i>
                                <span>Academic Year: <?php echo htmlspecialchars($subject['academic_year']); ?></span>
                            </div>
                        </div>
                        <div class="mt-6 flex items-center text-sm font-semibold text-red-600 group-hover:translate-x-1 transition-transform">
                            View Reports <i class="fas fa-arrow-right ml-2"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
