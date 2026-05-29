<?php
include("../model/db_conn.php");

$success_message = "";
$error_message = "";

if (isset($_POST['submit'])) {
    
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['pass'];
    $confirm_password = $_POST['cpass'];
    
    if ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        $check_query = "SELECT id FROM webusers WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "This email is already registered.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $image = "";
            if (!empty($_FILES['image']['name'])) {
                $upload_dir = __DIR__ . "/uploads/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $image = time() . '_' . basename($_FILES['image']['name']);
                $target_file = $upload_dir . $image;
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                if (in_array($_FILES['image']['type'], $allowed_types)) {
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $error_message = "Failed to upload image.";
                    }
                } else {
                    $error_message = "Only JPEG and PNG images are allowed.";
                }
            } else {
                $error_message = "Please select a profile picture.";
            }
            
            if (empty($error_message)) {
                $sql_webusers = "INSERT INTO webusers (name, email, pass, image) VALUES (?, ?, ?, ?)";
                $stmt_webusers = $conn->prepare($sql_webusers);
                $stmt_webusers->bind_param("ssss", $name, $email, $hashed_password, $image);
                
                $sql_login = "INSERT INTO login (email, password, role) VALUES (?, ?, 'webuser')";
                $stmt_login = $conn->prepare($sql_login);
                $stmt_login->bind_param("ss", $email, $hashed_password);
                
                if ($stmt_webusers->execute() && $stmt_login->execute()) {
                    $success_message = "Account created successfully! You can now login.";
                    $_POST = array();
                } else {
                    $error_message = "Registration failed. Please try again.";
                }
                $stmt_webusers->close();
                $stmt_login->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — GoZayan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --night:   #080f1e;
            --deep:    #0d1a30;
            --panel:   #111e35;
            --border:  #1c2f4a;
            --sky:     #0b72e6;
            --sky-lt:  #3d94f5;
            --gold:    #e8b84b;
            --gold-lt: #f5d07a;
            --text:    #e6edf8;
            --muted:   #6b86a6;
            --danger:  #f05a5a;
            --success: #2dd4a0;
            --r:       14px;
        }

        html, body { height: 100%; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--night);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── FULL-PAGE SPLIT LAYOUT ── */
        .page-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: calc(100vh - 60px); /* subtract approx header */
        }

        /* ════════════════════
           LEFT PANEL
        ════════════════════ */
        .left-panel {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            padding: 60px 56px;
            min-height: 100%;
        }

        /* Unsplash aerial night flight photo */
        .left-bg {
            position: absolute; inset: 0;
            background-image: url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=85');
            background-size: cover;
            background-position: center;
            animation: subtleShift 18s ease-in-out infinite alternate;
        }
        @keyframes subtleShift {
            from { transform: scale(1.0) translate(0, 0); }
            to   { transform: scale(1.06) translate(-12px, -8px); }
        }

        /* Dark gradient over photo */
        .left-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(
                160deg,
                rgba(8,15,30,0.25) 0%,
                rgba(8,15,30,0.55) 40%,
                rgba(8,15,30,0.90) 100%
            );
        }

        /* Diagonal gold accent strip */
        .left-accent {
            position: absolute;
            top: 0; right: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, transparent, var(--gold), transparent);
            opacity: 0.6;
        }

        .left-content {
            position: relative; z-index: 2;
        }

        /* Floating badge */
        .brand-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(232,184,75,0.15);
            border: 1px solid rgba(232,184,75,0.4);
            padding: 7px 18px; border-radius: 40px;
            font-size: 0.72rem; font-weight: 600;
            color: var(--gold);
            letter-spacing: 0.12em; text-transform: uppercase;
            margin-bottom: 28px;
        }

        .left-heading {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.4rem, 4vw, 3.6rem);
            font-weight: 700;
            color: #fff;
            line-height: 1.12;
            margin-bottom: 18px;
            letter-spacing: -0.5px;
        }
        .left-heading em {
            color: var(--gold);
            font-style: italic;
        }

        .left-desc {
            color: rgba(255,255,255,0.65);
            font-size: 0.92rem;
            line-height: 1.75;
            margin-bottom: 40px;
            max-width: 380px;
            font-weight: 300;
        }

        /* Feature list */
        .feat-list {
            list-style: none;
            display: flex; flex-direction: column; gap: 14px;
        }
        .feat-list li {
            display: flex; align-items: center; gap: 14px;
            font-size: 0.87rem; color: rgba(255,255,255,0.75);
            font-weight: 400;
        }
        .feat-icon {
            width: 34px; height: 34px; flex-shrink: 0;
            background: rgba(232,184,75,0.15);
            border: 1px solid rgba(232,184,75,0.3);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
        }

        /* Testimonial card at bottom-left */
        .testimonial-card {
            margin-top: 44px;
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: var(--r);
            padding: 20px 22px;
            max-width: 380px;
        }
        .testimonial-stars { color: var(--gold); font-size: 0.75rem; margin-bottom: 8px; letter-spacing: 2px; }
        .testimonial-text  { font-size: 0.83rem; color: rgba(255,255,255,0.75); line-height: 1.6; font-style: italic; margin-bottom: 12px; }
        .testimonial-author { font-size: 0.75rem; color: rgba(255,255,255,0.45); font-weight: 500; }

        /* ════════════════════
           RIGHT PANEL (FORM)
        ════════════════════ */
        .right-panel {
            background: var(--deep);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 56px;
            position: relative;
            overflow: hidden;
        }

        /* Subtle dot-grid background */
        .right-panel::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 26px 26px;
            pointer-events: none;
        }

        /* Glow blob top-right */
        .right-panel::after {
            content: '';
            position: absolute; top: -100px; right: -100px;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(11,114,230,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .form-box {
            position: relative; z-index: 2;
            width: 100%; max-width: 440px;
            animation: riseIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes riseIn {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Form header */
        .form-header { margin-bottom: 34px; }
        .form-header h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem; font-weight: 700;
            color: var(--text); margin-bottom: 6px;
            letter-spacing: -0.3px;
        }
        .form-header h2 span { color: var(--sky-lt); }
        .form-header p { font-size: 0.85rem; color: var(--muted); }
        .form-header a { color: var(--sky-lt); text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .form-header a:hover { color: var(--gold); }

        /* Progress dots */
        .progress-dots {
            display: flex; gap: 6px; margin-bottom: 32px;
        }
        .pdot {
            height: 3px; border-radius: 2px;
            background: var(--border);
            transition: all 0.3s;
        }
        .pdot.done  { background: var(--success); width: 28px; }
        .pdot.active { background: var(--sky); width: 40px; }
        .pdot.idle   { width: 16px; }

        /* Alert banners */
        .alert {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 13px 16px; border-radius: 10px;
            font-size: 0.85rem; line-height: 1.5;
            margin-bottom: 24px;
            animation: fadeSlide 0.3s ease both;
        }
        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: rgba(45,212,160,0.1); border: 1px solid rgba(45,212,160,0.3); color: #7fffd4; }
        .alert-error   { background: rgba(240,90,90,0.1);  border: 1px solid rgba(240,90,90,0.3);  color: #ffb3b3; }
        .alert-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

        /* Field groups */
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field-row.full { grid-template-columns: 1fr; }

        .field {
            display: flex; flex-direction: column; gap: 6px;
            margin-bottom: 16px;
        }
        .field label {
            font-size: 0.72rem; font-weight: 600;
            color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.09em;
        }
        .field label .req { color: var(--danger); margin-left: 2px; }

        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%;
            padding: 12px 42px 12px 16px;
            background: var(--panel);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            color: var(--text);
            outline: none;
            transition: border-color 0.22s, box-shadow 0.22s, background 0.22s;
        }
        .input-wrap input::placeholder { color: #3a5070; }
        .input-wrap input:focus {
            border-color: var(--sky);
            background: #0c1829;
            box-shadow: 0 0 0 4px rgba(11,114,230,0.14);
        }
        .input-wrap input.valid   { border-color: var(--success); }
        .input-wrap input.invalid { border-color: var(--danger); }

        .input-icon {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            font-size: 0.95rem; pointer-events: none; color: #3a5070;
            transition: color 0.2s;
        }
        .input-wrap input:focus ~ .input-icon { color: var(--sky-lt); }

        /* Toggle password */
        .pass-toggle {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: #3a5070; font-size: 0.95rem;
            cursor: pointer; padding: 4px; transition: color 0.2s;
        }
        .pass-toggle:hover { color: var(--text); }

        /* Password strength bar */
        .strength-bar-wrap { margin-top: 6px; }
        .strength-bar {
            height: 3px; border-radius: 2px;
            background: var(--border);
            overflow: hidden;
        }
        .strength-fill {
            height: 100%; border-radius: 2px;
            width: 0%; transition: width 0.4s ease, background 0.4s ease;
        }
        .strength-label {
            font-size: 0.68rem; color: var(--muted);
            margin-top: 4px; text-align: right;
        }

        /* File upload area */
        .file-drop {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 22px 16px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
            background: var(--panel);
        }
        .file-drop:hover { border-color: var(--sky); background: #0c1829; }
        .file-drop.dragover { border-color: var(--gold); background: rgba(232,184,75,0.06); }
        .file-drop input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .file-drop-icon { font-size: 1.8rem; margin-bottom: 6px; display: block; }
        .file-drop-label { font-size: 0.82rem; color: var(--muted); }
        .file-drop-label span { color: var(--sky-lt); font-weight: 600; }
        .file-name-tag {
            margin-top: 8px; font-size: 0.75rem;
            color: var(--success); min-height: 16px;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }

        /* Avatar preview */
        .avatar-ring {
            width: 64px; height: 64px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            object-fit: cover;
            display: none;
            margin: 10px auto 0;
            box-shadow: 0 0 0 4px rgba(232,184,75,0.15);
        }

        /* Terms */
        .terms-row {
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 20px; margin-top: 4px;
        }
        .terms-row input[type="checkbox"] {
            width: 16px; height: 16px; accent-color: var(--sky);
            flex-shrink: 0; margin-top: 2px; cursor: pointer;
        }
        .terms-row label {
            font-size: 0.78rem; color: var(--muted); cursor: pointer; line-height: 1.5;
        }
        .terms-row a { color: var(--sky-lt); text-decoration: none; font-weight: 500; }
        .terms-row a:hover { color: var(--gold); }

        /* Submit button */
        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--sky), #0550a8);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.04em;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            box-shadow: 0 6px 22px rgba(11,114,230,0.4);
            position: relative;
            overflow: hidden;
        }
        .btn-register::before {
            content: '';
            position: absolute; top: 0; left: -100%; right: 100%; bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
            transition: left 0.5s, right 0.5s;
        }
        .btn-register:hover::before { left: 100%; right: -100%; }
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(11,114,230,0.5);
        }
        .btn-register:active { transform: translateY(0); }

        /* Divider */
        .divider {
            display: flex; align-items: center; gap: 14px;
            margin: 20px 0; color: var(--border); font-size: 0.75rem;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px;
            background: var(--border);
        }
        .divider span { color: var(--muted); white-space: nowrap; }

        /* Login link row */
        .login-row {
            text-align: center; font-size: 0.82rem; color: var(--muted);
        }
        .login-row a { color: var(--sky-lt); font-weight: 600; text-decoration: none; transition: color 0.2s; }
        .login-row a:hover { color: var(--gold); }

        /* ── RESPONSIVE ── */
        @media (max-width: 860px) {
            .page-split { grid-template-columns: 1fr; }
            .left-panel { min-height: 340px; align-items: center; padding: 48px 32px; }
            .testimonial-card { display: none; }
            .right-panel { padding: 48px 28px; }
        }
        @media (max-width: 480px) {
            .field-row { grid-template-columns: 1fr; }
            .right-panel { padding: 36px 20px; }
        }
    </style>
</head>
<body>
<?php include("../includes/header.php"); ?>

<div class="page-split">

    <!-- ════════════ LEFT PANEL ════════════ -->
    <div class="left-panel">
        <div class="left-bg"></div>
        <div class="left-overlay"></div>
        <div class="left-accent"></div>

        <div class="left-content">
            <div class="brand-badge">✦ GoZayan Pro</div>

            <h2 class="left-heading">
                Professional travel<br>
                planning, <em>made simple</em>
            </h2>

            <p class="left-desc">
                Register for fast flight access, secure payment flow, and intelligent itinerary management across all routes.
            </p>

            <ul class="feat-list">
                <li>
                    <span class="feat-icon">⚡</span>
                    Fast onboarding with a streamlined form
                </li>
                <li>
                    <span class="feat-icon">✈</span>
                    Live flight availability with instant confirmation
                </li>
                <li>
                    <span class="feat-icon">🔒</span>
                    Encrypted payments and trusted data security
                </li>
                <li>
                    <span class="feat-icon">📋</span>
                    Manage bookings, tickets, and schedules from one place
                </li>
            </ul>

            <div class="testimonial-card">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"Booked my Dhaka–Chittagong flight in under 3 minutes. The ticket was in my email instantly. Genuinely the smoothest booking experience I've had."</p>
                <div class="testimonial-author">— Farhan R., GoZayan Traveller</div>
            </div>
        </div>
    </div>

    <!-- ════════════ RIGHT PANEL ════════════ -->
    <div class="right-panel">
        <div class="form-box">

            <div class="form-header">
                <h2>Create <span>Account</span></h2>
                <p>Already a member? <a href="login.php">Sign in here</a></p>
            </div>

            <!-- Progress indicator -->
            <div class="progress-dots" id="progressDots">
                <div class="pdot active"></div>
                <div class="pdot idle"></div>
                <div class="pdot idle"></div>
            </div>

            <!-- Alerts -->
            <?php if ($success_message): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <div><?= htmlspecialchars($success_message) ?> <a href="login.php" style="color:var(--success);font-weight:700;">Login now →</a></div>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <div><?= htmlspecialchars($error_message) ?></div>
            </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" id="registerForm" novalidate>

                <!-- Name + Email -->
                <div class="field-row">
                    <div class="field">
                        <label>Full Name <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="text" name="name" id="name"
                                   placeholder="Ahmed Rahman"
                                   value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
                                   required autocomplete="name">
                            <span class="input-icon">👤</span>
                        </div>
                    </div>
                    <div class="field">
                        <label>Email Address <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="email" name="email" id="email"
                                   placeholder="you@email.com"
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                   required autocomplete="email">
                            <span class="input-icon">✉️</span>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="field-row">
                    <div class="field">
                        <label>Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" name="pass" id="pass"
                                   placeholder="Min. 6 characters"
                                   required autocomplete="new-password"
                                   oninput="checkStrength(this.value)">
                            <button type="button" class="pass-toggle" onclick="togglePass('pass', this)">👁</button>
                        </div>
                        <!-- Strength bar -->
                        <div class="strength-bar-wrap">
                            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                            <div class="strength-label" id="strengthLabel"></div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Confirm Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" name="cpass" id="cpass"
                                   placeholder="Repeat password"
                                   required autocomplete="new-password">
                            <button type="button" class="pass-toggle" onclick="togglePass('cpass', this)">👁</button>
                        </div>
                    </div>
                </div>

                <!-- Profile picture -->
                <div class="field">
                    <label>Profile Picture <span class="req">*</span> <small style="color:var(--muted);font-weight:400;text-transform:none;">(JPG/PNG, max 2MB)</small></label>
                    <div class="file-drop" id="fileDrop">
                        <input type="file" name="image" id="image"
                               accept="image/jpg,image/jpeg,image/png"
                               required onchange="handleFile(this)">
                        <span class="file-drop-icon">🖼️</span>
                        <div class="file-drop-label"><span>Click to browse</span> or drag &amp; drop your photo</div>
                        <div class="file-name-tag" id="fileName"></div>
                        <img id="avatarPreview" class="avatar-ring" alt="Preview">
                    </div>
                </div>

                <!-- Terms checkbox -->
                <div class="terms-row">
                    <input type="checkbox" name="terms" id="terms" required>
                    <label for="terms">
                        I agree to GoZayan's <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" name="submit" class="btn-register">
                    ✈ Create My Account
                </button>

                <div class="divider"><span>Already have an account?</span></div>
                <div class="login-row">
                    <a href="login.php">Sign in to GoZayan →</a>
                </div>

            </form>
        </div>
    </div>

</div>

<script>
// Toggle password visibility
function togglePass(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
}

// Password strength checker
function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '0%',   color: 'transparent', text: '' },
        { pct: '25%',  color: '#f05a5a', text: 'Too weak' },
        { pct: '50%',  color: '#f5a623', text: 'Fair' },
        { pct: '75%',  color: '#2dd4a0', text: 'Good' },
        { pct: '90%',  color: '#2dd4a0', text: 'Strong' },
        { pct: '100%', color: '#e8b84b', text: '💪 Excellent' },
    ];
    const lvl = levels[Math.min(score, 5)];
    fill.style.width  = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent = lvl.text;
    label.style.color = lvl.color;
}

// File handler with preview
function handleFile(input) {
    const nameEl   = document.getElementById('fileName');
    const preview  = document.getElementById('avatarPreview');
    const dropZone = document.getElementById('fileDrop');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const kb   = (file.size / 1024).toFixed(0);
        nameEl.innerHTML = '✅ ' + file.name + ' (' + kb + ' KB)';
        dropZone.style.borderColor = 'var(--success)';

        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);

        // Update progress dots
        updateDots(2);
    }
}

// Drag events
const drop = document.getElementById('fileDrop');
drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('dragover'); });
drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
drop.addEventListener('drop',      e => { drop.classList.remove('dragover'); });

// Progress dots
function updateDots(step) {
    const dots = document.querySelectorAll('.pdot');
    dots.forEach((d, i) => {
        d.className = 'pdot ' + (i < step ? 'done' : i === step ? 'active' : 'idle');
    });
}

// Field activity tracking for progress
const nameInput  = document.getElementById('name');
const emailInput = document.getElementById('email');
const passInput  = document.getElementById('pass');

[nameInput, emailInput].forEach(el => {
    el.addEventListener('input', () => {
        if (nameInput.value.trim() && emailInput.value.trim()) updateDots(1);
    });
});
passInput.addEventListener('input', () => {
    if (passInput.value.length >= 6) updateDots(2);
});

// Client-side form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const name  = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const pass  = document.getElementById('pass').value;
    const cpass = document.getElementById('cpass').value;
    const image = document.getElementById('image').files[0];
    const terms = document.getElementById('terms').checked;
    const errors = [];

    if (!name)                             errors.push('Please enter your full name.');
    if (!/\S+@\S+\.\S+/.test(email))       errors.push('Please enter a valid email address.');
    if (pass.length < 6)                   errors.push('Password must be at least 6 characters.');
    if (pass !== cpass)                    errors.push('Passwords do not match.');
    if (!image)                            errors.push('Please select a profile picture.');
    if (!terms)                            errors.push('Please accept the Terms of Service.');

    if (errors.length > 0) {
        e.preventDefault();
        // Remove old client alert if any
        document.querySelector('.alert-client')?.remove();
        const alert = document.createElement('div');
        alert.className = 'alert alert-error alert-client';
        alert.innerHTML = '<span class="alert-icon">⚠️</span><div>' + errors.join('<br>') + '</div>';
        document.querySelector('.form-box').insertBefore(alert, document.querySelector('.progress-dots'));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
</script>

<?php include("../includes/footer.php"); ?>
</body>
</html>