<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}

$email   = $_SESSION['email'];
$message = '';
$msg_type = '';

// Fetch user
$stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$img_src = "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=1a6ff4&color=fff&size=200";
if (!empty($user['image']) && file_exists(__DIR__ . "/uploads/" . $user['image'])) {
    $img_src = "uploads/" . htmlspecialchars($user['image']);
}

// Handle form submission
if (isset($_POST['change_password'])) {
    $current  = $_POST['current_password']  ?? '';
    $new_pass = $_POST['new_password']       ?? '';
    $confirm  = $_POST['confirm_password']   ?? '';
    $errors   = [];

    if (empty($current) || empty($new_pass) || empty($confirm)) {
        $errors[] = "All fields are required.";
    } elseif (strlen($new_pass) < 8) {
        $errors[] = "New password must be at least 8 characters.";
    } elseif ($new_pass !== $confirm) {
        $errors[] = "New passwords do not match.";
    } elseif ($current === $new_pass) {
        $errors[] = "New password must be different from the current password.";
    } else {
        // Verify current password against both tables
        $chk = $conn->prepare("SELECT password FROM login WHERE email = ?");
        $chk->bind_param("s", $email);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        $valid = $row && (password_verify($current, $row['password']) || $current === $row['password']);

        if (!$valid) {
            $errors[] = "Current password is incorrect.";
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);

        // Update login table
        $u1 = $conn->prepare("UPDATE login SET password = ? WHERE email = ?");
        $u1->bind_param("ss", $hashed, $email);
        $u1->execute(); $u1->close();

        // Update webusers table (pass column)
        $u2 = $conn->prepare("UPDATE webusers SET pass = ? WHERE email = ?");
        $u2->bind_param("ss", $hashed, $email);
        $u2->execute(); $u2->close();

        $message  = "Password changed successfully! Please use your new password next time you log in.";
        $msg_type = 'success';
    } else {
        $message  = implode(' ', $errors);
        $msg_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Change Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:#1a6ff4; --primary-dark:#0d4fc4; --primary-glow:rgba(26,111,244,0.18);
            --secondary:#0a2d6e; --accent:#06c8a0; --dark:#0d1f35; --mid:#3d5a7a;
            --muted:#7a95b0; --border:#dce8f5; --surface:#ffffff; --bg:#f0f4fb;
            --sidebar-w:260px;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--dark);
             min-height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased}

        .dashboard{display:flex;flex:1}

        /* ══ SIDEBAR ══ */
        .sidebar{width:var(--sidebar-w);flex-shrink:0;
            background:linear-gradient(180deg,var(--secondary) 0%,#0d1f35 100%);
            display:flex;flex-direction:column;
            position:sticky;top:0;height:100vh;overflow-y:auto;z-index:100}
        .sidebar-brand{padding:28px 24px 20px;font-size:1.4rem;font-weight:900;
            color:#fff;letter-spacing:-0.5px;border-bottom:1px solid rgba(255,255,255,0.08)}
        .sidebar-brand a{text-decoration:none;color:inherit}
        .sidebar-brand span{color:#60a5fa}
        .sidebar-profile{padding:22px 20px;display:flex;flex-direction:column;
            align-items:center;gap:8px;border-bottom:1px solid rgba(255,255,255,0.08)}
        .profile-avatar{width:64px;height:64px;border-radius:50%;object-fit:cover;
            border:3px solid rgba(255,255,255,0.2)}
        .profile-name{font-size:0.9rem;font-weight:700;color:#fff;text-align:center}
        .profile-email{font-size:0.72rem;color:rgba(255,255,255,0.4);text-align:center;word-break:break-all}
        .sidebar-nav{padding:16px 12px;flex:1}
        .nav-label{font-size:0.65rem;font-weight:700;color:rgba(255,255,255,0.3);
            text-transform:uppercase;letter-spacing:1.2px;padding:0 12px;margin:14px 0 6px}
        .nav-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;
            text-decoration:none;color:rgba(255,255,255,0.6);font-size:0.88rem;font-weight:500;
            transition:all 0.2s;margin-bottom:2px}
        .nav-item:hover{background:rgba(255,255,255,0.08);color:#fff}
        .nav-item.active{background:rgba(26,111,244,0.25);color:#fff;font-weight:600}
        .nav-icon{font-size:1.1rem;width:22px;text-align:center}
        .sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.08)}
        .logout-btn{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;
            text-decoration:none;color:rgba(255,100,100,0.8);font-size:0.88rem;font-weight:600;transition:all 0.2s}
        .logout-btn:hover{background:rgba(239,68,68,0.12);color:#fca5a5}

        /* ══ MAIN ══ */
        .main{flex:1;display:flex;flex-direction:column;min-width:0}
        .topbar{background:var(--surface);border-bottom:1px solid var(--border);
            padding:16px 32px;display:flex;align-items:center;
            justify-content:space-between;position:sticky;top:0;z-index:10}
        .topbar-title{font-size:1rem;font-weight:800;color:var(--dark)}
        .topbar-back{font-size:0.85rem;font-weight:600;color:var(--muted);
            text-decoration:none;transition:color 0.2s}
        .topbar-back:hover{color:var(--primary)}

        /* ══ PAGE CONTENT ══ */
        .page-content{padding:40px 32px 60px;flex:1;
            display:flex;align-items:flex-start;justify-content:center}

        /* Two-column wrapper */
        .cp-grid{display:grid;grid-template-columns:280px 1fr;gap:24px;
            width:100%;max-width:860px;align-items:start}

        /* ── LEFT: Security card ── */
        .sec-card{background:var(--surface);border-radius:20px;
            border:1px solid var(--border);overflow:hidden;
            box-shadow:0 4px 20px rgba(13,31,53,0.07)}
        .sec-card-banner{height:80px;
            background:linear-gradient(135deg,var(--secondary) 0%,var(--primary) 100%);
            display:flex;align-items:center;justify-content:center;font-size:2.5rem}
        .sec-card-body{padding:22px 22px 24px}
        .sec-card-title{font-size:1rem;font-weight:800;color:var(--dark);margin-bottom:4px}
        .sec-card-sub{font-size:0.8rem;color:var(--muted);line-height:1.55;margin-bottom:20px}

        .tip-list{display:flex;flex-direction:column;gap:10px}
        .tip{display:flex;align-items:flex-start;gap:10px;
            padding:11px 13px;border-radius:11px;
            background:var(--bg);border:1px solid var(--border);font-size:0.82rem}
        .tip-icon{font-size:1rem;flex-shrink:0;margin-top:1px}
        .tip-text{color:var(--mid);line-height:1.45}
        .tip-text b{color:var(--dark);font-weight:700}

        /* ── RIGHT: Form card ── */
        .form-card{background:var(--surface);border-radius:20px;
            border:1px solid var(--border);overflow:hidden;
            box-shadow:0 4px 20px rgba(13,31,53,0.07)}
        .form-card-head{padding:20px 28px;border-bottom:1px solid var(--border)}
        .form-card-head h3{font-size:1rem;font-weight:800;color:var(--dark);margin-bottom:2px}
        .form-card-head p{font-size:0.8rem;color:var(--muted)}
        .form-card-body{padding:28px}

        /* Alert */
        .alert{display:flex;align-items:flex-start;gap:10px;padding:13px 16px;
            border-radius:12px;font-size:0.85rem;font-weight:500;margin-bottom:22px;line-height:1.5}
        .alert-success{background:rgba(6,200,160,0.08);border:1px solid rgba(6,200,160,0.25);color:#047857}
        .alert-error{background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.22);color:#dc2626}

        /* Fields */
        .field-group{margin-bottom:18px}
        .field-group label{display:block;font-size:0.72rem;font-weight:700;
            color:var(--muted);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:7px}
        .field-wrap{position:relative}
        .field-wrap input{width:100%;padding:12px 44px 12px 16px;
            border:1.5px solid var(--border);border-radius:11px;
            font-size:0.93rem;font-family:'Inter',sans-serif;
            color:var(--dark);background:#f8fbff;transition:all 0.2s;outline:none}
        .field-wrap input:focus{border-color:var(--primary);background:#fff;
            box-shadow:0 0 0 3.5px rgba(26,111,244,0.12)}
        .toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);
            background:none;border:none;cursor:pointer;color:var(--muted);
            padding:0;display:flex;align-items:center;transition:color 0.2s}
        .toggle-pw:hover{color:var(--primary)}

        /* Strength bar */
        .strength-wrap{margin-top:8px}
        .strength-bar{height:4px;border-radius:4px;background:var(--border);overflow:hidden;margin-bottom:4px}
        .strength-fill{height:100%;border-radius:4px;width:0;transition:width 0.3s,background 0.3s}
        .strength-label{font-size:0.72rem;color:var(--muted);font-weight:600}

        /* Divider */
        .field-divider{border:none;border-top:1px solid var(--border);margin:22px 0}

        /* Buttons */
        .btn-row{display:flex;gap:12px;margin-top:24px}
        .btn-save{flex:1;padding:13px;
            background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            color:#fff;border:none;border-radius:12px;font-size:0.93rem;font-weight:700;
            font-family:'Inter',sans-serif;cursor:pointer;
            box-shadow:0 4px 14px var(--primary-glow);transition:all 0.22s}
        .btn-save:hover{transform:translateY(-2px);filter:brightness(1.06)}
        .btn-save:active{transform:translateY(0)}
        .btn-cancel{padding:13px 24px;background:var(--surface);color:var(--mid);
            border:1.5px solid var(--border);border-radius:12px;font-size:0.93rem;
            font-weight:600;font-family:'Inter',sans-serif;
            text-decoration:none;display:inline-flex;align-items:center;
            justify-content:center;transition:all 0.22s}
        .btn-cancel:hover{border-color:var(--primary);color:var(--primary)}

        /* Responsive */
        @media(max-width:768px){
            .sidebar{display:none}
            .page-content{padding:20px 16px 50px}
            .topbar{padding:14px 16px}
            .cp-grid{grid-template-columns:1fr}
        }

        /* ══ FOOTER STYLING ══ */
        footer {
            background: linear-gradient(135deg, var(--secondary) 0%, #0d1f35 100%);
            color: rgba(255, 255, 255, 0.75);
            padding: 36px 32px;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            text-align: center;
        }
        .footer-container p {
            font-size: 0.88rem;
            line-height: 1.6;
        }
        .footer-container a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .footer-container a:hover {
            color: #93c5fd;
            text-decoration: underline;
        }
        .social-icons {
            display: flex;
            gap: 12px;
            margin: 4px 0;
        }
        .social-icons a {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff !important;
            font-size: 1rem;
            transition: all 0.25s;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .social-icons a:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 111, 244, 0.35);
        }
        .contact-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.55);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 14px;
            width: 100%;
            max-width: 600px;
        }
        .contact-info span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand"><a href="home.php">Go<span>Zayan</span></a></div>
        <div class="sidebar-profile">
            <img class="profile-avatar" src="<?= $img_src ?>" alt="">
            <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="userhome.php"      class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="searchflights.php" class="nav-item"><span class="nav-icon">🔍</span> Search Flights</a>
            <a href="myBookings.php"    class="nav-item"><span class="nav-icon">🎫</span> My Bookings</a>
            <div class="nav-label">Account</div>
            <a href="passengerProfile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
            <a href="changePassword.php"   class="nav-item active"><span class="nav-icon">🔒</span> Change Password</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main">
        <div class="topbar">
            <div class="topbar-title">🔒 Change Password</div>
            <a href="userhome.php" class="topbar-back">← Back to Dashboard</a>
        </div>

        <div class="page-content">
            <div class="cp-grid">

                <!-- LEFT: Security tips -->
                <div class="sec-card">
                    <div class="sec-card-banner">🔐</div>
                    <div class="sec-card-body">
                        <div class="sec-card-title">Password Security</div>
                        <div class="sec-card-sub">Keep your account safe with a strong, unique password.</div>
                        <div class="tip-list">
                            <div class="tip">
                                <span class="tip-icon">✅</span>
                                <div class="tip-text"><b>At least 8 characters</b> long</div>
                            </div>
                            <div class="tip">
                                <span class="tip-icon">✅</span>
                                <div class="tip-text">Mix <b>uppercase & lowercase</b> letters</div>
                            </div>
                            <div class="tip">
                                <span class="tip-icon">✅</span>
                                <div class="tip-text">Include <b>numbers</b> and <b>symbols</b></div>
                            </div>
                            <div class="tip">
                                <span class="tip-icon">🚫</span>
                                <div class="tip-text">Don't reuse <b>old passwords</b></div>
                            </div>
                            <div class="tip">
                                <span class="tip-icon">🚫</span>
                                <div class="tip-text">Avoid <b>personal info</b> like your name or birthday</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Form -->
                <div class="form-card">
                    <div class="form-card-head">
                        <h3>Update Your Password</h3>
                        <p>Enter your current password, then choose a new one</p>
                    </div>
                    <div class="form-card-body">

                        <?php if ($message): ?>
                        <div class="alert alert-<?= $msg_type ?>">
                            <?= $msg_type === 'success' ? '✅' : '❌' ?>
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>

                        <form action="" method="POST" autocomplete="off">

                            <div class="field-group">
                                <label>Current Password</label>
                                <div class="field-wrap">
                                    <input type="password" name="current_password"
                                           id="currentPw" placeholder="Enter your current password" required>
                                    <button type="button" class="toggle-pw"
                                            onclick="togglePw('currentPw','eye0')">
                                        <svg id="eye0" width="17" height="17" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <hr class="field-divider">

                            <div class="field-group">
                                <label>New Password</label>
                                <div class="field-wrap">
                                    <input type="password" name="new_password"
                                           id="newPw" placeholder="Create a new password"
                                           required oninput="checkStrength(this.value)">
                                    <button type="button" class="toggle-pw"
                                            onclick="togglePw('newPw','eye1')">
                                        <svg id="eye1" width="17" height="17" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="strength-wrap">
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <div class="strength-label" id="strengthLabel"></div>
                                </div>
                            </div>

                            <div class="field-group">
                                <label>Confirm New Password</label>
                                <div class="field-wrap">
                                    <input type="password" name="confirm_password"
                                           id="confirmPw" placeholder="Repeat your new password" required>
                                    <button type="button" class="toggle-pw"
                                            onclick="togglePw('confirmPw','eye2')">
                                        <svg id="eye2" width="17" height="17" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="btn-row">
                                <button type="submit" name="change_password" class="btn-save">
                                    🔒 Update Password
                                </button>
                                <a href="userhome.php" class="btn-cancel">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div><!-- /cp-grid -->
        </div><!-- /page-content -->
    </div><!-- /main -->
</div><!-- /dashboard -->

<script>
// Toggle password visibility
function togglePw(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        inp.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}

// Password strength meter
function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    if (!val) { fill.style.width = '0'; label.textContent = ''; return; }

    let score = 0;
    if (val.length >= 8)              score++;
    if (val.length >= 12)             score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))     score++;

    const levels = [
        { pct: '20%', color: '#ef4444', text: 'Very Weak' },
        { pct: '40%', color: '#f97316', text: 'Weak' },
        { pct: '60%', color: '#f59e0b', text: 'Fair' },
        { pct: '80%', color: '#3b82f6', text: 'Strong' },
        { pct: '100%',color: '#06c8a0', text: 'Very Strong' },
    ];
    const lvl = levels[Math.min(score - 1, 4)] || levels[0];
    fill.style.width    = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent   = lvl.text;
    label.style.color   = lvl.color;
}
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
