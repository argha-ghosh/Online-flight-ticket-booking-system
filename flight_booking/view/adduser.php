<?php
include("../model/db_conn.php");
include("../includes/adminheader.php");

if (isset($_POST['submit'])) {
   
    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $age   = (int) $_POST['age'];
    $dob   = $_POST['dob'];
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $gender = $_POST['gender'];
    $city   = $_POST['city'];
    $email  = mysqli_real_escape_string($conn, $_POST['email']);
    $role   = $_POST['role'];
    $password = password_hash($_POST['pass'], PASSWORD_DEFAULT);

    $profile_image = "";
    if (!empty($_FILES['image']['name'])) {
        $upload_dir = __DIR__ . "/uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $profile_image = time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $profile_image);
    }

    $sql = "INSERT INTO users (name, age, date_of_birth, phone, gender, city, email, password, role, profile_image)
            VALUES ('$name', '$age', '$dob', '$phone', '$gender', '$city', '$email', '$password', '$role', '$profile_image')";

    $sqladmin = "INSERT INTO login (email, password, role) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sqladmin);
    $stmt->bind_param("sss", $email, $password, $role);
    $stmt->execute();

    if (mysqli_query($conn, $sql)) {
        echo "User added successfully!";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Now</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <link rel="stylesheet" href="component.css">
</head>
<body>
    <section class="register">
        <form action="" enctype="multipart/form-data" method="post">
            <h3>Add New User</h3>
            <input type="text" name="name" placeholder="Enter user name" class="box" required>
            <input type="number" name="age" placeholder="Enter your age" class="box" required min="1" max="120">
            <input type="date" name="dob" class="box" required>
            <input type="tel" name="phone" placeholder="Enter phone number" class="box" required 
                   pattern="[0-9]{11}" title="Enter 11 digit number">
            <select name="gender" class="box" required>
                <option value="">Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
            <select name="city" class="box" required>
                <option value="">Select City</option>
                <option value="Dhaka">Dhaka</option>
                <option value="Chittagong">Chittagong</option>
                <option value="Khulna">Khulna</option>
                <option value="Rajshahi">Rajshahi</option>
                <option value="Sylhet">Sylhet</option>
            </select>
            <input type="email" name="email" placeholder="Enter user email" class="box" required>
            <input type="password" name="pass" placeholder="Enter user password" class="box" required>
            <select name="role" class="box" required>
                <option value="" disabled selected>Select Role</option>
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
            </select>
            <input type="file" name="image" accept="image/jpg, image/jpeg, image/png" class="box" required> 

            <input type="submit" value="Add User" class="btn" name="submit">
        </form>
    </section>
</body>
</html>

<?php
include("../includes/footer.php");  
?>