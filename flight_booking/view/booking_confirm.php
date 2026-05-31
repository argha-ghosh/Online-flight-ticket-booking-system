<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}
if (!isset($_GET['id'])) {
    header("Location: myBookings.php"); exit;
}

$booking_id = (int)$_GET['id'];
$email      = $_SESSION['email'];

$stmt = $conn->prepare("
    SELECT b.*,
           f.flight_name, f.airline_name, f.flight_code, f.duration, f.image as flight_image,
           f.departure_time, f.arrival_time,
           f.status as flight_status,
           ROUND(f.price * (1 - f.discount_pct / 100), 2) AS current_unit_price,
           f.discount_pct,
           s.departure_day, s.arrival_day,
           s.departure_time AS sched_dep_time,
           s.arrival_time   AS sched_arr_time,
           w.name as passenger_name, w.email as passenger_email, w.image as user_image
    FROM bookings b
    JOIN flights f  ON b.flight_id = f.id
    LEFT JOIN schedule s ON s.flight_code COLLATE utf8mb4_unicode_ci = f.flight_code
    JOIN webusers w ON b.user_id = w.id
    WHERE b.id = ? AND w.email = ?
");
$stmt->bind_param("is", $booking_id, $email);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) { header("Location: myBookings.php"); exit; }

/* Booking stats for sidebar */
$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*) as total,
        SUM(status='confirmed') as confirmed,
        SUM(status='cancelled') as cancelled,
        SUM(CASE WHEN status='confirmed' THEN total_price ELSE 0 END) as spent
    FROM bookings b
    JOIN webusers w ON b.user_id = w.id
    WHERE w.email = ?
");
$stats_stmt->bind_param("s", $email);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$dep_t   = substr(!empty($booking['sched_dep_time']) ? $booking['sched_dep_time'] : ($booking['departure_time'] ?? ''), 0, 5);
$arr_t   = substr(!empty($booking['sched_arr_time']) ? $booking['sched_arr_time'] : ($booking['arrival_time']   ?? ''), 0, 5);
$dep_day = $booking['departure_day'] ?? '';
$arr_day = $booking['arrival_day']   ?? '';
$ref     = str_pad($booking['id'], 6, '0', STR_PAD_LEFT);

$qr_data = "GoZayan #{$ref} | {$booking['passenger_name']} | {$booking['flight_code']} | {$booking['from_location']} to {$booking['to_location']} | " . date('d M Y', strtotime($booking['depart_date']));
$qr_url  = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($qr_data);

/* Avatar src */
$avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($booking['passenger_name']) . "&background=0f2540&color=fff&size=80";
if (!empty($booking['user_image']) && file_exists(__DIR__ . "/uploads/" . $booking['user_image'])) {
    $avatar_src = "uploads/" . htmlspecialchars($booking['user_image']);
}

include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GoZayan · Booking #<?= $ref ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:wght@400;500;600&family=Mulish:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
/* ─── TOKENS ──────────────────────────────────── */
:root {
    --navy:       #0b1f3a;
    --navy-2:     #142d52;
    --navy-3:     #1e3d63;
    --blue:       #1a56db;
    --gold:       #c9a84c;
    --gold-lt:    #e8c96a;
    --cream:      #f7f4ef;
    --cream-2:    #ede9e1;
    --ink:        #0f1923;
    --ink-2:      #3d4f63;
    --ink-3:      #7a90a6;
    --surface:    #ffffff;
    --green:      #059669;
    --green-bg:   #d1fae5;
    --red:        #dc2626;
    --border:     #e4ddd0;
    --shadow-sm:  0 2px 8px rgba(11,31,58,.06);
    --shadow:     0 4px 20px rgba(11,31,58,.08), 0 12px 40px rgba(11,31,58,.06);
    --mono:       'DM Mono','Courier New',monospace;
    --serif:      'Playfair Display',Georgia,serif;
    --sans:       'Mulish',sans-serif;
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; }

body {
    font-family: var(--sans);
    background: var(--cream);
    color: var(--ink);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
    /* ── FIX: push ALL content below the fixed header ── */
    padding-top: 68px;
}

/* ─── CONFIRMATION BANNER ─────────────────────── */
/* sits immediately below the fixed header — no overlap */
.confirm-banner {
    background: var(--navy);
    padding: 16px 36px;
    display: flex; align-items: center; gap: 18px;
    border-bottom: 1px solid rgba(255,255,255,.06);
    /* full-width stripe, not inside page-wrap */
}
.banner-check {
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--green); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
    box-shadow: 0 0 0 5px rgba(5,150,105,.2);
    animation: checkPop .5s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes checkPop { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
.banner-text h2 {
    font-family: var(--serif);
    font-size: 1.1rem; font-weight: 700;
    color: #fff; margin-bottom: 1px;
}
.banner-text p { font-size: .75rem; color: rgba(255,255,255,.45); }
.banner-ref {
    margin-left: auto;
    font-family: var(--mono); font-size: .75rem;
    color: var(--gold-lt);
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(201,168,76,.28);
    padding: 6px 16px; border-radius: 30px;
    letter-spacing: .08em; white-space: nowrap;
}

/* ─── BACK LINK ───────────────────────────────── */
.back-bar {
    max-width: 1280px; margin: 0 auto;
    padding: 16px 32px 0;
}
.back-link {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: .75rem; font-weight: 700; color: var(--ink-3);
    text-decoration: none; letter-spacing: .06em; text-transform: uppercase;
    transition: color .18s;
}
.back-link:hover { color: var(--blue); }
.back-link i { font-size: .7rem; }

/* ─── THREE-COLUMN LAYOUT ─────────────────────── */
.page-wrap {
    max-width: 1280px; margin: 0 auto;
    padding: 24px 32px 80px;
    display: grid;
    grid-template-columns: 220px 1fr 296px;
    gap: 24px;
    align-items: start;
}

/* ═══════════════════════════════════════════════
   LEFT SIDEBAR  (passenger nav)
═══════════════════════════════════════════════ */
.left-sidebar {
    display: flex; flex-direction: column; gap: 14px;
    position: sticky; top: 88px;   /* header height + small gap */
    animation: riseIn .45s .05s cubic-bezier(0.16,1,.3,1) both;
}

/* Profile mini-card */
.sidebar-profile {
    background: var(--navy);
    border-radius: 16px;
    padding: 20px 16px;
    text-align: center;
    border: 1px solid rgba(255,255,255,.06);
    box-shadow: var(--shadow-sm);
}
.sp-avatar-wrap {
    position: relative; display: inline-block; margin-bottom: 12px;
}
.sp-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(201,168,76,.4);
    box-shadow: 0 0 0 4px rgba(201,168,76,.1);
}
.sp-online {
    position: absolute; bottom: 2px; right: 2px;
    width: 12px; height: 12px; border-radius: 50%;
    background: var(--green); border: 2px solid var(--navy);
}
.sp-name { font-size: .88rem; font-weight: 800; color: #fff; margin-bottom: 2px; }
.sp-email { font-size: .68rem; color: rgba(255,255,255,.4); word-break: break-all; }
.sp-badge {
    display: inline-block; margin-top: 10px;
    font-family: var(--mono); font-size: .58rem; font-weight: 500;
    letter-spacing: .1em; text-transform: uppercase;
    color: var(--gold-lt);
    background: rgba(201,168,76,.12);
    border: 1px solid rgba(201,168,76,.22);
    padding: 3px 12px; border-radius: 20px;
}

/* Stats row */
.sidebar-stats {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.sstat {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 10px;
    text-align: center;
    box-shadow: var(--shadow-sm);
}
.sstat .ss-num {
    font-family: var(--mono); font-size: 1.15rem; font-weight: 600;
    color: var(--navy); font-variant-numeric: tabular-nums;
    display: block;
}
.sstat .ss-lbl {
    font-size: .62rem; color: var(--ink-3); font-weight: 600;
    text-transform: uppercase; letter-spacing: .06em;
    display: block; margin-top: 2px;
}
.sstat.gold .ss-num { color: var(--gold); }
.sstat.green .ss-num { color: var(--green); }

/* Nav links */
.sidebar-nav {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.snav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 16px;
    font-size: .82rem; font-weight: 600; color: var(--ink-2);
    text-decoration: none;
    border-bottom: 1px solid var(--border);
    transition: all .18s;
}
.snav-item:last-child { border-bottom: none; }
.snav-item:hover { background: var(--cream); color: var(--navy); padding-left: 20px; }
.snav-item.active {
    background: var(--cream);
    color: var(--navy);
    border-left: 3px solid var(--gold);
    font-weight: 800;
}
.snav-item i {
    width: 18px; text-align: center;
    font-size: .82rem; color: var(--ink-3);
    flex-shrink: 0;
}
.snav-item:hover i, .snav-item.active i { color: var(--gold); }

/* Logout button */
.sidebar-logout {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 11px;
    background: transparent; border: 1.5px solid var(--border);
    border-radius: 12px; color: var(--ink-3);
    font-family: var(--sans); font-size: .8rem; font-weight: 700;
    cursor: pointer; text-decoration: none;
    transition: all .2s; letter-spacing: .02em;
}
.sidebar-logout:hover { border-color: var(--red); color: var(--red); background: rgba(220,38,38,.04); }

/* ═══════════════════════════════════════════════
   BOARDING PASS  (centre column)
═══════════════════════════════════════════════ */
.bp {
    background: var(--surface);
    border-radius: 20px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: riseIn .5s .1s cubic-bezier(0.16,1,.3,1) both;
}
@keyframes riseIn { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

.bp-stripe {
    height: 4px;
    background: linear-gradient(90deg, var(--navy) 0%, var(--blue) 50%, var(--gold) 100%);
}

/* Head */
.bp-head {
    background: var(--navy);
    padding: 26px 32px 30px;
    position: relative; overflow: hidden;
}
.bp-head::before {
    content: '✈';
    position: absolute; right: -16px; top: -20px;
    font-size: 8rem; opacity: .04; line-height: 1; transform: rotate(8deg);
    pointer-events: none;
}
.bp-head::after {
    content: '';
    position: absolute; inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size: 22px 22px; pointer-events: none;
}
.bp-head-top {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 24px; position: relative; z-index: 1;
}
.bp-airline {
    font-family: var(--serif); font-size: 1.3rem; font-weight: 700;
    color: #fff; letter-spacing: -.02em; margin-bottom: 4px;
}
.bp-flight-sub { font-family: var(--mono); font-size: .65rem; color: rgba(255,255,255,.42); letter-spacing: .1em; }
.bp-ticket-ref {
    font-family: var(--mono); font-size: .72rem; font-weight: 500;
    color: var(--gold-lt);
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(201,168,76,.22);
    padding: 5px 14px; border-radius: 20px; letter-spacing: .08em;
}

/* Route */
.bp-route { display:flex; align-items:center; position:relative; z-index:1; }
.bp-city { text-align:center; }
.bp-iata {
    font-family: var(--serif); font-size: 3.2rem; font-weight: 900;
    color: #fff; letter-spacing: -.04em; line-height: 1;
}
.bp-city-name { font-size:.68rem; color:rgba(255,255,255,.42); margin-top:4px; font-weight:500; letter-spacing:.06em; text-transform:uppercase; }
.bp-time-block { margin-top:7px; font-family:var(--mono); font-size:.82rem; font-weight:500; color:var(--gold-lt); letter-spacing:.04em; }
.bp-day-block  { font-size:.6rem; color:rgba(255,255,255,.32); margin-top:2px; letter-spacing:.06em; }
.bp-route-mid { flex:1; display:flex; flex-direction:column; align-items:center; gap:7px; padding:0 22px; }
.bp-dur-pill {
    font-family:var(--mono); font-size:.6rem;
    color:rgba(255,255,255,.42);
    background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
    padding:3px 12px; border-radius:20px; letter-spacing:.08em;
}
.bp-line { display:flex; align-items:center; width:100%; }
.bp-line::before,.bp-line::after { content:''; flex:1; height:1px; background:linear-gradient(90deg,rgba(255,255,255,.1),rgba(255,255,255,.25)); }
.bp-line::after { background:linear-gradient(90deg,rgba(255,255,255,.25),rgba(255,255,255,.1)); }
.bp-plane-icon { font-size:.95rem; color:var(--gold-lt); margin:0 8px; }

/* Tear line */
.bp-tear {
    display:flex; align-items:center; background:var(--cream); height:28px; position:relative;
}
.bp-tear::before { content:''; position:absolute; left:-1px; top:50%; transform:translateY(-50%); width:18px; height:36px; border-radius:0 18px 18px 0; background:var(--cream); border:1px solid var(--border); border-left:none; }
.bp-tear::after  { content:''; position:absolute; right:-1px; top:50%; transform:translateY(-50%); width:18px; height:36px; border-radius:18px 0 0 18px; background:var(--cream); border:1px solid var(--border); border-right:none; }
.bp-tear-dash { flex:1; margin:0 22px; border-top:2px dashed var(--border); }
.bp-tear-scissors { font-size:.82rem; color:var(--ink-3); }

/* Body */
.bp-body {
    padding: 26px 32px 28px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 28px;
    align-items: start;
}
.bp-fields-grid {
    display: grid; grid-template-columns: repeat(4,1fr);
    gap: 18px 14px; margin-bottom: 24px;
}
.bp-field .f-lbl {
    font-family: var(--mono); font-size:.56rem; font-weight:500;
    letter-spacing:.14em; text-transform:uppercase; color:var(--ink-3); margin-bottom:4px;
}
.bp-field .f-val { font-size:.88rem; font-weight:700; color:var(--ink); line-height:1.3; }
.bp-rule { height:1px; background:var(--border); margin-bottom:22px; }
.bp-price-row { display:flex; align-items:center; justify-content:space-between; }
.price-label {
    font-family:var(--mono); font-size:.58rem; font-weight:500;
    letter-spacing:.14em; text-transform:uppercase; color:var(--ink-3); margin-bottom:4px;
}
.price-amount {
    font-family: var(--serif); font-size:2.4rem; font-weight:900;
    color:var(--navy); letter-spacing:-.04em; line-height:1;
}
.price-per { font-size:.7rem; color:var(--ink-3); font-weight:500; margin-top:2px; font-family:var(--mono); }
.status-badge {
    display:inline-flex; align-items:center; gap:7px;
    padding:8px 20px; border-radius:30px; font-size:.78rem; font-weight:800; letter-spacing:.04em;
}
.s-confirmed { background:var(--green-bg); color:var(--green); border:1px solid rgba(5,150,105,.22); }
.s-cancelled  { background:#fee2e2; color:var(--red); border:1px solid rgba(220,38,38,.18); }

/* QR */
.bp-qr { display:flex; flex-direction:column; align-items:center; gap:8px; text-align:center; }
.qr-frame { width:124px; height:124px; border:1.5px solid var(--border); border-radius:12px; overflow:hidden; background:#fff; padding:5px; box-shadow:var(--shadow-sm); }
.qr-frame img { width:100%; height:100%; display:block; border-radius:7px; }
.qr-label { font-family:var(--mono); font-size:.58rem; font-weight:500; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-3); }
.qr-ref   { font-family:var(--mono); font-size:.75rem; font-weight:600; color:var(--blue); letter-spacing:.04em; }

/* Payment strip */
.bp-payment {
    background:var(--cream); border-top:1px solid var(--border);
    padding:16px 32px; display:flex; gap:28px; flex-wrap:wrap;
}
.pay-item .py-lbl { font-family:var(--mono); font-size:.56rem; font-weight:500; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-3); margin-bottom:3px; }
.pay-item .py-val { font-size:.82rem; font-weight:700; color:var(--ink); }

/* ═══════════════════════════════════════════════
   RIGHT COLUMN  (actions + journey summary)
═══════════════════════════════════════════════ */
.right-col {
    display: flex; flex-direction: column; gap: 16px;
    animation: riseIn .5s .22s cubic-bezier(0.16,1,.3,1) both;
}

.panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.panel-head {
    padding: 13px 18px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 9px;
    font-size: .72rem; font-weight: 800;
    color: var(--ink); text-transform: uppercase; letter-spacing: .09em;
}
.ph-dot { width:7px; height:7px; border-radius:50%; background:var(--gold); flex-shrink:0; }
.panel-body { padding: 16px 18px; }

/* Journey mini */
.journey-card {
    background: var(--navy); border-radius: 14px; padding: 18px 16px;
}
.jc-route {
    display: flex; align-items: center; gap: 8px;
    font-family: var(--serif); font-size: 1.25rem; font-weight: 700; color: #fff;
    margin-bottom: 10px; letter-spacing: -.02em;
}
.jc-arrow { color: var(--gold-lt); font-size: .9rem; }
.jc-chips { display: flex; flex-wrap: wrap; gap: 5px; }
.jc-chip {
    font-family: var(--mono); font-size: .6rem; font-weight: 500; letter-spacing: .06em;
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.1);
    color: rgba(255,255,255,.55); padding: 3px 9px; border-radius: 20px;
}
.jc-chip.hi { background: rgba(201,168,76,.16); border-color: rgba(201,168,76,.28); color: var(--gold-lt); }

/* Info rows in panel */
.info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px solid #f5f2ec; font-size: .8rem;
}
.info-row:last-child { border-bottom: none; }
.info-row .k { color: var(--ink-3); font-weight: 500; }
.info-row .v { font-weight: 700; color: var(--ink); text-align: right; max-width: 58%; }

/* Action buttons */
.act-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 11px 14px; border-radius: 10px;
    font-family: var(--sans); font-size: .82rem; font-weight: 700;
    cursor: pointer; text-decoration: none; transition: all .2s;
    border: none; margin-bottom: 7px; letter-spacing: .02em;
}
.act-btn:last-child { margin-bottom: 0; }
.btn-navy { background:var(--navy); color:#fff; box-shadow:0 4px 14px rgba(11,31,58,.2); }
.btn-navy:hover { background:var(--navy-2); transform:translateY(-1px); }
.btn-outline { background:transparent; color:var(--navy); border:1.5px solid var(--border); }
.btn-outline:hover { border-color:var(--navy); background:var(--cream); }

/* ─── RESPONSIVE ──────────────────────────────── */
@media (max-width: 1060px) {
    .page-wrap { grid-template-columns: 200px 1fr; }
    .right-col { display: none; }   /* hidden on medium; move actions into sidebar */
}
@media (max-width: 780px) {
    .page-wrap { grid-template-columns: 1fr; padding: 18px 16px 60px; }
    .left-sidebar { position: static; display: none; }
    .left-sidebar.open { display: flex; }
    .mobile-sb-btn { display: flex !important; }
    .bp-fields-grid { grid-template-columns: repeat(2,1fr); gap:14px; }
    .bp-body { grid-template-columns: 1fr; gap: 22px; }
    .bp-head  { padding: 20px 20px 24px; }
    .bp-body  { padding: 20px 20px 24px; }
    .bp-payment { padding: 14px 20px; gap: 18px; }
    .bp-iata { font-size: 2.6rem; }
    .price-amount { font-size: 2rem; }
    .right-col { display: flex; }   /* show below on mobile */
}

/* ─── PRINT ───────────────────────────────────── */
@media print {
    .confirm-banner, .back-bar, .left-sidebar,
    .right-col, .bp-tear, header, footer { display: none !important; }
    *, *::before, *::after { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    body { background: #fff !important; padding-top: 0 !important; }
    .page-wrap { display: block !important; padding: 0 !important; }
    .bp { box-shadow: none !important; border-radius: 0 !important; }
}
</style>
</head>
<body>

<!-- ── CONFIRMATION BANNER (just below fixed header) ── -->
<div class="confirm-banner">
    <div class="banner-check"><i class="fas fa-check"></i></div>
    <div class="banner-text">
        <h2>Booking Confirmed!</h2>
        <p>Your ticket has been issued. Have a wonderful journey.</p>
    </div>
    <div class="banner-ref">Ref #<?= $ref ?></div>
</div>

<!-- ── BACK LINK ── -->
<div class="back-bar">
    <a href="myBookings.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to My Bookings
    </a>
</div>

<!-- Mobile sidebar toggle (visible only on small screens) -->
<div style="max-width:1280px;margin:0 auto;padding:10px 32px 0;display:none" id="mobileSbBar">
    <button class="mobile-sb-btn" onclick="document.querySelector('.left-sidebar').classList.toggle('open')"
        style="display:flex;align-items:center;gap:8px;background:var(--navy);color:#fff;border:none;padding:9px 20px;border-radius:10px;font-family:var(--sans);font-size:.82rem;font-weight:700;cursor:pointer">
        <i class="fas fa-bars"></i> My Account
    </button>
</div>

<!-- ── THREE-COLUMN GRID ── -->
<div class="page-wrap">

    <!-- ══════════ LEFT SIDEBAR ══════════ -->
    <aside class="left-sidebar">

        <!-- Profile card -->
        <div class="sidebar-profile">
            <div class="sp-avatar-wrap">
                <img class="sp-avatar" src="<?= $avatar_src ?>" alt="<?= htmlspecialchars($booking['passenger_name']) ?>">
                <div class="sp-online"></div>
            </div>
            <div class="sp-name"><?= htmlspecialchars(explode(' ', trim($booking['passenger_name']))[0]) ?></div>
            <div class="sp-email"><?= htmlspecialchars($booking['passenger_email']) ?></div>
            <div class="sp-badge">✈ GoZayan Traveller</div>
        </div>

        <!-- Stats -->
        <div class="sidebar-stats">
            <div class="sstat">
                <span class="ss-num"><?= (int)$stats['total'] ?></span>
                <span class="ss-lbl">Bookings</span>
            </div>
            <div class="sstat green">
                <span class="ss-num"><?= (int)$stats['confirmed'] ?></span>
                <span class="ss-lbl">Confirmed</span>
            </div>
            <div class="sstat gold" style="grid-column:1/-1">
                <span class="ss-num">$<?= number_format((float)$stats['spent'], 0) ?></span>
                <span class="ss-lbl">Total Spent</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <a href="userhome.php" class="snav-item">
                <i class="fas fa-house"></i> Dashboard
            </a>
            <a href="searchflights.php" class="snav-item">
                <i class="fas fa-magnifying-glass"></i> Search Flights
            </a>
            <a href="myBookings.php" class="snav-item">
                <i class="fas fa-ticket"></i> My Bookings
            </a>
            <a href="booking_confirm.php?id=<?= $booking_id ?>" class="snav-item active">
                <i class="fas fa-circle-check"></i> This Booking
            </a>
            <a href="passengerProfile.php" class="snav-item">
                <i class="fas fa-user"></i> My Profile
            </a>
            <a href="changePassword.php" class="snav-item">
                <i class="fas fa-lock"></i> Change Password
            </a>
        </nav>

        <!-- Logout -->
        <a href="/flight_booking/logout.php" class="sidebar-logout">
            <i class="fas fa-right-from-bracket"></i> Log Out
        </a>

    </aside>

    <!-- ══════════ BOARDING PASS ══════════ -->
    <div class="bp">
        <div class="bp-stripe"></div>

        <!-- Head -->
        <div class="bp-head">
            <div class="bp-head-top">
                <div>
                    <div class="bp-airline">✈ <?= htmlspecialchars($booking['airline_name']) ?></div>
                    <div class="bp-flight-sub">
                        <?= htmlspecialchars($booking['flight_code']) ?>
                        &nbsp;·&nbsp; <?= ucfirst($booking['trip_type']) ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($booking['class']) ?>
                    </div>
                </div>
                <div class="bp-ticket-ref"><?= htmlspecialchars($booking['flight_name']) ?></div>
            </div>

            <!-- Route -->
            <div class="bp-route">
                <div class="bp-city">
                    <div class="bp-iata"><?= strtoupper(substr($booking['from_location'],0,3)) ?></div>
                    <div class="bp-city-name"><?= htmlspecialchars($booking['from_location']) ?></div>
                    <?php if ($dep_t): ?><div class="bp-time-block"><?= htmlspecialchars($dep_t) ?></div><?php endif; ?>
                    <?php if ($dep_day): ?><div class="bp-day-block"><?= htmlspecialchars($dep_day) ?></div><?php endif; ?>
                </div>
                <div class="bp-route-mid">
                    <div class="bp-dur-pill"><?= htmlspecialchars($booking['duration']) ?></div>
                    <div class="bp-line"><span class="bp-plane-icon">✈</span></div>
                </div>
                <div class="bp-city" style="text-align:right">
                    <div class="bp-iata"><?= strtoupper(substr($booking['to_location'],0,3)) ?></div>
                    <div class="bp-city-name"><?= htmlspecialchars($booking['to_location']) ?></div>
                    <?php if ($arr_t): ?><div class="bp-time-block"><?= htmlspecialchars($arr_t) ?></div><?php endif; ?>
                    <?php if ($arr_day): ?><div class="bp-day-block"><?= htmlspecialchars($arr_day) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tear line -->
        <div class="bp-tear">
            <div class="bp-tear-dash"></div>
            <span class="bp-tear-scissors">✂</span>
            <div class="bp-tear-dash"></div>
        </div>

        <!-- Details -->
        <div class="bp-body">
            <div>
                <div class="bp-fields-grid">
                    <div class="bp-field">
                        <div class="f-lbl">Passenger</div>
                        <div class="f-val"><?= htmlspecialchars($booking['passenger_name']) ?></div>
                    </div>
                    <div class="bp-field">
                        <div class="f-lbl">Depart Date</div>
                        <div class="f-val"><?= date('d M Y', strtotime($booking['depart_date'])) ?></div>
                    </div>
                    <div class="bp-field">
                        <div class="f-lbl">Adults</div>
                        <div class="f-val"><?= $booking['adults'] ?></div>
                    </div>
                    <div class="bp-field">
                        <div class="f-lbl">Children</div>
                        <div class="f-val"><?= $booking['children'] ?: '—' ?></div>
                    </div>
                    <?php if ($dep_t): ?>
                    <div class="bp-field">
                        <div class="f-lbl">Dep. Time</div>
                        <div class="f-val"><?= htmlspecialchars($dep_t) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($arr_t): ?>
                    <div class="bp-field">
                        <div class="f-lbl">Arr. Time</div>
                        <div class="f-val"><?= htmlspecialchars($arr_t) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="bp-field">
                        <div class="f-lbl">Class</div>
                        <div class="f-val"><?= htmlspecialchars($booking['class']) ?></div>
                    </div>
                    <div class="bp-field">
                        <div class="f-lbl">Booked On</div>
                        <div class="f-val"><?= date('d M Y', strtotime($booking['booking_date'])) ?></div>
                    </div>
                </div>

                <div class="bp-rule"></div>

                <!-- Price + status -->
                <div class="bp-price-row">
                    <div>
                        <div class="price-label">Total Paid</div>
                        <div class="price-amount">$<?= number_format($booking['total_price'], 0) ?></div>
                        <div class="price-per">
                            $<?= number_format($booking['total_price'] / max(1, $booking['adults'] + $booking['children']), 0) ?>
                            per person
                        </div>
                    </div>
                    <span class="status-badge s-<?= $booking['status'] === 'confirmed' ? 'confirmed' : 'cancelled' ?>">
                        <?= $booking['status'] === 'confirmed' ? '✔ Confirmed' : '✖ ' . ucfirst($booking['status']) ?>
                    </span>
                </div>
            </div>

            <!-- QR Code -->
            <div class="bp-qr">
                <div class="qr-frame">
                    <img src="<?= $qr_url ?>" alt="QR Code">
                </div>
                <div class="qr-label">Scan E-Ticket</div>
                <div class="qr-ref">#<?= $ref ?></div>
            </div>
        </div>

        <!-- Payment strip -->
        <div class="bp-payment">
            <div class="pay-item">
                <div class="py-lbl">Payment Method</div>
                <div class="py-val"><?= ucfirst($booking['payment_method'] ?? '—') ?></div>
            </div>
            <div class="pay-item">
                <div class="py-lbl">Card</div>
                <div class="py-val">•••• <?= htmlspecialchars($booking['card_last4'] ?? '——') ?></div>
            </div>
            <div class="pay-item">
                <div class="py-lbl">Card Holder</div>
                <div class="py-val"><?= htmlspecialchars($booking['card_holder'] ?? '—') ?></div>
            </div>
            <div class="pay-item" style="margin-left:auto">
                <div class="py-lbl">Amount Charged</div>
                <div class="py-val" style="color:var(--blue);font-size:.95rem;font-family:var(--mono)">$<?= number_format($booking['total_price'], 0) ?></div>
            </div>
        </div>
    </div><!-- /bp -->

    <!-- ══════════ RIGHT COLUMN ══════════ -->
    <div class="right-col">

        <!-- Journey summary -->
        <div class="panel">
            <div class="journey-card">
                <div class="jc-route">
                    <?= htmlspecialchars($booking['from_location']) ?>
                    <span class="jc-arrow">→</span>
                    <?= htmlspecialchars($booking['to_location']) ?>
                </div>
                <div class="jc-chips">
                    <span class="jc-chip hi"><?= date('d M Y', strtotime($booking['depart_date'])) ?></span>
                    <span class="jc-chip"><?= htmlspecialchars($booking['class']) ?></span>
                    <span class="jc-chip"><?= ucfirst($booking['trip_type']) ?></span>
                    <span class="jc-chip"><?= $booking['adults'] ?> Adult<?= $booking['adults']>1?'s':'' ?></span>
                    <?php if ($booking['children'] > 0): ?>
                    <span class="jc-chip"><?= $booking['children'] ?> Child<?= $booking['children']>1?'ren':'' ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Booking details -->
        <div class="panel">
            <div class="panel-head"><span class="ph-dot"></span> Booking Info</div>
            <div class="panel-body">
                <div class="info-row">
                    <span class="k">Reference</span>
                    <span class="v" style="font-family:var(--mono);color:var(--blue)">#<?= $ref ?></span>
                </div>
                <div class="info-row">
                    <span class="k">Booked On</span>
                    <span class="v"><?= date('d M Y', strtotime($booking['booking_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="k">Status</span>
                    <span class="v" style="color:var(--<?= $booking['status']==='confirmed'?'green':'red' ?>)">
                        <?= $booking['status'] === 'confirmed' ? '✔ Confirmed' : '✖ ' . ucfirst($booking['status']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="k">Flight</span>
                    <span class="v"><?= htmlspecialchars($booking['flight_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="k">Flight Code</span>
                    <span class="v" style="font-family:var(--mono)"><?= htmlspecialchars($booking['flight_code']) ?></span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="panel">
            <div class="panel-head"><span class="ph-dot"></span> Actions</div>
            <div class="panel-body">
                <button onclick="window.print()" class="act-btn btn-navy">
                    <i class="fas fa-print"></i> Print Ticket
                </button>
                <a href="myBookings.php" class="act-btn btn-outline">
                    <i class="fas fa-list"></i> My Bookings
                </a>
                <a href="searchflights.php" class="act-btn btn-outline">
                    <i class="fas fa-magnifying-glass"></i> Search More Flights
                </a>
            </div>
        </div>

    </div><!-- /right-col -->

</div><!-- /page-wrap -->

<?php include("../includes/footer.php"); ?>

<script>
// Field entry animation
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.bp-field').forEach((f, i) => {
        f.style.opacity = '0';
        f.style.transform = 'translateY(8px)';
        f.style.transition = `opacity .4s ${.3+i*.05}s ease, transform .4s ${.3+i*.05}s ease`;
        requestAnimationFrame(() => { f.style.opacity='1'; f.style.transform='none'; });
    });
    // Show mobile sidebar button on small screens
    if (window.innerWidth <= 780) {
        document.getElementById('mobileSbBar').style.display = 'block';
    }
});
</script>
</body>
</html>