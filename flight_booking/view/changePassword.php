<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}

$email   = $_SESSION['email'];
$message = '';
$msg_type = '';

$stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
$stmt->bind_param("s", $email); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();

$img_src = "https://ui-avatars.com/api/?name=".urlencode($user['name'])."&background=0b1f3a&color=d4a84b&size=200&bold=true";
if (!empty($user['image']) && file_exists(__DIR__."/uploads/".$user['image']))
    $img_src = "uploads/".htmlspecialchars($user['image']);

if (isset($_POST['change_password'])) {
    $current  = $_POST['current_password']  ?? '';
    $new_pass = $_POST['new_password']       ?? '';
    $confirm  = $_POST['confirm_password']   ?? '';
    $errors   = [];

    if (empty($current)||empty($new_pass)||empty($confirm)) $errors[] = "All fields are required.";
    elseif (strlen($new_pass) < 8) $errors[] = "New password must be at least 8 characters.";
    elseif ($new_pass !== $confirm) $errors[] = "New passwords do not match.";
    elseif ($current === $new_pass) $errors[] = "New password must differ from the current one.";
    else {
        $chk = $conn->prepare("SELECT password FROM login WHERE email = ?");
        $chk->bind_param("s", $email); $chk->execute();
        $row = $chk->get_result()->fetch_assoc(); $chk->close();
        $valid = $row && (password_verify($current, $row['password']) || $current === $row['password']);
        if (!$valid) $errors[] = "Current password is incorrect.";
    }

    if (empty($errors)) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $u1 = $conn->prepare("UPDATE login SET password = ? WHERE email = ?");
        $u1->bind_param("ss", $hashed, $email); $u1->execute(); $u1->close();
        $u2 = $conn->prepare("UPDATE webusers SET pass = ? WHERE email = ?");
        $u2->bind_param("ss", $hashed, $email); $u2->execute(); $u2->close();
        $message = "Password changed successfully!"; $msg_type = 'success';
    } else { $message = implode(' ', $errors); $msg_type = 'error'; }
}

include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GoZayan · Change Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,900;1,600;1,700&family=DM+Mono:wght@400;500;600&family=Mulish:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{
    --navy:#08172e;--navy-2:#0f2444;--navy-3:#172f56;--navy-4:#1e3d6e;
    --gold:#c9a84c;--gold-lt:#e0bc6a;--gold-dk:#a8893a;--gold-tint:rgba(201,168,76,.09);
    --cream:#f8f5f0;--cream-2:#f0ebe2;--cream-3:#e6dfd4;
    --ink:#0d1a28;--ink-2:#2e4057;--ink-3:#6b84a0;--ink-4:#9db3c8;
    --surface:#ffffff;--surface-2:#fdfcfa;
    --green:#0a8f6a;--green-lt:#12b585;--green-bg:#d0f5ea;
    --red:#c8293a;--red-bg:#fde8ea;
    --border:#e2d9cc;--border-2:#ede7de;
    --sh-xs:0 1px 3px rgba(8,23,46,.05);
    --sh-sm:0 2px 10px rgba(8,23,46,.07),0 1px 3px rgba(8,23,46,.04);
    --sh-md:0 6px 24px rgba(8,23,46,.09),0 2px 8px rgba(8,23,46,.05);
    --sh-lg:0 16px 48px rgba(8,23,46,.12),0 4px 16px rgba(8,23,46,.06);
    --serif:'Playfair Display',Georgia,serif;--sans:'Mulish',system-ui,sans-serif;--mono:'DM Mono','Courier New',monospace;
    --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--sans);background:var(--cream);color:var(--ink);min-height:100vh;-webkit-font-smoothing:antialiased;padding-top:62px}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--cream-2)}::-webkit-scrollbar-thumb{background:var(--cream-3);border-radius:4px}
@keyframes riseIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideInL{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:translateX(0)}}

.sub-header{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-3) 100%);padding:18px 40px;display:flex;align-items:center;gap:20px;border-bottom:1px solid rgba(255,255,255,.05);position:relative;overflow:hidden}
.sub-header::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 80% 50%,rgba(201,168,76,.08) 0%,transparent 60%);pointer-events:none}
.sh-icon{width:44px;height:44px;border-radius:12px;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.25);display:flex;align-items:center;justify-content:center;color:var(--gold-lt);font-size:1.15rem;flex-shrink:0}
.sh-text h2{font-family:var(--serif);font-size:1.15rem;font-weight:700;color:#fff;letter-spacing:-.01em;margin-bottom:2px}
.sh-text p{font-size:.75rem;color:rgba(255,255,255,.42);font-weight:500}
.sh-badge{margin-left:auto;font-family:var(--mono);font-size:.68rem;font-weight:500;color:var(--gold-lt);letter-spacing:.1em;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.25);padding:6px 18px;border-radius:30px;white-space:nowrap}

.back-bar{max-width:1340px;margin:0 auto;padding:16px 36px 0}
.back-link{display:inline-flex;align-items:center;gap:7px;font-size:.73rem;font-weight:700;color:var(--ink-3);text-decoration:none;letter-spacing:.07em;text-transform:uppercase;transition:color .18s}
.back-link:hover{color:var(--gold-dk)}

.page-wrap{max-width:1340px;margin:0 auto;padding:22px 36px 100px;display:grid;grid-template-columns:256px 1fr;gap:28px;align-items:start}

/* Sidebar */
.sidebar{display:flex;flex-direction:column;gap:12px;position:sticky;top:82px;animation:slideInL .5s .05s cubic-bezier(0.16,1,.3,1) both}
.sb-profile{background:linear-gradient(160deg,var(--navy-2) 0%,var(--navy-4) 100%);border-radius:var(--r-lg);padding:24px 20px 20px;text-align:center;border:1px solid rgba(255,255,255,.06);box-shadow:var(--sh-md);position:relative;overflow:hidden}
.sb-profile::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(201,168,76,.12) 0%,transparent 65%);pointer-events:none}
.sb-av-wrap{position:relative;display:inline-block;margin-bottom:14px}
.sb-av{width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(201,168,76,.5);box-shadow:0 0 0 5px rgba(201,168,76,.1),var(--sh-md)}
.sb-online{position:absolute;bottom:3px;right:3px;width:14px;height:14px;border-radius:50%;background:var(--green-lt);border:2.5px solid var(--navy-2);box-shadow:0 0 6px rgba(18,181,133,.5)}
.sb-name{font-size:.95rem;font-weight:800;color:#fff;margin-bottom:3px;letter-spacing:-.01em}
.sb-email{font-size:.68rem;color:rgba(255,255,255,.38);word-break:break-all;line-height:1.4}
.sb-badge{display:inline-flex;align-items:center;gap:5px;margin-top:12px;font-family:var(--mono);font-size:.6rem;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--gold-lt);background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.22);padding:4px 14px;border-radius:20px}
.sb-nav{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-sm);overflow:hidden;box-shadow:var(--sh-sm)}
.sb-nav-item{display:flex;align-items:center;gap:12px;padding:12px 16px;font-size:.85rem;font-weight:600;color:var(--ink-2);text-decoration:none;border-bottom:1px solid var(--border-2);transition:background .18s,color .18s,border-color .18s;position:relative;letter-spacing:.01em}
.sb-nav-item:last-child{border-bottom:none}
.sb-nav-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--gold);transform:scaleX(0);transform-origin:left;transition:transform .18s;border-radius:0 2px 2px 0}
.sb-nav-item:hover{background:var(--cream-2);color:var(--navy)}
.sb-nav-item:hover::before,.sb-nav-item.active::before{transform:scaleX(1)}
.sb-nav-item.active{background:rgba(201,168,76,.1);color:var(--navy);font-weight:800;border-left:3px solid var(--gold)}
.sb-nav-item i{width:18px;text-align:center;font-size:.84rem;color:var(--ink-3);flex-shrink:0;transition:color .18s}
.sb-nav-item:hover i,.sb-nav-item.active i{color:var(--gold)}
/* Search Flights — gold accent row */
.sb-nav-item.search-link{background:rgba(201,168,76,.07);color:var(--gold-dk);font-weight:700;border-left:3px solid rgba(201,168,76,.5)}
.sb-nav-item.search-link i{color:var(--gold-dk)}
.sb-nav-item.search-link:hover{background:rgba(201,168,76,.15);color:var(--navy);border-left-color:var(--gold)}
.sb-logout{display:flex;align-items:center;justify-content:center;gap:9px;padding:12px;background:transparent;border:1.5px solid var(--border);border-radius:var(--r-md);color:var(--ink-3);font-family:var(--sans);font-size:.82rem;font-weight:700;text-decoration:none;transition:all .2s}
.sb-logout:hover{border-color:var(--red);color:var(--red);background:var(--red-bg)}
</style>
<style>
/* Main column */
.main-col{min-width:0;animation:riseIn .5s .1s cubic-bezier(0.16,1,.3,1) both}
.cp-grid{display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start}

/* Security card */
.sec-card{background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border-2);overflow:hidden;box-shadow:var(--sh-md)}
.sec-banner{height:88px;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-3) 100%);display:flex;align-items:center;justify-content:center;font-size:2.8rem;position:relative;overflow:hidden}
.sec-banner::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--gold-dk),var(--gold-lt))}
.sec-body{padding:24px}
.sec-title{font-family:var(--serif);font-size:1.05rem;font-weight:700;color:var(--ink);margin-bottom:5px}
.sec-sub{font-size:.8rem;color:var(--ink-3);line-height:1.55;margin-bottom:22px}
.tip-list{display:flex;flex-direction:column;gap:9px}
.tip{display:flex;align-items:flex-start;gap:11px;padding:12px 14px;border-radius:var(--r-md);background:var(--cream-2);border:1px solid var(--border-2);font-size:.82rem;transition:border-color .2s}
.tip:hover{border-color:var(--border)}
.tip-icon{font-size:.95rem;flex-shrink:0;margin-top:1px}
.tip-text{color:var(--ink-2);line-height:1.45}
.tip-text b{color:var(--ink);font-weight:700}
.tip.good .tip-icon{color:var(--green)}
.tip.bad  .tip-icon{color:var(--red)}

/* Form card */
.form-card{background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border-2);overflow:hidden;box-shadow:var(--sh-md)}
.form-card-head{padding:22px 28px;border-bottom:1px solid var(--border-2);background:var(--surface-2);position:relative}
.form-card-head::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--navy),var(--gold))}
.form-card-head h3{font-family:var(--serif);font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:3px}
.form-card-head p{font-size:.78rem;color:var(--ink-3)}
.form-card-body{padding:30px}

/* Alert */
.alert{display:flex;align-items:flex-start;gap:11px;padding:14px 18px;border-radius:var(--r-md);font-size:.85rem;font-weight:500;margin-bottom:24px;line-height:1.5}
.alert i{font-size:1rem;flex-shrink:0;margin-top:1px}
.alert-success{background:var(--green-bg);border:1px solid rgba(10,143,106,.22);color:var(--green)}
.alert-error{background:var(--red-bg);border:1px solid rgba(200,41,58,.18);color:var(--red)}

/* Fields */
.field-group{margin-bottom:20px}
.field-group label{display:block;font-size:.7rem;font-weight:700;color:var(--ink-3);text-transform:uppercase;letter-spacing:.09em;margin-bottom:8px}
.field-wrap{position:relative}
.field-wrap input{width:100%;padding:13px 48px 13px 16px;border:1.5px solid var(--border);border-radius:var(--r-md);font-size:.93rem;font-family:var(--sans);color:var(--ink);background:var(--cream-2);transition:all .2s;outline:none}
.field-wrap input:focus{border-color:var(--gold);background:var(--surface);box-shadow:0 0 0 4px var(--gold-tint)}
.toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--ink-3);padding:0;display:flex;align-items:center;font-size:.95rem;transition:color .2s}
.toggle-pw:hover{color:var(--gold-dk)}

/* Strength bar */
.strength-wrap{margin-top:9px}
.strength-bar{height:5px;border-radius:5px;background:var(--border-2);overflow:hidden;margin-bottom:5px}
.strength-fill{height:100%;border-radius:5px;width:0;transition:width .35s,background .35s}
.strength-label{font-size:.72rem;color:var(--ink-3);font-weight:600;font-family:var(--mono)}

.field-divider{border:none;border-top:1px solid var(--border-2);margin:24px 0}

/* Buttons */
.btn-row{display:flex;gap:12px;margin-top:26px}
.btn-save{flex:1;padding:14px;background:var(--navy);color:#fff;border:none;border-radius:var(--r-md);font-size:.93rem;font-weight:700;font-family:var(--sans);cursor:pointer;box-shadow:var(--sh-sm);transition:all .22s;letter-spacing:.02em}
.btn-save:hover{transform:translateY(-2px);background:var(--navy-2);box-shadow:var(--sh-md)}
.btn-cancel{padding:14px 26px;background:var(--surface);color:var(--ink-2);border:1.5px solid var(--border);border-radius:var(--r-md);font-size:.93rem;font-weight:600;font-family:var(--sans);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:all .22s}
.btn-cancel:hover{border-color:var(--gold);color:var(--gold-dk);background:var(--gold-tint)}

@media(max-width:1100px){.page-wrap{grid-template-columns:1fr}.sidebar{position:static}}
@media(max-width:900px){.cp-grid{grid-template-columns:1fr}}
@media(max-width:780px){.page-wrap{padding:18px 16px 80px}.sub-header{padding:14px 20px}}
</style>
</head>
<body>

<div class="sub-header">
    <div class="sh-icon"><i class="fas fa-lock"></i></div>
    <div class="sh-text">
        <h2>Change Password</h2>
        <p>Keep your account secure with a strong password</p>
    </div>
    <div class="sh-badge">✈ GoZayan Traveller</div>
</div>

<div class="back-bar">
    <a href="userhome.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="page-wrap">
    <aside class="sidebar">
        <div class="sb-profile">
            <div class="sb-av-wrap">
                <img class="sb-av" src="<?= $img_src ?>" alt="<?= htmlspecialchars($user['name']) ?>">
                <div class="sb-online"></div>
            </div>
            <div class="sb-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="sb-email"><?= htmlspecialchars($user['email']) ?></div>
            <div class="sb-badge"><i class="fas fa-star" style="font-size:.55rem"></i> GoZayan Traveller</div>
        </div>
        <nav class="sb-nav">
            <a href="userhome.php"         class="sb-nav-item"><i class="fas fa-house"></i> Dashboard</a>
            <a href="searchflights.php"    class="sb-nav-item search-link"><i class="fas fa-magnifying-glass"></i> Search Flights</a>
            <a href="myBookings.php"       class="sb-nav-item"><i class="fas fa-ticket"></i> My Bookings</a>
            <a href="passengerProfile.php" class="sb-nav-item"><i class="fas fa-user"></i> My Profile</a>
            <a href="changePassword.php"   class="sb-nav-item active"><i class="fas fa-lock"></i> Change Password</a>
        </nav>
        <a href="/flight_booking/logout.php" class="sb-logout"><i class="fas fa-right-from-bracket"></i> Sign Out</a>
    </aside>

    <div class="main-col">
        <div class="cp-grid">

            <!-- Security tips card -->
            <div class="sec-card">
                <div class="sec-banner">🔐</div>
                <div class="sec-body">
                    <div class="sec-title">Password Security</div>
                    <div class="sec-sub">Keep your account safe with a strong, unique password.</div>
                    <div class="tip-list">
                        <div class="tip good"><span class="tip-icon"><i class="fas fa-circle-check"></i></span><div class="tip-text"><b>At least 8 characters</b> long</div></div>
                        <div class="tip good"><span class="tip-icon"><i class="fas fa-circle-check"></i></span><div class="tip-text">Mix <b>uppercase &amp; lowercase</b> letters</div></div>
                        <div class="tip good"><span class="tip-icon"><i class="fas fa-circle-check"></i></span><div class="tip-text">Include <b>numbers</b> and <b>symbols</b></div></div>
                        <div class="tip bad"><span class="tip-icon"><i class="fas fa-circle-xmark"></i></span><div class="tip-text">Don't reuse <b>old passwords</b></div></div>
                        <div class="tip bad"><span class="tip-icon"><i class="fas fa-circle-xmark"></i></span><div class="tip-text">Avoid <b>personal info</b> like your name or birthday</div></div>
                    </div>
                </div>
            </div>

            <!-- Form card -->
            <div class="form-card">
                <div class="form-card-head">
                    <h3>Update Your Password</h3>
                    <p>Enter your current password, then choose a new one</p>
                </div>
                <div class="form-card-body">
                    <?php if ($message): ?>
                    <div class="alert alert-<?= $msg_type ?>">
                        <i class="fas fa-<?= $msg_type==='success'?'circle-check':'circle-xmark' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                    <?php endif; ?>

                    <form action="" method="POST" autocomplete="off">
                        <div class="field-group">
                            <label>Current Password</label>
                            <div class="field-wrap">
                                <input type="password" name="current_password" id="currentPw" placeholder="Enter your current password" required>
                                <button type="button" class="toggle-pw" onclick="togglePw('currentPw',this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>

                        <hr class="field-divider">

                        <div class="field-group">
                            <label>New Password</label>
                            <div class="field-wrap">
                                <input type="password" name="new_password" id="newPw" placeholder="Create a new password" required oninput="checkStrength(this.value)">
                                <button type="button" class="toggle-pw" onclick="togglePw('newPw',this)"><i class="fas fa-eye"></i></button>
                            </div>
                            <div class="strength-wrap">
                                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                                <div class="strength-label" id="strengthLabel"></div>
                            </div>
                        </div>

                        <div class="field-group">
                            <label>Confirm New Password</label>
                            <div class="field-wrap">
                                <input type="password" name="confirm_password" id="confirmPw" placeholder="Repeat your new password" required>
                                <button type="button" class="toggle-pw" onclick="togglePw('confirmPw',this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>

                        <div class="btn-row">
                            <button type="submit" name="change_password" class="btn-save"><i class="fas fa-lock"></i> Update Password</button>
                            <a href="userhome.php" class="btn-cancel">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function togglePw(inputId, btn) {
    const inp = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    if (!val) { fill.style.width='0'; label.textContent=''; return; }
    let score = 0;
    if (val.length >= 8)          score++;
    if (val.length >= 12)         score++;
    if (/[A-Z]/.test(val))        score++;
    if (/[0-9]/.test(val))        score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {pct:'20%',color:'#c8293a',text:'Very Weak'},
        {pct:'40%',color:'#e07b39',text:'Weak'},
        {pct:'60%',color:'#d4a017',text:'Fair'},
        {pct:'80%',color:'#2563eb',text:'Strong'},
        {pct:'100%',color:'#0a8f6a',text:'Very Strong'},
    ];
    const lvl = levels[Math.min(score-1,4)] || levels[0];
    fill.style.width = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent = lvl.text;
    label.style.color = lvl.color;
}
</script>
</body>
</html>
<?php include("../includes/footer.php"); ?>
