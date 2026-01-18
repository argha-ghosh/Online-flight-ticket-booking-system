<?php
session_start();
include("../model/db_conn.php");

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $current_pass = trim($_POST['current_password']);
    $new_pass = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $message = "All fields are required.";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "New password and confirm password do not match.";
    } elseif (strlen($new_pass) < 8) {
        $message = "New password must be at least 8 characters long.";
    } else {
        // Get user from session
        $email = $_SESSION['email'];
        $stmt = $conn->prepare("SELECT password FROM login WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($current_pass, $user['password']) || $current_pass === $user['password']) {
                // Hash new password
                $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE login SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $hashed_new_pass, $email);
                if ($update_stmt->execute()) {
                    $message = "Password changed successfully.";
                } else {
                    $message = "Error updating password.";
                }
                $update_stmt->close();
            } else {
                $message = "Current password is incorrect.";
            }
        } else {
            $message = "User not found.";
        }
        $stmt->close();
    }
}
?>

<?php include("../includes/adminheader.php"); ?>

<div class="changePassword">
    <form action="" method="post">
        <h3>Change Password</h3>
        <?php if ($message): ?>
            <p style="color: <?php echo strpos($message, 'successfully') !== false ? 'green' : 'red'; ?>;"><?php echo $message; ?></p>
        <?php endif; ?>
        <label for="current_password">Current Password:</label>
        <input type="password" id="current_password" name="current_password" required>
        
        <label for="new_password">New Password:</label>
        <input type="password" id="new_password" name="new_password" required>
        
        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        
        <div class="button-group">
            <input type="submit" name="save" value="Save">
            <input type="reset" value="Clear">
        </div>
    </form>
</div>

<?php include("../includes/footer.php"); ?>