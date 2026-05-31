<?php
session_start();
require_once __DIR__ . "/../config/base_url.php";
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
           f.departure_time, f.arrival_time, f.status as flight_status,
           ROUND(f.price * (1 - f.discount_pct / 100), 2) AS current_unit_price,
           f.discount_pct, s.departure_day, s.arrival_day,
           s.departure_time AS sched_dep_time, s.arrival_time AS sched_arr_time,
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

$stats_stmt = $conn->prepare("
    SELECT COUNT(*) as total, SUM(status='confirmed') as confirmed,
           SUM(status='cancelled') as cancelled,
           SUM(CASE WHEN status='confirmed' THEN total_price ELSE 0 END) as spent
    FROM bookings b JOIN webusers w ON b.user_id = w.id WHERE w.email = ?
");
$stats_stmt->bind_param("s", $email);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$dep_t   = substr(!empty($booking['sched_dep_time'])?$booking['sched_dep_time']:($booking['departure_time']??''),0,5);
$arr_t   = substr(!empty($booking['sched_arr_time'])?$booking['sched_arr_time']:($booking['arrival_time']??''),0,5);
$dep_day = $booking['departure_day']??'';
$arr_day = $booking['arrival_day']??'';
$ref     = str_pad($booking['id'],6,'0',STR_PAD_LEFT);

$qr_data = "GoZayan #{$ref} | {$booking['passenger_name']} | {$booking['flight_code']} | {$booking['from_location']} to {$booking['to_location']} | ".date('d M Y',strtotime($booking['depart_date']));
$qr_url  = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=".urlencode($qr_data);

$avatar_src = "https://ui-avatars.com/api/?name=".urlencode($booking['passenger_name'])."&background=0b1f3a&color=d4a84b&size=80&bold=true";
if (!empty($booking['user_image']) && file_exists(__DIR__."/uploads/".$booking['user_image']))
    $avatar_src = "uploads/".htmlspecialchars($booking['user_image']);

include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GoZayan · Booking #<?= $ref ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,900;1,600;1,700&family=DM+Mono:wght@400;500;600&family=Mulish:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --navy:#08172e; --navy-2:#0f2444; --navy-3:#172f56; --navy-4:#1e3d6e;
    --gold:#c9a84c; --gold-lt:#e0bc6a; --gold-dk:#a8893a;
    --gold-tint:rgba(201,168,76,.09); --gold-glow:rgba(201,168,76,.22);
    --cream:#f8f5f0; --cream-2:#f0ebe2; --cream-3:#e6dfd4;
    --ink:#0d1a28; --ink-2:#2e4057; --ink-3:#6b84a0; --ink-4:#9db3c8;
    --surface:#ffffff; --surface-2:#fdfcfa;
    --green:#0a8f6a; --green-lt:#12b585; --green-bg:#d0f5ea;
    --red:#c8293a; --red-bg:#fde8ea;
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
@keyframes checkPop{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}

/* ── Sub-header banner ── */
.sub-header{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-3) 100%);padding:18px 40px;display:flex;align-items:center;gap:20px;border-bottom:1px solid rgba(255,255,255,.05);position:relative;overflow:hidden}
.sub-header::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 80% 50%,rgba(201,168,76,.08) 0%,transparent 60%);pointer-events:none}
.sh-check{width:44px;height:44px;border-radius:50%;background:var(--green);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;box-shadow:0 0 0 6px rgba(10,143,106,.18);animation:checkPop .5s cubic-bezier(0.34,1.56,0.64,1) both}
.sh-text h2{font-family:var(--serif);font-size:1.15rem;font-weight:700;color:#fff;letter-spacing:-.01em;margin-bottom:2px}
.sh-text p{font-size:.75rem;color:rgba(255,255,255,.42);font-weight:500}
.sh-ref{margin-left:auto;font-family:var(--mono);font-size:.7rem;font-weight:500;color:var(--gold-lt);letter-spacing:.1em;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.25);padding:7px 20px;border-radius:30px;white-space:nowrap}

/* ── Back bar ── */
.back-bar{max-width:1340px;margin:0 auto;padding:16px 36px 0}
.back-link{display:inline-flex;align-items:center;gap:7px;font-size:.73rem;font-weight:700;color:var(--ink-3);text-decoration:none;letter-spacing:.07em;text-transform:uppercase;transition:color .18s}
.back-link:hover{color:var(--gold-dk)}

/* ── Three-column layout ── */
.page-wrap{max-width:1340px;margin:0 auto;padding:22px 36px 100px;display:grid;grid-template-columns:256px 1fr 288px;gap:26px;align-items:start}

/* ── Sidebar ── */
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
.sb-stat.c-gold .n{color:var(--gold-dk)}.sb-stat.c-green .n{color:var(--green)}
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
.sb-logout{display:flex;align-items:center;justify-content:center;gap:9px;padding:12px;background:transparent;border:1.5px solid var(--border);border-radius:var(--r-md);color:var(--ink-3);font-family:var(--sans);font-size:.82rem;font-weight:700;text-decoration:none;transition:all .2s}
.sb-logout:hover{border-color:var(--red);color:var(--red);background:var(--red-bg)}
</style>
<style>
/* ── Boarding Pass ── */
.bp{background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border-2);box-shadow:var(--sh-lg);overflow:hidden;animation:riseIn .5s .1s cubic-bezier(0.16,1,.3,1) both}
.bp-top-stripe{height:4px;background:linear-gradient(90deg,var(--navy) 0%,var(--navy-3) 40%,var(--gold) 100%)}
.bp-head{background:linear-gradient(145deg,var(--navy) 0%,var(--navy-3) 100%);padding:28px 34px 32px;position:relative;overflow:hidden}
.bp-head::before{content:'✈';position:absolute;right:-14px;top:-18px;font-size:9rem;opacity:.04;line-height:1;transform:rotate(10deg);pointer-events:none}
.bp-head::after{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.035) 1px,transparent 1px);background-size:24px 24px;pointer-events:none}
.bp-head-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:26px;position:relative;z-index:1}
.bp-airline{font-family:var(--serif);font-size:1.35rem;font-weight:700;color:#fff;letter-spacing:-.02em;margin-bottom:5px}
.bp-flight-sub{font-family:var(--mono);font-size:.65rem;color:rgba(255,255,255,.4);letter-spacing:.1em}
.bp-ticket-ref{font-family:var(--mono);font-size:.72rem;font-weight:500;color:var(--gold-lt);background:rgba(255,255,255,.07);border:1px solid rgba(201,168,76,.22);padding:6px 16px;border-radius:20px;letter-spacing:.08em}
.bp-route{display:flex;align-items:center;position:relative;z-index:1}
.bp-city{text-align:center}
.bp-iata{font-family:var(--serif);font-size:3.4rem;font-weight:900;color:#fff;letter-spacing:-.05em;line-height:1}
.bp-city-name{font-size:.67rem;color:rgba(255,255,255,.4);margin-top:5px;font-weight:500;letter-spacing:.07em;text-transform:uppercase}
.bp-time-block{margin-top:8px;font-family:var(--mono);font-size:.82rem;font-weight:500;color:var(--gold-lt);letter-spacing:.04em}
.bp-day-block{font-size:.6rem;color:rgba(255,255,255,.3);margin-top:2px;letter-spacing:.06em}
.bp-route-mid{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;padding:0 24px}
.bp-dur-pill{font-family:var(--mono);font-size:.6rem;color:rgba(255,255,255,.4);background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);padding:3px 13px;border-radius:20px;letter-spacing:.08em}
.bp-line{display:flex;align-items:center;width:100%}
.bp-line::before,.bp-line::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,rgba(255,255,255,.08),rgba(255,255,255,.22))}
.bp-line::after{background:linear-gradient(90deg,rgba(255,255,255,.22),rgba(255,255,255,.08))}
.bp-plane-icon{font-size:1rem;color:var(--gold-lt);margin:0 10px}

/* Tear line */
.bp-tear{display:flex;align-items:center;background:var(--cream-2);height:30px;position:relative}
.bp-tear::before{content:'';position:absolute;left:-1px;top:50%;transform:translateY(-50%);width:20px;height:40px;border-radius:0 20px 20px 0;background:var(--cream);border:1px solid var(--border-2);border-left:none}
.bp-tear::after{content:'';position:absolute;right:-1px;top:50%;transform:translateY(-50%);width:20px;height:40px;border-radius:20px 0 0 20px;background:var(--cream);border:1px solid var(--border-2);border-right:none}
.bp-tear-dash{flex:1;margin:0 24px;border-top:2px dashed var(--border)}
.bp-tear-scissors{font-size:.85rem;color:var(--ink-3)}

/* BP body */
.bp-body{padding:28px 34px 30px;display:grid;grid-template-columns:1fr auto;gap:30px;align-items:start}
.bp-fields-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px 16px;margin-bottom:26px}
.bp-field .f-lbl{font-family:var(--mono);font-size:.56rem;font-weight:500;letter-spacing:.15em;text-transform:uppercase;color:var(--ink-3);margin-bottom:5px}
.bp-field .f-val{font-size:.9rem;font-weight:700;color:var(--ink);line-height:1.3}
.bp-rule{height:1px;background:var(--border-2);margin-bottom:24px}
.bp-price-row{display:flex;align-items:center;justify-content:space-between}
.price-label{font-family:var(--mono);font-size:.58rem;font-weight:500;letter-spacing:.15em;text-transform:uppercase;color:var(--ink-3);margin-bottom:5px}
.price-amount{font-family:var(--serif);font-size:2.6rem;font-weight:900;color:var(--navy);letter-spacing:-.05em;line-height:1}
.price-per{font-size:.7rem;color:var(--ink-3);font-weight:500;margin-top:3px;font-family:var(--mono)}
.status-badge{display:inline-flex;align-items:center;gap:8px;padding:9px 22px;border-radius:30px;font-size:.8rem;font-weight:800;letter-spacing:.04em}
.s-confirmed{background:var(--green-bg);color:var(--green);border:1px solid rgba(10,143,106,.22)}
.s-cancelled{background:var(--red-bg);color:var(--red);border:1px solid rgba(200,41,58,.18)}

/* QR */
.bp-qr{display:flex;flex-direction:column;align-items:center;gap:9px;text-align:center}
.qr-frame{width:128px;height:128px;border:1.5px solid var(--border-2);border-radius:14px;overflow:hidden;background:#fff;padding:6px;box-shadow:var(--sh-sm)}
.qr-frame img{width:100%;height:100%;display:block;border-radius:9px}
.qr-label{font-family:var(--mono);font-size:.58rem;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-3)}
.qr-ref{font-family:var(--mono);font-size:.76rem;font-weight:600;color:var(--navy-3);letter-spacing:.04em}

/* Payment strip */
.bp-payment{background:var(--cream-2);border-top:1px solid var(--border-2);padding:18px 34px;display:flex;gap:30px;flex-wrap:wrap}
.pay-item .py-lbl{font-family:var(--mono);font-size:.56rem;font-weight:500;letter-spacing:.13em;text-transform:uppercase;color:var(--ink-3);margin-bottom:4px}
.pay-item .py-val{font-size:.84rem;font-weight:700;color:var(--ink)}

/* Right column */
.right-col{display:flex;flex-direction:column;gap:16px;animation:riseIn .5s .22s cubic-bezier(0.16,1,.3,1) both}
.panel{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--sh-sm)}
.panel-head{padding:14px 20px;border-bottom:1px solid var(--border-2);display:flex;align-items:center;gap:9px;font-size:.72rem;font-weight:800;color:var(--ink);text-transform:uppercase;letter-spacing:.09em;background:var(--surface-2)}
.ph-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0}
.panel-body{padding:16px 20px}
.journey-card{background:linear-gradient(145deg,var(--navy-2) 0%,var(--navy-4) 100%);border-radius:var(--r-md);padding:20px 18px}
.jc-route{display:flex;align-items:center;gap:9px;font-family:var(--serif);font-size:1.3rem;font-weight:700;color:#fff;margin-bottom:12px;letter-spacing:-.02em}
.jc-arrow{color:var(--gold-lt);font-size:.9rem}
.jc-chips{display:flex;flex-wrap:wrap;gap:6px}
.jc-chip{font-family:var(--mono);font-size:.6rem;font-weight:500;letter-spacing:.06em;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.55);padding:3px 10px;border-radius:20px}
.jc-chip.hi{background:rgba(201,168,76,.16);border-color:rgba(201,168,76,.28);color:var(--gold-lt)}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--cream-2);font-size:.8rem}
.info-row:last-child{border-bottom:none}
.info-row .k{color:var(--ink-3);font-weight:500}
.info-row .v{font-weight:700;color:var(--ink);text-align:right;max-width:58%}
.act-btn{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;padding:12px 16px;border-radius:var(--r-md);font-family:var(--sans);font-size:.83rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;border:none;margin-bottom:8px;letter-spacing:.02em}
.act-btn:last-child{margin-bottom:0}
.btn-navy{background:var(--navy);color:#fff;box-shadow:var(--sh-sm)}
.btn-navy:hover{background:var(--navy-2);transform:translateY(-1px);box-shadow:var(--sh-md)}
.btn-outline{background:transparent;color:var(--navy);border:1.5px solid var(--border)}
.btn-outline:hover{border-color:var(--navy);background:var(--cream-2)}

/* Responsive */
@media(max-width:1100px){.page-wrap{grid-template-columns:256px 1fr}.right-col{display:none}}
@media(max-width:780px){.page-wrap{grid-template-columns:1fr;padding:18px 16px 80px}.sidebar{position:static}.bp-fields-grid{grid-template-columns:repeat(2,1fr);gap:16px}.bp-body{grid-template-columns:1fr;gap:24px}.bp-head{padding:22px 22px 26px}.bp-body{padding:22px 22px 26px}.bp-payment{padding:16px 22px;gap:20px}.bp-iata{font-size:2.8rem}.price-amount{font-size:2.1rem}.right-col{display:flex}.sub-header{padding:14px 20px}}
@media print{.sub-header,.back-bar,.sidebar,.right-col,.bp-tear,header,footer{display:none!important}*,*::before,*::after{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{background:#fff!important;padding-top:0!important}.page-wrap{display:block!important;padding:0!important}.bp{box-shadow:none!important;border-radius:0!important}}
</style>
</head>
<body>

<div class="sub-header">
    <div class="sh-check"><i class="fas fa-check"></i></div>
    <div class="sh-text">
        <h2>Booking Confirmed!</h2>
        <p>Your ticket has been issued. Have a wonderful journey.</p>
    </div>
    <div class="sh-ref">Ref #<?= $ref ?></div>
</div>

<div class="back-bar">
    <a href="myBookings.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to My Bookings</a>
</div>

<div class="page-wrap">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
        <div class="sb-profile">
            <div class="sb-av-wrap">
                <img class="sb-av" src="<?= $avatar_src ?>" alt="<?= htmlspecialchars($booking['passenger_name']) ?>">
                <div class="sb-online"></div>
            </div>
            <div class="sb-name"><?= htmlspecialchars(explode(' ',trim($booking['passenger_name']))[0]) ?></div>
            <div class="sb-email"><?= htmlspecialchars($booking['passenger_email']) ?></div>
            <div class="sb-badge"><i class="fas fa-star" style="font-size:.55rem"></i> GoZayan Traveller</div>
        </div>
        <div class="sb-stats">
            <div class="sb-stat"><span class="n"><?= (int)$stats['total'] ?></span><span class="l">Bookings</span></div>
            <div class="sb-stat c-green"><span class="n"><?= (int)$stats['confirmed'] ?></span><span class="l">Confirmed</span></div>
            <div class="sb-stat c-gold" style="grid-column:1/-1"><span class="n">$<?= number_format((float)$stats['spent'],0) ?></span><span class="l">Total Spent</span></div>
        </div>
        <nav class="sb-nav">
            <a href="userhome.php"       class="sb-nav-item"><i class="fas fa-house"></i> Dashboard</a>
            <a href="searchflights.php"  class="sb-nav-item search-link"><i class="fas fa-magnifying-glass"></i> Search Flights</a>
            <a href="myBookings.php"     class="sb-nav-item"><i class="fas fa-ticket"></i> My Bookings</a>
            <a href="booking_confirm.php?id=<?= $booking_id ?>" class="sb-nav-item active"><i class="fas fa-circle-check"></i> This Booking</a>
            <a href="passengerProfile.php" class="sb-nav-item"><i class="fas fa-user"></i> My Profile</a>
            <a href="changePassword.php" class="sb-nav-item"><i class="fas fa-lock"></i> Change Password</a>
        </nav>
        <a href="<?= BASE_URL ?>/logout.php" class="sb-logout"><i class="fas fa-right-from-bracket"></i> Sign Out</a>
    </aside>

    <!-- ══ BOARDING PASS ══ -->
    <div class="bp">
        <div class="bp-top-stripe"></div>
        <div class="bp-head">
            <div class="bp-head-top">
                <div>
                    <div class="bp-airline"><i class="fas fa-plane" style="font-size:.9rem;margin-right:6px"></i><?= htmlspecialchars($booking['airline_name']) ?></div>
                    <div class="bp-flight-sub"><?= htmlspecialchars($booking['flight_code']) ?> &nbsp;·&nbsp; <?= ucfirst($booking['trip_type']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($booking['class']) ?></div>
                </div>
                <div class="bp-ticket-ref"><?= htmlspecialchars($booking['flight_name']) ?></div>
            </div>
            <div class="bp-route">
                <div class="bp-city">
                    <div class="bp-iata"><?= strtoupper(substr($booking['from_location'],0,3)) ?></div>
                    <div class="bp-city-name"><?= htmlspecialchars($booking['from_location']) ?></div>
                    <?php if ($dep_t): ?><div class="bp-time-block"><?= htmlspecialchars($dep_t) ?></div><?php endif; ?>
                    <?php if ($dep_day): ?><div class="bp-day-block"><?= htmlspecialchars($dep_day) ?></div><?php endif; ?>
                </div>
                <div class="bp-route-mid">
                    <div class="bp-dur-pill"><?= htmlspecialchars($booking['duration']) ?></div>
                    <div class="bp-line"><span class="bp-plane-icon"><i class="fas fa-plane"></i></span></div>
                </div>
                <div class="bp-city" style="text-align:right">
                    <div class="bp-iata"><?= strtoupper(substr($booking['to_location'],0,3)) ?></div>
                    <div class="bp-city-name"><?= htmlspecialchars($booking['to_location']) ?></div>
                    <?php if ($arr_t): ?><div class="bp-time-block"><?= htmlspecialchars($arr_t) ?></div><?php endif; ?>
                    <?php if ($arr_day): ?><div class="bp-day-block"><?= htmlspecialchars($arr_day) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bp-tear">
            <div class="bp-tear-dash"></div>
            <span class="bp-tear-scissors">✂</span>
            <div class="bp-tear-dash"></div>
        </div>

        <div class="bp-body">
            <div>
                <div class="bp-fields-grid">
                    <div class="bp-field"><div class="f-lbl">Passenger</div><div class="f-val"><?= htmlspecialchars($booking['passenger_name']) ?></div></div>
                    <div class="bp-field"><div class="f-lbl">Depart Date</div><div class="f-val"><?= date('d M Y',strtotime($booking['depart_date'])) ?></div></div>
                    <div class="bp-field"><div class="f-lbl">Adults</div><div class="f-val"><?= $booking['adults'] ?></div></div>
                    <div class="bp-field"><div class="f-lbl">Children</div><div class="f-val"><?= $booking['children'] ?: '—' ?></div></div>
                    <?php if ($dep_t): ?><div class="bp-field"><div class="f-lbl">Dep. Time</div><div class="f-val"><?= htmlspecialchars($dep_t) ?></div></div><?php endif; ?>
                    <?php if ($arr_t): ?><div class="bp-field"><div class="f-lbl">Arr. Time</div><div class="f-val"><?= htmlspecialchars($arr_t) ?></div></div><?php endif; ?>
                    <div class="bp-field"><div class="f-lbl">Class</div><div class="f-val"><?= htmlspecialchars($booking['class']) ?></div></div>
                    <div class="bp-field"><div class="f-lbl">Booked On</div><div class="f-val"><?= date('d M Y',strtotime($booking['booking_date'])) ?></div></div>
                </div>
                <div class="bp-rule"></div>
                <div class="bp-price-row">
                    <div>
                        <div class="price-label">Total Paid</div>
                        <div class="price-amount">$<?= number_format($booking['total_price'],0) ?></div>
                        <div class="price-per">$<?= number_format($booking['total_price']/max(1,$booking['adults']+$booking['children']),0) ?> per person</div>
                    </div>
                    <span class="status-badge s-<?= $booking['status']==='confirmed'?'confirmed':'cancelled' ?>">
                        <?= $booking['status']==='confirmed'?'<i class="fas fa-circle-check"></i> Confirmed':'<i class="fas fa-circle-xmark"></i> '.ucfirst($booking['status']) ?>
                    </span>
                </div>
            </div>
            <div class="bp-qr">
                <div class="qr-frame"><img src="<?= $qr_url ?>" alt="QR Code"></div>
                <div class="qr-label">Scan E-Ticket</div>
                <div class="qr-ref">#<?= $ref ?></div>
            </div>
        </div>

        <div class="bp-payment">
            <div class="pay-item"><div class="py-lbl">Payment Method</div><div class="py-val"><?= ucfirst($booking['payment_method']??'—') ?></div></div>
            <div class="pay-item"><div class="py-lbl">Card</div><div class="py-val">•••• <?= htmlspecialchars($booking['card_last4']??'——') ?></div></div>
            <div class="pay-item"><div class="py-lbl">Card Holder</div><div class="py-val"><?= htmlspecialchars($booking['card_holder']??'—') ?></div></div>
            <div class="pay-item" style="margin-left:auto"><div class="py-lbl">Amount Charged</div><div class="py-val" style="font-family:var(--mono);color:var(--navy-3);font-size:.95rem">$<?= number_format($booking['total_price'],0) ?></div></div>
        </div>
    </div>

    <!-- ══ RIGHT COLUMN ══ -->
    <div class="right-col">
        <div class="panel">
            <div class="journey-card">
                <div class="jc-route"><?= htmlspecialchars($booking['from_location']) ?><span class="jc-arrow">→</span><?= htmlspecialchars($booking['to_location']) ?></div>
                <div class="jc-chips">
                    <span class="jc-chip hi"><?= date('d M Y',strtotime($booking['depart_date'])) ?></span>
                    <span class="jc-chip"><?= htmlspecialchars($booking['class']) ?></span>
                    <span class="jc-chip"><?= ucfirst($booking['trip_type']) ?></span>
                    <span class="jc-chip"><?= $booking['adults'] ?> Adult<?= $booking['adults']>1?'s':'' ?></span>
                    <?php if ($booking['children']>0): ?><span class="jc-chip"><?= $booking['children'] ?> Child<?= $booking['children']>1?'ren':'' ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><span class="ph-dot"></span> Booking Info</div>
            <div class="panel-body">
                <div class="info-row"><span class="k">Reference</span><span class="v" style="font-family:var(--mono);color:var(--navy-3)">#<?= $ref ?></span></div>
                <div class="info-row"><span class="k">Booked On</span><span class="v"><?= date('d M Y',strtotime($booking['booking_date'])) ?></span></div>
                <div class="info-row"><span class="k">Status</span><span class="v" style="color:var(--<?= $booking['status']==='confirmed'?'green':'red' ?>)"><?= $booking['status']==='confirmed'?'✔ Confirmed':'✖ '.ucfirst($booking['status']) ?></span></div>
                <div class="info-row"><span class="k">Flight</span><span class="v"><?= htmlspecialchars($booking['flight_name']) ?></span></div>
                <div class="info-row"><span class="k">Code</span><span class="v" style="font-family:var(--mono)"><?= htmlspecialchars($booking['flight_code']) ?></span></div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><span class="ph-dot"></span> Actions</div>
            <div class="panel-body">
                <button onclick="window.print()" class="act-btn btn-navy"><i class="fas fa-print"></i> Print Ticket</button>
                <a href="myBookings.php" class="act-btn btn-outline"><i class="fas fa-list"></i> My Bookings</a>
                <a href="searchflights.php" class="act-btn btn-outline"><i class="fas fa-magnifying-glass"></i> Search More Flights</a>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.bp-field').forEach((f,i)=>{
        f.style.cssText=`opacity:0;transform:translateY(8px);transition:opacity .4s ${.3+i*.05}s ease,transform .4s ${.3+i*.05}s ease`;
        requestAnimationFrame(()=>{f.style.opacity='1';f.style.transform='none'});
    });
});
</script>
</body>
</html>
<?php include("../includes/footer.php"); ?>
