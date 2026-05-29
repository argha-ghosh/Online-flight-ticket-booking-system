<?php
require_once __DIR__ . "/../model/db_conn.php";
$r = $conn->query("SHOW CREATE TABLE flights");
$row = $r->fetch_assoc();
echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";

echo "<h3>Existing flights:</h3><pre>";
$r2 = $conn->query("SELECT id, flight_name, flight_code, status FROM flights ORDER BY id");
while ($f = $r2->fetch_assoc()) print_r($f);
echo "</pre>";
?>
