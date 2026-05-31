<?php
// ── Everything runs here — no external controller/model ──────
if (session_status() === PHP_SESSION_NONE) session_start();

// Guard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /flight_booking/view/login.php"); exit;
}

require_once __DIR__ . "/../model/db_conn.php";

// ── Ensure notifications table exists ────────────────────────
$notif_check = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'notifications' LIMIT 1");
if (!$notif_check || $notif_check->num_rows === 0) {
    $create_notif = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        flight_id INT,
        message VARCHAR(500) NOT NULL,
        type VARCHAR(50) DEFAULT 'flight_update',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES login(id) ON DELETE CASCADE,
        FOREIGN KEY (flight_id) REFERENCES flights(id) ON DELETE SET NULL,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created_at (created_at)
    )";
    $conn->query($create_notif);
}

// ── DELETE ───────────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    // delete image file
    $s = $conn->prepare("SELECT image FROM flights WHERE id=?");
    $s->bind_param("i", $id); $s->execute();
    $row = $s->get_result()->fetch_assoc(); $s->close();
    if ($row && !empty($row['image'])) {
        $p = __DIR__ . "/upload/" . $row['image'];
        if (file_exists($p)) unlink($p);
    }
    $s = $conn->prepare("DELETE FROM flights WHERE id=?");
    $s->bind_param("i", $id); $s->execute(); $s->close();
    $_SESSION['flight_msg']      = "Flight deleted.";
    $_SESSION['flight_msg_type'] = "success";
    header("Location: /flight_booking/view/addFlight.php"); exit;
}

// ── ADD ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flight_name    = trim($_POST['flight_name']);
    $airline_name   = trim($_POST['airline_name']);
    $flight_code    = trim($_POST['flight_code']);
    $departure      = trim($_POST['departure']);
    $arrival        = trim($_POST['arrival']);
    $departure_time = $_POST['departure_time'];
    $arrival_time   = $_POST['arrival_time'];
    $duration       = trim($_POST['duration']);
    $price          = (float)$_POST['price'];
    $flight_class   = $_POST['flight_class'];
    $seat_class     = $_POST['flight_class']; // same as flight_class on creation
    $total_seats    = (int)$_POST['total_seats'];
    $seat           = $total_seats;           // available = total on creation
    $discount_pct   = 0.00;
    $status         = $_POST['status'];

    // Upload image
    $uploadDir = __DIR__ . "/upload/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $image = time() . '_' . basename($_FILES['image']['name']);
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image)) {
        $_SESSION['flight_msg']      = "Image upload failed. Check upload folder permissions.";
        $_SESSION['flight_msg_type'] = "error";
        header("Location: /flight_booking/view/addFlight.php"); exit;
    }

    // INSERT — exact column order matching the schema
    $sql = "INSERT INTO flights
                (flight_name, airline_name, flight_code, departure, arrival,
                 departure_time, arrival_time, duration, price,
                 flight_class, seat_class, total_seats, seat, discount_pct, status, image)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['flight_msg']      = "DB prepare error: " . $conn->error;
        $_SESSION['flight_msg_type'] = "error";
        header("Location: /flight_booking/view/addFlight.php"); exit;
    }

    // Types: s s s s s  s  s  s  d  s  s  i  i  d  s  s  = 16 params, 16 chars
    $stmt->bind_param("ssssssssdssiidss",
        $flight_name, $airline_name, $flight_code,
        $departure, $arrival,
        $departure_time, $arrival_time, $duration,
        $price,
        $flight_class, $seat_class,
        $total_seats, $seat, $discount_pct,
        $status, $image
    );

    try {
        if ($stmt->execute()) {
            $flight_id = $conn->insert_id;
            
            // Send notifications to all managers about the new flight
            $mgr_sql = "SELECT id FROM login WHERE role = 'manager'";
            $mgr_res = $conn->query($mgr_sql);
            if ($mgr_res) {
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, flight_id, message, type) VALUES (?, ?, ?, 'flight_update')");
                $notif_msg = "New flight '" . htmlspecialchars($flight_name) . "' (" . htmlspecialchars($flight_code) . ") needs schedule details. Please update.";
                while ($mgr = $mgr_res->fetch_assoc()) {
                    $notif_stmt->bind_param("iss", $mgr['id'], $flight_id, $notif_msg);
                    $notif_stmt->execute();
                }
                $notif_stmt->close();
            }
            
            $_SESSION['flight_msg']      = "Flight added successfully!";
            $_SESSION['flight_msg_type'] = "success";
        } else {
            $_SESSION['flight_msg']      = "DB error: " . $stmt->error;
            $_SESSION['flight_msg_type'] = "error";
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            $_SESSION['flight_msg']      = "Error: Flight code '" . htmlspecialchars($flight_code) . "' already exists.";
        } else {
            $_SESSION['flight_msg']      = "DB error: " . $e->getMessage();
        }
        $_SESSION['flight_msg_type'] = "error";
        // Clean up the uploaded file if database insertion failed
        $p = $uploadDir . $image;
        if (file_exists($p)) unlink($p);
    }
    $stmt->close();
    header("Location: /flight_booking/view/addFlight.php"); exit;
}

// ── Consume flash ────────────────────────────────────────────
$flash_msg  = $_SESSION['flight_msg']      ?? '';
$flash_type = $_SESSION['flight_msg_type'] ?? '';
unset($_SESSION['flight_msg'], $_SESSION['flight_msg_type']);

// ── Fetch flights ────────────────────────────────────────────
$flights = [];
$res = $conn->query("SELECT * FROM flights ORDER BY id DESC");
if ($res) while ($r = $res->fetch_assoc()) $flights[] = $r;

include __DIR__ . "/../includes/adminheader.php";
?>
<style>
.fl-page { flex:1; padding:32px 32px 60px; max-width:1400px; width:100%; margin:0 auto; }
.fl-titlebar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.fl-titlebar-left { display:flex; align-items:center; gap:14px; }
.fl-titlebar-icon { width:52px; height:52px; background:linear-gradient(135deg,#0b72e6,#6c3de8); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 4px 14px rgba(11,114,230,0.3); flex-shrink:0; }
.fl-titlebar h1 { font-size:1.4rem; font-weight:800; color:#0f172a; letter-spacing:-0.4px; }
.fl-titlebar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }
.fl-count-pill { background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; font-size:0.8rem; font-weight:700; padding:6px 16px; border-radius:20px; box-shadow:0 2px 10px rgba(11,114,230,0.25); white-space:nowrap; }
.fl-flash { display:flex; align-items:center; gap:10px; padding:13px 18px; border-radius:12px; font-size:0.88rem; font-weight:600; margin-bottom:20px; animation:flFadeIn 0.3s ease; }
@keyframes flFadeIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.fl-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.fl-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.fl-flash .fl-close { margin-left:auto; cursor:pointer; opacity:0.5; font-size:1rem; background:none; border:none; color:inherit; padding:0; font-family:inherit; }
.fl-layout { display:grid; grid-template-columns:420px 1fr; gap:28px; align-items:start; }
.fl-form-panel { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,0.1); border:1px solid #e8f0fb; overflow:hidden; position:sticky; top:76px; }
.fl-form-header { background:linear-gradient(135deg,#0b72e6,#6c3de8); padding:20px 24px; display:flex; align-items:center; gap:12px; }
.fl-form-header-icon { width:40px; height:40px; background:rgba(255,255,255,0.2); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.fl-form-header h2 { color:#fff; font-size:1rem; font-weight:700; margin:0; }
.fl-form-header span { color:rgba(255,255,255,0.7); font-size:0.76rem; display:block; margin-top:2px; }
.fl-form-body { padding:22px 24px 24px; }
.fl-section { font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin:18px 0 12px; display:flex; align-items:center; gap:8px; }
.fl-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }
.fl-field { margin-bottom:14px; }
.fl-field label { display:block; font-size:0.74rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:5px; }
.fl-field label .req { color:#e53e3e; margin-left:2px; }
.fl-wrap { position:relative; }
.fl-wrap .fl-ico { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:0.9rem; pointer-events:none; line-height:1; z-index:1; }
.fl-wrap input, .fl-wrap select { width:100%; padding:10px 12px 10px 38px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.88rem; color:#1e293b; background:#f8fafc; transition:border-color 0.2s,box-shadow 0.2s,background 0.2s; outline:none; font-family:inherit; appearance:none; }
.fl-wrap input:focus, .fl-wrap select:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.fl-wrap input::placeholder { color:#b0bec5; }
.fl-sel-wrap::after { content:'▾'; position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; font-size:0.8rem; }
.fl-sel-wrap select { padding-right:30px; cursor:pointer; }
.fl-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.fl-file-zone { border:2px dashed #c7d8f0; border-radius:10px; padding:16px 14px; text-align:center; background:#f8fafc; cursor:pointer; transition:border-color 0.2s,background 0.2s; position:relative; }
.fl-file-zone:hover { border-color:#0b72e6; background:#f0f7ff; }
.fl-file-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.fl-file-zone .fz-icon { font-size:1.6rem; display:block; margin-bottom:4px; }
.fl-file-zone .fz-text { font-size:0.78rem; color:#64748b; line-height:1.5; }
.fl-file-zone .fz-text b { color:#0b72e6; }
.fl-submit { width:100%; padding:13px; background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; border:none; border-radius:11px; font-size:0.95rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity 0.2s,transform 0.15s,box-shadow 0.2s; box-shadow:0 4px 16px rgba(11,114,230,0.3); margin-top:10px; font-family:inherit; }
.fl-submit:hover { opacity:0.9; transform:translateY(-2px); }
.fl-list-panel { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,0.1); border:1px solid #e8f0fb; overflow:hidden; }
.fl-list-header { padding:16px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; background:#fafcff; }
.fl-list-header h2 { font-size:1rem; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px; }
.fl-list-header h2::before { content:''; display:inline-block; width:3px; height:1.1em; background:linear-gradient(180deg,#0b72e6,#6c3de8); border-radius:3px; }
.fl-list-body { padding:20px 24px 24px; }
.fl-search-wrap { position:relative; margin-bottom:18px; }
.fl-search-wrap input { width:100%; padding:10px 14px 10px 38px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.88rem; background:#f8fafc; outline:none; font-family:inherit; color:#1e293b; transition:border-color 0.2s,box-shadow 0.2s; }
.fl-search-wrap input:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.fl-search-wrap .s-ico { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:0.9rem; pointer-events:none; }
.fl-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:18px; }
.fl-card { border:1px solid #e8f0fb; border-radius:16px; overflow:hidden; background:#fff; transition:transform 0.22s,box-shadow 0.22s; display:flex; flex-direction:column; }
.fl-card:hover { transform:translateY(-5px); box-shadow:0 12px 32px rgba(11,114,230,0.14); }
.fl-card-img { height:110px; background:linear-gradient(135deg,#eef4ff,#f3eeff); display:flex; align-items:center; justify-content:center; padding:12px; position:relative; overflow:hidden; }
.fl-card-img img { max-width:100%; max-height:86px; object-fit:cover; border-radius:10px; box-shadow:0 2px 12px rgba(0,0,0,0.1); }
.fl-status-badge { position:absolute; top:8px; right:8px; font-size:0.65rem; font-weight:700; padding:3px 8px; border-radius:20px; text-transform:uppercase; }
.fl-status-badge.active   { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
.fl-status-badge.inactive { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; }
.fl-status-badge.cancelled{ background:#fef9c3; color:#92400e; border:1px solid #fde68a; }
.fl-card-body { padding:14px 16px 10px; flex:1; display:flex; flex-direction:column; gap:7px; }
.fl-card-body h3 { font-size:0.95rem; font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fl-route { display:flex; align-items:center; gap:6px; font-size:0.82rem; font-weight:600; color:#334155; }
.fl-route .route-arrow { color:#0b72e6; }
.fl-tags { display:flex; flex-wrap:wrap; gap:4px; }
.fl-tag { font-size:0.7rem; font-weight:600; padding:2px 8px; border-radius:20px; display:inline-flex; align-items:center; gap:3px; }
.fl-tag.blue   { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
.fl-tag.green  { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
.fl-tag.purple { background:#f5f3ff; color:#7c3aed; border:1px solid #ddd6fe; }
.fl-tag.amber  { background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
.fl-tag.rose   { background:#fff1f2; color:#be123c; border:1px solid #fecdd3; }
.fl-price { font-size:1.05rem; font-weight:800; color:#0b72e6; display:flex; align-items:baseline; gap:3px; }
.fl-price span { font-size:0.75rem; font-weight:500; color:#64748b; }
.fl-card-actions { padding:10px 14px 13px; display:flex; gap:8px; }
.fl-btn { flex:1; padding:8px 0; border-radius:9px; font-size:0.77rem; font-weight:600; text-decoration:none; text-align:center; cursor:pointer; border:1.5px solid transparent; transition:all 0.18s; display:flex; align-items:center; justify-content:center; gap:4px; font-family:inherit; }
.fl-btn.edit { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
.fl-btn.edit:hover { background:#2563eb; color:#fff; border-color:#2563eb; }
.fl-btn.del  { background:#fff5f5; color:#dc2626; border-color:#fecaca; }
.fl-btn.del:hover  { background:#dc2626; color:#fff; border-color:#dc2626; }
.fl-empty { grid-column:1/-1; text-align:center; padding:50px 20px; color:#94a3b8; }
.fl-empty .fl-empty-icon { font-size:3rem; display:block; margin-bottom:10px; opacity:0.4; }
.fl-no-results { display:none; grid-column:1/-1; text-align:center; padding:30px 20px; color:#94a3b8; font-size:0.88rem; }
@media (max-width:1050px) { .fl-layout { grid-template-columns:1fr; } .fl-form-panel { position:static; } }
@media (max-width:600px) { .fl-page { padding:16px 14px 40px; } .fl-row { grid-template-columns:1fr; } .fl-grid { grid-template-columns:1fr; } }
</style>

<div class="fl-page">
    <div class="fl-titlebar">
        <div class="fl-titlebar-left">
            <div class="fl-titlebar-icon">🛫</div>
            <div>
                <h1>Flight Management</h1>
                <p>Add, edit and manage flights on the GoZayan platform</p>
            </div>
        </div>
        <span class="fl-count-pill">🛫 <?= count($flights) ?> Flight<?= count($flights) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if ($flash_msg): ?>
        <div class="fl-flash <?= htmlspecialchars($flash_type) ?>" id="flFlash">
            <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($flash_msg) ?>
            <button class="fl-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div class="fl-layout">
        <!-- Form Panel -->
        <div class="fl-form-panel">
            <div class="fl-form-header">
                <div class="fl-form-header-icon">➕</div>
                <div><h2>Add New Flight</h2><span>Fill in the flight details below</span></div>
            </div>
            <div class="fl-form-body">
                <form action="/flight_booking/view/addFlight.php" method="POST" enctype="multipart/form-data">

                    <div class="fl-section">Flight Identity</div>
                    <div class="fl-field">
                        <label>Flight Name <span class="req">*</span></label>
                        <div class="fl-wrap"><span class="fl-ico">✈️</span>
                            <input type="text" name="flight_name" placeholder="GoZayan Express 101" required>
                        </div>
                    </div>
                    <div class="fl-row">
                        <div class="fl-field">
                            <label>Airline Name <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🏢</span>
                                <input type="text" name="airline_name" placeholder="Malaysia Airlines" required>
                            </div>
                        </div>
                        <div class="fl-field">
                            <label>Flight Code <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🔤</span>
                                <input type="text" name="flight_code" placeholder="MH370" required>
                            </div>
                        </div>
                    </div>

                    <div class="fl-section">Route &amp; Schedule</div>
                    <div class="fl-row">
                        <div class="fl-field">
                            <label>Departure City <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🛫</span>
                                <input type="text" name="departure" placeholder="Kuala Lumpur" required>
                            </div>
                        </div>
                        <div class="fl-field">
                            <label>Arrival City <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🛬</span>
                                <input type="text" name="arrival" placeholder="Singapore" required>
                            </div>
                        </div>
                    </div>
                    <div class="fl-row">
                        <div class="fl-field">
                            <label>Departure Time <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🕐</span>
                                <input type="time" name="departure_time" required>
                            </div>
                        </div>
                        <div class="fl-field">
                            <label>Arrival Time <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🕔</span>
                                <input type="time" name="arrival_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="fl-field">
                        <label>Duration <span class="req">*</span></label>
                        <div class="fl-wrap"><span class="fl-ico">⏱️</span>
                            <input type="text" name="duration" placeholder="2h 30m" required>
                        </div>
                    </div>

                    <div class="fl-section">Pricing &amp; Class</div>
                    <div class="fl-row">
                        <div class="fl-field">
                            <label>Price (USD) <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">💵</span>
                                <input type="number" step="0.01" min="0" name="price" placeholder="450.00" required>
                            </div>
                        </div>
                        <div class="fl-field">
                            <label>Total Seats <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">💺</span>
                                <input type="number" min="1" name="total_seats" placeholder="180" required>
                            </div>
                        </div>
                    </div>
                    <div class="fl-row">
                        <div class="fl-field">
                            <label>Class <span class="req">*</span></label>
                            <div class="fl-wrap fl-sel-wrap"><span class="fl-ico">🎫</span>
                                <select name="flight_class">
                                    <option value="Economy">Economy</option>
                                    <option value="Business">Business</option>
                                    <option value="First Class">First Class</option>
                                </select>
                            </div>
                        </div>
                        <div class="fl-field">
                            <label>Status</label>
                            <div class="fl-wrap fl-sel-wrap"><span class="fl-ico">🔘</span>
                                <select name="status">
                                    <option value="active">✅ Active</option>
                                    <option value="inactive">❌ Inactive</option>
                                    <option value="cancelled">⚠️ Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="fl-section">Flight Image</div>
                    <div class="fl-field">
                        <div class="fl-file-zone" id="flFileZone">
                            <input type="file" name="image" required accept="image/*" onchange="updateFlFileLabel(this)">
                            <span class="fz-icon">🖼️</span>
                            <p class="fz-text" id="flFileLabel"><b>Click to upload</b> or drag &amp; drop<br>PNG, JPG, WEBP</p>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="fl-submit">➕ Add Flight</button>
                </form>
            </div>
        </div>

        <!-- List Panel -->
        <div class="fl-list-panel">
            <div class="fl-list-header">
                <h2>Existing Flights</h2>
                <span class="fl-count-pill"><?= count($flights) ?> total</span>
            </div>
            <div class="fl-list-body">
                <div class="fl-search-wrap">
                    <span class="s-ico">🔍</span>
                    <input type="text" id="flSearch" placeholder="Search flights…" oninput="filterFlights(this.value)">
                </div>
                <div class="fl-grid" id="flGrid">
                    <?php if (empty($flights)): ?>
                        <div class="fl-empty"><span class="fl-empty-icon">🛫</span><p>No flights yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($flights as $f):
                            $status = $f['status'] ?? 'active'; ?>
                        <div class="fl-card"
                             data-name="<?= strtolower(htmlspecialchars($f['flight_name'])) ?>"
                             data-airline="<?= strtolower(htmlspecialchars($f['airline_name'])) ?>"
                             data-code="<?= strtolower(htmlspecialchars($f['flight_code'])) ?>"
                             data-dep="<?= strtolower(htmlspecialchars($f['departure'])) ?>"
                             data-arr="<?= strtolower(htmlspecialchars($f['arrival'])) ?>">
                            <div class="fl-card-img">
                                <img src="upload/<?= htmlspecialchars($f['image']) ?>" alt="<?= htmlspecialchars($f['flight_name']) ?>">
                                <span class="fl-status-badge <?= $status ?>"><?= ucfirst($status) ?></span>
                            </div>
                            <div class="fl-card-body">
                                <h3><?= htmlspecialchars($f['flight_name']) ?></h3>
                                <div class="fl-route">
                                    <?= htmlspecialchars($f['departure']) ?>
                                    <span class="route-arrow">→</span>
                                    <?= htmlspecialchars($f['arrival']) ?>
                                </div>
                                <div class="fl-tags">
                                    <span class="fl-tag blue">🏢 <?= htmlspecialchars($f['airline_name']) ?></span>
                                    <span class="fl-tag purple">🔤 <?= htmlspecialchars($f['flight_code']) ?></span>
                                    <span class="fl-tag amber">⏱️ <?= htmlspecialchars($f['duration']) ?></span>
                                    <span class="fl-tag green">🎫 <?= htmlspecialchars($f['flight_class']) ?></span>
                                    <span class="fl-tag rose">💺 <?= (int)$f['total_seats'] ?> seats</span>
                                </div>
                                <div class="fl-price">$<?= number_format((float)$f['price'], 2) ?><span>/ person</span></div>
                            </div>
                            <div class="fl-card-actions">
                                <a href="editFlight.php?id=<?= $f['id'] ?>" class="fl-btn edit">✏️ Edit</a>
                                <a href="?delete_id=<?= $f['id'] ?>" class="fl-btn del"
                                   onclick="return confirm('Delete <?= htmlspecialchars(addslashes($f['flight_name'])) ?>?')">🗑️ Delete</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="fl-no-results" id="flNoResults">No flights match your search.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFlFileLabel(input) {
    const label = document.getElementById('flFileLabel');
    const zone  = document.getElementById('flFileZone');
    if (input.files && input.files[0]) {
        label.innerHTML = '✅ <b>' + input.files[0].name + '</b>';
        zone.style.borderColor = '#16a34a';
        zone.style.background  = '#f0fdf4';
    }
}
function filterFlights(q) {
    q = q.toLowerCase().trim();
    const cards = document.querySelectorAll('#flGrid .fl-card');
    const noRes = document.getElementById('flNoResults');
    let v = 0;
    cards.forEach(c => {
        const m = !q || c.dataset.name.includes(q) || c.dataset.airline.includes(q)
                     || c.dataset.code.includes(q) || c.dataset.dep.includes(q) || c.dataset.arr.includes(q);
        c.style.display = m ? '' : 'none';
        if (m) v++;
    });
    if (noRes) noRes.style.display = (v === 0 && q) ? 'block' : 'none';
}
const flFlash = document.getElementById('flFlash');
if (flFlash) setTimeout(() => flFlash.remove(), 4000);
</script>

</body>
</html>
<?php include __DIR__ . "/../includes/footer.php"; ?>