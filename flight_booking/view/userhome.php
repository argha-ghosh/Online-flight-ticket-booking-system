<?php
session_start();
include("../model/db_conn.php");
include("../includes/header.php");

$is_logged_in = isset($_SESSION['email']) && isset($_SESSION['role']) && $_SESSION['role'] === 'webuser';
$user = null;
$booking_count = 0;

if ($is_logged_in) {
    $email = $_SESSION['email'];
    $stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $cnt_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE user_id = ?");
        $cnt_stmt->bind_param("i", $user['id']);
        $cnt_stmt->execute();
        $cnt_row = $cnt_stmt->get_result()->fetch_assoc();
        $booking_count = $cnt_row['cnt'];
        $cnt_stmt->close();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | My Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }

        /* HERO */
        .hero-banner {
            background: linear-gradient(135deg, #0b72e6, #0556b3);
            color: white;
            padding: 60px 30px;
            text-align: center;
        }
        .hero-banner h1 { font-size: 2.2rem; margin-bottom: 10px; }
        .hero-banner p { font-size: 1.1rem; opacity: 0.9; margin-bottom: 25px; }
        .hero-banner .btn-white {
            background: white;
            color: #0b72e6;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .hero-banner .btn-white:hover { background: #e0eeff; }

        /* STATS */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            background: white;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .stat-card {
            text-align: center;
            padding: 15px 30px;
            border-radius: 10px;
            background: #f0f7ff;
            min-width: 150px;
        }
        .stat-card .number { font-size: 2rem; font-weight: bold; color: #0b72e6; }
        .stat-card .label { font-size: 0.85rem; color: #666; margin-top: 5px; }

        /* QUICK ACTIONS */
        .section { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .section h2 { font-size: 1.5rem; color: #333; margin-bottom: 20px; border-left: 4px solid #0b72e6; padding-left: 12px; }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .action-card {
            background: white;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            transition: all 0.3s;
            border-top: 4px solid #0b72e6;
        }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(11,114,230,0.2); }
        .action-card .icon { font-size: 2.5rem; margin-bottom: 15px; }
        .action-card h3 { font-size: 1.1rem; margin-bottom: 8px; color: #0b72e6; }
        .action-card p { font-size: 0.85rem; color: #888; }

        /* RECENT BOOKINGS PREVIEW */
        .booking-preview {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        }
        .booking-preview table { width: 100%; border-collapse: collapse; }
        .booking-preview th {
            background: #0b72e6; color: white;
            padding: 12px; text-align: left; font-size: 0.9rem;
        }
        .booking-preview td {
            padding: 12px; border-bottom: 1px solid #eee; font-size: 0.9rem;
        }
        .booking-preview tr:hover td { background: #f5f9ff; }
        .badge {
            padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;
        }
        .badge-confirmed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .no-data { text-align: center; padding: 30px; color: #aaa; }
        .view-all { display: inline-block; margin-top: 15px; color: #0b72e6; text-decoration: none; font-weight: bold; font-size: 0.9rem; }
        .view-all:hover { text-decoration: underline; }

        /* GUEST BANNER */
        .guest-banner {
            max-width: 700px; margin: 60px auto; text-align: center; padding: 50px 30px;
            background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .guest-banner h2 { color: #0b72e6; margin-bottom: 15px; font-size: 1.8rem; }
        .guest-banner p { color: #666; margin-bottom: 25px; }
        .btn-primary {
            background: #0b72e6; color: white; padding: 12px 30px;
            border-radius: 30px; text-decoration: none; font-weight: bold; margin: 5px;
            display: inline-block; transition: background 0.3s;
        }
        .btn-primary:hover { background: #0556b3; }
        .btn-outline {
            border: 2px solid #0b72e6; color: #0b72e6; padding: 12px 30px;
            border-radius: 30px; text-decoration: none; font-weight: bold; margin: 5px;
            display: inline-block; transition: all 0.3s;
        }
        .btn-outline:hover { background: #0b72e6; color: white; }
    </style>
</head>
<body>

<?php if ($is_logged_in && $user): ?>

    <!-- HERO -->
    <div class="hero-banner">
        <h1>Welcome back, <?= htmlspecialchars($user['name']) ?> ✈️</h1>
        <p>Ready for your next adventure? Search and book your flights below.</p>
        <a href="searchflights.php" class="btn-white">Search Flights</a>
    </div>

    <!-- STATS -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="number"><?= $booking_count ?></div>
            <div class="label">Total Bookings</div>
        </div>
        <div class="stat-card">
            <div class="number">✈️</div>
            <div class="label">GoZayan Traveller</div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="section">
        <h2>Quick Actions</h2>
        <div class="cards-grid">
            <a href="searchflights.php" class="action-card">
                <div class="icon">🔍</div>
                <h3>Search Flights</h3>
                <p>Find and compare available flights</p>
            </a>
            <a href="myBookings.php" class="action-card">
                <div class="icon">🎫</div>
                <h3>My Bookings</h3>
                <p>View all your past and upcoming trips</p>
            </a>
            <a href="passengerProfile.php" class="action-card">
                <div class="icon">👤</div>
                <h3>My Profile</h3>
                <p>Update your personal information</p>
            </a>
            <a href="changePassword.php" class="action-card">
                <div class="icon">🔒</div>
                <h3>Change Password</h3>
                <p>Keep your account secure</p>
            </a>
        </div>
    </div>

    <!-- RECENT BOOKINGS -->
    <?php if ($booking_count > 0):
        $recent_stmt = $conn->prepare("
            SELECT b.*, f.flight_name, f.departure, f.arrival, f.flight_code
            FROM bookings b
            JOIN flights f ON b.flight_id = f.id
            WHERE b.user_id = ?
            ORDER BY b.booking_date DESC LIMIT 5
        ");
        $recent_stmt->bind_param("i", $user['id']);
        $recent_stmt->execute();
        $recent_bookings = $recent_stmt->get_result();
    ?>
    <div class="section">
        <h2>Recent Bookings</h2>
        <div class="booking-preview">
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Flight</th>
                        <th>Route</th>
                        <th>Date</th>
                        <th>Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($b = $recent_bookings->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td><?= htmlspecialchars($b['flight_name']) ?> (<?= htmlspecialchars($b['flight_code']) ?>)</td>
                        <td><?= htmlspecialchars($b['departure']) ?> → <?= htmlspecialchars($b['arrival']) ?></td>
                        <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                        <td>৳<?= number_format($b['total_price'], 2) ?></td>
                        <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <a href="myBookings.php" class="view-all">View All Bookings →</a>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- GUEST VIEW -->
    <div class="hero-banner">
        <h1>✈️ GoZayan Flight Booking</h1>
        <p>Search for flights freely. Login to book your seat.</p>
        <a href="searchflights.php" class="btn-white">Search Flights</a>
    </div>

    <div class="guest-banner">
        <h2>Ready to take off?</h2>
        <p>Create a free account or login to book flights, track your bookings, and manage your profile.</p>
        <a href="login.php" class="btn-primary">Login</a>
        <a href="register.php" class="btn-outline">Register</a>
    </div>
<?php endif; ?>

</body>
</html>

<?php include("../includes/footer.php"); ?>