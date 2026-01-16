<?php
include("../includes/adminheader.php");
include("../model/db_conn.php");

$admin_id = 2; 

$stmt = $conn->prepare("SELECT * FROM admininfo WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="viewadminprofile.css">
    <script src="../controller/editprofile.js" defer></script>

</head>
<body>

<div class="profile-container">
    <h2>Admin Profile</h2>

    <form>
        <label>Name:</label>
        <input type="text" id="name" value="<?= $admin['name']; ?>" readonly required>

        <label>Age:</label>
        <input type="number" id="age" value="<?= $admin['age']; ?>" readonly required>

        <label>Date of Birth:</label>
        <input type="date" id="dob" value="<?= $admin['dob']; ?>" readonly required>

        <label>Address:</label>
        <input type="text" id="address" value="<?= $admin['address']; ?>" readonly required>

        <label>Phone Number:</label>
        <input type="text" id="phone" value="<?= $admin['phone']; ?>" readonly required>

        <label>Gender:</label>
        <select id="gender" required>
            <option <?= $admin['gender'] == "Male" ? "selected" : "" ?>>Male</option>
            <option <?= $admin['gender'] == "Female" ? "selected" : ""  ?>>Female</option>
            <option <?= $admin['gender'] == "Other" ? "selected" : "" ?>>Other</option>
        </select>

        <label>Email:</label>
        <input type="email" id="email" value="<?= $admin['email']; ?>" readonly required>

        <div class="btn-group">
            <button type="button" class="btn" id="editBtn">Edit Profile</button>
            <button type="button" class="btn" id="saveBtn">Save</button>
        </div>
    </form>
</div>

</body>
</html>

<?php include("../includes/footer.php"); ?>
