<?php
// Proxy file: serves dashboard/recordings.php from the root URL (/recordings)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Prevent browsers/proxies from caching the auth-check result
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// If not logged in, show auth popup instead of redirecting
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/config.php';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Required - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.92) translateY(20px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-card { animation: modalIn 0.35s cubic-bezier(0.16,1,0.3,1) forwards; }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">

    <!-- Blurred background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-red-600/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-slate-600/30 rounded-full blur-3xl"></div>
    </div>

    <!-- Auth Modal -->
    <div class="modal-card relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center z-10">
        <!-- Icon -->
        <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-5">
            <i class="fas fa-play-circle text-red-600 text-3xl"></i>
        </div>

        <!-- Text -->
        <h1 class="text-2xl font-black text-slate-900 mb-2">Recordings</h1>
        <p class="text-slate-500 font-semibold text-sm mb-1">This section requires an account.</p>
        <p class="text-slate-400 text-xs mb-7">Login or create a free account to access class recordings and more.</p>

        <!-- Divider -->
        <div class="h-px bg-slate-100 mb-7"></div>

        <!-- Buttons -->
        <div class="space-y-3">
            <a href="<?php echo BASE_PATH; ?>"
               class="flex items-center justify-center gap-2 w-full bg-red-600 hover:bg-red-700 text-white font-black py-4 rounded-2xl transition-all shadow-lg shadow-red-600/25 hover:scale-[1.02] active:scale-[0.98]">
                <i class="fas fa-sign-in-alt text-sm"></i>
                Login Now
            </a>
            <a href="<?php echo BASE_PATH; ?>student_registration"
               class="flex items-center justify-center gap-2 w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-4 rounded-2xl transition-all hover:scale-[1.02] active:scale-[0.98]">
                <i class="fas fa-user-plus text-sm"></i>
                Create Account
            </a>
            <a href="<?php echo BASE_PATH; ?>"
               class="block text-slate-400 hover:text-slate-600 font-bold text-xs uppercase tracking-widest pt-2 transition-colors">
                ← Back to Home
            </a>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// User is logged in — serve the full page
require_once __DIR__ . '/dashboard/recordings.php';
