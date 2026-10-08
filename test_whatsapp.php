<?php
require_once 'config.php';
require_once 'whatsapp_config.php';

$target_number = isset($_POST['phone']) ? trim($_POST['phone']) : '0768368202';
$test_message = isset($_POST['message']) ? trim($_POST['message']) : "*සාදරයෙන් පිළිගනිමු!*\n\nඔබ Lernerr.LK වෙත සාර්ථකව පිවිසී ඇත.\nඔබගේ අධ්‍යාපන කටයුතු සාර්ථක කරගැනීමට Lernerr.LK වෙතින් උණුසුම් සුබ පැතුම්.\n\n*පිවිසූ වේලාව:* " . date('Y-m-d h:i A');

$result = null;
$debug_info = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['send_test'])) {
    $debug_info['api_url'] = WHATSAPP_API_URL;
    $debug_info['api_email'] = WHATSAPP_API_EMAIL;
    $debug_info['api_key_masked'] = substr(WHATSAPP_API_KEY, 0, 8) . '...' . substr(WHATSAPP_API_KEY, -6);
    $debug_info['formatted_number'] = formatWhatsAppNumber($target_number);
    
    $result = sendWhatsAppMessage($target_number, $test_message);
    $debug_info['result'] = $result;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp API Test - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen p-6 flex flex-col items-center justify-center font-sans">
    <div class="max-w-xl w-full bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
        <div class="flex items-center space-x-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center font-bold text-xl">
                WA
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-800">WhatsApp API Tester</h1>
                <p class="text-xs text-slate-500">Test WhatsApp notifications directly on Lernerr.LK</p>
            </div>
        </div>

        <?php if ($result !== null): ?>
            <div class="mb-6 p-4 rounded-xl text-sm <?= ($result['success'] ?? false) ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
                <div class="font-bold flex items-center mb-1">
                    <?= ($result['success'] ?? false) ? '✓ Message Sent Successfully' : '✗ Sending Failed' ?>
                </div>
                <div class="text-xs font-mono break-all mt-1">
                    <?= htmlspecialchars($result['message'] ?? 'No message returned') ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Phone Number (WhatsApp):</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($target_number) ?>" required
                       class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-green-500 focus:outline-none font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Message Content:</label>
                <textarea name="message" rows="5" required
                          class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-green-500 focus:outline-none font-sans"><?= htmlspecialchars($test_message) ?></textarea>
                <p class="text-[11px] text-slate-400 mt-1">Note: Global footer <code>| Lernerr.LK 🇱🇰</code> will be automatically appended.</p>
            </div>

            <button type="submit" name="send_test" value="1"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-xl shadow-md transition duration-150 ease-in-out">
                Send Test WhatsApp Message
            </button>
        </form>

        <?php if (!empty($debug_info)): ?>
            <div class="mt-6 pt-4 border-t border-slate-200">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Debug Details:</h3>
                <pre class="bg-slate-900 text-slate-200 text-xs p-3 rounded-lg overflow-x-auto font-mono"><?= htmlspecialchars(json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
