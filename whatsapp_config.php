<?php
// WhatsApp API Configuration - HostGrap API
// API Domain: C147C3764B
// API Key: C147C3764B

if (!defined('WHATSAPP_API_URL')) {
    define('WHATSAPP_API_URL', 'https://wa.hglk.link/api/send_message.php');
}


if (!defined('WHATSAPP_API_EMAIL')) {
    define('WHATSAPP_API_EMAIL', 'omalbasnayake@gmail.com');
}


if (!defined('WHATSAPP_API_KEY')) {
    define('WHATSAPP_API_KEY', 'C147C3764B');
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

        // Add WhatsApp suffix
        return $mobile . '@c.us';
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
        $message .= "\n\n| LernerrLK 🇱🇰\n| Best Place to Your Online Learning";
        
        
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
        $data = [
            'email' => $email,
            'api_key' => $api_key,
            'chatId' => $chatId,
            'text' => $message
        ];
        
        $json_data = json_encode($data);
        error_log("WhatsApp API Payload: " . $json_data);

        // Initialize cURL

        $ch = curl_init($whatsapp_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
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
        if (isset($response_data['status']) && $response_data['status'] === 'success') {
            $message = isset($response_data['message']) ? $response_data['message'] : 'Message sent successfully';
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => 'Unable to send the message: ' . ($response_data['message'] ?? 'Unknown error')];
        }
    }
}

/**
 * Notify all students enrolled in a subject
 */
if (!function_exists('notifyEnrolledStudents')) {
    function notifyEnrolledStudents($conn, $stream_subject_id, $academic_year, $message)
    {
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



