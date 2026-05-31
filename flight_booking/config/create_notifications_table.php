<?php
/**
 * Create notifications table for flight updates
 * Run this once to set up the table, then it can be deleted
 */

require_once __DIR__ . "/../model/db_conn.php";

$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    flight_id INT,
    message VARCHAR(500) NOT NULL,
    type VARCHAR(50) DEFAULT 'flight_update',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES login(id) ON DELETE CASCADE,
    FOREIGN KEY (flight_id) REFERENCES flights(id) ON DELETE SET NULL,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at)
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Notifications table created successfully!";
} else {
    echo "❌ Error creating table: " . $conn->error;
}

$conn->close();
?>
