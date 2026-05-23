<?php
// 1. Ensure clean, robust session orchestration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../includes/managerheader.php"); // Includes protection & db connection via parent layers[cite: 22, 26]
include_once("../model/db_conn.php");

// 2. Security Check: Resolve identity dynamically from session instead of hardcoding[cite: 22, 26]
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    header("Location: /flight_booking/view/login.php");
    exit;
}

$manager_email = $_SESSION['email'];
$error_message = "";
$success_message = "";

// Fetch manager dataset safely using session email criteria[cite: 26]
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $manager_email);
$stmt->execute();
$result = $stmt->get_result();
$manager = $result->fetch_assoc();
$stmt->close();

if (!$manager) {
    die("Manager record not resolved. Please contact your system administrator.");
}

$manager_id = $manager['id'];

// 3. Optimized POST Form Handler
if (isset($_POST['save'])) {
    $new_name  = trim($_POST['name']);
    $new_phone = trim($_POST['phone']);
    $new_city  = trim($_POST['city']);
    $new_age   = intval($_POST['age']);
    $new_dob   = $_POST['date_of_birth'];
    $new_gnd   = $_POST['gender'];
    $new_img   = $manager['profile_image']; // Fallback to current asset[cite: 22]

    // Secure Profile Image Upload Logic[cite: 22]
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['size'] > 0) {
        $upload_dir = "../view/uploadss/";
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = $_FILES['profile_image']['name'];
        $file_tmp  = $_FILES['profile_image']['tmp_name'];
        $file_size = $_FILES['profile_image']['size'];
        
        // Advanced Validations (Size cap at 2MB & Mime matching)
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_exts)) {
            if ($file_size <= 2097152) { // 2MB Limit
                $new_file_name = "profile_" . $manager_id . "_" . time() . "." . $file_ext;
                $upload_path   = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Housekeeping: Unlink the depreciated image asset safely[cite: 22]
                    if (!empty($manager['profile_image']) && file_exists($upload_dir . $manager['profile_image'])) {
                        @unlink($upload_dir . $manager['profile_image']);
                    }
                    $new_img = $new_file_name;
                } else {
                    $error_message = "Failed to migrate uploaded file to storage destination.";
                }
            } else {
                $error_message = "File dimensions or footprint too large. Maximum threshold is 2MB.";
            }
        } else {
            $error_message = "Invalid file type extension detected.";
        }
    }

    // Database Update if no errors occurred
    if (empty($error_message)) {
        $update_stmt = $conn->prepare("UPDATE users SET name = ?, age = ?, date_of_birth = ?, phone = ?, gender = ?, city = ?, profile_image = ? WHERE id = ?");
        $update_stmt->bind_param("sssssssi", $new_name, $new_age, $new_dob, $new_phone, $new_gnd, $new_city, $new_img, $manager_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile configurations committed successfully!";
            // Refresh local variable state
            $manager['name']          = $new_name;
            $manager['age']           = $new_age;
            $manager['date_of_birth'] = $new_dob;
            $manager['phone']         = $new_phone;
            $manager['gender']        = $new_gnd;
            $manager['city']          = $new_city;
            $manager['profile_image'] = $new_img;
        } else {
            $error_message = "Critical error committing profile adjustments to database.";
        }
        $update_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan | Edit Manager Profile</title>
    <link rel="stylesheet" href="viewmanagerprofile.css"> <!-- External Stylesheet Consistency[cite: 22] -->
</head>
<body>

<div class="profile-container">
    <h2>Manager Profile Management</h2>

    <!-- Dynamic Alert Components -->
    <?php if (!empty($success_message)): ?>
        <p style="color: #2ecc71; background: #e8f8f0; padding: 10px; border-radius: 5px; font-weight: 600;"><?= htmlspecialchars($success_message); ?></p>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <p style="color: #e74c3c; background: #fdedec; padding: 10px; border-radius: 5px; font-weight: 600;"><?= htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <label>Full Name:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($manager['name']); ?>" readonly required>

        <label>Age Metric:</label>
        <input type="number" name="age" value="<?= htmlspecialchars($manager['age']); ?>" readonly required>

        <label>Date of Birth:</label>
        <input type="date" name="date_of_birth" value="<?= htmlspecialchars($manager['date_of_birth']); ?>" readonly required>

        <label>Contact Phone Number:</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($manager['phone']); ?>" readonly required>

        <label>Gender:</label>
        <select name="gender" disabled required style="background-color: #f5f5f5;">
            <option value="Male" <?= $manager['gender'] == "Male" ? "selected" : "" ?>>Male</option>
            <option value="Female" <?= $manager['gender'] == "Female" ? "selected" : "" ?>>Female</option>
            <option value="Other" <?= $manager['gender'] == "Other" ? "selected" : "" ?>>Other</option>
        </select>

        <label>City Hub:</label>
        <input type="text" name="city" value="<?= htmlspecialchars($manager['city']); ?>" readonly required>

        <label>Email ID Account (Read-Only):</label>
        <input type="email" value="<?= htmlspecialchars($manager['email']); ?>" readonly disabled style="background-color: #e9ecef;">

        <label>Active Avatar Canvas:</label>
        <?php
            $image_path = "../view/uploadss/" . $manager['profile_image'];
            if (!empty($manager['profile_image']) && file_exists($image_path)) {
                echo '<img src="' . htmlspecialchars($image_path) . '" alt="Profile Image" class="profile-image" />';
            } else {
                echo '<img src="https://via.placeholder.com/250?text=No+Image" alt="Profile Image" class="profile-image" />';
            }
        ?>

        <label>Update Avatar File Asset:</label>
        <input type="file" name="profile_image" accept="image/jpg, image/jpeg, image/png, image/gif" class="box" />

        <div class="btn-group">
            <button type="button" class="btn" id="editBtn">Modify Fields</button>
            <button type="submit" class="btn" id="saveBtn" name="save" style="display: none;">Commit Modifications</button>
        </div>
    </form>
</div>

<script>
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const interactiveInputs = document.querySelectorAll('form input:not([disabled]), form select');

    editBtn.addEventListener('click', () => {
        interactiveInputs.forEach(input => {
            input.removeAttribute('readonly');
            input.removeAttribute('disabled');
            input.style.backgroundColor = "#fff";
        });
        editBtn.style.display = "none";
        saveBtn.style.display = "inline-block";
    });
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?> <!-- Unified system footer[cite: 22, 24] -->