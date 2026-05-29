<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect admin pages - redirect if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /flight_booking/view/login.php");
    exit;
}

$admin_email = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <link rel="stylesheet" href="changePassword.css">
    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f4fb;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Header ── */
        header {
            background: #0d1b3e;
            padding: 0 40px;
            height: 56px;
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .header-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header h1 {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Nav ── */
        nav { display: flex; align-items: center; gap: 4px; }

        nav > a {
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            padding: 7px 14px;
            border-radius: 6px;
            transition: background 0.15s, color 0.15s;
        }
        nav > a:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        /* ── Admin trigger ── */
        .admin-trigger {
            display: flex; align-items: center; gap: 7px;
            cursor: pointer; padding: 6px 12px;
            border-radius: 6px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            transition: background 0.15s;
            margin-left: 4px;
        }
        .admin-trigger:hover { background: rgba(255,255,255,0.15); }
        .admin-trigger .shield { font-size: 0.95rem; }
        .admin-trigger .a-label { color: rgba(255,255,255,0.9); font-size: 0.88rem; font-weight: 600; }
        .admin-trigger .a-arrow { color: rgba(255,255,255,0.6); font-size: 0.6rem; }

        /* ── Dropdown ── */
        .dropdown { position: relative; }
        .dropdown-content {
            display: none;
            position: absolute;
            top: 46px; right: 0;
            background: white;
            min-width: 220px;
            box-shadow: 0 10px 32px rgba(0,0,0,0.18);
            border-radius: 12px;
            overflow: hidden;
            z-index: 999;
            border: 1px solid #e8f0fb;
        }
        .dropdown-content.show { display: block; }

        .dropdown-admin-info {
            background: linear-gradient(135deg, #0b72e6, #6c3de8);
            padding: 16px 18px;
            color: white;
        }
        .dropdown-admin-info .d-icon { font-size: 1.5rem; margin-bottom: 5px; }
        .dropdown-admin-info .d-name { font-weight: 700; font-size: 0.92rem; }
        .dropdown-admin-info .d-email { font-size: 0.72rem; opacity: 0.8; word-break: break-all; margin-top: 3px; }

        .dropdown-content a {
            color: #334155;
            padding: 11px 18px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.88rem;
            transition: background 0.15s, color 0.15s;
        }
        .dropdown-content a:hover { background: #f0f7ff; color: #0b72e6; }
        .dropdown-content .logout-link { color: #e74c3c; border-top: 1px solid #f1f5f9; }
        .dropdown-content .logout-link:hover { background: #fff5f5; color: #c0392b; }

        /* ── Footer ── */
        footer {
            background: linear-gradient(135deg, #0b72e6 0%, #6c3de8 100%);
            color: rgba(255,255,255,0.85);
            padding: 28px 32px;
            margin-top: auto;
            text-align: center;
        }
        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }
        .footer-container p { font-size: 0.84rem; }
        .footer-container a { color: rgba(255,255,255,0.75); text-decoration: none; }
        .footer-container a:hover { color: #fff; }
        .social-icons { display: flex; gap: 12px; }
        .social-icons a {
            width: 34px; height: 34px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 0.9rem;
            transition: background 0.2s;
        }
        .social-icons a:hover { background: rgba(255,255,255,0.3); }
        .contact-info {
            display: flex; gap: 20px; flex-wrap: wrap;
            justify-content: center; font-size: 0.78rem;
        }

        @media screen and (max-width: 768px) {
            nav > a { display: none; }
            header { padding: 0 16px; }
        }
    </style>
</head>
<body>
<header>
    <div class="header-container">
        <h1>&#128737; Admin Panel</h1>
        <nav>
            <a href="/flight_booking/view/adminAnalytics.php">Analytics</a>
            <a href="/flight_booking/view/addAirline.php">Airlines</a>
            <a href="/flight_booking/view/addFlight.php">Flights</a>
            <a href="/flight_booking/view/adduser.php">Add User</a>

            <div class="dropdown">
                <div class="admin-trigger" onclick="toggleDropdown()">
                    <span class="shield">&#128737;</span>
                    <span class="a-label">Admin</span>
                    <span class="a-arrow">&#9660;</span>
                </div>
                <div class="dropdown-content" id="adminDropdown">
                    <div class="dropdown-admin-info">
                        <div class="d-icon">&#128737;</div>
                        <div class="d-name">Administrator</div>
                        <div class="d-email"><?= htmlspecialchars($admin_email) ?></div>
                    </div>
                    <a href="/flight_booking/view/adminAnalytics.php">📊 System Analytics</a>
                    <a href="/flight_booking/view/adminprofile.php">&#128100; View Profile</a>
                    <a href="/flight_booking/view/adminChangePassword.php">&#128274; Change Password</a>
                    <a href="/flight_booking/logout.php" class="logout-link">&#128682; Log Out</a>
                </div>
            </div>
        </nav>
    </div>
</header>

<script>
function toggleDropdown() {
    document.getElementById('adminDropdown')?.classList.toggle('show');
}
window.onclick = function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
    }
}
</script>
