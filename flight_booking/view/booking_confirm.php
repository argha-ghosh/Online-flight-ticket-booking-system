<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}
if (!isset($_GET['id'])) {
    header("Location: passengerHome.php"); exit;
}

$booking_id = (int)$_GET['id'];
$email = $_SESSION['email'];

// Fetch booking with flight details, verify it belongs to this user
$stmt = $conn->prepare("
    SELECT b.*, f.flight_name, f.airline_name, f.flight_code, f.duration, f.image as flight_image,
           w.name as passenger_name, w.email as passenger_email
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    JOIN webusers w ON b.user_id = w.id
    WHERE b.id = ? AND w.email = ?
");
$stmt->bind_param("is", $booking_id, $email);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header("Location: myBookings.php"); exit;
}

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Booking Confirmed</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }

        .success-banner {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white; text-align: center; padding: 40px 20px;
        }
        .success-banner .checkmark { font-size: 3.5rem; margin-bottom: 10px; }
        .success-banner h1 { font-size: 2rem; margin-bottom: 8px; }
        .success-banner p { opacity: 0.9; font-size: 1rem; }

        .ticket-wrapper { max-width: 680px; margin: 35px auto; padding: 0 20px 50px; }

        /* TICKET DESIGN */
        .ticket {
            background: white; border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden; margin-bottom: 20px;
        }

        .ticket-top {
            background: linear-gradient(135deg, #0b72e6, #0556b3);
            padding: 25px 30px; color: white;
        }
        .ticket-top .airline-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .ticket-top .airline-name { font-size: 1.1rem; font-weight: bold; opacity: 0.9; }
        .ticket-top .flight-code { font-size: 0.85rem; opacity: 0.7; }
        .ticket-top .booking-id { font-size: 0.9rem; font-family: monospace; background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px; }

        .route-main {
            display: flex; align-items: center; justify-content: space-between;
        }
        .route-city { text-align: center; }
        .route-city .city-name { font-size: 2.2rem; font-weight: bold; }
        .route-city .city-label { font-size: 0.78rem; opacity: 0.7; margin-top: 3px; }

        .route-middle { flex: 1; text-align: center; padding: 0 20px; }
        .route-line {
            display: flex; align-items: center; gap: 8px; justify-content: center;
            margin-bottom: 5px;
        }
        .route-line .line { flex: 1; height: 1px; background: rgba(255,255,255,0.3); }
        .route-middle .plane { font-size: 1.6rem; }
        .route-middle .duration { font-size: 0.78rem; opacity: 0.7; }

        /* TICKET DIVIDER */
        .ticket-divider {
            display: flex; align-items: center; position: relative;
            padding: 0 20px; background: white;
        }
        .ticket-divider::before {
            content: ''; position: absolute; left: 0; top: 50%;
            transform: translateY(-50%); width: 25px; height: 50px;
            background: #f0f4f8; border-radius: 0 25px 25px 0; border-right: 2px dashed #ddd;
        }
        .ticket-divider::after {
            content: ''; position: absolute; right: 0; top: 50%;
            transform: translateY(-50%); width: 25px; height: 50px;
            background: #f0f4f8; border-radius: 25px 0 0 25px; border-left: 2px dashed #ddd;
        }
        .ticket-divider .dashed-line {
            flex: 1; border-top: 2px dashed #ddd; margin: 20px 30px;
        }
        .ticket-divider .plane-icon { font-size: 1.2rem; color: #0b72e6; }

        /* TICKET BOTTOM */
        .ticket-bottom { padding: 25px 30px; }
        .detail-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 20px; margin-bottom: 20px;
        }
        .detail-item .label { font-size: 0.72rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .detail-item .value { font-size: 0.95rem; font-weight: 600; color: #333; }

        .ticket-footer {
            border-top: 1px solid #f0f0f0; padding-top: 18px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px;
        }
        .total-price-section .label { font-size: 0.75rem; color: #aaa; }
        .total-price-section .amount { font-size: 1.8rem; font-weight: bold; color: #0b72e6; }
        .status-badge {
            background: #d4edda; color: #155724; padding: 6px 18px;
            border-radius: 20px; font-weight: bold; font-size: 0.9rem;
        }

        /* PASSENGER INFO */
        .info-box {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            padding: 20px 25px; margin-bottom: 20px;
        }
        .info-box h3 { font-size: 1rem; color: #333; margin-bottom: 15px; border-left: 3px solid #0b72e6; padding-left: 10px; }
        .info-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #f5f5f5; font-size: 0.9rem; }
        .info-row:last-child { border-bottom: none; }
        .info-row .k { color: #888; }
        .info-row .v { font-weight: 600; color: #333; }

        /* ACTION BUTTONS */
        .action-btns { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-primary {
            flex: 1; padding: 13px 20px; background: #0b72e6; color: white;
            border: none; border-radius: 8px; font-size: 0.95rem; font-weight: bold;
            cursor: pointer; text-align: center; text-decoration: none;
            transition: background 0.3s; display: inline-block;
        }
        .btn-primary:hover { background: #0556b3; }
        .btn-outline {
            flex: 1; padding: 13px 20px; background: white; color: #0b72e6;
            border: 2px solid #0b72e6; border-radius: 8px; font-size: 0.95rem;
            font-weight: bold; cursor: pointer; text-align: center; text-decoration: none;
            transition: all 0.3s; display: inline-block;
        }
        .btn-outline:hover { background: #0b72e6; color: white; }

        @media print {
            .action-btns, header, footer { display: none !important; }
            body { background: white; }
            .ticket { box-shadow: none; }
        }
        @media (max-width: 500px) {
            .detail-grid { grid-template-columns: repeat(2, 1fr); }
            .route-city .city-name { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

<div class="success-banner">
    <div class="checkmark">✅</div>
    <h1>Booking Confirmed!</h1>
    <p>Your ticket has been booked successfully. Have a great flight!</p>
</div>

<div class="ticket-wrapper">

    <!-- TICKET -->
    <div class="ticket" id="printTicket">
        <!-- TOP: Route -->
        <div class="ticket-top">
            <div class="airline-row">
                <div>
                    <div class="airline-name">✈️ <?= htmlspecialchars($booking['airline_name']) ?></div>
                    <div class="flight-code"><?= htmlspecialchars($booking['flight_code']) ?> · <?= ucfirst($booking['trip_type']) ?> · <?= htmlspecialchars($booking['class']) ?></div>
                </div>
                <div class="booking-id">
                    #<?= str_pad($booking['id'], 5, '0', STR_PAD_LEFT) ?>
                </div>
            </div>
            <div class="route-main">
                <div class="route-city">
                    <div class="city-name"><?= strtoupper(substr($booking['from_location'], 0, 3)) ?></div>
                    <div class="city-label"><?= htmlspecialchars($booking['from_location']) ?></div>
                </div>
                <div class="route-middle">
                    <div class="route-line">
                        <div class="line"></div>
                        <div class="plane">✈️</div>
                        <div class="line"></div>
                    </div>
                    <div class="duration"><?= htmlspecialchars($booking['duration']) ?></div>
                </div>
                <div class="route-city">
                    <div class="city-name"><?= strtoupper(substr($booking['to_location'], 0, 3)) ?></div>
                    <div class="city-label"><?= htmlspecialchars($booking['to_location']) ?></div>
                </div>
            </div>
        </div>

        <!-- DIVIDER -->
        <div class="ticket-divider">
            <div class="dashed-line"></div>
            <div class="plane-icon">✂</div>
            <div class="dashed-line"></div>
        </div>

        <!-- BOTTOM: Details -->
        <div class="ticket-bottom">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="label">Passenger</div>
                    <div class="value"><?= htmlspecialchars($booking['passenger_name']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Depart Date</div>
                    <div class="value"><?= date('d M Y', strtotime($booking['depart_date'])) ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Flight</div>
                    <div class="value"><?= htmlspecialchars($booking['flight_name']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Adults</div>
                    <div class="value"><?= $booking['adults'] ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Children</div>
                    <div class="value"><?= $booking['children'] ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Booked On</div>
                    <div class="value"><?= date('d M Y', strtotime($booking['booking_date'])) ?></div>
                </div>
            </div>

            <div class="ticket-footer">
                <div class="total-price-section">
                    <div class="label">Total Paid</div>
                    <div class="amount">৳<?= number_format($booking['total_price'], 2) ?></div>
                </div>
                <span class="status-badge">✔ <?= ucfirst($booking['status']) ?></span>
            </div>
        </div>
    </div>

    <!-- PAYMENT INFO -->
    <div class="info-box">
        <h3>Payment Information</h3>
        <div class="info-row"><span class="k">Payment Method</span><span class="v"><?= ucfirst($booking['payment_method']) ?></span></div>
        <div class="info-row"><span class="k">Card</span><span class="v">•••• •••• •••• <?= htmlspecialchars($booking['card_last4']) ?></span></div>
        <div class="info-row"><span class="k">Card Holder</span><span class="v"><?= htmlspecialchars($booking['card_holder']) ?></span></div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-btns">
        <button onclick="window.print()" class="btn-primary">🖨️ Print Ticket</button>
        <a href="myBookings.php" class="btn-outline">📋 My Bookings</a>
        <a href="passengerHome.php" class="btn-outline">🔍 Search More</a>
    </div>

</div>
</body>
</html>

<?php include("../includes/footer.php"); ?>