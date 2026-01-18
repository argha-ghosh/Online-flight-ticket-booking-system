<?php
$servername = "localhost";
$username   = "root";
$db_password = ""; 
$dbname     = "flight_booking";

$conn = new mysqli($servername, $username, $db_password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>