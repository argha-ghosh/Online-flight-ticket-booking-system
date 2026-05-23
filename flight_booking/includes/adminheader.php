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
    <link rel="stylesheet" href="component.css">
    <link rel="stylesheet" href="changePassword.css">
    <style>
        nav { display: flex; align-items: center; gap: 10px; }

        .hamburger {
            display: flex; flex-direction: column;
            gap: 4px; cursor: pointer; padding: 10px;
        }
        .hamburger span {
            display: block; width: 30px; height: 3px;
            background-color: white; border-radius: 5px;
        }

        .dropdown { position: relative; }
        .dropdown-content {
            display: none; position: absolute;
            top: 55px; right: 0;
            background: white;
            min-width: 210px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border-radius: 10px; overflow: hidden; z-index: 999;
        }
        .dropdown-content.show { display: block; }

        .dropdown-admin-info {
            background: #0b72e6; padding: 14px 16px;
            color: white;
        }
        .dropdown-admin-info .d-icon { font-size: 1.5rem; margin-bottom: 4px; }
        .dropdown-admin-info .d-name { font-weight: 600; font-size: 0.92rem; }
        .dropdown-admin-info .d-email { font-size: 0.72rem; opacity: 0.8; word-break: break-all; }

        .dropdown-content a {
            color: #333; padding: 11px 16px;
            text-decoration: none; display: flex;
            align-items: center; gap: 10px;
            font-size: 0.9rem; transition: background 0.2s;
        }
        .dropdown-content a:hover { background: #f0f7ff; color: #0b72e6; }
        .dropdown-content .logout-link { color: #e74c3c; border-top: 1px solid #f0f0f0; }
        .dropdown-content .logout-link:hover { background: #fff5f5; color: #c0392b; }

        .admin-trigger {
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; padding: 6px 12px;
            border-radius: 25px; background: rgba(255,255,255,0.15);
            transition: background 0.2s;
        }
        .admin-trigger:hover { background: rgba(255,255,255,0.25); }
        .admin-trigger .shield { font-size: 1.1rem; }
        .admin-trigger .a-label { color: white; font-size: 0.88rem; font-weight: 600; }
        .admin-trigger .a-arrow { color: white; font-size: 0.65rem; opacity: 0.7; }

        @media screen and (max-width: 768px) {
            nav > a { display: none; }
        }
    </style>
</head>
<body>
<header>
    <div class="header-container">
        <h1>&#128737; Admin Panel</h1>
        <nav>
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
                    <a href="/flight_booking/view/adminprofile.php">&#128100; View Profile</a>
                    <a href="/flight_booking/view/addAirline.php">&#9992; Manage Airlines</a>
                    <a href="/flight_booking/view/addFlight.php">&#128640; Manage Flights</a>
                    <a href="/flight_booking/view/adduser.php">&#128113; Add User</a>
                    <a href="/flight_booking/view/changePassword.php">&#128274; Change Password</a>
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