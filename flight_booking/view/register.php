<?php
/**
 * register.php — GoZayan User Registration
 * Flow: Email → Send OTP → Verify OTP → Fill details → Create account
 */
session_start();

// Prevent browser from caching this page — OTP state must always be fresh
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

require_once __DIR__ . '/../config/base_url.php';
require_once __DIR__ . '/../config/google_oauth.php';
require_once __DIR__ . '/../model/db_conn.php';

// Pick up any error redirected from google_callback.php
$google_error = '';
if (!empty($_SESSION['google_error'])) {
    $google_error = $_SESSION['google_error'];
    unset($_SESSION['google_error']);
}

// Build Google OAuth URL — pass 'register' so callback knows this is a sign-UP
$googleAuthUrl = getGoogleAuthUrl('register');

$success_message = '';
$error_message   = '';

/* ─────────────────────────────────────────────────────────────
   Handle form submission (Step 2 — after OTP verified)
───────────────────────────────────────────────────────────── */
if (isset($_POST['submit'])) {

    $name             = trim($_POST['name']  ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['pass']       ?? '';
    $confirm_password = $_POST['cpass']      ?? '';

    // Must be OTP-verified in this session
    if (
        empty($_SESSION['reg_otp_verified']) ||
        ($_SESSION['reg_otp_email'] ?? '') !== $email
    ) {
        $error_message = 'Please verify your email with the OTP before registering.';

    } elseif (empty($name)) {
        $error_message = 'Please enter your full name.';

    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters.';

    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';

    } else {
        // Double-check email not already taken
        $chk = $conn->prepare('SELECT id FROM webusers WHERE email = ? LIMIT 1');
        $chk->bind_param('s', $email);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error_message = 'This email is already registered. Please log in.';
            $chk->close();
        } else {
            $chk->close();
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert into webusers (image left empty — no profile pic required)
            $s1 = $conn->prepare('INSERT INTO webusers (name, email, pass, image) VALUES (?, ?, ?, ?)');
            $img = '';
            $s1->bind_param('ssss', $name, $email, $hashed, $img);

            // Insert into login table
            $s2 = $conn->prepare("INSERT INTO login (email, password, role) VALUES (?, ?, 'webuser')");
            $s2->bind_param('ss', $email, $hashed);

            if ($s1->execute() && $s2->execute()) {
                $success_message = 'Account created successfully!';
                // Clear OTP session data
                unset(
                    $_SESSION['reg_otp_verified'],
                    $_SESSION['reg_otp_email']
                );
            } else {
                $error_message = 'Registration failed. Please try again.';
            }
            $s1->close();
            $s2->close();
        }
    }
}

// Is email already OTP-verified from a previous send in this session?
$otp_verified   = !empty($_SESSION['reg_otp_verified']);
$verified_email = $otp_verified ? htmlspecialchars($_SESSION['reg_otp_email'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
    <title>Create Account — GoZayan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── RESET & BASE ───────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }

        :root {
            --bg:         #f0f4f9;
            --white:      #ffffff;
            --panel:      #f8fafd;
            --border:     #dce8f5;
            --border-dk:  #b8d0ea;
            --blue:       #1a6ff4;
            --blue-dk:    #0d4fc4;
            --blue-lt:    #e8f0fe;
            --text:       #0d1f35;
            --text2:      #3d5a7a;
            --muted:      #7a95b0;
            --danger:     #ef4444;
            --danger-lt:  #fef2f2;
            --success:    #10b981;
            --success-lt: #ecfdf5;
            --gold:       #f59e0b;
            --header-h:   62px;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        /* ── SPLIT LAYOUT ───────────────────────────────── */
        .page-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
            padding-top: var(--header-h);   /* push below fixed header */
        }

        /* ── LEFT PANEL ─────────────────────────────────── */
        .left-panel {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 56px 52px;
            min-height: calc(100vh - var(--header-h));
        }

        .left-bg {
            position: absolute; inset: 0;
            background: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat;
            animation: bgDrift 22s ease-in-out infinite alternate;
        }
        @keyframes bgDrift {
            from { transform: scale(1.0) translate(0, 0); }
            to   { transform: scale(1.07) translate(-14px, -8px); }
        }

        .left-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(155deg,
                rgba(8,18,45,0.15) 0%,
                rgba(8,18,45,0.55) 45%,
                rgba(5,12,35,0.93) 100%
            );
        }

        /* Blue accent line on right edge of left panel */
        .left-edge {
            position: absolute; top: 0; right: 0;
            width: 3px; height: 100%;
            background: linear-gradient(to bottom, transparent 10%, var(--blue) 50%, transparent 90%);
            opacity: 0.6;
        }

        .left-content {
            position: relative;
            z-index: 2;
        }

        .badge-pill {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 5px 14px;
            border-radius: 40px;
            font-size: 0.68rem; font-weight: 600;
            color: rgba(255,255,255,0.85);
            letter-spacing: 0.1em; text-transform: uppercase;
            margin-bottom: 22px;
        }
        .badge-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 0 3px rgba(52,211,153,0.25);
        }

        .left-heading {
            font-size: clamp(2rem, 3.2vw, 3rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            letter-spacing: -0.7px;
            margin-bottom: 16px;
        }
        .left-heading em {
            font-style: italic;
            font-weight: 300;
            color: #93c5fd;
        }

        .left-sub {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.58);
            line-height: 1.75;
            max-width: 350px;
            margin-bottom: 34px;
        }

        .feat-list {
            display: flex; flex-direction: column; gap: 12px;
            margin-bottom: 40px;
        }
        .feat-item {
            display: flex; align-items: center; gap: 12px;
            font-size: 0.83rem;
            color: rgba(255,255,255,0.75);
        }
        .feat-ico {
            width: 32px; height: 32px; flex-shrink: 0;
            border-radius: 8px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.82rem;
        }

        .quote-card {
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.13);
            border-radius: 16px;
            padding: 20px 22px;
            max-width: 370px;
        }
        .quote-stars  { color: #fbbf24; font-size: 0.68rem; letter-spacing: 3px; margin-bottom: 9px; }
        .quote-text   { font-size: 0.81rem; color: rgba(255,255,255,0.7); font-style: italic; line-height: 1.65; margin-bottom: 10px; }
        .quote-author { font-size: 0.7rem; color: rgba(255,255,255,0.38); font-weight: 600; }

        /* ── RIGHT PANEL ────────────────────────────────── */
        .right-panel {
            background: var(--white);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 52px;
            position: relative;
            overflow-y: auto;
        }

        /* dot texture */
        .right-panel::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(0,0,0,0.025) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
        }

        /* glow blob */
        .right-panel::after {
            content: '';
            position: absolute; top: -80px; right: -80px;
            width: 320px; height: 320px; border-radius: 50%;
            background: radial-gradient(circle, rgba(26,111,244,0.07) 0%, transparent 70%);
            pointer-events: none;
        }

        .form-box {
            position: relative; z-index: 1;
            width: 100%; max-width: 440px;
            animation: riseUp 0.55s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes riseUp {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── FORM HEADER ────────────────────────────────── */
        .form-head {
            margin-bottom: 20px;
        }
        .form-head h2 {
            font-size: 1.75rem; font-weight: 800;
            color: var(--text); letter-spacing: -0.5px;
            margin-bottom: 4px;
            line-height: 1.2;
        }
        .form-head h2 span { color: var(--blue); }
        .form-head p  { font-size: 0.82rem; color: var(--muted); }
        .form-head a  { color: var(--blue); font-weight: 600; text-decoration: none; }
        .form-head a:hover { text-decoration: underline; }

        /* ── STEP BAR ───────────────────────────────────── */
        .step-bar {
            display: flex; align-items: center;
            margin-bottom: 20px; gap: 0;
            padding: 12px 14px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .step {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.68rem; font-weight: 600;
            color: var(--muted); text-transform: uppercase; letter-spacing: 0.07em;
            white-space: nowrap;
        }
        .step-num {
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--border); color: var(--muted);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.68rem; font-weight: 700; flex-shrink: 0;
            transition: background .3s, color .3s;
        }
        .step.active .step-num { background: var(--blue); color: #fff; }
        .step.active           { color: var(--blue); }
        .step.done .step-num   { background: var(--success); color: #fff; }
        .step.done             { color: var(--success); }

        .step-line {
            flex: 1; height: 2px; border-radius: 2px;
            background: var(--border); margin: 0 8px;
            transition: background .3s;
        }
        .step-line.done { background: var(--success); }

        /* ── ALERTS ─────────────────────────────────────── */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 11px 13px; border-radius: 10px;
            font-size: 0.82rem; line-height: 1.5;
            margin-bottom: 16px;
            animation: slideDown .3s ease both;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: var(--success-lt); border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error   { background: var(--danger-lt);  border: 1px solid #fecaca; color: #991b1b; }
        .alert svg     { flex-shrink: 0; margin-top: 1px; }

        /* ── GOOGLE BUTTON ──────────────────────────────── */
        .btn-google {
            width: 100%;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 10px 16px;
            background: var(--white);
            border: 1.5px solid var(--border-dk);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 0.86rem; font-weight: 600;
            color: var(--text2);
            cursor: pointer; text-decoration: none;
            transition: all .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-bottom: 14px;
        }
        .btn-google:hover {
            border-color: var(--blue);
            background: var(--blue-lt);
            color: var(--blue-dk);
            box-shadow: 0 3px 10px rgba(26,111,244,0.12);
            transform: translateY(-1px);
        }

        /* ── DIVIDER ────────────────────────────────────── */
        .or-divider {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 14px;
        }
        .or-divider::before, .or-divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
        .or-divider span { font-size: 0.72rem; color: var(--muted); font-weight: 500; }

        /* ── FIELDS ─────────────────────────────────────── */
        .field {
            display: flex; flex-direction: column; gap: 5px;
            margin-bottom: 12px;
        }
        .field label {
            font-size: 0.7rem; font-weight: 600;
            color: var(--text2);
            text-transform: uppercase; letter-spacing: 0.08em;
        }
        .req { color: var(--danger); margin-left: 2px; }

        .inp-wrap { position: relative; }
        .inp-wrap input {
            width: 100%;
            padding: 11px 40px 11px 14px;
            background: var(--panel);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 0.88rem;
            color: var(--text);
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .inp-wrap input::placeholder { color: #b0c4d8; }
        .inp-wrap input:focus {
            border-color: var(--blue);
            background: #fff;
            box-shadow: 0 0 0 3.5px rgba(26,111,244,0.13);
        }
        .inp-wrap input.is-valid   { border-color: var(--success); }
        .inp-wrap input.is-invalid { border-color: var(--danger); }

        /* icon inside input */
        .inp-ico {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            color: #b8d0ea; pointer-events: none;
            display: flex; align-items: center;
            transition: color .2s;
        }
        .inp-wrap input:focus ~ .inp-ico { color: var(--blue); }

        /* password toggle button */
        .pw-toggle {
            position: absolute; right: 11px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: #b8d0ea; cursor: pointer;
            display: flex; align-items: center; padding: 2px;
            transition: color .2s;
        }
        .pw-toggle:hover { color: var(--blue); }

        /* two-column row */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 0;
        }

        /* section divider between email and details */
        #secDetails {
            border-top: 1px solid var(--border);
            padding-top: 16px;
            margin-top: 4px;
        }

        /* ── EMAIL + OTP ROW ────────────────────────────── */
        .email-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: end;     /* aligns button to bottom of input */
        }
        .btn-send-otp {
            padding: 11px 14px;
            background: var(--blue-lt);
            border: 1.5px solid var(--blue);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 0.78rem; font-weight: 700;
            color: var(--blue-dk);
            cursor: pointer; white-space: nowrap;
            transition: all .2s;
            height: 42px;   /* same height as input */
        }
        .btn-send-otp:hover:not(:disabled) {
            background: var(--blue);
            color: #fff;
        }
        .btn-send-otp:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ── OTP DIGIT BOXES ────────────────────────────── */
        .otp-wrap {
            display: none;
            flex-direction: column;
            gap: 6px;
            margin-top: 2px;
            margin-bottom: 4px;
        }
        .otp-digits {
            display: flex; gap: 8px;
        }
        .otp-digits input {
            width: 44px; height: 48px;
            text-align: center;
            font-size: 1.15rem; font-weight: 700;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background: var(--panel);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            outline: none;
            caret-color: transparent;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .otp-digits input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(26,111,244,0.13);
            background: #fff;
        }
        .otp-digits input.d-filled  { border-color: var(--blue); }
        .otp-digits input.d-correct { border-color: var(--success); background: var(--success-lt); }
        .otp-digits input.d-wrong   { border-color: var(--danger);  background: var(--danger-lt);
                                      animation: shake .35s ease; }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60%  { transform: translateX(-4px); }
            40%,80%  { transform: translateX(4px); }
        }

        .otp-msg {
            font-size: 0.75rem; min-height: 16px;
            display: flex; align-items: center; gap: 5px;
        }
        .otp-msg.ok  { color: var(--success); }
        .otp-msg.err { color: var(--danger); }

        /* verified badge */
        .verified-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--success-lt);
            border: 1px solid #a7f3d0;
            padding: 5px 12px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 600;
            color: var(--success);
            margin-top: 4px;
            margin-bottom: 14px;
        }

        /* ── PASSWORD STRENGTH ──────────────────────────── */
        .strength-wrap { margin-top: 5px; }
        .strength-bar  { height: 3px; border-radius: 2px; background: var(--border); overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; width: 0; transition: width .4s, background .4s; }
        .strength-lbl  { font-size: 0.67rem; color: var(--muted); text-align: right; margin-top: 3px; }

        /* ── TERMS ──────────────────────────────────────── */
        .terms-row {
            display: flex; align-items: flex-start; gap: 9px;
            margin-bottom: 14px;
            margin-top: 2px;
        }
        .terms-row input[type="checkbox"] {
            width: 15px; height: 15px; flex-shrink: 0;
            margin-top: 2px; accent-color: var(--blue); cursor: pointer;
        }
        .terms-row label { font-size: 0.77rem; color: var(--muted); line-height: 1.5; cursor: pointer; }
        .terms-row a { color: var(--blue); text-decoration: none; font-weight: 500; }
        .terms-row a:hover { text-decoration: underline; }

        /* ── SUBMIT BUTTON ──────────────────────────────── */
        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--blue), var(--blue-dk));
            color: #fff; border: none; border-radius: 11px;
            font-family: 'Inter', sans-serif;
            font-size: 0.92rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.02em;
            box-shadow: 0 5px 18px rgba(26,111,244,0.3);
            transition: all .25s cubic-bezier(0.34,1.56,0.64,1);
            position: relative; overflow: hidden;
            margin-bottom: 14px;
        }
        .btn-register::after {
            content: '';
            position: absolute; top: 0; left: -100%; right: 100%; bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left .5s, right .5s;
        }
        .btn-register:hover::after { left: 100%; right: -100%; }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(26,111,244,0.4);
        }
        .btn-register:active { transform: translateY(0); }
        .btn-register:disabled { opacity: .55; cursor: not-allowed; transform: none; }

                /* ── BOTTOM LINK ────────────────────────────────── */
        .bottom-link {
            text-align: center;
            font-size: 0.81rem; color: var(--muted);
        }
        .bottom-link a { color: var(--blue); font-weight: 700; text-decoration: none; }
        .bottom-link a:hover { text-decoration: underline; }

        /* copyright */
        .copy {
            position: absolute; bottom: 16px; right: 24px;
            font-size: 0.7rem; color: #b8d0ea;
        }

        /* ── RESPONSIVE ─────────────────────────────────── */
        @media (max-width: 900px) {
            .page-wrap { grid-template-columns: 1fr; }
            .left-panel {
                min-height: 280px;
                justify-content: flex-end;
                padding: 32px 24px;
            }
            .quote-card { display: none; }
            .right-panel { padding: 36px 28px 64px; }
        }
        @media (max-width: 768px) {
            :root { --header-h: 52px; }
        }
        @media (max-width: 480px) {
            .two-col { grid-template-columns: 1fr; }
            .right-panel { padding: 28px 16px 56px; }
            .otp-digits input { width: 38px; height: 42px; font-size: 1rem; }
        }
    </style>
    <link rel="stylesheet" href="<?= BASE_URL ?>/dark.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="page-wrap">

    <!-- ════════════════════ LEFT PANEL ════════════════════ -->
    <div class="left-panel">
        <div class="left-bg"></div>
        <div class="left-overlay"></div>
        <div class="left-edge"></div>

        <div class="left-content">
            <div class="badge-pill">
                <span class="badge-dot"></span>
                Email Verified Registration
            </div>

            <h2 class="left-heading">
                Your journey<br><em>starts here</em>
            </h2>

            <p class="left-sub">
                Join GoZayan for fast flight booking, secure payments,
                and one place to manage all your travel.
            </p>

            <div class="feat-list">
                <div class="feat-item">
                    <div class="feat-ico">⚡</div>
                    Quick sign-up with email verification
                </div>
                <div class="feat-item">
                    <div class="feat-ico">✈</div>
                    Live flights with instant confirmation
                </div>
                <div class="feat-item">
                    <div class="feat-ico">🔒</div>
                    Encrypted payments and secure data
                </div>
                <div class="feat-item">
                    <div class="feat-ico">📋</div>
                    All bookings managed in one dashboard
                </div>
            </div>

            <div class="quote-card">
                <div class="quote-stars">★★★★★</div>
                <p class="quote-text">"Booked Dhaka–Chittagong in under 3 minutes. Ticket in my email instantly. Smoothest booking I've ever had."</p>
                <div class="quote-author">— Farhan R., GoZayan Traveller</div>
            </div>
        </div>
    </div>

    <!-- ════════════════════ RIGHT PANEL ═══════════════════ -->
    <div class="right-panel">
        <div class="form-box">

            <!-- Header -->
            <div class="form-head">
                <h2>Create <span>Account</span></h2>
                <p>Already a member? <a href="login.php">Sign in here</a></p>
            </div>

            <!-- Step bar: 3 steps -->
            <div class="step-bar">
                <div class="step <?= $otp_verified ? 'done' : 'active' ?>" id="s1">
                    <div class="step-num">1</div>Verify Email
                </div>
                <div class="step-line <?= $otp_verified ? 'done' : '' ?>" id="sl1"></div>
                <div class="step <?= $otp_verified ? 'active' : '' ?>" id="s2">
                    <div class="step-num">2</div>Your Details
                </div>
                <div class="step-line" id="sl2"></div>
                <div class="step" id="s3">
                    <div class="step-num">3</div>Done
                </div>
            </div>

            <!-- PHP server-side alerts -->
            <?php if ($success_message): ?>
            <div class="alert alert-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                <div>
                    <?= htmlspecialchars($success_message) ?>
                    &nbsp;<a href="login.php" style="color:var(--success);font-weight:700;">Login now →</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-error" id="phpErr">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div><?= htmlspecialchars($error_message) ?></div>
            </div>
            <?php endif; ?>

            <!-- ══ Google Sign-In ══ -->
            <?php if ($google_error): ?>
            <div class="alert alert-error" style="margin-bottom:14px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div><?= htmlspecialchars($google_error) ?></div>
            </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 48 48">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                Continue with Google
            </a>

            <div class="or-divider"><span>or register with email</span></div>

            <!-- ══════════ MAIN FORM ══════════ -->
            <form action="" method="POST" id="regForm" novalidate>

                <!-- ─── STEP 1: Email + OTP ─── -->
                <div id="secEmail">

                    <div class="field">
                        <label>Email Address <span class="req">*</span></label>
                        <div class="email-row">
                            <div class="inp-wrap">
                                <input type="email" name="email" id="emailInp"
                                    placeholder="you@example.com"
                                    value="<?= $verified_email ?>"
                                    required autocomplete="email"
                                    <?= $otp_verified ? 'readonly' : '' ?>>
                                <span class="inp-ico">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                            </div>
                            <button type="button" id="btnSendOtp" class="btn-send-otp"
                                <?= $otp_verified ? 'disabled' : '' ?>>
                                <?= $otp_verified ? '✓ Sent' : 'Send OTP' ?>
                            </button>
                        </div>
                    </div>

                    <?php if ($otp_verified): ?>
                    <!-- Already verified this session -->
                    <div class="verified-badge" style="margin-bottom:12px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                        Email verified — <?= $verified_email ?>
                    </div>
                    <?php else: ?>

                    <!-- OTP digit boxes — hidden until OTP sent -->
                    <div class="otp-wrap" id="otpWrap">
                        <div class="field" style="margin-bottom:4px;">
                            <label>6-Digit Code <span class="req">*</span></label>
                            <div class="otp-digits" id="otpDigits">
                                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" aria-label="OTP digit 1">
                                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 2">
                                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 3">
                                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 4">
                                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 5">
                                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 6">
                            </div>
                        </div>
                        <div class="otp-msg" id="otpMsg"></div>
                    </div>

                    <?php endif; ?>

                </div><!-- /secEmail -->

                <!-- ─── STEP 2: Name + Password (hidden until OTP verified) ─── -->
                <div id="secDetails" <?= $otp_verified ? '' : 'style="display:none;"' ?>>

                    <div class="two-col">
                        <div class="field">
                            <label>Full Name <span class="req">*</span></label>
                            <div class="inp-wrap">
                                <input type="text" name="name" id="nameInp"
                                    placeholder="Ahmed Rahman"
                                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                    required autocomplete="name">
                                <span class="inp-ico">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                            </div>
                        </div>

                        <div class="field">
                            <label>Password <span class="req">*</span></label>
                            <div class="inp-wrap">
                                <input type="password" name="pass" id="passInp"
                                    placeholder="Min. 6 characters"
                                    required autocomplete="new-password"
                                    oninput="checkStrength(this.value)">
                                <button type="button" class="pw-toggle" onclick="togglePw('passInp','eye1')" tabindex="-1">
                                    <svg id="eye1" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <div class="strength-wrap">
                                <div class="strength-bar"><div class="strength-fill" id="sFill"></div></div>
                                <div class="strength-lbl"  id="sLbl"></div>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label>Confirm Password <span class="req">*</span></label>
                        <div class="inp-wrap">
                            <input type="password" name="cpass" id="cpassInp"
                                placeholder="Repeat password"
                                required autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('cpassInp','eye2')" tabindex="-1">
                                <svg id="eye2" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="terms-row">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">
                            I agree to GoZayan's
                            <a href="#" tabindex="-1">Terms of Service</a> and
                            <a href="#" tabindex="-1">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" name="submit" class="btn-register">
                        Create My Account →
                    </button>

                </div><!-- /secDetails -->

                <div class="bottom-link">
                    Already have an account? <a href="login.php">Sign in to GoZayan</a>
                </div>

            </form>

        </div><!-- /form-box -->

        <span class="copy">&copy; GoZayan <?= date('Y') ?></span>
    </div>

</div><!-- /page-wrap -->

<script>
/* ── Config ─────────────────────────────────── */
const BASE         = '<?= BASE_URL ?>';
const INIT_VERIFIED = <?= $otp_verified ? 'true' : 'false' ?>;

/* ── DOM refs ───────────────────────────────── */
const emailInp   = document.getElementById('emailInp');
const btnSend    = document.getElementById('btnSendOtp');
const otpWrap    = document.getElementById('otpWrap');
const otpDigits  = document.querySelectorAll('#otpDigits input');
const otpMsg     = document.getElementById('otpMsg');
const secDetails = document.getElementById('secDetails');
const s1 = document.getElementById('s1');
const s2 = document.getElementById('s2');
const s3 = document.getElementById('s3');
const sl1 = document.getElementById('sl1');
const sl2 = document.getElementById('sl2');

let otpVerified = INIT_VERIFIED;
let countdown   = null;

/* ── Step UI ────────────────────────────────── */
function setStep(n) {
    // n=1 → step1 active; n=2 → step1 done, step2 active; n=3 → all done
    s1.className  = 'step ' + (n > 1 ? 'done' : 'active');
    s2.className  = 'step ' + (n > 2 ? 'done' : n === 2 ? 'active' : '');
    s3.className  = 'step ' + (n > 3 ? 'done' : n === 3 ? 'active' : '');
    sl1.className = 'step-line ' + (n > 1 ? 'done' : '');
    sl2.className = 'step-line ' + (n > 2 ? 'done' : '');
}
if (INIT_VERIFIED) setStep(2);

/* ── Send OTP ───────────────────────────────── */
btnSend && btnSend.addEventListener('click', async () => {
    const email = emailInp.value.trim();
    if (!/\S+@\S+\.\S+/.test(email)) {
        setMsg('err', 'Enter a valid email address first.');
        emailInp.focus();
        return;
    }
    btnSend.disabled    = true;
    btnSend.textContent = 'Sending…';
    clearMsg();

    try {
        const fd = new FormData();
        fd.append('email', email);
        const res  = await fetch(BASE + '/view/email_otp.php?action=send', { method:'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            otpWrap.style.display = 'flex';
            otpDigits[0].focus();
            setMsg('ok', '✅ OTP sent! Check your inbox and spam folder.');
            startCountdown(data.expires_in || 300);
        } else {
            setMsg('err', data.message || 'Failed to send OTP.');
            // Show SMTP debug info if available (remove once SMTP is working)
            if (data.debug) setMsg('err', (data.message || '') + '\n' + data.debug);
            btnSend.disabled    = false;
            btnSend.textContent = 'Send OTP';
        }
    } catch (e) {
        setMsg('err', 'Network error. Please try again.');
        btnSend.disabled    = false;
        btnSend.textContent = 'Send OTP';
    }
});

/* ── Countdown ──────────────────────────────── */
function startCountdown(secs) {
    clearInterval(countdown);
    let left = secs;
    tick(left);
    countdown = setInterval(() => {
        left--;
        tick(left);
        if (left <= 0) {
            clearInterval(countdown);
            btnSend.disabled    = false;
            btnSend.innerHTML   = 'Resend OTP';
        }
    }, 1000);
}
function tick(s) {
    const m  = String(Math.floor(s / 60)).padStart(2, '0');
    const ss = String(s % 60).padStart(2, '0');
    btnSend.disabled    = true;
    btnSend.textContent = m + ':' + ss;
}

/* ── OTP digit input handling ───────────────── */
otpDigits.forEach((inp, idx) => {
    inp.addEventListener('input', e => {
        const v = e.target.value.replace(/\D/g,'');
        inp.value = v;
        inp.classList.toggle('d-filled', !!v);
        if (v && idx < 5) otpDigits[idx + 1].focus();
        if ([...otpDigits].every(d => d.value.length === 1)) verifyOtp();
    });

    inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && idx > 0) {
            otpDigits[idx - 1].value = '';
            otpDigits[idx - 1].classList.remove('d-filled');
            otpDigits[idx - 1].focus();
        }
    });

    // paste support — paste full 6-digit code
    inp.addEventListener('paste', e => {
        const pasted = (e.clipboardData || window.clipboardData)
                       .getData('text').replace(/\D/g,'').slice(0, 6);
        if (pasted.length === 6) {
            e.preventDefault();
            pasted.split('').forEach((ch, i) => {
                otpDigits[i].value = ch;
                otpDigits[i].classList.add('d-filled');
            });
            otpDigits[5].focus();
            verifyOtp();
        }
    });
});

/* ── Verify OTP via AJAX ────────────────────── */
async function verifyOtp() {
    const code  = [...otpDigits].map(d => d.value).join('');
    const email = emailInp.value.trim();
    setMsg('', 'Verifying…');

    try {
        const fd = new FormData();
        fd.append('otp',   code);
        fd.append('email', email);
        const res  = await fetch(BASE + '/view/email_otp.php?action=verify', { method:'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            otpVerified = true;
            clearInterval(countdown);
            otpDigits.forEach(d => { d.classList.add('d-correct'); d.readOnly = true; });
            setMsg('ok', '✅ ' + data.message);

            setTimeout(() => {
                emailInp.readOnly   = true;
                btnSend.disabled    = true;
                btnSend.textContent = '✓ Verified';
                secDetails.style.display = 'block';
                secDetails.scrollIntoView({ behavior:'smooth', block:'nearest' });
                document.getElementById('nameInp').focus();
                setStep(2);
            }, 700);

        } else {
            otpDigits.forEach(d => { d.classList.add('d-wrong'); });
            setTimeout(() => {
                otpDigits.forEach(d => { d.classList.remove('d-wrong','d-filled'); d.value=''; });
                otpDigits[0].focus();
            }, 500);
            setMsg('err', data.message || 'Incorrect OTP.');

            if (data.expired || data.max_reached) {
                clearInterval(countdown);
                otpWrap.style.display = 'none';
                btnSend.disabled      = false;
                btnSend.textContent   = 'Send OTP';
            }
        }
    } catch(e) {
        setMsg('err', 'Network error. Please try again.');
    }
}

/* ── OTP message helpers ────────────────────── */
function setMsg(type, text) {
    otpMsg.className = 'otp-msg ' + type;
    otpMsg.textContent = text;
}
function clearMsg() {
    otpMsg.className   = 'otp-msg';
    otpMsg.textContent = '';
}

/* ── Password strength ──────────────────────── */
function checkStrength(val) {
    let score = 0;
    if (val.length >= 6)          score++;
    if (val.length >= 10)         score++;
    if (/[A-Z]/.test(val))        score++;
    if (/[0-9]/.test(val))        score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { w:'0%',   c:'transparent', t:'' },
        { w:'25%',  c:'#ef4444',     t:'Too weak' },
        { w:'50%',  c:'#f59e0b',     t:'Fair' },
        { w:'75%',  c:'#10b981',     t:'Good' },
        { w:'90%',  c:'#10b981',     t:'Strong' },
        { w:'100%', c:'#1a6ff4',     t:'💪 Excellent' },
    ];
    const l = levels[Math.min(score, 5)];
    const fill = document.getElementById('sFill');
    const lbl  = document.getElementById('sLbl');
    fill.style.width      = l.w;
    fill.style.background = l.c;
    lbl.textContent       = l.t;
    lbl.style.color       = l.c;
}

/* ── Toggle password visibility ─────────────── */
function togglePw(inputId, svgId) {
    const inp = document.getElementById(inputId);
    const svg = document.getElementById(svgId);
    if (inp.type === 'password') {
        inp.type = 'text';
        svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        inp.type = 'password';
        svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}

/* ── Google button — direct link, no JS needed ── */

/* ── Client-side form validation ────────────── */
document.getElementById('regForm').addEventListener('submit', function(e) {
    // Must be OTP-verified
    if (!otpVerified && !INIT_VERIFIED) {
        e.preventDefault();
        showClientErr('Please verify your email with the OTP first.');
        return;
    }

    const name  = document.getElementById('nameInp')?.value.trim()  || '';
    const pass  = document.getElementById('passInp')?.value          || '';
    const cpass = document.getElementById('cpassInp')?.value         || '';
    const terms = document.getElementById('terms')?.checked;
    const errs  = [];

    if (!name)           errs.push('Please enter your full name.');
    if (pass.length < 6) errs.push('Password must be at least 6 characters.');
    if (pass !== cpass)  errs.push('Passwords do not match.');
    if (!terms)          errs.push('Please accept the Terms of Service.');

    if (errs.length) {
        e.preventDefault();
        showClientErr(errs.join('<br>'));
    }
});

function showClientErr(html) {
    document.querySelector('.client-err')?.remove();
    const el = document.createElement('div');
    el.className = 'alert alert-error client-err';
    el.innerHTML =
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
        '<div>' + html + '</div>';
    const formBox = document.querySelector('.form-box');
    formBox.insertBefore(el, formBox.querySelector('.step-bar'));
    el.scrollIntoView({ behavior:'smooth', block:'nearest' });
}
</script>

<?php include '../includes/footer.php'; ?>

<!-- GoZayan Theme Toggle (floating) -->
<style>
.gz-float-theme {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    display: flex; align-items: center; gap: 7px;
    background: rgba(13,17,23,0.85);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 24px; padding: 8px 16px 8px 12px;
    cursor: pointer; font-family: inherit; font-size: 0.8rem; font-weight: 600;
    color: rgba(255,255,255,0.85);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    transition: all .2s; white-space: nowrap;
}
.gz-float-theme:hover { background: rgba(13,17,23,0.95); color: #fff; transform: translateY(-2px); }
.gz-float-theme .gz-theme-icon { font-size: 1rem; line-height: 1; }
.gz-float-theme .gz-theme-label { line-height: 1; }
@media (max-width: 480px) { .gz-float-theme { bottom: 16px; right: 16px; } .gz-float-theme .gz-theme-label { display: none; } }
</style>
<button class="gz-float-theme gz-theme-btn" type="button" aria-label="Toggle dark mode">
    <span class="gz-theme-icon">🌙</span>
    <span class="gz-theme-label">Dark</span>
</button>
</body>
</html>
