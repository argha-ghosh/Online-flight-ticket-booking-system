<?php
session_start();
header('Content-Type: application/json');

// Guard: only for logged-in users
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . "/../model/db_conn.php";

// Get user id from session email
$email = $_SESSION['email'];
$user_q = $conn->prepare("SELECT id FROM login WHERE email = ?");
$user_q->bind_param("s", $email);
$user_q->execute();
$user_res = $user_q->get_result();
$user_row = $user_res->fetch_assoc();
$user_q->close();

if (!$user_row) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$user_id = $user_row['id'];

// Handle different actions
$action = $_GET['action'] ?? 'get_unread';

if ($action === 'get_unread') {
    // Fetch unread notifications
    $stmt = $conn->prepare("SELECT id, message, flight_id, created_at FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifs = [];
    while ($row = $result->fetch_assoc()) {
        $notifs[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['notifications' => $notifs, 'count' => count($notifs)]);
} elseif ($action === 'mark_read') {
    // Mark notification as read
    $notif_id = (int)($_GET['notif_id'] ?? 0);
    if ($notif_id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notif_id']);
    }
} elseif ($action === 'mark_all_read') {
    // Mark all notifications as read
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
}

$conn->close();
?>
