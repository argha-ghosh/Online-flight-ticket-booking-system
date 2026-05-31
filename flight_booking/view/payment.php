<?php
session_start();
require_once __DIR__ . "/../config/base_url.php";
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['flight_id'])) {
    header("Location: searchflights.php"); exit;
}

$flight_id = (int)$_POST['flight_id'];
$stmt = $conn->prepare("
    SELECT f.*,
           ROUND(f.price * (1 - f.discount_pct / 100), 2) AS final_price,
           s.departure_day, s.arrival_day,
           s.departure_time AS sched_dep_time,
           s.arrival_time   AS sched_arr_time
    FROM flights f
    LEFT JOIN schedule s ON s.flight_code COLLATE utf8mb4_unicode_ci = f.flight_code
    WHERE f.id = ?
");
$stmt->bind_param("i", $flight_id);
$stmt->execute();
$flight = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$flight) { header("Location: searchflights.php"); exit; }

$trip_type   = $_POST['trip_type']   ?? 'one-way';
$from        = $_POST['from']        ?? $flight['departure'];
$to          = $_POST['to']          ?? $flight['arrival'];
$depart_date = $_POST['depart_date'] ?? date('Y-m-d');
$adults      = max(1, (int)($_POST['adults']   ?? 1));
$children    = max(0, (int)($_POST['children'] ?? 0));
$class       = $_POST['class']       ?? 'Economy';

$unit_price  = (float)($flight['final_price'] ?? $flight['price']);
$total_price = round($unit_price * ($adults + $children), 2);
$error = '';

if (isset($_POST['pay_now'])) {
    $card_holder = trim($_POST['card_holder'] ?? '');
    $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $expiry      = trim($_POST['expiry'] ?? '');
    $cvv         = trim($_POST['cvv'] ?? '');
    $pay_method  = $_POST['pay_method'] ?? 'card';

    if (empty($card_holder))                              $error = "Cardholder name is required.";
    elseif (strlen($card_number) < 13)                   $error = "Enter a valid card number.";
    elseif (!preg_match('/^\d{2}\/\d{2}$/', $expiry))    $error = "Expiry must be MM/YY format.";
    elseif (strlen($cvv) < 3)                             $error = "CVV must be at least 3 digits.";
    else {
        $email  = $_SESSION['email'];
        $u_stmt = $conn->prepare("SELECT id FROM webusers WHERE email = ?");
        $u_stmt->bind_param("s", $email);
        $u_stmt->execute();
        $u_row    = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();
        $user_id  = $u_row['id'];
        $card_last4 = substr($card_number, -4);

        $ins = $conn->prepare("
            INSERT INTO bookings (user_id,flight_id,trip_type,from_location,to_location,depart_date,adults,children,class,total_price,payment_method,card_last4,card_holder,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'confirmed')
        ");
        $ins->bind_param("iissssiiissss",
            $user_id,$flight_id,$trip_type,$from,$to,
            $depart_date,$adults,$children,$class,
            $total_price,$pay_method,$card_last4,$card_holder
        );
        if ($ins->execute()) {
            $booking_id = $ins->insert_id;
            $ins->close();
            $conn->query("UPDATE flights SET seat = seat - ".($adults+$children)." WHERE id = $flight_id AND seat > 0");
            header("Location: booking_confirm.php?id=$booking_id"); exit;
        } else {
            $error = "Booking failed. Please try again.";
        }
    }
}

$dep_t   = substr(!empty($flight['sched_dep_time']) ? $flight['sched_dep_time'] : ($flight['departure_time'] ?? ''), 0, 5);
$arr_t   = substr(!empty($flight['sched_arr_time']) ? $flight['sched_arr_time'] : ($flight['arrival_time']   ?? ''), 0, 5);
$dep_day = $flight['departure_day'] ?? '';
$arr_day = $flight['arrival_day']   ?? '';
$class_icon = ['Economy'=>'🪑','Business'=>'💼','Premium'=>'✨'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Secure Payment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Cormorant: editorial display | Outfit: clean body | Bebas Neue: price numerals | IBM Plex Mono: codes & card -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600;700&family=Bebas+Neue&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
    /* ═══════════════════════════════════════════
       TOKENS
    ═══════════════════════════════════════════ */
    :root {
        --bg:        #07101c;
        --bg-2:      #0c1928;
        --bg-3:      #112236;
        --surface:   #0f1e30;
        --surface-2: #152540;
        --border:    #1c3050;
        --border-2:  #243d5e;
        --gold:      #c9922a;
        --gold-lt:   #e4b55a;
        --gold-dim:  #7a5518;
        --gold-glow: rgba(201,146,42,.22);
        --gold-tint: rgba(201,146,42,.08);
        --blue:      #3b82f6;
        --blue-tint: rgba(59,130,246,.08);
        --green:     #22c55e;
        --red:       #ef4444;
        --text:      #e8edf5;
        --text-soft: #7a93ad;
        --text-dim:  #3d5570;
        --white:     #ffffff;

        --font-display: 'Cormorant Garamond', Georgia, serif;
        --font-body:    'Outfit', sans-serif;
        --font-mono:    'IBM Plex Mono', monospace;
        --font-price:   'Bebas Neue', sans-serif;

        --radius:    12px;
        --radius-lg: 20px;
        --radius-xl: 28px;
        --shadow:    0 8px 40px rgba(0,0,0,.5);
        --shadow-gold: 0 8px 32px rgba(201,146,42,.25);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }

    body {
        font-family: var(--font-body);
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        /* FIX: push content below the fixed header */
        padding-top: 68px;
        background-image:
            radial-gradient(ellipse 80% 50% at 50% -10%, rgba(201,146,42,.06) 0%, transparent 60%),
            radial-gradient(ellipse 60% 40% at 80% 80%, rgba(59,130,246,.04) 0%, transparent 50%);
    }

    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-track { background: var(--bg-2); }
    ::-webkit-scrollbar-thumb { background: var(--border-2); border-radius: 3px; }

    /* ═══════════════════════════════════════════
       PAGE HERO BANNER
    ═══════════════════════════════════════════ */
    .pay-hero {
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 36px 28px 32px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .pay-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(135deg, rgba(201,146,42,.05) 0%, transparent 60%);
        pointer-events: none;
    }
    /* decorative dot grid */
    .pay-hero::after {
        content: '';
        position: absolute; inset: 0;
        background-image: radial-gradient(circle, rgba(201,146,42,.12) 1px, transparent 1px);
        background-size: 28px 28px;
        opacity: .35;
        pointer-events: none;
    }
    .pay-hero-inner { position: relative; z-index: 1; }
    .pay-hero-badge {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: var(--font-mono);
        font-size: .65rem; letter-spacing: .18em; text-transform: uppercase;
        color: var(--gold-lt);
        background: rgba(201,146,42,.1);
        border: 1px solid rgba(201,146,42,.2);
        padding: 5px 16px; border-radius: 20px;
        margin-bottom: 16px;
    }
    .pay-hero-badge i { font-size: .7rem; }
    .pay-hero h1 {
        font-family: var(--font-display);
        font-size: 2.4rem; font-weight: 600;
        color: var(--text); letter-spacing: -.02em;
        margin-bottom: 8px;
    }
    .pay-hero h1 em { font-style: italic; color: var(--gold-lt); }
    .pay-hero p { font-size: .9rem; color: var(--text-soft); }

    /* progress stepper */
    .stepper {
        display: flex; align-items: center; justify-content: center;
        gap: 0; margin-top: 24px;
    }
    .step {
        display: flex; align-items: center; gap: 8px;
        font-size: .75rem; font-weight: 600;
        color: var(--text-dim); letter-spacing: .02em;
    }
    .step.done  .step-dot { background: var(--green); border-color: var(--green); color: #fff; }
    .step.active .step-dot { background: var(--gold); border-color: var(--gold); color: #fff; box-shadow: 0 0 14px var(--gold-glow); }
    .step.done, .step.active { color: var(--text-soft); }
    .step.active { color: var(--gold-lt); }
    .step-dot {
        width: 28px; height: 28px; border-radius: 50%;
        border: 2px solid var(--border-2);
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; font-weight: 700;
        background: var(--bg-3);
        flex-shrink: 0;
        transition: all .3s;
    }
    .step-line { width: 48px; height: 1px; background: var(--border); margin: 0 4px; }

    /* ═══════════════════════════════════════════
       MAIN GRID
    ═══════════════════════════════════════════ */
    .pay-grid {
        max-width: 1060px; margin: 40px auto 80px;
        padding: 0 24px;
        display: grid;
        grid-template-columns: 1fr 420px;
        gap: 28px;
        align-items: start;
    }

    /* ═══════════════════════════════════════════
       SECTION CARD
    ═══════════════════════════════════════════ */
    .pay-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .pay-card-head {
        display: flex; align-items: center; gap: 12px;
        padding: 18px 24px;
        background: var(--surface-2);
        border-bottom: 1px solid var(--border);
    }
    .pch-icon {
        width: 36px; height: 36px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: .9rem; flex-shrink: 0;
    }
    .pch-icon.gold { background: var(--gold-tint); color: var(--gold); border: 1px solid rgba(201,146,42,.2); }
    .pch-icon.blue { background: var(--blue-tint); color: var(--blue); border: 1px solid rgba(59,130,246,.2); }
    .pay-card-head h2 { font-size: 1rem; font-weight: 700; color: var(--text); }
    .pay-card-head p  { font-size: .72rem; color: var(--text-soft); margin-top: 2px; }
    .pay-card-body { padding: 24px; }

    /* ═══════════════════════════════════════════
       SUMMARY PANEL (LEFT)
    ═══════════════════════════════════════════ */

    /* Flight image with gradient overlay */
    .flight-img-wrap {
        position: relative; border-radius: var(--radius);
        overflow: hidden; margin-bottom: 20px;
        height: 170px;
    }
    .flight-img-wrap img {
        width: 100%; height: 100%; object-fit: cover;
        display: block;
    }
    .flight-img-wrap .img-overlay {
        position: absolute; inset: 0;
        background: linear-gradient(to top, rgba(7,16,28,.9) 0%, transparent 55%);
    }
    .flight-img-placeholder {
        width: 100%; height: 170px;
        background: linear-gradient(135deg, var(--bg-3) 0%, var(--surface-2) 100%);
        display: flex; align-items: center; justify-content: center;
        font-size: 3.5rem; border-radius: var(--radius);
        margin-bottom: 20px; color: var(--gold-dim);
    }

    /* Route display */
    .route-bar {
        display: flex; align-items: center; justify-content: center;
        gap: 12px; margin-bottom: 22px;
        background: var(--bg-3);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px 20px;
    }
    .route-city {
        font-family: var(--font-display);
        font-size: 1.7rem; font-weight: 700;
        color: var(--text); letter-spacing: -.02em; line-height: 1;
        text-align: center;
    }
    .route-city small {
        display: block; font-size: .65rem;
        font-family: var(--font-mono);
        color: var(--text-soft); font-weight: 400;
        letter-spacing: .06em; text-transform: uppercase;
        margin-top: 3px;
    }
    .route-mid {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; gap: 4px;
    }
    .route-line {
        width: 100%; display: flex; align-items: center;
    }
    .route-line::before, .route-line::after {
        content: ''; flex: 1; height: 1px;
        background: var(--border-2);
    }
    .route-plane { color: var(--gold); font-size: 1rem; }
    .route-duration {
        font-family: var(--font-mono); font-size: .65rem;
        color: var(--text-soft); text-align: center;
    }

    /* Schedule times */
    .times-row {
        display: flex; gap: 10px; margin-bottom: 18px;
    }
    .time-block {
        flex: 1; background: var(--bg-3);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 10px 14px;
        border-top: 2px solid var(--gold);
        text-align: center;
    }
    .time-block .t-label {
        font-family: var(--font-mono);
        font-size: .6rem; letter-spacing: .1em;
        text-transform: uppercase; color: var(--text-dim);
        margin-bottom: 4px;
    }
    .time-block .t-val {
        font-family: var(--font-mono);
        font-size: 1.05rem; font-weight: 600;
        color: var(--gold-lt);
        font-variant-numeric: tabular-nums lnum;
    }
    .time-block .t-day {
        font-size: .65rem; color: var(--text-soft);
        margin-top: 2px;
    }

    /* Detail rows */
    .detail-rows { display: flex; flex-direction: column; gap: 1px; }
    .dr {
        display: flex; justify-content: space-between;
        align-items: center; padding: 9px 12px;
        border-radius: 8px;
        transition: background .15s;
    }
    .dr:hover { background: var(--bg-3); }
    .dr .dk {
        font-size: .75rem; color: var(--text-soft);
        display: flex; align-items: center; gap: 7px;
    }
    .dr .dk i { color: var(--gold-dim); font-size: .7rem; width: 14px; text-align: center; }
    .dr .dv {
        font-size: .82rem; font-weight: 600; color: var(--text);
        text-align: right;
    }
    .dr .dv.mono {
        font-family: var(--font-mono);
        font-variant-numeric: tabular-nums lnum;
        font-size: .78rem;
    }

    /* class badge */
    .class-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 20px;
        font-size: .72rem; font-weight: 700;
        border: 1px solid;
    }
    .class-economy { background: rgba(59,130,246,.08); color: #93c5fd; border-color: rgba(59,130,246,.2); }
    .class-business{ background: rgba(201,146,42,.08); color: var(--gold-lt); border-color: rgba(201,146,42,.2); }
    .class-premium { background: linear-gradient(90deg,rgba(201,146,42,.12),rgba(168,85,247,.1)); color: #e9c46a; border-color: rgba(201,146,42,.3); }

    /* Divider */
    .divider {
        height: 1px; background: var(--border);
        margin: 16px 0;
    }

    /* Price total */
    .price-total-box {
        background: var(--bg-3);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px 20px;
        display: flex; align-items: center; justify-content: space-between;
        border-left: 3px solid var(--gold);
    }
    .ptb-label { font-size: .72rem; color: var(--text-soft); margin-bottom: 4px; font-family: var(--font-mono); text-transform: uppercase; letter-spacing: .1em; }
    .ptb-price {
        font-family: var(--font-price);
        font-size: 3rem; line-height: 1;
        color: var(--gold-lt);
        letter-spacing: .04em;
        font-variant-numeric: tabular-nums lnum;
    }
    .ptb-currency {
        font-family: var(--font-price);
        font-size: 1.6rem; color: var(--gold);
        align-self: flex-start; margin-top: 4px;
        letter-spacing: .04em;
    }
    .ptb-right { text-align: right; }
    .ptb-per {
        font-family: var(--font-mono);
        font-size: .68rem; color: var(--text-dim);
        font-variant-numeric: tabular-nums;
    }
    .ptb-discount-row { display: flex; align-items: center; gap: 6px; justify-content: flex-end; margin-top: 4px; }
    .ptb-orig { font-family: var(--font-mono); font-size: .72rem; text-decoration: line-through; color: var(--text-dim); font-variant-numeric: tabular-nums; }
    .ptb-pct  { font-family: var(--font-mono); font-size: .72rem; font-weight: 600; color: var(--green); background: rgba(34,197,94,.1); padding: 1px 6px; border-radius: 4px; }

    /* ═══════════════════════════════════════════
       PAYMENT FORM (RIGHT)
    ═══════════════════════════════════════════ */

    /* Method tabs */
    .method-tabs { display: flex; gap: 8px; margin-bottom: 22px; }
    .mtab {
        flex: 1; padding: 10px 6px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        text-align: center; cursor: pointer;
        transition: all .2s; user-select: none;
        background: var(--bg-3);
    }
    .mtab .mt-icon { font-size: 1.1rem; display: block; margin-bottom: 4px; }
    .mtab .mt-label { font-size: .72rem; font-weight: 600; color: var(--text-soft); display: block; }
    .mtab:hover { border-color: var(--border-2); }
    .mtab.active {
        border-color: var(--gold);
        background: var(--gold-tint);
        box-shadow: 0 0 0 1px rgba(201,146,42,.15), inset 0 1px 0 rgba(255,255,255,.04);
    }
    .mtab.active .mt-label { color: var(--gold-lt); }
    .mtab.bkash.active  { border-color: #e2136e; background: rgba(226,19,110,.07); }
    .mtab.bkash.active .mt-label { color: #f27db8; }
    .mtab.nagad.active  { border-color: #f55a00; background: rgba(245,90,0,.07); }
    .mtab.nagad.active  .mt-label { color: #ffa06a; }

    /* ─── CREDIT CARD VISUAL ─── */
    .card-3d {
        perspective: 1000px;
        height: 190px;
        margin-bottom: 22px;
        cursor: pointer;
    }
    .card-inner {
        width: 100%; height: 100%;
        position: relative;
        transform-style: preserve-3d;
        transition: transform .6s cubic-bezier(.4,0,.2,1);
        border-radius: 18px;
    }
    .card-3d.flipped .card-inner { transform: rotateY(180deg); }

    .card-face {
        position: absolute; inset: 0;
        border-radius: 18px;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        overflow: hidden;
        box-shadow: var(--shadow-gold), 0 2px 0 rgba(255,255,255,.06) inset;
    }
    .card-front { background: linear-gradient(135deg, #0f2540 0%, #1a3d6e 50%, #0d3060 100%); }
    .card-back  {
        background: linear-gradient(135deg, #0f2540 0%, #1a3d6e 50%, #0d3060 100%);
        transform: rotateY(180deg);
    }

    /* card shine overlay */
    .card-face::before {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,.08) 0%, transparent 50%, rgba(201,146,42,.06) 100%);
        pointer-events: none;
    }
    /* decorative circle */
    .card-face .deco-circle {
        position: absolute; top: -30px; right: -30px;
        width: 140px; height: 140px; border-radius: 50%;
        background: rgba(201,146,42,.12);
        pointer-events: none;
    }
    .card-face .deco-circle-2 {
        position: absolute; bottom: -40px; right: 30px;
        width: 180px; height: 180px; border-radius: 50%;
        background: rgba(59,130,246,.06);
        pointer-events: none;
    }

    /* Front elements */
    .cf-top { padding: 18px 22px 0; display: flex; justify-content: space-between; align-items: flex-start; }
    .cf-chip {
        width: 38px; height: 28px;
        background: linear-gradient(135deg, #c9922a 0%, #e4b55a 50%, #a37420 100%);
        border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: .55rem; color: rgba(0,0,0,.4); font-weight: 700;
    }
    .cf-network { font-size: 1.4rem; color: rgba(255,255,255,.7); }
    .cf-number {
        padding: 14px 22px 0;
        font-family: var(--font-mono);
        font-size: .95rem; letter-spacing: .22em;
        color: rgba(255,255,255,.85);
        font-variant-numeric: tabular-nums lnum;
        transition: all .2s;
    }
    .cf-bottom { padding: 12px 22px 18px; display: flex; justify-content: space-between; align-items: flex-end; }
    .cf-field { }
    .cf-field-label { font-family: var(--font-mono); font-size: .52rem; letter-spacing: .12em; text-transform: uppercase; color: rgba(255,255,255,.4); margin-bottom: 3px; }
    .cf-field-val { font-size: .82rem; font-weight: 600; color: rgba(255,255,255,.9); transition: all .2s; font-family: var(--font-mono); letter-spacing: .04em; }

    /* Back elements */
    .cb-stripe { background: rgba(0,0,0,.7); height: 44px; margin-top: 26px; }
    .cb-sig { margin: 14px 22px 0; display: flex; align-items: center; gap: 12px; }
    .cb-sig-strip {
        flex: 1; height: 36px;
        background: repeating-linear-gradient(90deg, #e8e8e8 0px, #e8e8e8 30px, #d0d0d0 30px, #d0d0d0 32px);
        border-radius: 4px;
        display: flex; align-items: center; justify-content: flex-end;
        padding-right: 8px;
    }
    .cb-sig-strip .cvv-display {
        font-family: var(--font-mono); font-size: .85rem;
        font-style: italic; color: #333; font-weight: 600;
        font-variant-numeric: tabular-nums;
        transition: all .2s;
    }
    .cb-note { margin: 12px 22px 0; font-family: var(--font-mono); font-size: .58rem; color: rgba(255,255,255,.35); letter-spacing: .03em; }
    .cb-logo { margin: 0 22px; text-align: right; font-size: .7rem; color: rgba(255,255,255,.4); font-style: italic; margin-top: 8px; }

    /* flip hint */
    .flip-hint { text-align: center; font-size: .68rem; color: var(--text-dim); margin-top: -10px; margin-bottom: 16px; font-family: var(--font-mono); }

    /* ─── FORM FIELDS ─── */
    .fg { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
    .fg label {
        font-family: var(--font-mono);
        font-size: .62rem; letter-spacing: .12em; text-transform: uppercase;
        color: var(--text-soft);
        display: flex; align-items: center; gap: 6px;
    }
    .fg label i { color: var(--gold-dim); font-size: .65rem; }
    .fg input, .fg select {
        padding: 12px 14px;
        background: var(--bg-3);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        font-family: var(--font-body);
        font-size: .9rem; color: var(--text);
        transition: border-color .2s, box-shadow .2s, background .2s;
        width: 100%;
    }
    .fg input::placeholder { color: var(--text-dim); font-size: .82rem; }
    .fg input:focus, .fg select:focus {
        outline: none;
        border-color: var(--gold);
        background: var(--surface);
        box-shadow: 0 0 0 3px rgba(201,146,42,.15);
    }
    .fg input.mono-input {
        font-family: var(--font-mono);
        letter-spacing: .06em;
        font-variant-numeric: tabular-nums lnum;
    }
    .fg-row { display: flex; gap: 10px; }
    .fg-row .fg { flex: 1; }

    /* error alert */
    .error-alert {
        display: flex; align-items: flex-start; gap: 10px;
        background: rgba(239,68,68,.08);
        border: 1px solid rgba(239,68,68,.25);
        border-radius: var(--radius-sm);
        padding: 12px 14px; margin-bottom: 16px;
        font-size: .85rem; color: #fca5a5;
        animation: slideIn .3s ease;
    }
    @keyframes slideIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
    .error-alert i { flex-shrink: 0; margin-top: 1px; }

    /* pay button */
    .pay-btn {
        width: 100%; padding: 15px;
        background: linear-gradient(135deg, var(--gold) 0%, #a07020 100%);
        color: #fff; border: none;
        border-radius: var(--radius);
        font-family: var(--font-body);
        font-size: 1rem; font-weight: 700;
        cursor: pointer; transition: all .25s;
        display: flex; align-items: center; justify-content: center; gap: 10px;
        box-shadow: var(--shadow-gold);
        margin-top: 6px; letter-spacing: .02em;
    }
    .pay-btn .btn-price {
        font-family: var(--font-price);
        font-size: 1.3rem; letter-spacing: .06em;
        font-variant-numeric: tabular-nums lnum;
    }
    .pay-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(201,146,42,.35);
        filter: brightness(1.1);
    }
    .pay-btn:active { transform: translateY(0); }

    /* secure badge */
    .secure-row {
        display: flex; align-items: center; justify-content: center; gap: 18px;
        margin-top: 14px; flex-wrap: wrap;
    }
    .secure-item {
        display: flex; align-items: center; gap: 5px;
        font-size: .7rem; color: var(--text-dim);
        font-family: var(--font-mono);
    }
    .secure-item i { color: var(--green); font-size: .7rem; }

    /* ═══════════════════════════════════════════
       RESPONSIVE
    ═══════════════════════════════════════════ */
    @media (max-width: 780px) {
        .pay-grid { grid-template-columns: 1fr; }
        body { padding-top: 60px; }
        .pay-hero h1 { font-size: 1.8rem; }
        .ptb-price { font-size: 2.4rem; }
    }
    @media (max-width: 480px) {
        .times-row { flex-direction: column; }
        .stepper { gap: 0; }
        .step-line { width: 24px; }
        .pay-grid { padding: 0 16px; }
    }
    </style>
</head>
<body>
<?php include("../includes/header.php"); ?>

<!-- ── HERO ── -->
<div class="pay-hero">
    <div class="pay-hero-inner">
        <div class="pay-hero-badge">
            <i class="fas fa-lock"></i>
            Encrypted &amp; Secure Checkout
        </div>
        <h1>Complete Your <em>Booking</em></h1>
        <p><?= htmlspecialchars($flight['flight_name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?></p>

        <!-- Progress stepper -->
        <div class="stepper">
            <div class="step done">
                <div class="step-dot"><i class="fas fa-check" style="font-size:.6rem"></i></div>
                Search
            </div>
            <div class="step-line"></div>
            <div class="step done">
                <div class="step-dot"><i class="fas fa-check" style="font-size:.6rem"></i></div>
                Select
            </div>
            <div class="step-line"></div>
            <div class="step active">
                <div class="step-dot">3</div>
                Payment
            </div>
            <div class="step-line"></div>
            <div class="step">
                <div class="step-dot">4</div>
                Confirm
            </div>
        </div>
    </div>
</div>

<form action="" method="POST" id="payForm">
    <!-- Carry booking data -->
    <input type="hidden" name="flight_id"   value="<?= $flight_id ?>">
    <input type="hidden" name="trip_type"   value="<?= htmlspecialchars($trip_type) ?>">
    <input type="hidden" name="from"        value="<?= htmlspecialchars($from) ?>">
    <input type="hidden" name="to"          value="<?= htmlspecialchars($to) ?>">
    <input type="hidden" name="depart_date" value="<?= htmlspecialchars($depart_date) ?>">
    <input type="hidden" name="adults"      value="<?= $adults ?>">
    <input type="hidden" name="children"    value="<?= $children ?>">
    <input type="hidden" name="class"       value="<?= htmlspecialchars($class) ?>">
    <input type="hidden" name="pay_method" id="pay_method_input" value="card">

    <div class="pay-grid">

        <!-- ══════════════ LEFT: SUMMARY ══════════════ -->
        <div class="pay-card">
            <div class="pay-card-head">
                <div class="pch-icon gold"><i class="fas fa-plane-departure"></i></div>
                <div>
                    <h2>Booking Summary</h2>
                    <p>Review your flight details before paying</p>
                </div>
            </div>
            <div class="pay-card-body">

                <?php if (!empty($flight['image'])): ?>
                <div class="flight-img-wrap">
                    <img src="upload/<?= htmlspecialchars($flight['image']) ?>" alt="Flight">
                    <div class="img-overlay"></div>
                </div>
                <?php else: ?>
                <div class="flight-img-placeholder">✈</div>
                <?php endif; ?>

                <!-- Route bar -->
                <div class="route-bar">
                    <div class="route-city">
                        <?= strtoupper(substr($from, 0, 3)) ?>
                        <small><?= htmlspecialchars($from) ?></small>
                    </div>
                    <div class="route-mid">
                        <div class="route-line">
                            <span class="route-plane">✈</span>
                        </div>
                        <div class="route-duration"><?= htmlspecialchars($flight['duration']) ?></div>
                    </div>
                    <div class="route-city">
                        <?= strtoupper(substr($to, 0, 3)) ?>
                        <small><?= htmlspecialchars($to) ?></small>
                    </div>
                </div>

                <!-- Schedule times -->
                <?php if ($dep_t || $arr_t): ?>
                <div class="times-row">
                    <?php if ($dep_t): ?>
                    <div class="time-block">
                        <div class="t-label"><i class="fas fa-plane-departure" style="margin-right:4px;color:var(--gold-dim)"></i>Departure</div>
                        <div class="t-val"><?= htmlspecialchars($dep_t) ?></div>
                        <?php if ($dep_day): ?><div class="t-day"><?= htmlspecialchars($dep_day) ?></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($arr_t): ?>
                    <div class="time-block">
                        <div class="t-label"><i class="fas fa-plane-arrival" style="margin-right:4px;color:var(--gold-dim)"></i>Arrival</div>
                        <div class="t-val"><?= htmlspecialchars($arr_t) ?></div>
                        <?php if ($arr_day): ?><div class="t-day"><?= htmlspecialchars($arr_day) ?></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="detail-rows">
                    <div class="dr">
                        <span class="dk"><i class="fas fa-plane"></i> Flight</span>
                        <span class="dv"><?= htmlspecialchars($flight['flight_name']) ?></span>
                    </div>
                    <div class="dr">
                        <span class="dk"><i class="fas fa-building"></i> Airline</span>
                        <span class="dv"><?= htmlspecialchars($flight['airline_name']) ?> <span style="color:var(--text-soft);font-size:.75rem">(<?= htmlspecialchars($flight['flight_code']) ?>)</span></span>
                    </div>
                    <div class="dr">
                        <span class="dk"><i class="fas fa-calendar"></i> Date</span>
                        <span class="dv mono"><?= date('d M Y', strtotime($depart_date)) ?></span>
                    </div>
                    <div class="dr">
                        <span class="dk"><i class="fas fa-rotate"></i> Trip</span>
                        <span class="dv"><?= ucfirst($trip_type) ?></span>
                    </div>
                    <div class="dr">
                        <span class="dk"><i class="fas fa-users"></i> Passengers</span>
                        <span class="dv">
                            <?= $adults ?> Adult<?= $adults>1?'s':'' ?>
                            <?= $children>0 ? ", $children Child".($children>1?'ren':'') : '' ?>
                        </span>
                    </div>
                    <div class="dr">
                        <span class="dk"><i class="fas fa-chair"></i> Class</span>
                        <span class="dv">
                            <?php $cls_l = strtolower($class); ?>
                            <span class="class-badge class-<?= $cls_l ?>">
                                <?= $class_icon[$class] ?? '💺' ?> <?= htmlspecialchars($class) ?>
                            </span>
                        </span>
                    </div>
                    <div class="dr">
                        <span class="dk"><i class="fas fa-dollar-sign"></i> Per person</span>
                        <span class="dv mono">
                            $<?= number_format($unit_price, 2) ?>
                            <?php if (($flight['discount_pct'] ?? 0) > 0): ?>
                            <span style="text-decoration:line-through;color:var(--text-dim);margin-left:6px;font-size:.72rem">$<?= number_format($flight['price'], 2) ?></span>
                            <span style="color:var(--green);font-size:.72rem;margin-left:3px">-<?= (int)$flight['discount_pct'] ?>%</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Total price -->
                <div class="price-total-box">
                    <div>
                        <div class="ptb-label">Total Amount</div>
                        <div style="display:flex;align-items:baseline;gap:2px">
                            <span class="ptb-currency">$</span>
                            <span class="ptb-price"><?= number_format($total_price, 2) ?></span>
                        </div>
                    </div>
                    <div class="ptb-right">
                        <div class="ptb-per">$<?= number_format($unit_price,2) ?> × <?= $adults+$children ?> pax</div>
                        <?php if (($flight['discount_pct'] ?? 0) > 0): ?>
                        <div class="ptb-discount-row">
                            <span class="ptb-orig">$<?= number_format($flight['price']*($adults+$children),2) ?></span>
                            <span class="ptb-pct">-<?= (int)$flight['discount_pct'] ?>% OFF</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- ══════════════ RIGHT: PAYMENT FORM ══════════════ -->
        <div class="pay-card">
            <div class="pay-card-head">
                <div class="pch-icon blue"><i class="fas fa-credit-card"></i></div>
                <div>
                    <h2>Payment Details</h2>
                    <p>All transactions are SSL encrypted</p>
                </div>
            </div>
            <div class="pay-card-body">

                <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <!-- Method tabs -->
                <div class="method-tabs">
                    <div class="mtab active" data-method="card" onclick="setMethod('card',this)">
                        <span class="mt-icon">💳</span>
                        <span class="mt-label">Card</span>
                    </div>
                    <div class="mtab bkash" data-method="bkash" onclick="setMethod('bkash',this)">
                        <span class="mt-icon">🟣</span>
                        <span class="mt-label">bKash</span>
                    </div>
                    <div class="mtab nagad" data-method="nagad" onclick="setMethod('nagad',this)">
                        <span class="mt-icon">🔴</span>
                        <span class="mt-label">Nagad</span>
                    </div>
                </div>

                <!-- 3D Credit Card -->
                <div class="card-3d" id="card3d" onclick="flipCard()">
                    <div class="card-inner" id="cardInner">

                        <!-- FRONT -->
                        <div class="card-face card-front" id="cardFront">
                            <div class="deco-circle"></div>
                            <div class="deco-circle-2"></div>
                            <div class="cf-top">
                                <div class="cf-chip">■■<br>■■</div>
                                <div class="cf-network" id="cardNetwork">VISA</div>
                            </div>
                            <div class="cf-number" id="cardDisplay">•••• &nbsp;•••• &nbsp;•••• &nbsp;••••</div>
                            <div class="cf-bottom">
                                <div class="cf-field">
                                    <div class="cf-field-label">Card Holder</div>
                                    <div class="cf-field-val" id="cardNameDisplay">YOUR NAME</div>
                                </div>
                                <div class="cf-field">
                                    <div class="cf-field-label">Expires</div>
                                    <div class="cf-field-val" id="cardExpiryDisplay">MM / YY</div>
                                </div>
                            </div>
                        </div>

                        <!-- BACK -->
                        <div class="card-face card-back">
                            <div class="deco-circle"></div>
                            <div class="cb-stripe"></div>
                            <div class="cb-sig">
                                <div class="cb-sig-strip">
                                    <span class="cvv-display" id="cvvDisplay">•••</span>
                                </div>
                            </div>
                            <div class="cb-note">This card is property of GoZayan Financial Services. If found please return to nearest branch.</div>
                            <div class="cb-logo">GoZayan™</div>
                        </div>
                    </div>
                </div>
                <div class="flip-hint">Click card to see CVV ↗</div>

                <!-- Form fields -->
                <div class="fg">
                    <label><i class="fas fa-user"></i> Cardholder Name</label>
                    <input type="text" name="card_holder" id="card_holder"
                           placeholder="As printed on card"
                           value="<?= htmlspecialchars($_POST['card_holder'] ?? '') ?>"
                           oninput="updateCard()">
                </div>

                <div class="fg">
                    <label><i class="fas fa-credit-card"></i> Card Number</label>
                    <input type="text" name="card_number" id="card_number"
                           class="mono-input"
                           placeholder="0000  0000  0000  0000"
                           maxlength="19" oninput="formatCard(this)">
                </div>

                <div class="fg-row">
                    <div class="fg">
                        <label><i class="fas fa-calendar-days"></i> Expiry</label>
                        <input type="text" name="expiry" id="expiry"
                               class="mono-input"
                               placeholder="MM / YY" maxlength="5"
                               oninput="formatExpiry(this)"
                               value="<?= htmlspecialchars($_POST['expiry'] ?? '') ?>">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-lock"></i> CVV</label>
                        <input type="password" name="cvv" id="cvv"
                               class="mono-input"
                               placeholder="•••" maxlength="4"
                               oninput="updateCvv(this)"
                               onfocus="flipCard(true)"
                               onblur="flipCard(false)">
                    </div>
                </div>

                <button type="submit" name="pay_now" class="pay-btn">
                    <i class="fas fa-lock"></i>
                    <span>Pay Now</span>
                    <span class="btn-price">$<?= number_format($total_price, 2) ?></span>
                </button>

                <div class="secure-row">
                    <div class="secure-item"><i class="fas fa-shield-halved"></i> SSL Encrypted</div>
                    <div class="secure-item"><i class="fas fa-check-circle"></i> PCI Compliant</div>
                    <div class="secure-item"><i class="fas fa-lock"></i> Secure Checkout</div>
                </div>

            </div>
        </div>

    </div>
</form>

<script>
/* ── CARD FLIP ── */
let isFlipped = false;
function flipCard(forceTo) {
    const el = document.getElementById('card3d');
    if (forceTo === true)  { isFlipped = true;  el.classList.add('flipped'); return; }
    if (forceTo === false) { isFlipped = false; el.classList.remove('flipped'); return; }
    isFlipped = !isFlipped;
    el.classList.toggle('flipped', isFlipped);
}

/* ── FORMAT CARD NUMBER ── */
function formatCard(input) {
    let raw = input.value.replace(/\D/g, '').substring(0, 16);
    input.value = raw.replace(/(.{4})/g, '$1 ').trim();
    // detect network
    const net = document.getElementById('cardNetwork');
    if (raw[0] === '4')           net.textContent = 'VISA';
    else if (/^5[1-5]/.test(raw)) net.textContent = 'MC';
    else if (/^3[47]/.test(raw))  net.textContent = 'AMEX';
    else                          net.textContent = 'CARD';
    // update display
    let display = raw.padEnd(16, '•');
    document.getElementById('cardDisplay').innerHTML =
        display.substring(0,4) + ' &nbsp;' +
        display.substring(4,8) + ' &nbsp;' +
        display.substring(8,12) + ' &nbsp;' +
        display.substring(12,16);
}

/* ── FORMAT EXPIRY ── */
function formatExpiry(input) {
    let raw = input.value.replace(/\D/g, '').substring(0, 4);
    if (raw.length >= 3) raw = raw.substring(0,2) + '/' + raw.substring(2);
    input.value = raw;
    document.getElementById('cardExpiryDisplay').textContent = raw || 'MM / YY';
}

/* ── UPDATE CVV ── */
function updateCvv(input) {
    const val = input.value.replace(/\D/g, '').substring(0, 4);
    document.getElementById('cvvDisplay').textContent = '•'.repeat(val.length) || '•••';
}

/* ── UPDATE NAME ── */
function updateCard() {
    const name = document.getElementById('card_holder').value;
    document.getElementById('cardNameDisplay').textContent = name.toUpperCase() || 'YOUR NAME';
}

/* ── SET PAYMENT METHOD ── */
function setMethod(method, tab) {
    document.querySelectorAll('.mtab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('pay_method_input').value = method;

    const front = document.getElementById('cardFront');
    const back  = document.querySelector('.card-back');
    const themes = {
        card:  'linear-gradient(135deg, #0f2540 0%, #1a3d6e 50%, #0d3060 100%)',
        bkash: 'linear-gradient(135deg, #3d0025 0%, #8a0848 50%, #c0106e 100%)',
        nagad: 'linear-gradient(135deg, #2d1400 0%, #7a3000 50%, #c94400 100%)',
    };
    front.style.background = themes[method] || themes.card;
    back.style.background  = themes[method] || themes.card;
}

/* ── MIN DATE ── */
document.addEventListener('DOMContentLoaded', () => {
    const cardNum = document.getElementById('card_number');
    if (cardNum && '<?= htmlspecialchars($_POST['card_number'] ?? '') ?>') formatCard(cardNum);
});
</script>

<?php include("../includes/footer.php"); ?>