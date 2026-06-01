<?php
/**
 * Base URL Configuration — auto-detects environment
 * - Local (XAMPP):  localhost → /flight_booking
 * - Production:     any other host → ''
 */
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($host === 'localhost' || $host === '127.0.0.1') {
    define('BASE_URL', '/flight_booking');
} else {
    define('BASE_URL', '');
}
?>
