<?php
header('Content-Type: application/json');

require_once 'config.php';

// Simulate OTP sending (for now)
$mobile_number = isset($_POST['mobile_number']) ? trim($_POST['mobile_number']) : '';

if (empty($mobile_number)) {
    echo json_encode(['success' => false, 'message' => 'Mobile number is required']);
    exit;
}

// Generate 6-digit OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

// Store OTP in session for verification
session_start();
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_mobile'] = $mobile_number;
$_SESSION['otp_expires'] = time() + 300; // OTP expires in 5 minutes

// In a real implementation, you would send this OTP via SMS/WhatsApp API
// For now, we'll just return success (in development, you might want to return the OTP for testing)
echo json_encode([
    'success' => true,
    'message' => 'OTP sent successfully',
    // Remove this in production - only for testing
    'otp' => $otp // TODO: Remove this in production
]);
?>















