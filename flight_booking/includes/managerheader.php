<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/base_url.php';

// Protect manager pages - redirect if not manager
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: " . BASE_URL . "/view/login.php");
    exit;
}

$manager_email = $_SESSION['email'] ?? '';

// Fetch manager name from users table
include_once "../model/db_conn.php";
$mgr_stmt = $conn->prepare("SELECT name FROM users WHERE email = ?");
$mgr_stmt->bind_param("s", $manager_email);
$mgr_stmt->execute();
$mgr_row = $mgr_stmt->get_result()->fetch_assoc();
$mgr_stmt->close();
$manager_name = $mgr_row['name'] ?? 'Manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f0f4fb; color: #1e293b;
            min-height: 100vh; display: flex; flex-direction: column;
        }
        header {
            background: #0d1b3e; padding: 0 40px; height: 56px;
            display: flex; align-items: center; position: sticky;
            top: 0; z-index: 100; box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .header-container {
            width: 100%; max-width: 1400px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
        }
        header h1 { color: #fff; font-size: 1.1rem; font-weight: 700; }
        footer {
            background: linear-gradient(135deg,#0b72e6,#6c3de8);
            color: rgba(255,255,255,0.85); padding: 28px 32px;
            margin-top: auto; text-align: center;
        }
        .footer-container { max-width:1400px; margin:0 auto; display:flex; flex-direction:column; gap:10px; align-items:center; }
        .footer-container p { font-size:0.84rem; }
        .footer-container a { color:rgba(255,255,255,0.75); text-decoration:none; }
        .social-icons { display:flex; gap:12px; }
        .social-icons a { width:34px; height:34px; background:rgba(255,255,255,0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; transition:background 0.2s; }
        .social-icons a:hover { background:rgba(255,255,255,0.3); }
        .contact-info { display:flex; gap:20px; flex-wrap:wrap; justify-content:center; font-size:0.78rem; }
        nav { display: flex; align-items: center; gap: 6px; }
        nav > a {
            color: rgba(255,255,255,0.8); text-decoration: none;
            font-size: 0.88rem; font-weight: 500; padding: 7px 14px;
            border-radius: 6px; transition: background 0.15s, color 0.15s;
        }
        nav > a:hover { background: rgba(255,255,255,0.1); color: #fff; }

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

        .dropdown-mgr-info {
            background: #0b72e6; padding: 14px 16px;
            color: white; display: flex; align-items: center; gap: 10px;
        }
        .dropdown-mgr-info .mgr-icon {
            width: 40px; height: 40px; border-radius: 50%;
            background: rgba(255,255,255,0.2); display: flex;
            align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .dropdown-mgr-info .d-name  { font-weight: 600; font-size: 0.92rem; }
        .dropdown-mgr-info .d-role  { font-size: 0.72rem; opacity: 0.8; }

        .dropdown-content a {
            color: #333; padding: 11px 16px;
            text-decoration: none; display: flex;
            align-items: center; gap: 10px;
            font-size: 0.9rem; transition: background 0.2s;
        }
        .dropdown-content a:hover { background: #f0f7ff; color: #0b72e6; }
        .dropdown-content .logout-link { color: #e74c3c; border-top: 1px solid #f0f0f0; }
        .dropdown-content .logout-link:hover { background: #fff5f5; color: #c0392b; }

        .mgr-trigger {
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; padding: 6px 12px;
            border-radius: 25px; background: rgba(255,255,255,0.15);
            transition: background 0.2s;
        }
        .mgr-trigger:hover { background: rgba(255,255,255,0.25); }
        .mgr-trigger .m-name  { color: white; font-size: 0.88rem; font-weight: 600; }
        .mgr-trigger .m-arrow { color: white; font-size: 0.65rem; opacity: 0.7; }

        @media screen and (max-width: 768px) {
            nav > a { display: none; }
        }
        /* Notification Bell */
        .notif-bell { position: relative; cursor: pointer; margin-right: 20px; }
        .notif-bell-icon {
            font-size: 1.3rem; color: #fff;
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px;
            transition: color 0.2s;
        }
        .notif-bell-icon:hover { color: #ffd700; }
        .notif-badge {
            position: absolute; top: -6px; right: -8px;
            background: #ef4444; color: #fff;
            font-size: 0.65rem; font-weight: 700;
            width: 20px; height: 20px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #0d1b3e;
        }
        .notif-dropdown {
            position: absolute; top: 100%; right: 0;
            background: #fff; border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            min-width: 320px; max-height: 400px; overflow-y: auto;
            display: none; z-index: 1000;
            border: 1px solid #e2e8f0;
        }
        .notif-dropdown.show { display: block; }
        .notif-header {
            padding: 12px 16px; border-bottom: 1px solid #e2e8f0;
            font-weight: 700; color: #0d1b3e; font-size: 0.9rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .notif-header button {
            background: none; border: none; color: #0b72e6; cursor: pointer;
            font-size: 0.75rem; font-weight: 700;
        }
        .notif-list { padding: 0; }
        .notif-item {
            padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
            cursor: pointer; transition: background 0.2s;
            font-size: 0.85rem; line-height: 1.4;
        }
        .notif-item:hover { background: #f8fafc; }
        .notif-item .notif-msg { color: #1e293b; }
        .notif-item .notif-time { color: #94a3b8; font-size: 0.75rem; margin-top: 4px; }
        .notif-empty {
            padding: 24px 16px; text-align: center;
            color: #94a3b8; font-size: 0.9rem;
        }
    </style>
</head>
<body>
<header>
    <div class="header-container">
        <h1>&#9992; Manager Panel</h1>
        <nav>
            <a href="<?= BASE_URL ?>/view/managerdemo.php">Manage Flights</a>
            <a href="<?= BASE_URL ?>/view/manageSeatupdatePrice.php">Seats &amp; Prices</a>
            <!-- <a href="<?= BASE_URL ?>/view/passengerHome.php">Passenger Search</a> -->

            <!-- Notification Bell -->
            <div class="notif-bell" onclick="toggleNotifDropdown(event)">
                <div class="notif-bell-icon">🔔</div>
                <div class="notif-badge" id="notifBadge" style="display: none;"><span id="notifCount">0</span></div>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        Notifications
                        <button onclick="markAllNotifRead()">Clear All</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="notif-empty">Loading...</div>
                    </div>
                </div>
            </div>

            <div class="dropdown">
                <div class="mgr-trigger" onclick="toggleDropdown()">
                    <span>&#128113;</span>
                    <span class="m-name"><?= htmlspecialchars(explode(' ', trim($manager_name))[0]) ?></span>
                    <span class="m-arrow">&#9660;</span>
                </div>
                <div class="dropdown-content" id="mgrDropdown">
                    <div class="dropdown-mgr-info">
                        <div class="mgr-icon">&#128113;</div>
                        <div>
                            <div class="d-name"><?= htmlspecialchars($manager_name) ?></div>
                            <div class="d-role">&#9196; Flight Manager</div>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/view/viewmanagerprofile.php">&#128100; View Profile</a>
                    <!-- <a href="<?= BASE_URL ?>/view/managerdemo.php">&#9992; Manage Flights</a>
                    <a href="<?= BASE_URL ?>/view/manageSeatupdatePrice.php">&#128186; Seats &amp; Prices</a>
                    <a href="<?= BASE_URL ?>/view/passengerHome.php">&#128269; Passenger Search</a>
                    <a href="<?= BASE_URL ?>/view/schedule_form.php">&#128197; My Schedule</a> -->
                    <a href="<?= BASE_URL ?>/view/changeManagerPass.php">&#128274; Change Password</a>
                    <a href="<?= BASE_URL ?>/logout.php" class="logout-link">&#128682; Log Out</a>
                </div>
            </div>
        </nav>
    </div>
</header>

<script>
function toggleDropdown() {
    document.getElementById('mgrDropdown')?.classList.toggle('show');
}

// Notification functions
function toggleNotifDropdown(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notifDropdown');
    dropdown?.classList.toggle('show');
    if (dropdown?.classList.contains('show')) {
        fetchNotifications();
    }
}

function fetchNotifications() {
    fetch('<?= BASE_URL ?>/view/notifications.php?action=get_unread')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            const count = document.getElementById('notifCount');
            
            if (data.count > 0) {
                badge.style.display = 'flex';
                count.textContent = data.count;
                let html = '';
                data.notifications.forEach(n => {
                    const date = new Date(n.created_at);
                    const time = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    html += `<div class="notif-item" onclick="markNotifRead(${n.id})">
                        <div class="notif-msg">${n.message}</div>
                        <div class="notif-time">${time}</div>
                    </div>`;
                });
                list.innerHTML = html;
            } else {
                badge.style.display = 'none';
                list.innerHTML = '<div class="notif-empty">No new notifications</div>';
            }
        })
        .catch(e => console.error('Error fetching notifications:', e));
}

function markNotifRead(notifId) {
    fetch(`<?= BASE_URL ?>/view/notifications.php?action=mark_read&notif_id=${notifId}`)
        .then(() => fetchNotifications())
        .catch(e => console.error('Error marking notification as read:', e));
}

function markAllNotifRead() {
    fetch('<?= BASE_URL ?>/view/notifications.php?action=mark_all_read')
        .then(() => fetchNotifications())
        .catch(e => console.error('Error marking all notifications as read:', e));
}

window.onclick = function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
    }
    if (!e.target.closest('.notif-bell')) {
        document.getElementById('notifDropdown')?.classList.remove('show');
    }
}

// Load notifications when page loads
document.addEventListener('DOMContentLoaded', () => {
    fetchNotifications();
    // Refresh every 30 seconds
    setInterval(fetchNotifications, 30000);
});
</script>