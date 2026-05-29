<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}

$email = $_SESSION['email'];

$u_stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
$u_stmt->bind_param("s", $email);
$u_stmt->execute();
$user = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

// Handle cancel
if (isset($_POST['cancel_id'])) {
    $cancel_id = (int)$_POST['cancel_id'];
    $c = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $c->bind_param("ii", $cancel_id, $user['id']);
    $c->execute();
    $c->close();
}

// Filter
$status_filter = $_GET['status'] ?? 'all';
$where = "WHERE b.user_id = {$user['id']}";
if ($status_filter === 'confirmed') $where .= " AND b.status = 'confirmed'";
if ($status_filter === 'cancelled')  $where .= " AND b.status = 'cancelled'";

$bookings_result = $conn->query("
    SELECT b.*,
           f.flight_name, f.airline_name, f.flight_code,
           f.departure, f.arrival, f.duration, f.image as flight_image,
           f.departure_time, f.arrival_time,
           f.status as flight_status,
           ROUND(f.price * (1 - f.discount_pct / 100), 2) AS current_unit_price,
           f.discount_pct,
           s.departure_day, s.arrival_day,
           s.departure_time AS sched_dep_time,
           s.arrival_time   AS sched_arr_time
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    LEFT JOIN schedule s ON s.flight_code COLLATE utf8mb4_unicode_ci = f.flight_code
    $where
    ORDER BY b.booking_date DESC
");

// Stats
$stats_q = $conn->query("SELECT status, COUNT(*) as cnt FROM bookings WHERE user_id = {$user['id']} GROUP BY status");
$total = 0; $confirmed = 0; $cancelled = 0;
while ($s = $stats_q->fetch_assoc()) {
    $total += $s['cnt'];
    if ($s['status'] === 'confirmed') $confirmed = $s['cnt'];
    if ($s['status'] === 'cancelled') $cancelled = $s['cnt'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | My Bookings</title>
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
            -webkit-font-smoothing: antialiased;
        }

        /* ══ LAYOUT ══ */
        .dashboard { display: flex; min-height: 100vh; }

        /* ══ SIDEBAR ══ */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--secondary) 0%, #0d1f35 100%);
            display: flex; flex-direction: column; flex-shrink: 0;
            position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            font-size: 1.4rem; font-weight: 900; color: #fff; letter-spacing: -0.5px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-brand a { text-decoration: none; color: inherit; }
        .sidebar-brand span { color: #60a5fa; }
        .sidebar-profile {
            padding: 22px 20px;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .profile-avatar {
            width: 64px; height: 64px; border-radius: 50%; object-fit: cover;
            border: 3px solid rgba(255,255,255,0.2);
        }
        .profile-avatar-placeholder {
            width: 64px; height: 64px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
            border: 3px solid rgba(255,255,255,0.2);
        }
        .profile-name { font-size: 0.9rem; font-weight: 700; color: #fff; text-align: center; }
        .profile-email { font-size: 0.72rem; color: rgba(255,255,255,0.4); text-align: center; word-break: break-all; }
        .sidebar-nav { padding: 16px 12px; flex: 1; }
        .nav-label {
            font-size: 0.65rem; font-weight: 700; color: rgba(255,255,255,0.3);
            text-transform: uppercase; letter-spacing: 1.2px; padding: 0 12px; margin: 14px 0 6px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; border-radius: 10px; text-decoration: none;
            color: rgba(255,255,255,0.6); font-size: 0.88rem; font-weight: 500;
            transition: all 0.2s; margin-bottom: 2px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: rgba(26,111,244,0.25); color: #fff; font-weight: 600; }
        .nav-icon { font-size: 1.1rem; width: 22px; text-align: center; }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .logout-btn {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; border-radius: 10px; text-decoration: none;
            color: rgba(255,100,100,0.8); font-size: 0.88rem; font-weight: 600;
            transition: all 0.2s; width: 100%;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.12); color: #fca5a5; }

        /* ══ MAIN ══ */
        .main { flex: 1; display: flex; flex-direction: column; min-width: 0; }

        /* Topbar */
        .topbar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 16px 32px; display: flex; align-items: center;
            justify-content: space-between; gap: 16px;
            position: sticky; top: 0; z-index: 10;
        }
        .topbar-title { font-size: 1rem; font-weight: 800; color: var(--dark); }
        .topbar-back {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.85rem; font-weight: 600; color: var(--muted);
            text-decoration: none; transition: color 0.2s;
        }
        .topbar-back:hover { color: var(--primary); }

        /* ══ STATS STRIP ══ */
        .stats-strip {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 0; border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .stat-block {
            padding: 22px 28px; text-align: center;
            border-right: 1px solid var(--border);
            transition: background 0.2s;
        }
        .stat-block:last-child { border-right: none; }
        .stat-block:hover { background: #f8fbff; }
        .stat-block .num {
            font-size: 2rem; font-weight: 900; letter-spacing: -1px; line-height: 1;
        }
        .num-total   { color: var(--dark); }
        .num-confirm { color: var(--accent); }
        .num-cancel  { color: #ef4444; }
        .stat-block .lbl {
            font-size: 0.75rem; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.5px; margin-top: 5px;
        }

        /* ══ FILTER BAR ══ */
        .filter-bar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 14px 32px; display: flex; align-items: center;
            justify-content: space-between; gap: 16px; flex-wrap: wrap;
        }
        .filter-tabs { display: flex; gap: 8px; }
        .ftab {
            padding: 7px 18px; border-radius: 50px; font-size: 0.82rem;
            font-weight: 600; text-decoration: none; transition: all 0.2s;
            border: 1.5px solid var(--border); color: var(--muted);
            background: var(--surface);
        }
        .ftab:hover { border-color: var(--primary); color: var(--primary); }
        .ftab.active {
            background: var(--primary); color: #fff;
            border-color: var(--primary);
            box-shadow: 0 3px 12px var(--primary-glow);
        }
        .result-count { font-size: 0.82rem; color: var(--muted); font-weight: 500; }

        /* ══ BOOKING LIST ══ */
        .bookings-area { padding: 28px 32px 60px; }

        /* Boarding-pass card */
        .bp-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            margin-bottom: 20px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 2px 14px rgba(13,31,53,0.06);
            transition: box-shadow 0.25s, transform 0.25s;
            position: relative;
        }
        .bp-card:hover {
            box-shadow: 0 8px 32px rgba(26,111,244,0.12);
            transform: translateY(-2px);
        }

        /* Status left stripe */
        .bp-stripe {
            width: 5px; flex-shrink: 0;
        }
        .stripe-confirmed { background: linear-gradient(180deg, var(--accent), #059669); }
        .stripe-cancelled { background: linear-gradient(180deg, #ef4444, #dc2626); }
        .stripe-pending   { background: linear-gradient(180deg, #f59e0b, #d97706); }

        /* Flight image */
        .bp-img {
            width: 110px; flex-shrink: 0;
            background: linear-gradient(135deg, #e8f0fe, #dbeafe);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; color: var(--primary);
            border-right: 1px solid var(--border);
        }
        .bp-img img { width: 100%; height: 100%; object-fit: cover; }

        /* Main body */
        .bp-body {
            flex: 1; padding: 20px 24px;
            display: flex; gap: 0; align-items: stretch;
            min-width: 0;
        }

        /* Left: flight info */
        .bp-flight { flex: 1; min-width: 0; padding-right: 24px; }
        .bp-booking-ref {
            font-size: 0.7rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;
            display: flex; align-items: center; gap: 8px;
        }
        .bp-booking-ref b { color: var(--primary); font-size: 0.82rem; }

        /* Route display */
        .bp-route {
            display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
        }
        .bp-city { font-size: 1.6rem; font-weight: 900; color: var(--dark); letter-spacing: -1px; }
        .bp-route-mid {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; gap: 4px;
        }
        .bp-route-line {
            width: 100%; display: flex; align-items: center; gap: 0;
        }
        .bp-route-line::before, .bp-route-line::after {
            content: ''; flex: 1; height: 1.5px;
            background: linear-gradient(90deg, var(--border), var(--primary));
        }
        .bp-route-line::after { background: linear-gradient(90deg, var(--primary), var(--border)); }
        .bp-plane { color: var(--primary); font-size: 1rem; }
        .bp-duration {
            font-size: 0.7rem; color: var(--muted); font-weight: 600;
            background: #f0f5ff; padding: 2px 8px; border-radius: 10px;
            border: 1px solid var(--border);
        }

        .bp-flight-name {
            font-size: 0.82rem; font-weight: 700; color: var(--mid); margin-bottom: 10px;
        }
        .bp-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .bp-tag {
            font-size: 0.72rem; padding: 3px 10px; border-radius: 20px;
            background: #f0f5ff; color: var(--mid); border: 1px solid var(--border);
        }
        /* Live schedule times row */
        .bp-times {
            display: flex; align-items: center; gap: 10px;
            background: #f0f6ff; border: 1px solid rgba(26,111,244,0.15);
            border-radius: 9px; padding: 6px 12px;
            margin-bottom: 10px; width: fit-content;
        }
        .bp-time-block {
            display: flex; flex-direction: column; align-items: center; gap: 1px;
        }
        .bp-time-block b { font-size: 0.95rem; font-weight: 800; color: var(--primary); }
        .bp-time-block span { font-size: 0.65rem; color: var(--mid); font-weight: 600; text-transform: uppercase; }
        .bp-time-block small { font-size: 0.6rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .bp-time-sep { color: var(--primary); font-weight: 700; font-size: 0.9rem; }
            font-weight: 500;
        }

        /* Divider notch */
        .bp-notch {
            width: 1px; background: repeating-linear-gradient(
                to bottom, var(--border) 0px, var(--border) 6px, transparent 6px, transparent 12px
            );
            margin: 0 4px; flex-shrink: 0; align-self: stretch;
        }

        /* Right: price + actions */
        .bp-right {
            padding-left: 24px; display: flex; flex-direction: column;
            align-items: flex-end; justify-content: space-between;
            min-width: 160px; flex-shrink: 0;
        }
        .bp-status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 700;
        }
        .badge-confirmed { background: rgba(6,200,160,0.1); color: #047857; border: 1px solid rgba(6,200,160,0.25); }
        .badge-cancelled { background: rgba(239,68,68,0.08); color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
        .badge-pending   { background: rgba(245,158,11,0.1); color: #b45309; border: 1px solid rgba(245,158,11,0.25); }

        .bp-price-wrap { text-align: right; }
        .bp-price-lbl { font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .bp-price { font-size: 1.7rem; font-weight: 900; color: var(--primary); letter-spacing: -1px; line-height: 1; }
        .bp-price-sub { font-size: 0.72rem; color: var(--muted); margin-top: 2px; }

        .bp-actions { display: flex; gap: 8px; }
        .btn-view {
            padding: 8px 16px; border-radius: 9px; font-size: 0.8rem; font-weight: 700;
            text-decoration: none; background: #f0f5ff; color: var(--primary);
            border: 1.5px solid rgba(26,111,244,0.2); transition: all 0.2s;
        }
        .btn-view:hover { background: var(--primary); color: #fff; }
        .btn-cancel {
            padding: 8px 16px; border-radius: 9px; font-size: 0.8rem; font-weight: 700;
            background: #fff0f0; color: #dc2626; border: 1.5px solid rgba(239,68,68,0.2);
            cursor: pointer; transition: all 0.2s; font-family: inherit;
        }
        .btn-cancel:hover { background: #ef4444; color: #fff; }

        /* Empty state */
        .empty-state {
            text-align: center; padding: 80px 40px;
            background: var(--surface); border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 14px rgba(13,31,53,0.05);
        }
        .empty-state .e-icon { font-size: 4rem; margin-bottom: 20px; display: block; }
        .empty-state h3 { font-size: 1.2rem; font-weight: 800; color: var(--dark); margin-bottom: 10px; }
        .empty-state p { color: var(--muted); font-size: 0.9rem; margin-bottom: 24px; }
        .btn-search {
            display: inline-block; padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; border-radius: 50px; text-decoration: none;
            font-weight: 700; font-size: 0.9rem;
            box-shadow: 0 4px 16px var(--primary-glow); transition: all 0.22s;
        }
        .btn-search:hover { transform: translateY(-2px); filter: brightness(1.06); }

        /* Responsive */
        @media (max-width: 900px) {
            .bp-body { flex-wrap: wrap; }
            .bp-right { min-width: 100%; padding-left: 0; padding-top: 16px;
                flex-direction: row; align-items: center; justify-content: space-between;
                border-top: 1px solid var(--border); }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .topbar, .filter-bar, .bookings-area { padding-left: 16px; padding-right: 16px; }
            .stats-strip { grid-template-columns: repeat(3,1fr); }
            .bp-img { width: 70px; }
            .bp-city { font-size: 1.2rem; }
        }

        /* ══ FOOTER STYLING ══
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
        } */
    </style>
</head>
<body>
<div class="dashboard">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
        <div class="sidebar-brand"><a href="home.php">Go<span>Zayan</span></a></div>
        <div class="sidebar-profile">
            <?php if (!empty($user['image'])): ?>
                <img class="profile-avatar" src="uploads/<?= htmlspecialchars($user['image']) ?>" alt="">
            <?php else: ?>
                <div class="profile-avatar-placeholder">✈</div>
            <?php endif; ?>
            <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="userhome.php" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="searchflights.php" class="nav-item"><span class="nav-icon">🔍</span> Search Flights</a>
            <a href="myBookings.php" class="nav-item active"><span class="nav-icon">🎫</span> My Bookings</a>
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

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-title">🎫 My Bookings</div>
            <a href="userhome.php" class="topbar-back">← Back to Dashboard</a>
        </div>

        <!-- Stats strip -->
        <div class="stats-strip">
            <div class="stat-block">
                <div class="num num-total"><?= $total ?></div>
                <div class="lbl">Total Bookings</div>
            </div>
            <div class="stat-block">
                <div class="num num-confirm"><?= $confirmed ?></div>
                <div class="lbl">Confirmed</div>
            </div>
            <div class="stat-block">
                <div class="num num-cancel"><?= $cancelled ?></div>
                <div class="lbl">Cancelled</div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <div class="filter-tabs">
                <a href="?status=all"       class="ftab <?= $status_filter==='all'       ? 'active':'' ?>">All</a>
                <a href="?status=confirmed" class="ftab <?= $status_filter==='confirmed' ? 'active':'' ?>">✅ Confirmed</a>
                <a href="?status=cancelled" class="ftab <?= $status_filter==='cancelled' ? 'active':'' ?>">❌ Cancelled</a>
            </div>
            <span class="result-count">
                <?= $bookings_result ? $bookings_result->num_rows : 0 ?> booking<?= ($bookings_result && $bookings_result->num_rows != 1) ? 's' : '' ?> found
            </span>
        </div>

        <!-- Booking cards -->
        <div class="bookings-area">
        <?php if ($bookings_result && $bookings_result->num_rows > 0):
            while ($b = $bookings_result->fetch_assoc()):
                $status = $b['status'] ?? 'pending';
        ?>
            <div class="bp-card">
                <!-- Status stripe -->
                <div class="bp-stripe stripe-<?= htmlspecialchars($status) ?>"></div>

                <!-- Flight image -->
                <div class="bp-img">
                    <?php if (!empty($b['flight_image'])): ?>
                        <img src="upload/<?= htmlspecialchars($b['flight_image']) ?>" alt="Flight">
                    <?php else: ?>
                        ✈️
                    <?php endif; ?>
                </div>

                <!-- Body -->
                <div class="bp-body">
                    <!-- Left: flight details -->
                    <div class="bp-flight">
                        <div class="bp-booking-ref">
                            Booking Ref &nbsp;<b>#<?= str_pad($b['id'], 6, '0', STR_PAD_LEFT) ?></b>
                            &nbsp;·&nbsp; <?= date('d M Y', strtotime($b['booking_date'])) ?>
                        </div>

                        <div class="bp-route">
                            <span class="bp-city"><?= htmlspecialchars(strtoupper(substr($b['from_location'] ?? $b['departure'], 0, 3))) ?></span>
                            <div class="bp-route-mid">
                                <div class="bp-route-line"><span class="bp-plane">✈</span></div>
                                <?php if (!empty($b['duration'])): ?>
                                <span class="bp-duration"><?= htmlspecialchars($b['duration']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="bp-city"><?= htmlspecialchars(strtoupper(substr($b['to_location'] ?? $b['arrival'], 0, 3))) ?></span>
                        </div>

                        <?php
                        // Live times: prefer schedule table, fall back to flights table
                        $dep_t = substr(!empty($b['sched_dep_time']) ? $b['sched_dep_time'] : ($b['departure_time'] ?? ''), 0, 5);
                        $arr_t = substr(!empty($b['sched_arr_time']) ? $b['sched_arr_time'] : ($b['arrival_time']   ?? ''), 0, 5);
                        $dep_day = $b['departure_day'] ?? '';
                        $arr_day = $b['arrival_day']   ?? '';
                        ?>
                        <?php if ($dep_t || $dep_day): ?>
                        <div class="bp-times">
                            <span class="bp-time-block">
                                <?php if ($dep_t): ?><b><?= htmlspecialchars($dep_t) ?></b><?php endif; ?>
                                <?php if ($dep_day): ?><span><?= htmlspecialchars($dep_day) ?></span><?php endif; ?>
                                <small>Dep</small>
                            </span>
                            <span class="bp-time-sep">→</span>
                            <span class="bp-time-block">
                                <?php if ($arr_t): ?><b><?= htmlspecialchars($arr_t) ?></b><?php endif; ?>
                                <?php if ($arr_day): ?><span><?= htmlspecialchars($arr_day) ?></span><?php endif; ?>
                                <small>Arr</small>
                            </span>
                        </div>
                        <?php endif; ?>

                        <div class="bp-flight-name">
                            <?= htmlspecialchars($b['flight_name']) ?>
                            &nbsp;·&nbsp; <?= htmlspecialchars($b['airline_name']) ?>
                            &nbsp;·&nbsp; <?= htmlspecialchars($b['flight_code']) ?>
                        </div>

                        <div class="bp-tags">
                            <span class="bp-tag">📅 <?= date('d M Y', strtotime($b['depart_date'])) ?></span>
                            <span class="bp-tag">👥 <?= $b['adults'] ?> Adult<?= $b['adults']>1?'s':'' ?><?= $b['children']>0 ? ', '.$b['children'].' Child'.($b['children']>1?'ren':'') : '' ?></span>
                            <span class="bp-tag">💺 <?= htmlspecialchars($b['class']) ?></span>
                            <span class="bp-tag">🔄 <?= ucfirst($b['trip_type']) ?></span>
                        </div>
                    </div>

                    <!-- Dashed divider -->
                    <div class="bp-notch"></div>

                    <!-- Right: price + actions -->
                    <div class="bp-right">
                        <span class="bp-status-badge badge-<?= htmlspecialchars($status) ?>">
                            <?= $status==='confirmed' ? '✔' : ($status==='cancelled' ? '✖' : '⏳') ?>
                            <?= ucfirst($status) ?>
                        </span>

                        <div class="bp-price-wrap">
                            <div class="bp-price-lbl">Total Paid</div>
                            <div class="bp-price">$<?= number_format($b['total_price'], 0) ?></div>
                            <div class="bp-price-sub">incl. all taxes</div>
                        </div>

                        <div class="bp-actions">
                            <a href="booking_confirm.php?id=<?= $b['id'] ?>" class="btn-view">View</a>
                            <?php if ($status === 'confirmed'): ?>
                            <form method="POST" onsubmit="return confirm('Cancel this booking?')" style="margin:0;">
                                <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn-cancel">Cancel</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="empty-state">
                <span class="e-icon">🎫</span>
                <h3>No bookings found</h3>
                <p>You haven't made any <?= $status_filter !== 'all' ? $status_filter : '' ?> bookings yet.<br>Start by searching for available flights.</p>
                <a href="searchflights.php" class="btn-search">Search Flights</a>
            </div>
        <?php endif; ?>
        </div><!-- /bookings-area -->

    </div><!-- /main -->
</div><!-- /dashboard -->

</body>
</html>
<?php include("../includes/footer.php"); ?>
