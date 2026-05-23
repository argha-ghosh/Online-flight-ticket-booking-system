<?php
session_start();
include("../model/db_conn.php");

// Must be logged in as webuser
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php");
    exit;
}

// Must come via POST with flight_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['flight_id'])) {
    header("Location: passengerHome.php");
    exit;
}

// Fetch flight details
$flight_id = (int)$_POST['flight_id'];
$stmt = $conn->prepare("SELECT * FROM flights WHERE id = ?");
$stmt->bind_param("i", $flight_id);
$stmt->execute();
$flight = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$flight) {
    header("Location: passengerHome.php");
    exit;
}

// Carry booking details from search
$trip_type   = $_POST['trip_type'] ?? 'one-way';
$from        = $_POST['from'] ?? $flight['departure'];
$to          = $_POST['to'] ?? $flight['arrival'];
$depart_date = $_POST['depart_date'] ?? date('Y-m-d');
$adults      = max(1, (int)($_POST['adults'] ?? 1));
$children    = max(0, (int)($_POST['children'] ?? 0));
$class       = $_POST['class'] ?? 'Economy';
$total_price = (float)($_POST['total_price'] ?? ($flight['price'] * ($adults + $children)));

$error = '';

// Handle payment submission
if (isset($_POST['pay_now'])) {
    $card_holder = trim($_POST['card_holder'] ?? '');
    $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $expiry      = trim($_POST['expiry'] ?? '');
    $cvv         = trim($_POST['cvv'] ?? '');
    $pay_method  = $_POST['pay_method'] ?? 'card';

    // Basic validation
    if (empty($card_holder)) {
        $error = "Card holder name is required.";
    } elseif (strlen($card_number) < 13 || strlen($card_number) > 19) {
        $error = "Please enter a valid card number.";
    } elseif (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
        $error = "Expiry must be in MM/YY format.";
    } elseif (strlen($cvv) < 3) {
        $error = "CVV must be at least 3 digits.";
    } else {
        // Get user id
        $email = $_SESSION['email'];
        $u_stmt = $conn->prepare("SELECT id FROM webusers WHERE email = ?");
        $u_stmt->bind_param("s", $email);
        $u_stmt->execute();
        $u_row = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();
        $user_id = $u_row['id'];

        $card_last4 = substr($card_number, -4);

        // Insert booking
        $ins = $conn->prepare("
            INSERT INTO bookings (user_id, flight_id, trip_type, from_location, to_location, depart_date, adults, children, class, total_price, payment_method, card_last4, card_holder, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
        ");
        $ins->bind_param(
            "iissssiiissss",
            $user_id, $flight_id, $trip_type, $from, $to,
            $depart_date, $adults, $children, $class,
            $total_price, $pay_method, $card_last4, $card_holder
        );

        if ($ins->execute()) {
            $booking_id = $ins->insert_id;
            $ins->close();

            // Decrease seat count
            $conn->query("UPDATE flights SET seat = seat - " . ($adults + $children) . " WHERE id = $flight_id AND seat > 0");

            // Redirect to confirmation
            header("Location: booking_confirm.php?id=$booking_id");
            exit;
        } else {
            $error = "Booking failed. Please try again.";
        }
    }
}

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Payment</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }

        .page-header {
            background: linear-gradient(135deg, #0b72e6, #0556b3);
            color: white; padding: 30px; text-align: center;
        }
        .page-header h1 { font-size: 1.8rem; }
        .page-header p { opacity: 0.85; margin-top: 5px; }

        .payment-wrapper {
            max-width: 900px; margin: 35px auto; padding: 0 20px;
            display: grid; grid-template-columns: 1fr 380px; gap: 25px;
        }

        /* FLIGHT SUMMARY */
        .summary-box {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden;
        }
        .summary-header {
            background: #0b72e6; color: white; padding: 15px 20px;
            font-weight: bold; font-size: 1rem;
        }
        .summary-body { padding: 20px; }
        .summary-flight-img {
            width: 100%; height: 160px; object-fit: cover;
            border-radius: 8px; margin-bottom: 15px;
        }
        .summary-row {
            display: flex; justify-content: space-between;
            padding: 8px 0; border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row .lbl { color: #888; }
        .summary-row .val { font-weight: 600; color: #333; text-align: right; max-width: 55%; }
        .route-display {
            text-align: center; padding: 15px 0; border-bottom: 1px solid #f0f0f0;
            margin-bottom: 5px;
        }
        .route-display .city { font-size: 1.3rem; font-weight: bold; color: #0b72e6; }
        .route-display .arrow { font-size: 1.5rem; color: #aaa; margin: 0 10px; }

        .price-total {
            background: #f0f7ff; border-radius: 8px; padding: 15px;
            margin-top: 15px; text-align: center;
        }
        .price-total .label { font-size: 0.85rem; color: #666; }
        .price-total .amount { font-size: 2rem; font-weight: bold; color: #0b72e6; }

        /* PAYMENT FORM */
        .payment-box {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden;
        }
        .payment-header {
            background: #0b72e6; color: white; padding: 15px 20px;
            font-weight: bold; font-size: 1rem;
        }
        .payment-body { padding: 25px; }

        .error-msg {
            background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
            padding: 12px; border-radius: 8px; margin-bottom: 15px;
            font-size: 0.9rem;
        }

        /* PAY METHOD TABS */
        .pay-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .pay-tab {
            flex: 1; padding: 10px; border: 2px solid #ddd;
            border-radius: 8px; text-align: center; cursor: pointer;
            transition: all 0.2s; font-size: 0.85rem; font-weight: 600;
            color: #666;
        }
        .pay-tab.active { border-color: #0b72e6; background: #e8f2ff; color: #0b72e6; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #555; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; }
        .form-group input, .form-group select {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #ddd; border-radius: 8px;
            font-size: 0.95rem; transition: border 0.2s;
        }
        .form-group input:focus, .form-group select:focus { border-color: #0b72e6; outline: none; }

        .form-row-2 { display: flex; gap: 12px; }
        .form-row-2 .form-group { flex: 1; }

        /* CARD VISUAL */
        .card-visual {
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            border-radius: 14px; padding: 22px; color: white; margin-bottom: 20px;
            position: relative; overflow: hidden; min-height: 140px;
        }
        .card-visual::before {
            content: ''; position: absolute; top: -30px; right: -30px;
            width: 120px; height: 120px; border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }
        .card-visual::after {
            content: ''; position: absolute; bottom: -40px; right: 40px;
            width: 160px; height: 160px; border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .card-chip { font-size: 1.5rem; margin-bottom: 12px; }
        .card-number-display { font-size: 1.05rem; letter-spacing: 3px; margin-bottom: 14px; font-family: monospace; color: rgba(255,255,255,0.85); }
        .card-bottom { display: flex; justify-content: space-between; align-items: flex-end; }
        .card-label { font-size: 0.65rem; color: rgba(255,255,255,0.5); text-transform: uppercase; }
        .card-value { font-size: 0.88rem; font-weight: 600; }
        .card-logo { font-size: 1.6rem; }

        .pay-btn {
            width: 100%; padding: 14px; background: #0b72e6; color: white;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: bold;
            cursor: pointer; transition: background 0.3s; margin-top: 5px;
        }
        .pay-btn:hover { background: #0556b3; }

        .secure-note { text-align: center; margin-top: 12px; font-size: 0.78rem; color: #aaa; }

        @media (max-width: 700px) {
            .payment-wrapper { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="page-header">
    <h1>💳 Secure Payment</h1>
    <p>Complete your booking for <?= htmlspecialchars($flight['flight_name']) ?></p>
</div>

<form action="" method="POST">
    <!-- Hidden fields to carry booking data -->
    <input type="hidden" name="flight_id" value="<?= $flight_id ?>">
    <input type="hidden" name="trip_type" value="<?= htmlspecialchars($trip_type) ?>">
    <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
    <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
    <input type="hidden" name="depart_date" value="<?= htmlspecialchars($depart_date) ?>">
    <input type="hidden" name="adults" value="<?= $adults ?>">
    <input type="hidden" name="children" value="<?= $children ?>">
    <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
    <input type="hidden" name="total_price" value="<?= $total_price ?>">
    <input type="hidden" name="pay_method" id="pay_method_input" value="card">

    <div class="payment-wrapper">
        <!-- FLIGHT SUMMARY -->
        <div class="summary-box">
            <div class="summary-header">✈️ Booking Summary</div>
            <div class="summary-body">
                <?php if (!empty($flight['image'])): ?>
                    <img src="upload/<?= htmlspecialchars($flight['image']) ?>" class="summary-flight-img" alt="Flight">
                <?php endif; ?>

                <div class="route-display">
                    <span class="city"><?= htmlspecialchars($from) ?></span>
                    <span class="arrow">→</span>
                    <span class="city"><?= htmlspecialchars($to) ?></span>
                </div>

                <div class="summary-row">
                    <span class="lbl">Flight</span>
                    <span class="val"><?= htmlspecialchars($flight['flight_name']) ?></span>
                </div>
                <div class="summary-row">
                    <span class="lbl">Airline</span>
                    <span class="val"><?= htmlspecialchars($flight['airline_name']) ?> (<?= htmlspecialchars($flight['flight_code']) ?>)</span>
                </div>
                <div class="summary-row">
                    <span class="lbl">Date</span>
                    <span class="val"><?= date('d M Y', strtotime($depart_date)) ?></span>
                </div>
                <div class="summary-row">
                    <span class="lbl">Duration</span>
                    <span class="val"><?= htmlspecialchars($flight['duration']) ?></span>
                </div>
                <div class="summary-row">
                    <span class="lbl">Trip Type</span>
                    <span class="val"><?= ucfirst($trip_type) ?></span>
                </div>
                <div class="summary-row">
                    <span class="lbl">Passengers</span>
                    <span class="val"><?= $adults ?> Adult<?= $adults > 1 ? 's' : '' ?><?= $children > 0 ? ", $children Child" . ($children > 1 ? 'ren' : '') : '' ?></span>
                </div>
                <div class="summary-row">
                    <span class="lbl">Class</span>
                    <span class="val"><?= htmlspecialchars($class) ?></span>
                </div>
                <div class="summary-row">
                    <span class="lbl">Price per person</span>
                    <span class="val">$<?= number_format($flight['price'], 2) ?></span>
                </div>

                <div class="price-total">
                    <div class="label">Total Amount</div>
                    <div class="amount">$<?= number_format($total_price, 2) ?></div>
                </div>
            </div>
        </div>

        <!-- PAYMENT FORM -->
        <div class="payment-box">
            <div class="payment-header">💳 Payment Details</div>
            <div class="payment-body">

                <?php if ($error): ?>
                    <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Payment Method Tabs -->
                <div class="pay-tabs">
                    <div class="pay-tab active" onclick="setMethod('card', this)">💳 Credit/Debit</div>
                    <div class="pay-tab" onclick="setMethod('bkash', this)">🟣 bKash</div>
                    <div class="pay-tab" onclick="setMethod('nagad', this)">🔴 Nagad</div>
                </div>

                <!-- Card Visual -->
                <div class="card-visual" id="cardVisual">
                    <div class="card-chip">▬▬</div>
                    <div class="card-number-display" id="cardDisplay">•••• •••• •••• ••••</div>
                    <div class="card-bottom">
                        <div>
                            <div class="card-label">Card Holder</div>
                            <div class="card-value" id="cardNameDisplay">YOUR NAME</div>
                        </div>
                        <div>
                            <div class="card-label">Expires</div>
                            <div class="card-value" id="cardExpiryDisplay">MM/YY</div>
                        </div>
                        <div class="card-logo">💳</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Card Holder Name</label>
                    <input type="text" name="card_holder" id="card_holder" placeholder="As printed on card"
                           value="<?= htmlspecialchars($_POST['card_holder'] ?? '') ?>"
                           oninput="document.getElementById('cardNameDisplay').textContent = this.value.toUpperCase() || 'YOUR NAME'">
                </div>

                <div class="form-group">
                    <label>Card Number</label>
                    <input type="text" name="card_number" id="card_number" placeholder="1234 5678 9012 3456"
                           maxlength="19" oninput="formatCard(this)">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Expiry (MM/YY)</label>
                        <input type="text" name="expiry" placeholder="MM/YY" maxlength="5"
                               oninput="formatExpiry(this)"
                               value="<?= htmlspecialchars($_POST['expiry'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>CVV</label>
                        <input type="password" name="cvv" placeholder="•••" maxlength="4">
                    </div>
                </div>

                <button type="submit" name="pay_now" class="pay-btn">
                    🔒 Pay $<?= number_format($total_price, 2) ?>
                </button>
                <div class="secure-note">🔐 Your payment is encrypted and secure</div>
            </div>
        </div>
    </div>
</form>

<script>
function formatCard(input) {
    let val = input.value.replace(/\D/g, '').substring(0, 16);
    input.value = val.replace(/(.{4})/g, '$1 ').trim();
    let display = val.padEnd(16, '•').replace(/(.{4})/g, '$1 ').trim();
    document.getElementById('cardDisplay').textContent = display;
}
function formatExpiry(input) {
    let val = input.value.replace(/\D/g, '').substring(0, 4);
    if (val.length >= 2) val = val.substring(0,2) + '/' + val.substring(2);
    input.value = val;
    document.getElementById('cardExpiryDisplay').textContent = val || 'MM/YY';
}
function setMethod(method, tab) {
    document.querySelectorAll('.pay-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('pay_method_input').value = method;
    const visual = document.getElementById('cardVisual');
    if (method === 'bkash') {
        visual.style.background = 'linear-gradient(135deg, #e2136e, #b00e56)';
    } else if (method === 'nagad') {
        visual.style.background = 'linear-gradient(135deg, #f55a00, #c94400)';
    } else {
        visual.style.background = 'linear-gradient(135deg, #1a1a2e, #16213e, #0f3460)';
    }
}
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?>