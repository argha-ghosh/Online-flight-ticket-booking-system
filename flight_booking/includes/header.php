<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) session_start();
}
require_once __DIR__ . '/../config/base_url.php';

if (isset($_SESSION['role']) && !headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
}

$is_webuser = isset($_SESSION['role']) && $_SESSION['role'] === 'webuser';
$user_name  = '';
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
    /* ── HEADER ─────────────────────────────────────── */
    header {
        position: fixed;
        top: 0; left: 0; right: 0;
        z-index: 1000;
        background: linear-gradient(135deg, #0f2444 0%, #1a3a6e 60%, #0f2444 100%);
        border-bottom: 2px solid rgba(201, 168, 76, 0.35);
        box-shadow: 0 4px 28px rgba(8, 23, 46, 0.45);
        padding: 0;
        transition: background 0.35s ease, box-shadow 0.35s ease;
    }

    /* Scrolled state */
    header.scrolled {
        background: linear-gradient(135deg, #08172e 0%, #142d52 60%, #08172e 100%);
        box-shadow: 0 4px 36px rgba(8, 23, 46, 0.6);
        border-bottom-color: rgba(201, 168, 76, 0.5);
    }

    .header-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 28px;
        height: 62px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    /* ── LOGO ── */
    .header-logo {
        display: flex;
        align-items: center;
        gap: 9px;
        text-decoration: none;
        flex-shrink: 0;
    }
    .header-logo .logo-icon {
        width: 34px; height: 34px;
        background: rgba(255,255,255,0.18);
        border: 1px solid rgba(255,255,255,0.28);
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        backdrop-filter: blur(6px);
        transition: background 0.2s;
    }
    .header-logo:hover .logo-icon { background: rgba(255,255,255,0.28); }
    .header-logo .logo-text {
        font-size: 1.25rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.4px;
    }

    /* ── NAV ── */
    nav {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    nav > a {
        color: rgba(255,255,255,0.88);
        text-decoration: none;
        font-size: 0.88rem;
        font-weight: 500;
        padding: 7px 14px;
        border-radius: 8px;
        transition: background 0.2s, color 0.2s;
        white-space: nowrap;
    }
    nav > a:hover {
        background: rgba(255,255,255,0.14);
        color: #fff;
    }

    /* Search Flights — gold accent pill */
    nav > a[href*="searchflights"] {
        background: rgba(201, 168, 76, 0.18);
        border: 1px solid rgba(201, 168, 76, 0.4);
        color: #e8c96a;
        font-weight: 700;
        padding: 7px 16px;
    }
    nav > a[href*="searchflights"]:hover {
        background: rgba(201, 168, 76, 0.32);
        border-color: rgba(201, 168, 76, 0.65);
        color: #f0d87a;
    }

    /* ── AUTH BUTTONS (guest) ── */
    .btn-nav-login {
        color: rgba(255,255,255,0.88) !important;
        border: 1px solid rgba(255,255,255,0.3) !important;
        border-radius: 8px !important;
        padding: 7px 16px !important;
        font-weight: 600 !important;
        transition: background 0.2s, border-color 0.2s, color 0.2s !important;
    }
    .btn-nav-login:hover {
        background: rgba(255,255,255,0.14) !important;
        border-color: rgba(255,255,255,0.55) !important;
        color: #fff !important;
    }

    .btn-nav-register {
        background: rgba(255,255,255,0.95) !important;
        color: #0b72e6 !important;
        border-radius: 8px !important;
        padding: 7px 16px !important;
        font-weight: 700 !important;
        font-size: 0.88rem !important;
        transition: background 0.2s, transform 0.15s !important;
        border: none !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    }
    .btn-nav-register:hover {
        background: #fff !important;
        color: #0556b3 !important;
        transform: translateY(-1px) !important;
    }

    /* ── DIVIDER ── */
    .nav-divider {
        width: 1px; height: 22px;
        background: rgba(255,255,255,0.2);
        margin: 0 6px;
        flex-shrink: 0;
    }

    /* ── AVATAR ── */
    .nav-avatar {
        width: 32px; height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255,255,255,0.5);
        flex-shrink: 0;
    }

    /* ── USER TRIGGER ── */
    .dropdown { position: relative; }

    .user-trigger {
        display: flex; align-items: center; gap: 8px;
        cursor: pointer;
        padding: 5px 10px 5px 6px;
        border-radius: 30px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.2);
        transition: background 0.2s, border-color 0.2s;
        backdrop-filter: blur(8px);
    }
    .user-trigger:hover {
        background: rgba(255,255,255,0.22);
        border-color: rgba(255,255,255,0.35);
    }
    .user-trigger .u-name {
        color: #fff;
        font-size: 0.86rem;
        font-weight: 600;
    }
    .user-trigger .u-arrow {
        color: rgba(255,255,255,0.6);
        font-size: 0.6rem;
    }

    /* ── DROPDOWN PANEL ── */
    .dropdown-content {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: #fff;
        min-width: 224px;
        border-radius: 14px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.16), 0 0 0 1px rgba(0,0,0,0.06);
        overflow: hidden;
        z-index: 999;
        animation: dropIn 0.2s ease;
    }
    @keyframes dropIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .dropdown-content.show { display: block; }

    /* User info strip */
    .dropdown-user-info {
        background: linear-gradient(135deg, #0b72e6, #0556b3);
        padding: 14px 16px;
        color: white;
        display: flex; align-items: center; gap: 10px;
    }
    .dropdown-user-info .d-avatar {
        width: 36px; height: 36px;
        border-radius: 50%; object-fit: cover;
        border: 2px solid rgba(255,255,255,0.4);
        flex-shrink: 0;
    }
    .dropdown-user-info .d-name  { font-weight: 700; font-size: 0.88rem; }
    .dropdown-user-info .d-role  { font-size: 0.7rem; opacity: 0.8; margin-top: 1px; }

    /* Links */
    .dropdown-content a {
        color: #374151;
        padding: 10px 16px;
        text-decoration: none;
        display: flex; align-items: center; gap: 10px;
        font-size: 0.87rem;
        font-weight: 500;
        transition: background 0.15s, color 0.15s;
    }
    .dropdown-content a:hover {
        background: #f0f7ff;
        color: #0b72e6;
    }
    .dropdown-content .logout-link {
        color: #dc2626;
        border-top: 1px solid #f3f4f6;
    }
    .dropdown-content .logout-link:hover {
        background: #fff5f5;
        color: #b91c1c;
    }

    /* ── BODY OFFSET (since header is fixed) ── */
    /* Hero pages handle their own top padding — no global offset */

    /* ── MOBILE HEADER ── */
    @media (max-width: 768px) {
        .header-container { padding: 0 14px; height: 52px; }
        .header-logo .logo-text { font-size: 1.1rem; }
        .header-logo .logo-icon { width: 30px; height: 30px; font-size: .9rem; }
        nav > a:not(.btn-nav-login):not(.btn-nav-register) { display: none; }
        .nav-divider { display: none; }
        .user-trigger .u-name { display: none; }
        .user-trigger { padding: 4px 8px 4px 4px; }
        .nav-avatar { width: 30px; height: 30px; }
        .dropdown-content { right: -10px; min-width: 200px; }
        .btn-nav-login, .btn-nav-register { padding: 6px 12px !important; font-size: .82rem !important; }
    }
</style>

<header id="siteHeader">
    <div class="header-container">

        <!-- Logo -->
        <a href="<?= BASE_URL ?>/view/home.php" class="header-logo">
            <div class="logo-icon">✈</div>
            <span class="logo-text">GoZayan</span>
        </a>

        <!-- Nav -->
        <nav>
            <a href="<?= BASE_URL ?>/view/searchflights.php">Search Flights</a>

            <?php if ($is_webuser): ?>
                <a href="<?= BASE_URL ?>/view/userhome.php">Dashboard</a>
                <div class="nav-divider"></div>

                <div class="dropdown">
                    <div class="user-trigger" onclick="toggleDropdown()">
                        <?php
                        $avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=0b72e6&color=fff&size=80";
                        if (!empty($user_image) && file_exists(__DIR__ . "/../view/uploads/" . $user_image)) {
                            $avatar_src = BASE_URL . "/view/uploads/" . htmlspecialchars($user_image);
                        }
                        ?>
                        <img src="<?= $avatar_src ?>" class="nav-avatar" alt="">
                        <span class="u-name"><?= htmlspecialchars(explode(' ', trim($user_name))[0]) ?></span>
                        <span class="u-arrow">▾</span>
                    </div>

                    <div class="dropdown-content" id="userDropdown">
                        <div class="dropdown-user-info">
                            <img src="<?= $avatar_src ?>" class="d-avatar" alt="">
                            <div>
                                <div class="d-name"><?= htmlspecialchars($user_name) ?></div>
                                <div class="d-role">✈ GoZayan Traveller</div>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>/view/userhome.php">🏠 Dashboard</a>
                        <a href="<?= BASE_URL ?>/view/searchflights.php">🔍 Search Flights</a>
                        <a href="<?= BASE_URL ?>/view/myBookings.php">🎫 My Bookings</a>
                        <a href="<?= BASE_URL ?>/view/passengerProfile.php">👤 My Profile</a>
                        <a href="<?= BASE_URL ?>/view/changePassword.php">🔒 Change Password</a>
                        <a href="<?= BASE_URL ?>/logout.php" class="logout-link">🚪 Log Out</a>
                    </div>
                </div>

            <?php else: ?>
                <div class="nav-divider"></div>
                <a href="<?= BASE_URL ?>/view/login.php" class="btn-nav-login">Login</a>
                <a href="<?= BASE_URL ?>/view/register.php" class="btn-nav-register">Register</a>
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

// Scroll — darken header when not at top
window.addEventListener('scroll', () => {
    document.getElementById('siteHeader')
            .classList.toggle('scrolled', window.scrollY > 10);
}, { passive: true });

// Session check on tab refocus
window.addEventListener('pageshow', e => { if (e.persisted) window.location.reload(); });
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        fetch('<?= BASE_URL ?>/view/session_check.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                const isLoggedIn = <?= $is_webuser ? 'true' : 'false' ?>;
                if (isLoggedIn && !data.logged_in) window.location.reload();
            }).catch(() => {});
    }
});
</script>