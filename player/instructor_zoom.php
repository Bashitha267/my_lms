<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config.php';

$user_id = $_SESSION['user_id'] ?? '';
$role    = $_SESSION['role'] ?? '';

if (empty($user_id)) {
    header("Location: /lms/login.php");
    exit();
}

$request_id = intval($_GET['request_id'] ?? 0);
if (!$request_id) {
    die("Invalid request.");
}

// lightweight endpoint for polling status
if (isset($_GET['check_status'])) {
    $r = $conn->query("SELECT status FROM instructor_sessions WHERE request_id = " . $request_id);
    $row = $r->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode(['status' => $row['status'] ?? '']);
    exit();
}

// Handle instructor ending the session (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_session'])) {
    $rid = intval($_POST['request_id']);
    // Mark instructor_sessions as completed
    $conn->query("UPDATE instructor_sessions SET status = 'completed', ended_at = NOW() WHERE request_id = $rid");
    // Also update the request for the student's view
    $conn->query("UPDATE instructor_requests SET status = 'completed' WHERE id = $rid AND accepted_by = '$user_id'");
    echo json_encode(['success' => true]);
    exit();
}

// Handle rating submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rid    = intval($_POST['request_id']);
    $rating = intval($_POST['rating']);
    $review = trim($_POST['review'] ?? '');
    if ($rating >= 1 && $rating <= 5) {
        // Get instructor_id from request
        $rq = $conn->query("SELECT accepted_by FROM instructor_requests WHERE id = $rid AND student_id = '$user_id'");
        if ($rq && $row = $rq->fetch_assoc()) {
            $instructor_id = $row['accepted_by'];
            $ins_stmt = $conn->prepare("INSERT INTO instructor_ratings (instructor_id, student_id, request_id, rating, review, created_at)
                                        VALUES (?, ?, ?, ?, ?, NOW())
                                        ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)");
            if ($ins_stmt) {
                $ins_stmt->bind_param("ssiis", $instructor_id, $user_id, $rid, $rating, $review);
                $ins_stmt->execute();
            }
            // Recalculate instructor average rating
            $conn->query("UPDATE users SET rating = (SELECT AVG(rating) FROM instructor_ratings WHERE instructor_id = '$instructor_id') WHERE user_id = '$instructor_id'");
        }
    }
    header("Location: ../dashboard/instructors.php");
    exit();
}

// Fetch the instructor request + session details from instructor_sessions
$stmt = $conn->prepare("
    SELECT ir.*, s.name as subject_name,
           u.first_name as inst_first, u.second_name as inst_second,
           u.profile_picture as inst_pic,
           isess.zoom_link, isess.zoom_meeting_id, isess.zoom_password,
           COALESCE(isess.status, 'scheduled') as session_status
    FROM instructor_requests ir
    JOIN subjects s ON ir.subject_id = s.id
    JOIN users u ON ir.accepted_by = u.user_id
    LEFT JOIN instructor_sessions isess ON isess.request_id = ir.id
    WHERE ir.id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    die("Session not found.");
}

// Access control
$is_instructor = ($role === 'instructor' && $req['accepted_by'] === $user_id);
$is_student    = ($role === 'student'    && $req['student_id'] === $user_id);
if (!$is_instructor && !$is_student) {
    die("Access denied.");
}

if (empty($req['zoom_link'])) {
    die("Zoom link not set yet. Please wait for your instructor to set it up.");
}

// Check if student already rated this session
$already_rated = false;
if ($is_student) {
    $rr = $conn->prepare("SELECT id FROM instructor_ratings WHERE request_id = ? AND student_id = ?");
    $rr->bind_param("is", $request_id, $user_id);
    $rr->execute();
    $already_rated = $rr->get_result()->num_rows > 0;
    $rr->close();
}

$instructor_name = trim($req['inst_first'] . ' ' . $req['inst_second']);
$zoom_link       = $req['zoom_link'];
$session_ended   = ($req['session_status'] === 'completed' || $req['status'] === 'completed');

$back_url = ($role === 'instructor') ? '../instructor/dashboard.php' : '../dashboard/instructors.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($req['subject_name']) ?> — Private Session | LearnerX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100vh; width: 100vw; overflow: hidden; background: #000; font-family: 'Inter', sans-serif; }
        #zoom-frame { width: 100%; height: 100vh; border: 0; display: block; }
        .hud {
            position: fixed; top: 1rem; left: 50%; transform: translateX(-50%);
            display: flex; align-items: center; gap: 1rem;
            background: rgba(0,0,0,0.75); backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 0.6rem 1.2rem; border-radius: 9999px; z-index: 100;
        }
        .hud-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; animation: blink 1.5s infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
        .end-btn {
            position: fixed; top: 1rem; right: 1rem; z-index: 100;
            background: rgba(220,38,38,0.9); backdrop-filter: blur(10px);
            color: white; border: none; padding: 0.65rem 1.2rem;
            border-radius: 0.5rem; cursor: pointer; font-weight: 700; font-size: 0.8rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .back-btn {
            position: fixed; top: 1rem; left: 1rem; z-index: 100;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(10px);
            color: white; border: none; padding: 0.65rem 1rem;
            border-radius: 0.5rem; cursor: pointer; font-weight: 600; font-size: 0.8rem;
            display: flex; align-items: center; gap: 0.5rem; text-decoration: none;
        }
        /* Rating Modal */
        .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 999; display: flex; align-items: center; justify-content: center; }
        .modal { background: white; border-radius: 1.5rem; padding: 2.5rem; max-width: 420px; width: 90%; text-align: center; }
        .stars-row { display: flex; justify-content: center; gap: 0.5rem; margin: 1.5rem 0; }
        .star { font-size: 2.5rem; cursor: pointer; color: #d1d5db; transition: color 0.15s; }
        .star.selected, .star:hover ~ .star { color: #d1d5db; }
        .stars-row:hover .star { color: #f59e0b; }
        .stars-row .star:hover ~ .star { color: #d1d5db; }
    </style>
</head>
<body>
    <a href="<?= $back_url ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>

    <!-- HUD -->
    <div class="hud">
        <?php if (!$session_ended): ?><div class="hud-dot"></div><?php endif; ?>
        <span style="color:white;font-size:0.8rem;font-weight:600;">
            <?= htmlspecialchars($req['subject_name']) ?> — <?= htmlspecialchars($instructor_name) ?>
        </span>
        <?php if ($session_ended): ?>
            <span style="background:#6b7280;color:white;font-size:0.65rem;font-weight:800;text-transform:uppercase;padding:2px 8px;border-radius:9999px;">Ended</span>
        <?php endif; ?>
    </div>

    <?php if ($is_instructor && !$session_ended): ?>
    <button class="end-btn" onclick="endSession()">
        <i class="fas fa-stop-circle"></i> End Session
    </button>
    <?php endif; ?>

    <!-- Zoom iframe (direct link, opens in frame) -->
    <iframe id="zoom-frame" src="<?= htmlspecialchars($zoom_link) ?>" allow="microphone; camera; fullscreen; display-capture"></iframe>

    <!-- Rating Modal (auto-shown when session ended) -->
    <?php if ($is_student && !$already_rated): ?>
    <div class="modal-bg" id="rating-modal" style="<?= $session_ended ? '' : 'display: none;' ?>">
        <div class="modal">
            <div style="width:64px;height:64px;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                <i class="fas fa-star" style="font-size:1.8rem;color:#f59e0b;"></i>
            </div>
            <h2 style="font-size:1.3rem;font-weight:800;color:#1e293b;margin-bottom:0.5rem;">Rate Your Session</h2>
            <p style="color:#64748b;font-size:0.85rem;margin-bottom:0.5rem;">How was your session with <strong><?= htmlspecialchars($instructor_name) ?></strong>?</p>
            
            <form method="POST">
                <input type="hidden" name="submit_rating" value="1">
                <input type="hidden" name="request_id" value="<?= $request_id ?>">
                <input type="hidden" name="rating" id="rating-val" value="0">

                <div class="stars-row" id="stars-row">
                    <?php for($i=1;$i<=5;$i++): ?>
                    <span class="star" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)">&#9733;</span>
                    <?php endfor; ?>
                </div>

                <textarea name="review" rows="3" placeholder="Share your experience (optional)..."
                    style="width:100%;padding:0.75rem 1rem;border:1px solid #e2e8f0;border-radius:0.75rem;font-size:0.85rem;resize:none;outline:none;margin-bottom:1.5rem;font-family:inherit;"></textarea>

                <button type="submit" style="width:100%;background:#1e293b;color:white;padding:0.9rem;border-radius:0.75rem;font-weight:800;font-size:0.9rem;border:none;cursor:pointer;">
                    Submit Rating
                </button>
                <a href="../dashboard/instructors.php" style="display:block;margin-top:0.75rem;color:#94a3b8;font-size:0.8rem;font-weight:600;">Skip for now</a>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function endSession() {
            if (!confirm('End this session? The student will be prompted to rate you.')) return;
            const fd = new FormData();
            fd.append('end_session', '1');
            fd.append('request_id', '<?= $request_id ?>');
            fetch('instructor_zoom.php?request_id=<?= $request_id ?>', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        alert('Session ended. The student will now be prompted to rate you.');
                        window.location.href = '<?= $back_url ?>';
                    }
                });
        }

        function setRating(val) {
            document.getElementById('rating-val').value = val;
            document.querySelectorAll('.star').forEach((s, i) => {
                s.style.color = i < val ? '#f59e0b' : '#d1d5db';
            });
        }

        <?php if ($is_student && !$session_ended): ?>
        // Poll for session end so modal appears automatically
        let pollStatus = setInterval(() => {
            fetch('instructor_zoom.php?request_id=<?= $request_id ?>&check_status=1')
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'completed') {
                        clearInterval(pollStatus);
                        // Hide zoom frame, show modal
                        document.getElementById('zoom-frame').style.display = 'none';
                        const modal = document.getElementById('rating-modal');
                        if (modal) modal.style.display = 'flex';
                        
                        // Update HUD
                        const hudDot = document.querySelector('.hud-dot');
                        if (hudDot) hudDot.remove();
                    }
                });
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
