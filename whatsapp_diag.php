<?php
/**
 * WhatsApp Diagnostic Tool
 * ========================
 * Run this file in browser: https://yourdomain.com/lms/whatsapp_diag.php
 * DELETE THIS FILE after diagnosis is done!
 */

// Basic security — only allow from localhost or with a secret key
$secret = $_GET['key'] ?? '';
if ($secret !== 'diag_lms_2024') {
    die('Access denied. Use ?key=diag_lms_2024');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/whatsapp_config.php';

$test_number = $_GET['phone'] ?? '0768368202'; // default to admin number

echo '<pre style="font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;font-size:13px;">';
echo "=============================================================\n";
echo " LMS WhatsApp Diagnostic\n";
echo " Time: " . date('Y-m-d H:i:s') . "\n";
echo "=============================================================\n\n";

// ── 1. Config Check ─────────────────────────────────────────────────────────
echo "[ 1 ] CONFIG VALUES\n";
echo "--------------------------------------\n";
echo "WHATSAPP_ENABLED : " . (WHATSAPP_ENABLED ? "✅ true" : "❌ false") . "\n";
echo "WHATSAPP_API_URL : " . WHATSAPP_API_URL . "\n";
echo "WHATSAPP_API_EMAIL : " . WHATSAPP_API_EMAIL . "\n";
echo "WHATSAPP_API_KEY : " . substr(WHATSAPP_API_KEY, 0, 10) . "..." . substr(WHATSAPP_API_KEY, -6) . " (" . strlen(WHATSAPP_API_KEY) . " chars)\n";
echo "Test phone : $test_number\n\n";

// ── 2. cURL Check ───────────────────────────────────────────────────────────
echo "[ 2 ] CURL EXTENSION\n";
echo "--------------------------------------\n";
if (function_exists('curl_init')) {
    echo "cURL loaded   : ✅ YES\n";
    $cv = curl_version();
    echo "cURL version  : " . $cv['version'] . "\n";
    echo "SSL version   : " . $cv['ssl_version'] . "\n";
    echo "SSL cert file : " . (ini_get('curl.cainfo') ?: 'Not set in php.ini (may use system bundle)') . "\n";
} else {
    echo "cURL loaded   : ❌ NO — install php-curl and restart PHP-FPM\n";
}
echo "\n";

// ── 3. DNS Resolution ───────────────────────────────────────────────────────
echo "[ 3 ] DNS RESOLUTION\n";
echo "--------------------------------------\n";
$api_host = parse_url(WHATSAPP_API_URL, PHP_URL_HOST);
$dns_result = gethostbyname($api_host);
if ($dns_result !== $api_host) {
    echo "DNS for $api_host : ✅ Resolved → $dns_result\n";
} else {
    echo "DNS for $api_host : ❌ FAILED — cannot resolve hostname\n";
    echo "   → Your VPS cannot reach the API domain. Check /etc/resolv.conf\n";
}
echo "\n";

// ── 4. Raw cURL Request ─────────────────────────────────────────────────────
echo "[ 4 ] RAW CURL TEST (SSL verify ON)\n";
echo "--------------------------------------\n";
$chatId = formatWhatsAppNumber($test_number);
echo "Formatted phone : $chatId\n";

$data = [
    'email'   => WHATSAPP_API_EMAIL,
    'api_key' => WHATSAPP_API_KEY,
    'phone'   => $chatId,
    'message' => '[TEST] Diagnostic ping from LMS at ' . date('H:i:s'),
];

$ch = curl_init(WHATSAPP_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_VERBOSE, false);

$start = microtime(true);
$response = curl_exec($ch);
$elapsed = round((microtime(true) - $start) * 1000) . "ms";
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "Time taken      : $elapsed\n";
echo "HTTP code       : $http_code\n";
if ($curl_error) {
    echo "cURL error      : ❌ [$curl_errno] $curl_error\n";
    if ($curl_errno === 60 || $curl_errno === 77) {
        echo "\n⚠  SSL CERTIFICATE ERROR — fix options:\n";
        echo "   a) Run: apt-get install ca-certificates && update-ca-certificates\n";
        echo "   b) Or pass SSL cert path to cURL: CURLOPT_CAINFO => '/etc/ssl/certs/ca-certificates.crt'\n";
    }
    if ($curl_errno === 6) {
        echo "\n⚠  DNS RESOLUTION FAILED — VPS cannot reach the API.\n";
        echo "   Check outbound firewall rules or /etc/resolv.conf\n";
    }
    if ($curl_errno === 28) {
        echo "\n⚠  TIMEOUT — API took too long to respond.\n";
        echo "   The API may be down or your VPS outbound port 443 is blocked.\n";
    }
} else {
    echo "cURL error      : ✅ None\n";
}
echo "Raw response    : " . ($response ?: '(empty)') . "\n\n";

// ── 5. SSL fallback test ────────────────────────────────────────────────────
if ($curl_errno === 60 || $curl_errno === 77) {
    echo "[ 5 ] SSL FALLBACK TEST (verify OFF)\n";
    echo "--------------------------------------\n";
    echo "⚠  Only for diagnosis — do not use SSL_VERIFYPEER=false in production!\n";
    $ch2 = curl_init(WHATSAPP_API_URL);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
    $r2 = curl_exec($ch2);
    $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $err2  = curl_error($ch2);
    curl_close($ch2);
    echo "HTTP code   : $code2\n";
    echo "cURL error  : " . ($err2 ?: '✅ None') . "\n";
    echo "Response    : " . ($r2 ?: '(empty)') . "\n";
    if (!$err2 && $code2 === 200) {
        echo "\n✅ Works with SSL off → SSL cert bundle missing on your VPS.\n";
        echo "   Fix: apt-get install ca-certificates && update-ca-certificates\n";
    }
    echo "\n";
} else {
    echo "[ 5 ] SSL FALLBACK TEST : Skipped (not needed)\n\n";
}

// ── 6. Response Analysis ────────────────────────────────────────────────────
echo "[ 6 ] RESPONSE ANALYSIS\n";
echo "--------------------------------------\n";
if ($response) {
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "JSON valid      : ✅ YES\n";
        echo "Decoded data    :\n";
        foreach ($decoded as $k => $v) {
            echo "   $k => " . (is_array($v) ? json_encode($v) : $v) . "\n";
        }
        $status = $decoded['status'] ?? 'missing';
        echo "\nStatus field    : $status\n";
        if ($status === 'success' || $status === 200) {
            echo "Result          : ✅ MESSAGE SENT SUCCESSFULLY\n";
        } else {
            echo "Result          : ❌ API rejected the message\n";
            echo "   Possible reasons:\n";
            echo "   - Wrong api_key\n";
            echo "   - Wrong email\n";
            echo "   - WhatsApp account not connected in HostGrap dashboard\n";
            echo "   - Phone number format issue (formatted to: $chatId)\n";
        }
    } else {
        echo "JSON valid      : ❌ Not JSON — raw response:\n";
        echo "   " . htmlspecialchars($response) . "\n";
        echo "   → The API might be returning an error page (HTML).\n";
        echo "      Check if the API URL is correct.\n";
    }
} else {
    echo "Response        : ❌ Empty — likely a connection/timeout issue\n";
}
echo "\n";

// ── 7. PHP-FPM / fastcgi_finish_request check ───────────────────────────────
echo "[ 7 ] SERVER ENVIRONMENT\n";
echo "--------------------------------------\n";
echo "PHP version            : " . phpversion() . "\n";
echo "SAPI                   : " . php_sapi_name() . "\n";
echo "fastcgi_finish_request : " . (function_exists('fastcgi_finish_request') ? "✅ Available (login will be instant)" : "❌ Not available — login waits for WhatsApp") . "\n";
echo "OPcache                : " . (function_exists('opcache_get_status') && opcache_get_status() ? "✅ Enabled" : "❌ Not active") . "\n";
echo "session.lazy_write     : " . ini_get('session.lazy_write') . "\n";
echo "\n";

echo "=============================================================\n";
echo " ⚠  DELETE THIS FILE after diagnosis: rm whatsapp_diag.php\n";
echo "=============================================================\n";
echo '</pre>';
?>
