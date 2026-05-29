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
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (isset($_POST['save_profile'])) {
    $new_name = trim($_POST['name'] ?? '');
    $errors = [];
    if (empty($new_name)) $errors[] = "Name cannot be empty.";

    $new_image = $user['image'];
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['image/jpeg','image/jpg','image/png'];
        if (!in_array($_FILES['image']['type'], $allowed)) {
            $errors[] = "Only JPG and PNG images allowed.";
        } elseif ($_FILES['image']['size'] > 10 * 1024 * 1024) {
            $errors[] = "Image must be less than 10MB.";
        } else {
            $upload_dir = __DIR__ . "/uploads/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $new_image = time() . '_' . basename($_FILES['image']['name']);
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_image)) {
                $errors[] = "Failed to upload image.";
                $new_image = $user['image'];
            }
        }
    }

    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE webusers SET name = ?, image = ? WHERE email = ?");
        $upd->bind_param("sss", $new_name, $new_image, $email);
        if ($upd->execute()) {
            $message = "Profile updated successfully!";
            $msg_type = 'success';
            $stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $message = "Error updating profile.";
            $msg_type = 'error';
        }
        $upd->close();
    } else {
        $message = implode(' ', $errors);
        $msg_type = 'error';
    }
}

$cnt   = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE user_id = {$user['id']}")->fetch_assoc();
$spent = $conn->query("SELECT SUM(total_price) as s FROM bookings WHERE user_id = {$user['id']} AND status='confirmed'")->fetch_assoc();
$booking_count = $cnt['c'];
$total_spent   = $spent['s'] ?? 0;

$img_src = "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=1a6ff4&color=fff&size=200";
if (!empty($user['image']) && file_exists(__DIR__ . "/uploads/" . $user['image'])) {
    $img_src = "uploads/" . htmlspecialchars($user['image']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | My Profile</title>
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

        /* ══ LAYOUT ══ */
        .dashboard{display:flex;flex:1}

        /* ══ SIDEBAR ══ */
        .sidebar{width:var(--sidebar-w);flex-shrink:0;
            background:linear-gradient(180deg,var(--secondary) 0%,#0d1f35 100%);
            display:flex;flex-direction:column;
            position:sticky;top:0;height:100vh;overflow-y:auto;z-index:100}
        .sidebar-brand{padding:28px 24px 20px;font-size:1.4rem;font-weight:900;
            color:#fff;letter-spacing:-0.5px;border-bottom:1px solid rgba(255,255,255,0.08)}
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

        /* ══ MAIN ══ */
        .main{flex:1;display:flex;flex-direction:column;min-width:0}
        .topbar{background:var(--surface);border-bottom:1px solid var(--border);
            padding:16px 32px;display:flex;align-items:center;
            justify-content:space-between;position:sticky;top:0;z-index:10}
        .topbar-title{font-size:1rem;font-weight:800;color:var(--dark)}
        .topbar-back{font-size:0.85rem;font-weight:600;color:var(--muted);
            text-decoration:none;transition:color 0.2s}
        .topbar-back:hover{color:var(--primary)}
    </style>
</head>
<body>
    <style>
        /* ══ PAGE CONTENT ══ */
        .page-content{padding:32px;flex:1}

        /* Two-column grid */
        .profile-grid{display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start}

        /* ── LEFT: Identity card ── */
        .identity-card{background:var(--surface);border-radius:20px;
            border:1px solid var(--border);overflow:hidden;
            box-shadow:0 4px 20px rgba(13,31,53,0.07)}

        .id-banner{height:90px;
            background:linear-gradient(135deg,var(--secondary) 0%,var(--primary) 100%);
            position:relative}
        .id-banner::after{content:'✈';position:absolute;right:16px;bottom:-10px;
            font-size:5rem;opacity:0.08;color:#fff}

        .id-avatar-wrap{padding:0 24px;margin-top:-44px;position:relative;z-index:2}
        .id-avatar{width:88px;height:88px;border-radius:50%;object-fit:cover;
            border:4px solid var(--surface);
            box-shadow:0 4px 16px rgba(13,31,53,0.15);display:block}

        .id-body{padding:14px 24px 24px}
        .id-name{font-size:1.2rem;font-weight:800;color:var(--dark);
            letter-spacing:-0.4px;margin-bottom:3px}
        .id-email{font-size:0.78rem;color:var(--muted);margin-bottom:14px}
        .id-badge{display:inline-flex;align-items:center;gap:6px;
            background:rgba(26,111,244,0.08);color:var(--primary);
            border:1px solid rgba(26,111,244,0.18);
            padding:5px 14px;border-radius:20px;font-size:0.75rem;font-weight:700;
            margin-bottom:22px}

        /* Stat tiles inside card */
        .id-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:22px}
        .id-stat{background:var(--bg);border-radius:12px;padding:14px 12px;
            border:1px solid var(--border);text-align:center}
        .id-stat .sv{font-size:1.3rem;font-weight:900;color:var(--primary);
            letter-spacing:-0.5px;line-height:1}
        .id-stat .sl{font-size:0.68rem;font-weight:600;color:var(--muted);
            text-transform:uppercase;letter-spacing:0.5px;margin-top:4px}

        /* Quick nav links */
        .id-links{display:flex;flex-direction:column;gap:8px}
        .id-link{display:flex;align-items:center;gap:12px;padding:11px 14px;
            border-radius:11px;text-decoration:none;color:var(--mid);
            border:1px solid var(--border);font-size:0.85rem;font-weight:600;
            transition:all 0.2s;background:var(--surface)}
        .id-link:hover{border-color:var(--primary);color:var(--primary);
            background:rgba(26,111,244,0.04);transform:translateX(3px)}
        .id-link .il-icon{font-size:1rem;width:20px;text-align:center}
        .id-link .il-arrow{margin-left:auto;color:var(--muted);font-size:0.8rem}
        .id-link:hover .il-arrow{color:var(--primary)}

        /* ── RIGHT: Edit form ── */
        .form-card{background:var(--surface);border-radius:20px;
            border:1px solid var(--border);overflow:hidden;
            box-shadow:0 4px 20px rgba(13,31,53,0.07)}

        .form-card-head{padding:20px 28px;border-bottom:1px solid var(--border);
            display:flex;align-items:center;justify-content:space-between}
        .form-card-head h3{font-size:1rem;font-weight:800;color:var(--dark)}
        .form-card-head span{font-size:0.78rem;color:var(--muted)}

        .form-card-body{padding:28px}

        /* Alert */
        .alert{display:flex;align-items:center;gap:10px;padding:12px 16px;
            border-radius:11px;font-size:0.85rem;font-weight:500;margin-bottom:22px}
        .alert-success{background:rgba(6,200,160,0.08);border:1px solid rgba(6,200,160,0.25);color:#047857}
        .alert-error{background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.22);color:#dc2626}

        /* Form fields */
        .field-group{margin-bottom:18px}
        .field-group label{display:block;font-size:0.72rem;font-weight:700;
            color:var(--muted);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:7px}
        .field-group input[type="text"],
        .field-group input[type="email"]{
            width:100%;padding:12px 16px;border:1.5px solid var(--border);
            border-radius:11px;font-size:0.93rem;font-family:'Inter',sans-serif;
            color:var(--dark);background:#f8fbff;transition:all 0.2s;outline:none}
        .field-group input:focus{border-color:var(--primary);background:#fff;
            box-shadow:0 0 0 3.5px rgba(26,111,244,0.12)}
        .field-group input[readonly]{background:#f0f4fb;color:var(--muted);cursor:not-allowed}

        /* Photo upload zone */
        .photo-zone{border:2px dashed var(--border);border-radius:14px;
            padding:24px 20px;text-align:center;cursor:pointer;
            transition:all 0.2s;background:#f8fbff;position:relative}
        .photo-zone:hover{border-color:var(--primary);background:rgba(26,111,244,0.03)}
        .photo-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .photo-zone .pz-icon{font-size:2rem;margin-bottom:8px;display:block}
        .photo-zone .pz-text{font-size:0.85rem;font-weight:600;color:var(--mid)}
        .photo-zone .pz-sub{font-size:0.75rem;color:var(--muted);margin-top:3px}
        .photo-preview{width:72px;height:72px;border-radius:12px;object-fit:cover;
            border:2px solid var(--border);margin:12px auto 0;display:none;
            box-shadow:0 3px 10px rgba(13,31,53,0.1)}

        /* Buttons */
        .btn-row{display:flex;gap:12px;margin-top:24px}
        .btn-save{flex:1;padding:13px;
            background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            color:#fff;border:none;border-radius:12px;font-size:0.93rem;font-weight:700;
            font-family:'Inter',sans-serif;cursor:pointer;
            box-shadow:0 4px 14px var(--primary-glow);transition:all 0.22s}
        .btn-save:hover{transform:translateY(-2px);filter:brightness(1.06)}
        .btn-reset{padding:13px 24px;background:var(--surface);color:var(--mid);
            border:1.5px solid var(--border);border-radius:12px;font-size:0.93rem;
            font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.22s}
        .btn-reset:hover{border-color:var(--primary);color:var(--primary)}

        /* Responsive */
        @media(max-width:900px){.profile-grid{grid-template-columns:1fr}}
        @media(max-width:768px){
            .sidebar{display:none}
            .page-content{padding:20px 16px}
            .topbar{padding:14px 16px}
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
            gap: 12px;
            margin: 4px 0;
        }
        .social-icons a {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff !important;
            font-size: 1rem;
            transition: all 0.25s;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .social-icons a:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 111, 244, 0.35);
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

<div class="dashboard">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand"><a href="home.php">Go<span>Zayan</span></a></div>
        <div class="sidebar-profile">
            <img class="profile-avatar" src="<?= $img_src ?>" alt="">
            <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="userhome.php"      class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="searchflights.php" class="nav-item"><span class="nav-icon">🔍</span> Search Flights</a>
            <a href="myBookings.php"    class="nav-item"><span class="nav-icon">🎫</span> My Bookings</a>
            <div class="nav-label">Account</div>
            <a href="passengerProfile.php" class="nav-item active"><span class="nav-icon">👤</span> My Profile</a>
            <a href="changePassword.php"   class="nav-item"><span class="nav-icon">🔒</span> Change Password</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main">
        <div class="topbar">
            <div class="topbar-title">👤 My Profile</div>
            <a href="userhome.php" class="topbar-back">← Back to Dashboard</a>
        </div>

        <div class="page-content">
            <div class="profile-grid">

                <!-- LEFT: Identity card -->
                <div class="identity-card">
                    <div class="id-banner"></div>
                    <div class="id-avatar-wrap">
                        <img class="id-avatar" src="<?= $img_src ?>" alt="Avatar" id="avatarPreview">
                    </div>
                    <div class="id-body">
                        <div class="id-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="id-email"><?= htmlspecialchars($user['email']) ?></div>
                        <div class="id-badge">✈️ GoZayan Traveller</div>

                        <div class="id-stats">
                            <div class="id-stat">
                                <div class="sv"><?= $booking_count ?></div>
                                <div class="sl">Bookings</div>
                            </div>
                            <div class="id-stat">
                                <div class="sv">$<?= number_format($total_spent, 0) ?></div>
                                <div class="sl">Total Spent</div>
                            </div>
                        </div>

                        <div class="id-links">
                            <a href="myBookings.php" class="id-link">
                                <span class="il-icon">🎫</span> My Bookings
                                <span class="il-arrow">›</span>
                            </a>
                            <a href="changePassword.php" class="id-link">
                                <span class="il-icon">🔒</span> Change Password
                                <span class="il-arrow">›</span>
                            </a>
                            <a href="searchflights.php" class="id-link">
                                <span class="il-icon">🔍</span> Search Flights
                                <span class="il-arrow">›</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Edit form -->
                <div class="form-card">
                    <div class="form-card-head">
                        <h3>Edit Profile</h3>
                        <span>Update your personal information</span>
                    </div>
                    <div class="form-card-body">

                        <?php if ($message): ?>
                        <div class="alert alert-<?= $msg_type ?>">
                            <?= $msg_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>

                        <form action="" method="POST" enctype="multipart/form-data">

                            <div class="field-group">
                                <label>Full Name</label>
                                <input type="text" name="name"
                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>

                            <div class="field-group">
                                <label>Email Address</label>
                                <input type="email"
                                       value="<?= htmlspecialchars($user['email']) ?>" readonly>
                            </div>

                            <div class="field-group">
                                <label>Profile Photo</label>
                                <div class="photo-zone" id="photoZone">
                                    <input type="file" name="image" id="image_upload"
                                           accept="image/jpg,image/jpeg,image/png"
                                           onchange="previewImage(this)">
                                    <span class="pz-icon">📷</span>
                                    <div class="pz-text" id="pzText">Click to upload a new photo</div>
                                    <div class="pz-sub">JPG or PNG, max 10MB</div>
                                    <img id="imgPreviewBox" class="photo-preview" alt="Preview">
                                </div>
                            </div>

                            <div class="btn-row">
                                <button type="submit" name="save_profile" class="btn-save">💾 Save Changes</button>
                                <button type="reset" class="btn-reset" onclick="resetPreview()">Reset</button>
                            </div>

                        </form>
                    </div>
                </div>

            </div><!-- /profile-grid -->
        </div><!-- /page-content -->
    </div><!-- /main -->
</div><!-- /dashboard -->

<script>
function previewImage(input) {
    const preview  = document.getElementById('imgPreviewBox');
    const avatar   = document.getElementById('avatarPreview');
    const pzText   = document.getElementById('pzText');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            avatar.src = e.target.result;
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
}
</script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
