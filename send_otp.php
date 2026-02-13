<?php
header('Content-Type: application/json');

require_once 'config.php';

// Include WhatsApp config for the sendWhatsAppMessage function
if (file_exists('whatsapp_config.php')) {
    require_once 'whatsapp_config.php';
}

$mobile_number = isset($_POST['mobile_number']) ? trim($_POST['mobile_number']) : '';

if (empty($mobile_number)) {
    echo json_encode(['success' => false, 'message' => 'Mobile number is required']);
    exit;
}

// Generate 6-digit OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

// Store OTP in session for verification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_mobile'] = $mobile_number;
$_SESSION['otp_expires'] = time() + 300; // OTP expires in 5 minutes

// Prepare WhatsApp message (Bilingual)
$message = "🔐 *LearnerX Verification Code / සත්‍යාපන කේතය*\n\n" .
           "Your verification code is: *{$otp}*\n" .
           "This code will expire in 5 minutes.\n\n" .
           "--------------------------\n\n" .
           "ඔබගේ සත්‍යාපන කේතය: *{$otp}*\n" .
           "මෙම කේතය විනාඩි 5 කින් අවලංගු වේ.\n\n" .
           "Thank you, LearnerX Team";


$whatsapp_sent = false;
$whatsapp_error = '';

// Send OTP via WhatsApp API if enabled
if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && function_exists('sendWhatsAppMessage')) {
    $result = sendWhatsAppMessage($mobile_number, $message);
    if ($result['success']) {
        $whatsapp_sent = true;
    } else {
        $whatsapp_error = $result['message'];
    }
}

// Return response
if ($whatsapp_sent) {
    echo json_encode([
        'success' => true,
        'message' => 'OTP sent successfully via WhatsApp',
        'otp' => $otp // Still returning for development/testing, should be removed for production
    ]);
} else {
    // If WhatsApp fails, we still return the OTP for now so the user can continue (fallback)
    // In production, you might want to fail or use SMS fallback
    echo json_encode([
        'success' => true, 
        'message' => 'OTP generated (WhatsApp failed: ' . $whatsapp_error . ')',
        'otp' => $otp,
        'whatsapp_error' => $whatsapp_error
    ]);
}
?>
















