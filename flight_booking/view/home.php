<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/base_url.php";
// include("../includes/header.php");
include("../model/db_conn.php");

$total_flights  = $conn->query("SELECT COUNT(*) as c FROM flights WHERE seat > 0")->fetch_assoc()['c'] ?? 0;
$total_airlines = $conn->query("SELECT COUNT(*) as c FROM airlines")->fetch_assoc()['c'] ?? 0;
$total_bookings = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Book Flights Easily</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sky:      #0b72e6;
            --sky-dark: #0556b3;
            --sky-deep: #03378a;
            --gold:     #f5c842;
            --cloud:    #f0f6ff;
            --ink:      #0d1b2a;
            --slate:    #4a6380;
            --mist:     #e8f0fb;
            --white:    #ffffff;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--white);
            color: var(--ink);
            overflow-x: hidden;
        }

        /* ════════════════════════════════
           HERO
        ════════════════════════════════ */
        .hero {
            position: relative;
            min-height: 100vh;
            margin-top: -62px;
            padding-top: 62px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Unsplash aerial flight photo */
        .hero-bg {
            position: absolute; inset: 0;
            background-image: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=1800&q=85&auto=format&fit=crop');
            background-size: cover;
            background-position: center 40%;
            transform: scale(1.04);
            animation: slowZoom 20s ease-in-out infinite alternate;
        }
        @keyframes slowZoom {
            from { transform: scale(1.04); }
            to   { transform: scale(1.10); }
        }

        /* Layered overlays for depth */
        .hero-overlay-bottom {
            position: absolute; inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(5, 20, 50, 0.45) 0%,
                rgba(5, 20, 50, 0.30) 40%,
                rgba(5, 20, 50, 0.72) 80%,
                rgba(5, 20, 50, 0.95) 100%
            );
        }
        .hero-overlay-left {
            position: absolute; inset: 0;
            background: linear-gradient(
                100deg,
                rgba(3, 55, 138, 0.5) 0%,
                transparent 55%
            );
        }

        /* Noise grain texture overlay */
        .hero-grain {
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            opacity: 0.35;
            pointer-events: none;
        }

        .hero-inner {
            position: relative; z-index: 5;
            max-width: 1160px; margin: 0 auto;
            padding: 0 32px;
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 60px;
            align-items: center;
            width: 100%;
            padding-top: 80px;
        }

        /* Left: text */
        .hero-text { animation: fadeUp 0.9s ease both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(32px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(245, 200, 66, 0.18);
            border: 1px solid rgba(245, 200, 66, 0.45);
            color: var(--gold);
            padding: 6px 16px; border-radius: 30px;
            font-size: 0.75rem; font-weight: 600;
            letter-spacing: 0.12em; text-transform: uppercase;
            margin-bottom: 24px;
        }
        .hero-eyebrow::before { content: '✦'; font-size: 0.65rem; }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.8rem, 5.5vw, 4.8rem);
            font-weight: 900;
            color: var(--white);
            line-height: 1.08;
            margin-bottom: 22px;
            letter-spacing: -1px;
        }
        .hero-title .highlight {
            color: var(--gold);
            font-style: italic;
            position: relative;
            display: inline-block;
        }
        .hero-title .highlight::after {
            content: '';
            position: absolute;
            bottom: 2px; left: 0; right: 0;
            height: 3px;
            background: var(--gold);
            border-radius: 2px;
            transform: skewX(-8deg);
        }

        .hero-sub {
            color: rgba(255,255,255,0.78);
            font-size: 1.08rem;
            line-height: 1.75;
            margin-bottom: 36px;
            max-width: 520px;
            font-weight: 300;
        }

        .hero-actions { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 52px; }

        .btn-primary {
            background: var(--gold);
            color: var(--ink);
            padding: 15px 34px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s;
            letter-spacing: 0.02em;
            box-shadow: 0 6px 24px rgba(245,200,66,0.4);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(245,200,66,0.5);
            background: #f7d44e;
        }

        .btn-ghost {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            color: var(--white);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 15px 34px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.6);
            transform: translateY(-2px);
        }

        /* Trust badges */
        .trust-row {
            display: flex; gap: 28px; flex-wrap: wrap; align-items: center;
        }
        .trust-item {
            display: flex; align-items: center; gap: 8px;
            color: rgba(255,255,255,0.65);
            font-size: 0.8rem; font-weight: 500;
        }
        .trust-item .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--gold); flex-shrink: 0;
        }

        /* Right: search card */
        .hero-card {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 36px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.15);
            animation: fadeUp 0.9s 0.2s ease both;
        }

        .card-header {
            margin-bottom: 28px;
        }
        .card-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .card-header p { font-size: 0.82rem; color: var(--slate); }

        /* Trip type tabs */
        .trip-tabs {
            display: flex; background: var(--mist);
            border-radius: 10px; padding: 4px;
            margin-bottom: 22px; gap: 2px;
        }
        .trip-tab {
            flex: 1; padding: 8px; border-radius: 8px;
            font-size: 0.8rem; font-weight: 600;
            text-align: center; cursor: pointer;
            color: var(--slate); transition: all 0.2s;
            border: none; background: none; font-family: 'Outfit', sans-serif;
        }
        .trip-tab.active {
            background: var(--white);
            color: var(--sky);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Form fields */
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .field-row.single { grid-template-columns: 1fr; }
        .field-row.triple { grid-template-columns: 1fr 1fr 1fr; }

        .sf { position: relative; }
        .sf label {
            display: block;
            font-size: 0.68rem; font-weight: 700;
            color: var(--slate);
            text-transform: uppercase; letter-spacing: 0.08em;
            margin-bottom: 5px;
        }
        .sf input, .sf select {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #dde5f0;
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            color: var(--ink);
            background: var(--white);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
        }
        .sf input:focus, .sf select:focus {
            border-color: var(--sky);
            box-shadow: 0 0 0 3px rgba(11,114,230,0.12);
        }
        .sf .field-icon {
            position: absolute; right: 12px; top: 65%;
            transform: translateY(-50%);
            font-size: 0.9rem; pointer-events: none;
        }

        /* Swap button */
        .swap-btn {
            position: absolute; right: -18px; top: 50%;
            transform: translateY(-50%);
            width: 32px; height: 32px;
            background: var(--sky); color: white;
            border: 3px solid white;
            border-radius: 50%; font-size: 0.75rem;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; z-index: 2; transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(11,114,230,0.3);
        }
        .swap-btn:hover { background: var(--sky-dark); transform: translateY(-50%) rotate(180deg); }
        .route-row { position: relative; }

        .search-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--sky), var(--sky-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
            transition: all 0.3s;
            letter-spacing: 0.04em;
            box-shadow: 0 6px 20px rgba(11,114,230,0.4);
        }
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(11,114,230,0.5);
        }

        /* ════════════════════════════════
           STATS BAND
        ════════════════════════════════ */
        .stats-band {
            background: var(--ink);
            padding: 0;
        }
        .stats-inner {
            max-width: 1160px; margin: 0 auto;
            display: grid; grid-template-columns: repeat(3, 1fr);
        }
        .stat-cell {
            padding: 36px 24px; text-align: center;
            border-right: 1px solid rgba(255,255,255,0.07);
            position: relative; overflow: hidden;
        }
        .stat-cell:last-child { border-right: none; }
        .stat-cell::before {
            content: '';
            position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
            width: 40px; height: 2px;
            background: var(--gold);
            border-radius: 2px;
        }
        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            font-weight: 900;
            color: var(--white);
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-num span { color: var(--gold); }
        .stat-desc { font-size: 0.8rem; color: rgba(255,255,255,0.5); font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; }

        /* ════════════════════════════════
           WHY US
        ════════════════════════════════ */
        .why-section {
            padding: 100px 32px;
            background: var(--cloud);
        }
        .section-tag {
            display: inline-block;
            font-size: 0.72rem; font-weight: 700;
            color: var(--sky);
            text-transform: uppercase; letter-spacing: 0.14em;
            background: rgba(11,114,230,0.1);
            padding: 5px 14px; border-radius: 20px;
            margin-bottom: 16px;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3.5vw, 2.8rem);
            font-weight: 900;
            color: var(--ink);
            line-height: 1.2;
            margin-bottom: 14px;
        }
        .section-sub { color: var(--slate); font-size: 0.95rem; line-height: 1.7; }

        .why-grid {
            max-width: 1160px; margin: 60px auto 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        .why-card {
            background: var(--white);
            border-radius: 20px;
            padding: 34px 28px;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1.5px solid transparent;
            position: relative; overflow: hidden;
        }
        .why-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--sky), var(--gold));
            transform: scaleX(0); transform-origin: left;
            transition: transform 0.3s ease;
        }
        .why-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(11,114,230,0.14); border-color: var(--mist); }
        .why-card:hover::before { transform: scaleX(1); }

        .why-icon {
            width: 58px; height: 58px;
            border-radius: 16px;
            background: linear-gradient(135deg, #e8f2ff, #d0e6ff);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; margin-bottom: 22px;
        }
        .why-card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem; font-weight: 700;
            color: var(--ink); margin-bottom: 10px;
        }
        .why-card p { font-size: 0.85rem; color: var(--slate); line-height: 1.7; }

        /* ════════════════════════════════
           ROUTES
        ════════════════════════════════ */
        .routes-section {
            padding: 100px 32px;
            background: var(--white);
        }
        .routes-header {
            max-width: 1160px; margin: 0 auto 56px;
            display: flex; justify-content: space-between; align-items: flex-end;
            flex-wrap: wrap; gap: 20px;
        }
        .view-all-link {
            color: var(--sky); font-size: 0.88rem; font-weight: 600;
            text-decoration: none; border-bottom: 1.5px solid transparent;
            transition: border-color 0.2s;
        }
        .view-all-link:hover { border-color: var(--sky); }

        .routes-grid {
            max-width: 1160px; margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .route-card {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            cursor: pointer;
        }
        .route-card-inner {
            background: var(--cloud);
            border: 1.5px solid #dde8f8;
            border-radius: 18px;
            padding: 28px;
            transition: all 0.3s;
            display: flex; flex-direction: column; gap: 6px;
        }
        .route-card:hover .route-card-inner {
            background: var(--sky);
            border-color: var(--sky);
            transform: scale(1.02);
            box-shadow: 0 16px 40px rgba(11,114,230,0.25);
        }
        .route-from-to {
            display: flex; align-items: center; gap: 10px;
            font-size: 1.1rem; font-weight: 700; color: var(--ink);
            transition: color 0.3s;
        }
        .route-card:hover .route-from-to { color: white; }
        .route-arrow { color: var(--sky); font-size: 1rem; transition: color 0.3s; }
        .route-card:hover .route-arrow { color: rgba(255,255,255,0.7); }
        .route-price {
            font-size: 0.92rem; color: var(--sky); font-weight: 700;
            transition: color 0.3s;
        }
        .route-card:hover .route-price { color: var(--gold); }
        .route-meta { font-size: 0.75rem; color: var(--slate); transition: color 0.3s; }
        .route-card:hover .route-meta { color: rgba(255,255,255,0.65); }

        /* Large featured route */
        .route-card.featured .route-card-inner {
            background: linear-gradient(135deg, var(--sky-deep), var(--sky));
            border-color: var(--sky);
            padding: 36px;
        }
        .route-card.featured .route-from-to { color: white; font-size: 1.25rem; }
        .route-card.featured .route-arrow { color: rgba(255,255,255,0.6); }
        .route-card.featured .route-price { color: var(--gold); font-size: 1rem; }
        .route-card.featured .route-meta { color: rgba(255,255,255,0.6); }
        .route-card.featured:hover .route-card-inner { background: linear-gradient(135deg, #02245e, var(--sky-deep)); transform: scale(1.02); }

        .featured-badge {
            display: inline-block;
            background: var(--gold); color: var(--ink);
            font-size: 0.65rem; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
            text-transform: uppercase; letter-spacing: 0.1em;
            margin-bottom: 10px;
        }

        /* Grid layout: first card spans 2 cols */
        .routes-grid .route-card:first-child {
            grid-column: span 1;
            grid-row: span 2;
        }

        /* ════════════════════════════════
           QUOTE
        ════════════════════════════════ */
        .quote-section {
            position: relative;
            padding: 100px 32px;
            background-image: url('https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?w=1600&q=80&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            text-align: center;
            overflow: hidden;
        }
        .quote-section::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(3,55,138,0.88), rgba(5,20,50,0.88));
        }
        .quote-inner { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; }
        .quote-mark {
            font-family: 'Playfair Display', serif;
            font-size: 8rem; color: var(--gold);
            opacity: 0.3; line-height: 0.5;
            margin-bottom: 20px; display: block;
        }
        .quote-text {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.3rem, 3vw, 1.9rem);
            font-style: italic;
            color: white;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .quote-cite {
            color: var(--gold);
            font-size: 0.88rem; font-weight: 600;
            letter-spacing: 0.08em; text-transform: uppercase;
        }

        /* ════════════════════════════════
           CTA
        ════════════════════════════════ */
        .cta-section {
            padding: 100px 32px;
            background: var(--cloud);
            text-align: center;
        }
        .cta-inner { max-width: 620px; margin: 0 auto; }
        .cta-section .section-title { margin-bottom: 16px; }
        .cta-section .section-sub { margin-bottom: 36px; }
        .cta-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
        .btn-cta-main {
            background: linear-gradient(135deg, var(--sky), var(--sky-dark));
            color: white; padding: 16px 40px; border-radius: 50px;
            font-weight: 700; font-size: 1rem;
            text-decoration: none; transition: all 0.3s;
            box-shadow: 0 6px 24px rgba(11,114,230,0.4);
        }
        .btn-cta-main:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(11,114,230,0.5); }
        .btn-cta-outline {
            background: white; color: var(--sky);
            border: 2px solid var(--sky);
            padding: 16px 40px; border-radius: 50px;
            font-weight: 700; font-size: 1rem;
            text-decoration: none; transition: all 0.3s;
        }
        .btn-cta-outline:hover { background: var(--sky); color: white; transform: translateY(-2px); }

        /* ════════════════════════════════
           SCROLL TO TOP
        ════════════════════════════════ */
        #scrollBtn {
            position: fixed; bottom: 32px; right: 32px;
            width: 48px; height: 48px;
            background: var(--ink); color: var(--gold);
            border: none; border-radius: 50%;
            font-size: 1.1rem; cursor: pointer;
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
            display: none; align-items: center; justify-content: center;
            transition: all 0.3s; z-index: 99;
        }
        #scrollBtn.show { display: flex; }
        #scrollBtn:hover { background: var(--sky); transform: translateY(-3px); }

        /* ════════════════════════════════
           RESPONSIVE
        ════════════════════════════════ */
        @media (max-width: 900px) {
            .hero-inner { grid-template-columns: 1fr; padding-top: 100px; padding-bottom: 60px; }
            .hero-card { max-width: 520px; margin: 0 auto; }
            .why-grid { grid-template-columns: repeat(2, 1fr); }
            .routes-grid { grid-template-columns: repeat(2, 1fr); }
            .routes-grid .route-card:first-child { grid-column: span 2; grid-row: span 1; }
            .hero-title { font-size: 2.6rem; }
        }
        @media (max-width: 600px) {
            .stats-inner { grid-template-columns: 1fr; }
            .stat-cell { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.07); }
            .why-grid { grid-template-columns: 1fr; }
            .routes-grid { grid-template-columns: 1fr; }
            .routes-grid .route-card:first-child { grid-column: span 1; }
            .field-row { grid-template-columns: 1fr; }
            .field-row.triple { grid-template-columns: 1fr 1fr; }
            .hero-inner { padding: 80px 20px 50px; }
            .why-section, .routes-section, .quote-section, .cta-section { padding: 70px 20px; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay-bottom"></div>
    <div class="hero-overlay-left"></div>
    <div class="hero-grain"></div>

    <div class="hero-inner">

        <!-- LEFT: Text -->
        <div class="hero-text">
            <div class="hero-eyebrow">Bangladesh's Smart Flight Booking</div>
            <h1 class="hero-title">
                Fly <span class="highlight">Smarter</span><br>
                with GoZayan
            </h1>
            <p class="hero-sub">
                Search, compare, and book flights in seconds.
                The fastest way to plan your next journey — across Bangladesh and beyond.
            </p>
            <div class="hero-actions">
                <a href="searchflights.php" class="btn-primary">✈ Search Flights</a>
                <?php if (!isset($_SESSION['role'])): ?>
                    <a href="register.php" class="btn-ghost">Create Free Account</a>
                <?php else: ?>
                    <a href="userhome.php" class="btn-ghost">My Dashboard →</a>
                <?php endif; ?>
            </div>
            <div class="trust-row">
                <div class="trust-item"><span class="dot"></span>No hidden fees</div>
                <div class="trust-item"><span class="dot"></span>Instant e-tickets</div>
                <div class="trust-item"><span class="dot"></span>bKash & card payment</div>
            </div>
        </div>

        <!-- RIGHT: Quick search card -->
        <div class="hero-card">
            <div class="card-header">
                <h3>Find Your Flight</h3>
                <p>Search available seats in real time</p>
            </div>

            <!-- Trip type tabs -->
            <div class="trip-tabs">
                <button class="trip-tab active" onclick="setTrip(this,'one-way')">One Way</button>
                <button class="trip-tab" onclick="setTrip(this,'round-trip')">Round Trip</button>
            </div>

            <form action="passengerHome.php" method="POST">
                <input type="hidden" name="trip_type" id="trip_type" value="one-way">

                <!-- From / To -->
                <div class="field-row route-row">
                    <div class="sf">
                        <label>From</label>
                        <input type="text" name="from" placeholder="Departure city" required>
                        <span class="field-icon">🛫</span>
                    </div>
                    <div class="sf">
                        <label>To</label>
                        <input type="text" name="to" placeholder="Destination city" required>
                        <span class="field-icon">🛬</span>
                    </div>
                    <button type="button" class="swap-btn" onclick="swapCities()" title="Swap">⇄</button>
                </div>

                <!-- Date -->
                <div class="field-row">
                    <div class="sf">
                        <label>Depart Date</label>
                        <input type="date" name="depart_date" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="sf" id="returnDateGroup" style="display:none;">
                        <label>Return Date</label>
                        <input type="date" name="return_date" min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- Passengers & Class -->
                <div class="field-row triple">
                    <div class="sf">
                        <label>Adults</label>
                        <input type="number" name="adults" min="1" max="9" value="1">
                    </div>
                    <div class="sf">
                        <label>Children</label>
                        <input type="number" name="children" min="0" max="9" value="0">
                    </div>
                    <div class="sf">
                        <label>Class</label>
                        <select name="class">
                            <option>Economy</option>
                            <option>Business</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="search-btn">Search Available Flights →</button>
            </form>
        </div>

    </div>
</section>

<!-- ══════════════════════════════════════
     STATS
══════════════════════════════════════ -->
<div class="stats-band">
    <div class="stats-inner">
        <div class="stat-cell">
            <div class="stat-num"><?= $total_flights ?><span>+</span></div>
            <div class="stat-desc">Available Flights</div>
        </div>
        <div class="stat-cell">
            <div class="stat-num"><?= $total_airlines ?><span>+</span></div>
            <div class="stat-desc">Partner Airlines</div>
        </div>
        <div class="stat-cell">
            <div class="stat-num"><?= $total_bookings ?><span>+</span></div>
            <div class="stat-desc">Happy Travellers</div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     WHY GOZAYAN
══════════════════════════════════════ -->
<section class="why-section">
    <div style="max-width:1160px; margin:0 auto;">
        <div class="section-tag">Why GoZayan?</div>
        <h2 class="section-title">Everything You Need<br>to Travel with Confidence</h2>
        <p class="section-sub" style="max-width:520px;">Built for travellers who value simplicity, security, and speed. No complicated process — just search, pay, and fly.</p>

        <div class="why-grid">
            <div class="why-card">
                <div class="why-icon">🔍</div>
                <h3>Instant Search</h3>
                <p>Real-time seat availability across multiple airlines. Find the best option in seconds.</p>
            </div>
            <div class="why-card">
                <div class="why-icon">💳</div>
                <h3>Secure Payment</h3>
                <p>Pay with credit cards, bKash, or Nagad. Every transaction is encrypted end-to-end.</p>
            </div>
            <div class="why-card">
                <div class="why-icon">🎫</div>
                <h3>Instant E-Ticket</h3>
                <p>Your printable ticket is generated immediately. No waiting, no queues.</p>
            </div>
            <div class="why-card">
                <div class="why-icon">📋</div>
                <h3>Full Booking History</h3>
                <p>All your trips in one place. View, print, or cancel any booking anytime.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     POPULAR ROUTES
══════════════════════════════════════ -->
<section class="routes-section">
    <div class="routes-header">
        <div>
            <div class="section-tag">Popular Routes</div>
            <h2 class="section-title">Most Booked<br>Destinations</h2>
        </div>
        <a href="searchflights.php" class="view-all-link">View all flights →</a>
    </div>

    <div class="routes-grid">
        <?php
        $routes = [
            ['Dhaka', "Cox's Bazar", 'From ৳4,200', '✈ Most popular', true],
            ['Dhaka', 'Chittagong',  'From ৳2,500', '✈ Daily flights', false],
            ['Dhaka', 'Sylhet',      'From ৳3,000', '✈ Multiple daily', false],
            ['Dhaka', 'Rajshahi',    'From ৳2,800', '✈ Quick hop', false],
            ['Chittagong', 'Dhaka',  'From ৳2,500', '✈ Return route', false],
        ];
        foreach ($routes as $r):
            $featured = $r[4] ?? false;
        ?>
        <div class="route-card <?= $featured ? 'featured' : '' ?>"
             onclick="window.location='searchflights.php?from=<?= urlencode($r[0]) ?>&to=<?= urlencode($r[1]) ?>'">
            <div class="route-card-inner">
                <?php if ($featured): ?>
                    <span class="featured-badge">⭐ Most Popular</span>
                <?php endif; ?>
                <div class="route-from-to">
                    <?= htmlspecialchars($r[0]) ?>
                    <span class="route-arrow">→</span>
                    <?= htmlspecialchars($r[1]) ?>
                </div>
                <div class="route-price"><?= $r[2] ?></div>
                <div class="route-meta"><?= $r[3] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ══════════════════════════════════════
     QUOTE
══════════════════════════════════════ -->
<div class="quote-section">
    <div class="quote-inner">
        <span class="quote-mark">"</span>
        <p class="quote-text">The world is a book, and those who do not travel read only one page.</p>
        <div class="quote-cite">— Saint Augustine</div>
    </div>
</div>

<!-- ══════════════════════════════════════
     CTA
══════════════════════════════════════ -->
<section class="cta-section">
    <div class="cta-inner">
        <div class="section-tag">Get Started</div>
        <h2 class="section-title">Ready to Take Off? 🚀</h2>
        <p class="section-sub">Create your free account and book your first flight in under 2 minutes. No fees, no fuss.</p>
        <div class="cta-btns">
            <?php if (!isset($_SESSION['role'])): ?>
                <a href="register.php" class="btn-cta-main">Create Free Account</a>
                <a href="searchflights.php" class="btn-cta-outline">Browse Flights</a>
            <?php else: ?>
                <a href="searchflights.php" class="btn-cta-main">Search Flights Now</a>
                <a href="userhome.php" class="btn-cta-outline">My Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- SCROLL TO TOP -->
<button id="scrollBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<script>
// Trip type tabs
function setTrip(btn, type) {
    document.querySelectorAll('.trip-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('trip_type').value = type;
    const ret = document.getElementById('returnDateGroup');
    ret.style.display = type === 'round-trip' ? 'block' : 'none';
}

// Swap from/to cities
function swapCities() {
    const fromInput = document.querySelector('input[name="from"]');
    const toInput   = document.querySelector('input[name="to"]');
    [fromInput.value, toInput.value] = [toInput.value, fromInput.value];
}

// Pre-fill search from URL params (popular routes click)
window.addEventListener('DOMContentLoaded', () => {
    const p = new URLSearchParams(window.location.search);
    if (p.get('from')) document.querySelector('input[name="from"]').value = p.get('from');
    if (p.get('to'))   document.querySelector('input[name="to"]').value   = p.get('to');
});

// Scroll to top button
window.addEventListener('scroll', () => {
    document.getElementById('scrollBtn').classList.toggle('show', window.scrollY > 500);
});
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>