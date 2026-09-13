<?php
/**
 * mail_config.php — SMTP / Email configuration for GoZayan
 *
 * Credentials are loaded from config/secrets.php (gitignored).
 * Copy config/google_oauth.example.php → config/secrets.php and fill in your values.
 *
 * Gmail App Password setup (one-time):
 *   1. https://myaccount.google.com/security → enable 2-Step Verification
 *   2. https://myaccount.google.com/apppasswords → create App Password
 *   3. Copy the 16-char password (no spaces) into secrets.php as MAIL_PASS
 */

// secrets.php defines MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASS,
// MAIL_FROM, MAIL_FROM_NAME — loaded by google_oauth.php or db_conn.php
// Guard: only load if constants not already defined
if (!defined('MAIL_HOST')) {
    $__sf = __DIR__ . '/secrets.php';
    if (!file_exists($__sf)) {
        die(
            '<b>Configuration missing.</b> ' .
            'Copy <code>config/google_oauth.example.php</code> to ' .
            '<code>config/secrets.php</code> and fill in your credentials.'
        );
    }
    require_once $__sf;
    unset($__sf);
}

// ── OTP settings (not secret, safe to commit) ─────────────────
if (!defined('OTP_EXPIRY_SECONDS')) {
    define('OTP_EXPIRY_SECONDS', 300);  // 5 minutes
    define('OTP_MAX_ATTEMPTS',   5);
    define('OTP_RATE_LIMIT',     3);    // max sends per OTP_RATE_WINDOW
    define('OTP_RATE_WINDOW',    300);  // 5-minute window
}
