<?php
session_start();
require_once __DIR__ . '/config/base_url.php';
$role = $_SESSION['role'] ?? '';
session_unset();
session_destroy();

// Prevent browser from caching — stops back button showing logged-in header
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

header("Location: " . BASE_URL . "/view/home.php");
exit;
