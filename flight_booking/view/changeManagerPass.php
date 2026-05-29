<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    header("Location: /flight_booking/view/login.php"); exit;
}

$email = $_SESSION['email'];

// ── Handle POST (PRG) ────────────────────────────────────────
if (isset($_POST['save'])) {
    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password']     ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    $errors  = [];

    if (empty($current) || empty($new) || empty($confirm)) {
        $errors[] = "All three fields are required.";
    } elseif (strlen($new) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    } elseif ($new !== $confirm) {
        $errors[] = "New passwords do not match.";
    } elseif ($current === $new) {
        $errors[] = "New password must be different from the current one.";
    } else {
        $chk = $conn->prepare("SELECT password FROM login WHERE email = ?");
        $chk->bind_param("s", $email); $chk->execute();
        $row = $chk->get_result()->fetch_assoc(); $chk->close();
        $valid = $row && (password_verify($current, $row['password']) || $current === $row['password']);
        if (!$valid) $errors[] = "Current password is incorrect.";
    }

    if (empty($errors)) {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $u1 = $conn->prepare("UPDATE login SET password=? WHERE email=?");
        $u1->bind_param("ss", $hashed, $email); $u1->execute(); $u1->close();
        $u2 = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $u2->bind_param("ss", $hashed, $email); $u2->execute(); $u2->close();
        $_SESSION['mgr_pass_msg']      = "Password changed successfully!";
        $_SESSION['mgr_pass_msg_type'] = "success";
    } else {
        $_SESSION['mgr_pass_msg']      = implode(' ', $errors);
        $_SESSION['mgr_pass_msg_type'] = "error";
    }
    header("Location: /flight_booking/view/changeManagerPass.php"); exit;
}

$flash_msg  = $_SESSION['mgr_pass_msg']      ?? '';
$flash_type = $_SESSION['mgr_pass_msg_type'] ?? '';
unset($_SESSION['mgr_pass_msg'], $_SESSION['mgr_pass_msg_type']);

include("../includes/managerheader.php");
?>
<style>
.cmp-page { flex:1; padding:32px 32px 60px; max-width:860px; width:100%; margin:0 auto; }

.cmp-titlebar { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
.cmp-titlebar-icon { width:52px; height:52px; background:linear-gradient(135deg,#0b72e6,#6c3de8); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 4px 14px rgba(11,114,230,0.3); flex-shrink:0; }
.cmp-titlebar h1 { font-size:1.4rem; font-weight:800; color:#0f172a; letter-spacing:-0.4px; }
.cmp-titlebar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }

.cmp-flash { display:flex; align-items:center; gap:10px; padding:13px 18px; border-radius:12px; font-size:0.88rem; font-weight:600; margin-bottom:24px; animation:cmpFade 0.3s ease; }
@keyframes cmpFade { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.cmp-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.cmp-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.cmp-flash .cmp-close { margin-left:auto; cursor:pointer; opacity:0.5; font-size:1rem; background:none; border:none; color:inherit; padding:0; font-family:inherit; }

.cmp-layout { display:grid; grid-template-columns:260px 1fr; gap:24px; align-items:start; }

/* Tips card */
.cmp-tips-card { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,0.1); border:1px solid #e8f0fb; overflow:hidden; position:sticky; top:76px; }
.cmp-tips-banner { height:90px; background:linear-gradient(135deg,#0b72e6,#6c3de8); display:flex; align-items:center; justify-content:center; font-size:2.8rem; }
.cmp-tips-body { padding:20px; }
.cmp-tips-body h3 { font-size:0.95rem; font-weight:800; color:#0f172a; margin-bottom:4px; }
.cmp-tips-body p  { font-size:0.78rem; color:#64748b; line-height:1.55; margin-bottom:16px; }
.cmp-tip { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border-radius:10px; background:#f8fafc; border:1px solid #e8f0fb; font-size:0.8rem; margin-bottom:8px; }
.cmp-tip:last-child { margin-bottom:0; }
.cmp-tip .ti { font-size:0.95rem; flex-shrink:0; margin-top:1px; }
.cmp-tip .tt { color:#475569; line-height:1.45; }
.cmp-tip .tt b { color:#0f172a; font-weight:700; }

/* Form card */
.cmp-form-card { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,0.1); border:1px solid #e8f0fb; overflow:hidden; }
.cmp-form-header { background:linear-gradient(135deg,#0b72e6,#6c3de8); padding:20px 26px; display:flex; align-items:center; gap:12px; }
.cmp-form-header-icon { width:40px; height:40px; background:rgba(255,255,255,0.2); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.cmp-form-header h2 { color:#fff; font-size:1rem; font-weight:700; margin:0; }
.cmp-form-header span { color:rgba(255,255,255,0.72); font-size:0.76rem; display:block; margin-top:2px; }
.cmp-form-body { padding:28px; }

.cmp-section { font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin:22px 0 14px; display:flex; align-items:center; gap:8px; }
.cmp-section:first-child { margin-top:0; }
.cmp-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }
.cmp-divider { border:none; border-top:1px solid #f1f5f9; margin:22px 0; }

.cmp-field { margin-bottom:18px; }
.cmp-field label { display:block; font-size:0.74rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:6px; }
.cmp-field label .req { color:#e53e3e; margin-left:2px; }
.cmp-pw-wrap { position:relative; }
.cmp-pw-wrap input { width:100%; padding:11px 44px 11px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.9rem; color:#1e293b; background:#f8fafc; transition:border-color 0.2s, box-shadow 0.2s, background 0.2s; outline:none; font-family:inherit; }
.cmp-pw-wrap input:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.cmp-pw-wrap input::placeholder { color:#b0bec5; }
.cmp-eye { position:absolute; right:13px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#94a3b8; font-size:1rem; padding:4px; line-height:1; transition:color 0.15s; }
.cmp-eye:hover { color:#0b72e6; }

.cmp-strength-bar { height:3px; border-radius:3px; background:#e2e8f0; margin-top:7px; overflow:hidden; }
.cmp-strength-fill { height:100%; width:0; border-radius:3px; transition:width 0.3s, background 0.3s; }
.cmp-strength-text { font-size:0.72rem; margin-top:4px; color:#94a3b8; font-weight:600; }
.cmp-match { font-size:0.75rem; margin-top:5px; font-weight:600; display:none; }
.cmp-match.ok  { color:#15803d; display:block; }
.cmp-match.bad { color:#dc2626; display:block; }

.cmp-footer { display:flex; align-items:center; justify-content:flex-end; gap:12px; padding-top:22px; border-top:1px solid #f1f5f9; margin-top:8px; }
.cmp-btn-cancel { padding:11px 22px; border:1.5px solid #e2e8f0; border-radius:10px; background:#fff; color:#64748b; font-size:0.9rem; font-weight:600; cursor:pointer; font-family:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:7px; transition:all 0.15s; }
.cmp-btn-cancel:hover { border-color:#94a3b8; color:#334155; }
.cmp-btn-submit { padding:11px 28px; background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; border:none; border-radius:10px; font-size:0.95rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px; transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s; box-shadow:0 4px 14px rgba(11,114,230,0.3); font-family:inherit; }
.cmp-btn-submit:hover { opacity:0.9; transform:translateY(-1px); }
.cmp-btn-submit:active { transform:translateY(0); }

@media (max-width:800px) { .cmp-layout { grid-template-columns:1fr; } .cmp-tips-card { position:static; } }
@media (max-width:500px) { .cmp-page { padding:16px 14px 40px; } .cmp-footer { flex-direction:column-reverse; } .cmp-btn-cancel, .cmp-btn-submit { width:100%; justify-content:center; } }
</style>

<div class="cmp-page">
    <div class="cmp-titlebar">
        <div class="cmp-titlebar-icon">🔒</div>
        <div><h1>Change Password</h1><p>Update your manager account password</p></div>
    </div>

    <?php if ($flash_msg): ?>
        <div class="cmp-flash <?= htmlspecialchars($flash_type) ?>" id="cmpFlash">
            <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($flash_msg) ?>
            <button class="cmp-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div class="cmp-layout">
        <!-- Tips -->
        <div class="cmp-tips-card">
            <div class="cmp-tips-banner">🔐</div>
            <div class="cmp-tips-body">
                <h3>Password Security</h3>
                <p>Keep your manager account safe with a strong password.</p>
                <div class="cmp-tip"><span class="ti">✅</span><div class="tt"><b>6+ characters</b> minimum</div></div>
                <div class="cmp-tip"><span class="ti">✅</span><div class="tt">Mix <b>uppercase &amp; lowercase</b></div></div>
                <div class="cmp-tip"><span class="ti">✅</span><div class="tt">Include <b>numbers</b> and <b>symbols</b></div></div>
                <div class="cmp-tip"><span class="ti">🚫</span><div class="tt">Never reuse <b>old passwords</b></div></div>
                <div class="cmp-tip"><span class="ti">🚫</span><div class="tt">Don't share with <b>anyone</b></div></div>
            </div>
        </div>

        <!-- Form -->
        <div class="cmp-form-card">
            <div class="cmp-form-header">
                <div class="cmp-form-header-icon">🔑</div>
                <div><h2>Update Password</h2><span>Changing password for <?= htmlspecialchars($email) ?></span></div>
            </div>
            <div class="cmp-form-body">
                <form action="/flight_booking/view/changeManagerPass.php" method="POST" autocomplete="off">

                    <div class="cmp-section">Current Password</div>
                    <div class="cmp-field">
                        <label>Current Password <span class="req">*</span></label>
                        <div class="cmp-pw-wrap">
                            <input type="password" name="current_password" id="cmpCurrent" placeholder="Enter your current password" required>
                            <button type="button" class="cmp-eye" onclick="cmpToggle('cmpCurrent',this)">👁️</button>
                        </div>
                    </div>

                    <hr class="cmp-divider">
                    <div class="cmp-section">New Password</div>

                    <div class="cmp-field">
                        <label>New Password <span class="req">*</span></label>
                        <div class="cmp-pw-wrap">
                            <input type="password" name="new_password" id="cmpNew" placeholder="Create a strong new password" required oninput="cmpStrength(this.value); cmpMatchCheck()">
                            <button type="button" class="cmp-eye" onclick="cmpToggle('cmpNew',this)">👁️</button>
                        </div>
                        <div class="cmp-strength-bar"><div class="cmp-strength-fill" id="cmpStrFill"></div></div>
                        <div class="cmp-strength-text" id="cmpStrText"></div>
                    </div>

                    <div class="cmp-field">
                        <label>Confirm New Password <span class="req">*</span></label>
                        <div class="cmp-pw-wrap">
                            <input type="password" name="confirm_password" id="cmpConfirm" placeholder="Repeat your new password" required oninput="cmpMatchCheck()">
                            <button type="button" class="cmp-eye" onclick="cmpToggle('cmpConfirm',this)">👁️</button>
                        </div>
                        <div class="cmp-match" id="cmpMatch"></div>
                    </div>

                    <div class="cmp-footer">
                        <a href="/flight_booking/view/viewmanagerprofile.php" class="cmp-btn-cancel">✕ Cancel</a>
                        <button type="submit" name="save" class="cmp-btn-submit">🔒 Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function cmpToggle(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁️' : '🙈';
}
function cmpStrength(val) {
    const fill = document.getElementById('cmpStrFill');
    const text = document.getElementById('cmpStrText');
    if (!val) { fill.style.width='0'; text.textContent=''; return; }
    let s = 0;
    if (val.length >= 6)           s++;
    if (val.length >= 10)          s++;
    if (/[A-Z]/.test(val))         s++;
    if (/[0-9]/.test(val))         s++;
    if (/[^A-Za-z0-9]/.test(val))  s++;
    const lvl = [{w:'20%',bg:'#ef4444',t:'Very Weak'},{w:'40%',bg:'#f97316',t:'Weak'},{w:'60%',bg:'#eab308',t:'Fair'},{w:'80%',bg:'#3b82f6',t:'Strong'},{w:'100%',bg:'#10b981',t:'Very Strong'}][Math.min(s-1,4)] || {w:'20%',bg:'#ef4444',t:'Very Weak'};
    fill.style.width=lvl.w; fill.style.background=lvl.bg; text.textContent=lvl.t; text.style.color=lvl.bg;
}
function cmpMatchCheck() {
    const nv = document.getElementById('cmpNew').value;
    const cv = document.getElementById('cmpConfirm').value;
    const el = document.getElementById('cmpMatch');
    if (!cv) { el.className='cmp-match'; return; }
    if (nv === cv) { el.className='cmp-match ok'; el.textContent='✅ Passwords match'; }
    else           { el.className='cmp-match bad'; el.textContent='❌ Passwords do not match'; }
}
const cmpFlash = document.getElementById('cmpFlash');
if (cmpFlash) setTimeout(() => cmpFlash.remove(), 5000);
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
