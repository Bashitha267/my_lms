<?php
header('Content-Type: application/json');

session_start();

$otp_code = isset($_POST['otp_code']) ? trim($_POST['otp_code']) : '';

if (empty($otp_code)) {
    echo json_encode(['success' => false, 'message' => 'OTP code is required']);
    exit;
}

// Check if OTP exists in session
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_expires'])) {
    echo json_encode(['success' => false, 'message' => 'OTP session expired. Please request a new OTP.']);
    exit;
}

// Check if OTP has expired
if (time() > $_SESSION['otp_expires']) {
    unset($_SESSION['otp_code']);
    unset($_SESSION['otp_expires']);
    unset($_SESSION['otp_mobile']);
    echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new OTP.']);
    exit;
}

// Verify OTP
if ($_SESSION['otp_code'] === $otp_code) {
    // OTP verified successfully
    $mobile = $_SESSION['otp_mobile'];
    unset($_SESSION['otp_code']);
    unset($_SESSION['otp_expires']);
    unset($_SESSION['otp_mobile']);
    
    echo json_encode([
        'success' => true,
        'verified' => true,
        'message' => 'OTP verified successfully',
        'mobile_number' => $mobile
    ]);
} else {
    echo json_encode([
        'success' => false,
        'verified' => false,
        'message' => 'Invalid OTP code. Please try again.'
    ]);
}
?>















