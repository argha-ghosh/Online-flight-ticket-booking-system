<?php
// TEMPORARY DEBUG FILE — DELETE AFTER FIXING
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../model/db_conn.php";

echo "<h2>DB Connection</h2>";
echo $conn->connect_error ? "FAILED: " . $conn->connect_error : "OK";

echo "<h2>POST Data</h2><pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>flights table columns</h2><pre>";
$r = $conn->query("DESCRIBE flights");
while ($row = $r->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . "\n";
echo "</pre>";

if (isset($_POST['do_update'])) {
    $id = (int)$_POST['id'];
    echo "<h2>Testing UPDATE for id=$id</h2>";

    $stmt = $conn->prepare("UPDATE flights SET flight_name=?, price=? WHERE id=?");
    if (!$stmt) {
        echo "Prepare FAILED: " . $conn->error;
    } else {
        $name  = "TEST_" . time();
        $price = 999.99;
        $stmt->bind_param("sdi", $name, $price, $id);
        if ($stmt->execute()) {
            echo "✅ Simple UPDATE worked! Rows affected: " . $stmt->affected_rows;
        } else {
            echo "❌ Execute FAILED: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<form method="POST">
    <input type="hidden" name="do_update" value="1">
    Flight ID to test: <input type="number" name="id" value="1">
    <button type="submit">Run Test UPDATE</button>
</form>
