<?php
include("../includes/adminheader.php");
include("../model/db_conn.php");

// Use session-based admin id, fall back to 2
$admin_id = $_SESSION['admin_id'] ?? 2;

$stmt = $conn->prepare("SELECT * FROM admininfo WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
?>
<style>
/* ── Page ── */
.ap-page {
    flex: 1;
    padding: 32px 32px 60px;
    max-width: 1100px;
    width: 100%;
    margin: 0 auto;
}
/* ── Title bar ── */
.ap-titlebar { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
.ap-titlebar-icon {
    width:52px; height:52px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    border-radius:14px; display:flex; align-items:center;
    justify-content:center; font-size:1.5rem;
    box-shadow:0 4px 14px rgba(11,114,230,0.3); flex-shrink:0;
}
.ap-titlebar h1 { font-size:1.4rem; font-weight:800; color:#0f172a; letter-spacing:-0.4px; }
.ap-titlebar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }

/* ── Flash ── */
.ap-flash {
    display:flex; align-items:center; gap:10px;
    padding:13px 18px; border-radius:12px;
    font-size:0.88rem; font-weight:600;
    margin-bottom:22px; animation:apFade 0.3s ease;
}
@keyframes apFade { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.ap-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.ap-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.ap-flash .ap-close {
    margin-left:auto; cursor:pointer; opacity:0.5; font-size:1rem;
    background:none; border:none; color:inherit; padding:0; font-family:inherit;
}

/* ── Two-column layout ── */
.ap-layout {
    display:grid;
    grid-template-columns:280px 1fr;
    gap:24px;
    align-items:start;
}

/* ── Left: Avatar card ── */
.ap-avatar-card {
    background:#fff; border-radius:20px;
    box-shadow:0 4px 24px rgba(11,114,230,0.1);
    border:1px solid #e8f0fb; overflow:visible;
    position:sticky; top:76px;
}
.ap-avatar-banner {
    height:100px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    border-radius:20px 20px 0 0;
    position:relative;
}
.ap-avatar-wrap {
    position:absolute; bottom:-40px; left:50%;
    transform:translateX(-50%);
    z-index:2;
}
.ap-avatar {
    width:80px; height:80px; border-radius:50%;
    border:4px solid #fff;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    display:flex; align-items:center; justify-content:center;
    font-size:2rem; color:#fff;
    box-shadow:0 4px 16px rgba(11,114,230,0.3);
    overflow:hidden;
}
.ap-avatar img { width:100%; height:100%; object-fit:cover; }
.ap-avatar-body {
    padding:52px 20px 24px;
    text-align:center;
    border-radius:0 0 20px 20px;
}
.ap-avatar-name {
    font-size:1.05rem; font-weight:800; color:#0f172a;
    margin-bottom:4px;
}
.ap-avatar-email {
    font-size:0.78rem; color:#64748b;
    word-break:break-all; margin-bottom:16px;
}
.ap-role-badge {
    display:inline-flex; align-items:center; gap:6px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    color:#fff; font-size:0.75rem; font-weight:700;
    padding:5px 14px; border-radius:20px;
    margin-bottom:20px;
}
.ap-stats {
    display:grid; grid-template-columns:1fr 1fr;
    gap:10px; margin-top:4px;
}
.ap-stat {
    background:#f8fafc; border:1px solid #e8f0fb;
    border-radius:12px; padding:12px 8px; text-align:center;
}
.ap-stat .stat-val { font-size:1.1rem; font-weight:800; color:#0b72e6; }
.ap-stat .stat-lbl { font-size:0.7rem; color:#64748b; margin-top:2px; }

/* ── Right: Details card ── */
.ap-details-card {
    background:#fff; border-radius:20px;
    box-shadow:0 4px 24px rgba(11,114,230,0.1);
    border:1px solid #e8f0fb; overflow:hidden;
}
.ap-details-header {
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    padding:18px 26px; display:flex; align-items:center;
    justify-content:space-between; gap:12px;
}
.ap-details-header-left { display:flex; align-items:center; gap:12px; }
.ap-details-header-icon {
    width:40px; height:40px; background:rgba(255,255,255,0.2);
    border-radius:10px; display:flex; align-items:center;
    justify-content:center; font-size:1.1rem; flex-shrink:0;
}
.ap-details-header h2 { color:#fff; font-size:1rem; font-weight:700; margin:0; }
.ap-details-header span { color:rgba(255,255,255,0.72); font-size:0.76rem; display:block; margin-top:2px; }
/* Edit toggle button in header */
.ap-edit-btn {
    display:flex; align-items:center; gap:7px;
    padding:8px 18px; border-radius:8px;
    background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.3);
    color:#fff; font-size:0.85rem; font-weight:600;
    cursor:pointer; transition:background 0.2s; font-family:inherit;
    white-space:nowrap;
}
.ap-edit-btn:hover { background:rgba(255,255,255,0.3); }

.ap-details-body { padding:26px; }

/* Section label */
.ap-section {
    font-size:0.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:1px; color:#94a3b8;
    margin:22px 0 14px; display:flex; align-items:center; gap:8px;
}
.ap-section:first-child { margin-top:0; }
.ap-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }

/* Grid */
.ap-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.ap-grid.three { grid-template-columns:1fr 1fr 1fr; }

/* Field */
.ap-field { display:flex; flex-direction:column; gap:6px; }
.ap-field label {
    font-size:0.74rem; font-weight:700; color:#475569;
    text-transform:uppercase; letter-spacing:0.6px;
}

/* Input — read-only view mode */
.ap-input {
    padding:11px 14px; border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:0.9rem; color:#1e293b; background:#f8fafc;
    transition:border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline:none; font-family:inherit; width:100%; appearance:none;
}
.ap-input[readonly] { cursor:default; color:#475569; }
.ap-input:not([readonly]):focus {
    border-color:#0b72e6; background:#fff;
    box-shadow:0 0 0 3px rgba(11,114,230,0.1);
}
/* Select */
.ap-sel { position:relative; }
.ap-sel::after {
    content:'▾'; position:absolute; right:13px; top:50%;
    transform:translateY(-50%); color:#94a3b8;
    pointer-events:none; font-size:0.8rem;
}
.ap-sel select { padding-right:32px; cursor:pointer; }
select.ap-input[disabled] { opacity:0.7; cursor:default; }

/* Editing state highlight */
.ap-editing .ap-input:not([readonly]),
.ap-editing select.ap-input:not([disabled]) {
    background:#fff; border-color:#0b72e6;
}

/* Footer actions */
.ap-footer {
    display:flex; align-items:center; justify-content:flex-end; gap:12px;
    padding-top:22px; border-top:1px solid #f1f5f9; margin-top:8px;
}
.ap-btn-cancel {
    padding:10px 22px; border:1.5px solid #e2e8f0; border-radius:10px;
    background:#fff; color:#64748b; font-size:0.9rem; font-weight:600;
    cursor:pointer; font-family:inherit; transition:all 0.15s; display:none;
}
.ap-btn-cancel:hover { border-color:#94a3b8; color:#334155; }
.ap-btn-save {
    padding:10px 26px;
    background:linear-gradient(135deg,#0b72e6,#6c3de8);
    color:#fff; border:none; border-radius:10px;
    font-size:0.9rem; font-weight:700; cursor:pointer;
    display:none; align-items:center; gap:8px;
    transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow:0 4px 14px rgba(11,114,230,0.3); font-family:inherit;
}
.ap-btn-save:hover { opacity:0.9; transform:translateY(-1px); }
.ap-btn-save:active { transform:translateY(0); }

/* Responsive */
@media (max-width:900px) {
    .ap-layout { grid-template-columns:1fr; }
    .ap-avatar-card { position:static; }
}
@media (max-width:600px) {
    .ap-page { padding:16px 14px 40px; }
    .ap-grid, .ap-grid.three { grid-template-columns:1fr; }
    .ap-footer { flex-direction:column-reverse; }
    .ap-btn-cancel, .ap-btn-save { width:100%; justify-content:center; }
}
</style>

<div class="ap-page">

    <!-- Title -->
    <div class="ap-titlebar">
        <div class="ap-titlebar-icon">🛡️</div>
        <div>
            <h1>Admin Profile</h1>
            <p>View and update your administrator account details</p>
        </div>
    </div>

    <!-- Flash (injected by JS after save) -->
    <div id="apFlash" style="display:none;" class="ap-flash success">
        <span>✅</span> <span id="apFlashMsg"></span>
        <button class="ap-close" onclick="this.parentElement.style.display='none'">✕</button>
    </div>

    <div class="ap-layout">

        <!-- ── Left: Avatar card ── -->
        <div class="ap-avatar-card">
            <div class="ap-avatar-banner"></div>
            <div class="ap-avatar-wrap">
                <div class="ap-avatar">
                    <?php
                    $initials = strtoupper(substr($admin['name'] ?? 'A', 0, 1));
                    ?>
                    <span><?= $initials ?></span>
                </div>
            </div>
            <div class="ap-avatar-body">
                <div class="ap-avatar-name" id="sidebarName"><?= htmlspecialchars($admin['name'] ?? '') ?></div>
                <div class="ap-avatar-email"><?= htmlspecialchars($admin['email'] ?? '') ?></div>
                <div class="ap-role-badge">🛡️ Administrator</div>
                <div class="ap-stats">
                    <div class="ap-stat">
                        <div class="stat-val">
                            <?php
                            $r = $conn->query("SELECT COUNT(*) AS c FROM flights");
                            echo $r ? $r->fetch_assoc()['c'] : '—';
                            ?>
                        </div>
                        <div class="stat-lbl">Flights</div>
                    </div>
                    <div class="ap-stat">
                        <div class="stat-val">
                            <?php
                            $r2 = $conn->query("SELECT COUNT(*) AS c FROM airlines");
                            echo $r2 ? $r2->fetch_assoc()['c'] : '—';
                            ?>
                        </div>
                        <div class="stat-lbl">Airlines</div>
                    </div>
                    <div class="ap-stat">
                        <div class="stat-val">
                            <?php
                            $r3 = $conn->query("SELECT COUNT(*) AS c FROM users");
                            echo $r3 ? $r3->fetch_assoc()['c'] : '—';
                            ?>
                        </div>
                        <div class="stat-lbl">Users</div>
                    </div>
                    <div class="ap-stat">
                        <div class="stat-val">
                            <?php
                            $r4 = $conn->query("SELECT COUNT(*) AS c FROM bookings");
                            echo $r4 ? $r4->fetch_assoc()['c'] : '—';
                            ?>
                        </div>
                        <div class="stat-lbl">Bookings</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Right: Details card ── -->
        <div class="ap-details-card">
            <div class="ap-details-header">
                <div class="ap-details-header-left">
                    <div class="ap-details-header-icon">📋</div>
                    <div>
                        <h2>Profile Details</h2>
                        <span>Click Edit to make changes</span>
                    </div>
                </div>
                <button type="button" class="ap-edit-btn" id="apEditBtn" onclick="apStartEdit()">
                    ✏️ Edit Profile
                </button>
            </div>

            <div class="ap-details-body" id="apDetailsBody">
                <form id="apForm">

                    <!-- Personal -->
                    <div class="ap-section">Personal Information</div>
                    <div class="ap-grid three">
                        <div class="ap-field">
                            <label>Full Name</label>
                            <input type="text" id="name" class="ap-input"
                                   value="<?= htmlspecialchars($admin['name'] ?? '') ?>" readonly>
                        </div>
                        <div class="ap-field">
                            <label>Age</label>
                            <input type="number" id="age" class="ap-input"
                                   value="<?= htmlspecialchars($admin['age'] ?? '') ?>"
                                   min="18" max="80" readonly>
                        </div>
                        <div class="ap-field">
                            <label>Date of Birth</label>
                            <input type="date" id="dob" class="ap-input"
                                   value="<?= htmlspecialchars($admin['dob'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="ap-section">Contact Details</div>
                    <div class="ap-grid">
                        <div class="ap-field">
                            <label>Phone Number</label>
                            <input type="tel" id="phone" class="ap-input"
                                   value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" readonly>
                        </div>
                        <div class="ap-field">
                            <label>Email Address</label>
                            <input type="email" id="email" class="ap-input"
                                   value="<?= htmlspecialchars($admin['email'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <div class="ap-grid" style="margin-top:16px;">
                        <div class="ap-field">
                            <label>Address</label>
                            <input type="text" id="address" class="ap-input"
                                   value="<?= htmlspecialchars($admin['address'] ?? '') ?>" readonly>
                        </div>
                        <div class="ap-field">
                            <label>Gender</label>
                            <div class="ap-sel">
                                <select id="gender" class="ap-input" disabled>
                                    <option <?= ($admin['gender'] ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
                                    <option <?= ($admin['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option <?= ($admin['gender'] ?? '') === 'Other'  ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Footer actions (hidden until edit mode) -->
                    <div class="ap-footer">
                        <button type="button" class="ap-btn-cancel" id="apCancelBtn" onclick="apCancelEdit()">
                            ✕ Cancel
                        </button>
                        <button type="button" class="ap-btn-save" id="apSaveBtn" onclick="apSave()">
                            💾 Save Changes
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<script>
// Store original values for cancel
let apOriginals = {};

function apStartEdit() {
    // Save originals
    ['name','age','dob','phone','email','address','gender'].forEach(id => {
        apOriginals[id] = document.getElementById(id).value;
    });

    // Unlock fields
    ['name','age','dob','phone','email','address'].forEach(id => {
        document.getElementById(id).removeAttribute('readonly');
    });
    document.getElementById('gender').removeAttribute('disabled');

    // Show save/cancel, update edit button
    document.getElementById('apSaveBtn').style.display   = 'flex';
    document.getElementById('apCancelBtn').style.display = 'block';
    document.getElementById('apEditBtn').style.display   = 'none';
    document.getElementById('apDetailsBody').classList.add('ap-editing');

    // Focus first field
    document.getElementById('name').focus();
}

function apCancelEdit() {
    // Restore originals
    ['name','age','dob','phone','email','address','gender'].forEach(id => {
        document.getElementById(id).value = apOriginals[id];
    });
    apLockFields();
}

function apLockFields() {
    ['name','age','dob','phone','email','address'].forEach(id => {
        document.getElementById(id).setAttribute('readonly', true);
    });
    document.getElementById('gender').setAttribute('disabled', true);

    document.getElementById('apSaveBtn').style.display   = 'none';
    document.getElementById('apCancelBtn').style.display = 'none';
    document.getElementById('apEditBtn').style.display   = 'flex';
    document.getElementById('apDetailsBody').classList.remove('ap-editing');
}

function apSave() {
    const saveBtn = document.getElementById('apSaveBtn');
    saveBtn.textContent = '⏳ Saving…';
    saveBtn.disabled = true;

    const data = new URLSearchParams({
        name:    document.getElementById('name').value,
        age:     document.getElementById('age').value,
        dob:     document.getElementById('dob').value,
        address: document.getElementById('address').value,
        phone:   document.getElementById('phone').value,
        gender:  document.getElementById('gender').value,
        email:   document.getElementById('email').value,
    });

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../controller/profileController.php', true);
    xhr.setRequestHeader('X-HTTP-Method-Override', 'PUT');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function () {
        saveBtn.innerHTML = '💾 Save Changes';
        saveBtn.disabled = false;

        if (xhr.status === 200) {
            // Update sidebar name
            document.getElementById('sidebarName').textContent = document.getElementById('name').value;
            apLockFields();
            apShowFlash('success', 'Profile updated successfully!');
        } else {
            apShowFlash('error', 'Failed to save. Please try again.');
        }
    };

    xhr.onerror = function () {
        saveBtn.innerHTML = '💾 Save Changes';
        saveBtn.disabled = false;
        apShowFlash('error', 'Network error. Please try again.');
    };

    xhr.send(data);
}

function apShowFlash(type, msg) {
    const flash = document.getElementById('apFlash');
    flash.className = 'ap-flash ' + type;
    flash.querySelector('span:first-child').textContent = type === 'success' ? '✅' : '❌';
    document.getElementById('apFlashMsg').textContent = msg;
    flash.style.display = 'flex';
    setTimeout(() => { flash.style.display = 'none'; }, 4000);
}
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
