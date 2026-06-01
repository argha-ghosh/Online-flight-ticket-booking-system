<?php
$host = $_SERVER['HTTP_HOST'] ?? '';

if ($host === 'localhost' || $host === '127.0.0.1') {
    // ── LOCAL (XAMPP) ──
    $servername  = "localhost";
    $username    = "root";
    $db_password = "";
    $dbname      = "if0_42063720_flight_booking";
} else {
    // ── PRODUCTION (InfinityFree) ──
    $servername  = "sql107.infinityfree.com";
    $username    = "if0_42063720";
    $db_password = "K8CvDhFFpMw0ft";
    $dbname      = "if0_42063720_flight_booking";
}

$conn = new mysqli($servername, $username, $db_password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>

