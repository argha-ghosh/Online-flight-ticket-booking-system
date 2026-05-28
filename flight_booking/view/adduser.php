<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once("../model/db_conn.php");

// ── Handle POST before any output (PRG pattern) ──────────────
if (isset($_POST['submit'])) {

    $name     = trim($_POST['name']);
    $age      = (int)$_POST['age'];
    $dob      = $_POST['dob'];
    $phone    = trim($_POST['phone']);
    $gender   = $_POST['gender'];
    $city     = $_POST['city'];
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $password = password_hash($_POST['pass'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM login WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        $_SESSION['user_msg']      = "A user with this email already exists.";
        $_SESSION['user_msg_type'] = "error";
    } else {
        $check->close();
        $profile_image = "";
        $err = "";

        if (!empty($_FILES['image']['name'])) {
            $upload_dir = __DIR__ . "/uploads/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'])) {
                $err = "Only JPG and PNG images are allowed.";
            } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $err = "Image must be under 2MB.";
            } else {
                $profile_image = time() . '_' . basename($_FILES['image']['name']);
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $profile_image)) {
                    $err = "Failed to upload image."; $profile_image = "";
                }
            }
        }

        if (empty($err)) {
            $conn->begin_transaction();
            try {
                $s1 = $conn->prepare(
                    "INSERT INTO users (name, age, date_of_birth, phone, gender, city, email, password, role, profile_image)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $s1->bind_param("sissssssss", $name, $age, $dob, $phone, $gender, $city, $email, $password, $role, $profile_image);
                $s1->execute(); $s1->close();

                $s2 = $conn->prepare("INSERT INTO login (email, password, role) VALUES (?, ?, ?)");
                $s2->bind_param("sss", $email, $password, $role);
                $s2->execute(); $s2->close();

                $conn->commit();
                $_SESSION['user_msg']      = "User " . htmlspecialchars($name) . " added successfully as " . ucfirst($role) . "!";
                $_SESSION['user_msg_type'] = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['user_msg']      = "Database error: " . $e->getMessage();
                $_SESSION['user_msg_type'] = "error";
            }
        } else {
            $_SESSION['user_msg']      = $err;
            $_SESSION['user_msg_type'] = "error";
        }
    }

    header("Location: /flight_booking/view/adduser.php");
    exit;
}

// Consume flash
$flash_msg  = $_SESSION['user_msg']      ?? '';
$flash_type = $_SESSION['user_msg_type'] ?? '';
unset($_SESSION['user_msg'], $_SESSION['user_msg_type']);

include("../includes/adminheader.php");
?>
<style>
/* ── Page ── */
.au-page {
    flex: 1;
    padding: 32px 32px 60px;
    max-width: 860px;
    width: 100%;
    margin: 0 auto;
}
/* ── Title bar ── */
.au-titlebar { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
.au-titlebar-icon {
    width:52px; height:52px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    border-radius:14px; display:flex; align-items:center;
    justify-content:center; font-size:1.5rem;
    box-shadow:0 4px 14px rgba(11,114,230,0.3); flex-shrink:0;
}
.au-titlebar h1 { font-size:1.4rem; font-weight:800; color:#0f172a; letter-spacing:-0.4px; }
.au-titlebar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }
/* ── Flash ── */
.au-flash {
    display:flex; align-items:center; gap:10px;
    padding:13px 18px; border-radius:12px;
    font-size:0.88rem; font-weight:600;
    margin-bottom:22px; animation:auFade 0.3s ease;
}
@keyframes auFade { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.au-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.au-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.au-flash .au-close {
    margin-left:auto; cursor:pointer; opacity:0.5; font-size:1rem;
    background:none; border:none; color:inherit; padding:0; font-family:inherit;
}
.au-flash .au-close:hover { opacity:1; }
/* ── Card ── */
.au-card {
    background:#fff; border-radius:20px;
    box-shadow:0 4px 24px rgba(11,114,230,0.1);
    border:1px solid #e8f0fb; overflow:hidden;
}
.au-card-header {
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    padding:20px 28px; display:flex; align-items:center; gap:14px;
}
.au-card-header-icon {
    width:42px; height:42px; background:rgba(255,255,255,0.2);
    border-radius:11px; display:flex; align-items:center;
    justify-content:center; font-size:1.2rem; flex-shrink:0;
}
.au-card-header h2 { color:#fff; font-size:1rem; font-weight:700; margin:0; }
.au-card-header span { color:rgba(255,255,255,0.72); font-size:0.76rem; display:block; margin-top:2px; }
.au-card-body { padding:28px; }
/* ── Section label ── */
.au-section {
    font-size:0.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:1px; color:#94a3b8;
    margin:22px 0 14px; display:flex; align-items:center; gap:8px;
}
.au-section:first-child { margin-top:0; }
.au-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }
/* ── Grid ── */
.au-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.au-grid.three { grid-template-columns:1fr 1fr 1fr; }
.au-grid.one   { grid-template-columns:1fr; }
/* ── Field ── */
.au-field { display:flex; flex-direction:column; gap:6px; }
.au-field label {
    font-size:0.74rem; font-weight:700; color:#475569;
    text-transform:uppercase; letter-spacing:0.6px;
    display:flex; align-items:center; gap:5px;
}
.au-field label .req { color:#e53e3e; }
/* ── Input ── */
.au-input {
    padding:11px 14px; border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:0.9rem; color:#1e293b; background:#f8fafc;
    transition:border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline:none; font-family:inherit; width:100%; appearance:none;
}
.au-input:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.au-input::placeholder { color:#b0bec5; }
select.au-input { cursor:pointer; }
/* Select arrow */
.au-sel { position:relative; }
.au-sel::after {
    content:'▾'; position:absolute; right:13px; top:50%;
    transform:translateY(-50%); color:#94a3b8;
    pointer-events:none; font-size:0.8rem;
}
.au-sel select { padding-right:32px; }
/* Password wrap */
.au-pass-wrap { position:relative; }
.au-pass-wrap .au-eye {
    position:absolute; right:13px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:#94a3b8;
    font-size:0.95rem; padding:4px; line-height:1;
}
.au-pass-wrap .au-eye:hover { color:#0b72e6; }
.au-pass-wrap .au-input { padding-right:40px; }
/* Strength bar */
.au-strength-bar { height:3px; border-radius:3px; background:#e2e8f0; margin-top:6px; overflow:hidden; }
.au-strength-fill { height:100%; width:0; border-radius:3px; transition:width 0.3s, background 0.3s; }
.au-strength-text { font-size:0.72rem; margin-top:4px; color:#94a3b8; }
</style>
<style>
/* ── Role cards ── */
.au-roles { display:flex; gap:14px; }
.au-role-card { flex:1; position:relative; cursor:pointer; }
.au-role-card input[type="radio"] { position:absolute; opacity:0; width:0; height:0; }
.au-role-inner {
    border:2px solid #e2e8f0; border-radius:14px;
    padding:18px 14px; text-align:center;
    background:#f8fafc; transition:all 0.2s;
}
.au-role-card:hover .au-role-inner { border-color:#0b72e6; background:#f0f7ff; }
.au-role-card input:checked + .au-role-inner {
    border-color:#0b72e6; background:#eff6ff;
    box-shadow:0 0 0 3px rgba(11,114,230,0.12);
}
.au-role-inner .ri-icon { font-size:1.8rem; display:block; margin-bottom:8px; }
.au-role-inner .ri-name { font-size:0.92rem; font-weight:700; color:#0f172a; }
.au-role-inner .ri-desc { font-size:0.74rem; color:#64748b; margin-top:3px; }
.au-role-dot {
    position:absolute; top:10px; right:10px;
    width:18px; height:18px; border:2px solid #e2e8f0;
    border-radius:50%; background:#fff; transition:all 0.2s;
    display:flex; align-items:center; justify-content:center;
}
.au-role-card input:checked ~ .au-role-dot { background:#0b72e6; border-color:#0b72e6; }
.au-role-card input:checked ~ .au-role-dot::after {
    content:''; width:6px; height:6px; border-radius:50%; background:#fff;
}
/* ── File upload ── */
.au-file-zone {
    border:2px dashed #c7d8f0; border-radius:10px;
    padding:18px 16px; text-align:center; background:#f8fafc;
    cursor:pointer; transition:border-color 0.2s, background 0.2s; position:relative;
}
.au-file-zone:hover { border-color:#0b72e6; background:#f0f7ff; }
.au-file-zone input[type="file"] {
    position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
}
.au-file-zone .fz-icon { font-size:1.8rem; display:block; margin-bottom:5px; }
.au-file-zone .fz-text { font-size:0.8rem; color:#64748b; line-height:1.5; }
.au-file-zone .fz-text b { color:#0b72e6; }
/* Preview */
.au-preview {
    display:none; align-items:center; gap:14px;
    margin-top:12px; padding:12px 14px;
    background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px;
}
.au-preview.show { display:flex; }
.au-preview img {
    width:52px; height:52px; border-radius:10px;
    object-fit:cover; border:2px solid #bbf7d0;
}
.au-preview .pv-info .pv-name { font-size:0.85rem; font-weight:700; color:#0f172a; }
.au-preview .pv-info .pv-size { font-size:0.75rem; color:#64748b; margin-top:2px; }
/* ── Footer ── */
.au-footer {
    display:flex; align-items:center; justify-content:flex-end; gap:12px;
    padding-top:22px; border-top:1px solid #f1f5f9; margin-top:8px;
}
.au-btn-reset {
    padding:11px 22px; border:1.5px solid #e2e8f0; border-radius:10px;
    background:#fff; color:#64748b; font-size:0.9rem; font-weight:600;
    cursor:pointer; font-family:inherit; transition:all 0.15s;
}
.au-btn-reset:hover { border-color:#94a3b8; color:#334155; }
.au-btn-submit {
    padding:11px 28px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    color:#fff; border:none; border-radius:10px;
    font-size:0.95rem; font-weight:700; cursor:pointer;
    display:flex; align-items:center; gap:8px;
    transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow:0 4px 14px rgba(11,114,230,0.3); font-family:inherit;
}
.au-btn-submit:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 6px 20px rgba(11,114,230,0.38); }
.au-btn-submit:active { transform:translateY(0); }
/* ── Responsive ── */
@media (max-width:700px) {
    .au-page { padding:16px 14px 40px; }
    .au-grid, .au-grid.three { grid-template-columns:1fr; }
    .au-roles { flex-direction:column; }
    .au-footer { flex-direction:column-reverse; }
    .au-btn-reset, .au-btn-submit { width:100%; justify-content:center; }
}
</style>

<div class="au-page">

    <!-- Title -->
    <div class="au-titlebar">
        <div class="au-titlebar-icon">👤</div>
        <div>
            <h1>Add New User</h1>
            <p>Create an admin or manager account for the GoZayan panel</p>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash_msg): ?>
        <div class="au-flash <?= htmlspecialchars($flash_type) ?>" id="auFlash">
            <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($flash_msg) ?>
            <button class="au-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div class="au-card">
        <div class="au-card-header">
            <div class="au-card-header-icon">🛡️</div>
            <div>
                <h2>User Information</h2>
                <span>Fields marked with * are required</span>
            </div>
        </div>

        <div class="au-card-body">
            <form action="/flight_booking/view/adduser.php" method="POST"
                  enctype="multipart/form-data" id="addUserForm">

                <!-- Personal Details -->
                <div class="au-section">Personal Details</div>
                <div class="au-grid three">
                    <div class="au-field">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="name" id="name" class="au-input"
                               placeholder="Ayesha Rahman" required>
                    </div>
                    <div class="au-field">
                        <label>Age <span class="req">*</span></label>
                        <input type="number" name="age" id="age" class="au-input"
                               placeholder="30" min="18" max="80" required>
                    </div>
                    <div class="au-field">
                        <label>Date of Birth <span class="req">*</span></label>
                        <input type="date" name="dob" id="dob" class="au-input" required>
                    </div>
                </div>

                <div class="au-grid" style="margin-top:16px;">
                    <div class="au-field">
                        <label>Gender <span class="req">*</span></label>
                        <div class="au-sel">
                            <select name="gender" id="gender" class="au-input" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="au-field">
                        <label>City <span class="req">*</span></label>
                        <div class="au-sel">
                            <select name="city" id="city" class="au-input" required>
                                <option value="">Select City</option>
                                <?php foreach (['Dhaka','Chittagong','Khulna','Rajshahi','Sylhet','Barisal','Comilla','Mymensingh'] as $c): ?>
                                    <option value="<?= $c ?>"><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contact & Access -->
                <div class="au-section">Contact &amp; Access</div>
                <div class="au-grid">
                    <div class="au-field">
                        <label>Phone Number <span class="req">*</span></label>
                        <input type="tel" name="phone" id="phone" class="au-input"
                               placeholder="01XXXXXXXXX" pattern="[0-9]{11}"
                               title="Enter 11-digit number" required>
                    </div>
                    <div class="au-field">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="email" id="email" class="au-input"
                               placeholder="user@gozayan.com" required>
                    </div>
                </div>

                <div class="au-grid one" style="margin-top:16px;">
                    <div class="au-field">
                        <label>Password <span class="req">*</span></label>
                        <div class="au-pass-wrap">
                            <input type="password" name="pass" id="pass" class="au-input"
                                   placeholder="Minimum 6 characters" required
                                   oninput="auStrength(this.value)">
                            <button type="button" class="au-eye" onclick="auTogglePass()">👁️</button>
                        </div>
                        <div class="au-strength-bar">
                            <div class="au-strength-fill" id="auStrFill"></div>
                        </div>
                        <div class="au-strength-text" id="auStrText"></div>
                    </div>
                </div>

                <!-- Role -->
                <div class="au-section">Role Assignment <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#e53e3e">*</span></div>
                <div class="au-roles" style="margin-bottom:22px;">
                    <label class="au-role-card">
                        <input type="radio" name="role" value="admin" required>
                        <div class="au-role-inner">
                            <span class="ri-icon">🛡️</span>
                            <div class="ri-name">Admin</div>
                            <div class="ri-desc">Full system access</div>
                        </div>
                        <div class="au-role-dot"></div>
                    </label>
                    <label class="au-role-card">
                        <input type="radio" name="role" value="manager">
                        <div class="au-role-inner">
                            <span class="ri-icon">✈️</span>
                            <div class="ri-name">Manager</div>
                            <div class="ri-desc">Flight &amp; schedule management</div>
                        </div>
                        <div class="au-role-dot"></div>
                    </label>
                    <!-- <label class="au-role-card">
                        <input type="radio" name="role" value="webuser">
                        <div class="au-role-inner">
                            <span class="ri-icon">🧳</span>
                            <div class="ri-name">Traveller</div>
                            <div class="ri-desc">Book &amp; manage flights</div>
                        </div>
                        <div class="au-role-dot"></div>
                    </label> -->
                </div>

                <!-- Profile Photo -->
                <div class="au-section">Profile Photo <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#e53e3e">*</span></div>
                <div class="au-field" style="margin-bottom:22px;">
                    <div class="au-file-zone" id="auFileZone">
                        <input type="file" name="image" id="image"
                               accept="image/jpg,image/jpeg,image/png"
                               required onchange="auPreview(this)">
                        <span class="fz-icon">🖼️</span>
                        <p class="fz-text" id="auFileLabel">
                            <b>Click to upload</b> or drag &amp; drop<br>JPG or PNG, max 2MB
                        </p>
                    </div>
                    <div class="au-preview" id="auPreview">
                        <img id="auPreviewImg" src="" alt="Preview">
                        <div class="pv-info">
                            <div class="pv-name" id="auPreviewName"></div>
                            <div class="pv-size" id="auPreviewSize"></div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="au-footer">
                    <button type="reset" class="au-btn-reset" onclick="auReset()">✕ Clear Form</button>
                    <button type="submit" name="submit" class="au-btn-submit">👤 Add User</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function auTogglePass() {
    const p = document.getElementById('pass');
    p.type = p.type === 'password' ? 'text' : 'password';
}

function auStrength(val) {
    const fill = document.getElementById('auStrFill');
    const text = document.getElementById('auStrText');
    let s = 0;
    if (val.length >= 6)           s++;
    if (val.length >= 10)          s++;
    if (/[A-Z]/.test(val))         s++;
    if (/[0-9]/.test(val))         s++;
    if (/[^A-Za-z0-9]/.test(val))  s++;
    const lvl = [
        {w:'0',   bg:'transparent', t:''},
        {w:'20%', bg:'#ef4444', t:'Weak'},
        {w:'45%', bg:'#f97316', t:'Fair'},
        {w:'65%', bg:'#eab308', t:'Good'},
        {w:'85%', bg:'#10b981', t:'Strong'},
        {w:'100%',bg:'#059669', t:'Very Strong'},
    ][Math.min(s, 5)];
    fill.style.width      = lvl.w;
    fill.style.background = lvl.bg;
    text.textContent      = lvl.t;
    text.style.color      = lvl.bg;
}

function auPreview(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('auPreviewImg').src  = e.target.result;
        document.getElementById('auPreviewName').textContent = file.name;
        document.getElementById('auPreviewSize').textContent = (file.size/1024).toFixed(1) + ' KB';
        document.getElementById('auPreview').classList.add('show');
        document.getElementById('auFileLabel').innerHTML = '✅ <b>' + file.name + '</b>';
        document.getElementById('auFileZone').style.borderColor = '#16a34a';
        document.getElementById('auFileZone').style.background  = '#f0fdf4';
    };
    reader.readAsDataURL(file);
}

function auReset() {
    document.getElementById('auPreview').classList.remove('show');
    document.getElementById('auPreviewImg').src = '';
    document.getElementById('auStrFill').style.width = '0';
    document.getElementById('auStrText').textContent = '';
    document.getElementById('auFileLabel').innerHTML = '<b>Click to upload</b> or drag & drop<br>JPG or PNG, max 2MB';
    document.getElementById('auFileZone').style.borderColor = '';
    document.getElementById('auFileZone').style.background  = '';
}

const auFlash = document.getElementById('auFlash');
if (auFlash) setTimeout(() => auFlash.remove(), 4000);
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
