<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}

$email = $_SESSION['email'];
$u_stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
$u_stmt->bind_param("s", $email); $u_stmt->execute();
$user = $u_stmt->get_result()->fetch_assoc(); $u_stmt->close();

if (isset($_POST['cancel_id'])) {
    $cancel_id = (int)$_POST['cancel_id'];
    $c = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $c->bind_param("ii", $cancel_id, $user['id']); $c->execute(); $c->close();
}

$status_filter = $_GET['status'] ?? 'all';
$where = "WHERE b.user_id = {$user['id']}";
if ($status_filter === 'confirmed') $where .= " AND b.status = 'confirmed'";
if ($status_filter === 'cancelled')  $where .= " AND b.status = 'cancelled'";

$bookings_result = $conn->query("
    SELECT b.*, f.flight_name, f.airline_name, f.flight_code,
           f.departure, f.arrival, f.duration, f.image as flight_image,
           f.departure_time, f.arrival_time, f.status as flight_status,
           ROUND(f.price * (1 - f.discount_pct / 100), 2) AS current_unit_price,
           f.discount_pct, s.departure_day, s.arrival_day,
           s.departure_time AS sched_dep_time, s.arrival_time AS sched_arr_time
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    LEFT JOIN schedule s ON s.flight_code COLLATE utf8mb4_unicode_ci = f.flight_code
    $where ORDER BY b.booking_date DESC
");

$sq = $conn->query("SELECT status, COUNT(*) as cnt, SUM(CASE WHEN status='confirmed' THEN total_price ELSE 0 END) as sp FROM bookings WHERE user_id = {$user['id']} GROUP BY status");
$total = 0; $confirmed = 0; $cancelled = 0; $spent = 0;
while ($s = $sq->fetch_assoc()) {
    $total += $s['cnt'];
    if ($s['status'] === 'confirmed') { $confirmed = $s['cnt']; $spent = $s['sp']; }
    if ($s['status'] === 'cancelled') $cancelled = $s['cnt'];
}

$avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=0b1f3a&color=d4a84b&size=80&bold=true";
if (!empty($user['image']) && file_exists(__DIR__ . "/uploads/" . $user['image']))
    $avatar_src = "uploads/" . htmlspecialchars($user['image']);

include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GoZayan · My Bookings</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,900;1,600;1,700&family=DM+Mono:wght@400;500;600&family=Mulish:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --navy:#08172e; --navy-2:#0f2444; --navy-3:#172f56; --navy-4:#1e3d6e;
    --gold:#c9a84c; --gold-lt:#e0bc6a; --gold-dk:#a8893a; --gold-tint:rgba(201,168,76,.09); --gold-glow:rgba(201,168,76,.22);
    --cream:#f8f5f0; --cream-2:#f0ebe2; --cream-3:#e6dfd4;
    --ink:#0d1a28; --ink-2:#2e4057; --ink-3:#6b84a0; --ink-4:#9db3c8;
    --surface:#ffffff; --surface-2:#fdfcfa;
    --green:#0a8f6a; --green-lt:#12b585; --green-bg:#d0f5ea;
    --red:#c8293a; --red-lt:#e53e50; --red-bg:#fde8ea;
    --amber:#b05c10; --amber-bg:rgba(176,92,16,.1);
    --border:#e2d9cc; --border-2:#ede7de;
    --sh-xs:0 1px 3px rgba(8,23,46,.05);
    --sh-sm:0 2px 10px rgba(8,23,46,.07),0 1px 3px rgba(8,23,46,.04);
    --sh-md:0 6px 24px rgba(8,23,46,.09),0 2px 8px rgba(8,23,46,.05);
    --sh-lg:0 16px 48px rgba(8,23,46,.12),0 4px 16px rgba(8,23,46,.06);
    --sh-gold:0 6px 24px rgba(201,168,76,.25);
    --serif:'Playfair Display',Georgia,serif;
    --sans:'Mulish',system-ui,sans-serif;
    --mono:'DM Mono','Courier New',monospace;
    --r-sm:8px; --r-md:14px; --r-lg:20px; --r-xl:28px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--sans);background:var(--cream);color:var(--ink);min-height:100vh;-webkit-font-smoothing:antialiased;padding-top:62px}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--cream-2)}::-webkit-scrollbar-thumb{background:var(--cream-3);border-radius:4px}
@keyframes riseIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideInL{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:translateX(0)}}

/* Sub-header */
.sub-header{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-3) 100%);padding:18px 40px;display:flex;align-items:center;gap:20px;border-bottom:1px solid rgba(255,255,255,.05);position:relative;overflow:hidden}
.sub-header::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 80% 50%,rgba(201,168,76,.08) 0%,transparent 60%);pointer-events:none}
.sh-icon{width:44px;height:44px;border-radius:12px;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.25);display:flex;align-items:center;justify-content:center;color:var(--gold-lt);font-size:1.15rem;flex-shrink:0}
.sh-text h2{font-family:var(--serif);font-size:1.15rem;font-weight:700;color:#fff;letter-spacing:-.01em;margin-bottom:2px}
.sh-text p{font-size:.75rem;color:rgba(255,255,255,.42);font-weight:500}
.sh-badge{margin-left:auto;font-family:var(--mono);font-size:.68rem;font-weight:500;color:var(--gold-lt);letter-spacing:.1em;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.25);padding:6px 18px;border-radius:30px;white-space:nowrap}

/* Back bar */
.back-bar{max-width:1340px;margin:0 auto;padding:16px 36px 0}
.back-link{display:inline-flex;align-items:center;gap:7px;font-size:.73rem;font-weight:700;color:var(--ink-3);text-decoration:none;letter-spacing:.07em;text-transform:uppercase;transition:color .18s}
.back-link:hover{color:var(--gold-dk)}

/* Layout */
.page-wrap{max-width:1340px;margin:0 auto;padding:22px 36px 100px;display:grid;grid-template-columns:256px 1fr;gap:28px;align-items:start}

/* Sidebar */
.sidebar{display:flex;flex-direction:column;gap:12px;position:sticky;top:82px;animation:slideInL .5s .05s cubic-bezier(0.16,1,.3,1) both}
.sb-profile{background:linear-gradient(160deg,var(--navy-2) 0%,var(--navy-4) 100%);border-radius:var(--r-lg);padding:24px 20px 20px;text-align:center;border:1px solid rgba(255,255,255,.06);box-shadow:var(--sh-md);position:relative;overflow:hidden}
.sb-profile::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(201,168,76,.12) 0%,transparent 65%);pointer-events:none}
.sb-av-wrap{position:relative;display:inline-block;margin-bottom:14px}
.sb-av{width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(201,168,76,.5);box-shadow:0 0 0 5px rgba(201,168,76,.1),var(--sh-md)}
.sb-online{position:absolute;bottom:3px;right:3px;width:14px;height:14px;border-radius:50%;background:var(--green-lt);border:2.5px solid var(--navy-2);box-shadow:0 0 6px rgba(18,181,133,.5)}
.sb-name{font-size:.95rem;font-weight:800;color:#fff;margin-bottom:3px;letter-spacing:-.01em}
.sb-email{font-size:.68rem;color:rgba(255,255,255,.38);word-break:break-all;line-height:1.4}
.sb-badge{display:inline-flex;align-items:center;gap:5px;margin-top:12px;font-family:var(--mono);font-size:.6rem;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--gold-lt);background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.22);padding:4px 14px;border-radius:20px}
.sb-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.sb-stat{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-md);padding:13px 10px;text-align:center;box-shadow:var(--sh-xs);transition:transform .2s,box-shadow .2s}
.sb-stat:hover{transform:translateY(-2px);box-shadow:var(--sh-sm)}
.sb-stat .n{font-family:var(--mono);font-size:1.2rem;font-weight:600;display:block;font-variant-numeric:tabular-nums;line-height:1;color:var(--navy)}
.sb-stat .l{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-3);display:block;margin-top:4px}
.sb-stat.c-gold .n{color:var(--gold-dk)}.sb-stat.c-green .n{color:var(--green)}.sb-stat.c-red .n{color:var(--red)}
.sb-nav{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-sm);overflow:hidden;box-shadow:var(--sh-sm)}
.sb-nav-item{display:flex;align-items:center;gap:12px;padding:12px 16px;font-size:.85rem;font-weight:600;color:var(--ink-2);text-decoration:none;border-bottom:1px solid var(--border-2);transition:background .18s,color .18s,border-color .18s;position:relative;letter-spacing:.01em}
.sb-nav-item:last-child{border-bottom:none}
.sb-nav-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--gold);transform:scaleX(0);transform-origin:left;transition:transform .18s;border-radius:0 2px 2px 0}
.sb-nav-item:hover{background:var(--cream-2);color:var(--navy)}
.sb-nav-item:hover::before,.sb-nav-item.active::before{transform:scaleX(1)}
.sb-nav-item.active{background:rgba(201,168,76,.1);color:var(--navy);font-weight:800;border-left:3px solid var(--gold)}
.sb-nav-item i{width:18px;text-align:center;font-size:.84rem;color:var(--ink-3);flex-shrink:0;transition:color .18s}
.sb-nav-item:hover i,.sb-nav-item.active i{color:var(--gold)}
/* Search Flights — gold accent row */
.sb-nav-item.search-link{background:rgba(201,168,76,.07);color:var(--gold-dk);font-weight:700;border-left:3px solid rgba(201,168,76,.5)}
.sb-nav-item.search-link i{color:var(--gold-dk)}
.sb-nav-item.search-link:hover{background:rgba(201,168,76,.15);color:var(--navy);border-left-color:var(--gold)}
.sb-logout{display:flex;align-items:center;justify-content:center;gap:9px;padding:12px;background:transparent;border:1.5px solid var(--border);border-radius:var(--r-md);color:var(--ink-3);font-family:var(--sans);font-size:.82rem;font-weight:700;text-decoration:none;transition:all .2s;letter-spacing:.02em}
.sb-logout:hover{border-color:var(--red);color:var(--red);background:var(--red-bg)}
</style>
<style>
/* Main column */
.main-col{min-width:0;animation:riseIn .5s .1s cubic-bezier(0.16,1,.3,1) both}

/* Stats row */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px}
.stat-card{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-lg);padding:20px 22px;text-align:center;box-shadow:var(--sh-sm);transition:transform .22s,box-shadow .22s;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;opacity:0;transition:opacity .22s}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--sh-md)}
.stat-card:hover::after{opacity:1}
.sc-total::after{background:var(--navy)}.sc-confirm::after{background:var(--green)}.sc-cancel::after{background:var(--red)}
.stat-card .sc-n{font-family:var(--mono);font-size:2rem;font-weight:600;line-height:1;display:block;font-variant-numeric:tabular-nums}
.stat-card .sc-l{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3);margin-top:6px;display:block}
.sc-total .sc-n{color:var(--navy)}.sc-confirm .sc-n{color:var(--green)}.sc-cancel .sc-n{color:var(--red)}

/* Filter bar */
.filter-bar{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-lg);padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px;box-shadow:var(--sh-sm)}
.filter-tabs{display:flex;gap:8px}
.ftab{padding:8px 20px;border-radius:30px;font-size:.8rem;font-weight:700;text-decoration:none;transition:all .2s;border:1.5px solid var(--border);color:var(--ink-3);background:var(--surface);font-family:var(--sans)}
.ftab:hover{border-color:var(--gold);color:var(--gold-dk);background:var(--gold-tint)}
.ftab.active{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:0 3px 12px rgba(8,23,46,.2)}
.result-count{font-family:var(--mono);font-size:.73rem;color:var(--ink-3)}

/* Booking card */
.bp-card{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-xl);margin-bottom:18px;display:flex;overflow:hidden;box-shadow:var(--sh-sm);transition:box-shadow .25s,transform .25s,border-color .25s;animation:riseIn .4s ease both;position:relative}
.bp-card:hover{box-shadow:var(--sh-lg);transform:translateY(-4px);border-color:var(--cream-3)}
.bp-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,var(--gold) 0%,var(--gold-lt) 100%);opacity:0;transition:opacity .25s;border-radius:var(--r-xl) 0 0 var(--r-xl)}
.bp-card:hover::before{opacity:1}

.bp-stripe{width:5px;flex-shrink:0}
.stripe-confirmed{background:linear-gradient(180deg,var(--green),#047857)}
.stripe-cancelled{background:linear-gradient(180deg,var(--red),#9b1c1c)}
.stripe-pending{background:linear-gradient(180deg,#f59e0b,#d97706)}

.bp-img{width:120px;flex-shrink:0;background:linear-gradient(145deg,var(--navy-2) 0%,var(--navy-4) 100%);display:flex;align-items:center;justify-content:center;font-size:2.6rem;color:var(--gold-lt);border-right:1px solid var(--border-2)}
.bp-img img{width:100%;height:100%;object-fit:cover}

.bp-body{flex:1;padding:22px 26px;display:flex;gap:0;align-items:stretch;min-width:0}
.bp-flight{flex:1;min-width:0;padding-right:26px}
.bp-ref{font-family:var(--mono);font-size:.67rem;font-weight:500;color:var(--ink-3);letter-spacing:.1em;text-transform:uppercase;margin-bottom:11px;display:flex;align-items:center;gap:8px}
.bp-ref b{color:var(--navy-3);font-size:.78rem}

.bp-route{display:flex;align-items:center;gap:14px;margin-bottom:13px}
.bp-iata{font-family:var(--serif);font-size:1.85rem;font-weight:900;color:var(--ink);letter-spacing:-.04em;line-height:1}
.bp-route-mid{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px}
.bp-route-line{width:100%;display:flex;align-items:center}
.bp-route-line::before,.bp-route-line::after{content:'';flex:1;height:1.5px;background:linear-gradient(90deg,var(--border),var(--gold))}
.bp-route-line::after{background:linear-gradient(90deg,var(--gold),var(--border))}
.bp-plane{color:var(--gold);font-size:1rem}
.bp-dur{font-family:var(--mono);font-size:.67rem;color:var(--ink-3);background:var(--cream-2);padding:2px 9px;border-radius:10px;border:1px solid var(--border)}

.bp-times{display:flex;align-items:center;gap:10px;background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.18);border-radius:10px;padding:7px 13px;margin-bottom:11px;width:fit-content}
.bp-tb{display:flex;flex-direction:column;align-items:center;gap:1px}
.bp-tb b{font-family:var(--mono);font-size:.88rem;font-weight:600;color:var(--navy)}
.bp-tb span{font-size:.6rem;color:var(--ink-2);font-weight:600;text-transform:uppercase}
.bp-tb small{font-size:.57rem;color:var(--ink-3);text-transform:uppercase;letter-spacing:.05em}
.bp-tsep{color:var(--gold);font-weight:700;font-size:.9rem}

.bp-fname{font-size:.8rem;font-weight:700;color:var(--ink-2);margin-bottom:11px}
.bp-tags{display:flex;flex-wrap:wrap;gap:6px}
.bp-tag{font-family:var(--mono);font-size:.67rem;padding:3px 10px;border-radius:20px;background:var(--cream-2);color:var(--ink-2);border:1px solid var(--border)}

.bp-notch{width:1px;background:repeating-linear-gradient(to bottom,var(--border) 0px,var(--border) 6px,transparent 6px,transparent 12px);margin:0 4px;flex-shrink:0;align-self:stretch}

.bp-right{padding-left:26px;display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between;min-width:168px;flex-shrink:0}
.bp-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 15px;border-radius:20px;font-size:.74rem;font-weight:700}
.badge-confirmed{background:var(--green-bg);color:var(--green);border:1px solid rgba(10,143,106,.2)}
.badge-cancelled{background:var(--red-bg);color:var(--red);border:1px solid rgba(200,41,58,.18)}
.badge-pending{background:var(--amber-bg);color:var(--amber);border:1px solid rgba(176,92,16,.2)}

.bp-price-wrap{text-align:right}
.bp-price-lbl{font-family:var(--mono);font-size:.58rem;color:var(--ink-3);text-transform:uppercase;letter-spacing:.09em;font-weight:500}
.bp-price{font-family:var(--serif);font-size:1.9rem;font-weight:900;color:var(--navy);letter-spacing:-.04em;line-height:1}
.bp-price-sub{font-family:var(--mono);font-size:.67rem;color:var(--ink-3);margin-top:2px}

.bp-actions{display:flex;gap:8px}
.btn-view{padding:9px 18px;border-radius:10px;font-size:.8rem;font-weight:700;text-decoration:none;background:var(--cream-2);color:var(--navy);border:1.5px solid var(--border);transition:all .2s;font-family:var(--sans)}
.btn-view:hover{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:var(--sh-sm)}
.btn-cancel{padding:9px 18px;border-radius:10px;font-size:.8rem;font-weight:700;background:var(--red-bg);color:var(--red);border:1.5px solid rgba(200,41,58,.2);cursor:pointer;transition:all .2s;font-family:var(--sans)}
.btn-cancel:hover{background:var(--red);color:#fff}

/* Empty state */
.empty-state{text-align:center;padding:90px 40px;background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border-2);box-shadow:var(--sh-sm)}
.empty-state .e-icon{font-size:3.5rem;color:var(--border);margin-bottom:22px;display:block}
.empty-state h3{font-family:var(--serif);font-size:1.5rem;font-weight:700;color:var(--ink);margin-bottom:10px}
.empty-state p{color:var(--ink-3);font-size:.9rem;margin-bottom:26px;line-height:1.6}
.btn-search{display:inline-block;padding:13px 32px;background:var(--navy);color:#fff;border-radius:50px;text-decoration:none;font-weight:700;font-size:.9rem;box-shadow:var(--sh-md);transition:all .22s;font-family:var(--sans)}
.btn-search:hover{transform:translateY(-2px);background:var(--navy-2);box-shadow:var(--sh-lg)}

@media(max-width:1100px){.page-wrap{grid-template-columns:1fr}.sidebar{position:static}}
@media(max-width:780px){.page-wrap{padding:18px 16px 80px}.bp-body{flex-wrap:wrap}.bp-right{min-width:100%;padding-left:0;padding-top:16px;flex-direction:row;align-items:center;justify-content:space-between;border-top:1px solid var(--border-2)}.bp-img{width:80px}.bp-iata{font-size:1.4rem}.stats-row{grid-template-columns:repeat(3,1fr)}.sub-header{padding:14px 20px}}
</style>
</head>
<body>

<div class="sub-header">
    <div class="sh-icon"><i class="fas fa-ticket"></i></div>
    <div class="sh-text">
        <h2>My Bookings</h2>
        <p>View and manage all your flight reservations</p>
    </div>
    <div class="sh-badge">✈ GoZayan Traveller</div>
</div>

<div class="back-bar">
    <a href="userhome.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="page-wrap">
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
            <div class="sb-stat"><span class="n"><?= $total ?></span><span class="l">Bookings</span></div>
            <div class="sb-stat c-green"><span class="n"><?= $confirmed ?></span><span class="l">Confirmed</span></div>
            <div class="sb-stat c-red"><span class="n"><?= $cancelled ?></span><span class="l">Cancelled</span></div>
            <div class="sb-stat c-gold" style="grid-column:1/-1"><span class="n">$<?= number_format((float)$spent,0) ?></span><span class="l">Total Spent</span></div>
        </div>
        <nav class="sb-nav">
            <a href="userhome.php"       class="sb-nav-item"><i class="fas fa-house"></i> Dashboard</a>
            <a href="searchflights.php"  class="sb-nav-item search-link"><i class="fas fa-magnifying-glass"></i> Search Flights</a>
            <a href="myBookings.php"     class="sb-nav-item active"><i class="fas fa-ticket"></i> My Bookings</a>
            <a href="passengerProfile.php" class="sb-nav-item"><i class="fas fa-user"></i> My Profile</a>
            <a href="changePassword.php" class="sb-nav-item"><i class="fas fa-lock"></i> Change Password</a>
        </nav>
        <a href="/flight_booking/logout.php" class="sb-logout"><i class="fas fa-right-from-bracket"></i> Sign Out</a>
    </aside>

    <div class="main-col">
        <div class="stats-row">
            <div class="stat-card sc-total"><span class="sc-n"><?= $total ?></span><span class="sc-l">Total Bookings</span></div>
            <div class="stat-card sc-confirm"><span class="sc-n"><?= $confirmed ?></span><span class="sc-l">Confirmed</span></div>
            <div class="stat-card sc-cancel"><span class="sc-n"><?= $cancelled ?></span><span class="sc-l">Cancelled</span></div>
        </div>

        <div class="filter-bar">
            <div class="filter-tabs">
                <a href="?status=all"       class="ftab <?= $status_filter==='all'       ?'active':'' ?>">All</a>
                <a href="?status=confirmed" class="ftab <?= $status_filter==='confirmed' ?'active':'' ?>"><i class="fas fa-check"></i> Confirmed</a>
                <a href="?status=cancelled" class="ftab <?= $status_filter==='cancelled' ?'active':'' ?>"><i class="fas fa-xmark"></i> Cancelled</a>
            </div>
            <span class="result-count"><?= $bookings_result?$bookings_result->num_rows:0 ?> booking<?= ($bookings_result&&$bookings_result->num_rows!=1)?'s':'' ?> found</span>
        </div>

        <?php if ($bookings_result && $bookings_result->num_rows > 0):
            while ($b = $bookings_result->fetch_assoc()):
                $status  = $b['status'] ?? 'pending';
                $dep_t   = substr(!empty($b['sched_dep_time'])?$b['sched_dep_time']:($b['departure_time']??''),0,5);
                $arr_t   = substr(!empty($b['sched_arr_time'])?$b['sched_arr_time']:($b['arrival_time']??''),0,5);
                $dep_day = $b['departure_day']??''; $arr_day = $b['arrival_day']??'';
        ?>
        <div class="bp-card">
            <div class="bp-stripe stripe-<?= htmlspecialchars($status) ?>"></div>
            <div class="bp-img">
                <?php if (!empty($b['flight_image'])): ?><img src="upload/<?= htmlspecialchars($b['flight_image']) ?>" alt="Flight">
                <?php else: ?><i class="fas fa-plane"></i><?php endif; ?>
            </div>
            <div class="bp-body">
                <div class="bp-flight">
                    <div class="bp-ref">Booking Ref &nbsp;<b>#<?= str_pad($b['id'],6,'0',STR_PAD_LEFT) ?></b>&nbsp;·&nbsp;<?= date('d M Y',strtotime($b['booking_date'])) ?></div>
                    <div class="bp-route">
                        <span class="bp-iata"><?= strtoupper(substr($b['from_location']??$b['departure'],0,3)) ?></span>
                        <div class="bp-route-mid">
                            <div class="bp-route-line"><span class="bp-plane"><i class="fas fa-plane"></i></span></div>
                            <?php if (!empty($b['duration'])): ?><span class="bp-dur"><?= htmlspecialchars($b['duration']) ?></span><?php endif; ?>
                        </div>
                        <span class="bp-iata"><?= strtoupper(substr($b['to_location']??$b['arrival'],0,3)) ?></span>
                    </div>
                    <?php if ($dep_t||$dep_day): ?>
                    <div class="bp-times">
                        <span class="bp-tb"><?php if($dep_t):?><b><?= htmlspecialchars($dep_t) ?></b><?php endif;?><?php if($dep_day):?><span><?= htmlspecialchars($dep_day) ?></span><?php endif;?><small>Dep</small></span>
                        <span class="bp-tsep">→</span>
                        <span class="bp-tb"><?php if($arr_t):?><b><?= htmlspecialchars($arr_t) ?></b><?php endif;?><?php if($arr_day):?><span><?= htmlspecialchars($arr_day) ?></span><?php endif;?><small>Arr</small></span>
                    </div>
                    <?php endif; ?>
                    <div class="bp-fname"><?= htmlspecialchars($b['flight_name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($b['airline_name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($b['flight_code']) ?></div>
                    <div class="bp-tags">
                        <span class="bp-tag"><i class="fas fa-calendar-days"></i> <?= date('d M Y',strtotime($b['depart_date'])) ?></span>
                        <span class="bp-tag"><i class="fas fa-users"></i> <?= $b['adults'] ?> Adult<?= $b['adults']>1?'s':'' ?><?= $b['children']>0?', '.$b['children'].' Child'.($b['children']>1?'ren':''):'' ?></span>
                        <span class="bp-tag"><i class="fas fa-chair"></i> <?= htmlspecialchars($b['class']) ?></span>
                        <span class="bp-tag"><i class="fas fa-rotate"></i> <?= ucfirst($b['trip_type']) ?></span>
                    </div>
                </div>
                <div class="bp-notch"></div>
                <div class="bp-right">
                    <span class="bp-badge badge-<?= htmlspecialchars($status) ?>">
                        <?php if($status==='confirmed'):?><i class="fas fa-circle-check"></i><?php elseif($status==='cancelled'):?><i class="fas fa-circle-xmark"></i><?php else:?><i class="fas fa-clock"></i><?php endif;?>
                        <?= ucfirst($status) ?>
                    </span>
                    <div class="bp-price-wrap">
                        <div class="bp-price-lbl">Total Paid</div>
                        <div class="bp-price">$<?= number_format($b['total_price'],0) ?></div>
                        <div class="bp-price-sub">incl. all taxes</div>
                    </div>
                    <div class="bp-actions">
                        <a href="booking_confirm.php?id=<?= $b['id'] ?>" class="btn-view">View</a>
                        <?php if ($status==='confirmed'): ?>
                        <form method="POST" onsubmit="return confirm('Cancel this booking?')" style="margin:0">
                            <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn-cancel">Cancel</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state">
            <span class="e-icon"><i class="fas fa-ticket"></i></span>
            <h3>No bookings found</h3>
            <p>You haven't made any <?= $status_filter!=='all'?$status_filter:'' ?> bookings yet.<br>Start by searching for available flights.</p>
            <a href="searchflights.php" class="btn-search">Search Flights</a>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
<?php include("../includes/footer.php"); ?>
