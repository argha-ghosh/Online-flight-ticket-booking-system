<?php
session_start();
require_once __DIR__ . "/../config/base_url.php";
include("../model/db_conn.php");

$is_logged_in = isset($_SESSION['email']) && isset($_SESSION['role']) && $_SESSION['role'] === 'webuser';
$user = null;
$booking_count = 0;
$confirmed = 0; $cancelled = 0; $spent = 0;

if ($is_logged_in) {
    $email = $_SESSION['email'];
    $stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $stats_stmt = $conn->prepare("
            SELECT COUNT(*) as total,
                   SUM(status='confirmed') as confirmed,
                   SUM(status='cancelled') as cancelled,
                   SUM(CASE WHEN status='confirmed' THEN total_price ELSE 0 END) as spent
            FROM bookings WHERE user_id = ?
        ");
        $stats_stmt->bind_param("i", $user['id']);
        $stats_stmt->execute();
        $stats = $stats_stmt->get_result()->fetch_assoc();
        $stats_stmt->close();
        $booking_count = (int)$stats['total'];
        $confirmed     = (int)$stats['confirmed'];
        $cancelled     = (int)$stats['cancelled'];
        $spent         = (float)$stats['spent'];
    }
}

$avatar_src = '';
if ($user) {
    $avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=0b1f3a&color=d4a84b&size=80&bold=true";
    if (!empty($user['image']) && file_exists(__DIR__ . "/uploads/" . $user['image'])) {
        $avatar_src = "uploads/" . htmlspecialchars($user['image']);
    }
}

include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GoZayan · My Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,900;1,600;1,700&family=DM+Mono:wght@400;500;600&family=Mulish:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
/* ══════════════════════════════════════════════════
   SHARED DESIGN SYSTEM — GoZayan User Portal
══════════════════════════════════════════════════ */
:root {
    /* Core palette */
    --navy:        #08172e;
    --navy-2:      #0f2444;
    --navy-3:      #172f56;
    --navy-4:      #1e3d6e;
    --gold:        #c9a84c;
    --gold-lt:     #e0bc6a;
    --gold-dk:     #a8893a;
    --gold-tint:   rgba(201,168,76,.09);
    --gold-glow:   rgba(201,168,76,.22);
    --cream:       #f8f5f0;
    --cream-2:     #f0ebe2;
    --cream-3:     #e6dfd4;
    --ink:         #0d1a28;
    --ink-2:       #2e4057;
    --ink-3:       #6b84a0;
    --ink-4:       #9db3c8;
    --surface:     #ffffff;
    --surface-2:   #fdfcfa;
    --green:       #0a8f6a;
    --green-lt:    #12b585;
    --green-bg:    #d0f5ea;
    --red:         #c8293a;
    --red-lt:      #e53e50;
    --red-bg:      #fde8ea;
    --amber:       #b05c10;
    --amber-bg:    rgba(176,92,16,.1);
    --border:      #e2d9cc;
    --border-2:    #ede7de;
    /* Shadows */
    --sh-xs:  0 1px 3px rgba(8,23,46,.05);
    --sh-sm:  0 2px 10px rgba(8,23,46,.07), 0 1px 3px rgba(8,23,46,.04);
    --sh-md:  0 6px 24px rgba(8,23,46,.09), 0 2px 8px rgba(8,23,46,.05);
    --sh-lg:  0 16px 48px rgba(8,23,46,.12), 0 4px 16px rgba(8,23,46,.06);
    --sh-gold: 0 6px 24px rgba(201,168,76,.25);
    /* Typography */
    --serif: 'Playfair Display', Georgia, serif;
    --sans:  'Mulish', system-ui, sans-serif;
    --mono:  'DM Mono', 'Courier New', monospace;
    /* Radii */
    --r-sm: 8px;
    --r-md: 14px;
    --r-lg: 20px;
    --r-xl: 28px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
    font-family: var(--sans);
    background: var(--cream);
    color: var(--ink);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
    padding-top: 62px; /* fixed header height */
}

/* ── Scrollbar ── */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: var(--cream-2); }
::-webkit-scrollbar-thumb { background: var(--cream-3); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--ink-4); }

/* ── Animations ── */
@keyframes riseIn   { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeIn   { from { opacity:0; } to { opacity:1; } }
@keyframes slideInL { from { opacity:0; transform:translateX(-16px); } to { opacity:1; transform:translateX(0); } }

/* ══════════════════════════════════════════════════
   SUB-HEADER BANNER
══════════════════════════════════════════════════ */
.sub-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-3) 100%);
    padding: 18px 40px;
    display: flex; align-items: center; gap: 20px;
    border-bottom: 1px solid rgba(255,255,255,.05);
    position: relative; overflow: hidden;
}
.sub-header::before {
    content: '';
    position: absolute; inset: 0;
    background-image: radial-gradient(circle at 80% 50%, rgba(201,168,76,.08) 0%, transparent 60%);
    pointer-events: none;
}
.sh-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: rgba(201,168,76,.15);
    border: 1px solid rgba(201,168,76,.25);
    display: flex; align-items: center; justify-content: center;
    color: var(--gold-lt); font-size: 1.15rem; flex-shrink: 0;
}
.sh-text h2 {
    font-family: var(--serif); font-size: 1.15rem; font-weight: 700;
    color: #fff; letter-spacing: -.01em; margin-bottom: 2px;
}
.sh-text p { font-size: .75rem; color: rgba(255,255,255,.42); font-weight: 500; }
.sh-badge {
    margin-left: auto;
    font-family: var(--mono); font-size: .68rem; font-weight: 500;
    color: var(--gold-lt); letter-spacing: .1em;
    background: rgba(201,168,76,.12);
    border: 1px solid rgba(201,168,76,.25);
    padding: 6px 18px; border-radius: 30px;
    white-space: nowrap;
}

/* ══════════════════════════════════════════════════
   PAGE LAYOUT
══════════════════════════════════════════════════ */
.page-wrap {
    max-width: 1340px; margin: 0 auto;
    padding: 28px 36px 100px;
    display: grid;
    grid-template-columns: 256px 1fr;
    gap: 28px;
    align-items: start;
}

/* ══════════════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════════════ */
.sidebar {
    display: flex; flex-direction: column; gap: 12px;
    position: sticky; top: 82px;
    animation: slideInL .5s .05s cubic-bezier(0.16,1,.3,1) both;
}

/* Profile card */
.sb-profile {
    background: linear-gradient(160deg, var(--navy-2) 0%, var(--navy-4) 100%);
    border-radius: var(--r-lg);
    padding: 24px 20px 20px;
    text-align: center;
    border: 1px solid rgba(255,255,255,.06);
    box-shadow: var(--sh-md);
    position: relative; overflow: hidden;
}
.sb-profile::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 50% 0%, rgba(201,168,76,.12) 0%, transparent 65%);
    pointer-events: none;
}
.sb-av-wrap {
    position: relative; display: inline-block; margin-bottom: 14px;
}
.sb-av {
    width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
    border: 3px solid rgba(201,168,76,.5);
    box-shadow: 0 0 0 5px rgba(201,168,76,.1), var(--sh-md);
}
.sb-online {
    position: absolute; bottom: 3px; right: 3px;
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--green-lt); border: 2.5px solid var(--navy-2);
    box-shadow: 0 0 6px rgba(18,181,133,.5);
}
.sb-name  { font-size: .95rem; font-weight: 800; color: #fff; margin-bottom: 3px; letter-spacing: -.01em; }
.sb-email { font-size: .68rem; color: rgba(255,255,255,.38); word-break: break-all; line-height: 1.4; }
.sb-badge {
    display: inline-flex; align-items: center; gap: 5px;
    margin-top: 12px;
    font-family: var(--mono); font-size: .6rem; font-weight: 500;
    letter-spacing: .1em; text-transform: uppercase;
    color: var(--gold-lt);
    background: rgba(201,168,76,.12);
    border: 1px solid rgba(201,168,76,.22);
    padding: 4px 14px; border-radius: 20px;
}

/* Stat tiles */
.sb-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.sb-stat {
    background: var(--surface);
    border: 1px solid var(--border-2);
    border-radius: var(--r-md);
    padding: 13px 10px; text-align: center;
    box-shadow: var(--sh-xs);
    transition: transform .2s, box-shadow .2s;
}
.sb-stat:hover { transform: translateY(-2px); box-shadow: var(--sh-sm); }
.sb-stat .n {
    font-family: var(--mono); font-size: 1.2rem; font-weight: 600;
    display: block; font-variant-numeric: tabular-nums; line-height: 1;
    color: var(--navy);
}
.sb-stat .l {
    font-size: .6rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: var(--ink-3); display: block; margin-top: 4px;
}
.sb-stat.c-gold  .n { color: var(--gold-dk); }
.sb-stat.c-green .n { color: var(--green); }
.sb-stat.c-red   .n { color: var(--red); }

/* Nav */
.sb-nav{background:var(--cream-2);border:1px solid var(--border-2);border-radius:var(--r-sm);overflow:hidden;box-shadow:var(--sh-sm);display:flex;flex-direction:column}
.sb-nav-item{display:flex;align-items:center;gap:12px;padding:12px 16px;font-size:.85rem;font-weight:600;color:var(--ink-2);text-decoration:none;border-bottom:1px solid var(--border-2);transition:background .18s,color .18s,border-color .18s;position:relative;letter-spacing:.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:100%;background:var(--cream-2)}
.sb-nav-item:last-child{border-bottom:none}
.sb-nav-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--gold);transform:scaleX(0);transform-origin:left;transition:transform .18s;border-radius:0 2px 2px 0}
.sb-nav-item:hover{background:var(--cream-2);color:var(--navy)}
.sb-nav-item:hover::before,.sb-nav-item.active::before{transform:scaleX(1)}
.sb-nav-item.active{background:rgba(201,168,76,.1);color:var(--navy);font-weight:800}
.sb-nav-item i{width:18px;text-align:center;font-size:.84rem;color:var(--ink-3);flex-shrink:0;transition:color .18s}
.sb-nav-item:hover i,.sb-nav-item.active i{color:var(--gold)}
/* Search Flights — gold accent row */
.sb-nav-item.search-link{background:rgba(201,168,76,.07);color:var(--gold-dk);font-weight:700;border-left:3px solid rgba(201,168,76,.5)}
.sb-nav-item.search-link i{color:var(--gold-dk)}
.sb-nav-item.search-link:hover{background:rgba(201,168,76,.15);color:var(--navy);border-left-color:var(--gold)}

/* Logout */
.sb-logout {
    display: flex; align-items: center; justify-content: center; gap: 9px;
    padding: 12px; background: transparent;
    border: 1.5px solid var(--border);
    border-radius: var(--r-md); color: var(--ink-3);
    font-family: var(--sans); font-size: .82rem; font-weight: 700;
    text-decoration: none; transition: all .2s; letter-spacing: .02em;
}
.sb-logout:hover { border-color: var(--red); color: var(--red); background: var(--red-bg); }
.sb-logout i { font-size: .82rem; }
</style>
<style>
/* ══════════════════════════════════════════════════
   MAIN COLUMN — USERHOME
══════════════════════════════════════════════════ */
.main-col { min-width: 0; animation: riseIn .5s .1s cubic-bezier(0.16,1,.3,1) both; }

/* Greeting bar */
.greeting-bar {
    background: var(--surface);
    border: 1px solid var(--border-2);
    border-radius: var(--r-lg);
    padding: 20px 26px;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    margin-bottom: 22px;
    box-shadow: var(--sh-sm);
    position: relative; overflow: hidden;
}
.greeting-bar::after {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--navy) 0%, var(--gold) 100%);
}
.greeting-text { font-family: var(--serif); font-size: 1.4rem; font-weight: 700; color: var(--ink); letter-spacing: -.02em; }
.greeting-text em { font-style: italic; color: var(--gold-dk); }
.search-pill {
    display: flex; align-items: center; gap: 10px;
    background: var(--cream); border: 1.5px solid var(--border);
    border-radius: 50px; padding: 10px 22px;
    text-decoration: none; color: var(--ink-3);
    font-size: .86rem; font-weight: 600; transition: all .22s;
    white-space: nowrap;
}
.search-pill i { color: var(--gold); }
.search-pill:hover { border-color: var(--gold); color: var(--gold-dk); background: var(--gold-tint); box-shadow: var(--sh-gold); }

/* Stat strip */
.stat-strip { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 26px; }
.stat-tile {
    background: var(--surface);
    border-radius: var(--r-lg);
    padding: 22px 24px;
    border: 1px solid var(--border-2);
    display: flex; align-items: center; gap: 18px;
    box-shadow: var(--sh-sm);
    transition: transform .22s, box-shadow .22s;
    position: relative; overflow: hidden;
}
.stat-tile::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
    opacity: 0; transition: opacity .22s;
}
.stat-tile:hover { transform: translateY(-3px); box-shadow: var(--sh-md); }
.stat-tile:hover::after { opacity: 1; }
.stat-tile.t-navy::after  { background: var(--navy); }
.stat-tile.t-green::after { background: var(--green); }
.stat-tile.t-gold::after  { background: var(--gold); }
.st-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem; flex-shrink: 0;
}
.st-icon.i-navy  { background: rgba(8,23,46,.07);  color: var(--navy-3); }
.st-icon.i-green { background: rgba(10,143,106,.08); color: var(--green); }
.st-icon.i-gold  { background: var(--gold-tint);    color: var(--gold-dk); }
.st-info .v { font-family: var(--mono); font-size: 1.8rem; font-weight: 600; color: var(--ink); letter-spacing: -.04em; line-height: 1; font-variant-numeric: tabular-nums; }
.st-info .l { font-size: .7rem; color: var(--ink-3); font-weight: 700; margin-top: 5px; text-transform: uppercase; letter-spacing: .06em; }

/* Body grid */
.body-grid { display: grid; grid-template-columns: 1fr 308px; gap: 22px; align-items: start; }

/* Panel */
.panel {
    background: var(--surface);
    border-radius: var(--r-lg);
    border: 1px solid var(--border-2);
    overflow: hidden;
    box-shadow: var(--sh-sm);
}
.panel-head {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border-2);
    display: flex; align-items: center; justify-content: space-between;
    background: var(--surface-2);
}
.panel-head h3 {
    font-family: var(--serif); font-size: 1.05rem; font-weight: 700;
    color: var(--ink); display: flex; align-items: center; gap: 8px;
}
.ph-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); flex-shrink: 0; }
.panel-head a {
    font-family: var(--mono); font-size: .7rem; font-weight: 500;
    color: var(--gold-dk); text-decoration: none; letter-spacing: .05em;
    transition: color .18s;
}
.panel-head a:hover { color: var(--navy); }

/* Booking ticket rows */
.bk-ticket {
    display: flex; align-items: center; gap: 16px;
    padding: 15px 22px;
    border-bottom: 1px solid var(--cream-2);
    transition: background .15s;
}
.bk-ticket:last-child { border-bottom: none; }
.bk-ticket:hover { background: var(--cream); }
.bk-icon {
    width: 42px; height: 42px; border-radius: 12px;
    background: linear-gradient(135deg, var(--navy-2) 0%, var(--navy-4) 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; color: var(--gold-lt); flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(8,23,46,.18);
}
.bk-info { flex: 1; min-width: 0; }
.bk-route { font-size: .9rem; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bk-meta  { font-size: .73rem; color: var(--ink-3); margin-top: 3px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.bk-right { text-align: right; flex-shrink: 0; }
.bk-price { font-family: var(--mono); font-size: .95rem; font-weight: 600; color: var(--navy); }
.bk-date  { font-size: .7rem; color: var(--ink-3); margin-top: 3px; }

/* Status badges */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 20px; font-size: .67rem; font-weight: 700; }
.badge-confirmed { background: var(--green-bg); color: var(--green); border: 1px solid rgba(10,143,106,.2); }
.badge-cancelled { background: var(--red-bg);   color: var(--red);   border: 1px solid rgba(200,41,58,.18); }
.badge-pending   { background: var(--amber-bg); color: var(--amber); border: 1px solid rgba(176,92,16,.2); }

.no-data { text-align: center; padding: 44px 20px; color: var(--ink-3); font-size: .88rem; }
.no-data i { font-size: 2.2rem; color: var(--border); display: block; margin-bottom: 12px; }
.no-data a { color: var(--gold-dk); font-weight: 700; text-decoration: none; }

/* Search CTA */
.search-cta {
    background: linear-gradient(145deg, var(--navy) 0%, var(--navy-3) 100%);
    border-radius: var(--r-lg);
    padding: 28px 22px; color: #fff; text-align: center;
    margin-bottom: 16px; position: relative; overflow: hidden;
    box-shadow: var(--sh-md);
}
.search-cta::before {
    content: '✈';
    position: absolute; right: -8px; top: -12px;
    font-size: 7rem; opacity: .04; line-height: 1;
    transform: rotate(12deg);
}
.search-cta::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--gold-dk), var(--gold-lt));
}
.search-cta h4 { font-family: var(--serif); font-size: 1.15rem; font-weight: 700; margin-bottom: 8px; letter-spacing: -.01em; }
.search-cta p  { font-size: .8rem; opacity: .6; margin-bottom: 20px; line-height: 1.55; }
.search-cta a {
    display: inline-block; background: var(--gold); color: var(--navy);
    padding: 11px 28px; border-radius: 50px; font-weight: 800;
    font-size: .88rem; text-decoration: none;
    box-shadow: var(--sh-gold); transition: all .22s; font-family: var(--sans);
    letter-spacing: .01em;
}
.search-cta a:hover { transform: translateY(-2px); background: var(--gold-lt); box-shadow: 0 8px 28px rgba(201,168,76,.35); }

/* Quick links */
.quick-links { display: flex; flex-direction: column; gap: 6px; padding: 14px; }
.quick-link {
    display: flex; align-items: center; gap: 13px;
    padding: 12px 15px; border-radius: var(--r-md);
    text-decoration: none; color: var(--ink);
    border: 1px solid var(--border-2); background: var(--surface);
    transition: all .2s; font-size: .86rem; font-weight: 600;
}
.quick-link:hover { border-color: var(--gold); background: var(--gold-tint); transform: translateX(4px); box-shadow: var(--sh-xs); }
.ql-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: .95rem; flex-shrink: 0; }
.ql-arrow { margin-left: auto; color: var(--ink-4); font-size: .75rem; transition: color .2s, transform .2s; }
.quick-link:hover .ql-arrow { color: var(--gold-dk); transform: translateX(3px); }

/* ── MOBILE BOTTOM NAV ── */
.mobile-nav{display:none;position:fixed;bottom:0;left:0;right:0;z-index:999;background:var(--navy);border-top:2px solid rgba(201,168,76,.3);padding:8px 0 env(safe-area-inset-bottom,8px);box-shadow:0 -4px 20px rgba(8,23,46,.3)}
.mobile-nav-inner{display:flex;justify-content:space-around;align-items:center;max-width:500px;margin:0 auto}
.mob-link{display:flex;flex-direction:column;align-items:center;gap:3px;text-decoration:none;color:rgba(255,255,255,.55);font-size:.62rem;font-weight:600;padding:4px 8px;border-radius:8px;transition:color .18s}
.mob-link i{font-size:1.1rem}
.mob-link.active,.mob-link:hover{color:var(--gold-lt)}

@media(max-width:1100px){
    .page-wrap{grid-template-columns:1fr;padding:20px 20px 100px}
    .sidebar{position:static;display:none}
    .body-grid{grid-template-columns:1fr}
}
@media(max-width:780px){
    .sub-header{padding:14px 16px;gap:14px}
    .sub-header .sh-badge{display:none}
    .sh-icon{width:38px;height:38px;font-size:1rem}
    .sh-text h2{font-size:1rem}
    .page-wrap{padding:16px 14px 100px;gap:18px}
    .mobile-nav{display:block}
    .sidebar{display:none}
    .stat-strip{grid-template-columns:1fr}
    .body-grid{grid-template-columns:1fr}
    .greeting-bar{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>

<?php if ($is_logged_in && $user): ?>

<div class="sub-header">
    <div class="sh-icon"><i class="fas fa-house"></i></div>
    <div class="sh-text">
        <h2>My Dashboard</h2>
        <p>Welcome back — here's your travel overview</p>
    </div>
    <div class="sh-badge">✈ GoZayan Traveller</div>
</div>

<div class="page-wrap">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
        <div class="sb-profile">
            <div class="sb-av-wrap">
                <img class="sb-av" src="<?= $avatar_src ?>" alt="<?= htmlspecialchars($user['name']) ?>">
                <div class="sb-online"></div>
            </div>
            <div class="sb-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="sb-email"><?= htmlspecialchars($user['email']) ?></div>
            <div class="sb-badge"><i class="fas fa-star" style="font-size:.55rem"></i> GoZayan Traveller</div>
        </div>

        <div class="sb-stats">
            <div class="sb-stat">
                <span class="n"><?= $booking_count ?></span>
                <span class="l">Bookings</span>
            </div>
            <div class="sb-stat c-green">
                <span class="n"><?= $confirmed ?></span>
                <span class="l">Confirmed</span>
            </div>
            <div class="sb-stat c-gold" style="grid-column:1/-1">
                <span class="n">$<?= number_format($spent, 0) ?></span>
                <span class="l">Total Spent</span>
            </div>
        </div>

        <nav class="sb-nav">
            <a href="userhome.php" class="sb-nav-item active"><i class="fas fa-house"></i> Dashboard</a>
        </nav>

        <a href="<?= BASE_URL ?>/logout.php" class="sb-logout">
            <i class="fas fa-right-from-bracket"></i> Sign Out
        </a>
    </aside>

    <!-- ══ MAIN ══ -->
    <div class="main-col">

        <div class="greeting-bar">
            <div class="greeting-text">Good day, <em><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></em> ✈</div>
            <a href="searchflights.php" class="search-pill">
                <i class="fas fa-magnifying-glass"></i> Search for a flight...
            </a>
        </div>

        <div class="stat-strip">
            <div class="stat-tile t-navy">
                <div class="st-icon i-navy"><i class="fas fa-ticket"></i></div>
                <div class="st-info">
                    <div class="v"><?= $booking_count ?></div>
                    <div class="l">Total Bookings</div>
                </div>
            </div>
            <div class="stat-tile t-green">
                <div class="st-icon i-green"><i class="fas fa-circle-check"></i></div>
                <div class="st-info">
                    <div class="v"><?= $confirmed ?></div>
                    <div class="l">Confirmed</div>
                </div>
            </div>
            <div class="stat-tile t-gold">
                <div class="st-icon i-gold"><i class="fas fa-coins"></i></div>
                <div class="st-info">
                    <div class="v">$<?= number_format($spent, 0) ?></div>
                    <div class="l">Total Spent</div>
                </div>
            </div>
        </div>

        <div class="body-grid">
            <div class="panel">
                <div class="panel-head">
                    <h3><span class="ph-dot"></span> Recent Bookings</h3>
                    <a href="myBookings.php">View all →</a>
                </div>
                <?php if ($booking_count > 0):
                    $rs = $conn->prepare("SELECT b.*, f.flight_name, f.departure, f.arrival, f.flight_code FROM bookings b JOIN flights f ON b.flight_id = f.id WHERE b.user_id = ? ORDER BY b.booking_date DESC LIMIT 6");
                    $rs->bind_param("i", $user['id']); $rs->execute();
                    $rb = $rs->get_result();
                    while ($b = $rb->fetch_assoc()):
                ?>
                <div class="bk-ticket">
                    <div class="bk-icon"><i class="fas fa-plane"></i></div>
                    <div class="bk-info">
                        <div class="bk-route"><?= htmlspecialchars($b['departure']) ?> → <?= htmlspecialchars($b['arrival']) ?></div>
                        <div class="bk-meta">
                            <?= htmlspecialchars($b['flight_name']) ?> · <?= htmlspecialchars($b['flight_code']) ?>
                            <span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= ucfirst($b['status']) ?></span>
                        </div>
                    </div>
                    <div class="bk-right">
                        <div class="bk-price">$<?= number_format($b['total_price'], 0) ?></div>
                        <div class="bk-date"><?= date('d M Y', strtotime($b['booking_date'])) ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-ticket"></i>
                    No bookings yet.<br>
                    <a href="searchflights.php">Search flights →</a>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <div class="search-cta">
                    <h4>Ready for your next trip?</h4>
                    <p>Find the best deals on flights across Bangladesh and beyond.</p>
                    <a href="searchflights.php">Search Flights</a>
                </div>
                <div class="panel">
                    <div class="panel-head"><h3><span class="ph-dot"></span> Quick Links</h3></div>
                    <div class="quick-links">
                        <a href="searchflights.php" class="quick-link">
                            <div class="ql-icon i-navy" style="background:rgba(8,23,46,.07);color:var(--navy-3)"><i class="fas fa-magnifying-glass"></i></div>
                            Search Flights <span class="ql-arrow"><i class="fas fa-chevron-right"></i></span>
                        </a>
                        <a href="myBookings.php" class="quick-link">
                            <div class="ql-icon" style="background:rgba(10,143,106,.08);color:var(--green)"><i class="fas fa-ticket"></i></div>
                            My Bookings <span class="ql-arrow"><i class="fas fa-chevron-right"></i></span>
                        </a>
                        <a href="passengerProfile.php" class="quick-link">
                            <div class="ql-icon" style="background:var(--gold-tint);color:var(--gold-dk)"><i class="fas fa-user"></i></div>
                            Edit Profile <span class="ql-arrow"><i class="fas fa-chevron-right"></i></span>
                        </a>
                        <a href="changePassword.php" class="quick-link">
                            <div class="ql-icon" style="background:var(--red-bg);color:var(--red)"><i class="fas fa-lock"></i></div>
                            Change Password <span class="ql-arrow"><i class="fas fa-chevron-right"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<style>
.guest-wrap { min-height: calc(100vh - 62px); display: grid; grid-template-columns: 1fr 1fr; }
.guest-left { background: linear-gradient(155deg, var(--navy) 0%, var(--navy-3) 100%); display: flex; flex-direction: column; justify-content: center; padding: 70px 60px; color: #fff; position: relative; overflow: hidden; }
.guest-left::before { content: ''; position: absolute; inset: 0; background: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?q=80&w=1748&auto=format&fit=crop') center/cover; opacity: .12; }
.guest-left::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--gold-dk), var(--gold-lt)); }
.guest-left-inner { position: relative; z-index: 1; }
.guest-left h1 { font-family: var(--serif); font-size: 3rem; font-weight: 700; line-height: 1.1; margin-bottom: 20px; letter-spacing: -.03em; }
.guest-left h1 em { font-style: italic; color: var(--gold-lt); }
.guest-left p { font-size: 1rem; opacity: .7; line-height: 1.7; max-width: 380px; }
.guest-right { display: flex; align-items: center; justify-content: center; padding: 60px 56px; background: var(--surface); }
.guest-right-inner { max-width: 360px; width: 100%; }
.guest-right h2 { font-family: var(--serif); font-size: 1.9rem; font-weight: 700; color: var(--ink); margin-bottom: 10px; letter-spacing: -.02em; }
.guest-right p { color: var(--ink-3); font-size: .9rem; margin-bottom: 32px; line-height: 1.6; }
.g-btn-solid { display: block; width: 100%; padding: 14px; background: var(--navy); color: #fff; border-radius: var(--r-md); text-decoration: none; font-weight: 700; font-size: .95rem; text-align: center; box-shadow: var(--sh-md); transition: all .22s; margin-bottom: 12px; font-family: var(--sans); }
.g-btn-solid:hover { transform: translateY(-2px); background: var(--navy-2); box-shadow: var(--sh-lg); }
.g-btn-ghost { display: block; width: 100%; padding: 13px; border: 2px solid var(--border); color: var(--ink-2); border-radius: var(--r-md); text-decoration: none; font-weight: 700; font-size: .95rem; text-align: center; transition: all .22s; font-family: var(--sans); }
.g-btn-ghost:hover { border-color: var(--gold); color: var(--gold-dk); background: var(--gold-tint); }
.g-divider { display: flex; align-items: center; gap: 12px; color: var(--ink-3); font-size: .8rem; margin: 14px 0; }
.g-divider::before,.g-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
@media (max-width: 768px) { .guest-wrap { grid-template-columns: 1fr; } .guest-left { display: none; } .guest-right { padding: 40px 24px; } }
</style>
<div class="guest-wrap">
    <div class="guest-left">
        <div class="guest-left-inner">
            <h1>Think Flights,<br><em>Think GoZayan</em></h1>
            <p>Search and book flights across Bangladesh. Fast, simple, and reliable — your journey starts here.</p>
        </div>
    </div>
    <div class="guest-right">
        <div class="guest-right-inner">
            <h2>Get Started</h2>
            <p>Login to your account or create a new one to start booking flights.</p>
            <a href="login.php" class="g-btn-solid">Login to your account</a>
            <div class="g-divider">or</div>
            <a href="register.php" class="g-btn-ghost">Create a free account</a>
            <p style="margin-top:20px;text-align:center;">
                <a href="searchflights.php" style="color:var(--gold-dk);font-weight:600;font-size:.88rem;text-decoration:none;">Browse flights without logging in →</a>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<nav class="mobile-nav"><div class="mobile-nav-inner">
    <a href="userhome.php" class="mob-link active"><i class="fas fa-house"></i>Home</a>
    <a href="searchflights.php" class="mob-link"><i class="fas fa-magnifying-glass"></i>Search</a>
    <a href="myBookings.php" class="mob-link"><i class="fas fa-ticket"></i>Bookings</a>
    <a href="passengerProfile.php" class="mob-link"><i class="fas fa-user"></i>Profile</a>
    <a href="<?= BASE_URL ?>/logout.php" class="mob-link"><i class="fas fa-right-from-bracket"></i>Logout</a>
</div></nav>
<?php include("../includes/footer.php"); ?>
</body>
</html>
