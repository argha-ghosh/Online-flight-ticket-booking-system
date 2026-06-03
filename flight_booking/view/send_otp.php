<?php
/**
 * send_otp.php — Demo mode OTP (no real SMS)
 */
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$mobile_raw = preg_replace('/\D/', '', $_POST['mobile'] ?? '');

if (strlen($mobile_raw) !== 11 || !str_starts_with($mobile_raw, '01')) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid 11-digit Bangladeshi mobile number.']);
    exit;
}

// Rate limiting — max 3 per 5 minutes
$rate_key  = 'otp_rate_' . $mobile_raw;
$rate_time = 'otp_rate_time_' . $mobile_raw;

if (isset($_SESSION[$rate_time]) && (time() - $_SESSION[$rate_time]) < 300) {
    if (($_SESSION[$rate_key] ?? 0) >= 3) {
        $wait = 300 - (time() - $_SESSION[$rate_time]);
        echo json_encode(['success' => false, 'message' => "Too many attempts. Wait {$wait}s."]);
        exit;
    }
    $_SESSION[$rate_key]++;
} else {
    $_SESSION[$rate_key]  = 1;
    $_SESSION[$rate_time] = time();
}

// Generate 6-digit OTP
$otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

// Store in session
$_SESSION['otp_code']     = $otp;
$_SESSION['otp_mobile']   = $mobile_raw;
$_SESSION['otp_time']     = time();
$_SESSION['otp_verified'] = false;

echo json_encode([
    'success' => true,
    'otp'     => $otp,  // Demo: return OTP in response for toast
    'mobile'  => substr($mobile_raw, 0, 4) . '***' . substr($mobile_raw, -4),
    'expires' => 120,
]);
