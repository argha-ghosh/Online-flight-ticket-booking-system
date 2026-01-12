<?php
include("../includes/adminheader.php");
?>

<?php
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
    <script src="editprofile.js" defer></script>
    <link rel="stylesheet" href="viewadminprofile.css">
    <!-- <script src="controller/editprofile.js"></script>  -->
</head>
<body>

<div class="profile-container">
    <h2>Admin Profile</h2>

    <form>
        <label>Name:</label>
        <input type="text" value="<?= $admin['name']; ?>" readonly required>

        <label>Age:</label>
        <input type="number" value="<?= $admin['age']; ?>" readonly required>

        <label>Date of Birth:</label>
        <input type="date" value="<?= $admin['dob']; ?>" readonly required>

        <label>Address:</label>
        <input type="text" value="<?= $admin['address']; ?>" readonly required>

        <label>Phone Number:</label>
        <input type="text" value="<?= $admin['phone']; ?>" readonly required>

        <label>Gender:</label>
        <select required>
            <option <?= $admin['gender'] == "Male" ? "selected" : "" ?>>Male</option>
            <option <?= $admin['gender'] == "Female" ? "selected" : ""  ?>>Female</option>
            <option <?= $admin['gender'] == "Other" ? "selected" : "" ?>>Other</option>
        </select>

        <label>Email:</label>
        <input type="email" value="<?= $admin['email']; ?>" readonly required>

        <div class="btn-group">
            <button type="button" class="btn" id="editBtn">Edit Profile</button>
            <button type="button" class="btn" id="saveBtn">Save</button>
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
        alert('Profile saved (not really, this is just a demo).');
    });
</script>

</body>
</html>


<?php include("../includes/footer.php"); ?>