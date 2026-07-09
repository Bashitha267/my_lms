<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all completed sessions for this instructor
// joining instructor_ratings to get the student's rating (if given)
$stmt = $conn->prepare("
    SELECT 
        ir.id as request_id,
        ir.session_date,
        ir.created_at,
        s.name as subject_name,
        u.first_name, u.second_name, u.profile_picture,
        isess.status as session_status,
        isess.ended_at,
        rat.rating, rat.review
    FROM instructor_requests ir
    JOIN subjects s ON ir.subject_id = s.id
    JOIN users u ON ir.student_id = u.user_id
    LEFT JOIN instructor_sessions isess ON isess.request_id = ir.id
    LEFT JOIN instructor_ratings rat ON rat.request_id = ir.id
    WHERE ir.accepted_by = ?
      AND ir.status IN ('completed', 'paid')
    ORDER BY COALESCE(isess.ended_at, ir.created_at) DESC
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary stats
$total = count($sessions);
$rated = array_filter($sessions, fn($r) => !is_null($r['rating']));
$avg_rating = count($rated) > 0 ? round(array_sum(array_column(iterator_to_array((function() use ($rated) { yield from $rated; })()), 'rating')) / count($rated), 1) : null;
// simpler avg:
$sum_rating = 0; $cnt_rating = 0;
foreach ($sessions as $s) { if (!is_null($s['rating'])) { $sum_rating += $s['rating']; $cnt_rating++; } }
$avg_rating = $cnt_rating > 0 ? round($sum_rating / $cnt_rating, 1) : null;
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
    <title>Session History | LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .star-filled { color: #f59e0b; }
        .star-empty  { color: #e2e8f0; }
    </style>
</head>
<body class="pb-16">

    <?php include 'navbar.php'; ?>

    <div class="max-w-5xl mx-auto px-4 pt-8">

        <!-- Header -->
        <div class="flex items-center justify-between mb-8 border-b border-slate-100 pb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Session History</h1>
                <p class="text-slate-400 text-sm mt-1">All your completed private sessions</p>
            </div>
            <span class="bg-slate-900 text-white px-3 py-1 rounded text-[10px] font-black uppercase tracking-widest">Instructor</span>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-white border border-slate-100 rounded-2xl p-5 text-center">
                <p class="text-3xl font-black text-slate-900"><?= $total ?></p>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Total Sessions</p>
            </div>
            <div class="bg-white border border-slate-100 rounded-2xl p-5 text-center">
                <p class="text-3xl font-black text-slate-900"><?= $cnt_rating ?></p>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Rated Sessions</p>
            </div>
            <div class="bg-white border border-slate-100 rounded-2xl p-5 text-center">
                <?php if ($avg_rating): ?>
                    <p class="text-3xl font-black text-amber-500"><?= $avg_rating ?> <span class="text-lg">★</span></p>
                <?php else: ?>
                    <p class="text-3xl font-black text-slate-300">—</p>
                <?php endif; ?>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Avg. Rating</p>
            </div>
        </div>

        <!-- Sessions Table -->
        <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
            <?php if (empty($sessions)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <i class="fas fa-history text-5xl text-slate-200 mb-4"></i>
                    <p class="text-slate-400 font-medium">No completed sessions yet.</p>
                    <p class="text-slate-300 text-sm mt-1">Sessions will appear here once marked as completed.</p>
                </div>
            <?php else: ?>
                <!-- Table Header -->
                <div class="grid grid-cols-12 bg-slate-50 border-b border-slate-100 px-6 py-3">
                    <div class="col-span-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Student</div>
                    <div class="col-span-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Subject</div>
                    <div class="col-span-2 text-[9px] font-black text-slate-400 uppercase tracking-widest">Date</div>
                    <div class="col-span-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Rating</div>
                </div>

                <!-- Rows -->
                <?php foreach ($sessions as $sess): 
                    $student_name = trim($sess['first_name'] . ' ' . $sess['second_name']);
                    $date_display = $sess['ended_at'] 
                        ? date('M d, Y', strtotime($sess['ended_at'])) 
                        : ($sess['session_date'] ? date('M d, Y', strtotime($sess['session_date'])) : date('M d, Y', strtotime($sess['created_at'])));
                    $rating = $sess['rating'];
                ?>
                <div class="grid grid-cols-12 items-center px-6 py-4 border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                    <!-- Student -->
                    <div class="col-span-4 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 text-sm font-bold flex-shrink-0">
                            <?php if (!empty($sess['profile_picture'])): ?>
                                <img src="../<?= htmlspecialchars($sess['profile_picture']) ?>" class="w-9 h-9 rounded-full object-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($sess['first_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800"><?= htmlspecialchars($student_name) ?></p>
                            <?php if (!is_null($sess['rating']) && !empty($sess['review'])): ?>
                                <p class="text-[10px] text-slate-400 italic truncate max-w-[160px]">"<?= htmlspecialchars($sess['review']) ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Subject -->
                    <div class="col-span-3">
                        <span class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($sess['subject_name']) ?></span>
                    </div>

                    <!-- Date -->
                    <div class="col-span-2">
                        <span class="text-sm text-slate-500"><?= $date_display ?></span>
                    </div>

                    <!-- Rating -->
                    <div class="col-span-3">
                        <?php if (!is_null($rating)): ?>
                            <div class="flex items-center gap-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star text-sm <?= $i <= $rating ? 'star-filled' : 'star-empty' ?>"></i>
                                <?php endfor; ?>
                                <span class="text-xs font-bold text-slate-500 ml-1"><?= $rating ?>/5</span>
                            </div>
                        <?php else: ?>
                            <span class="text-[11px] font-bold text-slate-300 uppercase tracking-widest">Not rated yet</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
