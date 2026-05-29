<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// DB and update MUST run before any output
include_once("../model/db_conn.php");

// Guard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /flight_booking/view/login.php"); exit;
}

// Guard — need a valid id
if (empty($_GET['id'])) {
    header("Location: /flight_booking/view/addAirline.php"); exit;
}
$airline_id = (int)$_GET['id'];

// ── Handle update (PRG) ──────────────────────────────────────
if (isset($_POST['update'])) {
    $id              = (int)$_POST['id'];
    $airline_name    = $conn->real_escape_string(trim($_POST['airline_name']));
    $country_name    = $conn->real_escape_string(trim($_POST['country_name']));
    $airline_code    = $conn->real_escape_string(trim($_POST['airline_code']));
    $airline_details = $conn->real_escape_string(trim($_POST['airline_details']));
    $website         = $conn->real_escape_string(trim($_POST['website']         ?? ''));
    $founded_year    = !empty($_POST['founded_year']) ? (int)$_POST['founded_year'] : null;
    $fleet_size      = !empty($_POST['fleet_size'])   ? (int)$_POST['fleet_size']   : null;
    $status          = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    // Image upload
    $image_sql = '';
    if (!empty($_FILES['image']['name'])) {
        $upload_dir = __DIR__ . "/onload/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $image_name = time() . '_' . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name)) {
                $image_sql = ", image = '$image_name'";
            }
        }
    }

    $fy_sql = $founded_year ? $founded_year : 'NULL';
    $fs_sql = $fleet_size   ? $fleet_size   : 'NULL';

    $sql = "UPDATE airlines SET
                airline_name='$airline_name', country_name='$country_name',
                airline_code='$airline_code', airline_details='$airline_details',
                website='$website', founded_year=$fy_sql, fleet_size=$fs_sql,
                status='$status' $image_sql
            WHERE id=$id";

    if ($conn->query($sql)) {
        $_SESSION['airline_msg']      = 'Airline updated successfully!';
        $_SESSION['airline_msg_type'] = 'success';
    } else {
        $_SESSION['airline_msg']      = 'Update error: ' . $conn->error;
        $_SESSION['airline_msg_type'] = 'error';
    }
    header("Location: /flight_booking/view/addAirline.php"); exit;
}

// ── Fetch airline ────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM airlines WHERE id = ?");
$stmt->bind_param("i", $airline_id);
$stmt->execute();
$airline = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$airline) {
    header("Location: /flight_booking/view/addAirline.php"); exit;
}

// Now safe to output HTML
include("../includes/adminheader.php");
?>
<style>
.ea-page { flex:1; padding:32px 32px 60px; max-width:860px; width:100%; margin:0 auto; }

/* Back */
.ea-back { display:inline-flex; align-items:center; gap:7px; color:#64748b; text-decoration:none; font-size:0.85rem; font-weight:600; margin-bottom:22px; transition:color 0.15s; }
.ea-back:hover { color:#0b72e6; }

/* Title */
.ea-titlebar { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
.ea-titlebar-icon { width:52px; height:52px; background:linear-gradient(135deg,#0b72e6,#6c3de8); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 4px 14px rgba(11,114,230,0.3); flex-shrink:0; }
.ea-titlebar h1 { font-size:1.4rem; font-weight:800; color:#0f172a; letter-spacing:-0.4px; }
.ea-titlebar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }

/* Card */
.ea-card { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,0.1); border:1px solid #e8f0fb; overflow:hidden; }
.ea-card-header { background:linear-gradient(135deg,#0b72e6,#6c3de8); padding:20px 28px; display:flex; align-items:center; gap:14px; }
.ea-card-header-icon { width:44px; height:44px; background:rgba(255,255,255,0.2); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.ea-card-header h2 { color:#fff; font-size:1.05rem; font-weight:700; margin:0; }
.ea-card-header span { color:rgba(255,255,255,0.72); font-size:0.78rem; display:block; margin-top:2px; }

/* Preview bar */
.ea-preview-bar { display:flex; align-items:center; gap:16px; padding:18px 28px; background:#fafcff; border-bottom:1px solid #f1f5f9; }
.ea-preview-bar img { width:80px; height:80px; object-fit:contain; border-radius:12px; border:2px solid #e8f0fb; background:#f0f4fb; padding:6px; box-shadow:0 2px 10px rgba(0,0,0,0.08); }
.ea-preview-bar .ep-info h4 { font-size:0.9rem; font-weight:700; color:#0f172a; }
.ea-preview-bar .ep-info p  { font-size:0.78rem; color:#64748b; margin-top:3px; }

.ea-card-body { padding:28px; }

/* Section */
.ea-section { font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin:22px 0 14px; display:flex; align-items:center; gap:8px; }
.ea-section:first-child { margin-top:0; }
.ea-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }

/* Grid */
.ea-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

/* Field */
.ea-field { margin-bottom:16px; }
.ea-field label { display:block; font-size:0.74rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:6px; }
.ea-field label .req { color:#e53e3e; margin-left:2px; }
.ea-wrap { position:relative; }
.ea-wrap .ea-ico { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:0.9rem; pointer-events:none; line-height:1; z-index:1; }
.ea-wrap.ta-wrap .ea-ico { top:12px; transform:none; }
.ea-wrap input, .ea-wrap textarea, .ea-wrap select {
    width:100%; padding:11px 13px 11px 40px;
    border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:0.9rem; color:#1e293b; background:#f8fafc;
    transition:border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline:none; font-family:inherit; appearance:none;
}
.ea-wrap input:focus, .ea-wrap textarea:focus, .ea-wrap select:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.ea-wrap input::placeholder, .ea-wrap textarea::placeholder { color:#b0bec5; }
.ea-wrap textarea { resize:vertical; min-height:90px; padding-top:11px; }
.ea-sel-wrap::after { content:'▾'; position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; font-size:0.8rem; }
.ea-sel-wrap select { padding-right:30px; cursor:pointer; }

/* File zone */
.ea-file-zone { border:2px dashed #c7d8f0; border-radius:10px; padding:18px 14px; text-align:center; background:#f8fafc; cursor:pointer; transition:border-color 0.2s, background 0.2s; position:relative; }
.ea-file-zone:hover { border-color:#0b72e6; background:#f0f7ff; }
.ea-file-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.ea-file-zone .fz-icon { font-size:1.6rem; display:block; margin-bottom:4px; }
.ea-file-zone .fz-text { font-size:0.78rem; color:#64748b; line-height:1.5; }
.ea-file-zone .fz-text b { color:#0b72e6; }

/* Actions */
.ea-actions { display:flex; gap:12px; margin-top:28px; padding-top:22px; border-top:1px solid #f1f5f9; }
.ea-btn-save { flex:1; padding:13px; background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; border:none; border-radius:11px; font-size:0.95rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s; box-shadow:0 4px 16px rgba(11,114,230,0.3); font-family:inherit; }
.ea-btn-save:hover { opacity:0.9; transform:translateY(-2px); }
.ea-btn-save:active { transform:translateY(0); }
.ea-btn-cancel { padding:13px 24px; background:#f8fafc; color:#64748b; border:1.5px solid #e2e8f0; border-radius:11px; font-size:0.95rem; font-weight:600; cursor:pointer; text-decoration:none; display:flex; align-items:center; gap:7px; transition:background 0.15s, color 0.15s; font-family:inherit; }
.ea-btn-cancel:hover { background:#f1f5f9; color:#334155; }

@media (max-width:640px) { .ea-page { padding:16px 14px 40px; } .ea-row { grid-template-columns:1fr; } .ea-actions { flex-direction:column; } }
</style>

<div class="ea-page">

    <a href="/flight_booking/view/addAirline.php" class="ea-back">← Back to Airlines</a>

    <div class="ea-titlebar">
        <div class="ea-titlebar-icon">✏️</div>
        <div>
            <h1>Edit Airline</h1>
            <p>Update details for <strong><?= htmlspecialchars($airline['airline_name']) ?></strong></p>
        </div>
    </div>

    <div class="ea-card">
        <div class="ea-card-header">
            <div class="ea-card-header-icon">✈️</div>
            <div>
                <h2><?= htmlspecialchars($airline['airline_name']) ?></h2>
                <span><?= htmlspecialchars($airline['country_name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($airline['airline_code']) ?></span>
            </div>
        </div>

        <!-- Current logo preview -->
        <div class="ea-preview-bar">
            <img src="onload/<?= htmlspecialchars(basename($airline['image'])) ?>"
                 alt="<?= htmlspecialchars($airline['airline_name']) ?>" id="eaImgPreview">
            <div class="ep-info">
                <h4>Current Logo</h4>
                <p>Upload a new file below to replace it</p>
            </div>
        </div>

        <div class="ea-card-body">
            <form action="/flight_booking/view/editAirline.php?id=<?= $airline_id ?>"
                  method="POST" enctype="multipart/form-data">

                <input type="hidden" name="id" value="<?= (int)$airline['id'] ?>">

                <!-- Basic Info -->
                <div class="ea-section">Basic Information</div>

                <div class="ea-field">
                    <label>Airline Name <span class="req">*</span></label>
                    <div class="ea-wrap">
                        <span class="ea-ico">✈️</span>
                        <input type="text" name="airline_name"
                               value="<?= htmlspecialchars($airline['airline_name']) ?>" required>
                    </div>
                </div>

                <div class="ea-row">
                    <div class="ea-field">
                        <label>Country <span class="req">*</span></label>
                        <div class="ea-wrap">
                            <span class="ea-ico">🌍</span>
                            <input type="text" name="country_name"
                                   value="<?= htmlspecialchars($airline['country_name']) ?>" required>
                        </div>
                    </div>
                    <div class="ea-field">
                        <label>IATA Code <span class="req">*</span></label>
                        <div class="ea-wrap">
                            <span class="ea-ico">🔤</span>
                            <input type="text" name="airline_code" maxlength="3"
                                   value="<?= htmlspecialchars($airline['airline_code']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="ea-field">
                    <label>Description <span class="req">*</span></label>
                    <div class="ea-wrap ta-wrap">
                        <span class="ea-ico">📝</span>
                        <textarea name="airline_details" required><?= htmlspecialchars($airline['airline_details']) ?></textarea>
                    </div>
                </div>

                <!-- Additional Details -->
                <div class="ea-section">Additional Details</div>

                <div class="ea-field">
                    <label>Website</label>
                    <div class="ea-wrap">
                        <span class="ea-ico">🌐</span>
                        <input type="url" name="website"
                               value="<?= htmlspecialchars($airline['website'] ?? '') ?>"
                               placeholder="https://www.airline.com">
                    </div>
                </div>

                <div class="ea-row">
                    <div class="ea-field">
                        <label>Founded Year</label>
                        <div class="ea-wrap">
                            <span class="ea-ico">📅</span>
                            <input type="number" name="founded_year" min="1900" max="<?= date('Y') ?>"
                                   value="<?= htmlspecialchars($airline['founded_year'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="ea-field">
                        <label>Fleet Size</label>
                        <div class="ea-wrap">
                            <span class="ea-ico">🛩️</span>
                            <input type="number" name="fleet_size" min="1"
                                   value="<?= htmlspecialchars($airline['fleet_size'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="ea-field">
                    <label>Status</label>
                    <div class="ea-wrap ea-sel-wrap">
                        <span class="ea-ico">🔘</span>
                        <select name="status">
                            <option value="active"   <?= ($airline['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>✅ Active</option>
                            <option value="inactive" <?= ($airline['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>❌ Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Logo -->
                <div class="ea-section">Replace Logo <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#b0bec5">(optional)</span></div>

                <div class="ea-field">
                    <div class="ea-file-zone" id="eaFileZone">
                        <input type="file" name="image" accept="image/*" onchange="eaPreview(this)">
                        <span class="fz-icon">🖼️</span>
                        <p class="fz-text" id="eaFileLabel">
                            <b>Click to upload</b> a new logo<br>Leave empty to keep current
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="ea-actions">
                    <a href="/flight_booking/view/addAirline.php" class="ea-btn-cancel">✕ Cancel</a>
                    <button type="submit" name="update" class="ea-btn-save">💾 Save Changes</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function eaPreview(input) {
    const label   = document.getElementById('eaFileLabel');
    const zone    = document.getElementById('eaFileZone');
    const preview = document.getElementById('eaImgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
        label.innerHTML = '✅ <b>' + input.files[0].name + '</b>';
        zone.style.borderColor = '#16a34a';
        zone.style.background  = '#f0fdf4';
    }
}
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
