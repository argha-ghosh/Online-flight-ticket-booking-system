<?php
session_start();
include("../model/db_conn.php");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'webuser') {
    header("Location: login.php"); exit;
}

$email = $_SESSION['email'];
$message = '';
$msg_type = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if (isset($_POST['save_profile'])) {
    $new_name = trim($_POST['name'] ?? '');
    $errors = [];

    if (empty($new_name)) $errors[] = "Name cannot be empty.";

    $new_image = $user['image'];
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($_FILES['image']['type'], $allowed)) {
            $errors[] = "Only JPG and PNG images allowed.";
        } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image must be less than 2MB.";
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
            $message = "✅ Profile updated successfully!";
            $msg_type = 'success';
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM webusers WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $message = "❌ Error updating profile.";
            $msg_type = 'error';
        }
        $upd->close();
    } else {
        $message = implode(' ', $errors);
        $msg_type = 'error';
    }
}

// Booking count for this user
$cnt = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE user_id = {$user['id']}")->fetch_assoc();
$booking_count = $cnt['c'];
$spent = $conn->query("SELECT SUM(total_price) as s FROM bookings WHERE user_id = {$user['id']} AND status='confirmed'")->fetch_assoc();
$total_spent = $spent['s'] ?? 0;

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | My Profile</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }

        .page-top {
            background: linear-gradient(135deg, #0b72e6, #0556b3);
            height: 160px; position: relative;
        }

        .profile-wrapper { max-width: 800px; margin: 0 auto; padding: 0 20px 50px; }

        /* PROFILE CARD */
        .profile-card {
            background: white; border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-top: -70px; position: relative; z-index: 5; overflow: hidden;
        }

        .profile-top {
            padding: 30px; display: flex; align-items: flex-end; gap: 20px;
            border-bottom: 1px solid #f0f0f0; flex-wrap: wrap;
        }

        .avatar-wrapper { position: relative; }
        .avatar-img {
            width: 110px; height: 110px; border-radius: 50%;
            border: 4px solid white; object-fit: cover;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .avatar-edit-btn {
            position: absolute; bottom: 5px; right: 5px;
            background: #0b72e6; color: white; border: none;
            width: 28px; height: 28px; border-radius: 50%;
            font-size: 0.75rem; cursor: pointer; border: 2px solid white;
        }

        .profile-name-section { flex: 1; min-width: 200px; padding-bottom: 5px; }
        .profile-name-section h2 { font-size: 1.5rem; color: #222; margin-bottom: 4px; }
        .profile-name-section .email-tag { color: #888; font-size: 0.88rem; }
        .profile-name-section .member-badge {
            display: inline-block; background: #e8f2ff; color: #0b72e6;
            padding: 3px 12px; border-radius: 20px; font-size: 0.78rem;
            font-weight: 600; margin-top: 6px;
        }

        /* STATS */
        .profile-stats {
            display: flex; gap: 0; border-bottom: 1px solid #f0f0f0;
        }
        .stat-item {
            flex: 1; text-align: center; padding: 18px;
            border-right: 1px solid #f0f0f0;
        }
        .stat-item:last-child { border-right: none; }
        .stat-item .num { font-size: 1.4rem; font-weight: bold; color: #0b72e6; }
        .stat-item .lbl { font-size: 0.75rem; color: #aaa; margin-top: 3px; }

        /* EDIT FORM */
        .edit-section { padding: 25px 30px; }
        .edit-section h3 { font-size: 1rem; color: #333; margin-bottom: 20px; border-left: 3px solid #0b72e6; padding-left: 10px; }

        .alert {
            padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 0.9rem;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-size: 0.78rem; font-weight: 600; color: #777; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.4px; }
        .form-group input {
            padding: 11px 14px; border: 1.5px solid #e0e0e0;
            border-radius: 8px; font-size: 0.95rem; transition: border 0.2s;
        }
        .form-group input:focus { border-color: #0b72e6; outline: none; }
        .form-group input[readonly] { background: #f9f9f9; color: #888; }

        .form-group input[type="file"] {
            padding: 8px 12px; border: 1.5px dashed #ccc;
            border-radius: 8px; font-size: 0.85rem; cursor: pointer;
        }

        .preview-img {
            width: 70px; height: 70px; border-radius: 10px;
            object-fit: cover; border: 2px solid #e0e0e0;
            margin-top: 8px; display: none;
        }

        .btn-group { display: flex; gap: 12px; margin-top: 20px; }
        .btn-save {
            background: #0b72e6; color: white; border: none;
            padding: 12px 30px; border-radius: 8px; font-size: 0.95rem;
            font-weight: bold; cursor: pointer; transition: background 0.3s;
        }
        .btn-save:hover { background: #0556b3; }
        .btn-reset {
            background: white; color: #666; border: 1.5px solid #ddd;
            padding: 12px 25px; border-radius: 8px; font-size: 0.95rem;
            font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-reset:hover { border-color: #999; color: #333; }

        /* QUICK LINKS */
        .quick-links { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }
        .quick-link {
            flex: 1; min-width: 140px; padding: 14px; background: white;
            border-radius: 10px; text-decoration: none; text-align: center;
            color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-top: 3px solid #0b72e6; transition: all 0.3s;
        }
        .quick-link:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(11,114,230,0.15); }
        .quick-link .ql-icon { font-size: 1.6rem; margin-bottom: 5px; }
        .quick-link .ql-label { font-size: 0.82rem; font-weight: 600; color: #0b72e6; }

        @media (max-width: 550px) {
            .form-grid { grid-template-columns: 1fr; }
            .profile-top { flex-direction: column; align-items: center; text-align: center; }
        }
    </style>
</head>
<body>

<div class="page-top"></div>

<div class="profile-wrapper">
    <div class="profile-card">
        <!-- PROFILE TOP -->
        <div class="profile-top">
            <div class="avatar-wrapper">
                <?php
                $img_src = "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=0b72e6&color=fff&size=200";
                if (!empty($user['image']) && file_exists(__DIR__ . "/uploads/" . $user['image'])) {
                    $img_src = "uploads/" . htmlspecialchars($user['image']);
                }
                ?>
                <img src="<?= $img_src ?>" alt="Profile" class="avatar-img" id="avatarPreview">
                <label for="image_upload" class="avatar-edit-btn" title="Change photo">✏️</label>
            </div>
            <div class="profile-name-section">
                <h2><?= htmlspecialchars($user['name']) ?></h2>
                <div class="email-tag">📧 <?= htmlspecialchars($user['email']) ?></div>
                <span class="member-badge">✈️ GoZayan Traveller</span>
            </div>
        </div>

        <!-- STATS -->
        <div class="profile-stats">
            <div class="stat-item">
                <div class="num"><?= $booking_count ?></div>
                <div class="lbl">Total Bookings</div>
            </div>
            <div class="stat-item">
                <div class="num">৳<?= number_format($total_spent, 0) ?></div>
                <div class="lbl">Total Spent</div>
            </div>
            <div class="stat-item">
                <div class="num">✈️</div>
                <div class="lbl">Frequent Flyer</div>
            </div>
        </div>

        <!-- EDIT FORM -->
        <div class="edit-section">
            <h3>Edit Profile</h3>

            <?php if ($message): ?>
                <div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email (cannot change)</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                    </div>
                    <div class="form-group full">
                        <label>Profile Photo</label>
                        <input type="file" name="image" id="image_upload" accept="image/jpg,image/jpeg,image/png" onchange="previewImage(this)">
                        <img id="imgPreviewBox" class="preview-img" alt="Preview">
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" name="save_profile" class="btn-save">💾 Save Changes</button>
                    <button type="reset" class="btn-reset">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- QUICK LINKS -->
    <div class="quick-links">
        <a href="passengerHome.php" class="quick-link">
            <div class="ql-icon">🔍</div>
            <div class="ql-label">Search Flights</div>
        </a>
        <a href="myBookings.php" class="quick-link">
            <div class="ql-icon">🎫</div>
            <div class="ql-label">My Bookings</div>
        </a>
        <a href="userhome.php" class="quick-link">
            <div class="ql-icon">🏠</div>
            <div class="ql-label">Dashboard</div>
        </a>
        <a href="login.php?logout=1" class="quick-link">
            <div class="ql-icon">🚪</div>
            <div class="ql-label">Logout</div>
        </a>
    </div>
</div>

<script>
function previewImage(input) {
    const preview = document.getElementById('imgPreviewBox');
    const avatar = document.getElementById('avatarPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            avatar.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?>