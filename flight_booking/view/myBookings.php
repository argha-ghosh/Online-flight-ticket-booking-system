<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}

$email = $_SESSION['email'];

// Get user
$u_stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
$u_stmt->bind_param("s", $email);
$u_stmt->execute();
$user = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

// Handle cancel booking
if (isset($_POST['cancel_id'])) {
    $cancel_id = (int)$_POST['cancel_id'];
    $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?")->execute() ||
    $conn->query("UPDATE bookings SET status = 'cancelled' WHERE id = $cancel_id AND user_id = {$user['id']}");
}

// Filter
$status_filter = $_GET['status'] ?? 'all';
$where = "WHERE b.user_id = {$user['id']}";
if ($status_filter === 'confirmed') $where .= " AND b.status = 'confirmed'";
if ($status_filter === 'cancelled') $where .= " AND b.status = 'cancelled'";

$bookings_result = $conn->query("
    SELECT b.*, f.flight_name, f.airline_name, f.flight_code, f.departure, f.arrival, f.image as flight_image
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    $where
    ORDER BY b.booking_date DESC
");

// Count stats
$stats = $conn->query("SELECT status, COUNT(*) as cnt FROM bookings WHERE user_id = {$user['id']} GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$total = 0; $confirmed = 0; $cancelled = 0;
foreach ($stats as $s) {
    $total += $s['cnt'];
    if ($s['status'] === 'confirmed') $confirmed = $s['cnt'];
    if ($s['status'] === 'cancelled') $cancelled = $s['cnt'];
}

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | My Bookings</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }

        .page-header {
            background: linear-gradient(135deg, #0b72e6, #0556b3);
            color: white; padding: 35px 30px; text-align: center;
        }
        .page-header h1 { font-size: 1.9rem; margin-bottom: 6px; }
        .page-header p { opacity: 0.85; }

        /* STATS */
        .stats-row {
            display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;
            max-width: 750px; margin: -20px auto 0; padding: 0 20px; position: relative; z-index: 10;
        }
        .stat-pill {
            background: white; border-radius: 50px; padding: 12px 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12); text-align: center; min-width: 120px;
        }
        .stat-pill .num { font-size: 1.5rem; font-weight: bold; color: #0b72e6; }
        .stat-pill .lbl { font-size: 0.75rem; color: #888; margin-top: 2px; }

        /* FILTER TABS */
        .filter-row {
            display: flex; gap: 10px; max-width: 900px; margin: 35px auto 15px;
            padding: 0 20px; flex-wrap: wrap;
        }
        .filter-tab {
            padding: 8px 20px; border-radius: 25px; font-size: 0.88rem;
            font-weight: 600; text-decoration: none; transition: all 0.2s;
            background: white; color: #555; box-shadow: 0 2px 6px rgba(0,0,0,0.07);
        }
        .filter-tab:hover { background: #e8f2ff; color: #0b72e6; }
        .filter-tab.active { background: #0b72e6; color: white; }

        .main-content { max-width: 900px; margin: 0 auto; padding: 0 20px 50px; }

        /* BOOKING CARD */
        .booking-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            margin-bottom: 18px; overflow: hidden; transition: box-shadow 0.3s;
        }
        .booking-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.12); }

        .booking-card-header {
            background: #f8f9ff; border-bottom: 1px solid #eef0f8;
            padding: 12px 20px; display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 8px;
        }
        .booking-id-label { font-size: 0.8rem; color: #888; }
        .booking-id-val { font-size: 1rem; font-weight: bold; color: #0b72e6; }
        .booking-date-label { font-size: 0.78rem; color: #aaa; }

        .booking-card-body { padding: 18px 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }

        .flight-thumb {
            width: 80px; height: 60px; object-fit: cover;
            border-radius: 8px; flex-shrink: 0;
        }
        .flight-thumb-placeholder {
            width: 80px; height: 60px; background: #e0eeff;
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 1.5rem; flex-shrink: 0;
        }

        .booking-main { flex: 1; min-width: 200px; }
        .booking-main .flight-name { font-size: 1rem; font-weight: bold; color: #222; margin-bottom: 4px; }
        .booking-main .airline { font-size: 0.8rem; color: #0b72e6; margin-bottom: 8px; }
        .route-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .route-row .city { font-weight: 600; color: #333; font-size: 0.95rem; }
        .route-row .arrow { color: #0b72e6; }
        .meta-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .meta-tag {
            background: #f0f4f8; padding: 3px 10px; border-radius: 20px;
            font-size: 0.75rem; color: #666;
        }

        .booking-right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; min-width: 130px; }
        .price-display { font-size: 1.4rem; font-weight: bold; color: #0b72e6; }
        .badge {
            padding: 5px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: bold;
        }
        .badge-confirmed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

        .action-row {
            display: flex; gap: 8px;
        }
        .btn-sm {
            padding: 7px 15px; border-radius: 6px; font-size: 0.82rem;
            font-weight: 600; text-decoration: none; border: none; cursor: pointer;
            transition: all 0.2s;
        }
        .btn-view { background: #e8f2ff; color: #0b72e6; }
        .btn-view:hover { background: #0b72e6; color: white; }
        .btn-cancel { background: #fdecea; color: #c0392b; }
        .btn-cancel:hover { background: #c0392b; color: white; }

        /* EMPTY STATE */
        .empty-state {
            text-align: center; padding: 60px 30px; background: white;
            border-radius: 12px; color: #aaa; box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        }
        .empty-state .icon { font-size: 3.5rem; margin-bottom: 15px; }
        .empty-state h3 { color: #666; margin-bottom: 10px; }
        .empty-state p { font-size: 0.9rem; margin-bottom: 20px; }
        .btn-search {
            background: #0b72e6; color: white; padding: 12px 28px;
            border-radius: 8px; text-decoration: none; font-weight: bold;
            display: inline-block; transition: background 0.3s;
        }
        .btn-search:hover { background: #0556b3; }
    </style>
</head>
<body>

<div class="page-header">
    <h1>🎫 My Bookings</h1>
    <p>All your flight bookings in one place</p>
</div>

<!-- STATS -->
<div class="stats-row">
    <div class="stat-pill">
        <div class="num"><?= $total ?></div>
        <div class="lbl">Total Bookings</div>
    </div>
    <div class="stat-pill">
        <div class="num"><?= $confirmed ?></div>
        <div class="lbl">Confirmed</div>
    </div>
    <div class="stat-pill">
        <div class="num"><?= $cancelled ?></div>
        <div class="lbl">Cancelled</div>
    </div>
</div>

<!-- FILTER TABS -->
<div class="filter-row">
    <a href="?status=all" class="filter-tab <?= $status_filter === 'all' ? 'active' : '' ?>">All Bookings</a>
    <a href="?status=confirmed" class="filter-tab <?= $status_filter === 'confirmed' ? 'active' : '' ?>">✅ Confirmed</a>
    <a href="?status=cancelled" class="filter-tab <?= $status_filter === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
</div>

<div class="main-content">
    <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
        <?php while ($b = $bookings_result->fetch_assoc()): ?>
        <div class="booking-card">
            <div class="booking-card-header">
                <div>
                    <div class="booking-id-label">Booking ID</div>
                    <div class="booking-id-val">#<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div class="booking-date-label">Booked on <?= date('d M Y, g:i A', strtotime($b['booking_date'])) ?></div>
            </div>

            <div class="booking-card-body">
                <?php if (!empty($b['flight_image'])): ?>
                    <img class="flight-thumb" src="upload/<?= htmlspecialchars($b['flight_image']) ?>" alt="Flight">
                <?php else: ?>
                    <div class="flight-thumb-placeholder">✈️</div>
                <?php endif; ?>

                <div class="booking-main">
                    <div class="flight-name"><?= htmlspecialchars($b['flight_name']) ?></div>
                    <div class="airline"><?= htmlspecialchars($b['airline_name']) ?> · <?= htmlspecialchars($b['flight_code']) ?></div>
                    <div class="route-row">
                        <span class="city"><?= htmlspecialchars($b['from_location']) ?></span>
                        <span class="arrow">→</span>
                        <span class="city"><?= htmlspecialchars($b['to_location']) ?></span>
                    </div>
                    <div class="meta-tags">
                        <span class="meta-tag">📅 <?= date('d M Y', strtotime($b['depart_date'])) ?></span>
                        <span class="meta-tag">👥 <?= $b['adults'] ?> Adult<?= $b['adults'] > 1 ? 's' : '' ?><?= $b['children'] > 0 ? ", {$b['children']} Child" . ($b['children'] > 1 ? 'ren' : '') : '' ?></span>
                        <span class="meta-tag">💺 <?= htmlspecialchars($b['class']) ?></span>
                        <span class="meta-tag">🔄 <?= ucfirst($b['trip_type']) ?></span>
                    </div>
                </div>

                <div class="booking-right">
                    <div class="price-display">৳<?= number_format($b['total_price'], 0) ?></div>
                    <span class="badge badge-<?= $b['status'] ?>"><?= $b['status'] === 'confirmed' ? '✔' : '✖' ?> <?= ucfirst($b['status']) ?></span>
                    <div class="action-row">
                        <a href="booking_confirm.php?id=<?= $b['id'] ?>" class="btn-sm btn-view">View</a>
                        <?php if ($b['status'] === 'confirmed'): ?>
                        <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                            <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn-sm btn-cancel">Cancel</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>

    <?php else: ?>
        <div class="empty-state">
            <div class="icon">🎫</div>
            <h3>No bookings found</h3>
            <p>You haven't made any <?= $status_filter !== 'all' ? $status_filter : '' ?> bookings yet.</p>
            <a href="searchflights.php" class="btn-search">Search Flights</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

<?php include("../includes/footer.php"); ?>