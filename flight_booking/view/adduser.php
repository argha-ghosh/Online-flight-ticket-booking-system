<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once("../model/db_conn.php");

if (isset($_POST['submit'])) {
    $name     = trim($_POST['name']);
    $age      = (int)$_POST['age'];
    $dob      = $_POST['dob'];
    $phone    = trim($_POST['phone']);
    $gender   = $_POST['gender'];
    $city     = $_POST['city'];
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $password = password_hash($_POST['pass'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM login WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        $_SESSION['user_msg']      = "A user with this email already exists.";
        $_SESSION['user_msg_type'] = "error";
    } else {
        $check->close();
        $profile_image = "";
        $err = "";

        if (!empty($_FILES['image']['name'])) {
            $upload_dir = __DIR__ . "/uploads/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'])) {
                $err = "Only JPG and PNG images are allowed.";
            } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $err = "Image must be under 2MB.";
            } else {
                $profile_image = time() . '_' . basename($_FILES['image']['name']);
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $profile_image)) {
                    $err = "Failed to upload image."; $profile_image = "";
                }
            }
        }

        if (empty($err)) {
            $conn->begin_transaction();
            try {
                $s1 = $conn->prepare("INSERT INTO users (name, age, date_of_birth, phone, gender, city, email, password, role, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $s1->bind_param("sissssssss", $name, $age, $dob, $phone, $gender, $city, $email, $password, $role, $profile_image);
                $s1->execute(); $s1->close();
                $s2 = $conn->prepare("INSERT INTO login (email, password, role) VALUES (?, ?, ?)");
                $s2->bind_param("sss", $email, $password, $role);
                $s2->execute(); $s2->close();
                $conn->commit();
                $_SESSION['user_msg']      = "User " . htmlspecialchars($name) . " added as " . ucfirst($role) . ".";
                $_SESSION['user_msg_type'] = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['user_msg']      = "Database error: " . $e->getMessage();
                $_SESSION['user_msg_type'] = "error";
            }
        } else {
            $_SESSION['user_msg']      = $err;
            $_SESSION['user_msg_type'] = "error";
        }
    }
    header("Location: /flight_booking/view/adduser.php"); exit;
}

$flash_msg  = $_SESSION['user_msg']      ?? '';
$flash_type = $_SESSION['user_msg_type'] ?? '';
unset($_SESSION['user_msg'], $_SESSION['user_msg_type']);

if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $sel = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $sel->bind_param("i", $delete_id);
    $sel->execute();
    $sel->bind_result($del_email);
    if ($sel->fetch()) {
        $sel->close();
        $conn->begin_transaction();
        try {
            $d1 = $conn->prepare("DELETE FROM users WHERE id = ?");
            $d1->bind_param("i", $delete_id); $d1->execute(); $d1->close();
            $d2 = $conn->prepare("DELETE FROM login WHERE email = ?");
            $d2->bind_param("s", $del_email); $d2->execute(); $d2->close();
            $conn->commit();
            $_SESSION['user_msg'] = 'User removed.';
            $_SESSION['user_msg_type'] = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['user_msg'] = 'Error: ' . $e->getMessage();
            $_SESSION['user_msg_type'] = 'error';
        }
    } else { $sel->close(); }
    header("Location: /flight_booking/view/adduser.php"); exit;
}

include("../includes/adminheader.php");

$users = [];
$uq = $conn->query("SELECT id, name, email, role, city, phone FROM users ORDER BY id DESC");
if ($uq) while ($r = $uq->fetch_assoc()) $users[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add User — GoZayan Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Geist:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ─── RESET & BASE ─────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:       #f9f8f6;
    --surface:  #ffffff;
    --ink:      #1a1814;
    --ink-2:    #5c574f;
    --ink-3:    #9e9890;
    --rule:     #ebe8e3;
    --blue:     #1a56db;
    --blue-bg:  #eff4ff;
    --green:    #057a55;
    --green-bg: #f3faf7;
    --red:      #c81e1e;
    --red-bg:   #fdf2f2;
    --gold:     #b45309;
    --r:        6px;
    --shadow:   0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05);
}

body {
    font-family: 'Geist', 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    font-size: 14px;
    line-height: 1.5;
}

/* ─── PAGE SHELL ────────────────────────────────── */
.page {
    max-width: 1060px;
    margin: 0 auto;
    padding: 44px 28px 80px;
}

/* ─── PAGE HEADING ──────────────────────────────── */
.page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 36px;
    gap: 16px;
}
.page-heading-left {}
.page-heading-eyebrow {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--ink-3);
    margin-bottom: 6px;
}
.page-heading h1 {
    font-family: 'DM Serif Display', Georgia, serif;
    font-size: 2rem;
    font-weight: 400;
    color: var(--ink);
    letter-spacing: -.4px;
    line-height: 1.15;
}
.page-heading h1 em {
    font-style: italic;
    color: var(--blue);
}

/* ─── FLASH MESSAGE ─────────────────────────────── */
.flash {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: var(--r);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 28px;
    border: 1px solid transparent;
    animation: flashIn .25s ease;
}
@keyframes flashIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.flash.success { background: var(--green-bg); border-color: #a7f3d0; color: var(--green); }
.flash.error   { background: var(--red-bg);   border-color: #fbd5d5; color: var(--red); }
.flash-close {
    margin-left: auto; background: none; border: none;
    cursor: pointer; color: inherit; opacity: .5;
    font-size: 14px; line-height: 1; padding: 2px 4px;
}
.flash-close:hover { opacity: 1; }

/* ─── TWO-COLUMN LAYOUT ─────────────────────────── */
.layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
    align-items: start;
}

/* ─── PANEL (shared card style) ────────────────── */
.panel {
    background: var(--surface);
    border: 1px solid var(--rule);
    border-radius: 10px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

/* ─── PANEL HEADER ──────────────────────────────── */
.panel-head {
    padding: 18px 24px;
    border-bottom: 1px solid var(--rule);
    display: flex;
    align-items: center;
    gap: 12px;
}
.panel-head-icon {
    width: 34px; height: 34px;
    background: var(--blue-bg);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
    color: var(--blue);
}
.panel-head-text h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 1rem;
    font-weight: 400;
    color: var(--ink);
    letter-spacing: -.2px;
}
.panel-head-text p {
    font-size: 11.5px;
    color: var(--ink-3);
    margin-top: 1px;
}

/* ─── PANEL BODY ────────────────────────────────── */
.panel-body { padding: 24px; }

/* ─── FORM SECTION ──────────────────────────────── */
.form-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--rule);
}
.form-section:last-of-type { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

.form-section-label {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--ink-3);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-section-label::after {
    content: '';
    flex: 1; height: 1px;
    background: var(--rule);
}

/* ─── GRID ──────────────────────────────────────── */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
.col-span-2 { grid-column: span 2; }

/* ─── FIELD ─────────────────────────────────────── */
.field { display: flex; flex-direction: column; gap: 5px; }

.field label {
    font-size: 12px;
    font-weight: 600;
    color: var(--ink-2);
    letter-spacing: .01em;
}
.field label .req { color: var(--red); margin-left: 2px; }

.field input,
.field select {
    padding: 9px 11px;
    border: 1px solid var(--rule);
    border-radius: var(--r);
    font-family: 'Geist', sans-serif;
    font-size: 13.5px;
    color: var(--ink);
    background: #fdfcfb;
    outline: none;
    transition: border-color .15s, box-shadow .15s, background .15s;
    width: 100%;
    appearance: none;
}
.field input::placeholder { color: #c5c0b8; }
.field input:focus,
.field select:focus {
    border-color: var(--blue);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(26,86,219,.1);
}

/* Select wrapper */
.select-wrap { position: relative; }
.select-wrap::after {
    content: '↓';
    position: absolute; right: 10px; top: 50%;
    transform: translateY(-50%);
    font-size: 11px; color: var(--ink-3);
    pointer-events: none;
}
.select-wrap select { padding-right: 28px; cursor: pointer; }

/* Password field */
.pass-wrap { position: relative; }
.pass-wrap input { padding-right: 60px; }
.pass-toggle {
    position: absolute; right: 0; top: 0; bottom: 0;
    width: 44px;
    background: none; border: none;
    font-size: 11px; font-weight: 600;
    color: var(--ink-3);
    cursor: pointer;
    font-family: 'Geist', sans-serif;
    letter-spacing: .02em;
    transition: color .15s;
}
.pass-toggle:hover { color: var(--blue); }

/* Strength meter */
.strength-track {
    height: 2px;
    background: var(--rule);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 7px;
}
.strength-fill {
    height: 100%;
    width: 0;
    border-radius: 2px;
    transition: width .3s, background .3s;
}
.strength-label {
    font-size: 11px;
    color: var(--ink-3);
    margin-top: 4px;
    text-align: right;
    transition: color .3s;
}

/* ─── ROLE SELECTOR ─────────────────────────────── */
.role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.role-option { position: relative; cursor: pointer; }
.role-option input[type="radio"] { position: absolute; opacity: 0; }

.role-box {
    border: 1px solid var(--rule);
    border-radius: var(--r);
    padding: 14px 14px 14px 44px;
    background: #fdfcfb;
    transition: border-color .15s, background .15s;
    position: relative;
}
.role-box::before {
    content: '';
    position: absolute;
    left: 14px; top: 50%;
    transform: translateY(-50%);
    width: 16px; height: 16px;
    border: 2px solid var(--rule);
    border-radius: 50%;
    background: #fff;
    transition: border-color .15s;
}
.role-option input:checked + .role-box {
    border-color: var(--blue);
    background: var(--blue-bg);
}
.role-option input:checked + .role-box::before {
    border-color: var(--blue);
    background: var(--blue);
    box-shadow: inset 0 0 0 3px #fff;
}
.role-box:hover { border-color: #9ab5f7; }

.role-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
}
.role-desc {
    font-size: 11.5px;
    color: var(--ink-3);
    margin-top: 2px;
}

/* ─── FILE UPLOAD ───────────────────────────────── */
.file-zone {
    border: 1.5px dashed var(--rule);
    border-radius: var(--r);
    padding: 22px 16px;
    text-align: center;
    cursor: pointer;
    background: #fdfcfb;
    position: relative;
    transition: border-color .15s, background .15s;
}
.file-zone:hover { border-color: var(--blue); background: var(--blue-bg); }
.file-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.file-zone-icon { font-size: 1.5rem; display: block; margin-bottom: 6px; }
.file-zone-text { font-size: 12.5px; color: var(--ink-3); line-height: 1.6; }
.file-zone-text b { color: var(--blue); font-weight: 600; }

.file-preview {
    display: none;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    padding: 10px 12px;
    background: var(--green-bg);
    border: 1px solid #a7f3d0;
    border-radius: var(--r);
}
.file-preview.show { display: flex; }
.file-preview img {
    width: 44px; height: 44px;
    border-radius: 6px; object-fit: cover;
    border: 1px solid #a7f3d0;
    flex-shrink: 0;
}
.file-preview-name { font-size: 12.5px; font-weight: 600; color: var(--ink); }
.file-preview-size { font-size: 11.5px; color: var(--ink-3); margin-top: 2px; }

/* ─── FORM ACTIONS ──────────────────────────────── */
.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid var(--rule);
    margin-top: 20px;
}

.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px;
    border-radius: var(--r);
    font-family: 'Geist', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: background .15s, border-color .15s, transform .1s, box-shadow .15s;
    letter-spacing: .01em;
    text-decoration: none;
}
.btn:active { transform: scale(.98); }

.btn-ghost {
    background: transparent;
    border-color: var(--rule);
    color: var(--ink-2);
}
.btn-ghost:hover { background: var(--bg); border-color: #ccc; color: var(--ink); }

.btn-primary {
    background: var(--blue);
    color: #fff;
    border-color: var(--blue);
    box-shadow: 0 1px 3px rgba(26,86,219,.3);
}
.btn-primary:hover {
    background: #1648c7;
    box-shadow: 0 3px 10px rgba(26,86,219,.35);
}

/* ─── SIDEBAR ───────────────────────────────────── */
.sidebar { display: flex; flex-direction: column; gap: 16px; }

/* Tips card */
.tips-list { list-style: none; display: flex; flex-direction: column; gap: 12px; }
.tips-list li {
    display: flex; gap: 10px;
    font-size: 12.5px; color: var(--ink-2); line-height: 1.5;
}
.tips-list .tip-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--blue); flex-shrink: 0; margin-top: 6px;
}

/* Summary badge */
.summary-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid var(--rule);
    font-size: 12.5px;
}
.summary-row:last-child { border-bottom: none; }
.summary-key { color: var(--ink-3); font-weight: 500; }
.summary-val { color: var(--ink); font-weight: 600; text-align: right; max-width: 60%; }

/* ─── USERS TABLE ───────────────────────────────── */
.users-panel { margin-top: 28px; }

.users-table {
    width: 100%;
    border-collapse: collapse;
}
.users-table th {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--ink-3);
    padding: 10px 14px;
    text-align: left;
    background: var(--bg);
    border-bottom: 1px solid var(--rule);
}
.users-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--rule);
    font-size: 13px;
    color: var(--ink);
    vertical-align: middle;
}
.users-table tr:last-child td { border-bottom: none; }
.users-table tr:hover td { background: #fdfcfb; }

.role-pill {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .03em;
}
.role-pill.admin   { background: #fef3c7; color: #92400e; }
.role-pill.manager { background: var(--blue-bg); color: var(--blue); }
.role-pill.webuser { background: #f0fdf4; color: var(--green); }

.table-action {
    background: none; border: none; cursor: pointer;
    font-size: 12px; font-weight: 600; color: var(--red);
    padding: 5px 8px; border-radius: 4px;
    transition: background .15s;
    font-family: 'Geist', sans-serif;
}
.table-action:hover { background: var(--red-bg); }

.empty-state {
    text-align: center; padding: 36px;
    color: var(--ink-3); font-size: 13px;
}

/* ─── RESPONSIVE ────────────────────────────────── */
@media (max-width: 820px) {
    .layout { grid-template-columns: 1fr; }
    .sidebar { display: grid; grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px) {
    .grid-2, .grid-3 { grid-template-columns: 1fr; }
    .col-span-2 { grid-column: span 1; }
    .role-grid { grid-template-columns: 1fr; }
    .sidebar { grid-template-columns: 1fr; }
    .page { padding: 24px 16px 60px; }
    .panel-body { padding: 18px; }
}
</style>

<div class="page">

    <!-- ── PAGE HEADING ───────────────────────────── -->
    <div class="page-heading">
        <div class="page-heading-left">
            <div class="page-heading-eyebrow">Admin Panel / Users</div>
            <h1>Add <em>New</em> User</h1>
        </div>
    </div>

    <!-- ── FLASH ─────────────────────────────────── -->
    <?php if ($flash_msg): ?>
    <div class="flash <?= htmlspecialchars($flash_type) ?>" id="flash">
        <?= $flash_type === 'success' ? '✓' : '✕' ?>
        <?= htmlspecialchars($flash_msg) ?>
        <button class="flash-close" onclick="this.parentElement.remove()">✕</button>
    </div>
    <?php endif; ?>

    <!-- ── TWO-COLUMN LAYOUT ─────────────────────── -->
    <div class="layout">

        <!-- LEFT: FORM PANEL -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-head-icon">👤</div>
                <div class="panel-head-text">
                    <h2>User Information</h2>
                    <p>All fields marked * are required</p>
                </div>
            </div>
            <div class="panel-body">
                <form method="POST" action="/flight_booking/view/adduser.php"
                      enctype="multipart/form-data" id="addUserForm">

                    <!-- Personal -->
                    <div class="form-section">
                        <div class="form-section-label">Personal Details</div>
                        <div class="grid-3" style="margin-bottom:14px;">
                            <div class="field">
                                <label>Full Name <span class="req">*</span></label>
                                <input type="text" name="name" id="name"
                                       placeholder="Ayesha Rahman" required
                                       oninput="updateSummary()">
                            </div>
                            <div class="field">
                                <label>Age <span class="req">*</span></label>
                                <input type="number" name="age" placeholder="30"
                                       min="18" max="80" required>
                            </div>
                            <div class="field">
                                <label>Date of Birth <span class="req">*</span></label>
                                <input type="date" name="dob" required>
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="field">
                                <label>Gender <span class="req">*</span></label>
                                <div class="select-wrap">
                                    <select name="gender" required>
                                        <option value="">Select</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="field">
                                <label>City <span class="req">*</span></label>
                                <div class="select-wrap">
                                    <select name="city" id="city" required oninput="updateSummary()">
                                        <option value="">Select</option>
                                        <?php foreach (['Dhaka','Chittagong','Khulna','Rajshahi','Sylhet','Barisal','Comilla','Mymensingh'] as $c): ?>
                                            <option value="<?= $c ?>"><?= $c ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact & Credentials -->
                    <div class="form-section">
                        <div class="form-section-label">Contact &amp; Credentials</div>
                        <div class="grid-2" style="margin-bottom:14px;">
                            <div class="field">
                                <label>Phone <span class="req">*</span></label>
                                <input type="tel" name="phone" placeholder="01XXXXXXXXX"
                                       pattern="[0-9]{11}" maxlength="11" required>
                            </div>
                            <div class="field">
                                <label>Email <span class="req">*</span></label>
                                <input type="email" name="email" id="email"
                                       placeholder="user@gozayan.com" required
                                       oninput="updateSummary()">
                            </div>
                        </div>
                        <div class="field">
                            <label>Password <span class="req">*</span></label>
                            <div class="pass-wrap">
                                <input type="password" name="pass" id="passInput"
                                       placeholder="Minimum 6 characters" required
                                       oninput="checkStrength(this.value)">
                                <button type="button" class="pass-toggle"
                                        onclick="togglePass(this)">show</button>
                            </div>
                            <div class="strength-track">
                                <div class="strength-fill" id="strFill"></div>
                            </div>
                            <div class="strength-label" id="strLabel"></div>
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="form-section">
                        <div class="form-section-label">Role Assignment <span style="color:var(--red);font-size:11px;text-transform:none;letter-spacing:0;font-weight:700;">*</span></div>
                        <div class="role-grid">
                            <label class="role-option">
                                <input type="radio" name="role" value="admin" required
                                       onchange="updateSummary()">
                                <div class="role-box">
                                    <div class="role-name">🛡️ Admin</div>
                                    <div class="role-desc">Full system access</div>
                                </div>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="manager"
                                       onchange="updateSummary()">
                                <div class="role-box">
                                    <div class="role-name">✈️ Manager</div>
                                    <div class="role-desc">Flights &amp; schedules</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Photo -->
                    <div class="form-section">
                        <div class="form-section-label">Profile Photo <span style="color:var(--red);font-size:11px;text-transform:none;letter-spacing:0;font-weight:700;">*</span></div>
                        <div class="file-zone" id="fileZone">
                            <input type="file" name="image" id="imageInput"
                                   accept="image/jpg,image/jpeg,image/png"
                                   required onchange="handleFile(this)">
                            <span class="file-zone-icon">🖼</span>
                            <p class="file-zone-text" id="fileLabel">
                                <b>Click to upload</b> or drag &amp; drop<br>
                                JPG or PNG — max 2 MB
                            </p>
                        </div>
                        <div class="file-preview" id="filePreview">
                            <img id="previewImg" src="" alt="">
                            <div>
                                <div class="file-preview-name" id="previewName"></div>
                                <div class="file-preview-size" id="previewSize"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-ghost"
                                onclick="resetForm()">Clear</button>
                        <button type="submit" name="submit" class="btn btn-primary">
                            + Add User
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- RIGHT: SIDEBAR -->
        <div class="sidebar">

            <!-- Live Summary -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-head-icon">📋</div>
                    <div class="panel-head-text">
                        <h2>Summary</h2>
                        <p>Updates as you type</p>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="summary-row">
                        <span class="summary-key">Name</span>
                        <span class="summary-val" id="sumName" style="color:var(--ink-3);">—</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-key">Email</span>
                        <span class="summary-val" id="sumEmail" style="color:var(--ink-3);">—</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-key">Role</span>
                        <span class="summary-val" id="sumRole" style="color:var(--ink-3);">—</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-key">City</span>
                        <span class="summary-val" id="sumCity" style="color:var(--ink-3);">—</span>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-head-icon" style="background:#fef3c7;color:#92400e;">💡</div>
                    <div class="panel-head-text">
                        <h2>Guidelines</h2>
                        <p>Before adding a user</p>
                    </div>
                </div>
                <div class="panel-body">
                    <ul class="tips-list">
                        <li><span class="tip-dot"></span>Email must be unique across all roles.</li>
                        <li><span class="tip-dot"></span>Password is hashed — cannot be recovered.</li>
                        <li><span class="tip-dot"></span>Admins have full access to all panels.</li>
                        <li><span class="tip-dot"></span>Managers can manage flights &amp; schedules only.</li>
                        <li><span class="tip-dot"></span>Photo must be JPG/PNG under 2 MB.</li>
                    </ul>
                </div>
            </div>

        </div><!-- /sidebar -->
    </div><!-- /layout -->

    <!-- ── USERS TABLE ───────────────────────────── -->
    <div class="panel users-panel">
        <div class="panel-head">
            <div class="panel-head-icon" style="background:#f0fdf4;color:#057a55;">👥</div>
            <div class="panel-head-text">
                <h2>Existing Users</h2>
                <p><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?> in the system</p>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <?php if (empty($users)): ?>
                <div class="empty-state">No users have been added yet.</div>
            <?php else: ?>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>City</th>
                        <th>Phone</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td style="color:var(--ink-3);font-size:12px;"><?= $i + 1 ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></td>
                    <td style="color:var(--ink-2);"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="role-pill <?= htmlspecialchars($u['role']) ?>">
                            <?= ucfirst(htmlspecialchars($u['role'])) ?>
                        </span>
                    </td>
                    <td style="color:var(--ink-2);"><?= htmlspecialchars($u['city']) ?></td>
                    <td style="color:var(--ink-2);"><?= htmlspecialchars($u['phone']) ?></td>
                    <td>
                        <button class="table-action"
                            onclick="if(confirm('Remove <?= htmlspecialchars(addslashes($u['name'])) ?>?')) window.location='?delete_id=<?= $u['id'] ?>'">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /page -->

<script>
function togglePass(btn) {
    const inp = document.getElementById('passInput');
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.textContent = show ? 'hide' : 'show';
}

function checkStrength(v) {
    const fill  = document.getElementById('strFill');
    const label = document.getElementById('strLabel');
    let s = 0;
    if (v.length >= 6)           s++;
    if (v.length >= 10)          s++;
    if (/[A-Z]/.test(v))         s++;
    if (/[0-9]/.test(v))         s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const levels = [
        { w:'0',    bg:'transparent', t:'' },
        { w:'20%',  bg:'#ef4444',     t:'Weak' },
        { w:'45%',  bg:'#f97316',     t:'Fair' },
        { w:'65%',  bg:'#eab308',     t:'Good' },
        { w:'85%',  bg:'#10b981',     t:'Strong' },
        { w:'100%', bg:'#059669',     t:'Very strong' },
    ][Math.min(s, 5)];
    fill.style.width      = levels.w;
    fill.style.background = levels.bg;
    label.textContent     = levels.t;
    label.style.color     = levels.bg;
}

function handleFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('previewName').textContent = file.name;
        document.getElementById('previewSize').textContent = (file.size / 1024).toFixed(0) + ' KB';
        document.getElementById('filePreview').classList.add('show');
        document.getElementById('fileLabel').innerHTML = '✓ <b>' + file.name + '</b> selected';
        document.getElementById('fileZone').style.borderColor = '#10b981';
        document.getElementById('fileZone').style.background  = 'var(--green-bg)';
    };
    reader.readAsDataURL(file);
}

function updateSummary() {
    const name  = document.getElementById('name').value.trim()  || '—';
    const email = document.getElementById('email').value.trim() || '—';
    const city  = document.getElementById('city').value         || '—';
    const role  = document.querySelector('input[name="role"]:checked')?.value || '—';

    const set = (id, val) => {
        const el = document.getElementById(id);
        el.textContent = val;
        el.style.color = val === '—' ? 'var(--ink-3)' : 'var(--ink)';
    };
    set('sumName',  name);
    set('sumEmail', email);
    set('sumRole',  role === '—' ? '—' : role.charAt(0).toUpperCase() + role.slice(1));
    set('sumCity',  city);
}

function resetForm() {
    document.getElementById('addUserForm').reset();
    document.getElementById('filePreview').classList.remove('show');
    document.getElementById('fileLabel').innerHTML = '<b>Click to upload</b> or drag & drop<br>JPG or PNG — max 2 MB';
    document.getElementById('fileZone').style.cssText = '';
    document.getElementById('strFill').style.cssText  = '';
    document.getElementById('strLabel').textContent   = '';
    ['sumName','sumEmail','sumRole','sumCity'].forEach(id => {
        const el = document.getElementById(id);
        el.textContent = '—';
        el.style.color = 'var(--ink-3)';
    });
}

document.querySelectorAll('input[name="role"]').forEach(r =>
    r.addEventListener('change', updateSummary)
);

const flash = document.getElementById('flash');
if (flash) setTimeout(() => flash.style.opacity === '' && flash.remove(), 5000);
</script>

<?php include("../includes/footer.php"); ?>
</body>
</html>