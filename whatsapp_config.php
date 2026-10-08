<?php
// WhatsApp API Configuration - HostGrap API V2
// API Domain: wa-api.hostgrap.com
// API Key: 9b0fb2ec77aa05750171504359fe6a99fb2ebf8f12df5c243a37fc2cf767c81b

if (!defined('WHATSAPP_API_URL')) {
    define('WHATSAPP_API_URL', 'https://wa-api.hostgrap.com/api/send-message.php');
}


if (!defined('WHATSAPP_API_EMAIL')) {
    define('WHATSAPP_API_EMAIL', 'apemediapanthiyaofficial@gmail.com');
}


if (!defined('WHATSAPP_API_KEY')) {
    define('WHATSAPP_API_KEY', '9b0fb2ec77aa05750171504359fe6a99fb2ebf8f12df5c243a37fc2cf767c81b');
}

// Flag to enable/disable WhatsApp functionality
if (!defined('WHATSAPP_ENABLED')) {
    define('WHATSAPP_ENABLED', true);
}

// Admin WhatsApp Number for notifications
if (!defined('ADMIN_WHATSAPP')) {
    define('ADMIN_WHATSAPP', '0768368202');
}


/**
 * Format mobile number to WhatsApp format (e.g., 0771234567 -> 94771234567@c.us)
 */
if (!function_exists('formatWhatsAppNumber')) {
    function formatWhatsAppNumber($mobile)
    {
        // Remove all non-numeric characters
        $mobile = preg_replace('/\D/', '', $mobile);

        // Remove leading 0 if present
        if (substr($mobile, 0, 1) === '0') {
            $mobile = substr($mobile, 1);
        }

        // Add country code 94 if not present
        if (substr($mobile, 0, 2) !== '94') {
            $mobile = '94' . $mobile;
        }

        // Return only the number (removed @c.us for better compatibility)
        return $mobile;
    }
}

/**
 * Helper: perform a cURL POST and return [response, http_code, error]
 */
if (!function_exists('_wa_curl_post')) {
    function _wa_curl_post($url, $post_fields, $use_ssl = true) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $use_ssl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $use_ssl ? 2 : 0);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        // Follow HTTP → HTTPS redirects
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);
        return [$response, $http_code, $error];
    }
}

/**
 * Send WhatsApp message via API.
 *
 * Tries three methods in order to work around VPS outbound firewall rules
 * that may block port 443 (HTTPS):
 *
 *   Method 1 — HTTP (port 80):  always open since the web server runs on it.
 *               CURLOPT_FOLLOWLOCATION will follow any 301→HTTPS redirect.
 *   Method 2 — exec() shell curl: runs outside PHP-FPM network namespace
 *               on some VPS setups, may bypass iptables OUTPUT rules.
 *   Method 3 — HTTPS (port 443): original attempt, works when port is open.
 */
// Global WhatsApp Message Footer
if (!defined('WHATSAPP_FOOTER')) {
    define('WHATSAPP_FOOTER', "\n\n-------------------------------------\nThank you,\n| Lernerr.LK 🇱🇰\n| Best Place to Your Online Learning");
}

if (!function_exists('sendWhatsAppMessage')) {
    function sendWhatsAppMessage($mobile, $message)
    {
        // Append global footer if not already present
        if (strpos($message, '| Lernerr.LK') === false) {
            $message .= WHATSAPP_FOOTER;
        }

        if (!WHATSAPP_ENABLED) {
            return ['success' => false, 'message' => 'WhatsApp API is disabled'];
        }
        if (empty(WHATSAPP_API_URL) || empty($mobile)) {
            return ['success' => false, 'message' => 'Missing API URL or phone number'];
        }

        $email       = WHATSAPP_API_EMAIL;
        $api_key     = WHATSAPP_API_KEY;
        $chatId      = formatWhatsAppNumber($mobile);
        $https_url   = WHATSAPP_API_URL;

        $data = [
            'email'   => $email,
            'api_key' => $api_key,
            'phone'   => $chatId,
            'message' => $message,
        ];
        $post_fields = http_build_query($data);

        // ── Method 1: Direct HTTPS cURL ───────────────────────────────────
        $ch = curl_init($https_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if (!$curl_err && $http_code === 200 && !empty($response)) {
            $result = _wa_parse_response($response);
            error_log("WhatsApp sent via HTTPS cURL to $chatId: " . $response);
            return $result;
        }

        // ── Method 2: System curl CLI with -k (bypasses local SSL issues) ──
        if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            $curl_bin = (stripos(PHP_OS, 'WIN') === 0) ? 'curl.exe' : 'curl';
            $tmp_post_file = str_replace('\\', '/', sys_get_temp_dir()) . '/wa_post_' . time() . '_' . rand(1000, 9999) . '.txt';
            file_put_contents($tmp_post_file, $post_fields);

            $cmd = $curl_bin . ' -k -s --max-time 15 --connect-timeout 6 -X POST "' . $https_url . '" --data @"' . $tmp_post_file . '"';

            $output = [];
            $ret_code = 0;
            @exec($cmd, $output, $ret_code);
            @unlink($tmp_post_file);
            $shell_response = implode("\n", $output);

            if (!empty($shell_response)) {
                $result = _wa_parse_response($shell_response);
                error_log("WhatsApp sent via system curl to $chatId: " . $shell_response);
                return $result;
            }
        }

        if ($curl_err) {
            error_log("WhatsApp cURL error: " . $curl_err);
            return ['success' => false, 'message' => 'Delivery failed: ' . $curl_err];
        }

        return _wa_parse_response($response ?? '');
    }
}

/**
 * Parse the HostGrap API JSON response into a standard result array.
 */
if (!function_exists('_wa_parse_response')) {
    function _wa_parse_response($response) {
        if (empty($response)) {
            return ['success' => false, 'message' => 'Empty response from API'];
        }
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid JSON from API: ' . substr($response, 0, 100)];
        }
        $status = $data['status'] ?? null;
        if ($status === 'success' || $status === 200 || $status === '200') {
            return ['success' => true,  'message' => $data['message'] ?? 'Sent', 'raw' => $response];
        }
        return ['success' => false, 'message' => $data['message'] ?? 'API rejected message', 'raw' => $response];
    }
}


/**
 * Send WhatsApp Media message (Image/File)
 */
if (!function_exists('sendWhatsAppMedia')) {
    function sendWhatsAppMedia($mobile, $caption, $media_url)
    {
        error_log("Attempting to send WhatsApp media to: " . $mobile);

        if (!WHATSAPP_ENABLED)
            return ['success' => false, 'message' => 'WhatsApp API is disabled'];

        $email = WHATSAPP_API_EMAIL;
        $api_key = WHATSAPP_API_KEY;
        $chatId = formatWhatsAppNumber($mobile);

        // Based on documentation: send-image.php
        // Parameters: email, api_key, phone, image_url, caption
        $media_api_url = str_replace('send-message.php', 'send-image.php', WHATSAPP_API_URL);

        // Append global footer to caption if not already present
        if (!empty($caption) && strpos($caption, '| Lernerr.LK') === false) {
            $caption .= WHATSAPP_FOOTER;
        }

        $data = [
            'email' => $email,
            'api_key' => $api_key,
            'phone' => $chatId,
            'caption' => $caption,
            'image_url' => $media_url
        ];

        $ch = curl_init($media_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        // Log only on failure
        if ($curl_err) {
            error_log("WhatsApp Media CURL Error: " . $curl_err);
        }

        $response_data = json_decode($response, true);
        if (isset($response_data['status']) && ($response_data['status'] === 'success' || $response_data['status'] === 200)) {
            return ['success' => true, 'message' => 'Media sent successfully'];
        } else {
            return [
                'success' => false,
                'message' => $response_data['message'] ?? 'Failed to send media',
                'raw' => $response,
                'http_code' => $http_code
            ];
        }
    }
}

/**
 * Notify a single student when they start watching a recording or join a live class
 */
function notifyStudentWatching($conn, $user_id, $recording_title, $remaining_watches = null)
{
    if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED)
        return;

    $query = "SELECT whatsapp_number, first_name FROM users WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $phone = $row['whatsapp_number'];
        $name = $row['first_name'];

        if (empty($phone))
            return;

        $msg = "📺 *Session Started / පන්තිය ආරම්භ විය*\n\n" .
            "Hello *{$name}*,\n" .
            "You have started watching: *{$recording_title}*\n";

        if ($remaining_watches !== null) {
            $views_text = ($remaining_watches === -1) ? "Unlimited" : $remaining_watches;
            $msg .= "Views remaining: *{$views_text}*\n";
        }

        $msg .= "\n--------------------------\n\n" .
            "ඔබ *{$recording_title}* පටිගත කිරීම/පන්තිය නැරඹීම ආරම්භ කර ඇත.\n";

        if ($remaining_watches !== null) {
            $views_text_si = ($remaining_watches === -1) ? "සීමාවක් නැත" : $remaining_watches;
            $msg .= "ඉතිරිව ඇති වාර ගණන: *{$views_text_si}*\n";
        }

        return sendWhatsAppMessage($phone, $msg);
    }
    return false;
}

/**
 * Notify all students enrolled in a subject
 */
if (!function_exists('notifyEnrolledStudents')) {
    function notifyEnrolledStudents($conn, $stream_subject_id, $academic_year, $message)
    {
        if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED)
            return;
        error_log("Notifying students for stream_subject_id: $stream_subject_id, year: $academic_year");

        $query = "SELECT u.whatsapp_number, u.first_name 
                  FROM student_enrollment se
                  INNER JOIN users u ON se.student_id = u.user_id
                  WHERE se.stream_subject_id = ? AND se.academic_year = ? AND se.status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $stream_subject_id, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['whatsapp_number'])) {
                sendWhatsAppMessage($row['whatsapp_number'], $message);
                $count++;
            }
        }
        $stmt->close();
        return $count;
    }
}

?>