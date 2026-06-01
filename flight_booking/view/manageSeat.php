<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/base_url.php";
require_once __DIR__ . "/../model/db_conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: " . BASE_URL . "/view/login.php"); exit;
}

// ── Handle seat update (PRG) ─────────────────────────────────
if (isset($_POST['update_seat'])) {
    $id   = (int)$_POST['flight_id'];
    $seat = max(0, (int)$_POST['seat']);
    $stmt = $conn->prepare("UPDATE flights SET seat = ? WHERE id = ?");
    $stmt->bind_param("ii", $seat, $id);
    if ($stmt->execute()) {
        $_SESSION['ms_msg']      = "Seats updated for flight #$id.";
        $_SESSION['ms_msg_type'] = "success";
    } else {
        $_SESSION['ms_msg']      = "Update failed: " . $stmt->error;
        $_SESSION['ms_msg_type'] = "error";
    }
    $stmt->close();
    header("Location: " . BASE_URL . "/view/manageSeat.php"); exit;
}

$flash_msg  = $_SESSION['ms_msg']      ?? '';
$flash_type = $_SESSION['ms_msg_type'] ?? '';
unset($_SESSION['ms_msg'], $_SESSION['ms_msg_type']);

// Fetch all flights
$flights = [];
$res = $conn->query("SELECT id, flight_name, airline_name, flight_code, departure, arrival, total_seats, seat, status, flight_class FROM flights ORDER BY id DESC");
if ($res) while ($r = $res->fetch_assoc()) $flights[] = $r;

$total_flights  = count($flights);
$total_seats    = array_sum(array_column($flights, 'seat'));
$low_seats      = count(array_filter($flights, fn($f) => (int)$f['seat'] <= 10 && $f['status'] === 'active'));
$full_flights   = count(array_filter($flights, fn($f) => (int)$f['seat'] === 0));

include("../includes/managerheader.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GoZayan | Manage Seats</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f4fb; color: #1e293b; }

.ms-wrap { flex: 1; padding: 28px 32px 80px; }

/* ── Top bar ── */
.ms-topbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
}
.ms-topbar h1 { font-size: 1.35rem; font-weight: 800; color: #0f172a; letter-spacing: -0.3px; }
.ms-topbar p  { font-size: 0.82rem; color: #64748b; margin-top: 2px; }
.ms-search-wrap { position: relative; }
.ms-search-wrap input {
    padding: 9px 14px 9px 36px; border: 1.5px solid #e2e8f0;
    border-radius: 10px; font-size: 0.87rem; background: #fff;
    outline: none; font-family: inherit; color: #1e293b; width: 260px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.ms-search-wrap input:focus { border-color: #0b72e6; box-shadow: 0 0 0 3px rgba(11,114,230,0.1); }
.ms-search-wrap .s-ico { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 0.85rem; pointer-events: none; }

/* ── Flash ── */
.ms-flash {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px; border-radius: 12px;
    font-size: 0.87rem; font-weight: 600;
    margin-bottom: 20px; animation: msFade 0.3s ease;
}
@keyframes msFade { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.ms-flash.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.ms-flash.error   { background: #fff5f5; border: 1px solid #fecaca; color: #dc2626; }
.ms-flash .ms-close { margin-left: auto; cursor: pointer; opacity: 0.5; font-size: 0.95rem; background: none; border: none; color: inherit; padding: 0; font-family: inherit; }

/* ── Stat cards ── */
.ms-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.ms-stat {
    background: #fff; border-radius: 16px; padding: 18px 20px;
    border: 1px solid #e8f0fb; box-shadow: 0 2px 12px rgba(11,114,230,0.06);
    display: flex; align-items: center; gap: 14px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.ms-stat:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(11,114,230,0.1); }
.ms-stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.ms-stat-icon.blue   { background: #eff6ff; }
.ms-stat-icon.green  { background: #f0fdf4; }
.ms-stat-icon.amber  { background: #fffbeb; }
.ms-stat-icon.red    { background: #fff5f5; }
.ms-stat-val { font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1; }
.ms-stat-lbl { font-size: 0.75rem; color: #64748b; margin-top: 3px; }

/* ── Card ── */
.ms-card {
    background: #fff; border-radius: 20px;
    box-shadow: 0 4px 24px rgba(11,114,230,0.08);
    border: 1px solid #e8f0fb; overflow: hidden;
}
.ms-card-head {
    padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
    background: #fafcff; flex-wrap: wrap; gap: 8px;
}
.ms-card-head h2 {
    font-size: 0.95rem; font-weight: 700; color: #0f172a;
    display: flex; align-items: center; gap: 8px;
}
.ms-card-head h2::before {
    content: ''; display: inline-block; width: 3px; height: 1em;
    background: linear-gradient(180deg, #0b72e6, #6c3de8); border-radius: 3px;
}
.ms-badge {
    font-size: 0.75rem; font-weight: 700; padding: 4px 12px;
    border-radius: 20px; background: linear-gradient(135deg, #0b72e6, #6c3de8); color: #fff;
}

/* ── Flight cards grid (mobile-first) ── */
.ms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    padding: 20px;
}

.ms-flight-card {
    background: #fff; border: 1px solid #e8f0fb; border-radius: 16px;
    overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 10px rgba(11,114,230,0.06);
}
.ms-flight-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(11,114,230,0.12); }

/* Card top strip */
.ms-fc-strip {
    height: 4px;
    background: linear-gradient(90deg, #0b72e6, #6c3de8);
}
.ms-fc-strip.low    { background: linear-gradient(90deg, #f59e0b, #ef4444); }
.ms-fc-strip.empty  { background: #ef4444; }
.ms-fc-strip.inactive { background: #94a3b8; }

.ms-fc-body { padding: 16px; }

/* Flight header row */
.ms-fc-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; gap: 8px; }
.ms-fc-name { font-size: 0.92rem; font-weight: 800; color: #0f172a; }
.ms-fc-code { font-size: 0.72rem; color: #64748b; margin-top: 2px; font-family: monospace; }
.ms-fc-status {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.68rem; font-weight: 700; padding: 3px 9px;
    border-radius: 20px; text-transform: uppercase; flex-shrink: 0;
}
.ms-fc-status.active    { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.ms-fc-status.inactive  { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
.ms-fc-status.cancelled { background: #fef9c3; color: #92400e; border: 1px solid #fde68a; }

/* Route */
.ms-fc-route {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.88rem; font-weight: 700; color: #0f172a;
    margin-bottom: 12px;
}
.ms-fc-route .arr { color: #0b72e6; }
.ms-fc-airline { font-size: 0.75rem; color: #64748b; margin-bottom: 14px; }

/* Seat progress bar */
.ms-seat-info { margin-bottom: 14px; }
.ms-seat-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 6px;
}
.ms-seat-label { font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
.ms-seat-count { font-size: 0.88rem; font-weight: 800; color: #0f172a; }
.ms-seat-count.low   { color: #f59e0b; }
.ms-seat-count.empty { color: #ef4444; }
.ms-progress-bg { height: 8px; background: #f1f5f9; border-radius: 8px; overflow: hidden; }
.ms-progress-fill {
    height: 100%; border-radius: 8px;
    background: linear-gradient(90deg, #0b72e6, #6c3de8);
    transition: width 0.4s ease;
}
.ms-progress-fill.low   { background: linear-gradient(90deg, #f59e0b, #ef4444); }
.ms-progress-fill.empty { background: #ef4444; width: 100% !important; opacity: 0.3; }
.ms-seat-meta { font-size: 0.7rem; color: #94a3b8; margin-top: 4px; }

/* Inline edit form */
.ms-edit-form {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 14px;
    background: #f0f7ff; border-top: 1px solid #bfdbfe;
    border-radius: 0 0 16px 16px;
}
.ms-edit-form input[type="number"] {
    flex: 1; padding: 9px 12px; border: 1.5px solid #bfdbfe;
    border-radius: 9px; font-size: 0.9rem; font-family: inherit;
    color: #1e293b; background: #fff; outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.ms-edit-form input[type="number"]:focus {
    border-color: #0b72e6; box-shadow: 0 0 0 3px rgba(11,114,230,0.1);
}
.ms-btn-update {
    padding: 9px 18px; background: linear-gradient(135deg, #0b72e6, #6c3de8);
    color: #fff; border: none; border-radius: 9px; font-size: 0.82rem;
    font-weight: 700; cursor: pointer; font-family: inherit;
    transition: opacity 0.2s, transform 0.15s;
    box-shadow: 0 3px 10px rgba(11,114,230,0.3); white-space: nowrap;
}
.ms-btn-update:hover { opacity: 0.9; transform: translateY(-1px); }
.ms-btn-update:active { transform: translateY(0); }

/* Empty state */
.ms-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.ms-empty .ei { font-size: 3rem; display: block; margin-bottom: 10px; opacity: 0.4; }

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .ms-stats { grid-template-columns: 1fr 1fr; }
    .ms-wrap { padding: 16px 14px 80px; }
    .ms-topbar { flex-direction: column; align-items: flex-start; gap: 10px; }
    .ms-search-wrap input { width: 100%; }
}
@media (max-width: 600px) {
    .ms-stats { grid-template-columns: 1fr 1fr; }
    .ms-stat { padding: 14px 12px; gap: 10px; }
    .ms-stat-icon { width: 38px; height: 38px; font-size: 1.1rem; }
    .ms-stat-val { font-size: 1.2rem; }
    .ms-grid { grid-template-columns: 1fr; padding: 14px; gap: 12px; }
    .ms-card-head { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 400px) {
    .ms-stats { grid-template-columns: 1fr; }
}
</style>

<div class="ms-wrap">

    <!-- Top bar -->
    <div class="ms-topbar">
        <div>
            <h1>💺 Manage Seats</h1>
            <p>Update available seat counts for all flights</p>
        </div>
        <div class="ms-search-wrap">
            <span class="s-ico">🔍</span>
            <input type="text" id="msSearch" placeholder="Search flights…" oninput="msFilter(this.value)">
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash_msg): ?>
    <div class="ms-flash <?= htmlspecialchars($flash_type) ?>" id="msFlash">
        <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
        <?= htmlspecialchars($flash_msg) ?>
        <button class="ms-close" onclick="this.parentElement.remove()">✕</button>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="ms-stats">
        <div class="ms-stat">
            <div class="ms-stat-icon blue">🛫</div>
            <div><div class="ms-stat-val"><?= $total_flights ?></div><div class="ms-stat-lbl">Total Flights</div></div>
        </div>
        <div class="ms-stat">
            <div class="ms-stat-icon green">💺</div>
            <div><div class="ms-stat-val"><?= number_format($total_seats) ?></div><div class="ms-stat-lbl">Available Seats</div></div>
        </div>
        <div class="ms-stat">
            <div class="ms-stat-icon amber">⚠️</div>
            <div><div class="ms-stat-val"><?= $low_seats ?></div><div class="ms-stat-lbl">Low Seats (≤10)</div></div>
        </div>
        <div class="ms-stat">
            <div class="ms-stat-icon red">🚫</div>
            <div><div class="ms-stat-val"><?= $full_flights ?></div><div class="ms-stat-lbl">Fully Booked</div></div>
        </div>
    </div>

    <!-- Flight cards -->
    <div class="ms-card">
        <div class="ms-card-head">
            <h2>All Flights</h2>
            <span class="ms-badge"><?= $total_flights ?> flights</span>
        </div>

        <?php if (empty($flights)): ?>
        <div class="ms-empty">
            <span class="ei">🛫</span>
            <p>No flights found. Add flights first.</p>
        </div>
        <?php else: ?>
        <div class="ms-grid" id="msGrid">
            <?php foreach ($flights as $f):
                $seat       = (int)$f['seat'];
                $total      = (int)($f['total_seats'] ?? max($seat, 1));
                $pct        = $total > 0 ? min(100, round($seat / $total * 100)) : 0;
                $status     = $f['status'] ?? 'active';
                $stripClass = $status === 'inactive' ? 'inactive' : ($seat === 0 ? 'empty' : ($seat <= 10 ? 'low' : ''));
                $countClass = $seat === 0 ? 'empty' : ($seat <= 10 ? 'low' : '');
                $barClass   = $seat === 0 ? 'empty' : ($seat <= 10 ? 'low' : '');
            ?>
            <div class="ms-flight-card"
                 data-name="<?= strtolower(htmlspecialchars($f['flight_name'])) ?>"
                 data-airline="<?= strtolower(htmlspecialchars($f['airline_name'])) ?>"
                 data-code="<?= strtolower(htmlspecialchars($f['flight_code'])) ?>">

                <div class="ms-fc-strip <?= $stripClass ?>"></div>

                <div class="ms-fc-body">
                    <div class="ms-fc-header">
                        <div>
                            <div class="ms-fc-name"><?= htmlspecialchars($f['flight_name']) ?></div>
                            <div class="ms-fc-code"><?= htmlspecialchars($f['flight_code']) ?></div>
                        </div>
                        <span class="ms-fc-status <?= $status ?>"><?= ucfirst($status) ?></span>
                    </div>

                    <div class="ms-fc-route">
                        <?= htmlspecialchars($f['departure']) ?>
                        <span class="arr">→</span>
                        <?= htmlspecialchars($f['arrival']) ?>
                    </div>
                    <div class="ms-fc-airline">
                        ✈ <?= htmlspecialchars($f['airline_name']) ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($f['flight_class'] ?? 'Economy') ?>
                    </div>

                    <div class="ms-seat-info">
                        <div class="ms-seat-row">
                            <span class="ms-seat-label">Available Seats</span>
                            <span class="ms-seat-count <?= $countClass ?>">
                                <?= $seat ?> / <?= $total ?>
                                <?php if ($seat === 0): ?> 🚫<?php elseif ($seat <= 10): ?> ⚠️<?php endif; ?>
                            </span>
                        </div>
                        <div class="ms-progress-bg">
                            <div class="ms-progress-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <div class="ms-seat-meta"><?= $pct ?>% seats remaining</div>
                    </div>
                </div>

                <form method="POST" action="<?= BASE_URL ?>/view/manageSeat.php" class="ms-edit-form">
                    <input type="hidden" name="flight_id" value="<?= $f['id'] ?>">
                    <input type="number" name="seat" value="<?= $seat ?>"
                           min="0" max="<?= $total ?>" required
                           placeholder="Seats">
                    <button type="submit" name="update_seat" class="ms-btn-update">
                        <i class="fas fa-floppy-disk"></i> Update
                    </button>
                </form>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function msFilter(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#msGrid .ms-flight-card').forEach(card => {
        const match = !q
            || card.dataset.name.includes(q)
            || card.dataset.airline.includes(q)
            || card.dataset.code.includes(q);
        card.style.display = match ? '' : 'none';
    });
}

const msFlash = document.getElementById('msFlash');
if (msFlash) setTimeout(() => msFlash.remove(), 4000);
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
