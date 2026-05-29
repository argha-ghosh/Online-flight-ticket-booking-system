<?php
session_start();
include("../model/db_conn.php");

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:      #1a6ff4;
            --primary-dark: #0d4fc4;
            --primary-glow: rgba(26,111,244,0.18);
            --secondary:    #0a2d6e;
            --accent:       #06c8a0;
            --warn:         #f59e0b;
            --danger:       #ef4444;
            --dark:         #0d1f35;
            --mid:          #3d5a7a;
            --muted:        #7a95b0;
            --border:       #dce8f5;
            --surface:      #ffffff;
            --bg:           #f0f4fb;
            --sidebar-w:    260px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        /* ══ LAYOUT ══ */
        .dashboard { display: flex; flex: 1; }

        /* ══ SIDEBAR — sticky within dashboard only ══ */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--secondary) 0%, #0d1f35 100%);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            font-size: 1.4rem;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.5px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-decoration: none;
        }
        .sidebar-brand a { text-decoration: none; color: inherit; }
        .sidebar-brand span { color: #60a5fa; }
        .sidebar-profile {
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .profile-avatar {
            width: 72px; height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.2);
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        }
        .profile-avatar-placeholder {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            border: 3px solid rgba(255,255,255,0.2);
        }
        .profile-name  { font-size: 0.95rem; font-weight: 700; color: #fff; text-align: center; }
        .profile-email { font-size: 0.75rem; color: rgba(255,255,255,0.45); text-align: center; word-break: break-all; }
        .sidebar-nav { padding: 16px 12px; flex: 1; }
        .nav-label {
            font-size: 0.65rem; font-weight: 700; color: rgba(255,255,255,0.3);
            text-transform: uppercase; letter-spacing: 1.2px;
            padding: 0 12px; margin: 16px 0 8px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; border-radius: 10px;
            text-decoration: none; color: rgba(255,255,255,0.65);
            font-size: 0.88rem; font-weight: 500;
            transition: all 0.2s; margin-bottom: 2px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: rgba(26,111,244,0.25); color: #fff; font-weight: 600; }
        .nav-item .nav-icon { font-size: 1.1rem; width: 22px; text-align: center; }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .logout-btn {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; border-radius: 10px;
            text-decoration: none; color: rgba(255,100,100,0.8);
            font-size: 0.88rem; font-weight: 600;
            transition: all 0.2s; width: 100%;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.12); color: #fca5a5; }

        /* ══ MAIN — offset by sidebar width ══ */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* Topbar */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 32px;
            display: flex; align-items: center;
            justify-content: space-between; gap: 16px;
            position: sticky; top: 0; z-index: 10;
        }
        .topbar-greeting { font-size: 1rem; font-weight: 700; color: var(--dark); }
        .topbar-greeting span { color: var(--primary); }
        .topbar-search {
            display: flex; align-items: center; gap: 10px;
            background: var(--bg); border: 1.5px solid var(--border);
            border-radius: 50px; padding: 9px 20px;
            flex: 1; max-width: 340px;
            text-decoration: none; color: var(--muted);
            font-size: 0.88rem; font-weight: 500; transition: all 0.2s;
        }
        .topbar-search:hover { border-color: var(--primary); color: var(--primary); background: rgba(26,111,244,0.04); }

        /* Page content */
        .page-content { padding: 32px; flex: 1; }

        /* ══ STAT STRIP ══ */
        .stat-strip {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 18px; margin-bottom: 32px;
        }
        .stat-tile {
            background: var(--surface); border-radius: 16px;
            padding: 22px 24px; border: 1px solid var(--border);
            display: flex; align-items: center; gap: 18px;
            box-shadow: 0 2px 12px rgba(13,31,53,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(13,31,53,0.09); }
        .stat-tile-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .icon-blue  { background: rgba(26,111,244,0.1); }
        .icon-teal  { background: rgba(6,200,160,0.1); }
        .icon-amber { background: rgba(245,158,11,0.1); }
        .stat-tile-info .val {
            font-size: 1.8rem; font-weight: 900; color: var(--dark);
            letter-spacing: -1px; line-height: 1;
        }
        .stat-tile-info .lbl {
            font-size: 0.78rem; color: var(--muted); font-weight: 600;
            margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px;
        }

        /* ══ TWO-COLUMN BODY ══ */
        .body-grid {
            display: grid; grid-template-columns: 1fr 320px;
            gap: 24px; align-items: start;
        }

        /* ══ PANELS ══ */
        .panel {
            background: var(--surface); border-radius: 18px;
            border: 1px solid var(--border); overflow: hidden;
            box-shadow: 0 2px 12px rgba(13,31,53,0.05);
        }
        .panel-head {
            padding: 18px 22px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .panel-head h3 { font-size: 0.95rem; font-weight: 800; color: var(--dark); }
        .panel-head a { font-size: 0.8rem; font-weight: 700; color: var(--primary); text-decoration: none; }
        .panel-head a:hover { text-decoration: underline; }

        /* Ticket rows */
        .booking-ticket {
            display: flex; align-items: center; gap: 16px;
            padding: 16px 22px; border-bottom: 1px solid #f0f5fb;
            transition: background 0.15s;
        }
        .booking-ticket:last-child { border-bottom: none; }
        .booking-ticket:hover { background: #f8fbff; }
        .ticket-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
            box-shadow: 0 3px 10px var(--primary-glow);
        }
        .ticket-info { flex: 1; min-width: 0; }
        .ticket-route {
            font-size: 0.92rem; font-weight: 700; color: var(--dark);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ticket-meta { font-size: 0.76rem; color: var(--muted); margin-top: 3px; }
        .ticket-right { text-align: right; flex-shrink: 0; }
        .ticket-price { font-size: 0.95rem; font-weight: 800; color: var(--primary); }
        .ticket-date  { font-size: 0.74rem; color: var(--muted); margin-top: 3px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
        .badge-confirmed { background: rgba(6,200,160,0.12); color: #047857; border: 1px solid rgba(6,200,160,0.25); }
        .badge-cancelled { background: rgba(239,68,68,0.08); color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
        .badge-pending   { background: rgba(245,158,11,0.1); color: #b45309; border: 1px solid rgba(245,158,11,0.25); }
        .no-data { text-align: center; padding: 40px 20px; color: var(--muted); font-size: 0.88rem; }

        /* Quick links */
        .quick-links { display: flex; flex-direction: column; gap: 10px; padding: 16px; }
        .quick-link {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px; border-radius: 12px;
            text-decoration: none; color: var(--dark);
            border: 1px solid var(--border); background: var(--surface);
            transition: all 0.2s; font-size: 0.88rem; font-weight: 600;
        }
        .quick-link:hover { border-color: var(--primary); background: rgba(26,111,244,0.04); transform: translateX(4px); }
        .quick-link .ql-icon {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .quick-link .ql-arrow { margin-left: auto; color: var(--muted); font-size: 0.8rem; }
        .quick-link:hover .ql-arrow { color: var(--primary); }

        /* Search CTA */
        .search-cta {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            border-radius: 18px; padding: 28px 22px; color: #fff;
            text-align: center; margin-bottom: 18px;
            position: relative; overflow: hidden;
        }
        .search-cta::before { content: '✈'; position: absolute; right: -10px; top: -10px; font-size: 6rem; opacity: 0.07; }
        .search-cta h4 { font-size: 1.05rem; font-weight: 800; margin-bottom: 8px; letter-spacing: -0.3px; }
        .search-cta p  { font-size: 0.8rem; opacity: 0.75; margin-bottom: 18px; line-height: 1.5; }
        .search-cta a {
            display: inline-block; background: #fff; color: var(--primary);
            padding: 10px 24px; border-radius: 50px; font-weight: 700;
            font-size: 0.88rem; text-decoration: none;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15); transition: all 0.2s;
        }
        .search-cta a:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }

        /* ══ GUEST LAYOUT ══ */
        .guest-page { min-height: 100vh; display: flex; flex-direction: column; }
        .guest-split { flex: 1; display: grid; grid-template-columns: 1fr 1fr; }
        .guest-left {
            background: linear-gradient(160deg, var(--secondary) 0%, var(--primary) 50%, var(--accent) 100%);
            display: flex; flex-direction: column; justify-content: center;
            padding: 60px 56px; color: #fff; position: relative; overflow: hidden;
        }
        .guest-left::before {
            content: '';
            position: absolute; inset: 0;
            background: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?q=80&w=1748&auto=format&fit=crop') center/cover;
            opacity: 0.15;
        }
        .guest-left-content { position: relative; z-index: 1; }
        .guest-left h1 { font-size: 2.8rem; font-weight: 900; letter-spacing: -1.5px; line-height: 1.1; margin-bottom: 18px; }
        .guest-left p  { font-size: 1rem; opacity: 0.8; line-height: 1.65; max-width: 380px; }
        .guest-right {
            display: flex; align-items: center; justify-content: center;
            padding: 60px 56px; background: var(--surface);
        }
        .guest-right-inner { max-width: 360px; width: 100%; }
        .guest-right h2 { font-size: 1.7rem; font-weight: 800; color: var(--dark); margin-bottom: 10px; letter-spacing: -0.5px; }
        .guest-right p  { color: var(--muted); font-size: 0.9rem; margin-bottom: 32px; line-height: 1.6; }
        .btn-solid {
            display: block; width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; border-radius: 12px; text-decoration: none;
            font-weight: 700; font-size: 0.95rem; text-align: center;
            box-shadow: 0 5px 18px var(--primary-glow); transition: all 0.22s; margin-bottom: 12px;
        }
        .btn-solid:hover { transform: translateY(-2px); filter: brightness(1.06); }
        .btn-ghost {
            display: block; width: 100%; padding: 13px;
            border: 2px solid var(--border); color: var(--mid);
            border-radius: 12px; text-decoration: none;
            font-weight: 700; font-size: 0.95rem; text-align: center; transition: all 0.22s;
        }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); background: rgba(26,111,244,0.04); }
        .divider {
            display: flex; align-items: center; gap: 12px;
            color: var(--muted); font-size: 0.8rem; margin: 14px 0;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* ══ RESPONSIVE ══ */
        @media (max-width: 1024px) { .body-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .stat-strip { grid-template-columns: 1fr 1fr; }
            .page-content { padding: 20px 16px; }
            .topbar { padding: 14px 16px; }
            .guest-split { grid-template-columns: 1fr; }
            .guest-left { display: none; }
            .guest-right { padding: 40px 24px; }
        }
        @media (max-width: 480px) { .stat-strip { grid-template-columns: 1fr; } }

        /* ══ FOOTER STYLING ══ */
        /* footer {
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
        } */
    </style>
</head>
<body>
<!-- <?php include("../includes/header.php"); ?> -->

<?php if ($is_logged_in && $user): ?>

<div class="dashboard">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
        <div class="sidebar-brand"><a href="home.php">Go<span>Zayan</span></a></div>

        <div class="sidebar-profile">
            <?php if (!empty($user['image'])): ?>
                <img class="profile-avatar" src="uploads/<?= htmlspecialchars($user['image']) ?>" alt="Avatar">
            <?php else: ?>
                <div class="profile-avatar-placeholder">✈</div>
            <?php endif; ?>
            <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="userhome.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="searchflights.php" class="nav-item"><span class="nav-icon">🔍</span> Search Flights</a>
            <a href="myBookings.php" class="nav-item"><span class="nav-icon">🎫</span> My Bookings</a>
            <div class="nav-label">Account</div>
            <a href="passengerProfile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
            <a href="changePassword.php" class="nav-item"><span class="nav-icon">🔒</span> Change Password</a>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <!-- ══ MAIN ══ -->
    <div class="main">

        <div class="topbar">
            <div class="topbar-greeting">Good day, <span><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span> ✈️</div>
            <a href="searchflights.php" class="topbar-search">🔍 &nbsp;Search for a flight...</a>
        </div>

        <div class="page-content">

            <!-- Stat strip -->
            <div class="stat-strip">
                <div class="stat-tile">
                    <div class="stat-tile-icon icon-blue">🎫</div>
                    <div class="stat-tile-info">
                        <div class="val"><?= $booking_count ?></div>
                        <div class="lbl">Total Bookings</div>
                    </div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile-icon icon-teal">✈️</div>
                    <div class="stat-tile-info">
                        <div class="val">BD</div>
                        <div class="lbl">GoZayan Traveller</div>
                    </div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile-icon icon-amber">🌏</div>
                    <div class="stat-tile-info">
                        <div class="val">∞</div>
                        <div class="lbl">Destinations</div>
                    </div>
                </div>
            </div>

            <!-- Body grid -->
            <div class="body-grid">

                <!-- Recent bookings -->
                <div class="panel">
                    <div class="panel-head">
                        <h3>Recent Bookings</h3>
                        <a href="myBookings.php">View all →</a>
                    </div>
                    <?php if ($booking_count > 0):
                        $recent_stmt = $conn->prepare("
                            SELECT b.*, f.flight_name, f.departure, f.arrival, f.flight_code
                            FROM bookings b JOIN flights f ON b.flight_id = f.id
                            WHERE b.user_id = ? ORDER BY b.booking_date DESC LIMIT 6
                        ");
                        $recent_stmt->bind_param("i", $user['id']);
                        $recent_stmt->execute();
                        $recent_bookings = $recent_stmt->get_result();
                        while ($b = $recent_bookings->fetch_assoc()):
                    ?>
                    <div class="booking-ticket">
                        <div class="ticket-icon">✈</div>
                        <div class="ticket-info">
                            <div class="ticket-route"><?= htmlspecialchars($b['departure']) ?> → <?= htmlspecialchars($b['arrival']) ?></div>
                            <div class="ticket-meta">
                                <?= htmlspecialchars($b['flight_name']) ?> · <?= htmlspecialchars($b['flight_code']) ?>
                                &nbsp;<span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= ucfirst($b['status']) ?></span>
                            </div>
                        </div>
                        <div class="ticket-right">
                            <div class="ticket-price">$<?= number_format($b['total_price'], 0) ?></div>
                            <div class="ticket-date"><?= date('d M Y', strtotime($b['booking_date'])) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <div class="no-data">
                        🎫 No bookings yet.<br>
                        <a href="searchflights.php" style="color:var(--primary);font-weight:700;text-decoration:none;margin-top:8px;display:inline-block;">Search flights →</a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right column -->
                <div>
                    <div class="search-cta">
                        <h4>Ready for your next trip?</h4>
                        <p>Find the best deals on flights across Bangladesh and beyond.</p>
                        <a href="searchflights.php">Search Flights</a>
                    </div>
                    <div class="panel">
                        <div class="panel-head"><h3>Quick Links</h3></div>
                        <div class="quick-links">
                            <a href="searchflights.php" class="quick-link">
                                <div class="ql-icon icon-blue">🔍</div> Search Flights <span class="ql-arrow">›</span>
                            </a>
                            <a href="myBookings.php" class="quick-link">
                                <div class="ql-icon icon-teal">🎫</div> My Bookings <span class="ql-arrow">›</span>
                            </a>
                            <a href="passengerProfile.php" class="quick-link">
                                <div class="ql-icon icon-amber">👤</div> Edit Profile <span class="ql-arrow">›</span>
                            </a>
                            <a href="changePassword.php" class="quick-link">
                                <div class="ql-icon" style="background:rgba(239,68,68,0.1);">🔒</div> Change Password <span class="ql-arrow">›</span>
                            </a>
                        </div>
                    </div>
                </div>

            </div><!-- /body-grid -->
        </div><!-- /page-content -->
    </div><!-- /main -->
</div><!-- /dashboard -->

<?php else: ?>

<!-- ══ GUEST: SPLIT SCREEN ══ -->
<div class="guest-page">
    <div class="guest-split">
        <div class="guest-left">
            <div class="guest-left-content">
                <h1>Think Flights,<br>Think GoZayan</h1>
                <p>Search and book flights across Bangladesh. Fast, simple, and reliable — your journey starts here.</p>
            </div>
        </div>
        <div class="guest-right">
            <div class="guest-right-inner">
                <h2>Get Started</h2>
                <p>Login to your account or create a new one to start booking flights.</p>
                <a href="login.php" class="btn-solid">Login to your account</a>
                <div class="divider">or</div>
                <a href="register.php" class="btn-ghost">Create a free account</a>
                <p style="margin-top:20px;text-align:center;">
                    <a href="searchflights.php" style="color:var(--primary);font-weight:600;font-size:0.88rem;text-decoration:none;">
                        Browse flights without logging in →
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
<?php include("../includes/footer.php"); ?>

</body>
</html>