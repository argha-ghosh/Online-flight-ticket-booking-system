<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}

$email = $_SESSION['email'];
$message = '';
$msg_type = '';

$stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
$stmt->bind_param("s", $email); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (isset($_POST['save_profile'])) {
    $new_name = trim($_POST['name'] ?? '');
    $errors = [];
    if (empty($new_name)) $errors[] = "Name cannot be empty.";
    $new_image = $user['image'];
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['image/jpeg','image/jpg','image/png'];
        if (!in_array($_FILES['image']['type'], $allowed)) $errors[] = "Only JPG and PNG images allowed.";
        elseif ($_FILES['image']['size'] > 10*1024*1024) $errors[] = "Image must be less than 10MB.";
        else {
            $upload_dir = __DIR__ . "/uploads/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $new_image = time() . '_' . basename($_FILES['image']['name']);
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_image)) {
                $errors[] = "Failed to upload image."; $new_image = $user['image'];
            }
        }
    }
    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE webusers SET name = ?, image = ? WHERE email = ?");
        $upd->bind_param("sss", $new_name, $new_image, $email);
        if ($upd->execute()) {
            $message = "Profile updated successfully!"; $msg_type = 'success';
            $stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
            $stmt->bind_param("s", $email); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        } else { $message = "Error updating profile."; $msg_type = 'error'; }
        $upd->close();
    } else { $message = implode(' ', $errors); $msg_type = 'error'; }
}

$cnt   = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE user_id = {$user['id']}")->fetch_assoc();
$spent = $conn->query("SELECT SUM(total_price) as s FROM bookings WHERE user_id = {$user['id']} AND status='confirmed'")->fetch_assoc();
$booking_count = $cnt['c'];
$total_spent   = $spent['s'] ?? 0;

$img_src = "https://ui-avatars.com/api/?name=".urlencode($user['name'])."&background=0b1f3a&color=d4a84b&size=200&bold=true";
if (!empty($user['image']) && file_exists(__DIR__."/uploads/".$user['image']))
    $img_src = "uploads/".htmlspecialchars($user['image']);

include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GoZayan · My Profile</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,900;1,600;1,700&family=DM+Mono:wght@400;500;600&family=Mulish:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{
    --navy:#08172e;--navy-2:#0f2444;--navy-3:#172f56;--navy-4:#1e3d6e;
    --gold:#c9a84c;--gold-lt:#e0bc6a;--gold-dk:#a8893a;--gold-tint:rgba(201,168,76,.09);--gold-glow:rgba(201,168,76,.22);
    --cream:#f8f5f0;--cream-2:#f0ebe2;--cream-3:#e6dfd4;
    --ink:#0d1a28;--ink-2:#2e4057;--ink-3:#6b84a0;--ink-4:#9db3c8;
    --surface:#ffffff;--surface-2:#fdfcfa;
    --green:#0a8f6a;--green-lt:#12b585;--green-bg:#d0f5ea;
    --red:#c8293a;--red-bg:#fde8ea;
    --border:#e2d9cc;--border-2:#ede7de;
    --sh-xs:0 1px 3px rgba(8,23,46,.05);
    --sh-sm:0 2px 10px rgba(8,23,46,.07),0 1px 3px rgba(8,23,46,.04);
    --sh-md:0 6px 24px rgba(8,23,46,.09),0 2px 8px rgba(8,23,46,.05);
    --sh-lg:0 16px 48px rgba(8,23,46,.12),0 4px 16px rgba(8,23,46,.06);
    --sh-gold:0 6px 24px rgba(201,168,76,.25);
    --serif:'Playfair Display',Georgia,serif;--sans:'Mulish',system-ui,sans-serif;--mono:'DM Mono','Courier New',monospace;
    --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--sans);background:var(--cream);color:var(--ink);min-height:100vh;-webkit-font-smoothing:antialiased;padding-top:62px}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--cream-2)}::-webkit-scrollbar-thumb{background:var(--cream-3);border-radius:4px}
@keyframes riseIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideInL{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:translateX(0)}}

.sub-header{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-3) 100%);padding:18px 40px;display:flex;align-items:center;gap:20px;border-bottom:1px solid rgba(255,255,255,.05);position:relative;overflow:hidden}
.sub-header::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 80% 50%,rgba(201,168,76,.08) 0%,transparent 60%);pointer-events:none}
.sh-icon{width:44px;height:44px;border-radius:12px;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.25);display:flex;align-items:center;justify-content:center;color:var(--gold-lt);font-size:1.15rem;flex-shrink:0}
.sh-text h2{font-family:var(--serif);font-size:1.15rem;font-weight:700;color:#fff;letter-spacing:-.01em;margin-bottom:2px}
.sh-text p{font-size:.75rem;color:rgba(255,255,255,.42);font-weight:500}
.sh-badge{margin-left:auto;font-family:var(--mono);font-size:.68rem;font-weight:500;color:var(--gold-lt);letter-spacing:.1em;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.25);padding:6px 18px;border-radius:30px;white-space:nowrap}

.back-bar{max-width:1340px;margin:0 auto;padding:16px 36px 0}
.back-link{display:inline-flex;align-items:center;gap:7px;font-size:.73rem;font-weight:700;color:var(--ink-3);text-decoration:none;letter-spacing:.07em;text-transform:uppercase;transition:color .18s}
.back-link:hover{color:var(--gold-dk)}

.page-wrap{max-width:1340px;margin:0 auto;padding:22px 36px 100px;display:grid;grid-template-columns:256px 1fr;gap:28px;align-items:start}

/* Sidebar */
.sidebar{display:flex;flex-direction:column;gap:12px;position:sticky;top:82px;animation:slideInL .5s .05s cubic-bezier(0.16,1,.3,1) both}
.sb-profile{background:linear-gradient(160deg,var(--navy-2) 0%,var(--navy-4) 100%);border-radius:var(--r-lg);padding:24px 20px 20px;text-align:center;border:1px solid rgba(255,255,255,.06);box-shadow:var(--sh-md);position:relative;overflow:hidden}
.sb-profile::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(201,168,76,.12) 0%,transparent 65%);pointer-events:none}
.sb-av-wrap{position:relative;display:inline-block;margin-bottom:14px}
.sb-av{width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(201,168,76,.5);box-shadow:0 0 0 5px rgba(201,168,76,.1),var(--sh-md)}
.sb-online{position:absolute;bottom:3px;right:3px;width:14px;height:14px;border-radius:50%;background:var(--green-lt);border:2.5px solid var(--navy-2);box-shadow:0 0 6px rgba(18,181,133,.5)}
.sb-name{font-size:.95rem;font-weight:800;color:#fff;margin-bottom:3px;letter-spacing:-.01em}
.sb-email{font-size:.68rem;color:rgba(255,255,255,.38);word-break:break-all;line-height:1.4}
.sb-badge{display:inline-flex;align-items:center;gap:5px;margin-top:12px;font-family:var(--mono);font-size:.6rem;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--gold-lt);background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.22);padding:4px 14px;border-radius:20px}
.sb-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.sb-stat{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-md);padding:13px 10px;text-align:center;box-shadow:var(--sh-xs);transition:transform .2s,box-shadow .2s}
.sb-stat:hover{transform:translateY(-2px);box-shadow:var(--sh-sm)}
.sb-stat .n{font-family:var(--mono);font-size:1.2rem;font-weight:600;display:block;font-variant-numeric:tabular-nums;line-height:1;color:var(--navy)}
.sb-stat .l{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-3);display:block;margin-top:4px}
.sb-stat.c-gold .n{color:var(--gold-dk)}.sb-stat.c-green .n{color:var(--green)}
.sb-nav{background:var(--surface);border:1px solid var(--border-2);border-radius:var(--r-sm);overflow:hidden;box-shadow:var(--sh-sm)}
.sb-nav-item{display:flex;align-items:center;gap:12px;padding:12px 16px;font-size:.85rem;font-weight:600;color:var(--ink-2);text-decoration:none;border-bottom:1px solid var(--border-2);transition:background .18s,color .18s,border-color .18s;position:relative;letter-spacing:.01em}
.sb-nav-item:last-child{border-bottom:none}
.sb-nav-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--gold);transform:scaleX(0);transform-origin:left;transition:transform .18s;border-radius:0 2px 2px 0}
.sb-nav-item:hover{background:var(--cream-2);color:var(--navy)}
.sb-nav-item:hover::before,.sb-nav-item.active::before{transform:scaleX(1)}
.sb-nav-item.active{background:rgba(201,168,76,.1);color:var(--navy);font-weight:800;border-left:3px solid var(--gold)}
.sb-nav-item i{width:18px;text-align:center;font-size:.84rem;color:var(--ink-3);flex-shrink:0;transition:color .18s}
.sb-nav-item:hover i,.sb-nav-item.active i{color:var(--gold)}
/* Search Flights — gold accent row */
.sb-nav-item.search-link{background:rgba(201,168,76,.07);color:var(--gold-dk);font-weight:700;border-left:3px solid rgba(201,168,76,.5)}
.sb-nav-item.search-link i{color:var(--gold-dk)}
.sb-nav-item.search-link:hover{background:rgba(201,168,76,.15);color:var(--navy);border-left-color:var(--gold)}
.sb-logout{display:flex;align-items:center;justify-content:center;gap:9px;padding:12px;background:transparent;border:1.5px solid var(--border);border-radius:var(--r-md);color:var(--ink-3);font-family:var(--sans);font-size:.82rem;font-weight:700;text-decoration:none;transition:all .2s}
.sb-logout:hover{border-color:var(--red);color:var(--red);background:var(--red-bg)}
</style>
<style>
/* Main column */
.main-col{min-width:0;animation:riseIn .5s .1s cubic-bezier(0.16,1,.3,1) both}
.profile-grid{display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start}

/* Identity card */
.id-card{background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border-2);overflow:hidden;box-shadow:var(--sh-md)}
.id-banner{height:96px;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-3) 100%);position:relative;overflow:hidden}
.id-banner::before{content:'✈';position:absolute;right:12px;bottom:-14px;font-size:6rem;opacity:.07;color:#fff;transform:rotate(8deg)}
.id-banner::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--gold-dk),var(--gold-lt))}
.id-av-wrap{padding:0 24px;margin-top:-46px;position:relative;z-index:2}
.id-av{width:92px;height:92px;border-radius:50%;object-fit:cover;border:4px solid var(--surface);box-shadow:var(--sh-md);display:block;transition:transform .3s}
.id-av:hover{transform:scale(1.04)}
.id-body{padding:14px 24px 26px}
.id-name{font-family:var(--serif);font-size:1.25rem;font-weight:700;color:var(--ink);letter-spacing:-.02em;margin-bottom:3px}
.id-email{font-size:.78rem;color:var(--ink-3);margin-bottom:16px}
.id-badge{display:inline-flex;align-items:center;gap:6px;background:var(--gold-tint);color:var(--gold-dk);border:1px solid rgba(201,168,76,.22);padding:5px 14px;border-radius:20px;font-size:.74rem;font-weight:700;margin-bottom:22px}
.id-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:22px}
.id-stat{background:var(--cream-2);border-radius:var(--r-md);padding:14px 12px;border:1px solid var(--border-2);text-align:center;transition:transform .2s,box-shadow .2s}
.id-stat:hover{transform:translateY(-2px);box-shadow:var(--sh-sm)}
.id-stat .sv{font-family:var(--mono);font-size:1.35rem;font-weight:600;color:var(--navy);letter-spacing:-.03em;line-height:1;font-variant-numeric:tabular-nums}
.id-stat .sl{font-size:.67rem;font-weight:700;color:var(--ink-3);text-transform:uppercase;letter-spacing:.06em;margin-top:4px}
.id-links{display:flex;flex-direction:column;gap:8px}
.id-link{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--r-md);text-decoration:none;color:var(--ink-2);border:1px solid var(--border-2);font-size:.84rem;font-weight:600;transition:all .2s;background:var(--surface)}
.id-link:hover{border-color:var(--gold);color:var(--gold-dk);background:var(--gold-tint);transform:translateX(3px)}
.id-link i{width:18px;text-align:center;color:var(--ink-3);transition:color .2s}
.id-link:hover i{color:var(--gold)}
.id-link .il-arrow{margin-left:auto;color:var(--ink-4);font-size:.75rem;transition:transform .2s}
.id-link:hover .il-arrow{transform:translateX(3px);color:var(--gold-dk)}

/* Form card */
.form-card{background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border-2);overflow:hidden;box-shadow:var(--sh-md)}
.form-card-head{padding:22px 28px;border-bottom:1px solid var(--border-2);background:var(--surface-2);position:relative}
.form-card-head::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--navy),var(--gold))}
.form-card-head h3{font-family:var(--serif);font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:3px}
.form-card-head span{font-size:.78rem;color:var(--ink-3)}
.form-card-body{padding:30px}

/* Alert */
.alert{display:flex;align-items:flex-start;gap:11px;padding:14px 18px;border-radius:var(--r-md);font-size:.85rem;font-weight:500;margin-bottom:24px;line-height:1.5}
.alert i{font-size:1rem;flex-shrink:0;margin-top:1px}
.alert-success{background:var(--green-bg);border:1px solid rgba(10,143,106,.22);color:var(--green)}
.alert-error{background:var(--red-bg);border:1px solid rgba(200,41,58,.18);color:var(--red)}

/* Fields */
.field-group{margin-bottom:20px}
.field-group label{display:block;font-size:.7rem;font-weight:700;color:var(--ink-3);text-transform:uppercase;letter-spacing:.09em;margin-bottom:8px}
.field-group input[type="text"],.field-group input[type="email"]{width:100%;padding:13px 16px;border:1.5px solid var(--border);border-radius:var(--r-md);font-size:.93rem;font-family:var(--sans);color:var(--ink);background:var(--cream-2);transition:all .2s;outline:none}
.field-group input:focus{border-color:var(--gold);background:var(--surface);box-shadow:0 0 0 4px var(--gold-tint)}
.field-group input[readonly]{background:var(--cream-3);color:var(--ink-3);cursor:not-allowed;border-color:var(--border-2)}

/* Photo upload */
.photo-zone{border:2px dashed var(--border);border-radius:var(--r-lg);padding:28px 20px;text-align:center;cursor:pointer;transition:all .22s;background:var(--cream-2);position:relative}
.photo-zone:hover{border-color:var(--gold);background:var(--gold-tint)}
.photo-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.pz-icon{font-size:2.2rem;color:var(--ink-4);margin-bottom:10px;display:block;transition:color .2s}
.photo-zone:hover .pz-icon{color:var(--gold-dk)}
.pz-text{font-size:.86rem;font-weight:700;color:var(--ink-2)}
.pz-sub{font-size:.74rem;color:var(--ink-3);margin-top:4px}
.photo-preview{width:76px;height:76px;border-radius:var(--r-md);object-fit:cover;border:2px solid var(--border);margin:14px auto 0;display:none;box-shadow:var(--sh-sm)}

/* Buttons */
.btn-row{display:flex;gap:12px;margin-top:26px}
.btn-save{flex:1;padding:14px;background:var(--navy);color:#fff;border:none;border-radius:var(--r-md);font-size:.93rem;font-weight:700;font-family:var(--sans);cursor:pointer;box-shadow:var(--sh-sm);transition:all .22s;letter-spacing:.02em}
.btn-save:hover{transform:translateY(-2px);background:var(--navy-2);box-shadow:var(--sh-md)}
.btn-reset{padding:14px 26px;background:var(--surface);color:var(--ink-2);border:1.5px solid var(--border);border-radius:var(--r-md);font-size:.93rem;font-weight:600;font-family:var(--sans);cursor:pointer;transition:all .22s}
.btn-reset:hover{border-color:var(--gold);color:var(--gold-dk);background:var(--gold-tint)}

@media(max-width:1100px){.page-wrap{grid-template-columns:1fr}.sidebar{position:static}}
@media(max-width:900px){.profile-grid{grid-template-columns:1fr}}
@media(max-width:780px){.page-wrap{padding:18px 16px 80px}.sub-header{padding:14px 20px}}
</style>
</head>
<body>

<div class="sub-header">
    <div class="sh-icon"><i class="fas fa-user"></i></div>
    <div class="sh-text">
        <h2>My Profile</h2>
        <p>Manage your personal information and photo</p>
    </div>
    <div class="sh-badge">✈ GoZayan Traveller</div>
</div>

<div class="back-bar">
    <a href="userhome.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="page-wrap">
    <aside class="sidebar">
        <div class="sb-profile">
            <div class="sb-av-wrap">
                <img class="sb-av" src="<?= $img_src ?>" alt="<?= htmlspecialchars($user['name']) ?>" id="sidebarAvatar">
                <div class="sb-online"></div>
            </div>
            <div class="sb-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="sb-email"><?= htmlspecialchars($user['email']) ?></div>
            <div class="sb-badge"><i class="fas fa-star" style="font-size:.55rem"></i> GoZayan Traveller</div>
        </div>
        <div class="sb-stats">
            <div class="sb-stat"><span class="n"><?= $booking_count ?></span><span class="l">Bookings</span></div>
            <div class="sb-stat c-gold"><span class="n">$<?= number_format($total_spent,0) ?></span><span class="l">Spent</span></div>
        </div>
        <nav class="sb-nav">
            <a href="userhome.php"         class="sb-nav-item"><i class="fas fa-house"></i> Dashboard</a>
            <a href="searchflights.php"    class="sb-nav-item search-link"><i class="fas fa-magnifying-glass"></i> Search Flights</a>
            <a href="myBookings.php"       class="sb-nav-item"><i class="fas fa-ticket"></i> My Bookings</a>
            <a href="passengerProfile.php" class="sb-nav-item active"><i class="fas fa-user"></i> My Profile</a>
            <a href="changePassword.php"   class="sb-nav-item"><i class="fas fa-lock"></i> Change Password</a>
        </nav>
        <a href="/flight_booking/logout.php" class="sb-logout"><i class="fas fa-right-from-bracket"></i> Sign Out</a>
    </aside>

    <div class="main-col">
        <div class="profile-grid">

            <!-- Identity card -->
            <div class="id-card">
                <div class="id-banner"></div>
                <div class="id-av-wrap">
                    <img class="id-av" src="<?= $img_src ?>" alt="Avatar" id="avatarPreview">
                </div>
                <div class="id-body">
                    <div class="id-name"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="id-email"><?= htmlspecialchars($user['email']) ?></div>
                    <div class="id-badge"><i class="fas fa-plane" style="font-size:.7rem"></i> GoZayan Traveller</div>
                    <div class="id-stats">
                        <div class="id-stat"><div class="sv"><?= $booking_count ?></div><div class="sl">Bookings</div></div>
                        <div class="id-stat"><div class="sv">$<?= number_format($total_spent,0) ?></div><div class="sl">Spent</div></div>
                    </div>
                    <div class="id-links">
                        <a href="myBookings.php" class="id-link"><i class="fas fa-ticket"></i> My Bookings <span class="il-arrow"><i class="fas fa-chevron-right"></i></span></a>
                        <a href="changePassword.php" class="id-link"><i class="fas fa-lock"></i> Change Password <span class="il-arrow"><i class="fas fa-chevron-right"></i></span></a>
                        <a href="searchflights.php" class="id-link"><i class="fas fa-magnifying-glass"></i> Search Flights <span class="il-arrow"><i class="fas fa-chevron-right"></i></span></a>
                    </div>
                </div>
            </div>

            <!-- Edit form -->
            <div class="form-card">
                <div class="form-card-head">
                    <h3>Edit Profile</h3>
                    <span>Update your personal information and photo</span>
                </div>
                <div class="form-card-body">
                    <?php if ($message): ?>
                    <div class="alert alert-<?= $msg_type ?>">
                        <i class="fas fa-<?= $msg_type==='success'?'circle-check':'circle-xmark' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="field-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label>Email Address</label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        </div>
                        <div class="field-group">
                            <label>Profile Photo</label>
                            <div class="photo-zone" id="photoZone">
                                <input type="file" name="image" id="image_upload" accept="image/jpg,image/jpeg,image/png" onchange="previewImage(this)">
                                <span class="pz-icon"><i class="fas fa-camera"></i></span>
                                <div class="pz-text" id="pzText">Click to upload a new photo</div>
                                <div class="pz-sub">JPG or PNG · max 10 MB</div>
                                <img id="imgPreviewBox" class="photo-preview" alt="Preview">
                            </div>
                        </div>
                        <div class="btn-row">
                            <button type="submit" name="save_profile" class="btn-save"><i class="fas fa-floppy-disk"></i> Save Changes</button>
                            <button type="reset" class="btn-reset" onclick="resetPreview()">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function previewImage(input) {
    const preview = document.getElementById('imgPreviewBox');
    const avatar  = document.getElementById('avatarPreview');
    const sbAv    = document.getElementById('sidebarAvatar');
    const pzText  = document.getElementById('pzText');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result; preview.style.display = 'block';
            avatar.src = e.target.result; sbAv.src = e.target.result;
            pzText.textContent = input.files[0].name;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function resetPreview() {
    const preview = document.getElementById('imgPreviewBox');
    const pzText  = document.getElementById('pzText');
    preview.style.display = 'none';
    pzText.textContent = 'Click to upload a new photo';
    document.getElementById('avatarPreview').src = '<?= $img_src ?>';
    document.getElementById('sidebarAvatar').src  = '<?= $img_src ?>';
}
</script>
</body>
</html>
<?php include("../includes/footer.php"); ?>
