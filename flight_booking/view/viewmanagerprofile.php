<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/base_url.php";
include_once("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    header("Location: " . BASE_URL . "/view/login.php"); exit;
}

$manager_email = $_SESSION['email'];

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $manager_email);
$stmt->execute();
$manager = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$manager) { die("Manager record not found."); }
$manager_id = $manager['id'];

// ── Handle save (PRG) ────────────────────────────────────────
if (isset($_POST['save'])) {
    $new_name  = trim($_POST['name']);
    $new_phone = trim($_POST['phone']);
    $new_city  = trim($_POST['city']);
    $new_age   = (int)$_POST['age'];
    $new_dob   = $_POST['date_of_birth'];
    $new_gnd   = $_POST['gender'];
    $new_img   = $manager['profile_image'];
    $err       = '';

    if (!empty($_FILES['profile_image']['name'])) {
        $upload_dir = __DIR__ . "/uploadss/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
            $err = "Only JPG, PNG and GIF images are allowed.";
        } elseif ($_FILES['profile_image']['size'] > 2097152) {
            $err = "Image must be under 2MB.";
        } else {
            $new_file = "profile_{$manager_id}_" . time() . ".$ext";
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_file)) {
                if (!empty($manager['profile_image']) && file_exists($upload_dir . $manager['profile_image']))
                    @unlink($upload_dir . $manager['profile_image']);
                $new_img = $new_file;
            } else { $err = "Failed to upload image."; }
        }
    }

    if (empty($err)) {
        $u = $conn->prepare("UPDATE users SET name=?,age=?,date_of_birth=?,phone=?,gender=?,city=?,profile_image=? WHERE id=?");
        $u->bind_param("sisssssi", $new_name, $new_age, $new_dob, $new_phone, $new_gnd, $new_city, $new_img, $manager_id);
        if ($u->execute()) {
            $_SESSION['mgr_profile_msg']      = "Profile updated successfully!";
            $_SESSION['mgr_profile_msg_type'] = "success";
            $manager = array_merge($manager, ['name'=>$new_name,'age'=>$new_age,'date_of_birth'=>$new_dob,'phone'=>$new_phone,'gender'=>$new_gnd,'city'=>$new_city,'profile_image'=>$new_img]);
        } else {
            $_SESSION['mgr_profile_msg']      = "Error updating profile.";
            $_SESSION['mgr_profile_msg_type'] = "error";
        }
        $u->close();
    } else {
        $_SESSION['mgr_profile_msg']      = $err;
        $_SESSION['mgr_profile_msg_type'] = "error";
    }
    header("Location: " . BASE_URL . "/view/viewmanagerprofile.php"); exit;
}

$flash_msg  = $_SESSION['mgr_profile_msg']      ?? '';
$flash_type = $_SESSION['mgr_profile_msg_type'] ?? '';
unset($_SESSION['mgr_profile_msg'], $_SESSION['mgr_profile_msg_type']);

// Stats
$total_flights   = $conn->query("SELECT COUNT(*) AS c FROM flights")->fetch_assoc()['c'] ?? 0;
$total_schedules = $conn->query("SELECT COUNT(*) AS c FROM schedule")->fetch_assoc()['c'] ?? 0;

include("../includes/managerheader.php");
?>
<style>
.mp-page { flex:1; padding:32px 32px 60px; max-width:1100px; width:100%; margin:0 auto; }

/* Title */
.mp-titlebar { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
.mp-titlebar-icon { width:52px; height:52px; background:linear-gradient(135deg,#0b72e6,#6c3de8); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 4px 14px rgba(11,114,230,0.3); flex-shrink:0; }
.mp-titlebar h1 { font-size:1.4rem; font-weight:800; color:#0f172a; letter-spacing:-0.4px; }
.mp-titlebar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }

/* Flash */
.mp-flash { display:flex; align-items:center; gap:10px; padding:13px 18px; border-radius:12px; font-size:0.88rem; font-weight:600; margin-bottom:22px; animation:mpFade 0.3s ease; }
@keyframes mpFade { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.mp-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.mp-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.mp-flash .mp-close { margin-left:auto; cursor:pointer; opacity:0.5; font-size:1rem; background:none; border:none; color:inherit; padding:0; font-family:inherit; }

/* Layout */
.mp-layout { display:grid; grid-template-columns:280px 1fr; gap:24px; align-items:start; }

/* Avatar card */
.mp-avatar-card { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,0.1); border:1px solid #e8f0fb; overflow:visible; position:sticky; top:76px; }
.mp-avatar-banner { height:100px; background:linear-gradient(135deg,#0b72e6,#6c3de8); border-radius:20px 20px 0 0; position:relative; }
.mp-avatar-wrap { position:absolute; bottom:-40px; left:50%; transform:translateX(-50%); z-index:2; }
.mp-avatar {
    width:80px; height:80px; border-radius:50%; border:4px solid #fff;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    display:flex; align-items:center; justify-content:center;
    font-size:2rem; color:#fff; box-shadow:0 4px 16px rgba(11,114,230,0.3); overflow:hidden;
}
.mp-avatar img { width:100%; height:100%; object-fit:cover; }
.mp-avatar-body { padding:52px 20px 24px; text-align:center; border-radius:0 0 20px 20px; }
.mp-avatar-name { font-size:1.05rem; font-weight:800; color:#0f172a; margin-bottom:4px; }
.mp-avatar-email { font-size:0.78rem; color:#64748b; word-break:break-all; margin-bottom:14px; }
.mp-role-badge { display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; font-size:0.75rem; font-weight:700; padding:5px 14px; border-radius:20px; margin-bottom:20px; }
.mp-stats { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.mp-stat { background:#f8fafc; border:1px solid #e8f0fb; border-radius:12px; padding:12px 8px; text-align:center; }
.mp-stat .sv { font-size:1.1rem; font-weight:800; color:#0b72e6; }
.mp-stat .sl { font-size:0.7rem; color:#64748b; margin-top:2px; }

/* Details card */
.mp-details-card { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,0.1); border:1px solid #e8f0fb; overflow:hidden; }
.mp-details-header { background:linear-gradient(135deg,#0b72e6,#6c3de8); padding:18px 26px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
.mp-details-header-left { display:flex; align-items:center; gap:12px; }
.mp-details-header-icon { width:40px; height:40px; background:rgba(255,255,255,0.2); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.mp-details-header h2 { color:#fff; font-size:1rem; font-weight:700; margin:0; }
.mp-details-header span { color:rgba(255,255,255,0.72); font-size:0.76rem; display:block; margin-top:2px; }
.mp-edit-btn { display:flex; align-items:center; gap:7px; padding:8px 18px; border-radius:8px; background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.3); color:#fff; font-size:0.85rem; font-weight:600; cursor:pointer; transition:background 0.2s; font-family:inherit; white-space:nowrap; }
.mp-edit-btn:hover { background:rgba(255,255,255,0.3); }
.mp-details-body { padding:26px; }

/* Section */
.mp-section { font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin:22px 0 14px; display:flex; align-items:center; gap:8px; }
.mp-section:first-child { margin-top:0; }
.mp-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }

/* Grid */
.mp-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.mp-grid.three { grid-template-columns:1fr 1fr 1fr; }

/* Field */
.mp-field { display:flex; flex-direction:column; gap:6px; }
.mp-field label { font-size:0.74rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; }
.mp-input {
    padding:11px 14px; border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:0.9rem; color:#1e293b; background:#f8fafc;
    transition:border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline:none; font-family:inherit; width:100%; appearance:none;
}
.mp-input[readonly], .mp-input[disabled] { cursor:default; color:#475569; }
.mp-input:not([readonly]):not([disabled]):focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.mp-sel { position:relative; }
.mp-sel::after { content:'▾'; position:absolute; right:13px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; font-size:0.8rem; }
.mp-sel select { padding-right:32px; cursor:pointer; }

/* File upload */
.mp-file-zone { border:2px dashed #c7d8f0; border-radius:10px; padding:16px 14px; text-align:center; background:#f8fafc; cursor:pointer; transition:border-color 0.2s, background 0.2s; position:relative; }
.mp-file-zone:hover { border-color:#0b72e6; background:#f0f7ff; }
.mp-file-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.mp-file-zone .fz-icon { font-size:1.6rem; display:block; margin-bottom:4px; }
.mp-file-zone .fz-text { font-size:0.78rem; color:#64748b; line-height:1.5; }
.mp-file-zone .fz-text b { color:#0b72e6; }

/* Current photo */
.mp-current-photo { display:flex; align-items:center; gap:14px; padding:14px 16px; background:#fafcff; border:1px solid #e8f0fb; border-radius:12px; margin-bottom:14px; }
.mp-current-photo img { width:52px; height:52px; border-radius:10px; object-fit:cover; border:2px solid #e8f0fb; }
.mp-current-photo .cp-info h4 { font-size:0.88rem; font-weight:700; color:#0f172a; }
.mp-current-photo .cp-info p  { font-size:0.75rem; color:#64748b; margin-top:2px; }

/* Footer */
.mp-footer { display:flex; align-items:center; justify-content:flex-end; gap:12px; padding-top:22px; border-top:1px solid #f1f5f9; margin-top:8px; }
.mp-btn-cancel { padding:10px 22px; border:1.5px solid #e2e8f0; border-radius:10px; background:#fff; color:#64748b; font-size:0.9rem; font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.15s; display:none; }
.mp-btn-cancel:hover { border-color:#94a3b8; color:#334155; }
.mp-btn-save { padding:10px 26px; background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; border:none; border-radius:10px; font-size:0.9rem; font-weight:700; cursor:pointer; display:none; align-items:center; gap:8px; transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s; box-shadow:0 4px 14px rgba(11,114,230,0.3); font-family:inherit; }
.mp-btn-save:hover { opacity:0.9; transform:translateY(-1px); }

/* Responsive */
@media (max-width:900px) {
    .mp-layout { grid-template-columns:1fr; }
    .mp-avatar-card { position:static; }
}
@media (max-width:600px) {
    .mp-page { padding:16px 14px 80px; }
    .mp-titlebar { gap:10px; }
    .mp-titlebar-icon { width:42px; height:42px; font-size:1.2rem; }
    .mp-titlebar h1 { font-size:1.15rem; }
    .mp-grid, .mp-grid.three { grid-template-columns:1fr; }
    .mp-details-header { flex-direction:column; align-items:flex-start; gap:10px; }
    .mp-edit-btn { width:100%; justify-content:center; }
    .mp-details-body { padding:18px 16px; }
    .mp-footer { flex-direction:column-reverse; }
    .mp-btn-cancel, .mp-btn-save { width:100%; justify-content:center; }
    .mp-stats { grid-template-columns:1fr 1fr; }
}
</style>

<div class="mp-page">
    <div class="mp-titlebar">
        <div class="mp-titlebar-icon">✈️</div>
        <div><h1>Manager Profile</h1><p>View and update your manager account details</p></div>
    </div>

    <?php if ($flash_msg): ?>
        <div class="mp-flash <?= htmlspecialchars($flash_type) ?>" id="mpFlash">
            <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($flash_msg) ?>
            <button class="mp-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div class="mp-layout">
        <!-- Avatar card -->
        <div class="mp-avatar-card">
            <div class="mp-avatar-banner"></div>
            <div class="mp-avatar-wrap">
                <div class="mp-avatar" id="mpAvatarCircle">
                    <?php
                    $img_path = __DIR__ . "/uploadss/" . ($manager['profile_image'] ?? '');
                    if (!empty($manager['profile_image']) && file_exists($img_path)):
                    ?>
                        <img src="uploadss/<?= htmlspecialchars($manager['profile_image']) ?>" id="mpAvatarImg" alt="Profile">
                    <?php else: ?>
                        <span id="mpAvatarInitial"><?= strtoupper(substr($manager['name'] ?? 'M', 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mp-avatar-body">
                <div class="mp-avatar-name" id="mpSidebarName"><?= htmlspecialchars($manager['name'] ?? '') ?></div>
                <div class="mp-avatar-email"><?= htmlspecialchars($manager['email'] ?? '') ?></div>
                <div class="mp-role-badge">✈️ Flight Manager</div>
                <div class="mp-stats">
                    <div class="mp-stat"><div class="sv"><?= $total_flights ?></div><div class="sl">Flights</div></div>
                    <div class="mp-stat"><div class="sv"><?= $total_schedules ?></div><div class="sl">Schedules</div></div>
                </div>
            </div>
        </div>

        <!-- Details card -->
        <div class="mp-details-card">
            <div class="mp-details-header">
                <div class="mp-details-header-left">
                    <div class="mp-details-header-icon">📋</div>
                    <div><h2>Profile Details</h2><span>Click Edit to make changes</span></div>
                </div>
                <button type="button" class="mp-edit-btn" id="mpEditBtn" onclick="mpStartEdit()">✏️ Edit Profile</button>
            </div>
            <div class="mp-details-body">
                <form action="<?= BASE_URL ?>/view/viewmanagerprofile.php" method="POST" enctype="multipart/form-data" id="mpForm">

                    <div class="mp-section">Personal Information</div>
                    <div class="mp-grid three">
                        <div class="mp-field">
                            <label>Full Name</label>
                            <input type="text" name="name" class="mp-input" value="<?= htmlspecialchars($manager['name'] ?? '') ?>" readonly>
                        </div>
                        <div class="mp-field">
                            <label>Age</label>
                            <input type="number" name="age" class="mp-input" value="<?= htmlspecialchars($manager['age'] ?? '') ?>" min="18" max="80" readonly>
                        </div>
                        <div class="mp-field">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="mp-input" value="<?= htmlspecialchars($manager['date_of_birth'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <div class="mp-section">Contact Details</div>
                    <div class="mp-grid">
                        <div class="mp-field">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" class="mp-input" value="<?= htmlspecialchars($manager['phone'] ?? '') ?>" readonly>
                        </div>
                        <div class="mp-field">
                            <label>Email Address</label>
                            <input type="email" class="mp-input" value="<?= htmlspecialchars($manager['email'] ?? '') ?>" readonly disabled>
                        </div>
                    </div>
                    <div class="mp-grid" style="margin-top:16px;">
                        <div class="mp-field">
                            <label>City</label>
                            <input type="text" name="city" class="mp-input" value="<?= htmlspecialchars($manager['city'] ?? '') ?>" readonly>
                        </div>
                        <div class="mp-field">
                            <label>Gender</label>
                            <div class="mp-sel">
                                <select name="gender" class="mp-input" disabled>
                                    <?php foreach (['Male','Female','Other'] as $g): ?>
                                        <option value="<?= $g ?>" <?= ($manager['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mp-section" id="mpPhotoSection" style="display:none;">Profile Photo</div>
                    <div id="mpPhotoUpload" style="display:none;">
                        <?php if (!empty($manager['profile_image']) && file_exists($img_path)): ?>
                        <div class="mp-current-photo">
                            <img src="uploadss/<?= htmlspecialchars($manager['profile_image']) ?>" alt="Current photo">
                            <div class="cp-info"><h4>Current Photo</h4><p>Upload below to replace it</p></div>
                        </div>
                        <?php endif; ?>
                        <div class="mp-file-zone" id="mpFileZone">
                            <input type="file" name="profile_image" accept="image/jpg,image/jpeg,image/png,image/gif" onchange="mpPreviewPhoto(this)">
                            <span class="fz-icon">🖼️</span>
                            <p class="fz-text" id="mpFileLabel"><b>Click to upload</b> or drag &amp; drop<br>JPG, PNG, GIF — max 2MB</p>
                        </div>
                    </div>

                    <div class="mp-footer">
                        <button type="button" class="mp-btn-cancel" id="mpCancelBtn" onclick="mpCancelEdit()">✕ Cancel</button>
                        <button type="submit" name="save" class="mp-btn-save" id="mpSaveBtn">💾 Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let mpOriginals = {};

function mpStartEdit() {
    ['name','age','date_of_birth','phone','city'].forEach(id => {
        const el = document.querySelector('[name="' + id + '"]');
        if (el) { mpOriginals[id] = el.value; el.removeAttribute('readonly'); }
    });
    const gnd = document.querySelector('[name="gender"]');
    if (gnd) { mpOriginals['gender'] = gnd.value; gnd.removeAttribute('disabled'); }

    document.getElementById('mpSaveBtn').style.display   = 'flex';
    document.getElementById('mpCancelBtn').style.display = 'block';
    document.getElementById('mpEditBtn').style.display   = 'none';
    document.getElementById('mpPhotoSection').style.display = '';
    document.getElementById('mpPhotoUpload').style.display  = '';
    document.querySelector('[name="name"]').focus();
}

function mpCancelEdit() {
    ['name','age','date_of_birth','phone','city'].forEach(id => {
        const el = document.querySelector('[name="' + id + '"]');
        if (el) { el.value = mpOriginals[id]; el.setAttribute('readonly', true); }
    });
    const gnd = document.querySelector('[name="gender"]');
    if (gnd) { gnd.value = mpOriginals['gender']; gnd.setAttribute('disabled', true); }

    document.getElementById('mpSaveBtn').style.display   = 'none';
    document.getElementById('mpCancelBtn').style.display = 'none';
    document.getElementById('mpEditBtn').style.display   = 'flex';
    document.getElementById('mpPhotoSection').style.display = 'none';
    document.getElementById('mpPhotoUpload').style.display  = 'none';
}

function mpPreviewPhoto(input) {
    const label = document.getElementById('mpFileLabel');
    const zone  = document.getElementById('mpFileZone');
    if (input.files && input.files[0]) {
        label.innerHTML = '✅ <b>' + input.files[0].name + '</b>';
        zone.style.borderColor = '#16a34a';
        zone.style.background  = '#f0fdf4';
    }
}

const mpFlash = document.getElementById('mpFlash');
if (mpFlash) setTimeout(() => mpFlash.remove(), 4000);
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
