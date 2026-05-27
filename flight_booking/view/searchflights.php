<?php
session_start();
include("../model/db_conn.php");
include("../includes/header.php");

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
        $from_pat = "%" . $from . "%";
        $to_pat   = "%" . $to   . "%";
        $stmt = $conn->prepare("SELECT * FROM flights WHERE departure LIKE ? AND arrival LIKE ? AND (seat IS NULL OR seat > 0) ORDER BY price ASC");
        $stmt->bind_param("ss", $from_pat, $to_pat);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $flights[] = $row;
        }
        $stmt->close();
    }
}

// Build city list for datalist autocomplete
$city_rows = $conn->query("SELECT DISTINCT departure FROM flights UNION SELECT DISTINCT arrival FROM flights ORDER BY departure");
$cities = [];
while ($cr = $city_rows->fetch_assoc()) {
    $cities[] = $cr['departure'];
}

// Derive stops from duration string for filtering (Non-stop < 2h, 1 stop 2-5h, 2+ stop >5h)
function getStops($duration) {
    preg_match('/(\d+)\s*h/', $duration, $h);
    preg_match('/(\d+)\s*m/', $duration, $m);
    $total_mins = (isset($h[1]) ? (int)$h[1] * 60 : 0) + (isset($m[1]) ? (int)$m[1] : 0);
    if ($total_mins < 120)  return 0;
    if ($total_mins < 300)  return 1;
    return 2;
}

// Build airline list with min prices for sidebar
$airline_prices = [];
foreach ($flights as $f) {
    $a = $f['airline_name'];
    if (!isset($airline_prices[$a]) || $f['price'] < $airline_prices[$a]) {
        $airline_prices[$a] = $f['price'];
    }
}
asort($airline_prices);

// Seat capacity buckets
$seat_buckets = ['0-50' => false, '51-150' => false, '151-300' => false, '300+' => false];
foreach ($flights as $f) {
    $s = (int)($f['seat'] ?? 0);
    if ($s <= 50)        $seat_buckets['0-50']    = true;
    elseif ($s <= 150)   $seat_buckets['51-150']  = true;
    elseif ($s <= 300)   $seat_buckets['151-300'] = true;
    else                 $seat_buckets['300+']    = true;
}

$min_price = count($flights) ? (int)min(array_column($flights, 'price')) : 0;
$max_price = count($flights) ? (int)max(array_column($flights, 'price')) : 10000;
$total_passengers = $adults + $children;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Search Flights</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:      #1a6ff4;
            --primary-dark: #0d4fc4;
            --primary-glow: rgba(26, 111, 244, 0.22);
            --secondary:    #0a2d6e;
            --accent:       #06c8a0;
            --warn:         #f59e0b;
            --danger:       #ef4444;
            --dark:         #0d1f35;
            --mid:          #3d5a7a;
            --muted:        #7a95b0;
            --border:       #dce8f5;
            --surface:      #ffffff;
            --bg:           #eef4fd;
            --card-shadow:  0 4px 24px rgba(13, 31, 53, 0.08);
            --card-hover:   0 12px 40px rgba(26, 111, 244, 0.14);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--dark);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── HERO SEARCH SECTION ── */
        .search-section {
            background-image: url("https://images.unsplash.com/photo-1587019158091-1a103c5dd17f?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D");
            background-size: cover;
            background-position: center 40%;
            position: relative;
            padding: 72px 20px 80px;
        }
        .search-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(9,28,72,0.82) 0%, rgba(11,57,115,0.70) 60%, rgba(6,200,160,0.18) 100%);
            z-index: 1;
        }
        .search-section::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 0; right: 0;
            height: 60px;
            background: var(--bg);
            clip-path: ellipse(55% 100% at 50% 100%);
            z-index: 2;
        }
        .search-section h2 {
            color: #fff;
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 8px;
            font-weight: 900;
            letter-spacing: -1px;
            position: relative;
            z-index: 3;
            text-shadow: 0 3px 20px rgba(0,0,0,0.35);
        }
        .search-section .hero-sub {
            color: rgba(255,255,255,0.75);
            text-align: center;
            font-size: 1rem;
            margin-bottom: 32px;
            position: relative;
            z-index: 3;
            font-weight: 400;
        }
        .search-form {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 22px;
            padding: 28px 30px;
            max-width: 1100px;
            margin: 0 auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.22), 0 0 0 1px rgba(255,255,255,0.3);
            position: relative;
            z-index: 3;
        }
        .trip-type-row {
            display: flex;
            gap: 6px;
            margin-bottom: 22px;
            background: #f0f5ff;
            border-radius: 12px;
            padding: 5px;
            width: fit-content;
        }
        .trip-type-row label {
            display: flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            font-weight: 600;
            color: var(--mid);
            font-size: 0.88rem;
            padding: 7px 18px;
            border-radius: 9px;
            transition: all 0.2s;
            user-select: none;
        }
        .trip-type-row label:has(input:checked) {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 3px 10px var(--primary-glow);
        }
        .trip-type-row input[type="radio"] { display: none; }

        .form-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 130px; }
        .form-group label {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-group input, .form-group select {
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 11px;
            font-size: 0.93rem;
            font-family: inherit;
            transition: all 0.22s;
            background: #f8fbff;
            color: var(--dark);
        }
        .form-group input::placeholder { color: #aabdd4; }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
            background: #fff;
            outline: none;
            box-shadow: 0 0 0 3.5px rgba(26, 111, 244, 0.13);
        }
        .search-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 11px 30px;
            border-radius: 11px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s;
            white-space: nowrap;
            height: 46px;
            align-self: flex-end;
            box-shadow: 0 5px 18px var(--primary-glow);
            letter-spacing: 0.3px;
        }
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(26, 111, 244, 0.35);
            filter: brightness(1.07);
        }
        .search-btn:active { transform: translateY(0); filter: brightness(0.97); }

        /* ── MAIN LAYOUT ── */
        .main-layout {
            max-width: 1240px;
            margin: 48px auto 0;
            padding: 0 20px 100px;
            display: grid;
            grid-template-columns: 290px 1fr;
            gap: 28px;
        }

        /* ── SIDEBAR & FILTERS ── */
        .sidebar { display: flex; flex-direction: column; gap: 16px; }
        .filter-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: box-shadow 0.2s;
        }
        .filter-card:hover { box-shadow: 0 8px 32px rgba(13,31,53,0.10); }
        .filter-card-header {
            background: linear-gradient(90deg, #f0f6ff 0%, #f8fbff 100%);
            color: var(--secondary);
            padding: 13px 18px;
            font-weight: 700;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--border);
            letter-spacing: 0.2px;
            text-transform: uppercase;
            font-size: 0.75rem;
        }
        .filter-card-body { padding: 16px 18px; }

        /* STOP OPTIONS */
        .stop-options { display: flex; flex-direction: column; gap: 9px; }
        .stop-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 11px;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fbff;
            color: var(--dark);
            user-select: none;
        }
        .stop-btn:hover {
            border-color: var(--primary);
            background: rgba(26, 111, 244, 0.04);
            transform: translateX(2px);
        }
        .stop-btn.active {
            border-color: var(--primary);
            background: linear-gradient(90deg, rgba(26,111,244,0.08) 0%, rgba(26,111,244,0.04) 100%);
            box-shadow: inset 3px 0 0 var(--primary);
            font-weight: 600;
        }
        .stop-btn input[type="checkbox"] { display: none; }
        .stop-left { display: flex; align-items: center; gap: 11px; }
        .stop-dot-wrap { display: flex; gap: 3px; align-items: center; }
        .dot {
            width: 9px; height: 9px; border-radius: 50%;
            background: var(--primary);
            box-shadow: 0 0 0 2px rgba(26,111,244,0.15);
        }
        .dot.empty { background: #d1dce8; border: 1.5px solid #b0c4d8; box-shadow: none; }
        .stop-label { font-size: 0.88rem; }
        .stop-count {
            font-size: 0.75rem;
            color: var(--muted);
            background: #eef4fd;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* AIRLINE FILTER */
        .airline-list { display: flex; flex-direction: column; gap: 6px; max-height: 230px; overflow-y: auto; padding-right: 2px; }
        .airline-list::-webkit-scrollbar { width: 4px; }
        .airline-list::-webkit-scrollbar-track { background: transparent; }
        .airline-list::-webkit-scrollbar-thumb { background: rgba(26,111,244,0.18); border-radius: 4px; }
        .airline-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            gap: 10px;
            border: 1px solid transparent;
        }
        .airline-item:hover { background: rgba(26,111,244,0.05); border-color: rgba(26,111,244,0.1); }
        .airline-item.active {
            background: rgba(26,111,244,0.08);
            border-color: rgba(26,111,244,0.2);
            box-shadow: inset 3px 0 0 var(--primary);
        }
        .airline-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            flex: 1;
            font-size: 0.88rem;
            color: var(--dark);
            font-weight: 500;
        }
        .airline-logo {
            width: 32px; height: 32px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-size: 0.68rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 3px 8px var(--primary-glow);
            letter-spacing: 0.5px;
        }
        .airline-min-price { font-size: 0.82rem; font-weight: 700; color: var(--primary); white-space: nowrap; }

        /* SEAT CAPACITY */
        .seat-options { display: flex; flex-direction: column; gap: 8px; }
        .seat-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 11px;
            cursor: pointer;
            transition: all 0.22s;
            background: #f8fbff;
            color: var(--dark);
            user-select: none;
        }
        .seat-item:hover {
            border-color: var(--primary);
            background: rgba(26,111,244,0.04);
            transform: translateX(2px);
        }
        .seat-item.active {
            border-color: var(--primary);
            background: linear-gradient(90deg, rgba(26,111,244,0.08) 0%, rgba(26,111,244,0.03) 100%);
            box-shadow: inset 3px 0 0 var(--primary);
            font-weight: 600;
        }
        .seat-item input[type="checkbox"] { display: none; }
        .seat-label { font-size: 0.88rem; display: flex; align-items: center; gap: 8px; }
        .seat-label .seat-icon { font-size: 1rem; }
        .seat-range-tag {
            font-size: 0.72rem;
            color: var(--muted);
            background: #eef4fd;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* PRICE RANGE */
        .price-range-wrap { padding: 4px 0; }
        .price-range-labels {
            display: flex;
            justify-content: space-between;
            margin-bottom: 14px;
            font-size: 0.82rem;
            color: var(--muted);
        }
        .price-range-labels span { font-weight: 700; color: var(--primary); }
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            cursor: pointer;
            background: linear-gradient(to right, var(--primary) 0%, var(--primary) var(--range-pct, 100%), #dce8f5 var(--range-pct, 100%), #dce8f5 100%);
            border-radius: 6px;
            outline: none;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px; height: 20px;
            border-radius: 50%;
            background: var(--surface);
            border: 2.5px solid var(--primary);
            box-shadow: 0 2px 8px var(--primary-glow);
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.15);
            box-shadow: 0 3px 14px rgba(26,111,244,0.35);
        }
        input[type="range"]::-moz-range-thumb {
            width: 20px; height: 20px;
            border-radius: 50%;
            background: var(--surface);
            border: 2.5px solid var(--primary);
            box-shadow: 0 2px 8px var(--primary-glow);
            cursor: pointer;
        }
        .price-display {
            text-align: center;
            margin-top: 14px;
            font-size: 0.88rem;
            color: var(--mid);
            background: #f0f6ff;
            padding: 7px 14px;
            border-radius: 10px;
            border: 1px solid rgba(26,111,244,0.12);
        }
        .price-display b { color: var(--primary); font-size: 1rem; font-weight: 800; }

        /* CLEAR FILTERS */
        .clear-btn {
            width: 100%;
            padding: 12px;
            background: transparent;
            border: 1.5px solid var(--danger);
            color: var(--danger);
            border-radius: 13px;
            font-weight: 700;
            font-size: 0.88rem;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s;
            letter-spacing: 0.3px;
        }
        .clear-btn:hover {
            background: var(--danger);
            color: white;
            box-shadow: 0 5px 18px rgba(239,68,68,0.22);
            transform: translateY(-1px);
        }

        /* ── RESULTS PANEL ── */
        .results-panel {}
        .results-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 14px;
        }
        .results-title {
            font-size: 1.35rem;
            color: var(--dark);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .results-count {
            background: linear-gradient(135deg, rgba(26,111,244,0.12) 0%, rgba(26,111,244,0.06) 100%);
            color: var(--primary);
            border: 1px solid rgba(26,111,244,0.2);
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .visible-count { font-size: 0.82rem; color: var(--muted); font-weight: 500; }

        /* SORT BAR */
        .sort-bar { display: flex; gap: 8px; flex-wrap: wrap; }
        .sort-btn {
            padding: 7px 16px;
            border-radius: 25px;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: inherit;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--muted);
            cursor: pointer;
            transition: all 0.22s;
        }
        .sort-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(26,111,244,0.04);
            transform: translateY(-1px);
        }
        .sort-btn.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 14px var(--primary-glow);
        }

        /* FLIGHT CARD */
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes swapFlash {
            0%   { background: rgba(26,111,244,0.15); }
            100% { background: #f8fbff; }
        }
        .swap-flash { animation: swapFlash 0.4s ease; }
        .flight-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            margin-bottom: 18px;
            overflow: hidden;
            display: flex;
            transition: box-shadow 0.25s, transform 0.25s, border-color 0.25s;
            box-shadow: var(--card-shadow);
            animation: cardIn 0.35s ease both;
            position: relative;
        }
        .flight-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
            background: linear-gradient(180deg, var(--primary) 0%, var(--accent) 100%);
            opacity: 0;
            transition: opacity 0.25s;
            border-radius: 20px 0 0 20px;
        }
        .flight-card:hover {
            box-shadow: var(--card-hover);
            transform: translateY(-3px);
            border-color: rgba(26,111,244,0.22);
        }
        .flight-card:hover::before { opacity: 1; }
        .flight-card.hidden { display: none; }

        .flight-img { width: 150px; min-height: 150px; object-fit: cover; flex-shrink: 0; }
        .flight-img-placeholder {
            width: 150px;
            background: linear-gradient(135deg, #e8f0fe 0%, #dbeafe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            flex-shrink: 0;
            border-right: 1px solid var(--border);
            color: var(--primary);
            position: relative;
            overflow: hidden;
        }
        .flight-img-placeholder::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 70%, rgba(26,111,244,0.08) 0%, transparent 70%);
        }
        .flight-body { flex: 1; padding: 20px 22px; display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
        .flight-info { flex: 1; min-width: 210px; }
        .flight-info h3 {
            font-size: 1.15rem;
            color: var(--dark);
            margin-bottom: 9px;
            font-weight: 800;
            letter-spacing: -0.3px;
        }

        .tags-row { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .tag {
            font-size: 0.72rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 700;
            border: 1px solid transparent;
            letter-spacing: 0.2px;
        }
        .tag-airline {
            background: rgba(26,111,244,0.08);
            color: var(--primary);
            border-color: rgba(26,111,244,0.18);
        }
        .tag-stop-0 {
            background: rgba(6,200,160,0.1);
            color: #059669;
            border-color: rgba(6,200,160,0.25);
        }
        .tag-stop-1 {
            background: rgba(245,158,11,0.1);
            color: #b45309;
            border-color: rgba(245,158,11,0.25);
        }
        .tag-stop-2 {
            background: rgba(239,68,68,0.08);
            color: #dc2626;
            border-color: rgba(239,68,68,0.2);
        }
        .tag-seat {
            background: #f0f5ff;
            color: var(--mid);
            border-color: var(--border);
        }

        /* Route timeline */
        .route {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 14px;
        }
        .route .city {
            font-weight: 800;
            font-size: 1.15rem;
            color: var(--dark);
            letter-spacing: -0.5px;
        }
        .route-line {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 12px;
            gap: 3px;
        }
        .route-line .duration-badge {
            font-size: 0.72rem;
            color: var(--muted);
            background: #f0f5ff;
            padding: 2px 10px;
            border-radius: 20px;
            border: 1px solid var(--border);
            font-weight: 600;
            white-space: nowrap;
        }
        .route-line .line-track {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0;
        }
        .route-line .line-track::before,
        .route-line .line-track::after {
            content: '';
            flex: 1;
            height: 1.5px;
            background: linear-gradient(90deg, var(--border), var(--primary));
        }
        .route-line .line-track::after {
            background: linear-gradient(90deg, var(--primary), var(--border));
        }
        .route-line .plane-icon {
            color: var(--primary);
            font-size: 1rem;
            transform: rotate(0deg);
        }

        .flight-meta { font-size: 0.78rem; color: var(--muted); display: flex; flex-wrap: wrap; gap: 10px; }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--muted);
            background: #f8fbff;
            padding: 3px 9px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-weight: 500;
        }

        /* PRICING PANEL */
        .flight-pricing {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
            min-width: 170px;
            padding: 20px 18px;
            background: linear-gradient(160deg, #f5f9ff 0%, #eef4fd 100%);
            border-left: 1px solid var(--border);
        }
        .price-label { font-size: 0.7rem; color: var(--muted); margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600; }
        .price-amount {
            font-size: 1.9rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 2px;
            letter-spacing: -1px;
            line-height: 1;
        }
        .per-person { font-size: 0.72rem; color: var(--muted); margin-bottom: 14px; font-weight: 500; }
        .seats-left {
            font-size: 0.76rem;
            color: #b45309;
            margin-bottom: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(245,158,11,0.1);
            padding: 3px 10px;
            border-radius: 8px;
            border: 1px solid rgba(245,158,11,0.2);
        }

        .book-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: 0 4px 14px var(--primary-glow);
            width: 100%;
            letter-spacing: 0.3px;
        }
        .book-btn:hover {
            box-shadow: 0 7px 22px rgba(26,111,244,0.35);
            transform: translateY(-2px);
            filter: brightness(1.07);
        }
        .book-btn:active { transform: translateY(0); }
        .book-btn.login-required {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 4px 14px rgba(245,158,11,0.25);
        }
        .book-btn.login-required:hover {
            box-shadow: 0 7px 22px rgba(245,158,11,0.35);
        }

        /* NO RESULTS */
        .no-results {
            text-align: center;
            padding: 70px 40px;
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            color: var(--muted);
            box-shadow: var(--card-shadow);
        }
        .no-results .icon { font-size: 4rem; margin-bottom: 20px; display: block; }
        .no-results p { font-size: 1rem; line-height: 1.6; }
        .no-results b { color: var(--dark); }
        .no-filter-results {
            text-align: center;
            padding: 50px;
            background: #fffdf5;
            border-radius: 18px;
            color: var(--dark);
            border: 2px dashed rgba(245,158,11,0.4);
            display: none;
        }
        .no-filter-results .icon { font-size: 2.5rem; margin-bottom: 12px; display: block; }

        /* POPULAR ROUTES */
        .popular-section { max-width: 1100px; margin: 50px auto 70px; padding: 0 20px; }
        .popular-section h2 {
            font-size: 1.55rem;
            color: var(--dark);
            margin-bottom: 24px;
            padding-left: 16px;
            font-weight: 800;
            position: relative;
            letter-spacing: -0.5px;
        }
        .popular-section h2::before {
            content: '';
            position: absolute;
            left: 0; top: 4px; bottom: 4px;
            width: 4px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 4px;
        }
        .popular-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 18px; }
        .popular-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 24px 20px;
            box-shadow: var(--card-shadow);
            cursor: pointer;
            transition: all 0.25s ease-in-out;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .popular-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
        }
        .popular-card::after {
            content: '✈';
            position: absolute;
            bottom: -10px; right: 10px;
            font-size: 4rem;
            opacity: 0.04;
            color: var(--primary);
            transition: opacity 0.25s, transform 0.25s;
        }
        .popular-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover);
            border-color: rgba(26,111,244,0.2);
        }
        .popular-card:hover::after { opacity: 0.08; transform: translateX(-4px); }
        .popular-card .route-text { font-weight: 800; color: var(--dark); margin-bottom: 8px; font-size: 1rem; letter-spacing: -0.3px; }
        .popular-card .route-price { color: var(--primary); font-size: 0.92rem; font-weight: 700; }

        /* ── SWAP BUTTON ── */
        .swap-btn {
            width: 38px; height: 38px; flex-shrink: 0;
            border-radius: 50%; border: 1.5px solid var(--border);
            background: var(--surface); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.22s;
            align-self: flex-end; margin-bottom: 1px;
            box-shadow: 0 2px 8px rgba(13,31,53,0.06);
        }
        .swap-btn:hover {
            background: var(--primary); color: #fff;
            border-color: var(--primary);
            transform: rotate(180deg);
            box-shadow: 0 4px 14px var(--primary-glow);
        }

        /* ── FORM ROWS ── */
        .form-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 12px; }
        .form-row:last-child { margin-bottom: 0; }
        .form-row-2 { align-items: flex-end; }
        .route-group { flex: 2; min-width: 160px; }
        .date-group  { flex: 1.2; min-width: 140px; }

        /* ── PASSENGER DROPDOWN ── */
        .pax-group { flex: 2; min-width: 220px; position: relative; }
        .pax-trigger {
            display: flex; align-items: center; justify-content: space-between;
            padding: 11px 14px; border: 1.5px solid var(--border);
            border-radius: 11px; background: #f8fbff; cursor: pointer;
            font-size: 0.93rem; color: var(--dark); transition: all 0.22s;
            user-select: none;
        }
        .pax-trigger:hover { border-color: var(--primary); background: #fff; }
        .pax-trigger.open  { border-color: var(--primary); background: #fff;
            box-shadow: 0 0 0 3.5px rgba(26,111,244,0.13); }
        .pax-dropdown {
            position: absolute; top: calc(100% + 8px); left: 0; right: 0;
            background: var(--surface); border: 1.5px solid var(--border);
            border-radius: 14px; padding: 16px;
            box-shadow: 0 12px 40px rgba(13,31,53,0.14);
            z-index: 50; display: none;
        }
        .pax-dropdown.open { display: block; }
        .pax-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid var(--border);
        }
        .pax-row:last-of-type { border-bottom: none; }
        .pax-info { display: flex; flex-direction: column; gap: 2px; }
        .pax-type { font-size: 0.88rem; font-weight: 700; color: var(--dark); }
        .pax-sub  { font-size: 0.72rem; color: var(--muted); }
        .pax-counter { display: flex; align-items: center; gap: 12px; }
        .pax-counter button {
            width: 30px; height: 30px; border-radius: 50%;
            border: 1.5px solid var(--border); background: var(--surface);
            color: var(--primary); font-size: 1.1rem; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.18s; line-height: 1;
        }
        .pax-counter button:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .pax-counter span { font-size: 1rem; font-weight: 800; color: var(--dark); min-width: 20px; text-align: center; }
        .pax-class-row { display: flex; gap: 8px; padding: 12px 0 4px; }
        .pax-class-opt {
            flex: 1; text-align: center; padding: 8px;
            border: 1.5px solid var(--border); border-radius: 9px;
            font-size: 0.85rem; font-weight: 600; color: var(--mid);
            cursor: pointer; transition: all 0.18s; user-select: none;
        }
        .pax-class-opt input { display: none; }
        .pax-class-opt.active, .pax-class-opt:has(input:checked) {
            background: var(--primary); color: #fff; border-color: var(--primary);
        }
        .pax-done {
            width: 100%; margin-top: 12px; padding: 9px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; border: none; border-radius: 9px;
            font-size: 0.88rem; font-weight: 700; font-family: inherit;
            cursor: pointer; transition: all 0.2s;
        }
        .pax-done:hover { filter: brightness(1.07); }

        /* ── SEARCH BTN ── */
        .search-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; border: none; padding: 11px 28px;
            border-radius: 11px; font-size: 0.95rem; font-weight: 700;
            font-family: inherit; cursor: pointer; transition: all 0.25s;
            white-space: nowrap; height: 46px; align-self: flex-end;
            box-shadow: 0 5px 18px var(--primary-glow); letter-spacing: 0.3px;
            display: flex; align-items: center; gap: 8px;
        }
        .search-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,111,244,0.35); filter: brightness(1.07); }
        .search-btn:active { transform: translateY(0); }
        .search-btn.loading { opacity: 0.75; pointer-events: none; }

        /* ── STICKY SEARCH BAR (on scroll after results) ── */
        .sticky-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            background: var(--secondary);
            padding: 10px 24px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            transform: translateY(-100%);
            transition: transform 0.3s ease;
        }
        .sticky-bar.visible { transform: translateY(0); }
        .sticky-route {
            font-size: 1rem; font-weight: 800; color: #fff; letter-spacing: -0.3px;
        }
        .sticky-meta { font-size: 0.8rem; color: rgba(255,255,255,0.6); }
        .sticky-modify {
            margin-left: auto; background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3); color: #fff;
            padding: 7px 18px; border-radius: 8px; font-size: 0.82rem;
            font-weight: 700; cursor: pointer; text-decoration: none;
            transition: background 0.2s;
        }
        .sticky-modify:hover { background: rgba(255,255,255,0.25); }

        /* ── RESULTS SUMMARY BAR ── */
        .results-summary {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 14px 20px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(13,31,53,0.05);
        }
        .rs-route { font-size: 1.1rem; font-weight: 800; color: var(--dark); letter-spacing: -0.3px; }
        .rs-arrow  { color: var(--primary); font-size: 1rem; }
        .rs-pill {
            background: #f0f5ff; color: var(--mid); border: 1px solid var(--border);
            padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600;
        }
        .rs-pill.highlight { background: rgba(26,111,244,0.08); color: var(--primary); border-color: rgba(26,111,244,0.2); }

        /* ── FLIGHT CARD ENHANCEMENTS ── */
        .flight-card-inner {
            display: flex; flex-direction: column; gap: 0; width: 100%;
        }
        .urgency-bar {
            background: linear-gradient(90deg, rgba(245,158,11,0.12), rgba(245,158,11,0.05));
            border-bottom: 1px solid rgba(245,158,11,0.2);
            padding: 5px 22px;
            font-size: 0.72rem; font-weight: 700; color: #b45309;
            display: flex; align-items: center; gap: 6px;
        }

        /* MOBILE */
        @media (max-width: 860px) {
            .swap-btn { display: none; }
            .route-group, .date-group { min-width: 120px; }
        }
                display: flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
                color: white;
                border: none;
                padding: 11px 22px;
                border-radius: 12px;
                font-weight: 700;
                font-family: inherit;
                cursor: pointer;
                margin-bottom: 20px;
                font-size: 0.92rem;
                box-shadow: 0 4px 14px var(--primary-glow);
            }
            .search-section h2 { font-size: 1.8rem; }
        }
        @media (min-width: 861px) {
            .mobile-filter-btn { display: none; }
        }
        @media (max-width: 520px) {
            .flight-card { flex-direction: column; }
            .flight-img, .flight-img-placeholder { width: 100%; height: 150px; border-right: none; border-bottom: 1px solid var(--border); }
            .flight-pricing {
                border-left: none;
                border-top: 1px solid var(--border);
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                width: 100%;
                background: linear-gradient(90deg, #f5f9ff 0%, #eef4fd 100%);
            }
            .book-btn { width: auto; min-width: 120px; }
            .search-section h2 { font-size: 1.5rem; }
            .search-form { padding: 20px 16px; }
        }
    </style>
</head>
<body>

<!-- SEARCH -->
<div class="search-section">
    <h2>✈️ Search Available Flights</h2>
    <p class="hero-sub">Find the best deals on flights across Bangladesh and beyond</p>

    <!-- City datalist for autocomplete -->
    <datalist id="cityList">
        <?php foreach ($cities as $city): ?>
        <option value="<?= htmlspecialchars($city) ?>">
        <?php endforeach; ?>
    </datalist>

    <form class="search-form" action="" method="POST" id="searchForm">
        <div class="trip-type-row">
            <label><input type="radio" name="trip_type" value="one-way" <?= $trip_type=='one-way' ? 'checked':'' ?> onchange="toggleReturnDate(this)"> One Way</label>
            <label><input type="radio" name="trip_type" value="return"  <?= $trip_type=='return'  ? 'checked':'' ?> onchange="toggleReturnDate(this)"> Return</label>
        </div>

        <!-- Row 1: Route + dates -->
        <div class="form-row">
            <div class="form-group route-group">
                <label>From</label>
                <input type="text" name="from" id="fromInput" placeholder="City or airport"
                       value="<?= htmlspecialchars($from) ?>" list="cityList" autocomplete="off" required>
            </div>

            <button type="button" class="swap-btn" onclick="swapCities()" title="Swap cities">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 16V4m0 0L3 8m4-4l4 4"/><path d="M17 8v12m0 0l4-4m-4 4l-4-4"/>
                </svg>
            </button>

            <div class="form-group route-group">
                <label>To</label>
                <input type="text" name="to" id="toInput" placeholder="City or airport"
                       value="<?= htmlspecialchars($to) ?>" list="cityList" autocomplete="off" required>
            </div>

            <div class="form-group date-group">
                <label>Depart</label>
                <input type="date" name="depart_date" id="departDate"
                       value="<?= htmlspecialchars($depart_date) ?>"
                       min="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group date-group" id="returnDateGroup" style="<?= $trip_type==='return' ? '' : 'display:none' ?>">
                <label>Return</label>
                <input type="date" name="return_date" id="returnDate"
                       value="<?= htmlspecialchars($return_date) ?>"
                       min="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <!-- Row 2: Passengers + class + search -->
        <div class="form-row form-row-2">
            <div class="form-group pax-group">
                <label>Passengers &amp; Class</label>
                <div class="pax-trigger" id="paxTrigger" onclick="togglePaxDropdown()">
                    <span id="paxSummary"><?= $adults ?> Adult<?= $adults>1?'s':'' ?><?= $children>0 ? ', '.$children.' Child'.($children>1?'ren':'') : '' ?> · <?= $class ?></span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="pax-dropdown" id="paxDropdown">
                    <div class="pax-row">
                        <div class="pax-info">
                            <span class="pax-type">Adults</span>
                            <span class="pax-sub">18+ years</span>
                        </div>
                        <div class="pax-counter">
                            <button type="button" onclick="changePax('adults',-1)">−</button>
                            <span id="adultsDisplay"><?= $adults ?></span>
                            <button type="button" onclick="changePax('adults',1)">+</button>
                        </div>
                    </div>
                    <div class="pax-row">
                        <div class="pax-info">
                            <span class="pax-type">Children</span>
                            <span class="pax-sub">Under 18</span>
                        </div>
                        <div class="pax-counter">
                            <button type="button" onclick="changePax('children',-1)">−</button>
                            <span id="childrenDisplay"><?= $children ?></span>
                            <button type="button" onclick="changePax('children',1)">+</button>
                        </div>
                    </div>
                    <div class="pax-class-row">
                        <label class="pax-class-opt <?= $class==='Economy'?'active':'' ?>">
                            <input type="radio" name="class" value="Economy" <?= $class==='Economy'?'checked':'' ?> onchange="updatePaxSummary()"> Economy
                        </label>
                        <label class="pax-class-opt <?= $class==='Business'?'active':'' ?>">
                            <input type="radio" name="class" value="Business" <?= $class==='Business'?'checked':'' ?> onchange="updatePaxSummary()"> Business
                        </label>
                    </div>
                    <input type="hidden" name="adults"   id="adultsInput"   value="<?= $adults ?>">
                    <input type="hidden" name="children" id="childrenInput" value="<?= $children ?>">
                    <button type="button" class="pax-done" onclick="togglePaxDropdown()">Done</button>
                </div>
            </div>

            <button type="submit" class="search-btn" id="searchBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Search Flights
            </button>
        </div>
    </form>
</div>

<?php if ($search_performed): ?>

<!-- STICKY SEARCH BAR -->
<div class="sticky-bar" id="stickyBar">
    <div>
        <div class="sticky-route">
            <?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?>
        </div>
        <div class="sticky-meta">
            <?= date('d M Y', strtotime($depart_date ?: 'today')) ?> &nbsp;·&nbsp;
            <?= $adults ?> Adult<?= $adults>1?'s':'' ?><?= $children>0?', '.$children.' Child'.($children>1?'ren':''):'' ?> &nbsp;·&nbsp;
            <?= $class ?>
        </div>
    </div>
    <a href="#" class="sticky-modify" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Modify Search</a>
</div>

<div class="main-layout">

    <!-- ══════════ LEFT SIDEBAR ══════════ -->
    <aside class="sidebar" id="sidebar">

        <!-- STOPS FILTER -->
        <div class="filter-card">
            <div class="filter-card-header">🔴 Stops</div>
            <div class="filter-card-body">
                <div class="stop-options">
                    <?php
                    $stop_labels = [
                        0 => ['label' => 'Non Stop', 'dots' => 1, 'filled' => 1],
                        1 => ['label' => '1 Stop',   'dots' => 2, 'filled' => 1],
                        2 => ['label' => '2+ Stops', 'dots' => 3, 'filled' => 1],
                    ];
                    $stop_counts = [0=>0, 1=>0, 2=>0];
                    foreach ($flights as $f) { $stop_counts[getStops($f['duration'])]++; }
                    foreach ($stop_labels as $val => $info):
                    ?>
                    <div class="stop-btn" id="stopBtn<?= $val ?>" onclick="toggleStop(<?= $val ?>, this)">
                        <input type="checkbox" id="stop<?= $val ?>">
                        <div class="stop-left">
                            <div class="stop-dot-wrap">
                                <?php for ($d = 0; $d < $info['dots']; $d++): ?>
                                    <div class="dot <?= $d >= $info['filled'] ? 'empty' : '' ?>"></div>
                                <?php endfor; ?>
                            </div>
                            <span class="stop-label"><?= $info['label'] ?></span>
                        </div>
                        <span class="stop-count"><?= $stop_counts[$val] ?> flight<?= $stop_counts[$val] != 1 ? 's' : '' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- PRICE RANGE FILTER -->
        <div class="filter-card">
            <div class="filter-card-header">💰 Price Range (per person)</div>
            <div class="filter-card-body">
                <div class="price-range-wrap">
                    <div class="price-range-labels">
                        <span>$<?= number_format($min_price) ?></span>
                        <span>$<?= number_format($max_price) ?></span>
                    </div>
                    <input type="range" id="priceRange"
                           min="<?= $min_price ?>" max="<?= $max_price ?>"
                           value="<?= $max_price ?>"
                           oninput="filterByPrice(this.value)">
                    <div class="price-display">
                        Up to <b id="priceDisplay">$<?= number_format($max_price) ?></b>
                    </div>
                </div>
            </div>
        </div>

        <!-- AIRLINES FILTER -->
        <?php if (count($airline_prices) > 0): ?>
        <div class="filter-card">
            <div class="filter-card-header">✈️ Airlines</div>
            <div class="filter-card-body">
                <div class="airline-list">
                    <?php foreach ($airline_prices as $airline => $min_p):
                        $initials = strtoupper(implode('', array_map(fn($w)=>$w[0], explode(' ', $airline))));
                        $initials = substr($initials, 0, 2);
                    ?>
                    <div class="airline-item" id="airlineItem_<?= md5($airline) ?>"
                         onclick="toggleAirline('<?= addslashes($airline) ?>', this)">
                        <label>
                            <input type="checkbox" id="airline_<?= md5($airline) ?>">
                            <div class="airline-logo"><?= htmlspecialchars($initials) ?></div>
                            <?= htmlspecialchars($airline) ?>
                        </label>
                        <span class="airline-min-price">$<?= number_format($min_p) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SEAT CAPACITY FILTER -->
        <div class="filter-card">
            <div class="filter-card-header">💺 Seat Capacity</div>
            <div class="filter-card-body">
                <div class="seat-options">
                    <?php
                    $seat_defs = [
                        '0-50'   => ['icon' => '🪑', 'label' => 'Small',  'desc' => 'Up to 50 seats'],
                        '51-150' => ['icon' => '✈️', 'label' => 'Medium', 'desc' => '51 – 150 seats'],
                        '151-300'=> ['icon' => '🛩️', 'label' => 'Large',  'desc' => '151 – 300 seats'],
                        '300+'   => ['icon' => '🛫', 'label' => 'Wide Body','desc' => '300+ seats'],
                    ];
                    foreach ($seat_defs as $key => $def):
                        if (!$seat_buckets[$key]) continue;
                    ?>
                    <div class="seat-item" id="seatItem_<?= $key ?>"
                         onclick="toggleSeat('<?= $key ?>', this)">
                        <input type="checkbox" id="seat_<?= $key ?>">
                        <span class="seat-label">
                            <span class="seat-icon"><?= $def['icon'] ?></span>
                            <?= $def['label'] ?>
                        </span>
                        <span class="seat-range-tag"><?= $def['desc'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- CLEAR ALL -->
        <button class="clear-btn" onclick="clearAllFilters()">✕ Clear All Filters</button>
    </aside>

    <!-- ══════════ RIGHT: RESULTS ══════════ -->
    <div class="results-panel">

        <!-- Mobile filter toggle -->
        <button class="mobile-filter-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">
            ⚙️ Filters
        </button>

        <div class="results-topbar">
            <!-- Results summary -->
            <div class="results-summary" style="width:100%">
                <span class="rs-route"><?= htmlspecialchars($from) ?></span>
                <span class="rs-arrow">→</span>
                <span class="rs-route"><?= htmlspecialchars($to) ?></span>
                <span class="rs-pill highlight"><?= count($flights) ?> flight<?= count($flights)!=1?'s':'' ?></span>
                <?php if ($depart_date): ?>
                <span class="rs-pill">📅 <?= date('d M Y', strtotime($depart_date)) ?></span>
                <?php endif; ?>
                <span class="rs-pill">👥 <?= $adults ?> Adult<?= $adults>1?'s':'' ?><?= $children>0?', '.$children.' Child'.($children>1?'ren':''):'' ?></span>
                <span class="rs-pill">💺 <?= $class ?></span>
                <span class="rs-pill">🔄 <?= ucfirst($trip_type) ?></span>
                <span class="visible-count" id="visibleCount" style="margin-left:auto"></span>
            </div>
            <div class="sort-bar">
                <button class="sort-btn active" onclick="sortFlights('price_asc', this)">💰 Cheapest</button>
                <button class="sort-btn" onclick="sortFlights('price_desc', this)">💎 Expensive</button>
                <button class="sort-btn" onclick="sortFlights('duration', this)">⏱️ Shortest</button>
                <button class="sort-btn" onclick="sortFlights('seats', this)">💺 Most Seats</button>
            </div>
        </div>

        <?php if (count($flights) > 0): ?>

        <div id="flightList">
            <?php foreach ($flights as $i => $flight):
                $stops      = getStops($flight['duration']);
                $total_price= $flight['price'] * $total_passengers;
                $seats_left = (int)($flight['seat'] ?? 0);
                $stop_labels_map = [0=>'Non Stop', 1=>'1 Stop', 2=>'2+ Stops'];
                $stop_tag_cls    = ['tag-stop-0','tag-stop-1','tag-stop-2'];
                $seat_bucket = $seats_left <= 50 ? '0-50' : ($seats_left <= 150 ? '51-150' : ($seats_left <= 300 ? '151-300' : '300+'));
            ?>
            <div class="flight-card"
                 data-price="<?= $flight['price'] ?>"
                 data-total="<?= $total_price ?>"
                 data-stops="<?= $stops ?>"
                 data-airline="<?= htmlspecialchars($flight['airline_name']) ?>"
                 data-seats="<?= $seats_left ?>"
                 data-seat-bucket="<?= $seat_bucket ?>"
                 data-duration-mins="<?php
                     preg_match('/(\d+)\s*h/', $flight['duration'], $hh);
                     preg_match('/(\d+)\s*m/', $flight['duration'], $mm);
                     echo (isset($hh[1])?(int)$hh[1]*60:0)+(isset($mm[1])?(int)$mm[1]:0);
                 ?>">

                <?php if ($seats_left > 0 && $seats_left <= 5): ?>
                <div class="urgency-bar">🔥 Only <?= $seats_left ?> seat<?= $seats_left>1?'s':'' ?> left at this price!</div>
                <?php endif; ?>

                <?php if (!empty($flight['image'])): ?>
                    <img class="flight-img" src="upload/<?= htmlspecialchars($flight['image']) ?>" alt="Flight">
                <?php else: ?>
                    <div class="flight-img-placeholder">✈️</div>
                <?php endif; ?>

                <div class="flight-body">
                    <div class="flight-info">
                        <h3><?= htmlspecialchars($flight['flight_name']) ?></h3>
                        <div class="tags-row">
                            <span class="tag tag-airline"><?= htmlspecialchars($flight['airline_name']) ?> · <?= htmlspecialchars($flight['flight_code']) ?></span>
                            <span class="tag <?= $stop_tag_cls[$stops] ?>"><?= $stop_labels_map[$stops] ?></span>
                            <?php if ($seats_left > 0): ?>
                            <span class="tag tag-seat">💺 <?= $seats_left ?> seats</span>
                            <?php endif; ?>
                        </div>
                        <div class="route">
                            <span class="city"><?= htmlspecialchars($flight['departure']) ?></span>
                            <div class="route-line">
                                <span class="duration-badge"><?= htmlspecialchars($flight['duration']) ?></span>
                                <div class="line-track"><span class="plane-icon">✈</span></div>
                            </div>
                            <span class="city"><?= htmlspecialchars($flight['arrival']) ?></span>
                        </div>
                        <div class="flight-meta">
                            <span class="meta-item">📅 <?= htmlspecialchars($depart_date) ?></span>
                            <span class="meta-item">👥 <?= $adults ?> Adult<?= $adults>1?'s':'' ?><?= $children>0?", $children Child".($children>1?'ren':''):'' ?></span>
                            <span class="meta-item">💺 <?= htmlspecialchars($class) ?></span>
                            <span class="meta-item">🔄 <?= ucfirst($trip_type) ?></span>
                        </div>
                    </div>

                    <div class="flight-pricing">
                        <div class="price-label">Total Price</div>
                        <div class="price-amount">$<?= number_format($total_price, 0) ?></div>
                        <div class="per-person">$<?= number_format($flight['price'], 0) ?> / person</div>
                        <?php if ($seats_left > 0 && $seats_left <= 10): ?>
                            <div class="seats-left">⚠️ Only <?= $seats_left ?> left!</div>
                        <?php endif; ?>

                        <?php if ($is_logged_in): ?>
                            <form action="payment.php" method="POST">
                                <input type="hidden" name="flight_id"   value="<?= $flight['id'] ?>">
                                <input type="hidden" name="trip_type"   value="<?= htmlspecialchars($trip_type) ?>">
                                <input type="hidden" name="from"        value="<?= htmlspecialchars($from) ?>">
                                <input type="hidden" name="to"          value="<?= htmlspecialchars($to) ?>">
                                <input type="hidden" name="depart_date" value="<?= htmlspecialchars($depart_date) ?>">
                                <input type="hidden" name="adults"      value="<?= $adults ?>">
                                <input type="hidden" name="children"    value="<?= $children ?>">
                                <input type="hidden" name="class"       value="<?= htmlspecialchars($class) ?>">
                                <input type="hidden" name="total_price" value="<?= $total_price ?>">
                                <button type="submit" class="book-btn">Book Now</button>
                            </form>
                        <?php else: ?>
                            <a href="login.php" class="book-btn login-required">Login to Book</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="no-filter-results" id="noFilterMsg">
            <div class="icon">🔍</div>
            <p>No flights match your selected filters.</p>
            <p style="font-size:0.85rem; margin-top:8px;">Try adjusting or clearing the filters.</p>
        </div>

        <?php else: ?>
        <div class="no-results">
            <div class="icon">🔍</div>
            <p>No flights found from <b><?= htmlspecialchars($from) ?></b> to <b><?= htmlspecialchars($to) ?></b>.</p>
            <p style="margin-top:10px; font-size:0.9rem;">Try different cities or dates.</p>
        </div>
        <?php endif; ?>

    </div><!-- /results-panel -->
</div><!-- /main-layout -->

<?php else: ?>
<!-- POPULAR ROUTES -->
<div class="popular-section">
    <h2>Popular Routes</h2>
    <div class="popular-grid">
        <div class="popular-card" onclick="fillSearch('Dhaka','Chittagong')">
            <div class="route-text">Dhaka → Chittagong</div>
            <div class="route-price">From $3,200</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Dhaka','Sylhet')">
            <div class="route-text">Dhaka → Sylhet</div>
            <div class="route-price">From $3,800</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Dhaka','Rajshahi')">
            <div class="route-text">Dhaka → Rajshahi</div>
            <div class="route-price">From $3,400</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Chittagong','Dhaka')">
            <div class="route-text">Chittagong → Dhaka</div>
            <div class="route-price">From $3,200</div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ── STATE ──
let activeStops       = new Set();
let activeAirlines    = new Set();
let activeSeatBuckets = new Set();
let maxPrice     = <?= $max_price ?>;
let currentPrice = <?= $max_price ?>;

// ── SWAP CITIES ──
function swapCities() {
    const f = document.getElementById('fromInput');
    const t = document.getElementById('toInput');
    [f.value, t.value] = [t.value, f.value];
    f.classList.add('swap-flash'); t.classList.add('swap-flash');
    setTimeout(() => { f.classList.remove('swap-flash'); t.classList.remove('swap-flash'); }, 400);
}

// ── RETURN DATE TOGGLE ──
function toggleReturnDate(radio) {
    const grp = document.getElementById('returnDateGroup');
    const rd  = document.getElementById('returnDate');
    if (radio.value === 'return') {
        grp.style.display = '';
        rd.required = true;
    } else {
        grp.style.display = 'none';
        rd.required = false;
    }
}

// ── PASSENGER DROPDOWN ──
let adults   = <?= $adults ?>;
let children = <?= $children ?>;

function togglePaxDropdown() {
    const dd = document.getElementById('paxDropdown');
    const tr = document.getElementById('paxTrigger');
    dd.classList.toggle('open');
    tr.classList.toggle('open');
}
function changePax(type, delta) {
    if (type === 'adults') {
        adults = Math.max(1, Math.min(9, adults + delta));
        document.getElementById('adultsDisplay').textContent = adults;
        document.getElementById('adultsInput').value = adults;
    } else {
        children = Math.max(0, Math.min(9, children + delta));
        document.getElementById('childrenDisplay').textContent = children;
        document.getElementById('childrenInput').value = children;
    }
    updatePaxSummary();
}
function updatePaxSummary() {
    const cls = document.querySelector('input[name="class"]:checked')?.value || 'Economy';
    // sync class radio active state
    document.querySelectorAll('.pax-class-opt').forEach(el => {
        el.classList.toggle('active', el.querySelector('input').value === cls);
    });
    let s = adults + ' Adult' + (adults > 1 ? 's' : '');
    if (children > 0) s += ', ' + children + ' Child' + (children > 1 ? 'ren' : '');
    s += ' · ' + cls;
    document.getElementById('paxSummary').textContent = s;
}
// Close dropdown on outside click
document.addEventListener('click', e => {
    const pg = document.getElementById('paxTrigger')?.closest('.pax-group');
    if (pg && !pg.contains(e.target)) {
        document.getElementById('paxDropdown')?.classList.remove('open');
        document.getElementById('paxTrigger')?.classList.remove('open');
    }
});

// ── SEARCH LOADING STATE ──
document.getElementById('searchForm')?.addEventListener('submit', () => {
    const btn = document.getElementById('searchBtn');
    if (btn) { btn.classList.add('loading'); btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/></circle></svg> Searching...'; }
});

// ── STICKY BAR ──
const stickyBar = document.getElementById('stickyBar');
const searchSection = document.querySelector('.search-section');
if (stickyBar && searchSection) {
    const observer = new IntersectionObserver(([entry]) => {
        stickyBar.classList.toggle('visible', !entry.isIntersecting);
    }, { threshold: 0 });
    observer.observe(searchSection);
}

// ── STOPS ──
function toggleStop(val, el) {
    if (activeStops.has(val)) { activeStops.delete(val); el.classList.remove('active'); }
    else                      { activeStops.add(val);    el.classList.add('active'); }
    applyFilters();
}

// ── AIRLINES ──
function toggleAirline(name, el) {
    if (activeAirlines.has(name)) { activeAirlines.delete(name); el.classList.remove('active'); }
    else                          { activeAirlines.add(name);    el.classList.add('active'); }
    applyFilters();
}

// ── SEATS ──
function toggleSeat(key, el) {
    if (activeSeatBuckets.has(key)) { activeSeatBuckets.delete(key); el.classList.remove('active'); }
    else                            { activeSeatBuckets.add(key);    el.classList.add('active'); }
    applyFilters();
}

// ── PRICE ──
function filterByPrice(val) {
    currentPrice = parseInt(val);
    const pd = document.getElementById('priceDisplay');
    if (pd) pd.textContent = '$' + parseInt(val).toLocaleString();
    const pr = document.getElementById('priceRange');
    if (pr) {
        const pct = ((currentPrice - parseFloat(pr.min)) / (parseFloat(pr.max) - parseFloat(pr.min))) * 100;
        pr.style.setProperty('--range-pct', pct + '%');
    }
    applyFilters();
}

// ── APPLY ALL ──
function applyFilters() {
    const cards = document.querySelectorAll('#flightList .flight-card');
    let visible = 0;
    cards.forEach(card => {
        const ok = (activeStops.size    === 0 || activeStops.has(parseInt(card.dataset.stops)))
                && (activeAirlines.size === 0 || activeAirlines.has(card.dataset.airline))
                && (parseFloat(card.dataset.price) <= currentPrice)
                && (activeSeatBuckets.size === 0 || activeSeatBuckets.has(card.dataset.seatBucket));
        card.classList.toggle('hidden', !ok);
        if (ok) visible++;
    });
    const countEl = document.getElementById('visibleCount');
    const noMsg   = document.getElementById('noFilterMsg');
    const hasFilters = activeStops.size > 0 || activeAirlines.size > 0 || activeSeatBuckets.size > 0 || currentPrice < maxPrice;
    if (countEl) countEl.textContent = hasFilters ? '(showing ' + visible + ')' : '';
    if (noMsg)   noMsg.style.display = (hasFilters && visible === 0) ? 'block' : 'none';
}

// ── SORT ──
function sortFlights(type, btn) {
    document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const list  = document.getElementById('flightList');
    if (!list) return;
    const cards = Array.from(list.querySelectorAll('.flight-card'));
    cards.sort((a, b) => {
        if (type === 'price_asc')  return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
        if (type === 'price_desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
        if (type === 'duration')   return parseInt(a.dataset.durationMins) - parseInt(b.dataset.durationMins);
        if (type === 'seats')      return parseInt(b.dataset.seats) - parseInt(a.dataset.seats);
    });
    cards.forEach(c => list.appendChild(c));
}

// ── CLEAR ALL ──
function clearAllFilters() {
    activeStops.clear(); activeAirlines.clear(); activeSeatBuckets.clear();
    currentPrice = maxPrice;
    document.querySelectorAll('.stop-btn,.airline-item,.seat-item').forEach(el => el.classList.remove('active'));
    const pr = document.getElementById('priceRange');
    if (pr) { pr.value = maxPrice; pr.style.setProperty('--range-pct', '100%'); }
    const pd = document.getElementById('priceDisplay');
    if (pd) pd.textContent = '$' + maxPrice.toLocaleString();
    applyFilters();
}

// ── INIT ──
const pr = document.getElementById('priceRange');
if (pr) { pr.value = maxPrice; pr.style.setProperty('--range-pct', '100%'); }
const pd = document.getElementById('priceDisplay');
if (pd) pd.textContent = '$' + maxPrice.toLocaleString();

// ── POPULAR ROUTES ──
function fillSearch(from, to) {
    document.querySelector('input[name="from"]').value = from;
    document.querySelector('input[name="to"]').value   = to;
    document.querySelector('.search-form').submit();
}

// ── DEPART DATE → set return date min ──
document.getElementById('departDate')?.addEventListener('change', function() {
    const rd = document.getElementById('returnDate');
    if (rd) rd.min = this.value;
});
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?>