<?php
/**
 * OTP Handler API
 * Handles OTP send and verify actions via AJAX.
 * 
 * Endpoints:
 *   POST ?action=send   — Generate & store OTP, return success
 *   POST ?action=verify — Verify OTP against stored record
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../model/db_conn.php";

// Only logged-in webusers can use OTP
if (!isset($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'webuser') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login first.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ─────────────────────────────────────────────
    // SEND OTP
    // ─────────────────────────────────────────────
    case 'send':
        $mobile = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        
        // Validate mobile number (Bangladesh: 11 digits, starts with 0)
        if (strlen($mobile) !== 11 || !str_starts_with($mobile, '0')) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid 11-digit mobile number starting with 0.']);
            exit;
        }

        // Rate limiting: max 5 OTPs per mobile per 10 minutes
        $rate_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM otp_codes WHERE mobile = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $rate_stmt->bind_param("s", $mobile);
        $rate_stmt->execute();
        $rate_result = $rate_stmt->get_result()->fetch_assoc();
        $rate_stmt->close();

        if ($rate_result['cnt'] >= 5) {
            echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please wait a few minutes.']);
            exit;
        }

        // Invalidate any existing unused OTPs for this mobile
        $cleanup = $conn->prepare("DELETE FROM otp_codes WHERE mobile = ? AND is_verified = 0");
        $cleanup->bind_param("s", $mobile);
        $cleanup->execute();
        $cleanup->close();

        // Generate 6-digit OTP
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Expiry: 2 minutes from now
        $expires_at = date('Y-m-d H:i:s', time() + 120);
        $purpose = 'payment';

        // Store in database
        $stmt = $conn->prepare("INSERT INTO otp_codes (mobile, otp_code, purpose, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $mobile, $otp, $purpose, $expires_at);

        if ($stmt->execute()) {
            $stmt->close();

            // ──────────────────────────────────────────
            // SIMULATED MODE: Return OTP in response
            // In production, replace this with real SMS API call:
            //   - Twilio: $twilio->messages->create('+88'.$mobile, ['body' => "Your GoZayan OTP: $otp"])
            //   - BulkSMS BD: file_get_contents("https://api.sslwireless.com/...&sms=Your+OTP:+$otp")
            // ──────────────────────────────────────────

            echo json_encode([
                'success'    => true,
                'message'    => 'OTP sent successfully!',
                'simulated'  => true,           // Remove in production
                'otp_code'   => $otp,            // Remove in production — only for demo
                'expires_in' => 120              // seconds
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to generate OTP. Please try again.']);
        }
        break;

    // ─────────────────────────────────────────────
    // VERIFY OTP
    // ─────────────────────────────────────────────
    case 'verify':
        $mobile = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        $otp_input = trim($_POST['otp'] ?? '');

        if (strlen($mobile) !== 11 || strlen($otp_input) !== 6) {
            echo json_encode(['success' => false, 'message' => 'Invalid mobile number or OTP format.']);
            exit;
        }

        // Find the latest unexpired, unverified OTP for this mobile
        $stmt = $conn->prepare("
            SELECT id, otp_code, attempts, max_attempts, expires_at
            FROM otp_codes 
            WHERE mobile = ? 
              AND purpose = 'payment' 
              AND is_verified = 0 
              AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("s", $mobile);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'OTP expired or not found. Please request a new OTP.', 'expired' => true]);
            exit;
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        // Check max attempts
        if ($row['attempts'] >= $row['max_attempts']) {
            // Invalidate this OTP
            $del = $conn->prepare("DELETE FROM otp_codes WHERE id = ?");
            $del->bind_param("i", $row['id']);
            $del->execute();
            $del->close();

            echo json_encode(['success' => false, 'message' => 'Maximum attempts exceeded. Please request a new OTP.', 'max_reached' => true]);
            exit;
        }

        // Increment attempt count
        $upd = $conn->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
        $upd->bind_param("i", $row['id']);
        $upd->execute();
        $upd->close();

        // Verify OTP
        if ($otp_input === $row['otp_code']) {
            // Mark as verified
            $verify = $conn->prepare("UPDATE otp_codes SET is_verified = 1 WHERE id = ?");
            $verify->bind_param("i", $row['id']);
            $verify->execute();
            $verify->close();

            // Store verification in session
            $_SESSION['otp_verified'] = true;
            $_SESSION['otp_mobile']   = $mobile;
            $_SESSION['otp_time']     = time();

            echo json_encode([
                'success'  => true,
                'verified' => true,
                'message'  => 'OTP verified successfully! ✅'
            ]);
        } else {
            $remaining = $row['max_attempts'] - ($row['attempts'] + 1);
            echo json_encode([
                'success'   => false,
                'message'   => "Incorrect OTP. $remaining attempt(s) remaining.",
                'remaining' => $remaining
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}

$conn->close();
?>
