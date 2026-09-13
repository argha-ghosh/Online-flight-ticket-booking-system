<?php
/**
 * google_oauth.php — Google OAuth 2.0 Configuration
 *
 * Credentials are loaded from config/secrets.php (gitignored).
 * Copy config/google_oauth.example.php → config/secrets.php and fill in your values.
 */

// Load secrets (contains GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI)
$_secretsFile = __DIR__ . '/secrets.php';
if (!file_exists($_secretsFile)) {
    die(
        '<b>Configuration missing.</b> ' .
        'Copy <code>config/google_oauth.example.php</code> to ' .
        '<code>config/secrets.php</code> and fill in your credentials.'
    );
}
require_once $_secretsFile;
unset($_secretsFile);

// ── Google OAuth endpoints (public, not secret) ───────────────
define('GOOGLE_AUTH_URL',     'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL',    'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v3/userinfo');

/**
 * Build the Google OAuth authorization URL with a CSRF state token.
 */
function getGoogleAuthUrl(string $returnTo = ''): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state']     = $state;
    $_SESSION['google_oauth_return_to'] = $returnTo;

    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
        'state'         => $state,
    ]);

    return GOOGLE_AUTH_URL . '?' . $params;
}
