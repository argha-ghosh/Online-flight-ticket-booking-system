<?php
/**
 * google_oauth.example.php — Google OAuth 2.0 Configuration Template
 *
 * SETUP:
 *   1. Copy this file to config/secrets.php
 *   2. Fill in your real credentials (never commit secrets.php)
 *
 * How to get credentials:
 *   - Go to https://console.cloud.google.com
 *   - APIs & Services → Credentials → Create OAuth Client ID
 *   - Application type: Web application
 *   - Authorized redirect URI: http://localhost/flight_booking/view/google_callback.php
 */

// ── Google OAuth credentials ──────────────────────────────────
define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'http://localhost/flight_booking/view/google_callback.php');

// ── Gmail SMTP credentials ────────────────────────────────────
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your_gmail@gmail.com');
define('MAIL_PASS',     'your_16_char_app_password');
define('MAIL_FROM',     'your_gmail@gmail.com');
define('MAIL_FROM_NAME','GoZayan');

// ── Database credentials ──────────────────────────────────────
// Local XAMPP (leave blank password as default)
define('DB_HOST_LOCAL',     'localhost');
define('DB_USER_LOCAL',     'root');
define('DB_PASS_LOCAL',     '');
define('DB_NAME_LOCAL',     'if0_42063720_flight_booking');

// Production (InfinityFree or your host)
define('DB_HOST_PROD',  'sql107.infinityfree.com');
define('DB_USER_PROD',  'YOUR_DB_USERNAME');
define('DB_PASS_PROD',  'YOUR_DB_PASSWORD');
define('DB_NAME_PROD',  'YOUR_DB_NAME');
