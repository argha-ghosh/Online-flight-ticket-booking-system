<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <link rel="stylesheet" href="component.css">
    <style>
        nav { display: flex; align-items: center; gap: 10px; }

        .nav-avatar {
            width: 35px; height: 35px; border-radius: 50%;
            object-fit: cover; border: 2px solid rgba(255,255,255,0.5);
            vertical-align: middle;
        }
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
            background-color: white;
            min-width: 210px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border-radius: 10px; overflow: hidden; z-index: 999;
        }
        .dropdown-content.show { display: block; }

        .dropdown-user-info {
            background: #0b72e6; padding: 14px 16px;
            color: white; display: flex; align-items: center; gap: 10px;
        }
        .dropdown-user-info .d-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid rgba(255,255,255,0.4);
        }
        .dropdown-user-info .d-name  { font-weight: 600; font-size: 0.92rem; }
        .dropdown-user-info .d-role  { font-size: 0.72rem; opacity: 0.8; }

        .dropdown-content a {
            color: #333; padding: 11px 16px;
            text-decoration: none; display: flex;
            align-items: center; gap: 10px;
            font-size: 0.9rem; transition: background 0.2s;
        }
        .dropdown-content a:hover { background-color: #f0f7ff; color: #0b72e6; }
        .dropdown-content .logout-link { color: #e74c3c; border-top: 1px solid #f0f0f0; }
        .dropdown-content .logout-link:hover { background: #fff5f5; color: #c0392b; }

        .user-trigger {
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; padding: 5px 10px;
            border-radius: 25px; transition: background 0.2s;
        }
        .user-trigger:hover { background: rgba(255,255,255,0.15); }
        .user-trigger .u-name  { color: white; font-size: 0.9rem; font-weight: 600; }
        .user-trigger .u-arrow { color: white; font-size: 0.65rem; opacity: 0.7; }

        @media screen and (max-width: 768px) {
            nav > a { display: none; }
        }
    </style>
</head>
<body>
<header>
    <div class="header-container">
        <h1><a href="/flight_booking/view/home.php" style="color:white;text-decoration:none;">GoZayan</a></h1>
        <nav>
            <a href="/flight_booking/view/searchflights.php">Search Flights</a>
            <!-- <a href="/flight_booking/view/home.php">Home</a> -->
            
            <?php if ($is_webuser): ?>
                <a href="/flight_booking/view/myBookings.php">My Bookings</a>

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
                            <!-- <img src="<?= $avatar_src ?>" class="d-avatar" alt=""> -->
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
        document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
    }
}
</script>