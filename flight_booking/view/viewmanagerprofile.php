<?php
include("../includes/header.php");


session_start();
include("../model/db_conn.php");

$manager_id = 16; 

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$result = $stmt->get_result();
$manager = $result->fetch_assoc();



if (isset($_POST['save'])) {
    // Get updated values from the form
    $new_name = $_POST['name'];
    $new_phone = $_POST['phone'];
    $new_city = $_POST['city'];
    $new_age = $_POST['age'];
    $new_date_of_birth = $_POST['date_of_birth'];
    $new_gender = $_POST['gender'];
    $new_profile_image = $manager['profile_image']; // Keep existing image by default

    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['size'] > 0) {
        $upload_dir = "../uploads/";
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = $_FILES['profile_image']['name'];
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_error = $_FILES['profile_image']['error'];
        
        // Validate file
        if ($file_error === 0) {
            // Get file extension
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');
            
            if (in_array($file_ext, $allowed_ext)) {
                // Generate unique filename
                $new_file_name = "profile_" . $manager_id . "_" . time() . "." . $file_ext;
                $upload_path = $upload_dir . $new_file_name;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Delete old image if it exists and is different
                    if (!empty($manager['profile_image']) && file_exists($upload_dir . $manager['profile_image'])) {
                        unlink($upload_dir . $manager['profile_image']);
                    }
                    $new_profile_image = $new_file_name;
                }
            }
        }
    }

    // Update the database with the new values using manager_id
    $update_stmt = $conn->prepare("UPDATE users SET name = ?, age = ?, date_of_birth = ?, phone = ?, gender = ?, city = ?, profile_image = ? WHERE id = ?");
    $update_stmt->bind_param("sssssssi", $new_name, $new_age, $new_date_of_birth, $new_phone, $new_gender, $new_city, $new_profile_image, $manager_id);
    
    if ($update_stmt->execute()) {
        $message = "Profile updated successfully!";
        // Refresh the data after update
        $stmt->execute();
        $result = $stmt->get_result();
        $manager = $result->fetch_assoc();
    } else {
        $message = "Error updating profile!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Profile</title>
    <script src="editprofile.js" defer></script>
    <link rel="stylesheet" href="viewmanagerprofile.css">
</head>
<body>

<div class="profile-container">
    <h2>Manager Profile</h2>

    <!-- Display any update message -->
    <?php if (isset($message)) { ?>
        <p style="color: green;"><?php echo $message; ?></p>
    <?php } ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <label>Name:</label>
        <input type="text" name="name" value="<?= $manager['name']; ?>" readonly required>

        <label>Age:</label>
        <input type="number" name="age" value="<?= $manager['age']; ?>" readonly required>

        <label>Date of Birth:</label>
        <input type="date" name="date_of_birth" value="<?= $manager['date_of_birth']; ?>" readonly required>

        <label>Phone Number:</label>
        <input type="text" name="phone" value="<?= $manager['phone']; ?>" readonly required>

        <label>Gender:</label>
        <select name="gender" readonly required>
            <option <?= $manager['gender'] == "Male" ? "selected" : "" ?>>Male</option>
            <option <?= $manager['gender'] == "Female" ? "selected" : "" ?>>Female</option>
            <option <?= $manager['gender'] == "Other" ? "selected" : "" ?>>Other</option>
        </select>

        <label>City:</label>
        <input type="text" name="city" value="<?= $manager['city']; ?>" readonly required>

        <label>Email:</label>
        <input type="email" value="<?= $manager['email']; ?>" readonly required>

        <label>Profile Image:</label>
        <?php
            $image_path = "../uploads/" . $manager['profile_image'];
            if (!empty($manager['profile_image']) && file_exists($image_path)) {
                echo '<img src="' . $image_path . '" alt="Profile Image" class="profile-image" />';
            } else {
                echo '<img src="https://via.placeholder.com/250?text=No+Image" alt="Profile Image" class="profile-image" />';
            }
        ?>

        <label>Change Profile Image:</label>
        <input type="file" name="profile_image" accept="image/jpg, image/jpeg, image/png" class="box" />

        <div class="btn-group">
            <button type="button" class="btn" id="editBtn">Edit Profile</button>
            <button type="submit" class="btn" id="saveBtn" name="save">Save</button>
        </div>
    </form>
</div>

<script>
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const inputs = document.querySelectorAll('form input, form select');

    editBtn.addEventListener('click', () => {
        inputs.forEach(input => input.removeAttribute('readonly'));
    });

    saveBtn.addEventListener('click', () => {
        inputs.forEach(input => input.setAttribute('readonly', true));
        alert('Profile saved successfully.');
    });
</script>

</body>
</html>


<?php include("../includes/footer.php"); ?>