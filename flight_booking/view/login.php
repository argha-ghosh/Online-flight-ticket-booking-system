<?php
session_start();
include("../model/db_conn.php");

$error = "";

if (isset($_POST['submit'])) {

    $email = trim($_POST['email']);
    $pass  = trim($_POST['pass']);

    if ($email === "" || $pass === "") {
        $error = "All fields are required.";
    } else {

        // Fetch admin/manager from table
        $stmt = $conn->prepare("SELECT * FROM login WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc(); // use $user for clarity

            // Check password (hashed or plain text)
            if (password_verify($pass, $user['password']) || $pass === $user['password']) {
                
                $_SESSION['email'] = $user['email'];
                $_SESSION['role']  = $user['role']; // store role in session

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: addAirline.php");
                    exit;
                } elseif ($user['role'] === 'manager') {
                    header("Location: managerdemo.php");
                    exit;
                } else {
                    $error = "Your role is not recognized.";
                }

            } else {
                $error = "Incorrect password.";
            }

        } else {
            $error = "Email not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <link rel="stylesheet" href="component.css">
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="login">
    <form action="" method="post">
    <h3>Login Now</h3>
    <input type="email" name="email" placeholder="Enter your email" class="box" required>
    <input type="password" name="pass" placeholder="Enter your password" class="box" required>
    <input type="submit" value="Login Now" class="btn" name="submit">

    <?php if ($error != "") { ?>
        <p style="color:red;"><?php echo $error; ?></p>
    <?php } ?>

    <p>Don't have an account? <a href="register.php">Register now</a></p>
</form>

</section>

<?php include("../includes/footer.php"); ?>

</body>
</html>