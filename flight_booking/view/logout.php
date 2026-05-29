<?php
session_start();

// Unset all session variables and destroy the session
$_SESSION = [];
session_unset();
session_destroy();

// Prevent browser from caching authenticated pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Redirect to login page
header('Location: login.php');
exit;
?>
