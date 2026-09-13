<?php
/**
 * google_callback.php — Google OAuth 2.0 Callback Handler
 *
 * Behaviour depends on where the user came from:
 *
 *   came_from = 'register'
 *       → New email   : create account, then log in
 *       → Known email : error — "already registered, please log in"
 *
 *   came_from = 'login' (or anything else)
 *       → Known email  : log in
 *       → Unknown email: error — "no account found, please register first"
 */
session_start();

require_once __DIR__ . '/../config/base_url.php';
require_once __DIR__ . '/../config/google_oauth.php';
require_once __DIR__ . '/../model/db_conn.php';

// ── Helpers ───────────────────────────────────────────────────
function failTo(string $page, string $msg): never
{
    $_SESSION['google_error'] = $msg;
    header('Location: ' . BASE_URL . '/view/' . $page);
    exit;
}

// ── 1. OAuth error from Google ────────────────────────────────
if (isset($_GET['error'])) {
    failTo('login.php', 'Google sign-in was cancelled or denied.');
}

$code      = trim($_GET['code']  ?? '');
$state     = trim($_GET['state'] ?? '');

if (empty($code)) {
    failTo('login.php', 'No authorization code received from Google.');
}

// ── 2. Verify CSRF state ──────────────────────────────────────
$storedState = $_SESSION['google_oauth_state'] ?? '';
$cameFrom    = $_SESSION['google_oauth_return_to'] ?? 'login';   // 'register' or 'login'

unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_return_to']);

if (empty($storedState) || !hash_equals($storedState, $state)) {
    failTo('login.php', 'Security check failed. Please try again.');
}

// ── 3. Exchange code → access token ──────────────────────────
$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'content' => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        'ignore_errors' => true,
    ],
]);

$tokenRaw = file_get_contents(GOOGLE_TOKEN_URL, false, $ctx);
$tokenData = json_decode($tokenRaw ?: '{}', true);

if (empty($tokenData['access_token'])) {
    $errDetail = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'unknown');
    error_log('Google OAuth token error: ' . $errDetail);
    failTo('login.php', 'Failed to get access token from Google. Please try again.');
}

$accessToken = $tokenData['access_token'];

// ── 4. Fetch Google user profile ──────────────────────────────
$infoCtx  = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'header'        => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
        'ignore_errors' => true,
    ],
]);

$infoRaw  = file_get_contents(GOOGLE_USERINFO_URL, false, $infoCtx);
$userInfo = json_decode($infoRaw ?: '{}', true);

if (empty($userInfo['email'])) {
    failTo('login.php', 'Could not retrieve your email from Google.');
}

$googleId    = $userInfo['sub']            ?? '';
$googleEmail = $userInfo['email']          ?? '';
$googleName  = $userInfo['name']           ?? 'Google User';
$emailVerified = (bool)($userInfo['email_verified'] ?? false);

if (!$emailVerified) {
    failTo('login.php', 'Your Google account email is not verified.');
}

// ── 5. Look up existing account ───────────────────────────────
$stmt = $conn->prepare('SELECT id, name, email FROM webusers WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $googleEmail);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isExisting = !empty($existing);

// ── 6. Route logic ────────────────────────────────────────────
if ($cameFrom === 'register') {
    // ── Came from Register page ───────────────────────────────

    if ($isExisting) {
        // Account already exists — tell user to log in instead
        failTo('register.php', 'An account with this Google email already exists. Please log in instead.');
    }

    // New user — create account
    $randomPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $imgVal     = '';

    $ins1 = $conn->prepare(
        'INSERT INTO webusers (name, email, pass, cpass, image, google_id, auth_provider)
         VALUES (?, ?, ?, ?, ?, ?, "google")'
    );
    $ins1->bind_param('ssssss', $googleName, $googleEmail, $randomPass, $randomPass, $imgVal, $googleId);

    $ins2 = $conn->prepare("INSERT INTO login (email, password, role) VALUES (?, ?, 'webuser')");
    $ins2->bind_param('ss', $googleEmail, $randomPass);

    if (!$ins1->execute() || !$ins2->execute()) {
        error_log('Google register insert error: ' . $conn->error);
        $ins1->close();
        $ins2->close();
        failTo('register.php', 'Account creation failed. Please try again.');
    }
    $ins1->close();
    $ins2->close();

} else {
    // ── Came from Login page ──────────────────────────────────

    if (!$isExisting) {
        // No account found — must register first
        failTo('login.php', 'No account found for this Google email. Please register first.');
    }

    // Existing user — update google_id if not already linked
    if (empty($existing['google_id'])) {
        $upd = $conn->prepare(
            'UPDATE webusers SET google_id = ?, auth_provider = "google" WHERE email = ?'
        );
        $upd->bind_param('ss', $googleId, $googleEmail);
        $upd->execute();
        $upd->close();
    }
}

// ── 7. Set session → log in ───────────────────────────────────
$_SESSION['email']        = $googleEmail;
$_SESSION['role']         = 'webuser';
$_SESSION['google_login'] = true;

// Clean up any leftover OTP state
unset($_SESSION['reg_otp_verified'], $_SESSION['reg_otp_email']);

header('Location: ' . BASE_URL . '/view/searchflights.php');
exit;
