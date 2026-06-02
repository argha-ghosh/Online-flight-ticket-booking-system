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
    $pay_method  = $_POST['pay_method'] ?? 'card';

    if ($pay_method === 'bkash' || $pay_method === 'nagad') {
        // Mobile banking flow
        $mobile_num  = preg_replace('/\D/', '', $_POST['mobile_number'] ?? '');
        $otp_entered = trim($_POST['otp_code'] ?? '');
        $trx_id      = strtoupper(trim($_POST['trx_id'] ?? ''));

        // OTP verification check
        $otp_verified = isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true;
        $otp_mobile_match = isset($_SESSION['otp_mobile']) && $_SESSION['otp_mobile'] === $mobile_num;
        $otp_fresh = isset($_SESSION['otp_time']) && (time() - $_SESSION['otp_time']) < 600; // 10 min validity

        if (strlen($mobile_num) !== 11 || !str_starts_with($mobile_num, '0')) {
            $error = "Please enter a valid 11-digit mobile number.";
        } elseif (!$otp_verified || !$otp_mobile_match || !$otp_fresh) {
            $error = "Please verify your mobile number with OTP before payment.";
        } elseif (empty($trx_id)) {
            $error = "Transaction ID is required.";
        } else {
            $card_holder = $_SESSION['email'];
            $card_last4  = substr($mobile_num, -4);
            $email       = $_SESSION['email'];
            $u_stmt = $conn->prepare("SELECT id FROM webusers WHERE email = ?");
            $u_stmt->bind_param("s", $email); $u_stmt->execute();
            $u_row = $u_stmt->get_result()->fetch_assoc(); $u_stmt->close();
            $user_id = $u_row['id'];

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
                $booking_id = $ins->insert_id; $ins->close();
                $conn->query("UPDATE flights SET seat = seat - ".($adults+$children)." WHERE id = $flight_id AND seat > 0");
                // Clear OTP session after successful payment
                unset($_SESSION['otp_verified'], $_SESSION['otp_mobile'], $_SESSION['otp_time']);
                header("Location: booking_confirm.php?id=$booking_id"); exit;
            } else {
                $error = "Booking failed. Please try again.";
            }
        }
    } else {
        // Card flow
        $card_holder = trim($_POST['card_holder'] ?? '');
        $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $expiry      = trim($_POST['expiry'] ?? '');
        $cvv         = trim($_POST['cvv'] ?? '');

        if (empty($card_holder))                              $error = "Cardholder name is required.";
        elseif (strlen($card_number) < 13)                   $error = "Enter a valid card number.";
        elseif (!preg_match('/^\d{2}\/\d{2}$/', $expiry))    $error = "Expiry must be MM/YY format.";
        elseif (strlen($cvv) < 3)                             $error = "CVV must be at least 3 digits.";
        else {
            $email  = $_SESSION['email'];
            $u_stmt = $conn->prepare("SELECT id FROM webusers WHERE email = ?");
            $u_stmt->bind_param("s", $email); $u_stmt->execute();
            $u_row    = $u_stmt->get_result()->fetch_assoc(); $u_stmt->close();
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
                $booking_id = $ins->insert_id; $ins->close();
                $conn->query("UPDATE flights SET seat = seat - ".($adults+$children)." WHERE id = $flight_id AND seat > 0");
                header("Location: booking_confirm.php?id=$booking_id"); exit;
            } else {
                $error = "Booking failed. Please try again.";
            }
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
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
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

    /* ─── MOBILE BANKING PANEL ─── */
    .mfs-panel { display: none; }
    .mfs-panel.active { display: block; animation: slideIn .3s ease; }

    /* bKash / Nagad brand header */
    .mfs-brand {
        border-radius: var(--radius);
        padding: 20px;
        text-align: center;
        margin-bottom: 18px;
        position: relative;
        overflow: hidden;
    }
    .mfs-brand.bkash-brand {
        background: linear-gradient(135deg, #e2136e 0%, #9c0a4e 100%);
    }
    .mfs-brand.nagad-brand {
        background: linear-gradient(135deg, #f55a00 0%, #a83b00 100%);
    }
    .mfs-brand::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(circle at 80% 20%, rgba(255,255,255,.15), transparent 60%);
    }
    .mfs-logo {
        font-size: 2.2rem;
        display: block;
        margin-bottom: 6px;
    }
    .mfs-brand-name {
        font-size: 1.4rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -.01em;
    }
    .mfs-brand-sub {
        font-size: .72rem;
        color: rgba(255,255,255,.75);
        margin-top: 2px;
    }

    /* Steps indicator */
    .mfs-steps {
        display: flex;
        gap: 0;
        margin-bottom: 20px;
        background: var(--bg-3);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
    }
    .mfs-step {
        flex: 1;
        padding: 10px 6px;
        text-align: center;
        font-size: .65rem;
        font-weight: 700;
        color: var(--text-dim);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    .mfs-step:last-child { border-right: none; }
    .mfs-step .ms-num {
        width: 22px; height: 22px;
        border-radius: 50%;
        background: var(--border);
        color: var(--text-dim);
        font-size: .7rem;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }
    .mfs-step.done .ms-num { background: var(--green); color: #fff; }
    .mfs-step.active .ms-num { background: var(--gold); color: #fff; }
    .mfs-step.active { color: var(--gold-lt); }
    .mfs-step.done   { color: var(--green); }

    /* Instruction box */
    .mfs-instruction {
        background: var(--bg-3);
        border: 1px solid var(--border);
        border-left: 3px solid var(--gold);
        border-radius: var(--radius-sm);
        padding: 14px 16px;
        margin-bottom: 18px;
        font-size: .82rem;
        color: var(--text-soft);
        line-height: 1.6;
    }
    .mfs-instruction strong { color: var(--gold-lt); }
    .mfs-instruction .mfs-number {
        font-family: var(--font-mono);
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text);
        display: block;
        margin: 6px 0 4px;
        letter-spacing: .06em;
    }

    /* amount chip */
    .mfs-amount-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(201,146,42,.1);
        border: 1px solid rgba(201,146,42,.25);
        border-radius: var(--radius);
        padding: 12px 18px;
        margin-bottom: 18px;
        width: 100%;
    }
    .mfs-amount-chip .mac-label {
        font-size: .7rem;
        color: var(--text-soft);
        font-family: var(--font-mono);
        text-transform: uppercase;
        letter-spacing: .1em;
    }
    .mfs-amount-chip .mac-val {
        font-family: var(--font-price);
        font-size: 1.6rem;
        color: var(--gold-lt);
        letter-spacing: .04em;
        margin-left: auto;
    }

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
       OTP VERIFICATION SECTION
    ═══════════════════════════════════════════ */

    /* OTP wrapper area */
    .otp-section {
        margin-top: 14px;
        animation: otpSlideIn .4s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes otpSlideIn {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* "Send OTP" button */
    .otp-send-btn {
        width: 100%;
        padding: 12px 16px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-family: var(--font-body);
        font-size: .88rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        letter-spacing: .02em;
        transition: all .25s;
        box-shadow: 0 6px 22px rgba(16,185,129,.3);
        position: relative;
        overflow: hidden;
    }
    .otp-send-btn::before {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.15), transparent);
        transform: translateX(-100%);
        transition: transform .5s;
    }
    .otp-send-btn:hover::before { transform: translateX(100%); }
    .otp-send-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(16,185,129,.4);
    }
    .otp-send-btn:active { transform: translateY(0); }
    .otp-send-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }

    /* Pulse animation for Send OTP */
    .otp-send-btn.pulse {
        animation: btnPulse 1.5s ease infinite;
    }
    @keyframes btnPulse {
        0%, 100% { box-shadow: 0 6px 22px rgba(16,185,129,.3); }
        50% { box-shadow: 0 6px 30px rgba(16,185,129,.6); }
    }

    /* OTP input area (6 digit boxes) */
    .otp-verify-area {
        display: none;
        animation: otpSlideIn .4s cubic-bezier(.22,1,.36,1) both;
    }
    .otp-verify-area.active { display: block; }

    .otp-header {
        text-align: center;
        margin-bottom: 16px;
    }
    .otp-header .otp-title {
        font-size: .9rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 4px;
    }
    .otp-header .otp-subtitle {
        font-size: .75rem;
        color: var(--text-soft);
        font-family: var(--font-mono);
    }
    .otp-header .otp-mobile-display {
        color: var(--gold-lt);
        font-weight: 600;
    }

    /* 6 digit boxes */
    .otp-digit-row {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin-bottom: 14px;
    }
    .otp-digit {
        width: 48px;
        height: 56px;
        text-align: center;
        font-family: var(--font-mono);
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text);
        background: var(--bg-3);
        border: 2px solid var(--border);
        border-radius: 12px;
        outline: none;
        transition: all .2s;
        caret-color: var(--gold);
    }
    .otp-digit:focus {
        border-color: var(--gold);
        background: var(--surface);
        box-shadow: 0 0 0 4px rgba(201,146,42,.18);
        transform: scale(1.05);
    }
    .otp-digit.filled {
        border-color: var(--gold-lt);
        background: rgba(201,146,42,.06);
    }
    .otp-digit.error {
        border-color: var(--red);
        animation: otpShake .4s ease;
    }
    .otp-digit.success {
        border-color: var(--green);
        background: rgba(34,197,94,.08);
    }

    @keyframes otpShake {
        0%, 100% { transform: translateX(0); }
        20% { transform: translateX(-6px); }
        40% { transform: translateX(6px); }
        60% { transform: translateX(-4px); }
        80% { transform: translateX(4px); }
    }

    /* Countdown timer */
    .otp-timer-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .otp-countdown {
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: var(--font-mono);
        font-size: .78rem;
        color: var(--text-soft);
    }
    .otp-countdown .timer-circle {
        width: 32px; height: 32px;
        border-radius: 50%;
        border: 2.5px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        font-size: .7rem; font-weight: 700;
        color: var(--gold-lt);
        position: relative;
    }
    .otp-countdown .timer-circle.urgent {
        border-color: var(--red);
        color: var(--red);
        animation: timerPulse 1s ease infinite;
    }
    @keyframes timerPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .5; }
    }

    /* Resend button */
    .otp-resend-btn {
        background: none;
        border: 1px solid var(--border);
        color: var(--text-soft);
        font-family: var(--font-mono);
        font-size: .72rem;
        padding: 6px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: all .2s;
        letter-spacing: .02em;
    }
    .otp-resend-btn:hover:not(:disabled) {
        border-color: var(--gold);
        color: var(--gold-lt);
        background: rgba(201,146,42,.06);
    }
    .otp-resend-btn:disabled {
        opacity: .35;
        cursor: not-allowed;
    }

    /* OTP Status message */
    .otp-status {
        text-align: center;
        font-size: .78rem;
        font-weight: 600;
        padding: 8px 12px;
        border-radius: 8px;
        margin-bottom: 14px;
        display: none;
        animation: otpSlideIn .3s ease;
    }
    .otp-status.show { display: block; }
    .otp-status.success {
        background: rgba(34,197,94,.1);
        border: 1px solid rgba(34,197,94,.25);
        color: #4ade80;
    }
    .otp-status.error {
        background: rgba(239,68,68,.08);
        border: 1px solid rgba(239,68,68,.25);
        color: #fca5a5;
    }

    /* Verified badge */
    .otp-verified-badge {
        display: none;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: rgba(34,197,94,.1);
        border: 1.5px solid rgba(34,197,94,.3);
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 18px;
        animation: verifiedGlow 2s ease infinite alternate;
    }
    .otp-verified-badge.show {
        display: flex;
    }
    .otp-verified-badge .badge-icon {
        width: 28px; height: 28px;
        border-radius: 50%;
        background: var(--green);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: .85rem;
        flex-shrink: 0;
    }
    .otp-verified-badge .badge-text {
        font-size: .85rem;
        font-weight: 700;
        color: #4ade80;
    }
    .otp-verified-badge .badge-mobile {
        font-family: var(--font-mono);
        font-size: .75rem;
        color: var(--text-soft);
    }

    @keyframes verifiedGlow {
        from { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        to   { box-shadow: 0 0 20px rgba(34,197,94,.12); }
    }

    /* Simulated OTP Toast */
    .otp-toast {
        position: fixed;
        top: 90px;
        right: 24px;
        z-index: 10000;
        background: linear-gradient(135deg, #1a3d6e, #0f2540);
        border: 1.5px solid rgba(201,146,42,.4);
        border-radius: 16px;
        padding: 18px 22px;
        min-width: 300px;
        box-shadow: 0 16px 50px rgba(0,0,0,.6), 0 0 30px rgba(201,146,42,.15);
        animation: toastIn .5s cubic-bezier(.22,1,.36,1) both;
        backdrop-filter: blur(20px);
    }
    .otp-toast.hide {
        animation: toastOut .4s ease forwards;
    }
    @keyframes toastIn {
        from { opacity: 0; transform: translateX(60px) scale(.9); }
        to   { opacity: 1; transform: translateX(0) scale(1); }
    }
    @keyframes toastOut {
        to { opacity: 0; transform: translateX(60px) scale(.9); }
    }
    .otp-toast-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .otp-toast-icon {
        width: 32px; height: 32px;
        border-radius: 50%;
        background: rgba(201,146,42,.15);
        border: 1px solid rgba(201,146,42,.3);
        display: flex; align-items: center; justify-content: center;
        font-size: .9rem;
        flex-shrink: 0;
    }
    .otp-toast-title {
        font-size: .8rem;
        font-weight: 700;
        color: var(--gold-lt);
        letter-spacing: .02em;
    }
    .otp-toast-sub {
        font-size: .65rem;
        color: var(--text-soft);
        font-family: var(--font-mono);
    }
    .otp-toast-close {
        position: absolute;
        top: 12px; right: 14px;
        background: none;
        border: none;
        color: var(--text-dim);
        cursor: pointer;
        font-size: .85rem;
        transition: color .2s;
    }
    .otp-toast-close:hover { color: var(--text); }
    .otp-toast-code {
        display: flex;
        gap: 6px;
        justify-content: center;
        margin: 8px 0;
    }
    .otp-toast-digit {
        width: 38px; height: 44px;
        background: rgba(201,146,42,.1);
        border: 1.5px solid rgba(201,146,42,.3);
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-family: var(--font-mono);
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--gold-lt);
        animation: digitPop .3s cubic-bezier(.22,1,.36,1) both;
    }
    .otp-toast-digit:nth-child(1) { animation-delay: .05s; }
    .otp-toast-digit:nth-child(2) { animation-delay: .1s; }
    .otp-toast-digit:nth-child(3) { animation-delay: .15s; }
    .otp-toast-digit:nth-child(4) { animation-delay: .2s; }
    .otp-toast-digit:nth-child(5) { animation-delay: .25s; }
    .otp-toast-digit:nth-child(6) { animation-delay: .3s; }
    @keyframes digitPop {
        from { opacity: 0; transform: scale(.5) translateY(8px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }
    .otp-toast-hint {
        text-align: center;
        font-size: .65rem;
        color: var(--text-dim);
        margin-top: 6px;
        font-family: var(--font-mono);
    }

    /* Hide TxID + Pay until OTP verified */
    .mfs-after-otp {
        display: none;
        animation: otpSlideIn .4s ease both;
    }
    .mfs-after-otp.show { display: block; }

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
    <input type="hidden" name="flight_id"     value="<?= $flight_id ?>">
    <input type="hidden" name="trip_type"     value="<?= htmlspecialchars($trip_type) ?>">
    <input type="hidden" name="from"          value="<?= htmlspecialchars($from) ?>">
    <input type="hidden" name="to"            value="<?= htmlspecialchars($to) ?>">
    <input type="hidden" name="depart_date"   value="<?= htmlspecialchars($depart_date) ?>">
    <input type="hidden" name="adults"        value="<?= $adults ?>">
    <input type="hidden" name="children"      value="<?= $children ?>">
    <input type="hidden" name="class"         value="<?= htmlspecialchars($class) ?>">
    <input type="hidden" name="pay_method"    id="pay_method_input" value="card">
    <!-- MFS hidden inputs — filled by JS before submit -->
    <input type="hidden" name="mobile_number" id="hidden_mobile" value="">
    <input type="hidden" name="trx_id"        id="hidden_trx"    value="">
    <input type="hidden" name="otp_code"      id="hidden_otp"    value="">

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
                        <span class="mt-icon" style="font-size:.95rem;font-weight:800;color:#e2136e;display:block;margin-bottom:2px;font-family:sans-serif">bKash</span>
                        <span class="mt-label">Mobile</span>
                    </div>
                    <div class="mtab nagad" data-method="nagad" onclick="setMethod('nagad',this)">
                        <span class="mt-icon" style="font-size:.95rem;font-weight:800;color:#f55a00;display:block;margin-bottom:2px;font-family:sans-serif">Nagad</span>
                        <span class="mt-label">Mobile</span>
                    </div>
                </div>

                <!-- 3D Credit Card -->
                <div id="cardSection">
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

                <button type="submit" name="pay_now" class="pay-btn" id="cardPayBtn">
                    <i class="fas fa-lock"></i>
                    <span>Pay Now</span>
                    <span class="btn-price">$<?= number_format($total_price, 2) ?></span>
                </button>
                </div><!-- /cardSection -->

                <!-- ══ bKash Panel ══ -->
                <div class="mfs-panel" id="bkashPanel">
                    <div class="mfs-brand bkash-brand">
                        <span class="mfs-logo">🟣</span>
                        <div class="mfs-brand-name">bKash</div>
                        <div class="mfs-brand-sub">Mobile Financial Service</div>
                    </div>

                    <!-- Step 1: Mobile number + Send OTP -->
                    <div class="otp-section" id="bkash_step1">
                        <div class="mfs-amount-chip">
                            <div><div class="mac-label">Amount to Pay</div></div>
                            <div class="mac-val">$<?= number_format($total_price, 2) ?></div>
                        </div>
                        <div class="fg">
                            <label><i class="fas fa-mobile-screen"></i> bKash Account Number</label>
                            <input type="text" id="bkash_mobile"
                                   class="mono-input" placeholder="01XXXXXXXXX"
                                   maxlength="11" oninput="this.value=this.value.replace(/\D/g,''); onMobileInput('bkash')">
                        </div>
                        <button type="button" class="otp-send-btn pulse" id="bkash_send_btn"
                                onclick="sendOtp('bkash')" disabled>
                            <i class="fas fa-paper-plane"></i> Send OTP to this number
                        </button>
                    </div>

                    <!-- Step 2: OTP verification -->
                    <div class="otp-verify-area" id="bkash_step2">
                        <div class="otp-header">
                            <div class="otp-title">Enter Verification Code</div>
                            <div class="otp-subtitle">OTP sent to <span class="otp-mobile-display" id="bkash_mobile_display"></span></div>
                        </div>
                        <div class="otp-digit-row" id="bkash_otp_row">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'bkash')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'bkash')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'bkash')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'bkash')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'bkash')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'bkash')" onkeydown="otpDigitKey(event,this)">
                        </div>
                        <div class="otp-status" id="bkash_otp_status"></div>
                        <div class="otp-timer-row">
                            <div class="otp-countdown">
                                <div class="timer-circle" id="bkash_timer_circle"><span id="bkash_timer">120</span></div>
                                <span>seconds remaining</span>
                            </div>
                            <button type="button" class="otp-resend-btn" id="bkash_resend_btn"
                                    onclick="sendOtp('bkash')" disabled>↺ Resend</button>
                        </div>
                    </div>

                    <!-- Step 3: Verified — TxID + Pay -->
                    <div class="otp-verified-badge" id="bkash_verified_badge">
                        <div class="badge-icon"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="badge-text">✓ Mobile Verified</div>
                            <div class="badge-mobile" id="bkash_verified_mobile"></div>
                        </div>
                    </div>

                    <div class="mfs-after-otp" id="bkash_after_otp">
                        <div class="mfs-instruction">
                            <strong>Now send the payment:</strong><br>
                            Open bKash app → <strong>Send Money</strong> → send <strong>$<?= number_format($total_price, 2) ?></strong><br>
                            to merchant: <span class="mfs-number">01XXXXXXXXX</span>
                            Then enter the Transaction ID below.
                        </div>
                        <div class="fg">
                            <label><i class="fas fa-receipt"></i> Transaction ID (TxID)</label>
                            <input type="text" id="bkash_trx"
                                   class="mono-input" placeholder="e.g. 8KJ3H2S9QD"
                                   oninput="this.value=this.value.toUpperCase()">
                        </div>
                        <button type="button" class="pay-btn" style="background:linear-gradient(135deg,#e2136e,#9c0a4e)"
                                onclick="submitMfs('bkash')">
                            <span>🟣</span>
                            <span>Confirm bKash Payment</span>
                            <span class="btn-price">$<?= number_format($total_price, 2) ?></span>
                        </button>
                    </div>
                </div>

                <!-- ══ Nagad Panel ══ -->
                <div class="mfs-panel" id="nagadPanel">
                    <div class="mfs-brand nagad-brand">
                        <span class="mfs-logo">🔴</span>
                        <div class="mfs-brand-name">Nagad</div>
                        <div class="mfs-brand-sub">Digital Financial Service</div>
                    </div>

                    <!-- Step 1 -->
                    <div class="otp-section" id="nagad_step1">
                        <div class="mfs-amount-chip">
                            <div><div class="mac-label">Amount to Pay</div></div>
                            <div class="mac-val">$<?= number_format($total_price, 2) ?></div>
                        </div>
                        <div class="fg">
                            <label><i class="fas fa-mobile-screen"></i> Nagad Account Number</label>
                            <input type="text" id="nagad_mobile"
                                   class="mono-input" placeholder="01XXXXXXXXX"
                                   maxlength="11" oninput="this.value=this.value.replace(/\D/g,''); onMobileInput('nagad')">
                        </div>
                        <button type="button" class="otp-send-btn pulse" id="nagad_send_btn"
                                onclick="sendOtp('nagad')" disabled>
                            <i class="fas fa-paper-plane"></i> Send OTP to this number
                        </button>
                    </div>

                    <!-- Step 2 -->
                    <div class="otp-verify-area" id="nagad_step2">
                        <div class="otp-header">
                            <div class="otp-title">Enter Verification Code</div>
                            <div class="otp-subtitle">OTP sent to <span class="otp-mobile-display" id="nagad_mobile_display"></span></div>
                        </div>
                        <div class="otp-digit-row" id="nagad_otp_row">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'nagad')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'nagad')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'nagad')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'nagad')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'nagad')" onkeydown="otpDigitKey(event,this)">
                            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="otpDigitInput(this,'nagad')" onkeydown="otpDigitKey(event,this)">
                        </div>
                        <div class="otp-status" id="nagad_otp_status"></div>
                        <div class="otp-timer-row">
                            <div class="otp-countdown">
                                <div class="timer-circle" id="nagad_timer_circle"><span id="nagad_timer">120</span></div>
                                <span>seconds remaining</span>
                            </div>
                            <button type="button" class="otp-resend-btn" id="nagad_resend_btn"
                                    onclick="sendOtp('nagad')" disabled>↺ Resend</button>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="otp-verified-badge" id="nagad_verified_badge">
                        <div class="badge-icon"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="badge-text">✓ Mobile Verified</div>
                            <div class="badge-mobile" id="nagad_verified_mobile"></div>
                        </div>
                    </div>

                    <div class="mfs-after-otp" id="nagad_after_otp">
                        <div class="mfs-instruction">
                            <strong>Now send the payment:</strong><br>
                            Open Nagad app → <strong>Send Money</strong> → send <strong>$<?= number_format($total_price, 2) ?></strong><br>
                            to merchant: <span class="mfs-number">01XXXXXXXXX</span>
                            Then enter the Transaction ID below.
                        </div>
                        <div class="fg">
                            <label><i class="fas fa-receipt"></i> Transaction ID (TxID)</label>
                            <input type="text" id="nagad_trx"
                                   class="mono-input" placeholder="e.g. NQ7K2M4P9R"
                                   oninput="this.value=this.value.toUpperCase()">
                        </div>
                        <button type="button" class="pay-btn" style="background:linear-gradient(135deg,#f55a00,#a83b00)"
                                onclick="submitMfs('nagad')">
                            <span>🔴</span>
                            <span>Confirm Nagad Payment</span>
                            <span class="btn-price">$<?= number_format($total_price, 2) ?></span>
                        </button>
                    </div>
                </div>

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

    const cardSection = document.getElementById('cardSection');
    const bkashPanel  = document.getElementById('bkashPanel');
    const nagadPanel  = document.getElementById('nagadPanel');

    cardSection.style.display = 'none';
    bkashPanel.classList.remove('active');
    nagadPanel.classList.remove('active');

    if (method === 'bkash')      bkashPanel.classList.add('active');
    else if (method === 'nagad') nagadPanel.classList.add('active');
    else                         cardSection.style.display = 'block';
}

/* ── OTP HELPERS ── */
const otpTimers = {};

function onMobileInput(provider) {
    const val = document.getElementById(provider + '_mobile').value;
    const btn = document.getElementById(provider + '_send_btn');
    btn.disabled = val.length !== 11;
    if (val.length === 11) btn.classList.add('pulse');
    else btn.classList.remove('pulse');
}

function sendOtp(provider) {
    const mobile = document.getElementById(provider + '_mobile').value.replace(/\D/g,'');
    if (mobile.length !== 11) return;

    const sendBtn   = document.getElementById(provider + '_send_btn');
    const resendBtn = document.getElementById(provider + '_resend_btn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    fetch('send_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mobile=' + encodeURIComponent(mobile)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Show step 2
            document.getElementById(provider + '_step1').style.opacity = '.5';
            document.getElementById(provider + '_step2').classList.add('active');
            document.getElementById(provider + '_mobile_display').textContent = data.mobile;

            // Show OTP toast (demo only)
            showOtpToast(data.otp, provider, mobile);

            // Start countdown
            startCountdown(provider, data.expires || 120);

            sendBtn.innerHTML = '<i class="fas fa-check"></i> OTP Sent';
        } else {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP to this number';
            showOtpStatus(provider, data.message, 'error');
        }
    })
    .catch(() => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP to this number';
        showOtpStatus(provider, 'Network error. Please try again.', 'error');
    });
}

function startCountdown(provider, seconds) {
    if (otpTimers[provider]) clearInterval(otpTimers[provider]);
    let remaining = seconds;
    const timerEl  = document.getElementById(provider + '_timer');
    const circleEl = document.getElementById(provider + '_timer_circle');
    const resendEl = document.getElementById(provider + '_resend_btn');

    resendEl.disabled = true;

    otpTimers[provider] = setInterval(() => {
        remaining--;
        if (timerEl) timerEl.textContent = remaining;
        if (remaining <= 20 && circleEl) circleEl.classList.add('urgent');
        if (remaining <= 0) {
            clearInterval(otpTimers[provider]);
            if (timerEl) timerEl.textContent = '0';
            resendEl.disabled = false;
            showOtpStatus(provider, 'OTP expired. Click Resend.', 'error');
        }
    }, 1000);
}

function otpDigitInput(input, provider) {
    input.value = input.value.replace(/\D/g,'').slice(-1);
    input.classList.toggle('filled', input.value !== '');

    const row    = document.getElementById(provider + '_otp_row');
    const digits = Array.from(row.querySelectorAll('.otp-digit'));
    const idx    = digits.indexOf(input);

    if (input.value && idx < digits.length - 1) digits[idx + 1].focus();

    // Auto-verify when all 6 filled
    const otp = digits.map(d => d.value).join('');
    if (otp.length === 6) verifyOtp(provider, otp);
}

function otpDigitKey(e, input) {
    if (e.key === 'Backspace' && !input.value) {
        const row    = input.closest('.otp-digit-row');
        const digits = Array.from(row.querySelectorAll('.otp-digit'));
        const idx    = digits.indexOf(input);
        if (idx > 0) { digits[idx - 1].value = ''; digits[idx - 1].classList.remove('filled'); digits[idx - 1].focus(); }
    }
}

function verifyOtp(provider, otp) {
    const mobile = document.getElementById(provider + '_mobile').value.replace(/\D/g,'');

    fetch('verify_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'otp=' + encodeURIComponent(otp) + '&mobile=' + encodeURIComponent(mobile)
    })
    .then(r => r.json())
    .then(data => {
        const row    = document.getElementById(provider + '_otp_row');
        const digits = Array.from(row.querySelectorAll('.otp-digit'));

        if (data.success) {
            digits.forEach(d => { d.classList.add('success'); d.classList.remove('error'); });
            if (otpTimers[provider]) clearInterval(otpTimers[provider]);
            showOtpStatus(provider, '✓ ' + data.message, 'success');

            // Show verified badge + TxID section
            setTimeout(() => {
                document.getElementById(provider + '_step2').style.display = 'none';
                const badge = document.getElementById(provider + '_verified_badge');
                badge.classList.add('show');
                document.getElementById(provider + '_verified_mobile').textContent = mobile;
                document.getElementById(provider + '_after_otp').classList.add('show');
            }, 800);
        } else {
            digits.forEach(d => d.classList.add('error'));
            showOtpStatus(provider, data.message, 'error');
            setTimeout(() => {
                digits.forEach(d => { d.classList.remove('error'); d.value = ''; d.classList.remove('filled'); });
                digits[0].focus();
            }, 1000);
        }
    });
}

function showOtpStatus(provider, msg, type) {
    const el = document.getElementById(provider + '_otp_status');
    if (!el) return;
    el.textContent = msg;
    el.className = 'otp-status show ' + type;
    setTimeout(() => el.classList.remove('show'), 4000);
}

function showOtpToast(otp, provider, mobile) {
    // Remove existing toast
    document.querySelectorAll('.otp-toast').forEach(t => t.remove());

    const brand   = provider === 'bkash' ? '🟣 bKash' : '🔴 Nagad';
    const masked  = mobile.substring(0,4) + '***' + mobile.substring(7);
    const digits  = otp.split('').map(d => `<div class="otp-toast-digit">${d}</div>`).join('');

    const toast = document.createElement('div');
    toast.className = 'otp-toast';
    toast.style.position = 'fixed';
    toast.innerHTML = `
        <button class="otp-toast-close" onclick="this.closest('.otp-toast').remove()">✕</button>
        <div class="otp-toast-header">
            <div class="otp-toast-icon">📱</div>
            <div>
                <div class="otp-toast-title">${brand} OTP</div>
                <div class="otp-toast-sub">Sent to ${masked}</div>
            </div>
        </div>
        <div class="otp-toast-code">${digits}</div>
        <div class="otp-toast-hint">⚠ Demo mode — enter this code above</div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.add('hide'); setTimeout(() => toast.remove(), 400); }, 15000);
}

function submitMfs(provider) {
    const mobile = document.getElementById(provider + '_mobile').value.replace(/\D/g,'');
    const trx    = (document.getElementById(provider + '_trx')?.value || '').trim();

    if (!trx) {
        alert('Please enter the Transaction ID (TxID) first.');
        return;
    }

    document.getElementById('hidden_mobile').value = mobile;
    document.getElementById('hidden_trx').value    = trx;
    document.getElementById('pay_method_input').value = provider;

    // Add pay_now to submit
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'pay_now'; inp.value = '1';
    document.getElementById('payForm').appendChild(inp);
    document.getElementById('payForm').submit();
}

/* ── MIN DATE ── */
document.addEventListener('DOMContentLoaded', () => {
    const cardNum = document.getElementById('card_number');
    if (cardNum && '<?= htmlspecialchars($_POST['card_number'] ?? '') ?>') formatCard(cardNum);

    const savedMethod = '<?= htmlspecialchars($_POST['pay_method'] ?? 'card') ?>';
    if (savedMethod !== 'card') {
        const tab = document.querySelector(`.mtab[data-method="${savedMethod}"]`);
        if (tab) setMethod(savedMethod, tab);
    }
});
</script>

<?php include("../includes/footer.php"); ?>