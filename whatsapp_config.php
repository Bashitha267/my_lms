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
 * Send WhatsApp message via API
 */
if (!function_exists('sendWhatsAppMessage')) {
    function sendWhatsAppMessage($mobile, $message)
    {
        error_log("Attempting to send WhatsApp message to: " . $mobile);
        
        // Append global footer
        $message .= "\n\n| Learner.LK 🇱🇰\n| Best Place to Your Online Learning";
        
        
        if (!WHATSAPP_ENABLED) {
            error_log("WhatsApp disabled in config");
            return ['success' => false, 'message' => 'WhatsApp API is disabled'];
        }
        
        if (empty(WHATSAPP_API_URL)) {
            error_log("WhatsApp API URL is empty");
            return ['success' => false, 'message' => 'WhatsApp API URL not configured'];
        }

        if (empty($mobile)) {
            error_log("Recipient mobile number is empty");
            return ['success' => false, 'message' => 'Recipient mobile number is required'];
        }

        // Get configuration

        $whatsapp_api_url = WHATSAPP_API_URL;
        $email = WHATSAPP_API_EMAIL;
        $api_key = WHATSAPP_API_KEY;

        // Format mobile number for WhatsApp
        $chatId = formatWhatsAppNumber($mobile);

        // Prepare JSON data
        // Prepare data for HostGrap API V2
        $data = [
            'email' => $email,
            'api_key' => $api_key,
            'phone' => $chatId, // V2 uses 'phone'
            'message' => $message
        ];
        
        $json_data = json_encode($data);
        error_log("WhatsApp API Payload: " . $json_data);

        // Initialize cURL
        $ch = curl_init($whatsapp_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // HostGrap usually expects form-data
        $post_fields = http_build_query($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Safety for local development/XAMPP
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Debug logging
        error_log("WhatsApp API Response: " . $response);
        error_log("WhatsApp API HTTP Code: " . $http_code);
        if ($curl_error) error_log("WhatsApp API CURL Error: " . $curl_error);


        // Check for cURL errors
        if ($curl_error) {
            return ['success' => false, 'message' => 'Failed to connect to WhatsApp API: ' . $curl_error];
        }

        // Check HTTP status code
        if ($http_code !== 200) {
            return ['success' => false, 'message' => 'WhatsApp API returned error code: ' . $http_code];
        }

        // Try to decode response
        $response_data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid response from WhatsApp API'];
        }

        // Check response data - API returns status field
        if (isset($response_data['status']) && ($response_data['status'] === 'success' || $response_data['status'] === 200)) {
            $message = isset($response_data['message']) ? $response_data['message'] : 'Message sent successfully';
            return ['success' => true, 'message' => $message, 'raw' => $response];
        } else {
            return [
                'success' => false, 
                'message' => 'Unable to send the message: ' . ($response_data['message'] ?? 'Unknown error'),
                'raw' => $response,
                'http_code' => $http_code
            ];
        }
    }
}

/**
 * Notify a single student when they start watching a recording or join a live class
 */
function notifyStudentWatching($conn, $user_id, $recording_title, $remaining_watches = null) {
    if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED) return;

    $query = "SELECT whatsapp_number, first_name FROM users WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $phone = $row['whatsapp_number'];
        $name = $row['first_name'];
        
        if (empty($phone)) return;

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

        $msg .= "\nThank you for learning with us!\n" .
                "*Team Learner.LK*";

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
        if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED) return;
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



