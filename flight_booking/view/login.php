<?php
session_start();
require_once __DIR__ . "/../config/base_url.php";
require_once __DIR__ . "/../config/google_oauth.php";
include("../model/db_conn.php");

$error = "";

// Pick up any Google OAuth error
if (!empty($_SESSION['google_error'])) {
    $error = $_SESSION['google_error'];
    unset($_SESSION['google_error']);
}

$googleAuthUrl = getGoogleAuthUrl('login');

if (isset($_POST['submit'])) {

    $email = trim($_POST['email']);
    $pass  = trim($_POST['pass']);

    if ($email === "" || $pass === "") {
        $error = "All fields are required.";
    } else {

        // Fetch admin/manager from table
        $stmt = $conn->prepare("SELECT * FROM login WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc(); // use $user for clarity

            // Check password (hashed or plain text)
            if (password_verify($pass, $user['password']) || $pass === $user['password']) {
                
                $_SESSION['email'] = $user['email'];
                $_SESSION['role']  = $user['role']; // store role in session

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: addAirline.php");
                    exit;
                } elseif ($user['role'] === 'manager') {
                    header("Location: managerdemo.php");
                    exit;
                }
                elseif ($user['role'] === 'webuser') {
                    // Redirect to original page if set, otherwise userhome
                    $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
                    if (!empty($redirect) && str_starts_with($redirect, 'searchflights.php')) {
                        header("Location: " . BASE_URL . "/view/" . ltrim($redirect, '/'));
                    } else {
                        header("Location: searchflights.php");
                    }
                    exit;       
                }
                else {
                    $error = "Your role is not recognized.";
                }

            } else {
                $error = "Incorrect password.";
            }

        } else {
            $error = "Email not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <title>GoZayan | Log In</title> -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            height: 100vh;
            display: flex;
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
        }

        /* ── OUTER WRAPPER ── */
        .login-wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
            background: #fff;
            overflow: hidden;
        }

        /* ── LEFT: IMAGE PANEL ── */
        .image-panel {
            position: relative;
            width: 50%;
            flex-shrink: 0;
            overflow: hidden;
            margin: 0;
            border-radius: 0 35px 35px 0;
            box-shadow: 4px 0 40px rgba(0,0,0,0.18);
        }

        /* Slides */
        .slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }
        .slide.active { opacity: 1; }

        /* Aviation / travel photos */
        .slide-1 { background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?q=80&w=1746&auto=format&fit=crop'); }
        .slide-2 { background-image: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?q=80&w=1748&auto=format&fit=crop'); }
        .slide-3 { background-image: url('https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?q=80&w=1740&auto=format&fit=crop'); }
        .slide-4 { background-image: url('https://images.unsplash.com/photo-1569154941061-e231b4aa8eda?q=80&w=1740&auto=format&fit=crop'); }

        /* Dark gradient — heavier at bottom for caption legibility */
        .image-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to top,
                rgba(0,0,0,0.78) 0%,
                rgba(0,0,0,0.25) 40%,
                rgba(0,0,0,0.04) 100%
            );
            z-index: 1;
            border-radius: 0 32px 32px 0;
        }

        /* Caption — bottom-left */
        .image-caption {
            position: absolute;
            bottom: 64px;
            left: 32px;
            right: 120px;
            z-index: 2;
            color: #fff;
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 16px rgba(0,0,0,0.5);
        }

        /* Arrows only — bottom-right, no dots */
        .slide-controls {
            position: absolute;
            bottom: 22px;
            right: 28px;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .slide-arrow {
            width: 38px; height: 38px;
            border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,0.5);
            background: rgba(255,255,255,0.15);
            color: #fff;
            font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, transform 0.15s;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            user-select: none;
        }
        .slide-arrow:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.95);
            transform: scale(1.08);
        }

        /* ── RIGHT: FORM PANEL ── */
        .form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 64px;
            position: relative;
            background: #fff;
            justify-content: center;
            overflow-y: auto;
        }

        /* Brand top-right */
        .brand { position: absolute; top: 28px; right: 36px; font-size: 1.35rem; font-weight: 900; color: #0b3973; letter-spacing: -0.5px; }
        .brand a { text-decoration: none; color: inherit; }
        .brand a span { color: #0b72e6; }

        /* Form content centered vertically */
        .form-inner {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .form-panel h2 {
            font-size: 1.9rem;
            font-weight: 800;
            color: #0d1f35;
            margin-bottom: 8px;
            letter-spacing: -0.6px;
        }
        .form-panel .sub {
            font-size: 0.88rem;
            color: #7a95b0;
            margin-bottom: 32px;
            line-height: 1.55;
        }
        .form-panel .sub b {
            color: #0b3973;
            font-weight: 700;
        }

        /* Field groups */
        .field-group {
            margin-bottom: 18px;
        }
        .field-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #3d5a7a;
            margin-bottom: 7px;
            letter-spacing: 0.1px;
        }
        .field-group label .req { color: #ef4444; margin-left: 2px; }

        .field-wrap {
            position: relative;
        }
        .field-wrap input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #dce8f5;
            border-radius: 10px;
            font-size: 0.93rem;
            font-family: 'Inter', sans-serif;
            color: #0d1f35;
            background: #f8fbff;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            outline: none;
        }
        .field-wrap input::placeholder { color: #aabdd4; }
        .field-wrap input:focus {
            border-color: #1a6ff4;
            background: #fff;
            box-shadow: 0 0 0 3.5px rgba(26,111,244,0.13);
        }
        .toggle-pw {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer;
            color: #7a95b0;
            font-size: 1rem;
            padding: 0;
            display: flex; align-items: center;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: #1a6ff4; }

        /* Forgot password */
        .forgot-row {
            text-align: right;
            margin-bottom: 20px;
            margin-top: -6px;
        }
        .forgot-row a {
            font-size: 0.83rem;
            font-weight: 600;
            color: #1a6ff4;
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-row a:hover { color: #0d4fc4; text-decoration: underline; }

        /* Error */
        .error-msg {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(239,68,68,0.07);
            border: 1px solid rgba(239,68,68,0.25);
            color: #dc2626;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.84rem;
            font-weight: 500;
            margin-bottom: 16px;
        }

        /* Submit button */
        .login-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #1a6ff4 0%, #0d4fc4 100%);
            color: #fff;
            border: none;
            border-radius: 11px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            letter-spacing: 0.3px;
            box-shadow: 0 5px 18px rgba(26,111,244,0.3);
            transition: all 0.22s;
            margin-bottom: 22px;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 26px rgba(26,111,244,0.4);
            filter: brightness(1.06);
        }
        .login-btn:active { transform: translateY(0); }

        /* Register link */
        .register-row {
            text-align: center;
            font-size: 0.87rem;
            color: #7a95b0;
        }

        /* Google Sign-In button */
        .btn-google-login {
            width: 100%;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 11px 16px;
            background: #ffffff;
            border: 1.5px solid #c4d8ee;
            border-radius: 11px;
            font-family: 'Inter', sans-serif;
            font-size: 0.88rem; font-weight: 600;
            color: #3d5a7a;
            cursor: pointer; text-decoration: none;
            transition: all .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-bottom: 16px;
        }
        .btn-google-login:hover {
            border-color: #1a6ff4;
            background: #e8f0fe;
            color: #0d4fc4;
            box-shadow: 0 3px 10px rgba(26,111,244,0.12);
            transform: translateY(-1px);
        }

        /* or divider */
        .or-line {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 18px;
        }
        .or-line::before, .or-line::after {
            content: ''; flex: 1; height: 1px; background: #dce8f5;
        }
        .or-line span { font-size: 0.72rem; color: #7a95b0; font-weight: 500; white-space: nowrap; }
        .register-row a {
            color: #1a6ff4;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }
        .register-row a:hover { color: #0d4fc4; text-decoration: underline; }

        /* Copyright */
        .copy {
            position: absolute;
            bottom: 20px; right: 36px;
            font-size: 0.75rem;
            color: #b0c4d8;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 700px) {
            body { overflow: auto; }
            .login-wrapper {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }
            .image-panel {
                flex-shrink: 0;
                position: relative;
                margin: 0;
                width: 100%;
                height: 260px;
                border-radius: 0 0 22px 22px;
            }
            .image-panel::after { border-radius: 0 0 22px 22px; }
            .image-caption { font-size: 1.15rem; bottom: 52px; left: 22px; }
            .slide-controls { right: 18px; bottom: 16px; }
            .form-panel { padding: 40px 28px 60px; justify-content: flex-start; }
            .brand { top: 20px; right: 20px; }
            .copy { right: 20px; }
        }
    </style>
    <script>
(function(){var t=localStorage.getItem('gz_theme');if(t==='dark')document.documentElement.style.cssText='background:#0d1117';})();
</script>
<script src="<?= BASE_URL ?>/theme.js" defer></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/dark.css">
</head>
<body>

<div class="login-wrapper">

    <!-- ── LEFT: IMAGE SLIDESHOW ── -->
    <div class="image-panel" id="imagePanel">
        <div class="slide slide-1 active"></div>
        <div class="slide slide-2"></div>
        <div class="slide slide-3"></div>
        <div class="slide slide-4"></div>

        <div class="image-caption" id="caption">Think Flights, Think GoZayan</div>

        <div class="slide-controls">
            <button class="slide-arrow" id="prevBtn" aria-label="Previous">&#8592;</button>
            <button class="slide-arrow" id="nextBtn" aria-label="Next">&#8594;</button>
        </div>
    </div>

    <!-- ── RIGHT: FORM PANEL ── -->
    <div class="form-panel">
        <div class="brand"><a href="home.php">Go<span>Zayan</span></a></div>

        <div class="form-inner">
            <h2>Welcome</h2>
            <p class="sub">Login with your <b>GoZayan</b> account and enjoy a seamless journey across all services</p>

            <form action="" method="post">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'] ?? '') ?>">
                <?php if ($error != ""): ?>
                <div class="error-msg">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <div class="field-group">
                    <label for="email">Email Address <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="email" id="email" name="email" placeholder="you@example.com" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="field-group">
                    <label for="pass">Password <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="password" id="pass" name="pass" placeholder="Enter your password" required>
                        <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Toggle password">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="forgot-row">
                    <a href="#">Forgot Password?</a>
                </div>

                <button type="submit" name="submit" class="login-btn">Log In</button>

                <!-- Google Sign-In -->
                <div class="or-line"><span>or continue with</span></div>

                <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn-google-login">
                    <svg width="18" height="18" viewBox="0 0 48 48">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                    </svg>
                    Sign in with Google
                </a>

                <p class="register-row">Don't have an account? <a href="register.php">Create Account</a></p>
            </form>
        </div>

        <span class="copy">&copy; GoZayan <?= date('Y') ?></span>
    </div>
</div>

<script>
    // ── SLIDESHOW ──
    const captions = [
        'Think Flights, Think GoZayan',
        'Your Journey Starts Here',
        'Fly Smarter, Travel Better',
        'Explore Bangladesh From Above'
    ];
    let current = 0;
    const slides = document.querySelectorAll('.slide');
    const cap    = document.getElementById('caption');

    function goTo(n) {
        slides[current].classList.remove('active');
        current = (n + slides.length) % slides.length;
        slides[current].classList.add('active');
        cap.textContent = captions[current];
    }

    document.getElementById('nextBtn').addEventListener('click', () => goTo(current + 1));
    document.getElementById('prevBtn').addEventListener('click', () => goTo(current - 1));

    // Auto-advance every 5s
    let timer = setInterval(() => goTo(current + 1), 5000);
    document.getElementById('imagePanel').addEventListener('mouseenter', () => clearInterval(timer));
    document.getElementById('imagePanel').addEventListener('mouseleave', () => {
        timer = setInterval(() => goTo(current + 1), 5000);
    });

    // ── PASSWORD TOGGLE ──
    function togglePassword() {
        const inp = document.getElementById('pass');
        const icon = document.getElementById('eyeIcon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
            inp.type = 'password';
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    }
</script>


<!-- GoZayan Theme Toggle (floating) -->
<style>
.gz-float-theme {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    display: flex; align-items: center; gap: 7px;
    background: rgba(13,17,23,0.85);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 24px;
    padding: 8px 16px 8px 12px;
    cursor: pointer; font-family: inherit; font-size: 0.8rem; font-weight: 600;
    color: rgba(255,255,255,0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    transition: all .2s; white-space: nowrap;
}
.gz-float-theme:hover { background: rgba(13,17,23,0.95); border-color: rgba(255,255,255,0.3); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,0.5); }
.gz-float-theme .gz-theme-icon  { font-size: 1rem; line-height: 1; }
.gz-float-theme .gz-theme-label { line-height: 1; }
@media (max-width: 480px) { .gz-float-theme { bottom: 16px; right: 16px; padding: 7px 12px 7px 10px; } .gz-float-theme .gz-theme-label { display: none; } }
</style>
<button class="gz-float-theme gz-theme-btn" type="button" aria-label="Toggle dark mode">
    <span class="gz-theme-icon">🌙</span>
    <span class="gz-theme-label">Dark</span>
</button>
</body>
</html>
<!-- <?php include("../includes/footer.php"); ?> -->