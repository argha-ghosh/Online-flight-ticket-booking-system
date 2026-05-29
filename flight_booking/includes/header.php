<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    }
}

// Prevent caching of authenticated pages
if (isset($_SESSION['role']) && !headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
}

$is_webuser = isset($_SESSION['role']) && $_SESSION['role'] === 'webuser';
$user_name = '';
$user_image = '';

if ($is_webuser && isset($_SESSION['email'])) {
    include_once "../model/db_conn.php";
    $stmt = $conn->prepare("SELECT name, image FROM webusers WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $user_name  = $row['name']  ?? 'User';
    $user_image = $row['image'] ?? '';
}
?>
<style>
    @import url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css");

    header {
        position: relative;
        z-index: 100;
        background: linear-gradient(135deg, #0b72e6, #0556b3);
        padding: 16px 0;
        box-shadow: 0 10px 30px rgba(3, 55, 138, 0.18);
    }

    .header-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    header h1 a {
        color: white;
        text-decoration: none;
        font-size: 1.8rem;
        font-weight: 900;
        letter-spacing: -0.5px;
    }

    nav {
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
    }

    nav a {
        color: white;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.25s ease;
        font-size: 0.95rem;
    }

    nav a:hover {
        color: #7dd3fc;
    }

    .nav-avatar {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255,255,255,0.5);
    }

    .dropdown {
        position: relative;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        top: 56px;
        right: 0;
        background-color: white;
        min-width: 220px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.16);
        border-radius: 12px;
        overflow: hidden;
        z-index: 999;
    }

    .dropdown-content.show {
        display: block;
    }

    .dropdown-user-info {
        background: #0b72e6;
        padding: 14px 16px;
        color: white;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .dropdown-user-info .d-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255,255,255,0.4);
    }

    .dropdown-user-info .d-name {
        font-weight: 600;
        font-size: 0.92rem;
    }

    .dropdown-user-info .d-role {
        font-size: 0.72rem;
        opacity: 0.85;
    }

    .dropdown-content a {
        color: #333;
        padding: 12px 16px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
        transition: background 0.2s ease;
    }

    .dropdown-content a:hover {
        background-color: #f0f7ff;
        color: #0b72e6;
    }

    .dropdown-content .logout-link {
        color: #e74c3c;
        border-top: 1px solid #f0f0f0;
    }

    .dropdown-content .logout-link:hover {
        background: #fff5f5;
        color: #c0392b;
    }

    .user-trigger {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 25px;
        transition: background 0.2s ease;
        background: rgba(255,255,255,0.08);
    }

    .user-trigger:hover {
        background: rgba(255,255,255,0.16);
    }

    .user-trigger .u-name {
        color: white;
        font-size: 0.92rem;
        font-weight: 600;
    }

    .user-trigger .u-arrow {
        color: white;
        font-size: 0.75rem;
        opacity: 0.8;
    }

    @media screen and (max-width: 768px) {
        nav > a {
            display: none;
        }

        .header-container {
            justify-content: space-between;
        }
    }
</style>
<header>
    <div class="header-container">
        <h1><a href="/flight_booking/view/home.php">GoZayan</a></h1>
        <nav>
            <a href="/flight_booking/view/searchflights.php">Search Flights</a>
            <?php if ($is_webuser): ?>
                <a href="/flight_booking/view/userhome.php">Dashboard</a>
                <div class="dropdown">
                    <div class="user-trigger" onclick="toggleDropdown()">
                        <?php
                        $avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=ffffff&color=0b72e6&size=80";
                        if (!empty($user_image) && file_exists(__DIR__ . "/../view/uploads/" . $user_image)) {
                            $avatar_src = "/flight_booking/view/uploads/" . htmlspecialchars($user_image);
                        }
                        ?>
                        <img src="<?= $avatar_src ?>" class="nav-avatar" alt="">
                        <span class="u-name"><?= htmlspecialchars(explode(' ', trim($user_name))[0]) ?></span>
                        <span class="u-arrow">&#9660;</span>
                    </div>
                    <div class="dropdown-content" id="userDropdown">
                        <div class="dropdown-user-info">
                            <div>
                                <div class="d-name"><?= htmlspecialchars($user_name) ?></div>
                                <div class="d-role">&#9992; GoZayan Traveller</div>
                            </div>
                        </div>
                        <a href="/flight_booking/view/userhome.php">&#127968; Dashboard</a>
                        <a href="/flight_booking/view/searchflights.php">&#128269; Search Flights</a>
                        <a href="/flight_booking/view/myBookings.php">&#127915; My Bookings</a>
                        <a href="/flight_booking/view/passengerProfile.php">&#128100; My Profile</a>
                        <a href="/flight_booking/view/changePassword.php">&#128274; Change Password</a>
                        <a href="/flight_booking/logout.php" class="logout-link">&#128682; Log Out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/flight_booking/view/login.php">Login</a>
                <a href="/flight_booking/view/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<script>
function toggleDropdown() {
    document.getElementById('userDropdown')?.classList.toggle('show');
}
window.onclick = function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-content').forEach(function(d) {
            d.classList.remove('show');
        });
    }
}

window.addEventListener('pageshow', function(e) {
    if (e.persisted) {
        window.location.reload();
    }
});

document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        fetch('/flight_booking/view/session_check.php', { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                const isLoggedIn = <?= $is_webuser ? 'true' : 'false' ?>;
                if (isLoggedIn && !data.logged_in) {
                    window.location.reload();
                }
            })
            .catch(function() {});
    }
});
</script>