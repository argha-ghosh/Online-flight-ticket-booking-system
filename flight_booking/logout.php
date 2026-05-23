<?php
session_start();
$role = $_SESSION['role'] ?? '';
session_unset();
session_destroy();

// Redirect based on who was logged in
if ($role === 'admin') {
    header("Location: /flight_booking/view/login.php");
} elseif ($role === 'manager') {
    header("Location: /flight_booking/view/login.php");
} else {
    header("Location: /flight_booking/view/home.php");
}
exit;