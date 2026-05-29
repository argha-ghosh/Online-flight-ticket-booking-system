<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}
if (!isset($_GET['id'])) {
    header("Location: myBookings.php"); exit;
}

$booking_id = (int)$_GET['id'];
$email = $_SESSION['email'];

$stmt = $conn->prepare("
    SELECT b.*,
           f.flight_name, f.airline_name, f.flight_code, f.duration, f.image as flight_image,
           f.departure_time, f.arrival_time,
           f.status as flight_status,
           ROUND(f.price * (1 - f.discount_pct / 100), 2) AS current_unit_price,
           f.discount_pct,
           s.departure_day, s.arrival_day,
           s.departure_time AS sched_dep_time,
           s.arrival_time   AS sched_arr_time,
           w.name as passenger_name, w.email as passenger_email, w.image as user_image
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    LEFT JOIN schedule s ON s.flight_code COLLATE utf8mb4_unicode_ci = f.flight_code
    JOIN webusers w ON b.user_id = w.id
    WHERE b.id = ? AND w.email = ?
");
$stmt->bind_param("is", $booking_id, $email);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) { header("Location: myBookings.php"); exit; }

// ── Resolve live schedule times once, used throughout the page ──
$dep_t   = substr(!empty($booking['sched_dep_time']) ? $booking['sched_dep_time'] : ($booking['departure_time'] ?? ''), 0, 5);
$arr_t   = substr(!empty($booking['sched_arr_time']) ? $booking['sched_arr_time'] : ($booking['arrival_time']   ?? ''), 0, 5);
$dep_day = $booking['departure_day'] ?? '';
$arr_day = $booking['arrival_day']   ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Booking #<?= str_pad($booking['id'],6,'0',STR_PAD_LEFT) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:#1a6ff4; --primary-dark:#0d4fc4; --primary-glow:rgba(26,111,244,0.18);
            --secondary:#0a2d6e; --accent:#06c8a0; --dark:#0d1f35; --mid:#3d5a7a;
            --muted:#7a95b0; --border:#dce8f5; --surface:#ffffff; --bg:#f0f4fb;
            --sidebar-w:260px;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--dark);
             min-height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased}

        /* LAYOUT */
        .dashboard{display:flex;flex:1}

        /* SIDEBAR */
        .sidebar{width:var(--sidebar-w);flex-shrink:0;
            background:linear-gradient(180deg,var(--secondary) 0%,#0d1f35 100%);
            display:flex;flex-direction:column;
            position:sticky;top:0;height:100vh;overflow-y:auto;z-index:100}
        .sidebar-brand{padding:28px 24px 20px;font-size:1.4rem;font-weight:900;color:#fff;
            letter-spacing:-0.5px;border-bottom:1px solid rgba(255,255,255,0.08)}
        .sidebar-brand a{text-decoration:none;color:inherit}
        .sidebar-brand span{color:#60a5fa}
        .sidebar-profile{padding:22px 20px;display:flex;flex-direction:column;
            align-items:center;gap:8px;border-bottom:1px solid rgba(255,255,255,0.08)}
        .profile-avatar{width:64px;height:64px;border-radius:50%;object-fit:cover;
            border:3px solid rgba(255,255,255,0.2)}
        .profile-avatar-placeholder{width:64px;height:64px;border-radius:50%;
            background:linear-gradient(135deg,var(--primary),var(--accent));
            display:flex;align-items:center;justify-content:center;font-size:1.6rem;
            border:3px solid rgba(255,255,255,0.2)}
        .profile-name{font-size:0.9rem;font-weight:700;color:#fff;text-align:center}
        .profile-email{font-size:0.72rem;color:rgba(255,255,255,0.4);text-align:center;word-break:break-all}
        .sidebar-nav{padding:16px 12px;flex:1}
        .nav-label{font-size:0.65rem;font-weight:700;color:rgba(255,255,255,0.3);
            text-transform:uppercase;letter-spacing:1.2px;padding:0 12px;margin:14px 0 6px}
        .nav-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;
            text-decoration:none;color:rgba(255,255,255,0.6);font-size:0.88rem;font-weight:500;
            transition:all 0.2s;margin-bottom:2px}
        .nav-item:hover{background:rgba(255,255,255,0.08);color:#fff}
        .nav-item.active{background:rgba(26,111,244,0.25);color:#fff;font-weight:600}
        .nav-icon{font-size:1.1rem;width:22px;text-align:center}
        .sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.08)}
        .logout-btn{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;
            text-decoration:none;color:rgba(255,100,100,0.8);font-size:0.88rem;font-weight:600;transition:all 0.2s}
        .logout-btn:hover{background:rgba(239,68,68,0.12);color:#fca5a5}

        /* MAIN */
        .main{flex:1;display:flex;flex-direction:column;min-width:0}
        .topbar{background:var(--surface);border-bottom:1px solid var(--border);
            padding:16px 32px;display:flex;align-items:center;
            justify-content:space-between;gap:16px;position:sticky;top:0;z-index:10}
        .topbar-title{font-size:1rem;font-weight:800;color:var(--dark)}
        .topbar-back{display:flex;align-items:center;gap:6px;font-size:0.85rem;
            font-weight:600;color:var(--muted);text-decoration:none;transition:color 0.2s}
        .topbar-back:hover{color:var(--primary)}
    </style>
</head>
<body>
    <style>
        /* SUCCESS STRIP */
        .success-strip{background:linear-gradient(90deg,#059669 0%,#06c8a0 100%);
            padding:18px 32px;display:flex;align-items:center;gap:14px;color:#fff}
        .success-strip .s-icon{font-size:1.6rem}
        .success-strip h2{font-size:1.05rem;font-weight:800}
        .success-strip p{font-size:0.82rem;opacity:0.85;margin-top:2px}
        .success-strip .ref-pill{margin-left:auto;background:rgba(255,255,255,0.2);
            border:1px solid rgba(255,255,255,0.35);padding:6px 18px;border-radius:50px;
            font-size:0.82rem;font-weight:700;font-family:monospace;white-space:nowrap}

        /* PAGE BODY — 2 columns */
        .page-body{padding:28px 32px 60px;display:grid;
            grid-template-columns:1fr 300px;gap:24px;align-items:start}

        /* BOARDING PASS */
        .bp{background:var(--surface);border-radius:20px;overflow:hidden;
            border:1px solid var(--border);
            box-shadow:0 4px 24px rgba(13,31,53,0.08)}

        .bp-header{background:linear-gradient(135deg,var(--secondary) 0%,var(--primary) 100%);
            padding:28px 30px;color:#fff;position:relative;overflow:hidden}
        .bp-header::after{content:'✈';position:absolute;right:-10px;top:-20px;
            font-size:8rem;opacity:0.06}
        .bp-airline-row{display:flex;justify-content:space-between;align-items:flex-start;
            margin-bottom:24px}
        .bp-airline-name{font-size:1rem;font-weight:700;opacity:0.9}
        .bp-flight-meta{font-size:0.78rem;opacity:0.6;margin-top:3px}
        .bp-ref{background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);
            padding:5px 14px;border-radius:50px;font-size:0.8rem;font-weight:700;
            font-family:monospace;white-space:nowrap}

        /* Route */
        .bp-route{display:flex;align-items:center;gap:0}
        .bp-city-block{text-align:center}
        .bp-iata{font-size:2.8rem;font-weight:900;letter-spacing:-2px;line-height:1}
        .bp-city-name{font-size:0.72rem;opacity:0.65;margin-top:4px}
        .bp-route-mid{flex:1;display:flex;flex-direction:column;align-items:center;
            gap:5px;padding:0 16px}
        .bp-route-track{width:100%;display:flex;align-items:center}
        .bp-route-track::before,.bp-route-track::after{content:'';flex:1;height:1.5px;
            background:rgba(255,255,255,0.3)}
        .bp-route-plane{font-size:1.1rem;margin:0 6px}
        .bp-dur{font-size:0.7rem;opacity:0.6;background:rgba(255,255,255,0.1);
            padding:2px 10px;border-radius:10px}

        /* Tear line */
        .bp-tear{display:flex;align-items:center;background:var(--bg);
            position:relative;height:32px}
        .bp-tear::before{content:'';position:absolute;left:0;top:50%;
            transform:translateY(-50%);width:20px;height:40px;
            background:var(--bg);border-radius:0 20px 20px 0}
        .bp-tear::after{content:'';position:absolute;right:0;top:50%;
            transform:translateY(-50%);width:20px;height:40px;
            background:var(--bg);border-radius:20px 0 0 20px}
        .bp-tear-line{flex:1;margin:0 24px;border-top:2px dashed var(--border)}
        .bp-tear-icon{font-size:0.9rem;color:var(--muted)}

        /* Details grid */
        .bp-details{padding:24px 30px}
        .bp-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:22px}
        .bp-field .lbl{font-size:0.65rem;font-weight:700;color:var(--muted);
            text-transform:uppercase;letter-spacing:0.8px;margin-bottom:4px}
        .bp-field .val{font-size:0.9rem;font-weight:700;color:var(--dark)}

        /* Price row */
        .bp-price-row{display:flex;align-items:center;justify-content:space-between;
            padding-top:18px;border-top:1px solid var(--border)}
        .bp-total-lbl{font-size:0.7rem;font-weight:700;color:var(--muted);
            text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px}
        .bp-total-amt{font-size:2rem;font-weight:900;color:var(--primary);letter-spacing:-1px}
        .bp-status{display:inline-flex;align-items:center;gap:6px;
            padding:8px 20px;border-radius:50px;font-size:0.82rem;font-weight:700}
        .status-confirmed{background:rgba(6,200,160,0.1);color:#047857;
            border:1px solid rgba(6,200,160,0.3)}
        .status-cancelled{background:rgba(239,68,68,0.08);color:#dc2626;
            border:1px solid rgba(239,68,68,0.2)}

        /* RIGHT COLUMN */
        .right-col{display:flex;flex-direction:column;gap:18px}

        .panel{background:var(--surface);border-radius:18px;border:1px solid var(--border);
            overflow:hidden;box-shadow:0 2px 12px rgba(13,31,53,0.05)}
        .panel-head{padding:16px 20px;border-bottom:1px solid var(--border);
            font-size:0.88rem;font-weight:800;color:var(--dark)}
        .panel-body{padding:16px 20px}

        /* Info rows */
        .info-row{display:flex;justify-content:space-between;align-items:center;
            padding:9px 0;border-bottom:1px solid #f0f5fb;font-size:0.85rem}
        .info-row:last-child{border-bottom:none}
        .info-row .k{color:var(--muted);font-weight:500}
        .info-row .v{font-weight:700;color:var(--dark);text-align:right}

        /* Action buttons */
        .action-btn{display:flex;align-items:center;justify-content:center;gap:8px;
            width:100%;padding:12px;border-radius:12px;font-size:0.88rem;font-weight:700;
            font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;
            transition:all 0.22s;border:none;margin-bottom:10px}
        .action-btn:last-child{margin-bottom:0}
        .btn-blue{background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            color:#fff;box-shadow:0 4px 14px var(--primary-glow)}
        .btn-blue:hover{transform:translateY(-2px);filter:brightness(1.06)}
        .btn-ghost{background:var(--surface);color:var(--primary);
            border:1.5px solid rgba(26,111,244,0.25)}
        .btn-ghost:hover{background:rgba(26,111,244,0.05);border-color:var(--primary)}

        /* Responsive */
        @media(max-width:900px){.page-body{grid-template-columns:1fr}}
        @media(max-width:768px){
            .sidebar{display:none}
            .page-body{padding:20px 16px 50px}
            .topbar{padding:14px 16px}
            .success-strip{padding:14px 16px;flex-wrap:wrap}
            .success-strip .ref-pill{margin-left:0}
            .bp-grid{grid-template-columns:repeat(2,1fr)}
            .bp-iata{font-size:2rem}
        }

        /* ══ FOOTER STYLING ══ */
        footer {
            background: linear-gradient(135deg, var(--secondary) 0%, #0d1f35 100%);
            color: rgba(255, 255, 255, 0.75);
            padding: 36px 32px;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            text-align: center;
        }
        .footer-container p {
            font-size: 0.88rem;
            line-height: 1.6;
        }
        .footer-container a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .footer-container a:hover {
            color: #93c5fd;
            text-decoration: underline;
        }
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 14px;
        }
        .social-icons a {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 18px;
            transition: all 0.25s ease;
        }
        .social-icons a:hover {
            background: #1a6ff4;
            transform: translateY(-2px);
        }
        .contact-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.55);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 14px;
            width: 100%;
            max-width: 600px;
        }
        .contact-info span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ══════════════════════════════════════
           PRINT — Full airline boarding pass
        ══════════════════════════════════════ */
        @media print {
            /* Hide everything except the ticket */
            .sidebar, .topbar, .right-col, .success-strip,
            .dashboard > .main > .topbar,
            header, footer { display: none !important; }

            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

            html, body {
                margin: 0; padding: 0;
                background: #fff !important;
                font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
            }

            .dashboard { display: block !important; }
            .main       { display: block !important; margin: 0 !important; }
            .page-body  { display: block !important; padding: 0 !important; margin: 0 !important; }

            /* Hide the screen boarding pass — we show the print-ticket instead */
            .bp { display: none !important; }

            /* ── PRINT TICKET ── */
            .print-ticket {
                display: flex !important;
                width: 210mm;
                min-height: 99mm;
                margin: 8mm auto;
                border-radius: 6mm;
                overflow: hidden;
                box-shadow: 0 0 0 1px #d0d8e8;
                font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
                page-break-inside: avoid;
            }

            /* LEFT main section */
            .pt-main {
                flex: 1;
                display: flex;
                flex-direction: column;
                border-right: 2px dashed #c8d6e8;
                position: relative;
            }

            /* Notch cutouts on tear line */
            .pt-main::after {
                content: '';
                position: absolute;
                right: -8px; top: 50%;
                transform: translateY(-50%);
                width: 16px; height: 16px;
                background: #fff;
                border-radius: 50%;
                border: 1px solid #c8d6e8;
            }

            /* Header band */
            .pt-head {
                background: #0a2d6e !important;
                padding: 7mm 8mm 6mm;
                color: #fff !important;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
            }
            .pt-airline {
                font-size: 13pt;
                font-weight: 800;
                color: #fff !important;
                letter-spacing: -0.3px;
            }
            .pt-flight-sub {
                font-size: 7.5pt;
                color: rgba(255,255,255,0.6) !important;
                margin-top: 2px;
                letter-spacing: 0.3px;
            }
            .pt-booking-ref {
                background: rgba(255,255,255,0.15) !important;
                border: 1px solid rgba(255,255,255,0.3) !important;
                color: #fff !important;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 7.5pt;
                font-weight: 700;
                font-family: 'Courier New', monospace;
                white-space: nowrap;
            }

            /* Route row */
            .pt-route {
                display: flex;
                align-items: center;
                padding: 5mm 8mm 4mm;
                background: #0d3580 !important;
                color: #fff !important;
            }
            .pt-city { text-align: center; }
            .pt-iata {
                font-size: 30pt;
                font-weight: 900;
                color: #fff !important;
                letter-spacing: -2px;
                line-height: 1;
            }
            .pt-city-lbl {
                font-size: 7pt;
                color: rgba(255,255,255,0.6) !important;
                margin-top: 2px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .pt-route-mid {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 3px;
                padding: 0 10mm;
            }
            .pt-line-wrap {
                width: 100%;
                display: flex;
                align-items: center;
                gap: 0;
            }
            .pt-line-wrap::before,
            .pt-line-wrap::after {
                content: '';
                flex: 1;
                height: 1px;
                background: rgba(255,255,255,0.35) !important;
            }
            .pt-plane-icon { font-size: 12pt; color: #fff !important; }
            .pt-dur {
                font-size: 7pt;
                color: rgba(255,255,255,0.65) !important;
                background: rgba(255,255,255,0.12) !important;
                padding: 1px 6px;
                border-radius: 8px;
            }

            /* Details section */
            .pt-body {
                flex: 1;
                padding: 5mm 8mm;
                background: #fff !important;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .pt-fields {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 4mm 6mm;
                margin-bottom: 4mm;
            }
            .pt-f-lbl {
                font-size: 6pt;
                font-weight: 700;
                color: #8fa0b5 !important;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                margin-bottom: 1.5px;
            }
            .pt-f-val {
                font-size: 9.5pt;
                font-weight: 700;
                color: #0d1f35 !important;
            }

            /* Bottom price + status */
            .pt-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding-top: 3mm;
                border-top: 1px solid #e8f0f8 !important;
            }
            .pt-price-lbl {
                font-size: 6pt;
                font-weight: 700;
                color: #8fa0b5 !important;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                margin-bottom: 1px;
            }
            .pt-price-amt {
                font-size: 18pt;
                font-weight: 900;
                color: #1a6ff4 !important;
                letter-spacing: -1px;
                line-height: 1;
            }
            .pt-status-badge {
                padding: 3px 12px;
                border-radius: 20px;
                font-size: 8pt;
                font-weight: 700;
                background: #d1fae5 !important;
                color: #065f46 !important;
                border: 1px solid #6ee7b7 !important;
            }

            /* RIGHT stub section */
            .pt-stub {
                width: 52mm;
                display: flex;
                flex-direction: column;
                background: #f8fbff !important;
            }
            .pt-stub-head {
                background: #0a2d6e !important;
                padding: 7mm 5mm 6mm;
                color: #fff !important;
                text-align: center;
            }
            .pt-stub-logo {
                font-size: 13pt;
                font-weight: 900;
                color: #fff !important;
                letter-spacing: -0.5px;
            }
            .pt-stub-logo span { color: #60a5fa !important; }
            .pt-stub-tagline {
                font-size: 6pt;
                color: rgba(255,255,255,0.5) !important;
                margin-top: 2px;
                letter-spacing: 0.5px;
            }
            .pt-stub-route {
                background: #0d3580 !important;
                padding: 4mm 5mm;
                text-align: center;
                color: #fff !important;
            }
            .pt-stub-iata {
                font-size: 16pt;
                font-weight: 900;
                color: #fff !important;
                letter-spacing: -1px;
            }
            .pt-stub-arrow { font-size: 9pt; color: rgba(255,255,255,0.6) !important; margin: 1mm 0; }

            .pt-stub-body {
                flex: 1;
                padding: 4mm 5mm;
                display: flex;
                flex-direction: column;
                gap: 3mm;
            }
            .pt-stub-field .sl { font-size: 6pt; font-weight: 700; color: #8fa0b5 !important; text-transform: uppercase; letter-spacing: 0.6px; }
            .pt-stub-field .sv { font-size: 8.5pt; font-weight: 700; color: #0d1f35 !important; margin-top: 1px; }

            /* Barcode strip */
            .pt-barcode {
                padding: 4mm 5mm 5mm;
                text-align: center;
                border-top: 1px dashed #c8d6e8 !important;
            }
            .pt-barcode-bars {
                display: flex;
                align-items: flex-end;
                justify-content: center;
                gap: 1.5px;
                height: 14mm;
                margin-bottom: 2mm;
            }
            .pt-barcode-bars span {
                display: inline-block;
                background: #0d1f35 !important;
                border-radius: 1px;
            }
            .pt-barcode-num {
                font-size: 6pt;
                color: #8fa0b5 !important;
                font-family: 'Courier New', monospace;
                letter-spacing: 1px;
            }
        }
    </style>

<div class="dashboard">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand"><a href="home.php">Go<span>Zayan</span></a></div>
        <div class="sidebar-profile">
            <?php if (!empty($booking['user_image'])): ?>
                <img class="profile-avatar" src="uploads/<?= htmlspecialchars($booking['user_image']) ?>" alt="">
            <?php else: ?>
                <div class="profile-avatar-placeholder">✈</div>
            <?php endif; ?>
            <div class="profile-name"><?= htmlspecialchars($booking['passenger_name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($booking['passenger_email']) ?></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="userhome.php"     class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="searchflights.php" class="nav-item"><span class="nav-icon">🔍</span> Search Flights</a>
            <a href="myBookings.php"   class="nav-item active"><span class="nav-icon">🎫</span> My Bookings</a>
            <div class="nav-label">Account</div>
            <a href="passengerProfile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
            <a href="changePassword.php"   class="nav-item"><span class="nav-icon">🔒</span> Change Password</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main">

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-title">🎫 Booking Details</div>
            <a href="myBookings.php" class="topbar-back">← Back to My Bookings</a>
        </div>

        <!-- Success strip -->
        <div class="success-strip">
            <span class="s-icon">✅</span>
            <div>
                <h2>Booking Confirmed!</h2>
                <p>Your ticket has been booked successfully. Have a great flight!</p>
            </div>
            <div class="ref-pill">#<?= str_pad($booking['id'],6,'0',STR_PAD_LEFT) ?></div>
        </div>

        <!-- Body -->
        <div class="page-body">

            <!-- LEFT: Boarding pass -->
            <div class="bp">

                <!-- Header / route -->
                <div class="bp-header">
                    <div class="bp-airline-row">
                        <div>
                            <div class="bp-airline-name">✈️ <?= htmlspecialchars($booking['airline_name']) ?></div>
                            <div class="bp-flight-meta"><?= htmlspecialchars($booking['flight_code']) ?> &nbsp;·&nbsp; <?= ucfirst($booking['trip_type']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($booking['class']) ?></div>
                        </div>
                        <div class="bp-ref"><?= htmlspecialchars($booking['flight_name']) ?></div>
                    </div>

                    <div class="bp-route">
                        <div class="bp-city-block">
                            <div class="bp-iata"><?= strtoupper(substr($booking['from_location'], 0, 3)) ?></div>
                            <div class="bp-city-name"><?= htmlspecialchars($booking['from_location']) ?></div>
                            <?php if ($dep_t): ?><div style="font-size:0.85rem;font-weight:800;color:var(--primary);margin-top:3px;"><?= htmlspecialchars($dep_t) ?></div><?php endif; ?>
                            <?php if ($dep_day): ?><div style="font-size:0.7rem;color:var(--muted);"><?= htmlspecialchars($dep_day) ?></div><?php endif; ?>
                        </div>
                        <div class="bp-route-mid">
                            <div class="bp-route-track"><span class="bp-route-plane">✈</span></div>
                            <div class="bp-dur"><?= htmlspecialchars($booking['duration']) ?></div>
                        </div>
                        <div class="bp-city-block">
                            <div class="bp-iata"><?= strtoupper(substr($booking['to_location'], 0, 3)) ?></div>
                            <div class="bp-city-name"><?= htmlspecialchars($booking['to_location']) ?></div>
                            <?php if ($arr_t): ?><div style="font-size:0.85rem;font-weight:800;color:var(--primary);margin-top:3px;"><?= htmlspecialchars($arr_t) ?></div><?php endif; ?>
                            <?php if ($arr_day): ?><div style="font-size:0.7rem;color:var(--muted);"><?= htmlspecialchars($arr_day) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tear line -->
                <div class="bp-tear">
                    <div class="bp-tear-line"></div>
                    <span class="bp-tear-icon">✂</span>
                    <div class="bp-tear-line"></div>
                </div>

                <!-- Details -->
                <div class="bp-details" style="display: flex; gap: 30px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; padding: 24px 30px;">
                    <div style="flex: 1; min-width: 280px;">
                        <div class="bp-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 22px;">
                            <div class="bp-field">
                                <div class="lbl">Passenger</div>
                                <div class="val"><?= htmlspecialchars($booking['passenger_name']) ?></div>
                            </div>
                            <div class="bp-field">
                                <div class="lbl">Depart Date</div>
                                <div class="val"><?= date('d M Y', strtotime($booking['depart_date'])) ?></div>
                            </div>
                            <?php if ($dep_t): ?>
                            <div class="bp-field">
                                <div class="lbl">Departure Time</div>
                                <div class="val"><?= htmlspecialchars($dep_t) ?><?= $dep_day ? ' · ' . htmlspecialchars($dep_day) : '' ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($arr_t): ?>
                            <div class="bp-field">
                                <div class="lbl">Arrival Time</div>
                                <div class="val"><?= htmlspecialchars($arr_t) ?><?= $arr_day ? ' · ' . htmlspecialchars($arr_day) : '' ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="bp-field">
                                <div class="lbl">Booked On</div>
                                <div class="val"><?= date('d M Y', strtotime($booking['booking_date'])) ?></div>
                            </div>
                            <div class="bp-field">
                                <div class="lbl">Adults</div>
                                <div class="val"><?= $booking['adults'] ?></div>
                            </div>
                            <div class="bp-field">
                                <div class="lbl">Children</div>
                                <div class="val"><?= $booking['children'] ?: '—' ?></div>
                            </div>
                            <div class="bp-field">
                                <div class="lbl">Trip Type</div>
                                <div class="val"><?= ucfirst($booking['trip_type']) ?></div>
                            </div>
                        </div>

                        <div class="bp-price-row">
                            <div>
                                <div class="bp-total-lbl">Total Paid</div>
                                <div class="bp-total-amt">$<?= number_format($booking['total_price'], 0) ?></div>
                            </div>
                            <span class="bp-status status-<?= htmlspecialchars($booking['status']) ?>">
                                <?= $booking['status']==='confirmed' ? '✔' : '✖' ?> <?= ucfirst($booking['status']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Beautiful interactive QR Code on the right -->
                    <?php
                    $qr_data = "GoZayan E-Ticket\n"
                             . "Ref: #" . str_pad($booking['id'], 6, '0', STR_PAD_LEFT) . "\n"
                             . "Passenger: " . $booking['passenger_name'] . "\n"
                             . "Flight: " . $booking['flight_code'] . " (" . $booking['airline_name'] . ")\n"
                             . "Route: " . $booking['from_location'] . " -> " . $booking['to_location'] . "\n"
                             . "Date: " . date('d M Y', strtotime($booking['depart_date'])) . "\n"
                             . "Class: " . $booking['class'] . "\n"
                             . "Status: " . ucfirst($booking['status']);
                    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=" . urlencode($qr_data);
                    ?>
                    <div style="background: #f8fbff; border: 1.5px solid #dce8f5; border-radius: 16px; padding: 18px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 180px; box-shadow: 0 4px 12px rgba(13,31,53,0.03); margin-top: 10px;">
                        <img src="<?= $qr_url ?>" alt="QR Code" style="width: 130px; height: 130px; border-radius: 8px; border: 1px solid #e2e8f0; background:#fff; padding: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                        <span style="font-size: 0.65rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px; margin-top: 10px; display: block;">Scan E-Ticket</span>
                        <span style="font-size: 0.72rem; font-weight: 800; color: var(--primary); font-family: monospace; margin-top: 2px; display: block;">#<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></span>
                    </div>
                </div>
            </div><!-- /bp -->

            <!-- RIGHT: Actions + Payment -->
            <div class="right-col">

                <!-- Actions -->
                <div class="panel">
                    <div class="panel-head">⚡ Actions</div>
                    <div class="panel-body">
                        <button onclick="doPrint()" class="action-btn btn-blue">🖨️ Print Ticket</button>
                        <a href="myBookings.php"    class="action-btn btn-ghost">📋 My Bookings</a>
                        <a href="searchflights.php" class="action-btn btn-ghost">🔍 Search More Flights</a>
                    </div>
                </div>

                <!-- Payment info -->
                <div class="panel">
                    <div class="panel-head">💳 Payment Information</div>
                    <div class="panel-body">
                        <div class="info-row">
                            <span class="k">Method</span>
                            <span class="v"><?= ucfirst($booking['payment_method'] ?? '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="k">Card</span>
                            <span class="v">•••• <?= htmlspecialchars($booking['card_last4'] ?? '——') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="k">Card Holder</span>
                            <span class="v"><?= htmlspecialchars($booking['card_holder'] ?? '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="k">Amount</span>
                            <span class="v" style="color:var(--primary)">$<?= number_format($booking['total_price'], 0) ?></span>
                        </div>
                    </div>
                </div>

            </div><!-- /right-col -->

        </div><!-- /page-body -->
    </div><!-- /main -->
</div><!-- /dashboard -->

<!-- ══ PRINT-ONLY BOARDING PASS ══ -->
<?php
// Generate a pseudo-barcode seed from booking id
$bc_seed = str_pad($booking['id'], 10, '0', STR_PAD_LEFT) . strtoupper(substr(md5($booking['id'].$booking['passenger_email']), 0, 12));
?>
<div class="print-ticket" id="printOnlyTicket" style="display:none;">

    <!-- MAIN SECTION -->
    <div class="pt-main">
        <div class="pt-head">
            <div>
                <div class="pt-airline">✈ <?= htmlspecialchars($booking['airline_name']) ?></div>
                <div class="pt-flight-sub"><?= htmlspecialchars($booking['flight_code']) ?> &nbsp;·&nbsp; <?= ucfirst($booking['trip_type']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($booking['class']) ?></div>
            </div>
            <div class="pt-booking-ref">#<?= str_pad($booking['id'],6,'0',STR_PAD_LEFT) ?></div>
        </div>

        <div class="pt-route">
            <div class="pt-city">
                <div class="pt-iata"><?= strtoupper(substr($booking['from_location'],0,3)) ?></div>
                <div class="pt-city-lbl"><?= htmlspecialchars($booking['from_location']) ?></div>
                <?php if ($dep_t): ?><div style="font-size:0.9rem;font-weight:800;color:#1a6ff4;margin-top:3px;"><?= htmlspecialchars($dep_t) ?></div><?php endif; ?>
                <?php if ($dep_day): ?><div style="font-size:0.7rem;color:#7a95b0;"><?= htmlspecialchars($dep_day) ?></div><?php endif; ?>
            </div>
            <div class="pt-route-mid">
                <div class="pt-line-wrap"><span class="pt-plane-icon">✈</span></div>
                <div class="pt-dur"><?= htmlspecialchars($booking['duration']) ?></div>
            </div>
            <div class="pt-city">
                <div class="pt-iata"><?= strtoupper(substr($booking['to_location'],0,3)) ?></div>
                <div class="pt-city-lbl"><?= htmlspecialchars($booking['to_location']) ?></div>
                <?php if ($arr_t): ?><div style="font-size:0.9rem;font-weight:800;color:#1a6ff4;margin-top:3px;"><?= htmlspecialchars($arr_t) ?></div><?php endif; ?>
                <?php if ($arr_day): ?><div style="font-size:0.7rem;color:#7a95b0;"><?= htmlspecialchars($arr_day) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="pt-body">
            <div class="pt-fields">
                <div>
                    <div class="pt-f-lbl">Passenger</div>
                    <div class="pt-f-val"><?= htmlspecialchars($booking['passenger_name']) ?></div>
                </div>
                <div>
                    <div class="pt-f-lbl">Depart Date</div>
                    <div class="pt-f-val"><?= date('d M Y', strtotime($booking['depart_date'])) ?></div>
                </div>
                <?php if ($dep_t): ?>
                <div>
                    <div class="pt-f-lbl">Departure Time</div>
                    <div class="pt-f-val"><?= htmlspecialchars($dep_t) ?><?= $dep_day ? ' · ' . htmlspecialchars($dep_day) : '' ?></div>
                </div>
                <?php endif; ?>
                <?php if ($arr_t): ?>
                <div>
                    <div class="pt-f-lbl">Arrival Time</div>
                    <div class="pt-f-val"><?= htmlspecialchars($arr_t) ?><?= $arr_day ? ' · ' . htmlspecialchars($arr_day) : '' ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div class="pt-f-lbl">Flight</div>
                    <div class="pt-f-val"><?= htmlspecialchars($booking['flight_name']) ?></div>
                </div>
                <div>
                    <div class="pt-f-lbl">Adults</div>
                    <div class="pt-f-val"><?= $booking['adults'] ?></div>
                </div>
                <div>
                    <div class="pt-f-lbl">Children</div>
                    <div class="pt-f-val"><?= $booking['children'] ?: '—' ?></div>
                </div>
                <div>
                    <div class="pt-f-lbl">Booked On</div>
                    <div class="pt-f-val"><?= date('d M Y', strtotime($booking['booking_date'])) ?></div>
                </div>
            </div>

            <div class="pt-footer">
                <div>
                    <div class="pt-price-lbl">Total Paid</div>
                    <div class="pt-price-amt">৳<?= number_format($booking['total_price'],0) ?></div>
                </div>
                <span class="pt-status-badge">✔ <?= ucfirst($booking['status']) ?></span>
            </div>
        </div>
    </div>

    <!-- STUB SECTION -->
    <div class="pt-stub">
        <div class="pt-stub-head">
            <div class="pt-stub-logo">Go<span>Zayan</span></div>
            <div class="pt-stub-tagline">BOARDING PASS</div>
        </div>
        <div class="pt-stub-route">
            <div class="pt-stub-iata"><?= strtoupper(substr($booking['from_location'],0,3)) ?></div>
            <div class="pt-stub-arrow">↓</div>
            <div class="pt-stub-iata"><?= strtoupper(substr($booking['to_location'],0,3)) ?></div>
        </div>
        <div class="pt-stub-body">
            <div class="pt-stub-field">
                <div class="sl">Passenger</div>
                <div class="sv"><?= htmlspecialchars(explode(' ',$booking['passenger_name'])[0]) ?></div>
            </div>
            <div class="pt-stub-field">
                <div class="sl">Date</div>
                <div class="sv"><?= date('d M Y', strtotime($booking['depart_date'])) ?></div>
            </div>
            <div class="pt-stub-field">
                <div class="sl">Class</div>
                <div class="sv"><?= htmlspecialchars($booking['class']) ?></div>
            </div>
            <div class="pt-stub-field">
                <div class="sl">Flight</div>
                <div class="sv"><?= htmlspecialchars($booking['flight_code']) ?></div>
            </div>
        </div>
        <div class="pt-barcode" style="padding: 4mm 5mm 5mm; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;">
            <img src="<?= $qr_url ?>" alt="QR Code" style="width: 24mm; height: 24mm; border: 1px solid #d0d8e8; background: #fff; padding: 1mm; border-radius: 2mm; margin-bottom: 2mm;">
            <div class="pt-barcode-bars" id="barcodeEl"></div>
            <div class="pt-barcode-num"><?= $bc_seed ?></div>
        </div>
    </div>

</div><!-- /print-ticket -->

<script>
// Print function — shows the ticket, prints, then hides it
function doPrint() {
    const ticket = document.getElementById('printOnlyTicket');
    ticket.style.display = 'flex';

    // Small delay so the browser renders it before printing
    setTimeout(function() {
        window.print();
    }, 150);
}

// Hide the print ticket after printing — multiple fallbacks
window.onafterprint = function() {
    document.getElementById('printOnlyTicket').style.display = 'none';
};
// Fallback: hide when window regains focus (covers "Save as PDF" dialog close)
window.addEventListener('focus', function() {
    document.getElementById('printOnlyTicket').style.display = 'none';
});
// Safety: always hidden on load
window.addEventListener('load', function() {
    document.getElementById('printOnlyTicket').style.display = 'none';
});

// Generate barcode bars
(function(){
    const el = document.getElementById('barcodeEl');
    if (!el) return;
    const seed = '<?= $bc_seed ?>';
    const widths  = [1,1,2,1,3,1,2,1,1,2,3,1,1,2,1,1,3,2,1,1,2,1,3,1,1,2,1,2,1,1,3,1,2,1,1,2,1,1,2,3];
    const heights = [14,10,14,8,14,10,12,14,8,14,10,14,12,8,14,10,14,8,12,14,10,14,8,14,12,10,14,8,14,10,12,14,8,14,10,12,14,8,14,10];
    for (let i = 0; i < 40; i++) {
        const bar = document.createElement('span');
        const w = widths[i % widths.length];
        const h = heights[i % heights.length];
        bar.style.cssText = `width:${w}px;height:${h}mm;background:#0d1f35;border-radius:1px;display:inline-block;`;
        el.appendChild(bar);
    }
})();
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
