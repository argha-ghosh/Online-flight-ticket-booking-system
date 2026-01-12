<?php
include("../model/db_conn.php");
include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Now</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />

    <link rel ="stylesheet" href= "component.css">
</head>
<body>
    <section class="register">
        <form action="" enctype="multipart/form-data" method="post">
            <h3>Register Now</h3>
            <input type="text" name="name" placeholder="Enter your name" class="box" required> 
            <input type="email" name="email" placeholder="Enter your email" class="box" required> 
            <input type="password" name="pass" placeholder="Enter your password" class="box" required> 
            <input type="password" name="cpass" placeholder="Confirm your password" class="box" required> 
            <input type="file" name="image" accept="image/jpg, image/jpeg, image/png" class="box" required> 
            <input type="submit" value="register now" class="btn" name="submit">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </section>
</body>
</html>

<?php
include("../includes/footer.php");  
?>