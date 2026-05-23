<?php
// Start session and include header FIRST (session_start is inside adminheader)
include("../includes/adminheader.php");
// db_conn is already included by managerheader via include_once, but we ensure it here
include_once("../model/db_conn.php");

$success_message = "";
$error_message   = "";

if (isset($_POST['submit'])) {

    $name     = trim($_POST['name']);
    $age      = (int) $_POST['age'];
    $dob      = $_POST['dob'];
    $phone    = trim($_POST['phone']);
    $gender   = $_POST['gender'];
    $city     = $_POST['city'];
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    // BUG FIX #3: Store hash in a variable — never interpolate bcrypt hashes
    // directly into double-quoted SQL strings ($ signs corrupt the value).
    $password = password_hash($_POST['pass'], PASSWORD_DEFAULT);

    // BUG FIX #1: Check email duplicate BEFORE doing anything else
    $check_stmt = $conn->prepare("SELECT id FROM login WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $error_message = "A user with this email already exists.";
        $check_stmt->close();
    } else {
        $check_stmt->close();

        // Handle image upload
        $profile_image = "";
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = __DIR__ . "/uploads/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $allowed = ['jpg','jpeg','png'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error_message = "Only JPG and PNG images are allowed.";
            } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $error_message = "Image must be under 2MB.";
            } else {
                $profile_image = time() . '_' . basename($_FILES['image']['name']);
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $profile_image)) {
                    $error_message = "Failed to upload image.";
                    $profile_image = "";
                }
            }
        }

        if (empty($error_message)) {
            // BUG FIX #4: Use a transaction so both inserts succeed or both roll back
            $conn->begin_transaction();

            try {
                // BUG FIX #3: Use prepared statement for users INSERT — no string interpolation
                $ins_users = $conn->prepare(
                    "INSERT INTO users (name, age, date_of_birth, phone, gender, city, email, password, role, profile_image)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $ins_users->bind_param(
                    "sissssssss",
                    $name, $age, $dob, $phone, $gender,
                    $city, $email, $password, $role, $profile_image
                );
                $ins_users->execute();
                $ins_users->close();

                // BUG FIX #2: login insert also checked for errors
                $ins_login = $conn->prepare(
                    "INSERT INTO login (email, password, role) VALUES (?, ?, ?)"
                );
                $ins_login->bind_param("sss", $email, $password, $role);
                $ins_login->execute();
                $ins_login->close();

                $conn->commit();
                // BUG FIX #5: Store message in a variable, render it inside the HTML
                $success_message = "User <strong>" . htmlspecialchars($name) . "</strong> added successfully as <strong>" . ucfirst($role) . "</strong>!";

                // Clear POST data so form resets
                $_POST = [];

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Add User</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>
        :root {
            --ink:      #0d1117;
            --ink-soft: #4a5568;
            --blue:     #0b72e6;
            --blue-dk:  #0556b3;
            --blue-lt:  #e8f2ff;
            --green:    #10b981;
            --green-lt: #d1fae5;
            --red:      #ef4444;
            --red-lt:   #fee2e2;
            --border:   #e2e8f0;
            --surface:  #f8fafc;
            --white:    #ffffff;
            --radius:   12px;
            --shadow:   0 4px 24px rgba(11,114,230,.10);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            min-height: 100vh;
        }

        /* ── PAGE WRAPPER ── */
        .adduser-page {
            max-width: 1080px;
            margin: 40px auto 60px;
            padding: 0 24px;
        }

        /* ── PAGE TITLE ── */
        .page-heading {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
        }
        .page-heading .icon-wrap {
            width: 52px; height: 52px;
            background: var(--blue);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: white;
            box-shadow: 0 8px 20px rgba(11,114,230,.3);
        }
        .page-heading h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--ink);
        }
        .page-heading p {
            font-size: .875rem;
            color: var(--ink-soft);
            margin-top: 3px;
        }

        /* ── ALERTS ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            font-size: .9rem;
            font-weight: 500;
            animation: slideDown .3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: var(--green-lt); color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error   { background: var(--red-lt);   color: #991b1b; border: 1px solid #fca5a5; }
        .alert i { font-size: 1.1rem; margin-top: 1px; flex-shrink: 0; }

        /* ── CARD ── */
        .form-card {
            background: var(--white);
            border-radius: 18px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .form-card-header {
            background: linear-gradient(135deg, var(--blue), var(--blue-dk));
            padding: 22px 32px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .form-card-header .step-circle {
            width: 32px; height: 32px;
            border: 2px solid rgba(255,255,255,.4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: .85rem; font-weight: 700;
        }
        .form-card-header h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: white;
        }
        .form-card-header p {
            font-size: .78rem;
            color: rgba(255,255,255,.75);
            margin-top: 2px;
        }

        .form-card-body {
            padding: 32px;
        }

        /* ── SECTION LABEL ── */
        .form-section-label {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--ink-soft);
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section-label i { color: var(--blue); font-size: .85rem; }

        /* ── GRID ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 28px;
        }
        .form-grid.three-col { grid-template-columns: 1fr 1fr 1fr; }
        .form-grid.full      { grid-template-columns: 1fr; }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.span-2 { grid-column: span 2; }

        .form-group label {
            font-size: .78rem;
            font-weight: 600;
            color: var(--ink-soft);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-group label .required { color: var(--red); }

        /* ── INPUTS ── */
        .form-control {
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: .92rem;
            color: var(--ink);
            background: var(--surface);
            transition: border-color .2s, box-shadow .2s, background .2s;
            width: 100%;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--blue);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(11,114,230,.12);
        }
        .form-control::placeholder { color: #a0aec0; }

        select.form-control { cursor: pointer; }
        select.form-control option[value=""] { color: #a0aec0; }

        /* ── FILE INPUT ── */
        .file-upload-wrap {
            position: relative;
        }
        .file-upload-wrap input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 2;
        }
        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            border: 1.5px dashed var(--border);
            border-radius: 10px;
            background: var(--surface);
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .file-upload-wrap:hover .file-upload-label,
        .file-upload-wrap input[type="file"]:focus + .file-upload-label {
            border-color: var(--blue);
            background: var(--blue-lt);
        }
        .file-upload-label .up-icon {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: var(--blue-lt);
            display: flex; align-items: center; justify-content: center;
            color: var(--blue); font-size: .95rem; flex-shrink: 0;
        }
        .file-upload-label .up-text { font-size: .88rem; color: var(--ink-soft); }
        .file-upload-label .up-text strong { display: block; color: var(--ink); font-size: .9rem; }
        .file-preview {
            display: none;
            margin-top: 10px;
            align-items: center;
            gap: 12px;
        }
        .file-preview.show { display: flex; }
        .file-preview img {
            width: 56px; height: 56px;
            border-radius: 10px; object-fit: cover;
            border: 2px solid var(--border);
        }
        .file-preview .fname {
            font-size: .82rem; color: var(--ink-soft);
        }
        .file-preview .fname strong { display: block; color: var(--ink); }

        /* ── ROLE CARDS ── */
        .role-cards {
            display: flex;
            gap: 14px;
        }
        .role-card {
            flex: 1;
            position: relative;
            cursor: pointer;
        }
        .role-card input[type="radio"] {
            position: absolute; opacity: 0; width: 0; height: 0;
        }
        .role-card-inner {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            transition: all .2s;
            background: var(--surface);
        }
        .role-card:hover .role-card-inner {
            border-color: var(--blue);
            background: var(--blue-lt);
        }
        .role-card input[type="radio"]:checked + .role-card-inner {
            border-color: var(--blue);
            background: var(--blue-lt);
            box-shadow: 0 0 0 3px rgba(11,114,230,.12);
        }
        .role-card-inner .role-icon {
            font-size: 1.6rem;
            margin-bottom: 8px;
            display: block;
        }
        .role-card-inner .role-name {
            font-weight: 700;
            font-size: .9rem;
            color: var(--ink);
        }
        .role-card-inner .role-desc {
            font-size: .75rem;
            color: var(--ink-soft);
            margin-top: 3px;
        }
        .role-check {
            position: absolute;
            top: 10px; right: 10px;
            width: 20px; height: 20px;
            border: 2px solid var(--border);
            border-radius: 50%;
            background: white;
            transition: all .2s;
            display: flex; align-items: center; justify-content: center;
        }
        .role-card input[type="radio"]:checked ~ .role-check {
            background: var(--blue);
            border-color: var(--blue);
        }
        .role-card input[type="radio"]:checked ~ .role-check::after {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: white;
        }

        /* Password strength */
        .password-wrap { position: relative; }
        .password-wrap .toggle-pass {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--ink-soft); cursor: pointer;
            font-size: .95rem; padding: 4px;
        }
        .strength-bar {
            height: 3px;
            border-radius: 3px;
            margin-top: 6px;
            background: var(--border);
            overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%;
            width: 0;
            border-radius: 3px;
            transition: width .3s, background .3s;
        }
        .strength-text {
            font-size: .72rem;
            color: var(--ink-soft);
            margin-top: 4px;
        }

        /* ── SUBMIT ── */
        .form-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            margin-top: 4px;
        }
        .btn-reset {
            padding: 11px 24px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: white;
            color: var(--ink-soft);
            font-family: 'DM Sans', sans-serif;
            font-size: .92rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }
        .btn-reset:hover { border-color: #999; color: var(--ink); }

        .btn-submit {
            padding: 11px 32px;
            border: none;
            border-radius: 10px;
            background: var(--blue);
            color: white;
            font-family: 'DM Sans', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(11,114,230,.35);
        }
        .btn-submit:hover {
            background: var(--blue-dk);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(11,114,230,.4);
        }
        .btn-submit:active { transform: translateY(0); }

        /* ── RESPONSIVE ── */
        @media (max-width: 700px) {
            .form-grid, .form-grid.three-col { grid-template-columns: 1fr; }
            .form-group.span-2 { grid-column: span 1; }
            .role-cards { flex-direction: column; }
            .form-card-body { padding: 20px; }
            .form-footer { flex-direction: column-reverse; }
            .btn-reset, .btn-submit { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="adduser-page">

    <!-- Heading -->
    <div class="page-heading">
        <div class="icon-wrap"><i class="fas fa-user-plus"></i></div>
        <div>
            <h1>Add New User</h1>
            <p>Create an admin or manager account for the GoZayan panel.</p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check"></i>
            <span><?= $success_message ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-triangle-exclamation"></i>
            <span><?= htmlspecialchars($error_message) ?></span>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-card-header">
            <div class="step-circle"><i class="fas fa-user-tie"></i></div>
            <div>
                <h2>User Information</h2>
                <p>All fields marked with * are required</p>
            </div>
        </div>

        <div class="form-card-body">
            <form action="" method="POST" enctype="multipart/form-data" id="addUserForm">

                <!-- PERSONAL DETAILS -->
                <div class="form-section-label">
                    <i class="fas fa-id-card"></i> Personal Details
                </div>
                <div class="form-grid three-col">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" class="form-control"
                               placeholder="e.g. Ayesha Rahman"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="age">Age <span class="required">*</span></label>
                        <input type="number" name="age" id="age" class="form-control"
                               placeholder="e.g. 30" min="18" max="80"
                               value="<?= htmlspecialchars($_POST['age'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="dob">Date of Birth <span class="required">*</span></label>
                        <input type="date" name="dob" id="dob" class="form-control"
                               value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <select name="gender" id="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male"   <?= (($_POST['gender'] ?? '') === 'Male')   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            <option value="Other"  <?= (($_POST['gender'] ?? '') === 'Other')  ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="city">City <span class="required">*</span></label>
                        <select name="city" id="city" class="form-control" required>
                            <option value="">Select City</option>
                            <?php foreach (['Dhaka','Chittagong','Khulna','Rajshahi','Sylhet'] as $c): ?>
                                <option value="<?= $c ?>" <?= (($_POST['city'] ?? '') === $c) ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- CONTACT -->
                <div class="form-section-label" style="margin-top:8px;">
                    <i class="fas fa-address-book"></i> Contact & Access
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" class="form-control"
                               placeholder="01XXXXXXXXX (11 digits)"
                               pattern="[0-9]{11}" title="Enter 11 digit number"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" class="form-control"
                               placeholder="user@example.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-grid full" style="margin-bottom:28px;">
                    <div class="form-group">
                        <label for="pass">Password <span class="required">*</span></label>
                        <div class="password-wrap">
                            <input type="password" name="pass" id="pass" class="form-control"
                                   placeholder="Minimum 6 characters" required
                                   style="padding-right:42px;"
                                   oninput="checkStrength(this.value)">
                            <button type="button" class="toggle-pass" onclick="togglePass()" tabindex="-1">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <div class="strength-bar"><div class="strength-bar-fill" id="strengthFill"></div></div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                </div>

                <!-- ROLE -->
                <div class="form-section-label">
                    <i class="fas fa-shield-halved"></i> Role Assignment <span class="required">*</span>
                </div>
                <div class="role-cards" style="margin-bottom:28px;">
                    <label class="role-card">
                        <input type="radio" name="role" value="admin"
                               <?= (($_POST['role'] ?? '') === 'admin') ? 'checked' : '' ?> required>
                        <div class="role-card-inner">
                            <span class="role-icon">🛡️</span>
                            <div class="role-name">Admin</div>
                            <div class="role-desc">Full system access</div>
                        </div>
                        <div class="role-check"></div>
                    </label>
                    <label class="role-card">
                        <input type="radio" name="role" value="manager"
                               <?= (($_POST['role'] ?? '') === 'manager') ? 'checked' : '' ?>>
                        <div class="role-card-inner">
                            <span class="role-icon">✈️</span>
                            <div class="role-name">Manager</div>
                            <div class="role-desc">Flight & schedule management</div>
                        </div>
                        <div class="role-check"></div>
                    </label>
                </div>

                <!-- PROFILE IMAGE -->
                <div class="form-section-label">
                    <i class="fas fa-image"></i> Profile Photo <span class="required">*</span>
                </div>
                <div class="form-group" style="margin-bottom:28px;">
                    <div class="file-upload-wrap">
                        <input type="file" name="image" id="image"
                               accept="image/jpg,image/jpeg,image/png"
                               required onchange="previewFile(this)">
                        <div class="file-upload-label">
                            <div class="up-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                            <div class="up-text">
                                <strong>Click to upload photo</strong>
                                JPG or PNG, max 2MB
                            </div>
                        </div>
                    </div>
                    <div class="file-preview" id="filePreview">
                        <img id="previewImg" src="" alt="Preview">
                        <div class="fname">
                            <strong id="previewName"></strong>
                            <span id="previewSize"></span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="form-footer">
                    <button type="reset" class="btn-reset" onclick="resetPreview()">
                        <i class="fas fa-rotate-left"></i> Clear Form
                    </button>
                    <button type="submit" name="submit" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
/* Password visibility toggle */
function togglePass() {
    const p = document.getElementById('pass');
    const i = document.getElementById('eyeIcon');
    if (p.type === 'password') { p.type = 'text'; i.className = 'fas fa-eye-slash'; }
    else                       { p.type = 'password'; i.className = 'fas fa-eye'; }
}

/* Password strength meter */
function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { w:'0%',   bg:'transparent', t:'' },
        { w:'25%',  bg:'#ef4444', t:'Weak' },
        { w:'50%',  bg:'#f97316', t:'Fair' },
        { w:'75%',  bg:'#eab308', t:'Good' },
        { w:'90%',  bg:'#10b981', t:'Strong' },
        { w:'100%', bg:'#059669', t:'Very Strong' },
    ];
    const l = levels[Math.min(score, 5)];
    fill.style.width = l.w;
    fill.style.background = l.bg;
    text.textContent = l.t;
    text.style.color = l.bg;
}

/* File preview */
function previewFile(input) {
    const preview = document.getElementById('filePreview');
    const img     = document.getElementById('previewImg');
    const name    = document.getElementById('previewName');
    const size    = document.getElementById('previewSize');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            name.textContent = file.name;
            size.textContent = (file.size / 1024).toFixed(1) + ' KB';
            preview.classList.add('show');
        };
        reader.readAsDataURL(file);
    }
}

function resetPreview() {
    document.getElementById('filePreview').classList.remove('show');
    document.getElementById('previewImg').src = '';
    document.getElementById('strengthFill').style.width = '0';
    document.getElementById('strengthText').textContent = '';
}
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?>