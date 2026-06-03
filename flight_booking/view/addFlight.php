<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/base_url.php";
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/view/login.php"); exit;
}
require_once __DIR__ . "/../model/db_conn.php";

// ── Ensure notifications table exists ────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
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
)");

// ── DELETE ───────────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
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
    header("Location: " . BASE_URL . "/view/addFlight.php"); exit;
}

// ── ADD (multi-class) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flight_name    = trim($_POST['flight_name']);
    $airline_name   = trim($_POST['airline_name']);
    $flight_code    = strtoupper(trim($_POST['flight_code']));
    $departure      = trim($_POST['departure']);
    $arrival        = trim($_POST['arrival']);
    $departure_time = $_POST['departure_time'];
    $arrival_time   = $_POST['arrival_time'];
    $duration       = trim($_POST['duration']);
    $status         = $_POST['status'];

    // Handle image — URL or file upload
    $uploadDir = __DIR__ . "/upload/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $image_url = trim($_POST['image_url'] ?? '');
    $image = '';

    if (!empty($image_url) && str_starts_with($image_url, 'http')) {
        // Use URL directly
        $image = $image_url;
    } elseif (!empty($_FILES['image']['name'])) {
        // File upload
        $image = time() . '_' . basename($_FILES['image']['name']);
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image)) {
            $_SESSION['flight_msg']      = "Image upload failed.";
            $_SESSION['flight_msg_type'] = "error";
            header("Location: " . BASE_URL . "/view/addFlight.php"); exit;
        }
    }
    // $image can be empty — flight will show placeholder icon

    // Collect enabled classes
    $classes = ['Economy', 'Business', 'First Class'];
    $inserted = 0; $errors = [];

    $sql = "INSERT INTO flights
                (flight_name, airline_name, flight_code, departure, arrival,
                 departure_time, arrival_time, duration, price,
                 flight_class, seat_class, total_seats, seat, discount_pct, status, image)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);

    foreach ($classes as $cls) {
        $key = strtolower(str_replace(' ', '_', $cls)); // economy / business / first_class
        if (empty($_POST['enable_' . $key])) continue;  // skip if not checked

        $price       = (float)($_POST['price_'       . $key] ?? 0);
        $total_seats = (int)  ($_POST['seats_'       . $key] ?? 0);
        $discount    = (float)($_POST['discount_'    . $key] ?? 0);
        $seat        = $total_seats;

        $stmt->bind_param("ssssssssdssiidss",
            $flight_name, $airline_name, $flight_code,
            $departure, $arrival,
            $departure_time, $arrival_time, $duration,
            $price, $cls, $cls,
            $total_seats, $seat, $discount,
            $status, $image
        );

        try {
            if ($stmt->execute()) {
                $flight_id = (int)$conn->insert_id;
                // Notify managers — collect IDs first, then insert notifications
                $mgr_ids = [];
                $mgr_res = $conn->query("SELECT id FROM login WHERE role='manager'");
                if ($mgr_res) {
                    while ($mgr = $mgr_res->fetch_assoc()) {
                        $mgr_ids[] = (int)$mgr['id'];
                    }
                    $mgr_res->free();
                }
                if (!empty($mgr_ids)) {
                    $ns = $conn->prepare("INSERT INTO notifications (user_id,flight_id,message,type) VALUES (?,?,?,'flight_update')");
                    if ($ns) {
                        $nm = "New flight '{$flight_name}' ({$flight_code} · {$cls}) added. Please set schedule.";
                        foreach ($mgr_ids as $mgr_id) {
                            $ns->bind_param("iis", $mgr_id, $flight_id, $nm);
                            $ns->execute();
                        }
                        $ns->close();
                    }
                }
                $inserted++;
            } else {
                $errors[] = "$cls: " . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $errors[] = "$cls: already exists for code '$flight_code'.";
            } else {
                $errors[] = "$cls: " . $e->getMessage();
            }
        }
    }
    $stmt->close();

    if ($inserted > 0 && empty($errors)) {
        $_SESSION['flight_msg']      = "Flight added successfully with $inserted class" . ($inserted > 1 ? 'es' : '') . "!";
        $_SESSION['flight_msg_type'] = "success";
    } elseif ($inserted > 0) {
        $_SESSION['flight_msg']      = "$inserted class(es) added. Skipped: " . implode(' | ', $errors);
        $_SESSION['flight_msg_type'] = "success";
    } else {
        $_SESSION['flight_msg']      = "Nothing added. " . implode(' | ', $errors);
        $_SESSION['flight_msg_type'] = "error";
        // Clean up uploaded file if nothing was inserted
        if (!empty($image) && !str_starts_with($image, 'http') && file_exists($uploadDir . $image)) {
            unlink($uploadDir . $image);
        }
    }

    header("Location: " . BASE_URL . "/view/addFlight.php"); exit;
}

// ── Consume flash ─────────────────────────────────────────────
$flash_msg  = $_SESSION['flight_msg']      ?? '';
$flash_type = $_SESSION['flight_msg_type'] ?? '';
unset($_SESSION['flight_msg'], $_SESSION['flight_msg_type']);

// ── Fetch flights ─────────────────────────────────────────────
$flights = [];
$res = $conn->query("SELECT * FROM flights ORDER BY flight_code ASC, FIELD(flight_class,'Economy','Business','First Class')");
if ($res) while ($r = $res->fetch_assoc()) $flights[] = $r;

include __DIR__ . "/../includes/adminheader.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Flight — GoZayan Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f4fb; color: #1e293b; }

.fl-page { flex:1; padding:32px 32px 60px; max-width:1400px; width:100%; margin:0 auto; }

/* Title */
.fl-titlebar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.fl-titlebar-left { display:flex; align-items:center; gap:14px; }
.fl-titlebar-icon { width:52px; height:52px; background:linear-gradient(135deg,#0b72e6,#6c3de8); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 4px 14px rgba(11,114,230,0.3); flex-shrink:0; }
.fl-titlebar h1 { font-size:1.4rem; font-weight:800; color:#0f172a; letter-spacing:-0.4px; }
.fl-titlebar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }
.fl-count-pill { background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; font-size:0.8rem; font-weight:700; padding:6px 16px; border-radius:20px; white-space:nowrap; }

/* Flash */
.fl-flash { display:flex; align-items:center; gap:10px; padding:13px 18px; border-radius:12px; font-size:0.88rem; font-weight:600; margin-bottom:20px; animation:flFade .3s ease; }
@keyframes flFade { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.fl-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.fl-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.fl-flash .fl-close { margin-left:auto; cursor:pointer; opacity:.5; font-size:1rem; background:none; border:none; color:inherit; padding:0; }

/* Layout */
.fl-layout { display:grid; grid-template-columns:480px 1fr; gap:28px; align-items:start; }
.fl-form-panel { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,.1); border:1px solid #e8f0fb; overflow:hidden; position:sticky; top:76px; }
.fl-form-header { background:linear-gradient(135deg,#0b72e6,#6c3de8); padding:20px 24px; display:flex; align-items:center; gap:12px; }
.fl-form-header-icon { width:40px; height:40px; background:rgba(255,255,255,.2); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.fl-form-header h2 { color:#fff; font-size:1rem; font-weight:700; margin:0; }
.fl-form-header span { color:rgba(255,255,255,.7); font-size:.76rem; display:block; margin-top:2px; }
.fl-form-body { padding:22px 24px 24px; max-height:calc(100vh - 160px); overflow-y:auto; }

/* Section label */
.fl-section { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin:18px 0 12px; display:flex; align-items:center; gap:8px; }
.fl-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }

/* Fields */
.fl-field { margin-bottom:14px; }
.fl-field label { display:block; font-size:.74rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.6px; margin-bottom:5px; }
.fl-field label .req { color:#e53e3e; margin-left:2px; }
.fl-wrap { position:relative; }
.fl-wrap .fl-ico { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:.9rem; pointer-events:none; z-index:1; }
.fl-wrap input, .fl-wrap select {
    width:100%; padding:10px 12px 10px 38px;
    border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:.88rem; color:#1e293b; background:#f8fafc;
    transition:border-color .2s,box-shadow .2s,background .2s;
    outline:none; font-family:inherit; appearance:none;
}
.fl-wrap input:focus, .fl-wrap select:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,.1); }
.fl-wrap input::placeholder { color:#b0bec5; }
.fl-sel-wrap::after { content:'▾'; position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; font-size:.8rem; }
.fl-sel-wrap select { padding-right:30px; cursor:pointer; }
.fl-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

/* File zone */
.fl-file-zone { border:2px dashed #c7d8f0; border-radius:10px; padding:16px 14px; text-align:center; background:#f8fafc; cursor:pointer; transition:border-color .2s,background .2s; position:relative; }
.fl-file-zone:hover { border-color:#0b72e6; background:#f0f7ff; }
.fl-file-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.fl-file-zone .fz-icon { font-size:1.6rem; display:block; margin-bottom:4px; }
.fl-file-zone .fz-text { font-size:.78rem; color:#64748b; line-height:1.5; }
.fl-file-zone .fz-text b { color:#0b72e6; }

/* ── CLASS CARDS ── */
.class-cards { display:flex; flex-direction:column; gap:12px; }

.class-card {
    border:2px solid #e2e8f0; border-radius:14px; overflow:hidden;
    transition:border-color .2s, box-shadow .2s;
}
.class-card.enabled { border-color:#0b72e6; box-shadow:0 0 0 3px rgba(11,114,230,.08); }

.class-card-header {
    display:flex; align-items:center; gap:12px;
    padding:13px 16px; cursor:pointer;
    background:#f8fafc; user-select:none;
    transition:background .15s;
}
.class-card-header:hover { background:#f0f7ff; }
.class-card.enabled .class-card-header { background:#eff6ff; }

.class-toggle {
    width:20px; height:20px; border-radius:5px;
    border:2px solid #cbd5e1; background:#fff;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; transition:all .15s;
}
.class-card.enabled .class-toggle { background:#0b72e6; border-color:#0b72e6; }
.class-toggle i { color:#fff; font-size:.7rem; display:none; }
.class-card.enabled .class-toggle i { display:block; }

.class-icon { font-size:1.4rem; flex-shrink:0; }
.class-label { font-size:.92rem; font-weight:700; color:#0f172a; flex:1; }
.class-sublabel { font-size:.72rem; color:#64748b; }
.class-chevron { color:#94a3b8; font-size:.75rem; transition:transform .2s; }
.class-card.enabled .class-chevron { transform:rotate(180deg); }

.class-card-body {
    display:none; padding:16px;
    border-top:1px solid #e2e8f0;
    background:#fff;
}
.class-card.enabled .class-card-body { display:block; }

.class-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
.class-field { display:flex; flex-direction:column; gap:5px; }
.class-field label { font-size:.68rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.5px; }
.class-field input {
    padding:9px 11px; border:1.5px solid #e2e8f0; border-radius:9px;
    font-size:.88rem; color:#1e293b; background:#f8fafc;
    outline:none; font-family:inherit;
    transition:border-color .2s, box-shadow .2s;
}
.class-field input:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,.1); }
.class-field input::placeholder { color:#b0bec5; }

/* Color accents per class */
.class-card.economy  .class-card-header { }
.class-card.business .class-card-header { }
.class-card.first    .class-card-header { }
.class-card.economy.enabled  { border-color:#16a34a; box-shadow:0 0 0 3px rgba(22,163,74,.08); }
.class-card.economy.enabled  .class-card-header { background:#f0fdf4; }
.class-card.economy.enabled  .class-toggle { background:#16a34a; border-color:#16a34a; }
.class-card.business.enabled { border-color:#0b72e6; box-shadow:0 0 0 3px rgba(11,114,230,.08); }
.class-card.business.enabled .class-card-header { background:#eff6ff; }
.class-card.business.enabled .class-toggle { background:#0b72e6; border-color:#0b72e6; }
.class-card.first.enabled    { border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.08); }
.class-card.first.enabled    .class-card-header { background:#f5f3ff; }
.class-card.first.enabled    .class-toggle { background:#7c3aed; border-color:#7c3aed; }

/* Submit */
.fl-submit { width:100%; padding:13px; background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; border:none; border-radius:11px; font-size:.95rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity .2s,transform .15s,box-shadow .2s; box-shadow:0 4px 16px rgba(11,114,230,.3); margin-top:16px; font-family:inherit; }
.fl-submit:hover { opacity:.9; transform:translateY(-2px); }

/* List panel */
.fl-list-panel { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,.1); border:1px solid #e8f0fb; overflow:hidden; }
.fl-list-header { padding:16px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; background:#fafcff; }
.fl-list-header h2 { font-size:1rem; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px; }
.fl-list-header h2::before { content:''; display:inline-block; width:3px; height:1.1em; background:linear-gradient(180deg,#0b72e6,#6c3de8); border-radius:3px; }
.fl-list-body { padding:20px 24px 24px; }
.fl-search-wrap { position:relative; margin-bottom:18px; }
.fl-search-wrap input { width:100%; padding:10px 14px 10px 38px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:.88rem; background:#f8fafc; outline:none; font-family:inherit; color:#1e293b; transition:border-color .2s,box-shadow .2s; }
.fl-search-wrap input:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,.1); }
.fl-search-wrap .s-ico { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:.9rem; pointer-events:none; }

/* Flight group (same code) */
.fl-group { margin-bottom:20px; }
.fl-group-header {
    display:flex; align-items:center; gap:10px;
    padding:10px 14px; background:#f8fafc;
    border:1px solid #e8f0fb; border-radius:12px 12px 0 0;
    border-bottom:none;
}
.fl-group-code { font-family:monospace; font-size:.82rem; font-weight:800; color:#0b72e6; background:#eff6ff; padding:3px 10px; border-radius:20px; border:1px solid #bfdbfe; }
.fl-group-name { font-size:.9rem; font-weight:700; color:#0f172a; }
.fl-group-route { font-size:.78rem; color:#64748b; }
.fl-group-img { width:44px; height:44px; object-fit:contain; border-radius:50%; border:1.5px solid #e8f0fb; flex-shrink:0; background:#fff; padding:4px; box-shadow:0 1px 4px rgba(0,0,0,.08); }

.fl-class-rows { border:1px solid #e8f0fb; border-radius:0 0 12px 12px; overflow:hidden; }
.fl-class-row {
    display:flex; align-items:center; gap:14px;
    padding:11px 16px; border-bottom:1px solid #f1f5f9;
    transition:background .15s;
}
.fl-class-row:last-child { border-bottom:none; }
.fl-class-row:hover { background:#fafcff; }
.fl-class-badge { font-size:.7rem; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; flex-shrink:0; }
.fl-class-badge.Economy     { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
.fl-class-badge.Business    { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.fl-class-badge.First\ Class { background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; }
.fl-class-price { font-size:.95rem; font-weight:800; color:#0b72e6; min-width:80px; }
.fl-class-seats { font-size:.78rem; color:#64748b; flex:1; }
.fl-status-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.fl-status-dot.active    { background:#16a34a; }
.fl-status-dot.inactive  { background:#dc2626; }
.fl-status-dot.cancelled { background:#d97706; }
.fl-class-actions { display:flex; gap:6px; margin-left:auto; }
.fl-btn { padding:6px 12px; border-radius:8px; font-size:.75rem; font-weight:600; text-decoration:none; cursor:pointer; border:1.5px solid transparent; transition:all .18s; display:inline-flex; align-items:center; gap:4px; font-family:inherit; }
.fl-btn.edit { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
.fl-btn.edit:hover { background:#2563eb; color:#fff; }
.fl-btn.del  { background:#fff5f5; color:#dc2626; border-color:#fecaca; }
.fl-btn.del:hover  { background:#dc2626; color:#fff; }

.fl-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.fl-empty .fl-empty-icon { font-size:3rem; display:block; margin-bottom:10px; opacity:.4; }

/* Responsive */
@media(max-width:1100px) { .fl-layout { grid-template-columns:1fr; } .fl-form-panel { position:static; } }
@media(max-width:600px) {
    .fl-page { padding:16px 14px 80px; }
    .fl-row { grid-template-columns:1fr; }
    .fl-class-grid { grid-template-columns:1fr 1fr; }
    .fl-titlebar { flex-direction:column; align-items:flex-start; gap:10px; }
    .fl-form-body { max-height:none; }
    .class-grid { grid-template-columns:1fr 1fr; }
}
</style>

<div class="fl-page">
    <div class="fl-titlebar">
        <div class="fl-titlebar-left">
            <div class="fl-titlebar-icon"><i class="fas fa-plane-departure"></i></div>
            <div>
                <h1>Flight Management</h1>
                <p>Add flights with multiple cabin classes in one go</p>
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

        <!-- ══ FORM PANEL ══ -->
        <div class="fl-form-panel">
            <div class="fl-form-header">
                <div class="fl-form-header-icon"><i class="fas fa-plus"></i></div>
                <div>
                    <h2>Add New Flight</h2>
                    <span>Enable each cabin class and set its price &amp; seats</span>
                </div>
            </div>
            <div class="fl-form-body">
                <form action="<?= BASE_URL ?>/view/addFlight.php" method="POST" enctype="multipart/form-data" id="addFlightForm">

                    <!-- Flight Identity -->
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
                                <input type="text" name="airline_name" placeholder="Biman Bangladesh" required>
                            </div>
                        </div>
                        <div class="fl-field">
                            <label>Flight Code <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🔤</span>
                                <input type="text" name="flight_code" placeholder="BG101" required style="text-transform:uppercase">
                            </div>
                        </div>
                    </div>

                    <!-- Route & Schedule -->
                    <div class="fl-section">Route &amp; Schedule</div>
                    <div class="fl-row">
                        <div class="fl-field">
                            <label>Departure City <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🛫</span>
                                <input type="text" name="departure" placeholder="Dhaka" required>
                            </div>
                        </div>
                        <div class="fl-field">
                            <label>Arrival City <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">🛬</span>
                                <input type="text" name="arrival" placeholder="Chittagong" required>
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
                    <div class="fl-row">
                        <div class="fl-field">
                            <label>Duration <span class="req">*</span></label>
                            <div class="fl-wrap"><span class="fl-ico">⏱️</span>
                                <input type="text" name="duration" placeholder="1h 20m" required>
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

                    <!-- Cabin Classes -->
                    <div class="fl-section">Cabin Classes <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#b0bec5;font-size:.65rem">(enable at least one)</span></div>

                    <div class="class-cards">

                        <!-- Economy -->
                        <div class="class-card economy" id="card_economy">
                            <div class="class-card-header" onclick="toggleClass('economy')">
                                <div class="class-toggle"><i class="fas fa-check"></i></div>
                                <span class="class-icon">🟢</span>
                                <div style="flex:1">
                                    <div class="class-label">Economy</div>
                                    <div class="class-sublabel">Standard cabin — most affordable</div>
                                </div>
                                <i class="fas fa-chevron-down class-chevron"></i>
                            </div>
                            <div class="class-card-body">
                                <input type="hidden" name="enable_economy" id="enable_economy" value="">
                                <div class="class-grid">
                                    <div class="class-field">
                                        <label>Price (USD) <span class="req">*</span></label>
                                        <input type="number" step="0.01" min="0" name="price_economy" placeholder="150.00">
                                    </div>
                                    <div class="class-field">
                                        <label>Total Seats <span class="req">*</span></label>
                                        <input type="number" min="1" name="seats_economy" placeholder="120">
                                    </div>
                                    <div class="class-field">
                                        <label>Discount %</label>
                                        <input type="number" step="0.1" min="0" max="100" name="discount_economy" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Business -->
                        <div class="class-card business" id="card_business">
                            <div class="class-card-header" onclick="toggleClass('business')">
                                <div class="class-toggle"><i class="fas fa-check"></i></div>
                                <span class="class-icon">🔵</span>
                                <div style="flex:1">
                                    <div class="class-label">Business</div>
                                    <div class="class-sublabel">Premium comfort &amp; service</div>
                                </div>
                                <i class="fas fa-chevron-down class-chevron"></i>
                            </div>
                            <div class="class-card-body">
                                <input type="hidden" name="enable_business" id="enable_business" value="">
                                <div class="class-grid">
                                    <div class="class-field">
                                        <label>Price (USD) <span class="req">*</span></label>
                                        <input type="number" step="0.01" min="0" name="price_business" placeholder="450.00">
                                    </div>
                                    <div class="class-field">
                                        <label>Total Seats <span class="req">*</span></label>
                                        <input type="number" min="1" name="seats_business" placeholder="40">
                                    </div>
                                    <div class="class-field">
                                        <label>Discount %</label>
                                        <input type="number" step="0.1" min="0" max="100" name="discount_business" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- First Class -->
                        <div class="class-card first" id="card_first_class">
                            <div class="class-card-header" onclick="toggleClass('first_class')">
                                <div class="class-toggle"><i class="fas fa-check"></i></div>
                                <span class="class-icon">🟣</span>
                                <div style="flex:1">
                                    <div class="class-label">First Class</div>
                                    <div class="class-sublabel">Luxury experience — top tier</div>
                                </div>
                                <i class="fas fa-chevron-down class-chevron"></i>
                            </div>
                            <div class="class-card-body">
                                <input type="hidden" name="enable_first_class" id="enable_first_class" value="">
                                <div class="class-grid">
                                    <div class="class-field">
                                        <label>Price (USD) <span class="req">*</span></label>
                                        <input type="number" step="0.01" min="0" name="price_first_class" placeholder="900.00">
                                    </div>
                                    <div class="class-field">
                                        <label>Total Seats <span class="req">*</span></label>
                                        <input type="number" min="1" name="seats_first_class" placeholder="10">
                                    </div>
                                    <div class="class-field">
                                        <label>Discount %</label>
                                        <input type="number" step="0.1" min="0" max="100" name="discount_first_class" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /class-cards -->

                    <!-- Image -->
                    <div class="fl-section" style="margin-top:18px;">Flight Image / Logo</div>
                    <div class="fl-field">
                        <label class="fl-label" style="font-size:.78rem;font-weight:600;color:#334155;margin-bottom:6px;display:block">Airline Logo URL <span style="color:#94a3b8;font-weight:400">(e.g. Google Flights logo URL)</span></label>
                        <input type="url" name="image_url" id="imageUrlInput"
                               placeholder="https://www.gstatic.com/flights/airline_logos/70px/BS.png"
                               style="width:100%;padding:10px 14px;border:1.5px solid #c7d8f0;border-radius:8px;font-size:.88rem;color:#0f172a;background:#f8fafc;margin-bottom:8px;outline:none"
                               oninput="previewLogoUrl(this.value)">
                        <div id="logoPreview" style="display:none;align-items:center;gap:12px;padding:10px 14px;background:#f0f7ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:10px">
                            <div style="width:44px;height:44px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;border:1.5px solid #e2e8f0;box-shadow:0 2px 6px rgba(0,0,0,.08);flex-shrink:0">
                                <img id="logoPreviewImg" src="" style="width:32px;height:32px;object-fit:contain" alt="">
                            </div>
                            <span style="font-size:.8rem;color:#2563eb;font-weight:600">✓ Logo loaded</span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;margin:4px 0 10px">
                        <div style="flex:1;height:1px;background:#e2e8f0"></div>
                        <span style="font-size:.72rem;color:#94a3b8;font-weight:600;white-space:nowrap">OR upload image</span>
                        <div style="flex:1;height:1px;background:#e2e8f0"></div>
                    </div>
                    <div class="fl-field">
                        <div class="fl-file-zone" id="flFileZone">
                            <input type="file" name="image" accept="image/*" onchange="updateFlFileLabel(this)">
                            <span class="fz-icon">🖼️</span>
                            <p class="fz-text" id="flFileLabel"><b>Click to upload</b> or drag &amp; drop<br>PNG, JPG, WEBP &nbsp;·&nbsp; <span style="color:#94a3b8">Optional</span></p>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="fl-submit">
                        <i class="fas fa-plus"></i> Add Flight
                    </button>
                </form>
            </div>
        </div>

        <!-- ══ LIST PANEL ══ -->
        <div class="fl-list-panel">
            <div class="fl-list-header">
                <h2>Existing Flights</h2>
                <span class="fl-count-pill"><?= count($flights) ?> total</span>
            </div>
            <div class="fl-list-body">
                <div class="fl-search-wrap">
                    <span class="s-ico">🔍</span>
                    <input type="text" id="flSearch" placeholder="Search by name, code, route…" oninput="filterFlights(this.value)">
                </div>

                <?php if (empty($flights)): ?>
                    <div class="fl-empty"><span class="fl-empty-icon">🛫</span><p>No flights yet. Add your first one!</p></div>
                <?php else:
                    // Group by flight_code
                    $groups = [];
                    foreach ($flights as $f) {
                        $groups[$f['flight_code']][] = $f;
                    }
                ?>
                <div id="flGroups">
                <?php foreach ($groups as $code => $rows):
                    $first = $rows[0];
                    $searchStr = strtolower($first['flight_name'] . ' ' . $code . ' ' . $first['departure'] . ' ' . $first['arrival'] . ' ' . $first['airline_name']);
                ?>
                <div class="fl-group" data-search="<?= htmlspecialchars($searchStr) ?>">
                    <div class="fl-group-header">
                        <?php $img_src = str_starts_with($first['image'] ?? '', 'http') ? htmlspecialchars($first['image']) : 'upload/' . htmlspecialchars($first['image'] ?? ''); ?>
                        <img class="fl-group-img" src="<?= $img_src ?>" alt="" onerror="this.style.display='none'">
                        <div style="flex:1;min-width:0">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <span class="fl-group-code"><?= htmlspecialchars($code) ?></span>
                                <span class="fl-group-name"><?= htmlspecialchars($first['flight_name']) ?></span>
                            </div>
                            <div class="fl-group-route">
                                <?= htmlspecialchars($first['departure']) ?> → <?= htmlspecialchars($first['arrival']) ?>
                                &nbsp;·&nbsp; <?= htmlspecialchars($first['airline_name']) ?>
                                &nbsp;·&nbsp; <?= htmlspecialchars($first['duration']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="fl-class-rows">
                    <?php foreach ($rows as $f):
                        $cls = $f['flight_class'];
                        $discount = (float)($f['discount_pct'] ?? 0);
                        $final = $f['price'] * (1 - $discount / 100);
                    ?>
                        <div class="fl-class-row">
                            <span class="fl-status-dot <?= $f['status'] ?>"></span>
                            <span class="fl-class-badge <?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></span>
                            <span class="fl-class-price">$<?= number_format($final, 2) ?></span>
                            <span class="fl-class-seats">
                                💺 <?= (int)$f['seat'] ?>/<?= (int)$f['total_seats'] ?> seats
                                <?php if ($discount > 0): ?>
                                    &nbsp;<span style="color:#16a34a;font-size:.7rem;font-weight:700">↓<?= $discount ?>% off</span>
                                <?php endif; ?>
                            </span>
                            <div class="fl-class-actions">
                                <a href="editFlight.php?id=<?= $f['id'] ?>" class="fl-btn edit"><i class="fas fa-pen"></i> Edit</a>
                                <a href="?delete_id=<?= $f['id'] ?>" class="fl-btn del"
                                   onclick="return confirm('Delete <?= htmlspecialchars($cls) ?> class for <?= htmlspecialchars(addslashes($code)) ?>?')">
                                   <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
// Toggle class card on/off
function toggleClass(key) {
    const card   = document.getElementById('card_' + key);
    const hidden = document.getElementById('enable_' + key);
    const isOn   = card.classList.contains('enabled');
    card.classList.toggle('enabled', !isOn);
    hidden.value = isOn ? '' : '1';
}

// File label update
function updateFlFileLabel(input) {
    const label = document.getElementById('flFileLabel');
    const zone  = document.getElementById('flFileZone');
    if (input.files && input.files[0]) {
        label.innerHTML = '✅ <b>' + input.files[0].name + '</b>';
        zone.style.borderColor = '#16a34a';
        zone.style.background  = '#f0fdf4';
    }
}

// Logo URL preview
function previewLogoUrl(url) {
    const preview = document.getElementById('logoPreview');
    const img     = document.getElementById('logoPreviewImg');
    if (url && url.startsWith('http')) {
        img.src = url;
        img.onload  = () => { preview.style.display = 'flex'; };
        img.onerror = () => { preview.style.display = 'none'; };
    } else {
        preview.style.display = 'none';
    }
}

// Search filter
function filterFlights(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#flGroups .fl-group').forEach(g => {
        g.style.display = (!q || g.dataset.search.includes(q)) ? '' : 'none';
    });
}

// Form validation — at least one class must be enabled
document.getElementById('addFlightForm').addEventListener('submit', function(e) {
    const anyEnabled = ['economy','business','first_class'].some(k =>
        document.getElementById('enable_' + k).value === '1'
    );
    if (!anyEnabled) {
        e.preventDefault();
        alert('Please enable at least one cabin class (Economy, Business, or First Class).');
    }
});

// Auto-dismiss flash
const flFlash = document.getElementById('flFlash');
if (flFlash) setTimeout(() => flFlash.remove(), 5000);
</script>

</body>
</html>
<?php include __DIR__ . "/../includes/footer.php"; ?>
