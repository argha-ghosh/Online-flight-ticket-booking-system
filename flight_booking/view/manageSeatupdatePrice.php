<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/base_url.php";
include("../model/db_conn.php");

// Guard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: " . BASE_URL . "/view/login.php"); exit;
}

// ── Handle update (PRG) ──────────────────────────────────────
if (isset($_POST['update_flight'])) {
    $id           = (int)$_POST['flight_id'];
    $price        = (float)$_POST['price'];
    $seat         = (int)$_POST['seat'];
    $discount_pct = isset($_POST['discount_pct']) ? min(100, max(0, (float)$_POST['discount_pct'])) : 0;
    $seat_class   = in_array($_POST['seat_class'] ?? '', ['Economy','Business','First Class'])
                    ? $_POST['seat_class'] : 'Economy';
    $status       = in_array($_POST['status'] ?? '', ['active','inactive','cancelled'])
                    ? $_POST['status'] : 'active';

    $stmt = $conn->prepare(
        "UPDATE flights SET price=?, seat=?, discount_pct=?, seat_class=?, status=? WHERE id=?"
    );
    $stmt->bind_param("didssi", $price, $seat, $discount_pct, $seat_class, $status, $id);

    if ($stmt->execute()) {
        $_SESSION['sp_msg']      = "Flight #$id updated successfully!";
        $_SESSION['sp_msg_type'] = "success";
    } else {
        $_SESSION['sp_msg']      = "Update failed: " . $stmt->error;
        $_SESSION['sp_msg_type'] = "error";
    }
    $stmt->close();
    header("Location: " . BASE_URL . "/view/manageSeatupdatePrice.php"); exit;
}

// Consume flash
$flash_msg  = $_SESSION['sp_msg']      ?? '';
$flash_type = $_SESSION['sp_msg_type'] ?? '';
unset($_SESSION['sp_msg'], $_SESSION['sp_msg_type']);

// Fetch flights
$flights = [];
$res = $conn->query("SELECT * FROM flights ORDER BY id DESC");
if ($res) while ($r = $res->fetch_assoc()) $flights[] = $r;

// Stats
$total_flights   = count($flights);
$total_seats     = array_sum(array_column($flights, 'seat'));
$avg_price       = $total_flights ? array_sum(array_column($flights, 'price')) / $total_flights : 0;
$active_flights  = count(array_filter($flights, fn($f) => ($f['status'] ?? 'active') === 'active'));

include("../includes/managerheader.php");
?>
<style>
/* ── Dashboard wrapper ── */
.sp-wrap { flex:1; padding:28px 32px 60px; }
.sp-wrap * { box-sizing:border-box; }

/* ── Top bar ── */
.sp-topbar {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:24px; flex-wrap:wrap; gap:12px;
}
.sp-topbar-left h1 {
    font-size:1.35rem; font-weight:800; color:#0f172a; letter-spacing:-0.3px;
}
.sp-topbar-left p { font-size:0.82rem; color:#64748b; margin-top:2px; }
.sp-search-wrap { position:relative; }
.sp-search-wrap input {
    padding:9px 14px 9px 36px; border:1.5px solid #e2e8f0;
    border-radius:10px; font-size:0.87rem; background:#fff;
    outline:none; font-family:inherit; color:#1e293b; width:260px;
    transition:border-color 0.2s, box-shadow 0.2s;
}
.sp-search-wrap input:focus { border-color:#0b72e6; box-shadow:0 0 0 3px rgba(11,114,230,0.1); }
.sp-search-wrap .s-ico {
    position:absolute; left:11px; top:50%; transform:translateY(-50%);
    font-size:0.85rem; pointer-events:none;
}

/* ── Flash ── */
.sp-flash {
    display:flex; align-items:center; gap:10px;
    padding:12px 18px; border-radius:12px;
    font-size:0.87rem; font-weight:600;
    margin-bottom:20px; animation:spFade 0.3s ease;
}
@keyframes spFade { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.sp-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.sp-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.sp-flash .sp-close {
    margin-left:auto; cursor:pointer; opacity:0.5; font-size:0.95rem;
    background:none; border:none; color:inherit; padding:0; font-family:inherit;
}

/* ── Stat cards ── */
.sp-stats {
    display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;
}
.sp-stat {
    background:#fff; border-radius:16px; padding:18px 20px;
    border:1px solid #e8f0fb; box-shadow:0 2px 12px rgba(11,114,230,0.06);
    display:flex; align-items:center; gap:14px;
}
.sp-stat-icon {
    width:46px; height:46px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.3rem; flex-shrink:0;
}
.sp-stat-icon.blue   { background:#eff6ff; }
.sp-stat-icon.green  { background:#f0fdf4; }
.sp-stat-icon.purple { background:#f5f3ff; }
.sp-stat-icon.amber  { background:#fffbeb; }
.sp-stat-val { font-size:1.4rem; font-weight:800; color:#0f172a; line-height:1; }
.sp-stat-lbl { font-size:0.75rem; color:#64748b; margin-top:3px; }

/* ── Table card ── */
.sp-card {
    background:#fff; border-radius:20px;
    box-shadow:0 4px 24px rgba(11,114,230,0.08);
    border:1px solid #e8f0fb; overflow:hidden;
}
.sp-card-head {
    padding:16px 24px; border-bottom:1px solid #f1f5f9;
    display:flex; align-items:center; justify-content:space-between;
    background:#fafcff;
}
.sp-card-head h2 {
    font-size:0.95rem; font-weight:700; color:#0f172a;
    display:flex; align-items:center; gap:8px;
}
.sp-card-head h2::before {
    content:''; display:inline-block; width:3px; height:1em;
    background:linear-gradient(180deg,#0b72e6,#6c3de8); border-radius:3px;
}
.sp-badge {
    font-size:0.75rem; font-weight:700; padding:4px 12px;
    border-radius:20px; background:linear-gradient(135deg,#0b72e6,#6c3de8); color:#fff;
}
</style>
<style>
/* ── Table ── */
.sp-table-wrap { overflow-x:auto; }
table.sp-table {
    width:100%; border-collapse:collapse; font-size:0.86rem;
}
.sp-table thead tr {
    background:#f8fafc; border-bottom:2px solid #e8f0fb;
}
.sp-table th {
    padding:12px 16px; text-align:left; font-size:0.72rem;
    font-weight:700; color:#64748b; text-transform:uppercase;
    letter-spacing:0.6px; white-space:nowrap;
}
.sp-table tbody tr {
    border-bottom:1px solid #f1f5f9;
    transition:background 0.15s;
}
.sp-table tbody tr:hover { background:#fafcff; }
.sp-table tbody tr.sp-editing { background:#f0f7ff; }
.sp-table td { padding:13px 16px; color:#334155; vertical-align:middle; }

/* Route cell */
.sp-route { display:flex; align-items:center; gap:6px; font-weight:600; color:#0f172a; }
.sp-route .arr { color:#0b72e6; font-size:1rem; }

/* Status badge */
.sp-status {
    display:inline-flex; align-items:center; gap:4px;
    font-size:0.7rem; font-weight:700; padding:3px 9px;
    border-radius:20px; text-transform:uppercase; letter-spacing:0.4px;
}
.sp-status.active    { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
.sp-status.inactive  { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; }
.sp-status.cancelled { background:#fef9c3; color:#92400e; border:1px solid #fde68a; }

/* Class badge */
.sp-class {
    font-size:0.72rem; font-weight:600; padding:3px 9px;
    border-radius:20px; background:#f5f3ff; color:#7c3aed; border:1px solid #ddd6fe;
}

/* Price display */
.sp-price { font-weight:700; color:#0b72e6; font-size:0.95rem; }
.sp-discount { font-size:0.72rem; color:#16a34a; font-weight:600; }

/* Seats display */
.sp-seats { font-weight:700; color:#0f172a; }

/* ── Edit row inline inputs ── */
.sp-edit-row { display:none; }
.sp-edit-row.open { display:table-row; }
.sp-edit-cell {
    padding:16px 16px 20px; background:#f0f7ff;
    border-bottom:2px solid #0b72e6;
}
.sp-edit-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
    gap:14px; align-items:end;
}
.sp-ef { display:flex; flex-direction:column; gap:5px; }
.sp-ef label {
    font-size:0.7rem; font-weight:700; color:#475569;
    text-transform:uppercase; letter-spacing:0.5px;
}
.sp-ef input, .sp-ef select {
    padding:9px 12px; border:1.5px solid #bfdbfe; border-radius:9px;
    font-size:0.87rem; color:#1e293b; background:#fff;
    outline:none; font-family:inherit; appearance:none;
    transition:border-color 0.2s, box-shadow 0.2s;
}
.sp-ef input:focus, .sp-ef select:focus {
    border-color:#0b72e6; box-shadow:0 0 0 3px rgba(11,114,230,0.1);
}
.sp-ef .sel-wrap { position:relative; }
.sp-ef .sel-wrap::after {
    content:'▾'; position:absolute; right:10px; top:50%;
    transform:translateY(-50%); color:#94a3b8; pointer-events:none; font-size:0.75rem;
}
.sp-ef .sel-wrap select { padding-right:28px; width:100%; }

/* Edit action buttons */
.sp-edit-actions { display:flex; gap:10px; align-items:flex-end; }
.sp-btn-save {
    padding:9px 20px; background:linear-gradient(135deg,#0b72e6,#6c3de8);
    color:#fff; border:none; border-radius:9px; font-size:0.85rem;
    font-weight:700; cursor:pointer; font-family:inherit;
    transition:opacity 0.2s, transform 0.15s;
    box-shadow:0 3px 10px rgba(11,114,230,0.3); white-space:nowrap;
}
.sp-btn-save:hover { opacity:0.9; transform:translateY(-1px); }
.sp-btn-cancel-edit {
    padding:9px 16px; background:#fff; color:#64748b;
    border:1.5px solid #e2e8f0; border-radius:9px; font-size:0.85rem;
    font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.15s;
}
.sp-btn-cancel-edit:hover { border-color:#94a3b8; color:#334155; }

/* Row edit trigger */
.sp-btn-edit {
    padding:7px 14px; background:#eff6ff; color:#2563eb;
    border:1.5px solid #bfdbfe; border-radius:8px; font-size:0.78rem;
    font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.18s;
    white-space:nowrap;
}
.sp-btn-edit:hover { background:#2563eb; color:#fff; border-color:#2563eb; }

/* Empty state */
.sp-empty { text-align:center; padding:60px 20px; color:#94a3b8; }
.sp-empty .sp-empty-icon { font-size:3rem; display:block; margin-bottom:10px; opacity:0.4; }

/* Responsive */
@media (max-width:900px) {
    .sp-stats { grid-template-columns:1fr 1fr; }
    .sp-wrap { padding:16px 14px 40px; }
}
@media (max-width:500px) {
    .sp-stats { grid-template-columns:1fr; }
    .sp-search-wrap input { width:100%; }
    .sp-topbar { flex-direction:column; align-items:flex-start; }
}
</style>

<div class="sp-wrap">

    <!-- Top bar -->
    <div class="sp-topbar">
        <div class="sp-topbar-left">
            <h1>💺 Seats &amp; Pricing</h1>
            <p>Update seat availability, prices and discounts for all flights</p>
        </div>
        <div class="sp-search-wrap">
            <span class="s-ico">🔍</span>
            <input type="text" id="spSearch" placeholder="Search flights…" oninput="spFilter(this.value)">
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash_msg): ?>
        <div class="sp-flash <?= htmlspecialchars($flash_type) ?>" id="spFlash">
            <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($flash_msg) ?>
            <button class="sp-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="sp-stats">
        <div class="sp-stat">
            <div class="sp-stat-icon blue">🛫</div>
            <div>
                <div class="sp-stat-val"><?= $total_flights ?></div>
                <div class="sp-stat-lbl">Total Flights</div>
            </div>
        </div>
        <div class="sp-stat">
            <div class="sp-stat-icon green">✅</div>
            <div>
                <div class="sp-stat-val"><?= $active_flights ?></div>
                <div class="sp-stat-lbl">Active Flights</div>
            </div>
        </div>
        <div class="sp-stat">
            <div class="sp-stat-icon purple">💺</div>
            <div>
                <div class="sp-stat-val"><?= number_format($total_seats) ?></div>
                <div class="sp-stat-lbl">Total Seats</div>
            </div>
        </div>
        <div class="sp-stat">
            <div class="sp-stat-icon amber">💵</div>
            <div>
                <div class="sp-stat-val">$<?= number_format($avg_price, 0) ?></div>
                <div class="sp-stat-lbl">Avg. Price</div>
            </div>
        </div>
    </div>

    <!-- Table card -->
    <div class="sp-card">
        <div class="sp-card-head">
            <h2>All Flights</h2>
            <span class="sp-badge"><?= $total_flights ?> flights</span>
        </div>

        <div class="sp-table-wrap">
            <?php if (empty($flights)): ?>
                <div class="sp-empty">
                    <span class="sp-empty-icon">🛫</span>
                    <p>No flights found. Add flights first.</p>
                </div>
            <?php else: ?>
            <table class="sp-table" id="spTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Flight</th>
                        <th>Airline</th>
                        <th>Route</th>
                        <th>Class</th>
                        <th>Price</th>
                        <th>Discount</th>
                        <th>Seats</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($flights as $i => $f):
                    $status      = $f['status']       ?? 'active';
                    $seat_class  = $f['seat_class']    ?? ($f['flight_class'] ?? 'Economy');
                    $discount    = (float)($f['discount_pct'] ?? 0);
                    $final_price = $f['price'] * (1 - $discount / 100);
                ?>
                    <!-- Data row -->
                    <tr class="sp-data-row" id="row-<?= $f['id'] ?>"
                        data-name="<?= strtolower(htmlspecialchars($f['flight_name'])) ?>"
                        data-airline="<?= strtolower(htmlspecialchars($f['airline_name'])) ?>"
                        data-code="<?= strtolower(htmlspecialchars($f['flight_code'])) ?>">
                        <td style="color:#94a3b8;font-size:0.8rem;"><?= $i + 1 ?></td>
                        <td>
                            <div style="font-weight:700;color:#0f172a;"><?= htmlspecialchars($f['flight_name']) ?></div>
                            <div style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($f['flight_code']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($f['airline_name']) ?></td>
                        <td>
                            <div class="sp-route">
                                <span><?= htmlspecialchars($f['departure']) ?></span>
                                <span class="arr">→</span>
                                <span><?= htmlspecialchars($f['arrival']) ?></span>
                            </div>
                        </td>
                        <td><span class="sp-class"><?= htmlspecialchars($seat_class) ?></span></td>
                        <td>
                            <div class="sp-price">$<?= number_format($final_price, 2) ?></div>
                            <?php if ($discount > 0): ?>
                                <div class="sp-discount">↓<?= $discount ?>% off $<?= number_format($f['price'], 2) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $discount > 0 ? $discount . '%' : '—' ?></td>
                        <td><span class="sp-seats"><?= (int)($f['seat'] ?? 0) ?></span></td>
                        <td><span class="sp-status <?= $status ?>"><?= ucfirst($status) ?></span></td>
                        <td>
                            <button class="sp-btn-edit" onclick="spOpenEdit(<?= $f['id'] ?>)">
                                ✏️ Edit
                            </button>
                        </td>
                    </tr>
                    <!-- Inline edit row -->
                    <tr class="sp-edit-row" id="edit-<?= $f['id'] ?>">
                        <td class="sp-edit-cell" colspan="10">
                            <form method="POST" action="<?= BASE_URL ?>/view/manageSeatupdatePrice.php">
                                <input type="hidden" name="flight_id" value="<?= $f['id'] ?>">
                                <div class="sp-edit-grid">
                                    <div class="sp-ef">
                                        <label>Base Price ($)</label>
                                        <input type="number" step="0.01" min="0" name="price"
                                               value="<?= number_format($f['price'], 2, '.', '') ?>" required>
                                    </div>
                                    <div class="sp-ef">
                                        <label>Discount (%)</label>
                                        <input type="number" step="0.1" min="0" max="100" name="discount_pct"
                                               value="<?= $discount ?>" placeholder="0">
                                    </div>
                                    <div class="sp-ef">
                                        <label>Available Seats</label>
                                        <input type="number" min="0" name="seat"
                                               value="<?= (int)($f['seat'] ?? 0) ?>" required>
                                    </div>
                                    <div class="sp-ef">
                                        <label>Seat Class</label>
                                        <div class="sel-wrap">
                                            <select name="seat_class">
                                                <?php foreach (['Economy','Business','First Class'] as $cls): ?>
                                                    <option value="<?= $cls ?>" <?= $seat_class === $cls ? 'selected' : '' ?>><?= $cls ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="sp-ef">
                                        <label>Status</label>
                                        <div class="sel-wrap">
                                            <select name="status">
                                                <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>✅ Active</option>
                                                <option value="inactive"  <?= $status === 'inactive'  ? 'selected' : '' ?>>❌ Inactive</option>
                                                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>⚠️ Cancelled</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="sp-ef sp-edit-actions">
                                        <label>&nbsp;</label>
                                        <button type="submit" name="update_flight" class="sp-btn-save">💾 Save</button>
                                        <button type="button" class="sp-btn-cancel-edit"
                                                onclick="spCloseEdit(<?= $f['id'] ?>)">✕</button>
                                    </div>
                                </div>
                            </form>
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
function spOpenEdit(id) {
    // Close any other open edit rows
    document.querySelectorAll('.sp-edit-row.open').forEach(r => r.classList.remove('open'));
    document.querySelectorAll('.sp-data-row.sp-editing').forEach(r => r.classList.remove('sp-editing'));
    document.getElementById('edit-' + id).classList.add('open');
    document.getElementById('row-'  + id).classList.add('sp-editing');
}
function spCloseEdit(id) {
    document.getElementById('edit-' + id).classList.remove('open');
    document.getElementById('row-'  + id).classList.remove('sp-editing');
}
function spFilter(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.sp-data-row').forEach(row => {
        const match = !q || row.dataset.name.includes(q)
                         || row.dataset.airline.includes(q)
                         || row.dataset.code.includes(q);
        const editRow = document.getElementById('edit-' + row.id.replace('row-',''));
        row.style.display     = match ? '' : 'none';
        if (editRow) editRow.style.display = match ? '' : 'none';
    });
}
const spFlash = document.getElementById('spFlash');
if (spFlash) setTimeout(() => spFlash.remove(), 4000);
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
