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
$adults      = 1;
$children    = 0;
$class       = 'Economy';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $trip_type   = $_POST['trip_type']   ?? 'one-way';
    $from        = trim($_POST['from']   ?? '');
    $to          = trim($_POST['to']     ?? '');
    $depart_date = $_POST['depart_date'] ?? '';
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
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }

        /* ── SEARCH BAR ── */
        .search-section {
            background: linear-gradient(135deg, #0b72e6, #0556b3);
            padding: 35px 20px;
        }
        .search-section h2 { color: white; text-align: center; font-size: 1.7rem; margin-bottom: 22px; }
        .search-form {
            background: white; border-radius: 14px; padding: 22px;
            max-width: 1100px; margin: 0 auto;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .trip-type-row { display: flex; gap: 18px; margin-bottom: 14px; }
        .trip-type-row label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 600; color: #555; }
        .trip-type-row input[type="radio"] { accent-color: #0b72e6; }
        .form-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 120px; }
        .form-group label { font-size: 0.75rem; font-weight: 700; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.4px; }
        .form-group input, .form-group select {
            padding: 10px 12px; border: 1.5px solid #ddd; border-radius: 8px;
            font-size: 0.92rem; transition: border 0.2s; background: #fafafa;
        }
        .form-group input:focus, .form-group select:focus { border-color: #0b72e6; outline: none; background: white; }
        .search-btn {
            background: #0b72e6; color: white; border: none;
            padding: 11px 28px; border-radius: 8px; font-size: 0.97rem;
            font-weight: bold; cursor: pointer; transition: background 0.3s;
            white-space: nowrap; height: 43px; align-self: flex-end;
        }
        .search-btn:hover { background: #0556b3; }

        /* ── MAIN LAYOUT ── */
        .main-layout {
            max-width: 1200px; margin: 30px auto; padding: 0 20px 50px;
            display: grid; grid-template-columns: 280px 1fr; gap: 24px;
        }

        /* ── SIDEBAR ── */
        .sidebar { display: flex; flex-direction: column; gap: 16px; }

        .filter-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden;
        }
        .filter-card-header {
            background: #0b72e6; color: white;
            padding: 12px 16px; font-weight: 700; font-size: 0.9rem;
            display: flex; align-items: center; gap: 8px;
        }
        .filter-card-body { padding: 14px 16px; }

        /* STOP OPTIONS */
        .stop-options { display: flex; flex-direction: column; gap: 10px; }
        .stop-btn {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; border: 1.5px solid #e0e8f5;
            border-radius: 8px; cursor: pointer; transition: all 0.2s;
            background: #f8faff;
        }
        .stop-btn:hover { border-color: #0b72e6; background: #eef5ff; }
        .stop-btn.active { border-color: #0b72e6; background: #e0eeff; }
        .stop-btn input[type="checkbox"] { display: none; }
        .stop-left { display: flex; align-items: center; gap: 10px; }
        .stop-dot-wrap { display: flex; gap: 4px; align-items: center; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #0b72e6; }
        .dot.empty { background: #ccc; border: 1.5px solid #aaa; }
        .stop-label { font-size: 0.88rem; font-weight: 600; color: #333; }
        .stop-count { font-size: 0.75rem; color: #888; }

        /* AIRLINE FILTER */
        .airline-list { display: flex; flex-direction: column; gap: 8px; max-height: 220px; overflow-y: auto; }
        .airline-list::-webkit-scrollbar { width: 4px; }
        .airline-list::-webkit-scrollbar-thumb { background: #cde; border-radius: 4px; }
        .airline-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 10px; border-radius: 7px; cursor: pointer;
            transition: background 0.15s; gap: 8px;
        }
        .airline-item:hover { background: #f0f7ff; }
        .airline-item.active { background: #e0eeff; }
        .airline-item label {
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; flex: 1; font-size: 0.87rem; color: #333;
        }
        .airline-item input[type="checkbox"] { accent-color: #0b72e6; width: 15px; height: 15px; flex-shrink: 0; }
        .airline-logo {
            width: 28px; height: 28px; border-radius: 6px;
            background: linear-gradient(135deg, #0b72e6, #0556b3);
            color: white; font-size: 0.65rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .airline-min-price { font-size: 0.78rem; font-weight: 700; color: #0b72e6; white-space: nowrap; }

        /* SEAT CAPACITY */
        .seat-options { display: flex; flex-direction: column; gap: 8px; }
        .seat-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 9px 12px; border: 1.5px solid #e0e8f5;
            border-radius: 8px; cursor: pointer; transition: all 0.2s; background: #f8faff;
        }
        .seat-item:hover { border-color: #0b72e6; background: #eef5ff; }
        .seat-item.active { border-color: #0b72e6; background: #e0eeff; }
        .seat-item input[type="checkbox"] { display: none; }
        .seat-label { font-size: 0.87rem; font-weight: 600; color: #333; display: flex; align-items: center; gap: 7px; }
        .seat-label .seat-icon { font-size: 0.95rem; }
        .seat-range-tag { font-size: 0.72rem; color: #888; }

        /* PRICE RANGE */
        .price-range-wrap { padding: 4px 0; }
        .price-range-labels { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.82rem; color: #666; }
        .price-range-labels span { font-weight: 700; color: #0b72e6; }
        input[type="range"] {
            width: 100%; accent-color: #0b72e6;
            height: 4px; cursor: pointer;
        }
        .price-display {
            text-align: center; margin-top: 10px;
            font-size: 0.85rem; color: #555;
        }
        .price-display b { color: #0b72e6; }

        /* CLEAR FILTERS */
        .clear-btn {
            width: 100%; padding: 10px; background: white;
            border: 1.5px solid #e74c3c; color: #e74c3c;
            border-radius: 8px; font-weight: 700; font-size: 0.88rem;
            cursor: pointer; transition: all 0.2s;
        }
        .clear-btn:hover { background: #e74c3c; color: white; }

        /* ── RESULTS PANEL ── */
        .results-panel {}
        .results-topbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 18px; flex-wrap: wrap; gap: 10px;
        }
        .results-title { font-size: 1.2rem; color: #333; display: flex; align-items: center; gap: 10px; }
        .results-count { background: #0b72e6; color: white; border-radius: 20px; padding: 3px 13px; font-size: 0.82rem; font-weight: 600; }
        .visible-count { font-size: 0.82rem; color: #888; }

        /* SORT BAR */
        .sort-bar { display: flex; gap: 8px; flex-wrap: wrap; }
        .sort-btn {
            padding: 6px 14px; border-radius: 20px; font-size: 0.8rem;
            font-weight: 600; border: 1.5px solid #ddd; background: white;
            color: #666; cursor: pointer; transition: all 0.2s;
        }
        .sort-btn:hover { border-color: #0b72e6; color: #0b72e6; }
        .sort-btn.active { background: #0b72e6; color: white; border-color: #0b72e6; }

        /* FLIGHT CARD */
        .flight-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            margin-bottom: 16px; overflow: hidden;
            display: flex; transition: box-shadow 0.3s, transform 0.2s;
        }
        .flight-card:hover { box-shadow: 0 6px 20px rgba(11,114,230,0.15); transform: translateY(-1px); }
        .flight-card.hidden { display: none; }

        .flight-img { width: 120px; min-height: 120px; object-fit: cover; flex-shrink: 0; }
        .flight-img-placeholder {
            width: 120px; background: linear-gradient(135deg, #e0eeff, #c8dcff);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; flex-shrink: 0;
        }
        .flight-body { flex: 1; padding: 16px 18px; display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
        .flight-info { flex: 1; min-width: 180px; }
        .flight-info h3 { font-size: 1rem; color: #222; margin-bottom: 4px; }

        .tags-row { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px; }
        .tag {
            font-size: 0.72rem; padding: 2px 9px; border-radius: 20px; font-weight: 600;
        }
        .tag-airline { background: #e8f2ff; color: #0b72e6; }
        .tag-stop-0 { background: #d4edda; color: #155724; }
        .tag-stop-1 { background: #fff3cd; color: #856404; }
        .tag-stop-2 { background: #fde8e8; color: #721c24; }
        .tag-seat  { background: #f0f0f0; color: #555; }

        .route { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; }
        .route .city { font-weight: 700; font-size: 0.97rem; color: #333; }
        .route .arrow { color: #0b72e6; font-size: 1.1rem; }
        .route .duration { font-size: 0.78rem; color: #aaa; background: #f5f5f5; padding: 2px 8px; border-radius: 10px; }

        .flight-meta { font-size: 0.78rem; color: #888; display: flex; flex-wrap: wrap; gap: 8px; }
        .meta-item { display: flex; align-items: center; gap: 3px; }

        .flight-pricing {
            display: flex; flex-direction: column; align-items: flex-end;
            justify-content: center; min-width: 140px; padding: 16px;
            border-left: 1px solid #f0f0f0;
        }
        .price-label { font-size: 0.72rem; color: #aaa; margin-bottom: 2px; }
        .price-amount { font-size: 1.5rem; font-weight: 800; color: #0b72e6; margin-bottom: 3px; }
        .per-person { font-size: 0.7rem; color: #bbb; margin-bottom: 10px; }
        .seats-left { font-size: 0.75rem; color: #e67e00; margin-bottom: 8px; font-weight: 700; }

        .book-btn {
            background: #0b72e6; color: white; border: none;
            padding: 9px 20px; border-radius: 8px; font-size: 0.85rem;
            font-weight: bold; cursor: pointer; transition: background 0.3s;
            text-decoration: none; display: inline-block; text-align: center;
        }
        .book-btn:hover { background: #0556b3; }
        .book-btn.login-required { background: #f0a500; }
        .book-btn.login-required:hover { background: #d4900a; }

        /* NO RESULTS */
        .no-results {
            text-align: center; padding: 50px 30px; background: white;
            border-radius: 12px; color: #aaa;
        }
        .no-results .icon { font-size: 3rem; margin-bottom: 15px; }
        .no-filter-results {
            text-align: center; padding: 40px; background: #fffbf0;
            border-radius: 12px; color: #888; border: 1.5px dashed #f0c040;
            display: none;
        }
        .no-filter-results .icon { font-size: 2rem; margin-bottom: 10px; }

        /* POPULAR ROUTES */
        .popular-section { max-width: 1100px; margin: 30px auto; padding: 0 20px 40px; }
        .popular-section h2 { font-size: 1.3rem; color: #333; margin-bottom: 18px; border-left: 4px solid #0b72e6; padding-left: 12px; }
        .popular-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .popular-card {
            background: white; border-radius: 10px; padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07); cursor: pointer;
            transition: all 0.3s; text-align: center; border-top: 3px solid #0b72e6;
        }
        .popular-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(11,114,230,0.15); }
        .popular-card .route-text { font-weight: bold; color: #333; margin-bottom: 5px; }
        .popular-card .route-price { color: #0b72e6; font-size: 0.9rem; }

        /* MOBILE */
        @media (max-width: 800px) {
            .main-layout { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .sidebar.open { display: flex; }
            .mobile-filter-btn {
                display: flex; align-items: center; gap: 8px;
                background: #0b72e6; color: white; border: none;
                padding: 10px 18px; border-radius: 8px; font-weight: 600;
                cursor: pointer; margin-bottom: 15px; font-size: 0.9rem;
            }
        }
        @media (min-width: 801px) {
            .mobile-filter-btn { display: none; }
        }
        @media (max-width: 500px) {
            .flight-card { flex-direction: column; }
            .flight-img, .flight-img-placeholder { width: 100%; height: 120px; }
            .flight-pricing { border-left: none; border-top: 1px solid #f0f0f0; flex-direction: row; align-items: center; justify-content: space-between; padding: 12px 16px; }
        }
    </style>
</head>
<body>

<!-- SEARCH -->
<div class="search-section">
    <h2>✈️ Search Available Flights</h2>
    <form class="search-form" action="" method="POST">
        <div class="trip-type-row">
            <label><input type="radio" name="trip_type" value="one-way"  <?= $trip_type=='one-way'  ? 'checked':'' ?>> One Way</label>
            <label><input type="radio" name="trip_type" value="return"   <?= $trip_type=='return'   ? 'checked':'' ?>> Return</label>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>From</label>
                <input type="text" name="from" placeholder="Departure From" value="<?= htmlspecialchars($from) ?>" required>
            </div>
                <div class="form-group">
                <label>To</label>
                <input type="text" name="to" placeholder="Arrival To" value="<?= htmlspecialchars($to) ?>" required>
            </div>
            <div class="form-group">
                <label>Depart Date</label>
                <input type="date" name="depart_date" value="<?= htmlspecialchars($depart_date) ?>" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Adults (18+)</label>
                <input type="number" name="adults" min="1" max="9" value="<?= $adults ?>">
            </div>
            <div class="form-group">
                <label>Children</label>
                <input type="number" name="children" min="0" max="9" value="<?= $children ?>">
            </div>
            <div class="form-group">
                <label>Class</label>
                <select name="class">
                    <option value="Economy"  <?= $class=='Economy'  ? 'selected':'' ?>>Economy</option>
                    <option value="Business" <?= $class=='Business' ? 'selected':'' ?>>Business</option>
                </select>
            </div>
            <button type="submit" class="search-btn">🔍 Search</button>
        </div>
    </form>
</div>

<?php if ($search_performed): ?>

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
            <div class="results-title">
                Results
                <span class="results-count" id="totalCount"><?= count($flights) ?> flight<?= count($flights)!=1?'s':'' ?></span>
                <span class="visible-count" id="visibleCount"></span>
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
                            <span class="arrow">→</span>
                            <span class="city"><?= htmlspecialchars($flight['arrival']) ?></span>
                            <span class="duration"><?= htmlspecialchars($flight['duration']) ?></span>
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
            <div class="route-price">From $2,500</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Dhaka','Sylhet')">
            <div class="route-text">Dhaka → Sylhet</div>
            <div class="route-price">From $3,000</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Dhaka','Rajshahi')">
            <div class="route-text">Dhaka → Rajshahi</div>
            <div class="route-price">From $2,800</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Chittagong','Dhaka')">
            <div class="route-text">Chittagong → Dhaka</div>
            <div class="route-price">From $2,500</div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ── STATE ──
let activeStops    = new Set();
let activeAirlines = new Set();
let activeSeatBuckets = new Set();
let maxPrice = <?= $max_price ?>;
let currentPrice = <?= $max_price ?>;

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
    document.getElementById('priceDisplay').textContent = '' + parseInt(val).toLocaleString('en-BD');
    applyFilters();
}

// ── APPLY ALL ──
function applyFilters() {
    const cards = document.querySelectorAll('#flightList .flight-card');
    let visible = 0;
    cards.forEach(card => {
        const stops      = parseInt(card.dataset.stops);
        const airline    = card.dataset.airline;
        const price      = parseFloat(card.dataset.price);
        const seatBucket = card.dataset.seatBucket;

        const stopOk    = activeStops.size    === 0 || activeStops.has(stops);
        const airlineOk = activeAirlines.size === 0 || activeAirlines.has(airline);
        const priceOk   = price <= currentPrice;
        const seatOk    = activeSeatBuckets.size === 0 || activeSeatBuckets.has(seatBucket);

        if (stopOk && airlineOk && priceOk && seatOk) {
            card.classList.remove('hidden'); visible++;
        } else {
            card.classList.add('hidden');
        }
    });

    const countEl = document.getElementById('visibleCount');
    const noMsg   = document.getElementById('noFilterMsg');
    const hasFilters = activeStops.size > 0 || activeAirlines.size > 0 || activeSeatBuckets.size > 0 || currentPrice < maxPrice;

    if (hasFilters) {
        countEl.textContent = '(showing ' + visible + ')';
        noMsg.style.display = visible === 0 ? 'block' : 'none';
    } else {
        countEl.textContent = '';
        noMsg.style.display = 'none';
    }
}

// ── SORT ──
function sortFlights(type, btn) {
    document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const list  = document.getElementById('flightList');
    const cards = Array.from(list.querySelectorAll('.flight-card'));

    cards.sort((a, b) => {
        switch(type) {
            case 'price_asc':  return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
            case 'price_desc': return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
            case 'duration':   return parseInt(a.dataset.durationMins) - parseInt(b.dataset.durationMins);
            case 'seats':      return parseInt(b.dataset.seats) - parseInt(a.dataset.seats);
        }
    });
    cards.forEach(c => list.appendChild(c));
}

// ── CLEAR ALL ──
function clearAllFilters() {
    activeStops.clear();
    activeAirlines.clear();
    activeSeatBuckets.clear();
    currentPrice = maxPrice;

    document.querySelectorAll('.stop-btn').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.airline-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.seat-item').forEach(el => el.classList.remove('active'));

    const pr = document.getElementById('priceRange');
    if (pr) { pr.value = maxPrice; }
    document.getElementById('priceDisplay').textContent = '৳' + maxPrice.toLocaleString('en-BD');

    applyFilters();
}

// POPULAR ROUTES
function fillSearch(from, to) {
    document.querySelector('input[name="from"]').value = from;
    document.querySelector('input[name="to"]').value = to;
}
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?>