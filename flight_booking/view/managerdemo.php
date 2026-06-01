<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/base_url.php";
include("../model/db_conn.php");

// ── AJAX: get flight details by code ────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_flight') {
    header('Content-Type: application/json');
    $code = trim($_GET['code'] ?? '');
    if ($code) {
        $s = $conn->prepare("SELECT flight_name, airline_name FROM flights WHERE flight_code = ?");
        $s->bind_param("s", $code);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        echo $row ? json_encode(['success'=>true,'flight_name'=>$row['flight_name'],'airline_name'=>$row['airline_name']])
                  : json_encode(['success'=>false]);
    } else {
        echo json_encode(['success'=>false]);
    }
    exit;
}

// Guard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: " . BASE_URL . "/view/login.php"); exit;
}

// ── Helper ───────────────────────────────────────────────────
function extractTime($v) {
    $v = (string)$v;
    if (strlen($v) === 5 && str_contains($v, ':')) return $v;
    if (str_contains($v, ' ')) { $p = explode(' ', $v); $v = end($p); }
    if (preg_match('/(\d{2}):(\d{2})/', $v, $m)) return $m[1].':'.$m[2];
    return '00:00';
}

// ── Delete schedule (PRG) ────────────────────────────────────
if (isset($_GET['delete_schedule'])) {
    $code = $_GET['delete_schedule'];
    $s = $conn->prepare("DELETE FROM schedule WHERE flight_code = ?");
    $s->bind_param("s", $code);
    $s->execute();
    $_SESSION['mgr_msg']      = "Schedule deleted.";
    $_SESSION['mgr_msg_type'] = "success";
    header("Location: " . BASE_URL . "/view/managerdemo.php"); exit;
}

// ── Save / update schedule (PRG) ────────────────────────────
if (isset($_POST['save_schedule'])) {
    $flight_name  = trim($_POST['flight_name']    ?? '');
    $airline_name = trim($_POST['airline_name']   ?? '');
    $flight_code  = trim($_POST['flight_code']    ?? '');
    $dep_day      = $_POST['departure_from']      ?? '';
    $dep_time     = ($_POST['departure_time']     ?? '') . ':00';
    $arr_day      = $_POST['arrival_to']          ?? '';
    $arr_time     = ($_POST['arrival_time']       ?? '') . ':00';

    if (!$flight_code || !$flight_name || !$airline_name || !$dep_day || !$arr_day) {
        $_SESSION['mgr_msg']      = "All fields are required.";
        $_SESSION['mgr_msg_type'] = "error";
    } else {
        $chk = $conn->prepare("SELECT id FROM schedule WHERE flight_code = ?");
        $chk->bind_param("s", $flight_code); $chk->execute();
        $chk->store_result();
        $exists = $chk->num_rows > 0; $chk->close();

        if ($exists) {
            $s = $conn->prepare("UPDATE schedule SET flight_name=?,airline_name=?,departure_day=?,departure_time=?,arrival_day=?,arrival_time=? WHERE flight_code=?");
            $s->bind_param("sssssss",$flight_name,$airline_name,$dep_day,$dep_time,$arr_day,$arr_time,$flight_code);
        } else {
            $s = $conn->prepare("INSERT INTO schedule (flight_name,airline_name,flight_code,departure_day,departure_time,arrival_day,arrival_time) VALUES (?,?,?,?,?,?,?)");
            $s->bind_param("sssssss",$flight_name,$airline_name,$flight_code,$dep_day,$dep_time,$arr_day,$arr_time);
        }
        $s->execute();
        $_SESSION['mgr_msg']      = $exists ? "Schedule updated successfully!" : "Schedule saved successfully!";
        $_SESSION['mgr_msg_type'] = "success";
    }
    header("Location: " . BASE_URL . "/view/managerdemo.php"); exit;
}

// Consume flash
$flash_msg  = $_SESSION['mgr_msg']      ?? '';
$flash_type = $_SESSION['mgr_msg_type'] ?? '';
unset($_SESSION['mgr_msg'], $_SESSION['mgr_msg_type']);

// Fetch data
$flights   = [];
$res = $conn->query("SELECT * FROM flights ORDER BY flight_code ASC, FIELD(flight_class,'Economy','Business','First Class')");
if ($res) while ($r = $res->fetch_assoc()) $flights[] = $r;

// Group flights by code
$flight_groups = [];
foreach ($flights as $f) {
    $flight_groups[$f['flight_code']][] = $f;
}

$schedules = [];
$res2 = $conn->query("SELECT * FROM schedule ORDER BY flight_code ASC");
if ($res2) while ($r = $res2->fetch_assoc()) $schedules[] = $r;

// Stats — count unique codes as "flights"
$total_flights   = count($flight_groups);
$total_schedules = count($schedules);
$active_flights  = count(array_filter($flight_groups, fn($rows) =>
    count(array_filter($rows, fn($f) => ($f['status'] ?? 'active') === 'active')) > 0
));
$avg_price = count($flights) ? array_sum(array_column($flights, 'price')) / count($flights) : 0;

include("../includes/managerheader.php");
?>
<style>
.mgr-wrap { flex:1; padding:28px 32px 60px; }
.mgr-wrap * { box-sizing:border-box; }

/* ── Top bar ── */
.mgr-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.mgr-topbar h1 { font-size:1.35rem; font-weight:800; color:#0f172a; letter-spacing:-0.3px; }
.mgr-topbar p  { font-size:0.82rem; color:#64748b; margin-top:2px; }
.mgr-search-wrap { position:relative; }
.mgr-search-wrap input {
    padding:9px 14px 9px 36px; border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:0.87rem; background:#fff; outline:none; font-family:inherit;
    color:#1e293b; width:260px; transition:border-color 0.2s, box-shadow 0.2s;
}
.mgr-search-wrap input:focus { border-color:#0b72e6; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.mgr-search-wrap .s-ico { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:0.85rem; pointer-events:none; }

/* ── Flash ── */
.mgr-flash {
    display:flex; align-items:center; gap:10px; padding:12px 18px;
    border-radius:12px; font-size:0.87rem; font-weight:600;
    margin-bottom:20px; animation:mgrFade 0.3s ease;
}
@keyframes mgrFade { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.mgr-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.mgr-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.mgr-flash .mgr-close { margin-left:auto; cursor:pointer; opacity:0.5; font-size:0.95rem; background:none; border:none; color:inherit; padding:0; font-family:inherit; }

/* ── Stat cards ── */
.mgr-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.mgr-stat { background:#fff; border-radius:16px; padding:18px 20px; border:1px solid #e8f0fb; box-shadow:0 2px 12px rgba(11,114,230,0.06); display:flex; align-items:center; gap:14px; }
.mgr-stat-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.mgr-stat-icon.blue   { background:#eff6ff; }
.mgr-stat-icon.green  { background:#f0fdf4; }
.mgr-stat-icon.purple { background:#f5f3ff; }
.mgr-stat-icon.amber  { background:#fffbeb; }
.mgr-stat-val { font-size:1.4rem; font-weight:800; color:#0f172a; line-height:1; }
.mgr-stat-lbl { font-size:0.75rem; color:#64748b; margin-top:3px; }

/* ── Card ── */
.mgr-card { background:#fff; border-radius:20px; box-shadow:0 4px 24px rgba(11,114,230,0.08); border:1px solid #e8f0fb; overflow:hidden; margin-bottom:24px; }
.mgr-card-head { padding:16px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; background:#fafcff; }
.mgr-card-head h2 { font-size:0.95rem; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px; }
.mgr-card-head h2::before { content:''; display:inline-block; width:3px; height:1em; background:linear-gradient(180deg,#0b72e6,#6c3de8); border-radius:3px; }
.mgr-badge { font-size:0.75rem; font-weight:700; padding:4px 12px; border-radius:20px; background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff; }

/* ── Table ── */
.mgr-table-wrap { overflow-x:auto; }
table.mgr-table { width:100%; border-collapse:collapse; font-size:0.86rem; }
.mgr-table thead tr { background:#f8fafc; border-bottom:2px solid #e8f0fb; }
.mgr-table th { padding:12px 16px; text-align:left; font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; white-space:nowrap; }
.mgr-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background 0.15s; }
.mgr-table tbody tr:hover { background:#fafcff; }
.mgr-table td { padding:13px 16px; color:#334155; vertical-align:middle; }

/* Route */
.mgr-route { display:flex; align-items:center; gap:6px; font-weight:600; color:#0f172a; }
.mgr-route .arr { color:#0b72e6; }

/* Status badge */
.mgr-status { display:inline-flex; align-items:center; gap:4px; font-size:0.7rem; font-weight:700; padding:3px 9px; border-radius:20px; text-transform:uppercase; }
.mgr-status.active    { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
.mgr-status.inactive  { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; }
.mgr-status.cancelled { background:#fef9c3; color:#92400e; border:1px solid #fde68a; }

/* Tags */
.mgr-tag { font-size:0.72rem; font-weight:600; padding:3px 9px; border-radius:20px; }
.mgr-tag.blue   { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
.mgr-tag.purple { background:#f5f3ff; color:#7c3aed; border:1px solid #ddd6fe; }

/* Price */
.mgr-price { font-weight:700; color:#0b72e6; }

/* Action buttons */
.mgr-btn-edit { padding:7px 14px; background:#eff6ff; color:#2563eb; border:1.5px solid #bfdbfe; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.18s; white-space:nowrap; }
.mgr-btn-edit:hover { background:#2563eb; color:#fff; border-color:#2563eb; }
.mgr-btn-del  { padding:7px 14px; background:#fff5f5; color:#dc2626; border:1.5px solid #fecaca; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.18s; text-decoration:none; display:inline-flex; align-items:center; }
.mgr-btn-del:hover  { background:#dc2626; color:#fff; border-color:#dc2626; }

/* Empty */
.mgr-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.mgr-empty .ei { font-size:3rem; display:block; margin-bottom:10px; opacity:0.4; }

/* ── Schedule form panel ── */
.mgr-sched-form { padding:24px; }
.mgr-sched-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; align-items:end; }
.mgr-sf { display:flex; flex-direction:column; gap:6px; }
.mgr-sf label { font-size:0.72rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px; }
.mgr-sf input, .mgr-sf select {
    padding:10px 13px; border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:0.88rem; color:#1e293b; background:#f8fafc;
    outline:none; font-family:inherit; appearance:none;
    transition:border-color 0.2s, box-shadow 0.2s;
}
.mgr-sf input:focus, .mgr-sf select:focus { border-color:#0b72e6; background:#fff; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.mgr-sf input[readonly] { color:#64748b; cursor:default; }
.mgr-sf .sel-w { position:relative; }
.mgr-sf .sel-w::after { content:'▾'; position:absolute; right:11px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; font-size:0.78rem; }
.mgr-sf .sel-w select { padding-right:28px; width:100%; }
.mgr-sched-actions { display:flex; gap:10px; align-items:flex-end; }
.mgr-btn-save {
    padding:10px 22px; background:linear-gradient(135deg,#0b72e6,#6c3de8);
    color:#fff; border:none; border-radius:10px; font-size:0.9rem; font-weight:700;
    cursor:pointer; font-family:inherit; box-shadow:0 3px 12px rgba(11,114,230,0.3);
    transition:opacity 0.2s, transform 0.15s; white-space:nowrap;
}
.mgr-btn-save:hover { opacity:0.9; transform:translateY(-1px); }
.mgr-btn-reset { padding:10px 18px; background:#fff; color:#64748b; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.9rem; font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.15s; }
.mgr-btn-reset:hover { border-color:#94a3b8; color:#334155; }

/* Day badge */
.mgr-day { display:inline-flex; align-items:center; gap:4px; font-size:0.78rem; font-weight:600; color:#0f172a; }

/* Responsive */
@media (max-width:900px) {
    .mgr-stats { grid-template-columns:1fr 1fr; }
    .mgr-wrap { padding:16px 14px 80px; }
    .mgr-topbar { flex-direction:column; align-items:flex-start; gap:10px; }
    .mgr-search-wrap input { width:100%; }
    .mgr-sched-grid { grid-template-columns:1fr 1fr; }
}
@media (max-width:600px) {
    .mgr-stats { grid-template-columns:1fr 1fr; }
    .mgr-stat { padding:14px 12px; gap:10px; }
    .mgr-stat-icon { width:38px; height:38px; font-size:1.1rem; }
    .mgr-stat-val { font-size:1.2rem; }
    .mgr-sched-grid { grid-template-columns:1fr; }
    .mgr-sched-actions { flex-direction:row; }
    .mgr-btn-save, .mgr-btn-reset { flex:1; justify-content:center; }
    .mgr-card-head { flex-direction:column; align-items:flex-start; gap:6px; }
    /* Hide less critical table columns on mobile */
    .mgr-table th:nth-child(3), .mgr-table td:nth-child(3),
    .mgr-table th:nth-child(6), .mgr-table td:nth-child(6),
    .mgr-table th:nth-child(7), .mgr-table td:nth-child(7) { display:none; }
}
@media (max-width:400px) {
    .mgr-stats { grid-template-columns:1fr; }
}
</style>

<div class="mgr-wrap">

    <!-- Top bar -->
    <div class="mgr-topbar">
        <div>
            <h1>✈️ Manage Flights</h1>
            <p>View flights and manage weekly schedules</p>
        </div>
        <div class="mgr-search-wrap">
            <span class="s-ico">🔍</span>
            <input type="text" id="mgrSearch" placeholder="Search flights…" oninput="mgrFilter(this.value)">
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash_msg): ?>
        <div class="mgr-flash <?= htmlspecialchars($flash_type) ?>" id="mgrFlash">
            <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($flash_msg) ?>
            <button class="mgr-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="mgr-stats">
        <div class="mgr-stat">
            <div class="mgr-stat-icon blue">🛫</div>
            <div><div class="mgr-stat-val"><?= $total_flights ?></div><div class="mgr-stat-lbl">Total Flights</div></div>
        </div>
        <div class="mgr-stat">
            <div class="mgr-stat-icon green">✅</div>
            <div><div class="mgr-stat-val"><?= $active_flights ?></div><div class="mgr-stat-lbl">Active</div></div>
        </div>
        <div class="mgr-stat">
            <div class="mgr-stat-icon purple">📅</div>
            <div><div class="mgr-stat-val"><?= $total_schedules ?></div><div class="mgr-stat-lbl">Schedules</div></div>
        </div>
        <div class="mgr-stat">
            <div class="mgr-stat-icon amber">💵</div>
            <div><div class="mgr-stat-val">$<?= number_format($avg_price, 0) ?></div><div class="mgr-stat-lbl">Avg. Price</div></div>
        </div>
    </div>

    <!-- ══ Flights Table ══ -->
    <div class="mgr-card">
        <div class="mgr-card-head">
            <h2>Available Flights</h2>
            <span class="mgr-badge"><?= count($flight_groups) ?> flight<?= count($flight_groups) !== 1 ? 's' : '' ?></span>
        </div>
        <?php if (empty($flight_groups)): ?>
            <div class="mgr-empty"><span class="ei">🛫</span><p>No flights found.</p></div>
        <?php else: ?>
        <div style="padding:16px 20px;display:flex;flex-direction:column;gap:14px;" id="mgrFlightsContainer">
        <?php foreach ($flight_groups as $code => $rows):
            $first = $rows[0];
            $dep_time = extractTime($first['departure_time'] ?? '');
            $arr_time = extractTime($first['arrival_time']   ?? '');
            $searchStr = strtolower($first['flight_name'].' '.$code.' '.$first['departure'].' '.$first['arrival'].' '.$first['airline_name']);
        ?>
        <div class="mgr-flight-group" data-search="<?= htmlspecialchars($searchStr) ?>">
            <!-- Group header -->
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:#f8fafc;border:1px solid #e8f0fb;border-radius:12px 12px 0 0;border-bottom:none;flex-wrap:wrap;gap:10px;">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span style="font-family:monospace;font-size:.82rem;font-weight:800;color:#0b72e6;background:#eff6ff;padding:3px 10px;border-radius:20px;border:1px solid #bfdbfe;"><?= htmlspecialchars($code) ?></span>
                        <span style="font-size:.92rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($first['flight_name']) ?></span>
                        <span style="font-size:.78rem;color:#64748b;"><?= htmlspecialchars($first['airline_name']) ?></span>
                    </div>
                    <div style="font-size:.78rem;color:#64748b;margin-top:4px;">
                        🛫 <?= htmlspecialchars($first['departure']) ?> → <?= htmlspecialchars($first['arrival']) ?>
                        &nbsp;·&nbsp; ⏱️ <?= htmlspecialchars($first['duration']) ?>
                        &nbsp;·&nbsp; 🕐 <?= $dep_time ?> – <?= $arr_time ?>
                    </div>
                </div>
                <button class="mgr-btn-edit" onclick="mgrFillSchedule('<?= htmlspecialchars(addslashes($code)) ?>','<?= htmlspecialchars(addslashes($first['flight_name'])) ?>','<?= htmlspecialchars(addslashes($first['airline_name'])) ?>')">
                    📅 Set Schedule
                </button>
            </div>
            <!-- Class rows -->
            <div style="border:1px solid #e8f0fb;border-radius:0 0 12px 12px;overflow:hidden;">
            <?php foreach ($rows as $f):
                $cls    = $f['flight_class'];
                $status = $f['status'] ?? 'active';
                $disc   = (float)($f['discount_pct'] ?? 0);
                $final  = $f['price'] * (1 - $disc / 100);
                $clsBg  = $cls === 'Economy' ? '#f0fdf4' : ($cls === 'Business' ? '#eff6ff' : '#f5f3ff');
                $clsClr = $cls === 'Economy' ? '#15803d' : ($cls === 'Business' ? '#1d4ed8' : '#6d28d9');
                $clsBdr = $cls === 'Economy' ? '#bbf7d0' : ($cls === 'Business' ? '#bfdbfe' : '#ddd6fe');
            ?>
            <div style="display:flex;align-items:center;gap:14px;padding:11px 16px;border-bottom:1px solid #f1f5f9;flex-wrap:wrap;">
                <span style="font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px;background:<?= $clsBg ?>;color:<?= $clsClr ?>;border:1px solid <?= $clsBdr ?>;white-space:nowrap;"><?= htmlspecialchars($cls) ?></span>
                <span style="font-size:.95rem;font-weight:800;color:#0b72e6;min-width:80px;">$<?= number_format($final, 2) ?></span>
                <span style="font-size:.78rem;color:#64748b;">
                    💺 <?= (int)$f['seat'] ?>/<?= (int)$f['total_seats'] ?> seats
                    <?php if ($disc > 0): ?>&nbsp;<span style="color:#16a34a;font-weight:700;">↓<?= $disc ?>% off</span><?php endif; ?>
                </span>
                <span class="mgr-status <?= $status ?>" style="margin-left:auto;"><?= ucfirst($status) ?></span>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <!-- ══ Add / Update Schedule ══ -->
    <div class="mgr-card">
        <div class="mgr-card-head">
            <h2>Add / Update Schedule</h2>
            <span class="mgr-badge" id="schedFormBadge">New Schedule</span>
        </div>
        <div class="mgr-sched-form">
            <form method="POST" action="<?= BASE_URL ?>/view/managerdemo.php" id="schedForm">
                <div class="mgr-sched-grid">
                    <div class="mgr-sf">
                        <label>Flight Code <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="flight_code" id="sched_code"
                               placeholder="Enter or click Schedule above"
                               required oninput="mgrAutoFetch(this.value)">
                    </div>
                    <div class="mgr-sf">
                        <label>Flight Name</label>
                        <input type="text" name="flight_name" id="sched_name" readonly placeholder="Auto-filled">
                    </div>
                    <div class="mgr-sf">
                        <label>Airline</label>
                        <input type="text" name="airline_name" id="sched_airline" readonly placeholder="Auto-filled">
                    </div>
                    <div class="mgr-sf">
                        <label>Departure Day <span style="color:#e53e3e">*</span></label>
                        <div class="sel-w">
                            <select name="departure_from" id="sched_dep_day" required>
                                <option value="">Select Day</option>
                                <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                                    <option value="<?= $d ?>"><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mgr-sf">
                        <label>Departure Time <span style="color:#e53e3e">*</span></label>
                        <input type="time" name="departure_time" id="sched_dep_time" required>
                    </div>
                    <div class="mgr-sf">
                        <label>Arrival Day <span style="color:#e53e3e">*</span></label>
                        <div class="sel-w">
                            <select name="arrival_to" id="sched_arr_day" required>
                                <option value="">Select Day</option>
                                <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                                    <option value="<?= $d ?>"><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mgr-sf">
                        <label>Arrival Time <span style="color:#e53e3e">*</span></label>
                        <input type="time" name="arrival_time" id="sched_arr_time" required>
                    </div>
                    <div class="mgr-sf mgr-sched-actions">
                        <label>&nbsp;</label>
                        <button type="submit" name="save_schedule" class="mgr-btn-save">💾 Save Schedule</button>
                        <button type="reset" class="mgr-btn-reset" onclick="mgrResetForm()">✕ Reset</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ Schedules List ══ -->
    <div class="mgr-card">
        <div class="mgr-card-head">
            <h2>Flight Schedules</h2>
            <span class="mgr-badge"><?= $total_schedules ?> schedules</span>
        </div>
        <div class="mgr-table-wrap">
            <?php if (empty($schedules)): ?>
                <div class="mgr-empty"><span class="ei">📅</span><p>No schedules yet. Add one above.</p></div>
            <?php else: ?>
            <table class="mgr-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Flight</th>
                        <th>Airline</th>
                        <th>Code</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($schedules as $i => $sc):
                    $dt = extractTime($sc['departure_time']);
                    $at = extractTime($sc['arrival_time']);
                ?>
                    <tr>
                        <td style="color:#94a3b8;font-size:0.8rem;"><?= $i+1 ?></td>
                        <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($sc['flight_name']) ?></td>
                        <td><?= htmlspecialchars($sc['airline_name']) ?></td>
                        <td><span class="mgr-tag blue"><?= htmlspecialchars($sc['flight_code']) ?></span></td>
                        <td>
                            <div class="mgr-day">📅 <?= htmlspecialchars($sc['departure_day']) ?></div>
                            <div style="font-size:0.78rem;color:#64748b;margin-top:2px;">🕐 <?= $dt ?></div>
                        </td>
                        <td>
                            <div class="mgr-day">📅 <?= htmlspecialchars($sc['arrival_day']) ?></div>
                            <div style="font-size:0.78rem;color:#64748b;margin-top:2px;">🕔 <?= $at ?></div>
                        </td>
                        <td style="display:flex;gap:8px;align-items:center;">
                            <button class="mgr-btn-edit"
                                onclick="mgrEditSchedule('<?= htmlspecialchars(addslashes($sc['flight_code'])) ?>','<?= htmlspecialchars(addslashes($sc['flight_name'])) ?>','<?= htmlspecialchars(addslashes($sc['airline_name'])) ?>','<?= htmlspecialchars($sc['departure_day']) ?>','<?= $dt ?>','<?= htmlspecialchars($sc['arrival_day']) ?>','<?= $at ?>')">
                                ✏️ Edit
                            </button>
                            <a href="?delete_schedule=<?= urlencode($sc['flight_code']) ?>"
                               class="mgr-btn-del"
                               onclick="return confirm('Delete schedule for <?= htmlspecialchars(addslashes($sc['flight_code'])) ?>?')">
                                🗑️ Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
// Filter flights — now grouped
function mgrFilter(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#mgrFlightsContainer .mgr-flight-group').forEach(g => {
        g.style.display = (!q || g.dataset.search.includes(q)) ? '' : 'none';
    });
}

// Fill schedule form from flight row button
function mgrFillSchedule(code, name, airline) {
    document.getElementById('sched_code').value    = code;
    document.getElementById('sched_name').value    = name;
    document.getElementById('sched_airline').value = airline;
    document.getElementById('schedFormBadge').textContent = 'Editing: ' + code;
    document.getElementById('schedForm').scrollIntoView({ behavior:'smooth', block:'center' });
}

// Fill schedule form from schedule list edit button
function mgrEditSchedule(code, name, airline, depDay, depTime, arrDay, arrTime) {
    document.getElementById('sched_code').value    = code;
    document.getElementById('sched_name').value    = name;
    document.getElementById('sched_airline').value = airline;
    document.getElementById('sched_dep_day').value  = depDay;
    document.getElementById('sched_dep_time').value = depTime;
    document.getElementById('sched_arr_day').value  = arrDay;
    document.getElementById('sched_arr_time').value = arrTime;
    document.getElementById('schedFormBadge').textContent = 'Updating: ' + code;
    document.getElementById('schedForm').scrollIntoView({ behavior:'smooth', block:'center' });
}

// Auto-fetch flight name/airline when code is typed manually
let mgrFetchTimer;
function mgrAutoFetch(code) {
    clearTimeout(mgrFetchTimer);
    if (code.length < 2) return;
    mgrFetchTimer = setTimeout(() => {
        fetch('managerdemo.php?action=get_flight&code=' + encodeURIComponent(code))
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('sched_name').value    = d.flight_name;
                    document.getElementById('sched_airline').value = d.airline_name;
                }
            });
    }, 400);
}

function mgrResetForm() {
    document.getElementById('schedFormBadge').textContent = 'New Schedule';
}

// Auto-dismiss flash
const mgrFlash = document.getElementById('mgrFlash');
if (mgrFlash) setTimeout(() => mgrFlash.remove(), 4000);
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
