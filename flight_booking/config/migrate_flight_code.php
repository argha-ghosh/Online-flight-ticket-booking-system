<?php
/**
 * Migration: Allow same flight_code for different classes
 * Run this ONCE on any database that still has the old unique constraint.
 * Access: http://yourdomain/flight_booking/config/migrate_flight_code.php
 * DELETE this file after running it.
 */

// Only allow admin session or direct CLI
session_start();
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    die("Access denied. Log in as admin first.");
}

require_once __DIR__ . '/../model/db_conn.php';

$steps = [];

// 1. Check if old unique key exists
$res = $conn->query("SHOW INDEX FROM flights WHERE Key_name = 'uq_flight_code'");
if ($res && $res->num_rows > 0) {
    if ($conn->query("ALTER TABLE flights DROP INDEX uq_flight_code")) {
        $steps[] = "✅ Dropped old UNIQUE KEY on flight_code.";
    } else {
        $steps[] = "❌ Failed to drop old key: " . $conn->error;
    }
} else {
    $steps[] = "ℹ️ Old unique key 'uq_flight_code' not found — already removed or never existed.";
}

// 2. Check if new composite key already exists
$res2 = $conn->query("SHOW INDEX FROM flights WHERE Key_name = 'uq_flight_code_class'");
if ($res2 && $res2->num_rows > 0) {
    $steps[] = "ℹ️ Composite key 'uq_flight_code_class' already exists — skipping.";
} else {
    if ($conn->query("ALTER TABLE flights ADD UNIQUE KEY uq_flight_code_class (flight_code, flight_class)")) {
        $steps[] = "✅ Added composite UNIQUE KEY on (flight_code, flight_class).";
    } else {
        $steps[] = "❌ Failed to add composite key: " . $conn->error;
    }
}

$steps[] = "🎉 Done! Same flight code can now be used for Economy, Business, and First Class separately.";
$steps[] = "<strong>⚠️ Delete this file after running it.</strong>";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Migration — Flight Code Fix</title>
<style>
body { font-family: system-ui, sans-serif; background: #f0f4fb; padding: 40px; }
.box { background: #fff; border-radius: 12px; padding: 28px 32px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
h2 { color: #0f172a; margin-bottom: 20px; }
li { padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
li:last-child { border-bottom: none; }
</style>
</head>
<body>
<div class="box">
    <h2>🛠️ Migration: Flight Code Fix</h2>
    <ul>
        <?php foreach ($steps as $s): ?>
        <li><?= $s ?></li>
        <?php endforeach; ?>
    </ul>
</div>
</body>
</html>
