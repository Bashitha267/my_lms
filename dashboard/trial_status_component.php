<?php
/**
 * trial_status_component.php
 * Displays the student's trial quota status in a beautiful card.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/trial_functions.php';

$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if ($role !== 'student' || empty($user_id)) {
    return; // Don't show for teachers or non-logged users
}

$recording_trial_count = getRecordingTrialCount($conn, $user_id);
$live_trial_count = getLiveClassTrialCount($conn, $user_id);

$recording_left = max(0, 2 - $recording_trial_count);
$live_left = max(0, 2 - $live_trial_count);

$recording_percent = min(100, ($recording_trial_count / 2) * 100);
$live_percent = min(100, ($live_trial_count / 2) * 100);
?>

<div class="glass-card rounded-3xl p-6 mb-8 border border-white/20 shadow-2xl bg-white/10 backdrop-blur-md">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-900">Free Trial Credits</h3>
            <p class="text-sm text-gray-500">You can watch limited content for free before payment</p>
        </div>
        <div class="bg-blue-100 text-blue-700 px-4 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
            Active Trial
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Recordings Trial -->
        <div class="bg-white/50 rounded-2xl p-4 border border-white/30">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="font-bold text-gray-800">Recordings</span>
                </div>
                <span class="text-sm font-bold <?php echo $recording_left > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo $recording_left; ?> of 2 Left
                </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="bg-red-600 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo $recording_percent; ?>%"></div>
            </div>
            <p class="text-[10px] text-gray-400 mt-2">Unique recording videos watched</p>
        </div>

        <!-- Live Class Trial -->
        <div class="bg-white/50 rounded-2xl p-4 border border-white/30">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span class="font-bold text-gray-800">Live Classes</span>
                </div>
                <span class="text-sm font-bold <?php echo $live_left > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo $live_left; ?> of 2 Left
                </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo $live_percent; ?>%"></div>
            </div>
            <p class="text-[10px] text-gray-400 mt-2">Unique live sessions (Zoom/YouTube) joined</p>
        </div>
    </div>
    
    <?php if ($recording_left === 0 || $live_left === 0): ?>
    <div class="mt-6 flex items-center p-3 bg-yellow-50 rounded-xl border border-yellow-100">
        <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span class="text-xs text-yellow-700">
            Some content may now require payment. Please complete your monthly payment to get unlimited access.
        </span>
    </div>
    <?php endif; ?>
</div>
