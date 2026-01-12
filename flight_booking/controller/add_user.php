<?php
include("../model/db_conn.php");

if (isset($_POST['submit'])) {

    $name   = $_POST['name'];
    $age    = $_POST['age'];
    $dob    = $_POST['dob'];
    $phone  = $_POST['phone'];
    $gender = $_POST['gender'];
    $city   = $_POST['city'];

    $email  = $_POST['email'];
    $role   = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sqluser = "INSERT INTO users 
        (name, age, date_of_birth, phone, gender, city, email, role) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt1 = $conn->prepare($sqluser);
    $stmt1->bind_param("sisssss", $name, $age, $dob, $phone, $gender, $city, $email, $role);
    $stmt1->execute();

    $sqladmin = "INSERT INTO admin (email, password, role)
                 VALUES (?, ?, ?)";

    $stmt2 = $conn->prepare($sqladmin);
    $stmt2->bind_param("sss", $email, $password, $role);
    $stmt2->execute();

    header("Location: add_users.php");
    exit;
}
?>


