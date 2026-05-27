<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode([
    'logged_in' => isset($_SESSION['role']) && $_SESSION['role'] === 'webuser'
]);
