<?php
include("../model/db_conn.php");

function updateProfile($admin_id, $name, $age, $dob, $address, $phone, $gender, $email) {
    global $conn; 

    // Get the old email before updating
    $old_email_stmt = $conn->prepare("SELECT email FROM admininfo WHERE id = ?");
    $old_email_stmt->bind_param("i", $admin_id);
    $old_email_stmt->execute();
    $old_result = $old_email_stmt->get_result();
    $old_email = $old_result->fetch_assoc()['email'];
    $old_email_stmt->close();

    // Update admininfo table
    $stmt = $conn->prepare("UPDATE admininfo SET name = ?, age = ?, dob = ?, address = ?, phone = ?, gender = ?, email = ? WHERE id = ?");
    $stmt->bind_param("sisssssi", $name, $age, $dob, $address, $phone, $gender, $email, $admin_id);

    if ($stmt->execute()) {
        $stmt->close();

        // If email changed, update login table
        if ($email != $old_email) {
            $update_login_stmt = $conn->prepare("UPDATE login SET email = ? WHERE role = 'admin'");
            $update_login_stmt->bind_param("s", $email);
            if (!$update_login_stmt->execute()) {
                error_log("Failed to update login table: " . $update_login_stmt->error);
                $update_login_stmt->close();
                $conn->close();
                return false;
            }
            $update_login_stmt->close();
        }

        $conn->close();
        return true; 
    } else {
        $stmt->close();
        $conn->close();
        return false; 
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_id = 2;  
    $name = $_POST['name'];
    $age = $_POST['age'];
    $dob = $_POST['dob'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $email = $_POST['email'];

    if (updateProfile($admin_id, $name, $age, $dob, $address, $phone, $gender, $email)) {
        echo "Profile updated successfully.";
    } else {
        echo "Failed to update profile.";
    }
}
?>

