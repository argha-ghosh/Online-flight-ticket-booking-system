<?php
/**
 * Migration: Create otp_codes table
 * Run this once to create the OTP storage table.
 */
require_once __DIR__ . "/../model/db_conn.php";

$sql = "CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mobile VARCHAR(15) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    purpose VARCHAR(30) DEFAULT 'payment',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_mobile_purpose (mobile, purpose),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "<div style='font-family:sans-serif;padding:40px;text-align:center;'>";
    echo "<h2 style='color:#22c55e;'>✅ Table `otp_codes` created successfully!</h2>";
    echo "<p style='color:#666;'>You can now use OTP verification in payments.</p>";
    echo "<a href='../view/home.php' style='color:#0b72e6;'>← Go to Home</a>";
    echo "</div>";
} else {
    echo "<div style='font-family:sans-serif;padding:40px;text-align:center;'>";
    echo "<h2 style='color:#ef4444;'>❌ Error creating table</h2>";
    echo "<p style='color:#666;'>" . htmlspecialchars($conn->error) . "</p>";
    echo "</div>";
}

$conn->close();
?>
