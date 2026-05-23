<?php
session_start();
include("../model/db_conn.php");
include("../includes/header.php");

$is_logged_in = isset($_SESSION['email']) && isset($_SESSION['role']) && $_SESSION['role'] === 'webuser';

$flights = [];
$search_performed = false;
$trip_type = 'one-way';
$from = '';
$to = '';
$depart_date = '';
$adults = 1;
$children = 0;
$class = 'Economy';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $trip_type   = $_POST['trip_type'] ?? 'one-way';
    $from        = trim($_POST['from'] ?? '');
    $to          = trim($_POST['to'] ?? '');
    $depart_date = $_POST['depart_date'] ?? '';
    $adults      = max(1, (int)($_POST['adults'] ?? 1));
    $children    = max(0, (int)($_POST['children'] ?? 0));
    $class       = $_POST['class'] ?? 'Economy';
    $search_performed = true;

    if (!empty($from) && !empty($to)) {
        $from_pattern = "%" . $from . "%";
        $to_pattern   = "%" . $to . "%";
        $stmt = $conn->prepare("SELECT * FROM flights WHERE departure LIKE ? AND arrival LIKE ? AND (seat IS NULL OR seat > 0)");
        $stmt->bind_param("ss", $from_pattern, $to_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $flights[] = $row;
        }
        $stmt->close();
    }
}
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

        /* SEARCH BAR */
        .search-section {
            background: linear-gradient(135deg, #0b72e6, #0556b3);
            padding: 40px 20px;
        }
        .search-section h2 { color: white; text-align: center; font-size: 1.8rem; margin-bottom: 25px; }

        .search-form {
            background: white;
            border-radius: 14px;
            padding: 25px;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .form-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }

        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 130px; }
        .form-group label { font-size: 0.78rem; font-weight: bold; color: #555; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border 0.2s;
            background: #fafafa;
        }
        .form-group input:focus,
        .form-group select:focus { border-color: #0b72e6; outline: none; background: white; }

        .trip-type-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .trip-type-row label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 600; color: #555; }
        .trip-type-row input[type="radio"] { accent-color: #0b72e6; }

        .search-btn {
            background: #0b72e6; color: white; border: none;
            padding: 12px 30px; border-radius: 8px; font-size: 1rem;
            font-weight: bold; cursor: pointer; transition: background 0.3s;
            white-space: nowrap; height: 44px;
        }
        .search-btn:hover { background: #0556b3; }

        /* RESULTS */
        .results-section { max-width: 1000px; margin: 35px auto; padding: 0 20px; }
        .results-title {
            font-size: 1.3rem; color: #333; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .results-count {
            background: #0b72e6; color: white;
            border-radius: 20px; padding: 3px 12px; font-size: 0.85rem;
        }

        /* FLIGHT CARD */
        .flight-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 18px; overflow: hidden;
            display: flex; transition: box-shadow 0.3s;
        }
        .flight-card:hover { box-shadow: 0 6px 20px rgba(11,114,230,0.15); }

        .flight-img { width: 130px; min-height: 130px; object-fit: cover; }
        .flight-img-placeholder { width: 130px; background: #e0eeff; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; }

        .flight-body { flex: 1; padding: 18px 20px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }

        .flight-info { flex: 1; min-width: 200px; }
        .flight-info h3 { font-size: 1.1rem; color: #222; margin-bottom: 5px; }
        .airline-tag { font-size: 0.8rem; color: #0b72e6; background: #e8f2ff; padding: 2px 10px; border-radius: 20px; display: inline-block; margin-bottom: 10px; }

        .route { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .route .city { font-weight: bold; font-size: 1rem; color: #333; }
        .route .arrow { color: #0b72e6; font-size: 1.2rem; }
        .route .duration { font-size: 0.8rem; color: #888; }

        .flight-meta { font-size: 0.82rem; color: #777; }
        .flight-meta span { margin-right: 12px; }

        .flight-pricing { display: flex; flex-direction: column; align-items: flex-end; justify-content: center; min-width: 150px; padding: 18px; border-left: 1px solid #f0f0f0; }
        .price-label { font-size: 0.75rem; color: #999; margin-bottom: 3px; }
        .price-amount { font-size: 1.6rem; font-weight: bold; color: #0b72e6; margin-bottom: 5px; }
        .per-person { font-size: 0.72rem; color: #aaa; margin-bottom: 12px; }
        .seats-left { font-size: 0.78rem; color: #e67e00; margin-bottom: 10px; font-weight: 600; }

        .book-btn {
            background: #0b72e6; color: white; border: none;
            padding: 10px 22px; border-radius: 8px; font-size: 0.9rem;
            font-weight: bold; cursor: pointer; transition: background 0.3s;
            text-decoration: none; display: inline-block;
        }
        .book-btn:hover { background: #0556b3; }
        .book-btn.login-required { background: #f0a500; }
        .book-btn.login-required:hover { background: #d4900a; }

        /* NO RESULTS */
        .no-results {
            text-align: center; padding: 50px; background: white;
            border-radius: 12px; color: #888;
        }
        .no-results .icon { font-size: 3rem; margin-bottom: 15px; }
        .no-results p { font-size: 1rem; }

        /* POPULAR ROUTES */
        .popular-section { max-width: 1000px; margin: 30px auto; padding: 0 20px 40px; }
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

        @media (max-width: 650px) {
            .flight-card { flex-direction: column; }
            .flight-img, .flight-img-placeholder { width: 100%; height: 140px; }
            .flight-pricing { border-left: none; border-top: 1px solid #f0f0f0; flex-direction: row; align-items: center; justify-content: space-between; padding: 12px 18px; }
        }
    </style>
</head>
<body>

<!-- SEARCH SECTION -->
<div class="search-section">
    <h2>✈️ Search Available Flights</h2>
    <form class="search-form" action="" method="POST">
        <div class="trip-type-row">
            <label><input type="radio" name="trip_type" value="one-way" <?= $trip_type == 'one-way' ? 'checked' : '' ?>> One Way</label>
            <label><input type="radio" name="trip_type" value="return" <?= $trip_type == 'return' ? 'checked' : '' ?>> Return</label>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>From</label>
                <input type="text" name="from" placeholder="e.g. Dhaka" value="<?= htmlspecialchars($from) ?>" required>
            </div>
            <div class="form-group">
                <label>To</label>
                <input type="text" name="to" placeholder="e.g. Chittagong" value="<?= htmlspecialchars($to) ?>" required>
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
                    <option value="Economy" <?= $class == 'Economy' ? 'selected' : '' ?>>Economy</option>
                    <option value="Business" <?= $class == 'Business' ? 'selected' : '' ?>>Business</option>
                </select>
            </div>
            <button type="submit" class="search-btn">🔍 Search</button>
        </div>
    </form>
</div>

<!-- RESULTS -->
<?php if ($search_performed): ?>
<div class="results-section">
    <div class="results-title">
        Search Results
        <span class="results-count"><?= count($flights) ?> flight<?= count($flights) != 1 ? 's' : '' ?> found</span>
    </div>

    <?php if (count($flights) > 0): ?>
        <?php foreach ($flights as $flight):
            $total_passengers = $adults + $children;
            $total_price = $flight['price'] * $total_passengers;
            $seats_left = $flight['seat'] ?? null;
        ?>
        <div class="flight-card">
            <?php if (!empty($flight['image'])): ?>
                <img class="flight-img" src="upload/<?= htmlspecialchars($flight['image']) ?>" alt="<?= htmlspecialchars($flight['flight_name']) ?>">
            <?php else: ?>
                <div class="flight-img-placeholder">✈️</div>
            <?php endif; ?>

            <div class="flight-body">
                <div class="flight-info">
                    <h3><?= htmlspecialchars($flight['flight_name']) ?></h3>
                    <span class="airline-tag"><?= htmlspecialchars($flight['airline_name']) ?> · <?= htmlspecialchars($flight['flight_code']) ?></span>
                    <div class="route">
                        <span class="city"><?= htmlspecialchars($flight['departure']) ?></span>
                        <span class="arrow">→</span>
                        <span class="city"><?= htmlspecialchars($flight['arrival']) ?></span>
                        <span class="duration">(<?= htmlspecialchars($flight['duration']) ?>)</span>
                    </div>
                    <div class="flight-meta">
                        <span>📅 <?= htmlspecialchars($depart_date) ?></span>
                        <span>👥 <?= $adults ?> Adult<?= $adults > 1 ? 's' : '' ?><?= $children > 0 ? ", $children Child" . ($children > 1 ? 'ren' : '') : '' ?></span>
                        <span>💺 <?= htmlspecialchars($class) ?></span>
                    </div>
                </div>

                <div class="flight-pricing">
                    <div class="price-label">Total Price</div>
                    <div class="price-amount">৳<?= number_format($total_price, 0) ?></div>
                    <div class="per-person">৳<?= number_format($flight['price'], 0) ?> per person</div>
                    <?php if ($seats_left !== null && $seats_left <= 10): ?>
                        <div class="seats-left">⚠️ Only <?= $seats_left ?> seats left!</div>
                    <?php endif; ?>

                    <?php if ($is_logged_in): ?>
                        <form action="payment.php" method="POST">
                            <input type="hidden" name="flight_id" value="<?= $flight['id'] ?>">
                            <input type="hidden" name="trip_type" value="<?= htmlspecialchars($trip_type) ?>">
                            <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                            <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                            <input type="hidden" name="depart_date" value="<?= htmlspecialchars($depart_date) ?>">
                            <input type="hidden" name="adults" value="<?= $adults ?>">
                            <input type="hidden" name="children" value="<?= $children ?>">
                            <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
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

    <?php else: ?>
        <div class="no-results">
            <div class="icon">🔍</div>
            <p>No flights found from <b><?= htmlspecialchars($from) ?></b> to <b><?= htmlspecialchars($to) ?></b>.</p>
            <p style="margin-top:10px; font-size:0.9rem;">Try different cities or check back later.</p>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- POPULAR ROUTES (shown before search) -->
<div class="popular-section">
    <h2>Popular Routes</h2>
    <div class="popular-grid">
        <div class="popular-card" onclick="fillSearch('Dhaka','Chittagong')">
            <div class="route-text">Dhaka → Chittagong</div>
            <div class="route-price">From ৳2,500</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Dhaka','Sylhet')">
            <div class="route-text">Dhaka → Sylhet</div>
            <div class="route-price">From ৳3,000</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Dhaka','Rajshahi')">
            <div class="route-text">Dhaka → Rajshahi</div>
            <div class="route-price">From ৳2,800</div>
        </div>
        <div class="popular-card" onclick="fillSearch('Chittagong','Dhaka')">
            <div class="route-text">Chittagong → Dhaka</div>
            <div class="route-price">From ৳2,500</div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function fillSearch(from, to) {
    document.querySelector('input[name="from"]').value = from;
    document.querySelector('input[name="to"]').value = to;
    document.querySelector('input[name="from"]').focus();
}
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?>