<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once("../model/db_conn.php");

// Guard — admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /flight_booking/view/login.php"); exit;
}

$email = $_SESSION['email'] ?? '';

// ── Handle POST (PRG) ────────────────────────────────────────
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $errors  = [];

    if (empty($current) || empty($new) || empty($confirm)) {
        $errors[] = "All three fields are required.";
    } elseif (strlen($new) < 8) {
        $errors[] = "New password must be at least 8 characters.";
    } elseif ($new !== $confirm) {
        $errors[] = "New passwords do not match.";
    } elseif ($current === $new) {
        $errors[] = "New password must be different from the current one.";
    } else {
        // Verify current password
        $chk = $conn->prepare("SELECT password FROM login WHERE email = ?");
        $chk->bind_param("s", $email);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        $valid = $row && (
            password_verify($current, $row['password']) ||
            $current === $row['password']   // plain-text fallback for legacy rows
        );

        if (!$valid) {
            $errors[] = "Current password is incorrect.";
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($new, PASSWORD_DEFAULT);

        // Update login table
        $u1 = $conn->prepare("UPDATE login SET password = ? WHERE email = ?");
        $u1->bind_param("ss", $hashed, $email);
        $u1->execute(); $u1->close();

        // Update users/admininfo table if it has a password column
        $u2 = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $u2->bind_param("ss", $hashed, $email);
        $u2->execute(); $u2->close();

        $_SESSION['acp_msg']      = "Password changed successfully!";
        $_SESSION['acp_msg_type'] = "success";
    } else {
        $_SESSION['acp_msg']      = implode(' ', $errors);
        $_SESSION['acp_msg_type'] = "error";
    }

    header("Location: /flight_booking/view/adminChangePassword.php"); exit;
}

// Consume flash
$flash_msg  = $_SESSION['acp_msg']      ?? '';
$flash_type = $_SESSION['acp_msg_type'] ?? '';
unset($_SESSION['acp_msg'], $_SESSION['acp_msg_type']);

include("../includes/adminheader.php");
?>
<style>
/* ── Page ── */
.acp-page {
    flex: 1;
    padding: 32px 32px 60px;
    max-width: 860px;
    width: 100%;
    margin: 0 auto;
}

/* ── Title bar ── */
.acp-titlebar { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
.acp-titlebar-icon {
    width:52px; height:52px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    border-radius:14px; display:flex; align-items:center;
    justify-content:center; font-size:1.5rem;
    box-shadow:0 4px 14px rgba(11,114,230,0.3); flex-shrink:0;
}
.acp-titlebar h1 { font-size:1.4rem; font-weight:800; color:#0f172a; letter-spacing:-0.4px; }
.acp-titlebar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }

/* ── Flash ── */
.acp-flash {
    display:flex; align-items:center; gap:10px;
    padding:13px 18px; border-radius:12px;
    font-size:0.88rem; font-weight:600;
    margin-bottom:24px; animation:acpFade 0.3s ease;
}
@keyframes acpFade { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.acp-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.acp-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.acp-flash .acp-close {
    margin-left:auto; cursor:pointer; opacity:0.5; font-size:1rem;
    background:none; border:none; color:inherit; padding:0; font-family:inherit;
}
.acp-flash .acp-close:hover { opacity:1; }

/* ── Two-column layout ── */
.acp-layout {
    display:grid;
    grid-template-columns:260px 1fr;
    gap:24px;
    align-items:start;
}

/* ── Left: Tips card ── */
.acp-tips-card {
    background:#fff; border-radius:20px;
    box-shadow:0 4px 24px rgba(11,114,230,0.1);
    border:1px solid #e8f0fb; overflow:hidden;
    position:sticky; top:76px;
}
.acp-tips-banner {
    height:90px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    display:flex; align-items:center; justify-content:center;
    font-size:2.8rem;
}
.acp-tips-body { padding:20px; }
.acp-tips-body h3 { font-size:0.95rem; font-weight:800; color:#0f172a; margin-bottom:4px; }
.acp-tips-body p  { font-size:0.78rem; color:#64748b; line-height:1.55; margin-bottom:16px; }
.acp-tip {
    display:flex; align-items:flex-start; gap:10px;
    padding:10px 12px; border-radius:10px;
    background:#f8fafc; border:1px solid #e8f0fb;
    font-size:0.8rem; margin-bottom:8px;
}
.acp-tip:last-child { margin-bottom:0; }
.acp-tip .ti { font-size:0.95rem; flex-shrink:0; margin-top:1px; }
.acp-tip .tt { color:#475569; line-height:1.45; }
.acp-tip .tt b { color:#0f172a; font-weight:700; }

/* ── Right: Form card ── */
.acp-form-card {
    background:#fff; border-radius:20px;
    box-shadow:0 4px 24px rgba(11,114,230,0.1);
    border:1px solid #e8f0fb; overflow:hidden;
}
.acp-form-header {
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    padding:20px 26px; display:flex; align-items:center; gap:12px;
}
.acp-form-header-icon {
    width:40px; height:40px; background:rgba(255,255,255,0.2);
    border-radius:10px; display:flex; align-items:center;
    justify-content:center; font-size:1.2rem; flex-shrink:0;
}
.acp-form-header h2 { color:#fff; font-size:1rem; font-weight:700; margin:0; }
.acp-form-header span { color:rgba(255,255,255,0.72); font-size:0.76rem; display:block; margin-top:2px; }
.acp-form-body { padding:28px; }

/* Section label */
.acp-section {
    font-size:0.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:1px; color:#94a3b8;
    margin:22px 0 14px; display:flex; align-items:center; gap:8px;
}
.acp-section:first-child { margin-top:0; }
.acp-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }

/* Divider */
.acp-divider { border:none; border-top:1px solid #f1f5f9; margin:22px 0; }

/* Field */
.acp-field { margin-bottom:18px; }
.acp-field label {
    display:block; font-size:0.74rem; font-weight:700;
    color:#475569; text-transform:uppercase;
    letter-spacing:0.6px; margin-bottom:6px;
}
.acp-field label .req { color:#e53e3e; margin-left:2px; }

/* Input wrap with eye toggle */
.acp-pw-wrap { position:relative; }
.acp-pw-wrap input {
    width:100%; padding:11px 44px 11px 14px;
    border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:0.9rem; color:#1e293b; background:#f8fafc;
    transition:border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline:none; font-family:inherit;
}
.acp-pw-wrap input:focus {
    border-color:#0b72e6; background:#fff;
    box-shadow:0 0 0 3px rgba(11,114,230,0.1);
}
.acp-pw-wrap input::placeholder { color:#b0bec5; }
.acp-eye {
    position:absolute; right:13px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:#94a3b8;
    font-size:1rem; padding:4px; line-height:1; transition:color 0.15s;
}
.acp-eye:hover { color:#0b72e6; }

/* Strength bar */
.acp-strength-bar { height:3px; border-radius:3px; background:#e2e8f0; margin-top:7px; overflow:hidden; }
.acp-strength-fill { height:100%; width:0; border-radius:3px; transition:width 0.3s, background 0.3s; }
.acp-strength-text { font-size:0.72rem; margin-top:4px; color:#94a3b8; font-weight:600; }

/* Match indicator */
.acp-match {
    font-size:0.75rem; margin-top:5px; font-weight:600;
    display:none;
}
.acp-match.ok  { color:#15803d; display:block; }
.acp-match.bad { color:#dc2626; display:block; }

/* Footer */
.acp-footer {
    display:flex; align-items:center; justify-content:flex-end; gap:12px;
    padding-top:22px; border-top:1px solid #f1f5f9; margin-top:8px;
}
.acp-btn-cancel {
    padding:11px 22px; border:1.5px solid #e2e8f0; border-radius:10px;
    background:#fff; color:#64748b; font-size:0.9rem; font-weight:600;
    cursor:pointer; font-family:inherit; text-decoration:none;
    display:inline-flex; align-items:center; gap:7px; transition:all 0.15s;
}
.acp-btn-cancel:hover { border-color:#94a3b8; color:#334155; }
.acp-btn-submit {
    padding:11px 28px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    color:#fff; border:none; border-radius:10px;
    font-size:0.95rem; font-weight:700; cursor:pointer;
    display:flex; align-items:center; gap:8px;
    transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow:0 4px 14px rgba(11,114,230,0.3); font-family:inherit;
}
.acp-btn-submit:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 6px 20px rgba(11,114,230,0.38); }
.acp-btn-submit:active { transform:translateY(0); }

/* Responsive */
@media (max-width:800px) {
    .acp-layout { grid-template-columns:1fr; }
    .acp-tips-card { position:static; }
}
@media (max-width:500px) {
    .acp-page { padding:16px 14px 40px; }
    .acp-footer { flex-direction:column-reverse; }
    .acp-btn-cancel, .acp-btn-submit { width:100%; justify-content:center; }
}
</style>

<div class="acp-page">

    <!-- Title -->
    <div class="acp-titlebar">
        <div class="acp-titlebar-icon">🔒</div>
        <div>
            <h1>Change Password</h1>
            <p>Update your administrator account password</p>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash_msg): ?>
        <div class="acp-flash <?= htmlspecialchars($flash_type) ?>" id="acpFlash">
            <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($flash_msg) ?>
            <button class="acp-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div class="acp-layout">

        <!-- ── Left: Security tips ── -->
        <div class="acp-tips-card">
            <div class="acp-tips-banner">🔐</div>
            <div class="acp-tips-body">
                <h3>Password Security</h3>
                <p>Keep your admin account safe with a strong, unique password.</p>

                <div class="acp-tip">
                    <span class="ti">✅</span>
                    <div class="tt"><b>8+ characters</b> minimum length</div>
                </div>
                <div class="acp-tip">
                    <span class="ti">✅</span>
                    <div class="tt">Mix <b>uppercase &amp; lowercase</b> letters</div>
                </div>
                <div class="acp-tip">
                    <span class="ti">✅</span>
                    <div class="tt">Include <b>numbers</b> and <b>symbols</b></div>
                </div>
                <div class="acp-tip">
                    <span class="ti">🚫</span>
                    <div class="tt">Never reuse <b>old passwords</b></div>
                </div>
                <div class="acp-tip">
                    <span class="ti">🚫</span>
                    <div class="tt">Avoid <b>personal info</b> like your name or birthday</div>
                </div>
                <div class="acp-tip">
                    <span class="ti">🚫</span>
                    <div class="tt">Don't share your password with <b>anyone</b></div>
                </div>
            </div>
        </div>

        <!-- ── Right: Form ── -->
        <div class="acp-form-card">
            <div class="acp-form-header">
                <div class="acp-form-header-icon">🔑</div>
                <div>
                    <h2>Update Password</h2>
                    <span>Changing password for <?= htmlspecialchars($email) ?></span>
                </div>
            </div>
            <div class="acp-form-body">
                <form action="/flight_booking/view/adminChangePassword.php"
                      method="POST" autocomplete="off">

                    <!-- Current password -->
                    <div class="acp-section">Current Password</div>

                    <div class="acp-field">
                        <label>Current Password <span class="req">*</span></label>
                        <div class="acp-pw-wrap">
                            <input type="password" name="current_password" id="acpCurrent"
                                   placeholder="Enter your current password" required>
                            <button type="button" class="acp-eye"
                                    onclick="acpToggle('acpCurrent', this)">👁️</button>
                        </div>
                    </div>

                    <hr class="acp-divider">

                    <!-- New password -->
                    <div class="acp-section">New Password</div>

                    <div class="acp-field">
                        <label>New Password <span class="req">*</span></label>
                        <div class="acp-pw-wrap">
                            <input type="password" name="new_password" id="acpNew"
                                   placeholder="Create a strong new password"
                                   required oninput="acpStrength(this.value); acpMatchCheck()">
                            <button type="button" class="acp-eye"
                                    onclick="acpToggle('acpNew', this)">👁️</button>
                        </div>
                        <div class="acp-strength-bar">
                            <div class="acp-strength-fill" id="acpStrFill"></div>
                        </div>
                        <div class="acp-strength-text" id="acpStrText"></div>
                    </div>

                    <div class="acp-field">
                        <label>Confirm New Password <span class="req">*</span></label>
                        <div class="acp-pw-wrap">
                            <input type="password" name="confirm_password" id="acpConfirm"
                                   placeholder="Repeat your new password"
                                   required oninput="acpMatchCheck()">
                            <button type="button" class="acp-eye"
                                    onclick="acpToggle('acpConfirm', this)">👁️</button>
                        </div>
                        <div class="acp-match" id="acpMatch"></div>
                    </div>

                    <!-- Footer -->
                    <div class="acp-footer">
                        <a href="/flight_booking/view/adminprofile.php" class="acp-btn-cancel">
                            ✕ Cancel
                        </a>
                        <button type="submit" name="change_password" class="acp-btn-submit">
                            🔒 Change Password
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<script>
function acpToggle(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁️' : '🙈';
}

function acpStrength(val) {
    const fill = document.getElementById('acpStrFill');
    const text = document.getElementById('acpStrText');
    if (!val) { fill.style.width = '0'; text.textContent = ''; return; }

    let s = 0;
    if (val.length >= 8)           s++;
    if (val.length >= 12)          s++;
    if (/[A-Z]/.test(val))         s++;
    if (/[0-9]/.test(val))         s++;
    if (/[^A-Za-z0-9]/.test(val))  s++;

    const lvl = [
        { w:'20%', bg:'#ef4444', t:'Very Weak'   },
        { w:'40%', bg:'#f97316', t:'Weak'         },
        { w:'60%', bg:'#eab308', t:'Fair'         },
        { w:'80%', bg:'#3b82f6', t:'Strong'       },
        { w:'100%',bg:'#10b981', t:'Very Strong'  },
    ][Math.min(s - 1, 4)] || { w:'20%', bg:'#ef4444', t:'Very Weak' };

    fill.style.width      = lvl.w;
    fill.style.background = lvl.bg;
    text.textContent      = lvl.t;
    text.style.color      = lvl.bg;
}

function acpMatchCheck() {
    const nv  = document.getElementById('acpNew').value;
    const cv  = document.getElementById('acpConfirm').value;
    const el  = document.getElementById('acpMatch');
    if (!cv) { el.className = 'acp-match'; return; }
    if (nv === cv) {
        el.className = 'acp-match ok';
        el.textContent = '✅ Passwords match';
    } else {
        el.className = 'acp-match bad';
        el.textContent = '❌ Passwords do not match';
    }
}

// Auto-dismiss flash
const acpFlash = document.getElementById('acpFlash');
if (acpFlash) setTimeout(() => acpFlash.remove(), 5000);
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
