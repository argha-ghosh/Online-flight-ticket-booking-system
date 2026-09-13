<?php
/**
 * email_otp.php — Real Email OTP for Registration (PHPMailer + Gmail SMTP)
 *
 * POST ?action=send   — generate OTP, email it to the user
 * POST ?action=verify — verify submitted OTP against session
 */
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

require_once __DIR__ . '/../config/mail_config.php';
require_once __DIR__ . '/../model/db_conn.php';

// PHPMailer autoload
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

// ═══════════════════════════════════════════════════════════
// SEND OTP
// ═══════════════════════════════════════════════════════════
if ($action === 'send') {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    // ── Rate limiting (session-based) ──────────────────────
    $rate_key  = 'email_otp_rate_'      . md5($email);
    $rate_time = 'email_otp_rate_time_' . md5($email);

    if (isset($_SESSION[$rate_time]) && (time() - $_SESSION[$rate_time]) < OTP_RATE_WINDOW) {
        if (($_SESSION[$rate_key] ?? 0) >= OTP_RATE_LIMIT) {
            $wait = OTP_RATE_WINDOW - (time() - $_SESSION[$rate_time]);
            echo json_encode([
                'success' => false,
                'message' => "Too many attempts. Please wait {$wait} seconds."
            ]);
            exit;
        }
        $_SESSION[$rate_key]++;
    } else {
        $_SESSION[$rate_key]  = 1;
        $_SESSION[$rate_time] = time();
    }

    // ── Duplicate email check ──────────────────────────────
    $chk = $conn->prepare("SELECT id FROM webusers WHERE email = ? LIMIT 1");
    $chk->bind_param('s', $email);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        echo json_encode(['success' => false, 'message' => 'This email is already registered. Please log in instead.']);
        exit;
    }
    $chk->close();

    // ── Generate OTP ───────────────────────────────────────
    $otp        = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = time() + OTP_EXPIRY_SECONDS;

    // Store in session
    $_SESSION['reg_otp_code']     = $otp;
    $_SESSION['reg_otp_email']    = $email;
    $_SESSION['reg_otp_expires']  = $expires_at;
    $_SESSION['reg_otp_verified'] = false;
    $_SESSION['reg_otp_attempts'] = 0;

    // ── Send via PHPMailer ─────────────────────────────────
    $mail = new PHPMailer(true);

    try {
        // Server config
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your GoZayan Verification Code: ' . $otp;
        $mail->Body    = buildEmailHTML($otp, $email);
        $mail->AltBody = "Your GoZayan OTP is: {$otp}\n\nThis code expires in 5 minutes.\nDo not share it with anyone.";

        $mail->send();

        echo json_encode([
            'success'    => true,
            'message'    => "OTP sent to {$email}. Please check your inbox (and spam folder).",
            'expires_in' => OTP_EXPIRY_SECONDS,
        ]);

    } catch (MailException $e) {
        // Clear session OTP if mail failed
        unset(
            $_SESSION['reg_otp_code'],
            $_SESSION['reg_otp_email'],
            $_SESSION['reg_otp_expires'],
            $_SESSION['reg_otp_attempts']
        );
        error_log('GoZayan OTP Mail Error: ' . $mail->ErrorInfo);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Please check SMTP settings or try again later.',
            'debug'   => $mail->ErrorInfo  // remove in production
        ]);
    }

    exit;
}

// ═══════════════════════════════════════════════════════════
// VERIFY OTP
// ═══════════════════════════════════════════════════════════
if ($action === 'verify') {

    $entered = trim($_POST['otp']   ?? '');
    $email   = trim($_POST['email'] ?? '');

    if (strlen($entered) !== 6 || !ctype_digit($entered)) {
        echo json_encode(['success' => false, 'message' => 'Please enter the 6-digit OTP.']);
        exit;
    }

    $sess_otp     = $_SESSION['reg_otp_code']    ?? '';
    $sess_email   = $_SESSION['reg_otp_email']   ?? '';
    $sess_expires = $_SESSION['reg_otp_expires'] ?? 0;
    $attempts     = $_SESSION['reg_otp_attempts'] ?? 0;

    if (empty($sess_otp)) {
        echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
        exit;
    }

    if ($email !== $sess_email) {
        echo json_encode(['success' => false, 'message' => 'Email mismatch. Please request a new OTP.']);
        exit;
    }

    if (time() > $sess_expires) {
        unset(
            $_SESSION['reg_otp_code'], $_SESSION['reg_otp_email'],
            $_SESSION['reg_otp_expires'], $_SESSION['reg_otp_attempts']
        );
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.', 'expired' => true]);
        exit;
    }

    if ($attempts >= OTP_MAX_ATTEMPTS) {
        unset(
            $_SESSION['reg_otp_code'], $_SESSION['reg_otp_email'],
            $_SESSION['reg_otp_expires'], $_SESSION['reg_otp_attempts']
        );
        echo json_encode(['success' => false, 'message' => 'Too many wrong attempts. Please request a new OTP.', 'max_reached' => true]);
        exit;
    }

    if ($entered !== $sess_otp) {
        $_SESSION['reg_otp_attempts']++;
        $left = OTP_MAX_ATTEMPTS - $_SESSION['reg_otp_attempts'];
        echo json_encode([
            'success'   => false,
            'message'   => "Incorrect OTP. {$left} attempt(s) remaining.",
            'remaining' => $left,
        ]);
        exit;
    }

    // ✅ Correct
    $_SESSION['reg_otp_verified'] = true;
    $_SESSION['reg_otp_email']    = $email;
    unset($_SESSION['reg_otp_code'], $_SESSION['reg_otp_expires'], $_SESSION['reg_otp_attempts']);

    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully! You can now create your account.',
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);

// ═══════════════════════════════════════════════════════════
// Email HTML template
// ═══════════════════════════════════════════════════════════
function buildEmailHTML(string $otp, string $email): string
{
    $digits = implode('', array_map(
        fn($ch) => "<span style='display:inline-block;width:48px;height:56px;line-height:56px;text-align:center;font-size:1.6rem;font-weight:800;color:#0d1f35;background:#f0f4f9;border:2px solid #dce8f5;border-radius:10px;margin:0 4px;font-family:monospace;'>{$ch}</span>",
        str_split($otp)
    ));

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f9;font-family:'Inter',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f9;padding:40px 20px;">
  <tr><td align="center">
    <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#1a6ff4,#0d4fc4);padding:36px 40px;text-align:center;">
          <div style="font-size:2rem;margin-bottom:8px;">✈</div>
          <div style="font-size:1.6rem;font-weight:800;color:#ffffff;letter-spacing:-0.5px;">GoZayan</div>
          <div style="font-size:0.85rem;color:rgba(255,255,255,0.75);margin-top:4px;">Email Verification</div>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:40px 40px 32px;">
          <p style="font-size:1rem;color:#0d1f35;font-weight:600;margin:0 0 6px;">Hello 👋</p>
          <p style="font-size:0.9rem;color:#3d5a7a;line-height:1.65;margin:0 0 28px;">
            Use the verification code below to complete your GoZayan registration.
            This code is valid for <strong>5 minutes</strong>.
          </p>

          <!-- OTP digits -->
          <div style="text-align:center;margin-bottom:28px;">
            {$digits}
          </div>

          <div style="background:#f8fafd;border:1px solid #dce8f5;border-radius:12px;padding:16px 20px;margin-bottom:24px;">
            <p style="font-size:0.78rem;color:#7a95b0;margin:0;line-height:1.6;">
              🔒 <strong>Security tip:</strong> GoZayan will never ask for this code via phone or chat.
              Do not share it with anyone.
            </p>
          </div>

          <p style="font-size:0.78rem;color:#b0c4d8;margin:0;">
            This code was requested for <strong style="color:#3d5a7a;">{$email}</strong>.
            If you didn't request this, you can safely ignore this email.
          </p>
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#f8fafd;border-top:1px solid #dce8f5;padding:20px 40px;text-align:center;">
          <p style="font-size:0.72rem;color:#b0c4d8;margin:0;">
            &copy; GoZayan &nbsp;·&nbsp; Secure flight booking platform
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
