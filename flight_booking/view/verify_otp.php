<?php
/**
 * verify_otp.php — Session-based OTP verification (demo mode)
 */
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$otp_entered = trim($_POST['otp'] ?? '');
$mobile_raw  = preg_replace('/\D/', '', $_POST['mobile'] ?? '');

if (strlen($otp_entered) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Enter the 6-digit OTP.']);
    exit;
}

$session_otp    = $_SESSION['otp_code']   ?? '';
$session_mobile = $_SESSION['otp_mobile'] ?? '';
$session_time   = $_SESSION['otp_time']   ?? 0;

if (empty($session_otp)) {
    echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
    exit;
}

if ((time() - $session_time) > 120) {
    unset($_SESSION['otp_code'], $_SESSION['otp_mobile'], $_SESSION['otp_time']);
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
    exit;
}

if ($session_mobile !== $mobile_raw) {
    echo json_encode(['success' => false, 'message' => 'Mobile number mismatch.']);
    exit;
}

if ($otp_entered !== $session_otp) {
    echo json_encode(['success' => false, 'message' => 'Incorrect OTP. Please try again.']);
    exit;
}

// Mark as verified
$_SESSION['otp_verified'] = true;
unset($_SESSION['otp_code']);

echo json_encode(['success' => true, 'message' => 'Mobile number verified successfully!']);
