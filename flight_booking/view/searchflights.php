<?php
session_start();
require_once __DIR__ . "/../config/base_url.php";
include("../model/db_conn.php");

$is_logged_in = isset($_SESSION['email']) && isset($_SESSION['role']) && $_SESSION['role'] === 'webuser';

$flights = [];
$search_performed = false;
$trip_type   = 'one-way';
$from        = '';
$to          = '';
$depart_date = '';
$return_date = '';
$adults      = 1;
$children    = 0;
$class       = 'Economy';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $trip_type   = $_POST['trip_type']   ?? 'one-way';
    $from        = trim($_POST['from']   ?? '');
    $to          = trim($_POST['to']     ?? '');
    $depart_date = $_POST['depart_date'] ?? '';
    $return_date = $_POST['return_date'] ?? '';
    $adults      = max(1, (int)($_POST['adults']   ?? 1));
    $children    = max(0, (int)($_POST['children'] ?? 0));
    $class       = $_POST['class']       ?? 'Economy';
    $search_performed = true;

    if (!empty($from) && !empty($to)) {
        $from_pat   = "%" . $from . "%";
        $to_pat     = "%" . $to   . "%";
        $search_day = !empty($depart_date) ? date('l', strtotime($depart_date)) : '';

        $sql = "
            SELECT f.*,
                   ROUND(f.price * (1 - f.discount_pct / 100), 2) AS final_price,
                   s.departure_day, s.arrival_day,
                   s.departure_time AS sched_dep_time,
                   s.arrival_time   AS sched_arr_time
            FROM flights f
            LEFT JOIN schedule s ON s.flight_code COLLATE utf8mb4_unicode_ci = f.flight_code
            WHERE f.departure LIKE ? AND f.arrival LIKE ?
              AND f.status = 'active'
              AND (f.seat IS NULL OR f.seat > 0)
        ";
        if (!empty($search_day)) $sql .= " AND (s.departure_day IS NULL OR s.departure_day = ?)";
        $sql .= " ORDER BY final_price ASC";

        if (!empty($search_day)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $from_pat, $to_pat, $search_day);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $from_pat, $to_pat);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $flights[] = $row;
        $stmt->close();
    }
}

$city_rows = $conn->query("SELECT DISTINCT departure FROM flights UNION SELECT DISTINCT arrival FROM flights ORDER BY departure");
$cities = [];
while ($cr = $city_rows->fetch_assoc()) $cities[] = $cr['departure'];

function getStops($duration) {
    preg_match('/(\d+)\s*h/', $duration, $h);
    preg_match('/(\d+)\s*m/', $duration, $m);
    $total_mins = (isset($h[1]) ? (int)$h[1] * 60 : 0) + (isset($m[1]) ? (int)$m[1] : 0);
    if ($total_mins < 120) return 0;
    if ($total_mins < 300) return 1;
    return 2;
}

$airline_prices = [];
foreach ($flights as $f) {
    $a  = $f['airline_name'];
    $fp = (float)($f['final_price'] ?? $f['price']);
    if (!isset($airline_prices[$a]) || $fp < $airline_prices[$a]) $airline_prices[$a] = $fp;
}
asort($airline_prices);

$seat_buckets = ['0-50' => false, '51-150' => false, '151-300' => false, '300+' => false];
foreach ($flights as $f) {
    $s = (int)($f['seat'] ?? 0);
    if ($s <= 50) $seat_buckets['0-50'] = true;
    elseif ($s <= 150) $seat_buckets['51-150'] = true;
    elseif ($s <= 300) $seat_buckets['151-300'] = true;
    else $seat_buckets['300+'] = true;
}

$min_price = count($flights) ? (int)min(array_map(fn($f) => $f['final_price'] ?? $f['price'], $flights)) : 0;
$max_price = count($flights) ? (int)max(array_map(fn($f) => $f['final_price'] ?? $f['price'], $flights)) : 10000;
$total_passengers = $adults + $children;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Search Flights</title>

    <!-- Premium Fonts -->
    <!-- Cormorant Garamond: editorial display headings -->
    <!-- DM Sans: clean body text -->
    <!-- Bebas Neue: large price display numerals -->
    <!-- IBM Plex Mono: tabular data (codes, times, small figures) — true tnum/lnum -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Bebas+Neue&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

    <style>
    /* ═══════════════════════════════════════════════════
       DESIGN TOKENS  —  Luxury Travel Editorial
    ═══════════════════════════════════════════════════ */
    :root {
        /* Palette */
        --ink:        #0f1923;
        --ink-2:      #2c3e52;
        --ink-3:      #5a7080;
        --ink-4:      #8fa4b4;
        --cream:      #f7f2eb;
        --cream-2:    #ede6db;
        --cream-3:    #e2d9cc;
        --gold:       #b8872a;
        --gold-lt:    #d4a84b;
        --gold-glow:  rgba(184,135,42,.18);
        --gold-tint:  rgba(184,135,42,.07);
        --navy:       #0f2540;
        --navy-2:     #162e4d;
        --navy-3:     #1e3d63;
        --sky:        #3b82f6;
        --sky-tint:   rgba(59,130,246,.09);
        --teal:       #0d9488;
        --red:        #c0392b;
        --green:      #15803d;
        --green-tint: rgba(21,128,61,.08);
        --amber:      #b45309;
        --amber-tint: rgba(180,83,9,.08);

        /* Typography */
        --font-display: 'Cormorant Garamond', Georgia, serif;
        --font-body:    'DM Sans', sans-serif;
        --font-mono:    'IBM Plex Mono', 'Courier New', monospace;
        --font-price:   'Bebas Neue', 'Arial Narrow', sans-serif;

        /* Spacing */
        --radius-sm:  8px;
        --radius:     14px;
        --radius-lg:  22px;
        --radius-xl:  30px;

        /* Shadows */
        --shadow-sm:  0 2px 8px rgba(15,25,35,.06);
        --shadow:     0 6px 24px rgba(15,25,35,.09);
        --shadow-lg:  0 16px 56px rgba(15,25,35,.14);
        --shadow-gold: 0 8px 32px rgba(184,135,42,.2);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { scroll-behavior: smooth; }

    body {
        font-family: var(--font-body);
        background: var(--cream);
        color: var(--ink);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
        background-image:
            radial-gradient(ellipse 100% 60% at 50% 0%, rgba(15,37,64,.04) 0%, transparent 70%);
    }

    /* ══════════════════════════════════
       SCROLLBAR
    ══════════════════════════════════ */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: var(--cream-2); }
    ::-webkit-scrollbar-thumb { background: var(--cream-3); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--ink-4); }

    /* ══════════════════════════════════
       HERO / SEARCH SECTION
    ══════════════════════════════════ */
    .hero {
        position: relative;
        min-height: 560px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 80px 24px 120px;
        overflow: hidden;
        background-color: var(--navy);
    }

    /* Background image with editorial overlay */
    .hero-bg {
        position: absolute; inset: 0; z-index: 0;
        background-image: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?q=80&w=2074&auto=format&fit=crop');
        background-size: cover; background-position: center 55%;
        opacity: .38;
    }
    .hero-bg::after {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(180deg,
            rgba(15,25,35,.55) 0%,
            rgba(15,37,64,.80) 60%,
            var(--navy) 100%);
    }

    /* Decorative diagonal rule */
    .hero-rule {
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 90px;
        background: var(--cream);
        clip-path: polygon(0 100%, 100% 100%, 100% 40%, 0 100%);
        z-index: 2;
    }

    .hero-content {
        position: relative; z-index: 3;
        text-align: center;
        margin-bottom: 48px;
    }
    .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-family: var(--font-mono);
        font-size: .7rem;
        font-weight: 500;
        letter-spacing: .2em;
        text-transform: uppercase;
        color: var(--gold-lt);
        margin-bottom: 18px;
    }
    .hero-eyebrow::before,
    .hero-eyebrow::after {
        content: '';
        display: block;
        width: 32px; height: 1px;
        background: var(--gold-lt);
        opacity: .6;
    }
    .hero h1 {
        font-family: var(--font-display);
        font-size: clamp(2.6rem, 5vw, 4.2rem);
        font-weight: 600;
        color: #fff;
        letter-spacing: -.02em;
        line-height: 1.08;
        margin-bottom: 16px;
    }
    .hero h1 em {
        font-style: italic;
        color: var(--gold-lt);
    }
    .hero-sub {
        font-size: 1rem;
        color: rgba(255,255,255,.6);
        font-weight: 400;
        letter-spacing: .01em;
    }

    /* ── SEARCH FORM CARD ── */
    .search-card {
        position: relative; z-index: 3;
        background: rgba(255,255,255,.97);
        backdrop-filter: blur(24px);
        border-radius: var(--radius-xl);
        padding: 32px 36px;
        width: 100%;
        max-width: 1060px;
        box-shadow: var(--shadow-lg), 0 0 0 1px rgba(255,255,255,.5);
        border: 1px solid rgba(255,255,255,.6);
    }

    /* Trip type tabs */
    .trip-tabs {
        display: flex;
        gap: 0;
        background: var(--cream);
        border: 1.5px solid var(--cream-3);
        border-radius: var(--radius-sm);
        padding: 4px;
        width: fit-content;
        margin-bottom: 24px;
    }
    .trip-tabs label {
        display: flex; align-items: center; gap: 7px;
        padding: 7px 20px;
        border-radius: calc(var(--radius-sm) - 2px);
        font-size: .82rem; font-weight: 600;
        color: var(--ink-3);
        cursor: pointer;
        transition: all .2s;
        user-select: none;
        letter-spacing: .01em;
    }
    .trip-tabs input[type="radio"] { display: none; }
    .trip-tabs label:has(input:checked) {
        background: var(--navy);
        color: #fff;
        box-shadow: 0 2px 8px rgba(15,37,64,.25);
    }
    .trip-tabs label i { font-size: .75rem; }

    /* Form rows */
    .form-row-1, .form-row-2 {
        display: flex; gap: 10px;
        align-items: flex-end; flex-wrap: wrap;
    }
    .form-row-1 { margin-bottom: 12px; }

    .fg { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 130px; }
    .fg label {
        font-family: var(--font-mono);
        font-size: .62rem; font-weight: 500;
        letter-spacing: .12em; text-transform: uppercase;
        color: var(--ink-3);
    }
    .fg input, .fg select {
        padding: 12px 15px;
        border: 1.5px solid var(--cream-3);
        border-radius: var(--radius-sm);
        font-family: var(--font-body);
        font-size: .93rem;
        color: var(--ink);
        background: var(--cream);
        transition: border-color .2s, background .2s, box-shadow .2s;
    }
    .fg input::placeholder { color: var(--ink-4); }
    .fg input:focus, .fg select:focus {
        outline: none;
        border-color: var(--gold);
        background: #fff;
        box-shadow: 0 0 0 3px var(--gold-glow);
    }

    /* Divider between From/To */
    .swap-btn {
        width: 40px; height: 40px; flex-shrink: 0;
        border-radius: 50%;
        border: 1.5px solid var(--cream-3);
        background: #fff;
        color: var(--gold);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all .22s;
        align-self: flex-end;
        font-size: .9rem;
    }
    .swap-btn:hover {
        background: var(--gold); color: #fff;
        border-color: var(--gold);
        transform: rotate(180deg);
        box-shadow: var(--shadow-gold);
    }

    /* Passenger dropdown */
    .pax-wrap { flex: 2; min-width: 220px; position: relative; }
    .pax-trigger {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 15px;
        border: 1.5px solid var(--cream-3);
        border-radius: var(--radius-sm);
        background: var(--cream);
        cursor: pointer; font-size: .93rem; color: var(--ink);
        transition: all .2s; user-select: none;
    }
    .pax-trigger:hover,
    .pax-trigger.open {
        border-color: var(--gold);
        background: #fff;
        box-shadow: 0 0 0 3px var(--gold-glow);
    }
    .pax-trigger .pax-icon { color: var(--gold); margin-right: 8px; }
    .pax-dropdown {
        position: absolute; top: calc(100% + 8px); left: 0; right: 0;
        background: #fff;
        border: 1.5px solid var(--cream-3);
        border-radius: var(--radius);
        padding: 18px;
        box-shadow: var(--shadow-lg);
        z-index: 100; display: none;
        min-width: 260px;
    }
    .pax-dropdown.open { display: block; animation: dropIn .2s ease; }
    @keyframes dropIn {
        from { opacity:0; transform:translateY(-6px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .pax-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 11px 0; border-bottom: 1px solid var(--cream-2);
    }
    .pax-row:last-of-type { border-bottom: none; }
    .pax-type { font-weight: 600; font-size: .88rem; color: var(--ink); }
    .pax-sub  { font-size: .72rem; color: var(--ink-4); margin-top: 2px; }
    .pax-counter { display: flex; align-items: center; gap: 14px; }
    .pax-counter button {
        width: 32px; height: 32px; border-radius: 50%;
        border: 1.5px solid var(--cream-3);
        background: var(--cream);
        color: var(--gold); font-size: 1.1rem; font-weight: 700;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: all .18s; line-height: 1;
    }
    .pax-counter button:hover { background: var(--gold); color: #fff; border-color: var(--gold); }
    .pax-counter span { font-weight: 700; font-size: 1rem; color: var(--ink); min-width: 20px; text-align: center; }
    .pax-class-row { display: flex; gap: 8px; padding: 12px 0 4px; }
    .pax-class-opt {
        flex: 1; text-align: center; padding: 8px;
        border: 1.5px solid var(--cream-3); border-radius: var(--radius-sm);
        font-size: .82rem; font-weight: 600; color: var(--ink-3);
        cursor: pointer; transition: all .18s; user-select: none;
    }
    .pax-class-opt input { display: none; }
    .pax-class-opt.active, .pax-class-opt:has(input:checked) {
        background: var(--navy); color: #fff; border-color: var(--navy);
    }
    .pax-done {
        width: 100%; margin-top: 12px; padding: 10px;
        background: var(--navy); color: #fff;
        border: none; border-radius: var(--radius-sm);
        font-family: var(--font-body); font-size: .88rem; font-weight: 600;
        cursor: pointer; transition: all .2s;
    }
    .pax-done:hover { background: var(--navy-3); }

    /* Search button */
    .search-btn {
        display: flex; align-items: center; gap: 10px;
        padding: 13px 32px;
        background: var(--gold);
        color: #fff;
        border: none; border-radius: var(--radius-sm);
        font-family: var(--font-body); font-size: .95rem; font-weight: 700;
        cursor: pointer; transition: all .25s;
        white-space: nowrap; align-self: flex-end;
        box-shadow: var(--shadow-gold);
        letter-spacing: .02em;
    }
    .search-btn:hover {
        background: var(--gold-lt);
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(184,135,42,.32);
    }
    .search-btn:active { transform: translateY(0); }

    /* Return date group */
    .return-group { display: contents; }

    /* ══════════════════════════════════
       STICKY SEARCH BAR
    ══════════════════════════════════ */
    .sticky-bar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 999;
        background: var(--navy);
        border-bottom: 1px solid rgba(255,255,255,.07);
        padding: 12px 28px;
        display: flex; align-items: center; gap: 20px;
        box-shadow: 0 4px 24px rgba(0,0,0,.3);
        transform: translateY(-100%);
        transition: transform .3s ease;
    }
    .sticky-bar.visible { transform: translateY(0); }
    .sticky-route {
        font-family: var(--font-display);
        font-size: 1.15rem; font-weight: 600;
        color: #fff; letter-spacing: -.01em;
    }
    .sticky-divider { color: var(--gold-lt); }
    .sticky-meta { font-size: .78rem; color: rgba(255,255,255,.5); }
    .sticky-cta {
        margin-left: auto;
        padding: 7px 20px;
        background: rgba(184,135,42,.2);
        border: 1px solid var(--gold);
        color: var(--gold-lt);
        border-radius: var(--radius-sm);
        font-size: .8rem; font-weight: 600;
        cursor: pointer; text-decoration: none;
        transition: background .2s;
        font-family: var(--font-body);
    }
    .sticky-cta:hover { background: var(--gold); color: #fff; }

    /* ══════════════════════════════════
       MAIN LAYOUT
    ══════════════════════════════════ */
    .page-body {
        max-width: 1280px;
        margin: 0 auto;
        padding: 52px 28px 100px;
        display: grid;
        grid-template-columns: 276px 1fr;
        gap: 32px;
        align-items: start;
    }

    /* ══════════════════════════════════
       SIDEBAR
    ══════════════════════════════════ */
    .sidebar { display: flex; flex-direction: column; gap: 14px; position: sticky; top: 24px; }

    .filter-block {
        background: #fff;
        border: 1px solid var(--cream-2);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: box-shadow .2s;
    }
    .filter-block:hover { box-shadow: var(--shadow); }

    .filter-head {
        padding: 12px 18px;
        background: var(--cream);
        border-bottom: 1px solid var(--cream-2);
        display: flex; align-items: center; gap: 9px;
        font-family: var(--font-mono);
        font-size: .67rem; font-weight: 500;
        letter-spacing: .14em; text-transform: uppercase;
        color: var(--ink-2);
    }
    .filter-head .fh-icon { color: var(--gold); font-size: .8rem; }
    .filter-body { padding: 14px 16px; }

    /* Stop options */
    .stop-list { display: flex; flex-direction: column; gap: 6px; }
    .stop-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 9px 12px;
        border: 1.5px solid var(--cream-2);
        border-radius: var(--radius-sm);
        cursor: pointer; transition: all .18s;
        background: var(--cream);
        user-select: none;
    }
    .stop-item:hover { border-color: var(--gold); background: var(--gold-tint); transform: translateX(2px); }
    .stop-item.active {
        border-color: var(--gold);
        background: var(--gold-tint);
        box-shadow: inset 3px 0 0 var(--gold);
        font-weight: 600;
    }
    .stop-item input { display: none; }
    .stop-lhs { display: flex; align-items: center; gap: 10px; }
    .stop-dots { display: flex; gap: 3px; align-items: center; }
    .sdot { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); }
    .sdot.e { background: var(--cream-3); border: 1.5px solid var(--ink-4); }
    .stop-name { font-size: .85rem; color: var(--ink); }
    .stop-ct {
        font-size: .72rem; color: var(--ink-3);
        background: var(--cream-2);
        padding: 2px 8px; border-radius: 20px;
        font-family: var(--font-mono);
    }

    /* Price slider */
    
    .price-ends {
        display: flex; justify-content: space-between;
        margin-bottom: 12px;
        font-family: var(--font-mono); font-size: .75rem;
        color: var(--ink-3);
    }
    .price-ends span { color: var(--gold); font-weight: 500; }
    input[type="range"] {
        -webkit-appearance: none; appearance: none;
        width: 100%; height: 4px; cursor: pointer;
        border-radius: 4px; outline: none;
        background: linear-gradient(to right, var(--gold) 0%, var(--gold) var(--range-pct,100%), var(--cream-3) var(--range-pct,100%), var(--cream-3) 100%);
    }
    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 18px; height: 18px; border-radius: 50%;
        background: #fff; border: 2.5px solid var(--gold);
        box-shadow: var(--shadow-gold); cursor: pointer;
        transition: transform .15s;
    }
    input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.2); }
    .price-readout {
        margin-top: 12px; text-align: center;
        font-size: .82rem; color: var(--ink-3);
        background: var(--cream); padding: 6px 12px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--cream-2);
    }
    .price-readout b { font-family: var(--font-mono); color: var(--gold); font-size: .92rem; font-variant-numeric: tabular-nums lnum; }

    /* Airline list */
    .airline-list { display: flex; flex-direction: column; gap: 4px; max-height: 220px; overflow-y: auto; }
    .al-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 8px 10px; border-radius: var(--radius-sm);
        cursor: pointer; transition: all .18s;
        border: 1px solid transparent;
    }
    .al-item:hover { background: var(--gold-tint); border-color: var(--cream-2); }
    .al-item.active { background: var(--gold-tint); border-color: var(--gold); box-shadow: inset 3px 0 0 var(--gold); }
    .al-item input { display: none; }
    .al-lhs { display: flex; align-items: center; gap: 10px; }
    .al-logo {
        width: 30px; height: 30px; border-radius: 8px;
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-3) 100%);
        color: var(--gold-lt); font-size: .65rem; font-weight: 700;
        font-family: var(--font-mono);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; letter-spacing: .04em;
    }
    .al-name { font-size: .85rem; color: var(--ink); font-weight: 500; }
    .al-price { font-family: var(--font-mono); font-size: .78rem; font-weight: 600; color: var(--gold); font-variant-numeric: tabular-nums lnum; }

    /* Seat capacity */
    .seat-list { display: flex; flex-direction: column; gap: 6px; }
    .seat-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 9px 12px;
        border: 1.5px solid var(--cream-2);
        border-radius: var(--radius-sm);
        cursor: pointer; transition: all .18s;
        background: var(--cream); user-select: none;
    }
    .seat-item:hover { border-color: var(--gold); background: var(--gold-tint); transform: translateX(2px); }
    .seat-item.active { border-color: var(--gold); background: var(--gold-tint); box-shadow: inset 3px 0 0 var(--gold); font-weight: 600; }
    .seat-item input { display: none; }
    .seat-lhs { display: flex; align-items: center; gap: 8px; font-size: .85rem; color: var(--ink); }
    .seat-lhs .s-icon { font-size: .9rem; }
    .seat-tag { font-size: .7rem; color: var(--ink-4); font-family: var(--font-mono); }

    /* Clear button */
    .clear-filters {
        width: 100%; padding: 11px;
        background: transparent; border: 1.5px solid var(--cream-3);
        color: var(--ink-3); border-radius: var(--radius);
        font-family: var(--font-body); font-size: .85rem; font-weight: 600;
        cursor: pointer; transition: all .22s; letter-spacing: .01em;
    }
    .clear-filters:hover {
        border-color: var(--red); color: var(--red);
        background: rgba(192,57,43,.04);
        transform: translateY(-1px);
    }

    /* ══════════════════════════════════
       RESULTS PANEL
    ══════════════════════════════════ */
    .results-panel { min-width: 0; }

    /* Results header bar */
    .results-header {
        background: #fff;
        border: 1px solid var(--cream-2);
        border-radius: var(--radius);
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow-sm);
    }
    .results-row-1 {
        display: flex; align-items: center; flex-wrap: wrap;
        gap: 10px; margin-bottom: 14px;
    }
    .route-display {
        font-family: var(--font-display);
        font-size: 1.5rem; font-weight: 600;
        color: var(--ink); letter-spacing: -.02em;
        display: flex; align-items: center; gap: 10px;
    }
    .route-arrow { color: var(--gold); font-size: 1rem; }
    .rh-pill {
        display: inline-flex; align-items: center; gap: 5px;
        background: var(--cream); border: 1px solid var(--cream-3);
        padding: 4px 12px; border-radius: 20px;
        font-size: .75rem; font-weight: 600; color: var(--ink-3);
    }
    .rh-pill.hi { background: var(--gold-tint); border-color: var(--gold); color: var(--gold); }
    .visible-count { margin-left: auto; font-size: .78rem; color: var(--ink-4); font-family: var(--font-mono); }

    /* Sort bar */
    .sort-row { display: flex; gap: 6px; flex-wrap: wrap; }
    .sort-btn {
        padding: 6px 16px; border-radius: 20px;
        font-family: var(--font-body); font-size: .78rem; font-weight: 600;
        border: 1.5px solid var(--cream-3);
        background: #fff; color: var(--ink-3);
        cursor: pointer; transition: all .2s; letter-spacing: .01em;
    }
    .sort-btn:hover { border-color: var(--gold); color: var(--gold); background: var(--gold-tint); }
    .sort-btn.active { background: var(--navy); color: #fff; border-color: var(--navy); box-shadow: 0 3px 10px rgba(15,37,64,.2); }

    /* ── FLIGHT CARD ── */
    @keyframes cardReveal {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .flight-card {
        background: #fff;
        border: 1px solid var(--cream-2);
        border-radius: var(--radius-lg);
        margin-bottom: 16px;
        display: flex;
        box-shadow: var(--shadow-sm);
        transition: box-shadow .25s, transform .25s, border-color .25s;
        animation: cardReveal .4s ease both;
        overflow: hidden;
        position: relative;
    }
    .flight-card:nth-child(2) { animation-delay: .05s; }
    .flight-card:nth-child(3) { animation-delay: .10s; }
    .flight-card:nth-child(4) { animation-delay: .15s; }
    .flight-card:nth-child(n+5) { animation-delay: .2s; }

    .flight-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-4px);
        border-color: var(--cream-3);
    }
    .flight-card::after {
        content: '';
        position: absolute; left: 0; top: 0; bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--gold) 0%, var(--gold-lt) 100%);
        opacity: 0; transition: opacity .25s;
        border-radius: var(--radius-lg) 0 0 var(--radius-lg);
    }
    .flight-card:hover::after { opacity: 1; }
    .flight-card.hidden { display: none; }

    /* Urgency banner */
    .urgency-strip {
        position: absolute; top: 0; left: 0; right: 0;
        background: linear-gradient(90deg, rgba(180,83,9,.1), rgba(180,83,9,.05));
        border-bottom: 1px solid rgba(180,83,9,.15);
        padding: 5px 22px;
        font-size: .7rem; font-weight: 700;
        color: var(--amber); letter-spacing: .02em;
        display: flex; align-items: center; gap: 6px;
    }

    /* Flight image */
    .fc-img {
        width: 170px; flex-shrink: 0;
        object-fit: cover;
    }
    .fc-img-placeholder {
        width: 170px; flex-shrink: 0;
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-3) 100%);
        display: flex; align-items: center; justify-content: center;
        font-size: 3.2rem;
        border-right: 1px solid var(--cream-2);
        position: relative; overflow: hidden;
    }
    .fc-img-placeholder::after {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 30% 60%, rgba(184,135,42,.15) 0%, transparent 70%);
    }

    /* Card body */
    .fc-body {
        flex: 1; padding: 22px 24px;
        display: flex; gap: 20px; align-items: stretch;
        flex-wrap: wrap;
    }

    /* Info section */
    .fc-info { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 12px; }
    .fc-title {
        font-family: var(--font-display);
        font-size: 1.25rem; font-weight: 600;
        color: var(--ink); letter-spacing: -.02em;
    }

    /* Tags */
    .fc-tags { display: flex; flex-wrap: wrap; gap: 6px; }
    .tag {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: .7rem; font-weight: 600;
        padding: 3px 10px; border-radius: 20px;
        letter-spacing: .02em;
        border: 1px solid transparent;
    }
    .tag-airline { background: rgba(15,37,64,.07); color: var(--navy); border-color: rgba(15,37,64,.12); }
    .tag-stop-0  { background: var(--green-tint); color: var(--green); border-color: rgba(21,128,61,.15); }
    .tag-stop-1  { background: var(--amber-tint); color: var(--amber); border-color: rgba(180,83,9,.15); }
    .tag-stop-2  { background: rgba(192,57,43,.07); color: var(--red); border-color: rgba(192,57,43,.15); }
    .tag-seat    { background: var(--cream); color: var(--ink-3); border-color: var(--cream-3); }

    /* Route visual */
    .fc-route {
        display: flex; align-items: center; gap: 0;
    }
    .fc-city { font-weight: 700; font-size: 1.1rem; color: var(--ink); letter-spacing: -.02em; }
    .fc-route-mid {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; gap: 4px; margin: 0 12px;
    }
    .fc-duration {
        font-family: var(--font-mono);
        font-size: .68rem; color: var(--ink-4);
        background: var(--cream);
        padding: 2px 10px; border-radius: 20px;
        border: 1px solid var(--cream-2);
        white-space: nowrap;
    }
    .fc-line {
        width: 100%; display: flex; align-items: center; gap: 0;
    }
    .fc-line::before, .fc-line::after {
        content: ''; flex: 1; height: 1px;
        background: linear-gradient(90deg, var(--cream-2), var(--gold));
    }
    .fc-line::after { background: linear-gradient(90deg, var(--gold), var(--cream-2)); }
    .fc-plane { color: var(--gold); font-size: .95rem; }

    /* Schedule times */
    .fc-times {
        display: flex; align-items: center; gap: 14px;
        background: var(--cream); border: 1px solid var(--cream-2);
        border-radius: var(--radius-sm); padding: 10px 14px;
        border-left: 3px solid var(--gold);
    }
    .time-blk { display: flex; flex-direction: column; align-items: center; gap: 1px; }
    .time-val { font-family: var(--font-mono); font-size: .95rem; font-weight: 500; color: var(--navy); font-variant-numeric: tabular-nums lnum; }
    .time-day { font-size: .65rem; font-weight: 600; color: var(--gold); text-transform: uppercase; letter-spacing: .06em; }
    .time-lbl { font-size: .6rem; color: var(--ink-4); text-transform: uppercase; letter-spacing: .06em; }
    .time-arrow { flex: 1; text-align: center; color: var(--gold); font-size: .9rem; }

    /* Meta row */
    .fc-meta { display: flex; flex-wrap: wrap; gap: 6px; }
    .fc-meta-item {
        display: flex; align-items: center; gap: 4px;
        background: var(--cream); border: 1px solid var(--cream-2);
        padding: 3px 9px; border-radius: 6px;
        font-size: .73rem; color: var(--ink-3); font-weight: 500;
    }

    /* Pricing panel */
    .fc-pricing {
        display: flex; flex-direction: column; align-items: flex-end;
        justify-content: center; gap: 10px;
        min-width: 160px;
        padding: 20px 18px;
        background: linear-gradient(160deg, var(--cream) 0%, var(--cream-2) 100%);
        border-left: 1px solid var(--cream-2);
        text-align: right;
    }
    .price-lbl {
        font-family: var(--font-mono);
        font-size: .62rem; letter-spacing: .12em; text-transform: uppercase;
        color: var(--ink-4); margin-bottom: -6px;
    }
    .price-amount {
        font-family: var(--font-price);
        font-size: 2.8rem; font-weight: 400;
        color: var(--navy); line-height: 1;
        letter-spacing: .04em;
        font-variant-numeric: tabular-nums lnum;
    }
    .price-per {
        font-size: .72rem; color: var(--ink-4); font-weight: 500;
        font-family: var(--font-mono);
        font-variant-numeric: tabular-nums lnum;
    }
    .price-original {
        text-decoration: line-through; color: var(--ink-4);
        font-size: .72rem; margin-right: 4px;
        font-family: var(--font-mono);
        font-variant-numeric: tabular-nums lnum;
    }
    .price-discount {
        font-family: var(--font-mono);
        font-size: .72rem; font-weight: 600;
        color: var(--green);
        background: var(--green-tint);
        padding: 2px 7px; border-radius: 4px;
    }
    .seats-warning {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: .72rem; font-weight: 700; color: var(--amber);
        background: var(--amber-tint);
        padding: 4px 10px; border-radius: 6px;
        border: 1px solid rgba(180,83,9,.15);
    }

    /* Book button */
    .book-btn {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; padding: 12px 20px;
        background: var(--navy);
        color: #fff; border: none;
        border-radius: var(--radius-sm);
        font-family: var(--font-body); font-size: .88rem; font-weight: 700;
        cursor: pointer; transition: all .25s;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(15,37,64,.25);
        letter-spacing: .02em;
    }
    .book-btn:hover {
        background: var(--gold);
        box-shadow: var(--shadow-gold);
        transform: translateY(-2px);
    }
    .book-btn.login-req {
        background: var(--gold);
        box-shadow: var(--shadow-gold);
    }
    .book-btn.login-req:hover { background: var(--gold-lt); }

    /* ── NO RESULTS ── */
    .no-results {
        text-align: center; padding: 80px 40px;
        background: #fff;
        border-radius: var(--radius-lg);
        border: 1px solid var(--cream-2);
        box-shadow: var(--shadow-sm);
    }
    .no-results .nr-icon {
        font-family: var(--font-display);
        font-size: 4rem; color: var(--gold-lt);
        display: block; margin-bottom: 20px;
        font-style: italic;
    }
    .no-results h3 {
        font-family: var(--font-display);
        font-size: 1.8rem; font-weight: 600;
        color: var(--ink); margin-bottom: 10px;
    }
    .no-results p { font-size: .9rem; color: var(--ink-3); line-height: 1.6; }
    .no-filter-msg {
        display: none; text-align: center; padding: 44px 30px;
        background: #fffdf8; border-radius: var(--radius-lg);
        border: 2px dashed var(--cream-3);
    }
    .no-filter-msg p { color: var(--ink-3); font-size: .88rem; }

    /* ══════════════════════════════════
       POPULAR ROUTES (pre-search)
    ══════════════════════════════════ */
    .popular-wrap {
        max-width: 1060px; margin: 60px auto 80px;
        padding: 0 28px;
    }
    .popular-heading {
        font-family: var(--font-display);
        font-size: 2rem; font-weight: 600;
        color: var(--ink); margin-bottom: 8px;
        letter-spacing: -.03em;
    }
    .popular-heading em { font-style: italic; color: var(--gold); }
    .popular-sub {
        font-size: .88rem; color: var(--ink-3); margin-bottom: 28px;
    }
    .popular-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
    .popular-card {
        background: #fff;
        border: 1px solid var(--cream-2);
        border-radius: var(--radius);
        padding: 22px 20px;
        cursor: pointer; transition: all .25s;
        position: relative; overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .popular-card::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--gold) 0%, var(--gold-lt) 100%);
    }
    .popular-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
        border-color: var(--cream-3);
    }
    .popular-card .pr-cities {
        font-family: var(--font-display);
        font-size: 1.05rem; font-weight: 600;
        color: var(--ink); margin-bottom: 6px;
        letter-spacing: -.01em;
    }
    .popular-card .pr-price {
        font-family: var(--font-mono);
        font-size: .82rem; color: var(--gold); font-weight: 500;
    }
    .popular-card .pr-bg {
        position: absolute; bottom: -12px; right: -6px;
        font-size: 3.5rem; opacity: .05; user-select: none;
    }

    /* ══════════════════════════════════
       RESPONSIVE
    ══════════════════════════════════ */
    @media (max-width: 900px) {
        .page-body { grid-template-columns: 1fr; }
        .sidebar { position: static; display: none; }
        .sidebar.open { display: flex; }
        .mobile-filter-btn { display: flex; }
        .swap-btn { display: none; }
    }
    @media (min-width: 901px) { .mobile-filter-btn { display: none; } }
    @media (max-width: 560px) {
        .search-card { padding: 20px 18px; }
        .flight-card { flex-direction: column; }
        .fc-img, .fc-img-placeholder { width: 100%; height: 160px; border-right: none; border-bottom: 1px solid var(--cream-2); }
        .fc-pricing { border-left: none; border-top: 1px solid var(--cream-2); flex-direction: row; align-items: center; justify-content: space-between; width: 100%; }
        .hero h1 { font-size: 2rem; }
    }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<!-- City datalist -->
<datalist id="cityList">
    <?php foreach ($cities as $city): ?>
    <option value="<?= htmlspecialchars($city) ?>">
    <?php endforeach; ?>
</datalist>

<!-- ══════════════════ HERO ══════════════════ -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-content">
        <div class="hero-eyebrow">GoZayan Flight Search</div>
        <h1>Your next journey<br><em>starts here</em></h1>
        <p class="hero-sub">Search, compare and book flights across Bangladesh and beyond</p>
    </div>

    <form class="search-card" action="" method="POST" id="searchForm">

        <!-- Trip type tabs -->
        <div class="trip-tabs">
            <label>
                <input type="radio" name="trip_type" value="one-way"
                    <?= $trip_type=='one-way'?'checked':'' ?>
                    onchange="toggleReturn(this)">
                <i class="fas fa-arrow-right"></i> One Way
            </label>
            <label>
                <input type="radio" name="trip_type" value="return"
                    <?= $trip_type=='return'?'checked':'' ?>
                    onchange="toggleReturn(this)">
                <i class="fas fa-arrows-left-right"></i> Return
            </label>
        </div>

        <!-- Row 1: Route + dates -->
        <div class="form-row-1">
            <div class="fg" style="flex:2; min-width:160px;">
                <label><i class="fas fa-plane-departure" style="color:var(--gold)"></i> &nbsp;From</label>
                <input type="text" name="from" id="fromInput"
                       placeholder="Departure city"
                       value="<?= htmlspecialchars($from) ?>"
                       list="cityList" autocomplete="off" required>
            </div>

            <button type="button" class="swap-btn" onclick="swapCities()" title="Swap">
                <i class="fas fa-arrow-right-arrow-left"></i>
            </button>

            <div class="fg" style="flex:2; min-width:160px;">
                <label><i class="fas fa-plane-arrival" style="color:var(--gold)"></i> &nbsp;To</label>
                <input type="text" name="to" id="toInput"
                       placeholder="Destination city"
                       value="<?= htmlspecialchars($to) ?>"
                       list="cityList" autocomplete="off" required>
            </div>

            <div class="fg" style="flex:1.2; min-width:140px;">
                <label><i class="fas fa-calendar-days" style="color:var(--gold)"></i> &nbsp;Depart</label>
                <input type="date" name="depart_date" id="departDate"
                       value="<?= htmlspecialchars($depart_date) ?>"
                       min="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="fg return-group" id="returnGroup"
                 style="flex:1.2; min-width:140px; <?= $trip_type==='return'?'':'display:none' ?>">
                <label><i class="fas fa-calendar-check" style="color:var(--gold)"></i> &nbsp;Return</label>
                <input type="date" name="return_date" id="returnDate"
                       value="<?= htmlspecialchars($return_date) ?>"
                       min="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <!-- Row 2: Passengers + search -->
        <div class="form-row-2">
            <div class="pax-wrap">
                <div class="fg" style="flex:1">
                    <label><i class="fas fa-users" style="color:var(--gold)"></i> &nbsp;Passengers &amp; Class</label>
                </div>
                <div class="pax-trigger" id="paxTrigger" onclick="togglePax()">
                    <span>
                        <span class="pax-icon"><i class="fas fa-user"></i></span>
                        <span id="paxSummary"><?= $adults ?> Adult<?= $adults>1?'s':'' ?><?= $children>0?', '.$children.' Child'.($children>1?'ren':''):'' ?> · <?= $class ?></span>
                    </span>
                    <i class="fas fa-chevron-down" style="font-size:.7rem; color:var(--ink-4)"></i>
                </div>
                <div class="pax-dropdown" id="paxDropdown">
                    <div class="pax-row">
                        <div><div class="pax-type">Adults</div><div class="pax-sub">18 years and above</div></div>
                        <div class="pax-counter">
                            <button type="button" onclick="changePax('adults',-1)">−</button>
                            <span id="adultsDisplay"><?= $adults ?></span>
                            <button type="button" onclick="changePax('adults',1)">+</button>
                        </div>
                    </div>
                    <div class="pax-row">
                        <div><div class="pax-type">Children</div><div class="pax-sub">Under 18 years</div></div>
                        <div class="pax-counter">
                            <button type="button" onclick="changePax('children',-1)">−</button>
                            <span id="childrenDisplay"><?= $children ?></span>
                            <button type="button" onclick="changePax('children',1)">+</button>
                        </div>
                    </div>
                    <div class="pax-class-row">
                        <label class="pax-class-opt <?= $class==='Economy'?'active':'' ?>">
                            <input type="radio" name="class" value="Economy" <?= $class==='Economy'?'checked':'' ?> onchange="syncClass()"> Economy
                        </label>
                        <label class="pax-class-opt <?= $class==='Business'?'active':'' ?>">
                            <input type="radio" name="class" value="Business" <?= $class==='Business'?'checked':'' ?> onchange="syncClass()"> Business
                        </label>
                    </div>
                    <input type="hidden" name="adults"   id="adultsHidden"   value="<?= $adults ?>">
                    <input type="hidden" name="children" id="childrenHidden" value="<?= $children ?>">
                    <button type="button" class="pax-done" onclick="togglePax()">Done ✓</button>
                </div>
            </div>

            <button type="submit" class="search-btn" id="searchBtn">
                <i class="fas fa-magnifying-glass"></i>
                Search Flights
            </button>
        </div>

    </form>
    <div class="hero-rule"></div>
</section>

<?php if ($search_performed): ?>

<!-- Sticky bar -->
<div class="sticky-bar" id="stickyBar">
    <div class="sticky-route">
        <?= htmlspecialchars($from) ?>
        <span class="sticky-divider"> &nbsp;→&nbsp; </span>
        <?= htmlspecialchars($to) ?>
    </div>
    <div class="sticky-meta">
        <?= $depart_date ? date('d M Y', strtotime($depart_date)) : '' ?> &nbsp;·&nbsp;
        <?= $adults ?> Adult<?= $adults>1?'s':'' ?><?= $children>0?', '.$children.' Child'.($children>1?'ren':''):'' ?> &nbsp;·&nbsp;
        <?= $class ?>
    </div>
    <a href="#" class="sticky-cta" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">
        <i class="fas fa-pen-to-square"></i> Modify
    </a>
</div>

<div class="page-body">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar" id="sidebar">

        <!-- STOPS -->
        <div class="filter-block">
            <div class="filter-head"><i class="fas fa-circle-dot fh-icon"></i> Stops</div>
            <div class="filter-body">
                <div class="stop-list">
                    <?php
                    $stop_counts = [0=>0, 1=>0, 2=>0];
                    foreach ($flights as $f) $stop_counts[getStops($f['duration'])]++;
                    $stop_cfg = [
                        0 => ['Non Stop', 1],
                        1 => ['1 Stop',   2],
                        2 => ['2+ Stops', 3],
                    ];
                    foreach ($stop_cfg as $val => [$lbl, $dots]):
                    ?>
                    <div class="stop-item" id="sBtn<?= $val ?>" onclick="toggleStop(<?= $val ?>,this)">
                        <input type="checkbox" id="stop<?= $val ?>">
                        <div class="stop-lhs">
                            <div class="stop-dots">
                                <?php for ($d=0;$d<$dots;$d++): ?>
                                <div class="sdot <?= $d>0?'e':'' ?>"></div>
                                <?php endfor; ?>
                            </div>
                            <span class="stop-name"><?= $lbl ?></span>
                        </div>
                        <span class="stop-ct"><?= $stop_counts[$val] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- PRICE -->
        <div class="filter-block">
            <div class="filter-head"><i class="fas fa-dollar-sign fh-icon"></i> Price Range</div>
            <div class="filter-body">
                <div class="price-ends">
                    <span>$<?= number_format($min_price) ?></span>
                    <span>$<?= number_format($max_price) ?></span>
                </div>
                <input type="range" id="priceRange"
                       min="<?= $min_price ?>" max="<?= $max_price ?>"
                       value="<?= $max_price ?>"
                       oninput="filterByPrice(this.value)">
                <div class="price-readout">Up to <b id="priceDisplay">$<?= number_format($max_price) ?></b></div>
            </div>
        </div>

        <!-- AIRLINES -->
        <?php if (count($airline_prices) > 0): ?>
        <div class="filter-block">
            <div class="filter-head"><i class="fas fa-plane fh-icon"></i> Airlines</div>
            <div class="filter-body">
                <div class="airline-list">
                    <?php foreach ($airline_prices as $airline => $mp):
                        $ini = strtoupper(implode('', array_map(fn($w)=>$w[0], explode(' ',$airline))));
                        $ini = substr($ini,0,2);
                    ?>
                    <div class="al-item" id="ai_<?= md5($airline) ?>" onclick="toggleAirline('<?= addslashes($airline) ?>',this)">
                        <label class="al-lhs">
                            <input type="checkbox">
                            <div class="al-logo"><?= htmlspecialchars($ini) ?></div>
                            <span class="al-name"><?= htmlspecialchars($airline) ?></span>
                        </label>
                        <span class="al-price">$<?= number_format($mp) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SEAT CAPACITY -->
        <div class="filter-block">
            <div class="filter-head"><i class="fas fa-couch fh-icon"></i> Seat Capacity</div>
            <div class="filter-body">
                <div class="seat-list">
                    <?php
                    $seat_cfg = [
                        '0-50'    => ['🪑','Small','≤ 50 seats'],
                        '51-150'  => ['✈️','Medium','51–150 seats'],
                        '151-300' => ['🛩️','Large','151–300 seats'],
                        '300+'    => ['🛫','Wide-body','300+ seats'],
                    ];
                    foreach ($seat_cfg as $key => [$icon,$lbl,$desc]):
                        if (!$seat_buckets[$key]) continue;
                    ?>
                    <div class="seat-item" id="si_<?= $key ?>" onclick="toggleSeat('<?= $key ?>',this)">
                        <input type="checkbox">
                        <span class="seat-lhs"><span class="s-icon"><?= $icon ?></span> <?= $lbl ?></span>
                        <span class="seat-tag"><?= $desc ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <button class="clear-filters" onclick="clearFilters()">
            <i class="fas fa-xmark"></i> &nbsp;Clear All Filters
        </button>
    </aside>

    <!-- ══ RESULTS ══ -->
    <div class="results-panel">

        <!-- Mobile filter toggle -->
        <button class="mobile-filter-btn" style="
            display:none; align-items:center; gap:8px;
            background:var(--navy); color:#fff; border:none;
            padding:11px 22px; border-radius:var(--radius);
            font-family:var(--font-body); font-size:.88rem; font-weight:600;
            cursor:pointer; margin-bottom:20px;
            box-shadow:0 4px 14px rgba(15,37,64,.2);"
            onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="fas fa-sliders"></i> Filters
        </button>

        <!-- Results header -->
        <div class="results-header">
            <div class="results-row-1">
                <div class="route-display">
                    <?= htmlspecialchars($from) ?>
                    <span class="route-arrow"><i class="fas fa-arrow-right"></i></span>
                    <?= htmlspecialchars($to) ?>
                </div>
                <span class="rh-pill hi"><?= count($flights) ?> flight<?= count($flights)!=1?'s':'' ?></span>
                <?php if ($depart_date): ?>
                <span class="rh-pill"><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($depart_date)) ?></span>
                <?php endif; ?>
                <span class="rh-pill"><i class="fas fa-users"></i> <?= $adults ?> Adult<?= $adults>1?'s':'' ?><?= $children>0?', '.$children.' Child'.($children>1?'ren':''):'' ?></span>
                <span class="rh-pill"><i class="fas fa-chair"></i> <?= $class ?></span>
                <span class="rh-pill"><i class="fas fa-rotate"></i> <?= ucfirst($trip_type) ?></span>
                <span class="visible-count" id="visibleCount"></span>
            </div>
            <div class="sort-row">
                <button class="sort-btn active" onclick="sortFlights('price_asc',this)">💰 Cheapest</button>
                <button class="sort-btn" onclick="sortFlights('price_desc',this)">💎 Expensive</button>
                <button class="sort-btn" onclick="sortFlights('duration',this)">⏱ Shortest</button>
                <button class="sort-btn" onclick="sortFlights('seats',this)">💺 Most Seats</button>
            </div>
        </div>

        <?php if (count($flights) > 0): ?>
        <div id="flightList">
        <?php foreach ($flights as $i => $flight):
            $stops        = getStops($flight['duration']);
            $unit_price   = (float)($flight['final_price'] ?? $flight['price']);
            $total_price  = round($unit_price * $total_passengers, 2);
            $seats_left   = (int)($flight['seat'] ?? 0);
            $stop_lbl_map = [0=>'Non Stop', 1=>'1 Stop', 2=>'2+ Stops'];
            $stop_cls_map = ['tag-stop-0','tag-stop-1','tag-stop-2'];
            $sb = $seats_left<=50?'0-50':($seats_left<=150?'51-150':($seats_left<=300?'151-300':'300+'));
            $dep_t = substr(!empty($flight['sched_dep_time'])?$flight['sched_dep_time']:($flight['departure_time']??''),0,5);
            $arr_t = substr(!empty($flight['sched_arr_time'])?$flight['sched_arr_time']:($flight['arrival_time']??''),0,5);
            $dep_day = $flight['departure_day'] ?? '';
            $arr_day = $flight['arrival_day']   ?? '';
            preg_match('/(\d+)\s*h/',$flight['duration'],$hh);
            preg_match('/(\d+)\s*m/',$flight['duration'],$mm);
            $dur_mins = (isset($hh[1])?(int)$hh[1]*60:0)+(isset($mm[1])?(int)$mm[1]:0);
        ?>
        <div class="flight-card"
             data-price="<?= $unit_price ?>"
             data-stops="<?= $stops ?>"
             data-airline="<?= htmlspecialchars($flight['airline_name']) ?>"
             data-seats="<?= $seats_left ?>"
             data-seat-bucket="<?= $sb ?>"
             data-duration-mins="<?= $dur_mins ?>">

            <?php if ($seats_left > 0 && $seats_left <= 5): ?>
            <div class="urgency-strip">
                <i class="fas fa-fire"></i>
                Only <?= $seats_left ?> seat<?= $seats_left>1?'s':'' ?> left at this price!
            </div>
            <?php endif; ?>

            <?php if (!empty($flight['image'])): ?>
                <img class="fc-img" src="upload/<?= htmlspecialchars($flight['image']) ?>" alt="Flight"
                     style="<?= ($seats_left<=5&&$seats_left>0)?'margin-top:28px':'' ?>">
            <?php else: ?>
                <div class="fc-img-placeholder" style="<?= ($seats_left<=5&&$seats_left>0)?'margin-top:28px':'' ?>">✈</div>
            <?php endif; ?>

            <div class="fc-body">
                <div class="fc-info">
                    <div class="fc-title"><?= htmlspecialchars($flight['flight_name']) ?></div>

                    <div class="fc-tags">
                        <span class="tag tag-airline">
                            <?= htmlspecialchars($flight['airline_name']) ?>
                            &nbsp;·&nbsp;
                            <?= htmlspecialchars($flight['flight_code']) ?>
                        </span>
                        <span class="tag <?= $stop_cls_map[$stops] ?>"><?= $stop_lbl_map[$stops] ?></span>
                        <?php if ($seats_left > 0): ?>
                        <span class="tag tag-seat"><i class="fas fa-couch"></i> <?= $seats_left ?> seats</span>
                        <?php endif; ?>
                    </div>

                    <div class="fc-route">
                        <span class="fc-city"><?= htmlspecialchars($flight['departure']) ?></span>
                        <div class="fc-route-mid">
                            <span class="fc-duration"><?= htmlspecialchars($flight['duration']) ?></span>
                            <div class="fc-line"><span class="fc-plane">✈</span></div>
                        </div>
                        <span class="fc-city"><?= htmlspecialchars($flight['arrival']) ?></span>
                    </div>

                    <?php if ($dep_t || $dep_day): ?>
                    <div class="fc-times">
                        <div class="time-blk">
                            <?php if ($dep_t): ?><span class="time-val"><?= htmlspecialchars($dep_t) ?></span><?php endif; ?>
                            <?php if ($dep_day): ?><span class="time-day"><?= htmlspecialchars($dep_day) ?></span><?php endif; ?>
                            <span class="time-lbl">Departs</span>
                        </div>
                        <div class="time-arrow">→</div>
                        <div class="time-blk">
                            <?php if ($arr_t): ?><span class="time-val"><?= htmlspecialchars($arr_t) ?></span><?php endif; ?>
                            <?php if ($arr_day): ?><span class="time-day"><?= htmlspecialchars($arr_day) ?></span><?php endif; ?>
                            <span class="time-lbl">Arrives</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="fc-meta">
                        <?php if ($depart_date): ?>
                        <span class="fc-meta-item"><i class="fas fa-calendar-day"></i> <?= date('d M Y',strtotime($depart_date)) ?></span>
                        <?php endif; ?>
                        <span class="fc-meta-item"><i class="fas fa-users"></i>
                            <?= $adults ?> Adult<?= $adults>1?'s':'' ?><?= $children>0?', '.$children.' Child'.($children>1?'ren':''):'' ?>
                        </span>
                        <span class="fc-meta-item"><i class="fas fa-chair"></i> <?= htmlspecialchars($class) ?></span>
                        <span class="fc-meta-item"><i class="fas fa-rotate"></i> <?= ucfirst($trip_type) ?></span>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="fc-pricing">
                    <span class="price-lbl">Total Price</span>
                    <span class="price-amount">$<?= number_format($total_price, 0) ?></span>
                    <div>
                        <?php if (($flight['discount_pct'] ?? 0) > 0): ?>
                        <span class="price-original">$<?= number_format($flight['price'],0) ?></span>
                        <span class="price-discount">-<?= (int)$flight['discount_pct'] ?>%</span>
                        <?php endif; ?>
                        <div class="price-per">$<?= number_format($unit_price,0) ?> / person</div>
                    </div>

                    <?php if ($seats_left > 0 && $seats_left <= 10): ?>
                    <div class="seats-warning"><i class="fas fa-triangle-exclamation"></i> <?= $seats_left ?> left!</div>
                    <?php endif; ?>

                    <?php if ($is_logged_in): ?>
                    <form action="payment.php" method="POST" style="width:100%">
                        <input type="hidden" name="flight_id"   value="<?= $flight['id'] ?>">
                        <input type="hidden" name="trip_type"   value="<?= htmlspecialchars($trip_type) ?>">
                        <input type="hidden" name="from"        value="<?= htmlspecialchars($from) ?>">
                        <input type="hidden" name="to"          value="<?= htmlspecialchars($to) ?>">
                        <input type="hidden" name="depart_date" value="<?= htmlspecialchars($depart_date) ?>">
                        <input type="hidden" name="adults"      value="<?= $adults ?>">
                        <input type="hidden" name="children"    value="<?= $children ?>">
                        <input type="hidden" name="class"       value="<?= htmlspecialchars($class) ?>">
                        <input type="hidden" name="total_price" value="<?= $total_price ?>">
                        <button type="submit" class="book-btn">
                            <i class="fas fa-ticket"></i> Book Now
                        </button>
                    </form>
                    <?php else: ?>
                    <a href="login.php" class="book-btn login-req">
                        <i class="fas fa-arrow-right-to-bracket"></i> Login to Book
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <div class="no-filter-msg" id="noFilterMsg">
            <p style="font-size:1.5rem; margin-bottom:10px;">🔍</p>
            <p><strong>No flights match your current filters.</strong></p>
            <p>Try adjusting or clearing the filters on the left.</p>
        </div>

        <?php else: ?>
        <div class="no-results">
            <span class="nr-icon">✈</span>
            <h3>No flights found</h3>
            <p>No flights found from <strong><?= htmlspecialchars($from) ?></strong> to <strong><?= htmlspecialchars($to) ?></strong>.<br>Try different cities or travel dates.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /page-body -->

<?php else: ?>

<!-- Popular routes (pre-search) -->
<div class="popular-wrap">
    <h2 class="popular-heading">Popular <em>Routes</em></h2>
    <p class="popular-sub">Frequently booked destinations from our travellers</p>
    <div class="popular-grid">
        <?php
        $routes = [
            ['Dhaka','Chittagong','3,200'],
            ['Dhaka','Sylhet','3,800'],
            ['Dhaka','Rajshahi','3,400'],
            ['Chittagong','Dhaka','3,200'],
            ['Sylhet','Dhaka','3,800'],
            ['Rajshahi','Sylhet','4,200'],
        ];
        foreach ($routes as [$f,$t,$p]):
        ?>
        <div class="popular-card" onclick="quickSearch('<?= $f ?>','<?= $t ?>')">
            <div class="pr-cities"><?= $f ?> → <?= $t ?></div>
            <div class="pr-price">From $<?= $p ?></div>
            <div class="pr-bg">✈</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>

<script>
/* ═══════════════════════════
   STATE
═══════════════════════════ */
let activeStops = new Set(), activeAirlines = new Set(), activeSeatBuckets = new Set();
let maxPrice = <?= $max_price ?>, curPrice = <?= $max_price ?>;

/* SWAP */
function swapCities() {
    const f = document.getElementById('fromInput'), t = document.getElementById('toInput');
    [f.value, t.value] = [t.value, f.value];
    [f, t].forEach(el => { el.style.transition='background .3s'; el.style.background='rgba(184,135,42,.12)'; setTimeout(()=>el.style.background='',300); });
}

/* RETURN DATE */
function toggleReturn(r) {
    const g = document.getElementById('returnGroup');
    g.style.display = r.value==='return' ? '' : 'none';
    document.getElementById('returnDate').required = r.value==='return';
}

/* PASSENGER DROPDOWN */
let adults = <?= $adults ?>, children = <?= $children ?>;
function togglePax() {
    const dd = document.getElementById('paxDropdown'), tr = document.getElementById('paxTrigger');
    dd.classList.toggle('open'); tr.classList.toggle('open');
}
function changePax(t, d) {
    if (t==='adults')   adults   = Math.max(1, Math.min(9, adults   + d));
    else                children = Math.max(0, Math.min(9, children + d));
    document.getElementById(t+'Display').textContent  = t==='adults'?adults:children;
    document.getElementById(t+'Hidden').value         = t==='adults'?adults:children;
    syncPaxSummary();
}
function syncClass() {
    document.querySelectorAll('.pax-class-opt').forEach(el => {
        el.classList.toggle('active', el.querySelector('input').checked);
    });
    syncPaxSummary();
}
function syncPaxSummary() {
    const cls = document.querySelector('input[name="class"]:checked')?.value || 'Economy';
    let s = adults + ' Adult' + (adults>1?'s':'');
    if (children>0) s += ', '+children+' Child'+(children>1?'ren':'');
    s += ' · ' + cls;
    document.getElementById('paxSummary').textContent = s;
}
document.addEventListener('click', e => {
    if (!e.target.closest('.pax-wrap')) {
        document.getElementById('paxDropdown')?.classList.remove('open');
        document.getElementById('paxTrigger')?.classList.remove('open');
    }
});

/* STICKY BAR */
const heroEl = document.querySelector('.hero');
const stickyEl = document.getElementById('stickyBar');
if (heroEl && stickyEl) {
    new IntersectionObserver(([e]) => stickyEl.classList.toggle('visible', !e.isIntersecting), {threshold:0}).observe(heroEl);
}

/* SEARCH LOADING */
document.getElementById('searchForm')?.addEventListener('submit', () => {
    const btn = document.getElementById('searchBtn');
    if (btn) { btn.style.opacity='.6'; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Searching...'; }
});

/* DEPART → min return */
document.getElementById('departDate')?.addEventListener('change', function() {
    const r = document.getElementById('returnDate');
    if (r) r.min = this.value;
});

/* STOPS */
function toggleStop(val, el) {
    activeStops.has(val) ? (activeStops.delete(val), el.classList.remove('active'))
                         : (activeStops.add(val),    el.classList.add('active'));
    applyFilters();
}

/* AIRLINES */
function toggleAirline(name, el) {
    activeAirlines.has(name) ? (activeAirlines.delete(name), el.classList.remove('active'))
                             : (activeAirlines.add(name),    el.classList.add('active'));
    applyFilters();
}

/* SEATS */
function toggleSeat(key, el) {
    activeSeatBuckets.has(key) ? (activeSeatBuckets.delete(key), el.classList.remove('active'))
                               : (activeSeatBuckets.add(key),    el.classList.add('active'));
    applyFilters();
}

/* PRICE */
function filterByPrice(val) {
    curPrice = parseInt(val);
    const pd = document.getElementById('priceDisplay');
    if (pd) pd.textContent = '$' + parseInt(val).toLocaleString();
    const pr = document.getElementById('priceRange');
    if (pr) { const p = ((curPrice-parseFloat(pr.min))/(parseFloat(pr.max)-parseFloat(pr.min)))*100; pr.style.setProperty('--range-pct', p+'%'); }
    applyFilters();
}

/* APPLY */
function applyFilters() {
    let visible = 0;
    document.querySelectorAll('#flightList .flight-card').forEach(card => {
        const ok = (activeStops.size===0      || activeStops.has(parseInt(card.dataset.stops)))
                && (activeAirlines.size===0   || activeAirlines.has(card.dataset.airline))
                && (parseFloat(card.dataset.price) <= curPrice)
                && (activeSeatBuckets.size===0 || activeSeatBuckets.has(card.dataset.seatBucket));
        card.classList.toggle('hidden', !ok);
        if (ok) visible++;
    });
    const hasF = activeStops.size>0 || activeAirlines.size>0 || activeSeatBuckets.size>0 || curPrice < maxPrice;
    const vc = document.getElementById('visibleCount');
    if (vc) vc.textContent = hasF ? '(showing '+visible+')' : '';
    const nm = document.getElementById('noFilterMsg');
    if (nm) nm.style.display = (hasF && visible===0) ? 'block' : 'none';
}

/* SORT */
function sortFlights(type, btn) {
    document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const list = document.getElementById('flightList');
    if (!list) return;
    const cards = Array.from(list.querySelectorAll('.flight-card'));
    cards.sort((a, b) => {
        if (type==='price_asc')  return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
        if (type==='price_desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
        if (type==='duration')   return parseInt(a.dataset.durationMins) - parseInt(b.dataset.durationMins);
        if (type==='seats')      return parseInt(b.dataset.seats) - parseInt(a.dataset.seats);
    });
    cards.forEach((c, i) => { c.style.animationDelay = (i*0.04)+'s'; list.appendChild(c); });
}

/* CLEAR */
function clearFilters() {
    activeStops.clear(); activeAirlines.clear(); activeSeatBuckets.clear();
    curPrice = maxPrice;
    document.querySelectorAll('.stop-item,.al-item,.seat-item').forEach(el => el.classList.remove('active'));
    const pr = document.getElementById('priceRange');
    if (pr) { pr.value = maxPrice; pr.style.setProperty('--range-pct','100%'); }
    const pd = document.getElementById('priceDisplay');
    if (pd) pd.textContent = '$' + maxPrice.toLocaleString();
    applyFilters();
}

/* INIT RANGE */
const prInit = document.getElementById('priceRange');
if (prInit) { prInit.value = maxPrice; prInit.style.setProperty('--range-pct','100%'); }

/* POPULAR ROUTES */
function quickSearch(from, to) {
    document.querySelector('input[name="from"]').value = from;
    document.querySelector('input[name="to"]').value   = to;
    document.getElementById('searchForm').submit();
}

/* MOBILE FILTER BTN */
if (window.innerWidth <= 900) {
    const mfb = document.querySelector('.mobile-filter-btn');
    if (mfb) mfb.style.display = 'flex';
}
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?>