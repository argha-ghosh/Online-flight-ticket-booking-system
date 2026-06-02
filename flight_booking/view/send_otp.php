<?php
/**
 * send_otp.php — Simulated OTP sender
 * Generates a 6-digit OTP, stores in session, returns it as JSON (demo mode).
 * In production: replace with real SMS API (SSL Wireless, Twilio, etc.)
 */
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$mobile = preg_replace('/\D/', '', $_POST['mobile'] ?? '');

if (strlen($mobile) !== 11 || !str_starts_with($mobile, '01')) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid 11-digit Bangladeshi mobile number.']);
    exit;
}

// Rate limiting — max 3 OTPs per 5 minutes per mobile
$rate_key = 'otp_count_' . $mobile;
$rate_time = 'otp_rate_time_' . $mobile;

if (isset($_SESSION[$rate_time]) && (time() - $_SESSION[$rate_time]) < 300) {
    $count = $_SESSION[$rate_key] ?? 0;
    if ($count >= 3) {
        $wait = 300 - (time() - $_SESSION[$rate_time]);
        echo json_encode(['success' => false, 'message' => "Too many attempts. Wait {$wait}s before retrying."]);
        exit;
    }
    $_SESSION[$rate_key] = $count + 1;
} else {
    $_SESSION[$rate_key]  = 1;
    $_SESSION[$rate_time] = time();
}

// Generate 6-digit OTP
$otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

// Store in session
$_SESSION['otp_code']     = $otp;
$_SESSION['otp_mobile']   = $mobile;
$_SESSION['otp_time']     = time();
$_SESSION['otp_verified'] = false;

// In demo mode we return the OTP directly so the UI can show the toast.
// In production: send SMS here, never return otp in response.
echo json_encode([
    'success' => true,
    'otp'     => $otp,   // DEMO ONLY — remove in production
    'mobile'  => substr($mobile, 0, 4) . '***' . substr($mobile, -4),
    'expires' => 120,    // seconds
]);
