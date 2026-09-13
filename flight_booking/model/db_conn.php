<?php
/**
 * db_conn.php — Database connection for GoZayan
 *
 * Credentials are loaded from config/secrets.php (gitignored).
 * Copy config/google_oauth.example.php → config/secrets.php and fill in your values.
 */

// Load secrets if not already loaded
if (!defined('DB_HOST_LOCAL')) {
    $__sf = __DIR__ . '/../config/secrets.php';
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

$host = $_SERVER['HTTP_HOST'] ?? '';

if ($host === 'localhost' || $host === '127.0.0.1') {
    // ── LOCAL (XAMPP) ──────────────────────────────────────────
    $servername  = DB_HOST_LOCAL;
    $username    = DB_USER_LOCAL;
    $db_password = DB_PASS_LOCAL;
    $dbname      = DB_NAME_LOCAL;
} else {
    // ── PRODUCTION ─────────────────────────────────────────────
    $servername  = DB_HOST_PROD;
    $username    = DB_USER_PROD;
    $db_password = DB_PASS_PROD;
    $dbname      = DB_NAME_PROD;
}

$conn = new mysqli($servername, $username, $db_password, $dbname);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
